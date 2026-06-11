# Fleet Command System — Deployment Guide

Remote ADB-equivalent fleet control for owned Android devices. Commander sends privileged commands over the internet using `X-Commander-Token`; the Laravel orchestrator dispatches tasks to Agent apps; the admin dashboard requires `X-Operator-Key` in addition to Sanctum login.

## Architecture

```mermaid
flowchart LR
    Commander[Fleet Commander App] -->|X-Commander-Token| API[Laravel /v1/fleet/*]
    Dashboard[Admin Dashboard] -->|Sanctum + X-Operator-Key| AdminAPI[/api/admin/fleet/*]
    API --> Orchestrator[FleetOrchestrator]
    Orchestrator --> Agent1[Fleet Agent]
    Orchestrator --> Agent2[Fleet Agent]
    Agent1 -->|poll + HTTPS| API
```

## Environment variables

Add to Forge `.env` on `api.eisbridge.com` and `sandbox.eisbridge.com`:

```env
FLEET_COMMANDER_TOKEN=generate_a_long_random_secret
FLEET_OPERATOR_KEY=generate_a_different_long_secret
FLEET_TASK_TIMEOUT_SEC=60
FLEET_POLL_INTERVAL_SEC=15
```

Generate secrets:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

## Forge deploy steps

1. Push this branch to GitHub.
2. On each API Forge site, add the fleet env vars above.
3. Run deploy script (`deploy/forge-deploy-api.sh` or sandbox variant) — migrations create `fleet_agents`, `fleet_tasks`, `fleet_task_results`.
4. Confirm routes:

```bash
curl -s https://sandbox.eisbridge.com/v1/fleet/agents \
  -H "X-Commander-Token: YOUR_COMMANDER_TOKEN"
```

5. Install **Fleet Agent** APK on each tablet (Device Owner provisioning required for reboot/silent install).
6. Install **Fleet Commander** APK on operator phone; enter API URL and commander token.

## Building Android APKs

Requires Android SDK and JDK 17:

```bash
cd apps/fleet-agent
gradle wrapper
./gradlew assembleRelease

cd ../fleet-commander
gradle wrapper
./gradlew assembleRelease
```

Release APK paths:

- `apps/fleet-agent/app/build/outputs/apk/release/app-release-unsigned.apk`
- `apps/fleet-commander/app/build/outputs/apk/release/app-release-unsigned.apk`

Sign APKs with your release keystore before fleet rollout.

## API reference

| Endpoint | Auth | Purpose |
|----------|------|---------|
| `POST /v1/fleet/agents/register` | none | Agent registration (returns token once) |
| `POST /v1/fleet/agents/heartbeat` | X-Agent-Token | Agent heartbeat |
| `GET /v1/fleet/agents/me/pending-tasks` | X-Agent-Token | Poll pending commands |
| `POST /v1/fleet/agents/me/task-results/{id}` | X-Agent-Token | Submit command result |
| `GET /v1/fleet/agents` | Commander or Operator | List agents |
| `POST /v1/fleet/tasks` | Commander, Agent (self), or Operator | Create task |
| `GET /v1/fleet/tasks/{id}` | Commander or Operator | Aggregated results |
| `GET /api/admin/fleet/*` | Sanctum + X-Operator-Key | Dashboard fleet control |

### Task targets

```json
{ "command": "reboot", "targets": "tablet-001", "payload": {} }
{ "command": "device-status", "targets": ["a1", "a2"], "payload": {} }
{ "command": "clear-cache", "targets": "ALL", "payload": { "package": "ph.bai.kahero" } }
```

## Agent local HTTPS endpoints

When reachable (LAN, Tailscale, or reverse tunnel), agents expose:

- `POST /execute-shell`
- `POST /reboot`
- `POST /install-apk`
- `POST /clear-cache`
- `POST /launch-app`
- `POST /stop-app`
- `POST /pull-logs`
- `POST /device-status`

All require header `X-Agent-Token`.

## Tests

```bash
cd api
php artisan test --filter=Fleet
```

## Device Owner provisioning

Reboot and silent package operations require Device Owner on the Agent app. Provision via:

```bash
adb shell dpm set-device-owner com.webshoppe.fleetagent/.DeviceAdminReceiver
```

Factory-reset devices with no accounts present, or use your existing MDM enrollment flow.
