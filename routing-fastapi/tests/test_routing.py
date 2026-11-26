from __future__ import annotations

from collections.abc import Iterator
from typing import Any

import pytest
from fastapi.testclient import TestClient

from app.core.config import reload_settings
from app.schemas.routing import Coordinate, RoutingRequest
from app.services.osrm_engine import RoutingEngine, RoutingEngineError
from app.services.road_graph import RoadGraph, haversine_meters
from main import app

TOKEN = "test-internal-token"
HEADERS = {"X-Internal-Token": TOKEN}
CALCULATE_URL = "/api/v1/routing/calculate-optimal-crew"


@pytest.fixture()
def client(monkeypatch: pytest.MonkeyPatch) -> Iterator[TestClient]:
    monkeypatch.setenv("INTERNAL_API_TOKEN", TOKEN)
    monkeypatch.setenv("OSRM_REQUEST_TIMEOUT", "0.05")
    for name in ("OSRM_BASE_URL", "TRAFFIC_FACTOR", "CARONI_SPLIT_LONGITUDE"):
        monkeypatch.delenv(name, raising=False)
    reload_settings()
    with TestClient(app) as test_client:
        yield test_client
    reload_settings()


def sample_payload() -> dict[str, Any]:
    return {
        "incident_id": 1045,
        "incident_location": {"latitude": 8.296542, "longitude": -62.741285},
        "specialty_required": "VIALIDAD",
        "candidates": [
            {
                "crew_id": 3,
                "latitude": 8.289120,
                "longitude": -62.756310,
                "current_load": 2,
            },
            {
                "crew_id": 7,
                "latitude": 8.312450,
                "longitude": -62.685410,
                "current_load": 1,
            },
        ],
    }


def test_health_is_public_and_reports_osrm_state(client: TestClient) -> None:
    response = client.get("/health")

    assert response.status_code == 200
    assert response.json() == {"status": "ok", "service": "routing", "osrm_configured": False}


def test_calculate_optimal_crew_requires_internal_token(client: TestClient) -> None:
    payload = sample_payload()

    missing_token = client.post(CALCULATE_URL, json=payload)
    wrong_token = client.post(
        CALCULATE_URL,
        json=payload,
        headers={"X-Internal-Token": "wrong-token"},
    )

    assert missing_token.status_code == 401
    assert wrong_token.status_code == 401


def test_valid_request_picks_closest_west_side_crew(client: TestClient) -> None:
    response = client.post(CALCULATE_URL, json=sample_payload(), headers=HEADERS)

    assert response.status_code == 200
    body = response.json()
    assert set(body) == {"status", "incident_id", "optimal_crew", "evaluated_candidates_count"}
    assert body["status"] == "success"
    assert body["incident_id"] == 1045
    assert body["evaluated_candidates_count"] == 2

    crew = body["optimal_crew"]
    assert set(crew) == {
        "crew_id",
        "distance_meters",
        "duration_seconds",
        "distance_km",
        "eta_minutes",
        "route_summary",
        "bridge_crossing_required",
        "polyline",
    }
    assert crew["crew_id"] == 3
    assert crew["distance_meters"] == 3150.4
    assert crew["duration_seconds"] == 385.0
    assert crew["distance_km"] == 3.15
    assert crew["eta_minutes"] == 6.4
    assert crew["bridge_crossing_required"] is False
    assert "Av. Guayana" in crew["route_summary"]
    assert crew["polyline"]


def test_bridge_crossing_true_for_east_side_candidate(client: TestClient) -> None:
    payload = sample_payload()
    payload["candidates"] = [payload["candidates"][1]]

    response = client.post(CALCULATE_URL, json=payload, headers=HEADERS)

    assert response.status_code == 200
    crew = response.json()["optimal_crew"]
    assert crew["crew_id"] == 7
    assert crew["bridge_crossing_required"] is True
    assert "Puente" in crew["route_summary"]
    assert crew["distance_meters"] > 9000


def test_validation_rejects_invalid_coordinates_and_empty_candidates(client: TestClient) -> None:
    invalid_latitude = sample_payload()
    invalid_latitude["incident_location"]["latitude"] = 91.0

    invalid_longitude = sample_payload()
    invalid_longitude["candidates"][0]["longitude"] = -181.0

    empty_candidates = sample_payload()
    empty_candidates["candidates"] = []

    for payload in (invalid_latitude, invalid_longitude, empty_candidates):
        response = client.post(CALCULATE_URL, json=payload, headers=HEADERS)
        assert response.status_code == 422


def test_engine_errors_return_502(client: TestClient, monkeypatch: pytest.MonkeyPatch) -> None:
    async def fail(_self: RoutingEngine, _request: RoutingRequest) -> None:
        raise RoutingEngineError("boom")

    monkeypatch.setattr(RoutingEngine, "calculate", fail)

    response = client.post(CALCULATE_URL, json=sample_payload(), headers=HEADERS)

    assert response.status_code == 502
    assert response.json() == {"detail": "routing engine unavailable"}


def test_road_graph_uses_bridge_for_short_east_west_trip() -> None:
    alta_vista = Coordinate(latitude=8.305500, longitude=-62.716500)
    san_felix = Coordinate(latitude=8.305500, longitude=-62.697500)

    straight_line_meters = haversine_meters(
        (alta_vista.latitude, alta_vista.longitude),
        (san_felix.latitude, san_felix.longitude),
    )
    distance_meters, _duration_seconds, _polyline, summary = RoadGraph().shortest_path(
        alta_vista, san_felix
    )

    assert straight_line_meters < 2500.0
    assert distance_meters > 9000.0
    assert "Puente" in summary
