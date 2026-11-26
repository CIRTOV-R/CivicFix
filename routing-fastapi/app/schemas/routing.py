from __future__ import annotations

from typing import Literal

from pydantic import BaseModel, ConfigDict, Field


class Coordinate(BaseModel):
    model_config = ConfigDict(extra="forbid")

    latitude: float = Field(ge=-90.0, le=90.0)
    longitude: float = Field(ge=-180.0, le=180.0)


class CrewCandidate(Coordinate):
    crew_id: int = Field(ge=1)
    current_load: int = Field(ge=0)


class RoutingRequest(BaseModel):
    model_config = ConfigDict(extra="forbid")

    incident_id: int = Field(ge=1)
    incident_location: Coordinate
    specialty_required: str = Field(min_length=1)
    candidates: list[CrewCandidate] = Field(min_length=1)


class OptimalCrew(BaseModel):
    model_config = ConfigDict(extra="forbid")

    crew_id: int = Field(ge=1)
    distance_meters: float = Field(ge=0.0)
    duration_seconds: float = Field(ge=0.0)
    distance_km: float = Field(ge=0.0)
    eta_minutes: float = Field(ge=0.0)
    route_summary: str
    bridge_crossing_required: bool
    polyline: str


class RoutingResponse(BaseModel):
    model_config = ConfigDict(extra="forbid")

    status: Literal["success"] = "success"
    incident_id: int = Field(ge=1)
    optimal_crew: OptimalCrew
    evaluated_candidates_count: int = Field(ge=1)
