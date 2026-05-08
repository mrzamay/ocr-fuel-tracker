# OCR Fuel Tracker

OCR Fuel Tracker is a fuel-expense tracker with receipt recognition. The project is split into a Laravel API, a Vue/Vite PWA frontend, a PostgreSQL database, an Nginx gateway, and a small FastAPI OCR service powered by Tesseract.

## Stack

- Backend: PHP 8.3, Laravel, Sanctum, PostgreSQL
- Frontend: Vue 3, Vite, Pinia, Vue Router, Chart.js, PWA support
- OCR: Python, FastAPI, Pillow, pytesseract, Tesseract `rus+eng`
- Runtime: Docker Compose with PHP-FPM, Nginx, PostgreSQL, and OCR containers

## Project Structure

```text
.
+-- docker/
|   +-- nginx/        # Nginx config for API and built frontend
|   +-- ocr/          # FastAPI OCR microservice
|   +-- php/          # PHP-FPM image for Laravel
+-- frontend/         # Vue/Vite PWA
+-- src/              # Laravel API application
+-- docker-compose.yml
```

## Quick Start

1. Create the Laravel environment file:

```bash
cp src/.env.example src/.env
```

2. Build and start containers:

```bash
docker compose up -d --build
```

3. Install backend dependencies and prepare Laravel:

```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan storage:link
```

4. Install and build the frontend:

```bash
cd frontend
npm install
npm run build
```

5. Open the application:

```text
http://localhost:8080
```

## Local Frontend Development

Run the Docker services first, then start Vite on the host:

```bash
cd frontend
npm install
npm run dev
```

The dev server runs on `http://localhost:5173` and proxies `/api` and `/storage` to the Nginx service on `http://localhost:8080`.

## API

Public endpoints:

- `POST /api/register`
- `POST /api/login`

Authenticated endpoints use a Sanctum bearer token:

- `POST /api/logout`
- `GET /api/user`
- `GET /api/records`
- `POST /api/records`
- `PUT /api/records/{id}`
- `DELETE /api/records/{id}`

## OCR Service

The OCR container exposes `POST /recognize` internally as `http://ocr:8000/recognize`. Laravel sends uploaded receipt images there, receives extracted `amount` and `volume`, and stores the result in `fuel_records`.

## Useful Commands

```bash
docker compose ps
docker compose logs -f app
docker compose logs -f ocr
docker compose exec app php artisan test
docker compose exec app php artisan migrate:fresh
cd frontend && npm run build
```

## Notes

- Keep real secrets in `src/.env`; commit only `src/.env.example`.
- Uploaded receipts are stored through Laravel's public disk. Run `php artisan storage:link` after first setup.
- If OCR returns no values, the record is still saved with `ocr_pending` so it can be corrected manually.
