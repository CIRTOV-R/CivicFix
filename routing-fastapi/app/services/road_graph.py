from __future__ import annotations

import heapq
import math
from collections.abc import Iterable
from dataclasses import dataclass
from typing import Literal

from app.core.config import Settings, get_settings
from app.schemas.routing import Coordinate

GeoPoint = tuple[float, float]
BridgeSide = Literal["west", "east"]
EARTH_RADIUS_METERS = 6371008.8


class RoadGraphError(Exception):
    pass


@dataclass(frozen=True)
class GraphNode:
    id: str
    name: str
    latitude: float
    longitude: float
    side: BridgeSide

    @property
    def point(self) -> GeoPoint:
        return (self.latitude, self.longitude)


@dataclass(frozen=True)
class GraphSegment:
    source: str
    target: str
    distance_meters: float
    name: str
    bridge_name: str | None = None


@dataclass(frozen=True)
class GraphEdge:
    target: str
    distance_meters: float
    name: str
    bridge_name: str | None = None


@dataclass(frozen=True)
class Bridge:
    name: str
    west_node_id: str
    east_node_id: str
    latitude: float
    longitude: float

    @property
    def point(self) -> GeoPoint:
        return (self.latitude, self.longitude)


NODES: tuple[GraphNode, ...] = (
    GraphNode("redoma_chilemex", "Redoma de Chilemex", 8.296542, -62.741285, "west"),
    GraphNode("zona_industrial_unare", "Zona Industrial Unare", 8.289120, -62.756310, "west"),
    GraphNode("alta_vista", "Alta Vista", 8.305500, -62.716500, "west"),
    GraphNode("centro_civico_puerto_ordaz", "Centro Cívico Puerto Ordaz", 8.301000, -62.721000, "west"),
    GraphNode("los_olivos", "Los Olivos", 8.312000, -62.723500, "west"),
    GraphNode("paseo_caroni", "Paseo Caroní", 8.293000, -62.723000, "west"),
    GraphNode("puente_caroni_west", "Puente Caroní, margen occidental", 8.307500, -62.716800, "west"),
    GraphNode("puente_caroni_east", "Puente Caroní, margen oriental", 8.310000, -62.690000, "east"),
    GraphNode("puente_macagua_west", "Puente Macagua, margen occidental", 8.285000, -62.722000, "west"),
    GraphNode("puente_macagua_east", "Puente Macagua, margen oriental", 8.284500, -62.690000, "east"),
    GraphNode("puente_angosturita_west", "Puente Angosturita, margen occidental", 8.276000, -62.729000, "west"),
    GraphNode("puente_angosturita_east", "Puente Angosturita, margen oriental", 8.275500, -62.691000, "east"),
    GraphNode("san_felix", "San Félix", 8.305500, -62.697500, "east"),
    GraphNode("los_sabanales", "Los Sabanales", 8.317000, -62.650000, "east"),
)

SEGMENTS: tuple[GraphSegment, ...] = (
    GraphSegment("redoma_chilemex", "zona_industrial_unare", 3150.40, "Av. Guayana"),
    GraphSegment("redoma_chilemex", "alta_vista", 1350.0, "Av. Guayana"),
    GraphSegment("redoma_chilemex", "paseo_caroni", 1500.0, "Av. Guayana"),
    GraphSegment("alta_vista", "los_olivos", 1400.0, "Av. Guayana"),
    GraphSegment("los_olivos", "centro_civico_puerto_ordaz", 1800.0, "Av. Guayana"),
    GraphSegment("paseo_caroni", "centro_civico_puerto_ordaz", 2700.0, "Av. Guayana"),
    GraphSegment("paseo_caroni", "puente_macagua_west", 1800.0, "Av. Guayana"),
    GraphSegment("centro_civico_puerto_ordaz", "puente_caroni_west", 1400.0, "Av. Guayana"),
    GraphSegment("zona_industrial_unare", "puente_angosturita_west", 2400.0, "Vía Unare"),
    GraphSegment("paseo_caroni", "puente_angosturita_west", 2200.0, "Vía Unare"),
    GraphSegment("puente_caroni_west", "puente_caroni_east", 3000.0, "Puente Caroní", "Puente Caroní"),
    GraphSegment("puente_macagua_west", "puente_macagua_east", 3600.0, "Puente Macagua", "Puente Macagua"),
    GraphSegment("puente_angosturita_west", "puente_angosturita_east", 4200.0, "Puente Angosturita", "Puente Angosturita"),
    GraphSegment("puente_caroni_east", "san_felix", 2400.0, "Av. Guayana"),
    GraphSegment("puente_macagua_east", "san_felix", 2800.0, "Av. Guayana"),
    GraphSegment("puente_angosturita_east", "san_felix", 3200.0, "Av. Guayana"),
    GraphSegment("san_felix", "los_sabanales", 1800.0, "Av. Guayana"),
)

BRIDGE_PAIRS: tuple[tuple[str, str, str], ...] = (
    ("Puente Caroní", "puente_caroni_west", "puente_caroni_east"),
    ("Puente Macagua", "puente_macagua_west", "puente_macagua_east"),
    ("Puente Angosturita", "puente_angosturita_west", "puente_angosturita_east"),
)


def haversine_meters(first: GeoPoint, second: GeoPoint) -> float:
    first_lat, first_lon = map(math.radians, first)
    second_lat, second_lon = map(math.radians, second)
    delta_lat = second_lat - first_lat
    delta_lon = second_lon - first_lon
    haversine = (
        math.sin(delta_lat / 2.0) ** 2
        + math.cos(first_lat) * math.cos(second_lat) * math.sin(delta_lon / 2.0) ** 2
    )
    return 2.0 * EARTH_RADIUS_METERS * math.asin(math.sqrt(haversine))


def _encode_value(value: int) -> str:
    encoded = ~(value << 1) if value < 0 else value << 1
    chunks: list[str] = []
    while encoded >= 0x20:
        chunks.append(chr((0x20 | (encoded & 0x1F)) + 63))
        encoded >>= 5
    chunks.append(chr(encoded + 63))
    return "".join(chunks)


def encode_polyline(points: Iterable[GeoPoint]) -> str:
    encoded_parts: list[str] = []
    previous_latitude = 0
    previous_longitude = 0
    for latitude, longitude in points:
        scaled_latitude = int(round(latitude * 100000))
        scaled_longitude = int(round(longitude * 100000))
        encoded_parts.append(_encode_value(scaled_latitude - previous_latitude))
        encoded_parts.append(_encode_value(scaled_longitude - previous_longitude))
        previous_latitude = scaled_latitude
        previous_longitude = scaled_longitude
    return "".join(encoded_parts)


def _as_point(coord: Coordinate | GeoPoint) -> GeoPoint:
    if isinstance(coord, tuple):
        return coord
    return (coord.latitude, coord.longitude)


class RoadGraph:
    def __init__(self, settings: Settings | None = None) -> None:
        self.settings = settings or get_settings()
        self.nodes: dict[str, GraphNode] = {node.id: node for node in NODES}
        self.adjacency: dict[str, list[GraphEdge]] = {node_id: [] for node_id in self.nodes}
        for segment in SEGMENTS:
            self.adjacency[segment.source].append(
                GraphEdge(segment.target, segment.distance_meters, segment.name, segment.bridge_name)
            )
            self.adjacency[segment.target].append(
                GraphEdge(segment.source, segment.distance_meters, segment.name, segment.bridge_name)
            )
        bridges: list[Bridge] = []
        for name, west_node_id, east_node_id in BRIDGE_PAIRS:
            west_node = self.nodes[west_node_id]
            east_node = self.nodes[east_node_id]
            bridges.append(
                Bridge(
                    name,
                    west_node_id,
                    east_node_id,
                    (west_node.latitude + east_node.latitude) / 2.0,
                    (west_node.longitude + east_node.longitude) / 2.0,
                )
            )
        self.bridges: tuple[Bridge, ...] = tuple(bridges)

    def nearest_node(self, coord: Coordinate | GeoPoint) -> str:
        point = _as_point(coord)
        return min(self.nodes.values(), key=lambda node: haversine_meters(point, node.point)).id

    def nearest_bridge_name(self, start: Coordinate | GeoPoint, end: Coordinate | GeoPoint) -> str:
        start_latitude, start_longitude = _as_point(start)
        end_latitude, end_longitude = _as_point(end)
        midpoint: GeoPoint = (
            (start_latitude + end_latitude) / 2.0,
            (start_longitude + end_longitude) / 2.0,
        )
        return min(self.bridges, key=lambda bridge: haversine_meters(midpoint, bridge.point)).name

    def shortest_path(self, start: Coordinate, end: Coordinate) -> tuple[float, float, str, str]:
        start_id = self.nearest_node(start)
        end_id = self.nearest_node(end)
        path_ids, graph_distance, edges = self._dijkstra(start_id, end_id)
        start_point = _as_point(start)
        end_point = _as_point(end)
        access_distance = haversine_meters(start_point, self.nodes[start_id].point)
        access_distance += haversine_meters(self.nodes[end_id].point, end_point)
        total_distance = graph_distance + access_distance

        points: list[GeoPoint] = [start_point]
        for node_id in path_ids:
            node_point = self.nodes[node_id].point
            if points[-1] != node_point:
                points.append(node_point)
        if points[-1] != end_point:
            points.append(end_point)
        if len(points) == 1:
            points.append(end_point)

        duration_seconds = self.settings.travel_seconds(total_distance)
        summary = self._build_summary(edges, self.nodes[end_id].name)
        return (
            round(total_distance, 2),
            round(duration_seconds, 1),
            encode_polyline(points),
            summary,
        )

    def _dijkstra(self, start_id: str, end_id: str) -> tuple[list[str], float, list[GraphEdge]]:
        distances: dict[str, float] = {node_id: math.inf for node_id in self.nodes}
        previous: dict[str, tuple[str, GraphEdge]] = {}
        distances[start_id] = 0.0
        heap: list[tuple[float, str]] = [(0.0, start_id)]

        while heap:
            current_distance, node_id = heapq.heappop(heap)
            if current_distance > distances[node_id]:
                continue
            if node_id == end_id:
                break
            for edge in self.adjacency[node_id]:
                candidate_distance = current_distance + edge.distance_meters
                if candidate_distance < distances.get(edge.target, math.inf):
                    distances[edge.target] = candidate_distance
                    previous[edge.target] = (node_id, edge)
                    heapq.heappush(heap, (candidate_distance, edge.target))

        if math.isinf(distances[end_id]):
            raise RoadGraphError(f"no road path from {start_id} to {end_id}")

        path_ids = [end_id]
        edges: list[GraphEdge] = []
        while path_ids[-1] != start_id:
            current_node = path_ids[-1]
            if current_node not in previous:
                raise RoadGraphError(f"incomplete road path from {start_id} to {end_id}")
            previous_node, edge = previous[current_node]
            edges.append(edge)
            path_ids.append(previous_node)
        path_ids.reverse()
        edges.reverse()
        return path_ids, distances[end_id], edges

    def _build_summary(self, edges: list[GraphEdge], destination_name: str) -> str:
        road_names: list[str] = []
        for edge in edges:
            if edge.bridge_name is None and edge.name not in road_names:
                road_names.append(edge.name)
        road_name = road_names[0] if road_names else "red vial"
        bridge_names = [edge.bridge_name for edge in edges if edge.bridge_name is not None]
        if bridge_names:
            return f"Vía {road_name} con cruce por {bridge_names[0]} hacia {destination_name}"
        return f"Vía {road_name} hacia {destination_name}"
