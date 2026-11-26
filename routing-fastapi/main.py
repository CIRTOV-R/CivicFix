from __future__ import annotations

from fastapi import FastAPI

from app.api.routes import router as routing_router
from app.core.config import get_settings

app = FastAPI(title="CivicFix Routing Service", version="1.0.0")


@app.get("/health")
async def health() -> dict[str, str | bool]:
    settings = get_settings()
    return {
        "status": "ok",
        "service": "routing",
        "osrm_configured": settings.osrm_configured,
    }


app.include_router(routing_router, prefix="/api/v1")
