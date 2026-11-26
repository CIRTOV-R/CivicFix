from __future__ import annotations

import secrets

from fastapi import APIRouter, Depends, Header, HTTPException, status

from app.core.config import Settings, get_settings
from app.schemas.routing import RoutingRequest, RoutingResponse
from app.services.osrm_engine import RoutingEngine, RoutingEngineError

router = APIRouter()


def verify_internal_token(
    x_internal_token: str | None = Header(default=None, alias="X-Internal-Token"),
    settings: Settings = Depends(get_settings),
) -> None:
    expected_token = settings.internal_api_token
    if x_internal_token is None or not expected_token:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="unauthorized")
    if not secrets.compare_digest(x_internal_token, expected_token):
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="unauthorized")


@router.post("/routing/calculate-optimal-crew", response_model=RoutingResponse)
async def calculate_optimal_crew(
    payload: RoutingRequest,
    settings: Settings = Depends(get_settings),
    _: None = Depends(verify_internal_token),
) -> RoutingResponse:
    engine = RoutingEngine(settings)
    try:
        return await engine.calculate(payload)
    except RoutingEngineError as exc:
        raise HTTPException(
            status_code=status.HTTP_502_BAD_GATEWAY,
            detail="routing engine unavailable",
        ) from exc
