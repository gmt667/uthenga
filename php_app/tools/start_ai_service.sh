#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
project_root="$(cd "$script_dir/../.." && pwd)"
config_file="$project_root/php_app/config.local.php"
venv_python="$project_root/ai_service/.venv/bin/python"

if [[ ! -f "$config_file" ]]; then
  printf 'Missing php_app/config.local.php. Configure the local application first.\n' >&2
  exit 1
fi
if [[ ! -x "$venv_python" ]]; then
  printf 'Missing ai_service/.venv. Install AI dependencies before starting the service.\n' >&2
  exit 1
fi

# Secrets remain in config.local.php and are passed only to the child process.
export UTHENGA_AI_SERVICE_TOKEN="$(/opt/lampp/bin/php -r '$c=include $argv[1]; echo (string)($c["TIE_FASTAPI_SERVICE_TOKEN"]??"");' "$config_file")"
export GROQ_API_KEY="$(/opt/lampp/bin/php -r '$c=include $argv[1]; echo (string)($c["TIE_GROQ_API_KEY"]??"");' "$config_file")"
export UTHENGA_AI_PROVIDER="groq"
export UTHENGA_AI_MODEL="$(/opt/lampp/bin/php -r '$c=include $argv[1]; echo (string)($c["TIE_AI_MODEL"]??"openai/gpt-oss-20b");' "$config_file")"

if [[ ${#UTHENGA_AI_SERVICE_TOKEN} -lt 32 || ${#GROQ_API_KEY} -lt 12 ]]; then
  printf 'FastAPI service token or Groq key is not configured in php_app/config.local.php.\n' >&2
  exit 1
fi

cd "$project_root/ai_service"
exec "$venv_python" -m uvicorn app.main:app --host 127.0.0.1 --port 8001
