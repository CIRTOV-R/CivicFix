# CivicFix Routing Service

Stateless FastAPI microservice that selects the optimal crew for an incident. It never reads the Laravel database and keeps no sessions.

## Run locally

```bash
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
INTERNAL_API_TOKEN=local-secret uvicorn main:app --reload --port 8000
```

Run tests with `python3 -m pytest -q`.

## Environment

| Variable | Default | Purpose |
| --- | --- | --- |
| `INTERNAL_API_TOKEN` | empty | Required value of `X-Internal-Token`; empty disables all calculate calls |
| `OSRM_BASE_URL` | empty | Base URL of an OSRM server; empty enables the embedded road graph |
| `OSRM_REQUEST_TIMEOUT` | `1.0` | Per-request HTTP timeout in seconds |
| `CARONI_SPLIT_LONGITUDE` | `-62.715` | Longitude split used to detect a Caroní bridge crossing |
| `TRAFFIC_FACTOR` | `1.25` | Multiplier applied to city travel time |

## Endpoints

- `GET /health` — public; returns `{"status":"ok","service":"routing","osrm_configured":bool}`.
- `POST /api/v1/routing/calculate-optimal-crew` — requires `X-Internal-Token`; accepts the incident, required specialty, and candidate crews; returns the optimal crew with distance, ETA, summary, bridge-crossing flag, and encoded polyline.

Invalid coordinates, empty candidate lists, or malformed payloads return `422`. A missing or wrong token returns `401`. Internal engine failures return `502 {"detail":"routing engine unavailable"}`.

## Routing behavior

With `OSRM_BASE_URL`, the service queries the OSRM table for all candidates, then requests a full route for the winner. Timeouts, HTTP errors, or malformed OSRM responses fall back to the embedded Ciudad Guayana graph, where Puerto Ordaz and San Félix are connected only by the Caroní, Macagua, and Angosturita bridges. The graph uses Dijkstra with city speeds and the traffic factor, and returns a Google encoded polyline.
