from __future__ import annotations

from dataclasses import dataclass

import httpx

from app.core.config import Settings
from app.schemas.routing import Coordinate, CrewCandidate, OptimalCrew, RoutingRequest, RoutingResponse
from app.services.road_graph import RoadGraph, RoadGraphError, encode_polyline


class RoutingEngineError(Exception):
    pass


@dataclass(frozen=True)
class RouteResult:
    distance_meters: float
    duration_seconds: float
    polyline: str
    summary: str


@dataclass(frozen=True)
class CandidateEvaluation:
    candidate: CrewCandidate
    distance_meters: float
    duration_seconds: float
    route: RouteResult | None = None

    @property
    def score(self) -> float:
        return self.duration_seconds + self.candidate.current_load * 30.0 + self.distance_meters / 100000.0


@dataclass(frozen=True)
class TableResult:
    distances: tuple[float, ...]
    durations: tuple[float, ...]


class RoutingEngine:
    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self.road_graph = RoadGraph(settings)

    async def calculate(self, request: RoutingRequest) -> RoutingResponse:
        try:
            table = await self._request_table(request) if self.settings.osrm_configured else None
            if table is None:
                evaluations = [
                    self._evaluate_with_graph(request.incident_location, candidate)
                    for candidate in request.candidates
                ]
            else:
                evaluations = [
                    CandidateEvaluation(candidate, table.distances[index], table.durations[index])
                    for index, candidate in enumerate(request.candidates)
                ]

            winner = min(evaluations, key=lambda evaluation: evaluation.score)
            route = winner.route
            if route is None:
                try:
                    route = await self._request_route(request.incident_location, winner.candidate)
                except RoutingEngineError:
                    route = self._route_with_graph(request.incident_location, winner.candidate)

            crossing_required = self._bridge_crossing_required(request.incident_location, winner.candidate)
            summary = self._enrich_summary(
                route.summary,
                request.incident_location,
                winner.candidate,
                crossing_required,
            )

            return RoutingResponse(
                incident_id=request.incident_id,
                optimal_crew=OptimalCrew(
                    crew_id=winner.candidate.crew_id,
                    distance_meters=round(route.distance_meters, 2),
                    duration_seconds=round(route.duration_seconds, 1),
                    distance_km=round(route.distance_meters / 1000.0, 2),
                    eta_minutes=round(route.duration_seconds / 60.0, 1),
                    route_summary=summary,
                    bridge_crossing_required=crossing_required,
                    polyline=route.polyline,
                ),
                evaluated_candidates_count=len(request.candidates),
            )
        except RoutingEngineError:
            raise
        except Exception as exc:
            raise RoutingEngineError("routing engine failed") from exc

    def _evaluate_with_graph(self, incident: Coordinate, candidate: CrewCandidate) -> CandidateEvaluation:
        route = self._route_with_graph(incident, candidate)
        return CandidateEvaluation(candidate, route.distance_meters, route.duration_seconds, route)

    def _route_with_graph(self, incident: Coordinate, candidate: CrewCandidate) -> RouteResult:
        try:
            distance_meters, duration_seconds, polyline, summary = self.road_graph.shortest_path(
                incident, candidate
            )
        except RoadGraphError as exc:
            raise RoutingEngineError(str(exc)) from exc
        return RouteResult(distance_meters, duration_seconds, polyline, summary)

    def _client(self) -> httpx.AsyncClient:
        return httpx.AsyncClient(timeout=self.settings.osrm_request_timeout)

    def _coordinate_for_osrm(self, coord: Coordinate) -> str:
        return f"{coord.longitude:.6f},{coord.latitude:.6f}"

    def _parse_matrix(self, values: object, expected_length: int) -> tuple[float, ...] | None:
        if not isinstance(values, list) or len(values) != expected_length:
            return None
        parsed: list[float] = []
        for value in values:
            if isinstance(value, bool) or not isinstance(value, (int, float)):
                return None
            number = float(value)
            if number < 0:
                return None
            parsed.append(number)
        return tuple(parsed)

    async def _request_table(self, request: RoutingRequest) -> TableResult | None:
        coordinates = [self._coordinate_for_osrm(request.incident_location)]
        coordinates.extend(self._coordinate_for_osrm(candidate) for candidate in request.candidates)
        url = f"{self.settings.osrm_base_url}/table/v1/driving/{';'.join(coordinates)}"
        try:
            async with self._client() as client:
                response = await client.get(url, params={"annotations": "distance,duration"})
            response.raise_for_status()
            payload = response.json()
            distances = self._parse_matrix(payload["distances"][0][1:], len(request.candidates))
            durations = self._parse_matrix(payload["durations"][0][1:], len(request.candidates))
        except (httpx.HTTPError, KeyError, IndexError, TypeError, ValueError):
            return None
        if distances is None or durations is None:
            return None
        return TableResult(distances, durations)

    async def _request_route(self, incident: Coordinate, candidate: CrewCandidate) -> RouteResult:
        coordinates = f"{self._coordinate_for_osrm(incident)};{self._coordinate_for_osrm(candidate)}"
        url = f"{self.settings.osrm_base_url}/route/v1/driving/{coordinates}"
        try:
            async with self._client() as client:
                response = await client.get(
                    url,
                    params={"overview": "full", "geometries": "geojson"},
                )
            response.raise_for_status()
            route = response.json()["routes"][0]
            distance_meters = float(route["distance"])
            duration_seconds = float(route["duration"])
            points: list[tuple[float, float]] = []
            for entry in route["geometry"]["coordinates"]:
                if len(entry) >= 2:
                    points.append((float(entry[1]), float(entry[0])))
            if not points:
                raise ValueError("empty route geometry")
            summary = route.get("legs", [{}])[0].get("summary") or "Ruta óptima por red vial"
        except (httpx.HTTPError, KeyError, IndexError, TypeError, ValueError) as exc:
            raise RoutingEngineError("OSRM route request failed") from exc
        return RouteResult(
            round(distance_meters, 2),
            round(duration_seconds, 1),
            encode_polyline(points),
            str(summary),
        )

    def _bridge_crossing_required(self, incident: Coordinate, candidate: CrewCandidate) -> bool:
        split_longitude = self.settings.caroni_split_longitude
        return (incident.longitude > split_longitude) != (candidate.longitude > split_longitude)

    def _enrich_summary(
        self,
        summary: str,
        incident: Coordinate,
        candidate: CrewCandidate,
        crossing_required: bool,
    ) -> str:
        if not crossing_required:
            return summary
        bridge_name = self.road_graph.nearest_bridge_name(incident, candidate)
        if bridge_name in summary:
            return summary
        return f"{summary} - Cruce por {bridge_name}"
