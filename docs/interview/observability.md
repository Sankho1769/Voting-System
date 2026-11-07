# VerityVote — Observability, Structured Logging, Health Probes & Telemetry

> **Document Classification:** Engineering Reference / Technical Architecture  
> **Phase:** 12 — Performance, Scalability, Observability & Distributed Systems  
> **Components:** `app/Core/Logger.php`, `app/Core/Metrics.php`, `/public/health/`  
> **Formats:** Structured JSON Lines (ndjson), OpenMetrics / Prometheus Telemetry

---

## 1. Architectural Strategy & Pillars of Observability

In mission-critical institutional voting, system transparency and rapid issue detection are paramount. VerityVote provides full observability across the three core pillars:
1. **Logs:** Correlation-ID traced structured JSON logs detailing events, execution durations, and error classifications.
2. **Metrics:** Real-time counters, gauges, and timing histograms exported via standard OpenMetrics/Prometheus formats.
3. **Health Checks:** Native Kubernetes / Docker container liveness (`/health/live`) and readiness (`/health/ready`) probes.

```mermaid
flowchart LR
    Client[Voter / Admin] --> Proxy[Reverse Proxy / Apache]
    Proxy -->|X-Correlation-ID| App[PHP Front Controller]
    App --> Logger[Structured JSON Logger]
    App --> Metrics[Metrics Collector]
    App --> Health[Health Probes]
    
    Logger -->|ndjson| LogFile[(storage/logs/verityvote-json.log)]
    Metrics -->|Prometheus Text| Scraping[/metrics Endpoint]
    Health -->|HTTP 200/503| Orchestrator[Kubelet / AWS ALB]
```

---

## 2. Structured JSON Logging Specification

Log lines are appended atomically in JSON Lines format (`storage/logs/verityvote-json-YYYY-MM-DD.log`):

```json
{
  "timestamp": "2026-11-06T20:34:52+05:30",
  "level": "INFO",
  "correlation_id": "req_8f1a2c9b4e5d6f70",
  "route": "/public/election?id=1",
  "method": "GET",
  "client_ip": "192.168.1.100",
  "message": "Public election detail accessed",
  "execution_time_ms": 14.80,
  "context": {
    "election_id": 1,
    "user_id": 42
  }
}
```

### Distributed Correlation Tracing
- `Logger::getCorrelationId()` automatically captures `HTTP_X_CORRELATION_ID` or `HTTP_X_REQUEST_ID` forwarded by an upstream API Gateway, CDN, or Load Balancer.
- If absent, it cryptographically generates a unique request token (`req_` + 16 hex characters).
- This correlation ID is passed to MariaDB audit logs (`audit_logs.correlation_id`), allowing instant end-to-end tracing of an entire transaction across web server, PHP execution, and database writes.

### Absolute Secret Redaction Guard
The logger strictly scrubs all sensitive parameters before persisting to disk or database:
- `password`, `temp_password`, `new_password`, `password_confirmation`
- `csrf_token`, `token`, `session_id`, `cookie`
- `authorization`, `http_authorization`, `bearer`, `metrics_token`, `secret`, `api_key`
- `candidate_id`, `candidate_choice`, `choice` (Guarantees ballot secrecy: **a voter's choice is NEVER logged anywhere on the server!**)
- Request routes are stripped of URL query parameters (`strtok($uri, '?')`) to eliminate credential persistence from accidental query-string inputs.

---

## 3. Telemetry & OpenMetrics Collector (`/metrics`)

The telemetry engine (`app/Core/Metrics.php`) tracks institutional activity without external dependencies:

```
# HELP verityvote_metrics Institutional Voting Telemetry
# TYPE verityvote_counter counter
verityvote_votes_submitted_total 904
verityvote_duplicate_votes_blocked_total 124
verityvote_cache_hits_total 15820
verityvote_cache_misses_total 142
verityvote_http_requests_total 21450
verityvote_auth_attempts_total 1205
verityvote_auth_failures_total 34
verityvote_auth_lockouts_total 2
verityvote_http_request_duration_ms_avg_ms 4.16
verityvote_http_request_duration_ms_max_ms 47.60
```

### Metrics Endpoint Security & Access Control
- **Header-Only Authentication**: Access to `/public/metrics` strictly requires an `Authorization: Bearer <token>` HTTP header.
- **Strict Query-String Rejection**: To protect against credential leaks in web access logs, proxy caches, and browser histories, URL query parameter authentication (`?token=...`) is strictly rejected with `HTTP 401 Unauthorized`, even if the token is valid.
- **Fail-Secure Architecture**: If the token is not defined in `VV_METRICS_TOKEN` or `config.php`, token-based access fails securely (returns 401).
- **Constant-Time Verification**: Token evaluation uses `hash_equals()` to prevent timing side-channel attacks.
- **Administrative Diagnostic Access**: Logged-in institutional administrators (`Auth::isLoggedIn() && Auth::isAdmin()`) can view Prometheus metrics directly in administrative dashboards.

---

## 4. Health Probes & Orchestrator Integration

### A. Liveness Probe (`/public/health/live` or `/public/health/live.php`)
- **Intent:** Verifies that Apache and PHP-FPM can accept connections and parse scripts.
- **HTTP Response:** `200 OK`
```json
{
  "status": "alive",
  "timestamp": "2026-11-06T20:34:47+05:30",
  "php_version": "8.2.12",
  "memory_usage_kb": 2048.0
}
```

### B. Readiness Probe (`/public/health/ready` or `/public/health/ready.php`)
- **Intent:** Verifies all subsystem dependencies before routing production traffic:
  1. MariaDB connection ping (`SELECT 1`).
  2. Critical tables exist (`users`, `elections`, `candidates`, `votes`, `election_participation`, `audit_logs`).
  3. Storage directories (`storage/logs`, `storage/cache`, `storage/uploads`) are writable.
  4. Memory usage sanity check.
- **HTTP Response:** `200 OK` when healthy; `503 Service Unavailable` if degraded or database disconnected.
```json
{
  "status": "ready",
  "checks": {
    "database": "ok",
    "schema_tables": "ok",
    "storage": "ok",
    "memory": {
      "used_kb": 2048.0,
      "status": "ok"
    }
  },
  "timestamp": "2026-11-06T20:35:12+05:30",
  "environment": "production"
}
```

---

## 5. Web Server Logging Hardening & Credential Protection (Phase 13)

### The Credential Leakage Vector
By default, standard web server access logs (such as Apache `combined` format) record the request line using `%r`, which logs `METHOD /path?query HTTP/1.1`. If an attacker, administrator, or misconfigured monitoring scraper passes credentials via URL (e.g. `/public/metrics?token=SECRET_VALUE`), the web server logs the plain-text secret to disk even if the application layer returns `401 Unauthorized`.

### Server-Level Hardened Access Logging
VerityVote specifies a hardened access log format (`verityvote_secure`):
```apache
LogFormat "%h %l %u %t \"%m %U %H\" %>s %b %D" verityvote_secure
CustomLog "logs/access.log" verityvote_secure
```
- `%m`: HTTP Method (`GET`, `POST`)
- `%U`: URL path strictly excluding query parameters (`/public/metrics`)
- `%H`: Protocol (`HTTP/1.1`)
- `%>s`: Response status code
- `%b`: Response body byte count
- `%D`: Request latency in microseconds
- Excludes `%r`, `%q`, `%{User-Agent}i`, and `%{Referer}i` to eliminate credential injection and header poisoning risks.

### Error Log Sanitization
```apache
ErrorLogFormat "[%{u}t] [%-m:%l] [pid %P:tid %T] %7F: %E: [client\ %a] %M"
```
Eliminates raw request lines and referers containing sensitive query strings from server error logs while retaining client IPs and diagnostic context.

### Application-Layer URI Sanitization
- `Logger::sanitizeUri($uri)` strips query strings before persisting route information into structured JSON logs and database audit logs.
- Verified by automated tests in `tests/Integration/ApacheLoggingSecurityTest.php`.
