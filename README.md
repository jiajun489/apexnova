# Product Inventory Management System — REST API

REST API for products, categories and suppliers, built with Laravel 11.

**Features:** Product CRUD with filtering and pagination · Sanctum token auth · Form Requests · API Resources · soft deletes · feature tests (see [`TEST_REPORT.md`](TEST_REPORT.md))

## Setup with Docker (recommended)

You only need Docker Desktop or OrbStack.

```bash
git clone https://github.com/jiajun489/apexnova.git && cd apexnova
cp .env.example .env

# install PHP dependencies with a throwaway container (no local PHP needed)
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
  laravelsail/php84-composer:latest composer install --ignore-platform-reqs

./vendor/bin/sail up -d --wait
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
```

| Service | URL / port |
|---|---|
| API | http://localhost:8080/api |
| API docs (Swagger / OpenAPI) | http://localhost:8080/docs/api |
| PostgreSQL | `localhost:5436`, user `sail`, password `password`, database `laravel` |

If port 8080 or 5436 is already in use, change `APP_PORT` or `FORWARD_DB_PORT` in `.env`. If you change `FORWARD_DB_PORT`, set `DB_PORT` to the same value.

To stop the containers, run `./vendor/bin/sail down`. Add `-v` to also delete the database.

## Setup without Docker

Requires PHP 8.2+, Composer 2, and PostgreSQL or SQLite.

```bash
git clone https://github.com/jiajun489/apexnova.git && cd apexnova
composer install
cp .env.example .env
php artisan key:generate
```

Then choose a database in `.env`:

- **PostgreSQL:** set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` for your server, and create that database.
- **SQLite:** set `DB_CONNECTION=sqlite` and **delete the `DB_DATABASE` line**. Then run `touch database/database.sqlite`.

```bash
php artisan migrate --seed
php artisan serve        # API at http://127.0.0.1:8000/api, docs at http://127.0.0.1:8000/docs/api
```

## Sample data

`migrate --seed` creates 5 categories, 8 suppliers, 50 products, and this login:

| Email | Password |
|---|---|
| `test@example.com` | `password` |

To wipe the database and reseed, run `php artisan migrate:fresh --seed`. With Docker, prefix any command with `./vendor/bin/sail`.

## Try it

```bash
# 1. Log in to get a token
curl -X POST http://localhost:8080/api/login \
  -H "Accept: application/json" \
  -d email=test@example.com -d password=password

# 2. Call the API with the token
curl "http://localhost:8080/api/products?category_id=1&min_price=10&max_price=100" \
  -H "Accept: application/json" -H "Authorization: Bearer <token>"
```

All endpoints, parameters and response formats are documented at `/docs/api`. You can also send requests from that page.

## Run the tests

```bash
php artisan test          # or: ./vendor/bin/sail test
```

The tests use an in-memory SQLite database, so they don't touch your data. Full results are in [`TEST_REPORT.md`](TEST_REPORT.md).

## Note: Laravel 11 security advisories

Laravel 11 is no longer supported, and Composer blocks its final release (11.57.1) because of open security advisories. `composer.json` therefore ignores advisories for `laravel/framework` only, so `composer install` works. Upgrade to a supported Laravel version before using this in production.
