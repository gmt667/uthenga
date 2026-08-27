# Uthenga AI service

This FastAPI service is an **internal AI orchestration boundary**, not a
marketplace backend. It must not receive browser traffic directly in
production, connect to MySQL, or initiate bookings, inventory changes, or
payments.

## Local run

```bash
cd ai_service
python3 -m venv .venv
. .venv/bin/activate
pip install -r requirements.txt
export UTHENGA_AI_SERVICE_TOKEN='use-a-new-random-local-token'
uvicorn app.main:app --host 127.0.0.1 --port 8001 --reload
```

For this Uthenga workspace, use `php_app/tools/start_ai_service.sh` instead.
It reads only the necessary local values from `php_app/config.local.php` and
passes them to the FastAPI process without creating a second secrets file.

`GET /health` is safe to use for readiness checks. `POST /v1/conversation`
requires the internal bearer token and accepts only the allowlisted travel
tools. Set `UTHENGA_AI_PROVIDER=groq` and `GROQ_API_KEY` in the service
environment to enable the Groq adapter. The provider receives only minimised,
PHP-approved evidence; it cannot access MySQL, booking, payment, or inventory.

For PHP tool execution, configure the same long random `TIE_AI_TOOL_TOKEN` in
PHP and `UTHENGA_PHP_TOOL_TOKEN` here, plus a separate PHP-only
`TIE_AI_CAPABILITY_SECRET`. PHP issues a 60-second user-scoped capability at
`/api/tie/ai/capability.php`; FastAPI can only forward that capability to the
read-only `/api/tie/ai/tools.php` allowlist.
