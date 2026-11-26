from __future__ import annotations

import os
from dataclasses import dataclass, field
from functools import lru_cache

CITY: dict[str, object] = {
    "name": "Ciudad Guayana",
    "state": "Bolívar",
    "country": "Venezuela",
    "latitude": 8.296542,
    "longitude": -62.741285,
    "timezone": "America/Caracas",
}


@dataclass(frozen=True)
class Settings:
    internal_api_token: str
    osrm_base_url: str
    osrm_request_timeout: float
    caroni_split_longitude: float
    traffic_factor: float
    city_base_speed_kmh: float = 36.823
    city: dict[str, object] = field(default_factory=lambda: dict(CITY))

    def __post_init__(self) -> None:
        if self.osrm_request_timeout <= 0:
            raise ValueError("OSRM_REQUEST_TIMEOUT must be greater than zero")
        if self.traffic_factor <= 0:
            raise ValueError("TRAFFIC_FACTOR must be greater than zero")
        if self.city_base_speed_kmh <= 0:
            raise ValueError("city base speed must be greater than zero")
        if not -180.0 <= self.caroni_split_longitude <= 180.0:
            raise ValueError("CARONI_SPLIT_LONGITUDE must be between -180 and 180")

    @property
    def osrm_configured(self) -> bool:
        return bool(self.osrm_base_url)

    def travel_seconds(self, distance_meters: float) -> float:
        effective_speed_mps = (self.city_base_speed_kmh * 1000.0 / 3600.0) / self.traffic_factor
        return distance_meters / effective_speed_mps


def _env_float(name: str, default: float) -> float:
    raw_value = os.environ.get(name)
    if raw_value is None or raw_value.strip() == "":
        return default
    try:
        return float(raw_value)
    except ValueError as exc:
        raise ValueError(f"{name} must be a number") from exc


def _build_settings() -> Settings:
    return Settings(
        internal_api_token=os.environ.get("INTERNAL_API_TOKEN", "").strip(),
        osrm_base_url=os.environ.get("OSRM_BASE_URL", "").strip().rstrip("/"),
        osrm_request_timeout=_env_float("OSRM_REQUEST_TIMEOUT", 1.0),
        caroni_split_longitude=_env_float("CARONI_SPLIT_LONGITUDE", -62.715),
        traffic_factor=_env_float("TRAFFIC_FACTOR", 1.25),
    )


@lru_cache(maxsize=1)
def _cached_settings() -> Settings:
    return _build_settings()


def get_settings() -> Settings:
    return _cached_settings()


def reload_settings() -> Settings:
    _cached_settings.cache_clear()
    return get_settings()
