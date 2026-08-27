# Uthenga React frontend

This is the incremental React/Vite/Tailwind presentation layer. PHP remains
the authority for the session cookie, CSRF, marketplace data, inventory,
bookings, payments, and deterministic TIE decisions.

## Development with XAMPP

```bash
cd frontend
npm install
npm run dev
```

Open `http://127.0.0.1:5173`. The Vite server proxies `/api/*` to the local
XAMPP application at `http://127.0.0.1/uthenga/*`; sign in through the PHP
application first so its same-host session cookie is available.

## Apache/XAMPP static build

```bash
cd frontend
VITE_BASE=/uthenga/frontend/ npm run build
```

Deploy the resulting `dist/` files to the Apache-served `php_app/frontend/`
directory, or run `../php_app/tools/deploy_frontend.sh`. Then open
`/uthenga/ai.php`. The app uses hash routes, so no Apache history-fallback
rule is needed. Set `VITE_API_ROOT` if the deployment path differs from
`/uthenga/api/tie/`.

React never connects to MySQL and sends `credentials: include` to PHP. All
writes carry the CSRF token returned only by the authenticated PHP bootstrap
endpoint.
