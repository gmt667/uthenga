#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
project_root="$(cd "$script_dir/../.." && pwd)"

cd "$project_root/frontend"
VITE_BASE="/uthenga/frontend/" npm run build
manifest="$project_root/frontend/dist/.vite/manifest.json"
test -s "$manifest"
test -s "$project_root/frontend/dist/index.html"

# The Vite manifest is the only authority for deployable hashed assets. Keep
# Apache's static directory an exact mirror so retired bundles cannot continue
# calling removed APIs from a stale bookmark or service worker cache.
mkdir -p "$project_root/php_app/frontend"
rsync --archive --delete "$project_root/frontend/dist/" "$project_root/php_app/frontend/"
printf 'React assets deployed to php_app/frontend/. Open /uthenga/ai.php.\n'
