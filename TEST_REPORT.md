# Test Report — Product Inventory Management System API

**Result: 18 passed, 0 failed, 116 assertions. Line coverage 100%.**

| | |
|---|---|
| Framework | Laravel 11.57.1, PHPUnit 11 |
| Test database | In-memory SQLite (`phpunit.xml`), rebuilt for every test with `RefreshDatabase` |
| Test cache | `array` store |
| Report date | 2026-11-07 |

## How to reproduce

```bash
php artisan test                                                        # local
./vendor/bin/sail test                                                  # Docker
./vendor/bin/sail exec -e XDEBUG_MODE=coverage laravel.test php artisan test --coverage
```

## Runs

| Environment | Command | Result | Duration |
|---|---|---|---|
| Local (PHP 8.4) | `php artisan test` | 18 passed (116 assertions) | 0.40s |
| Docker / Sail (PHP 8.4) | `XDEBUG_MODE=coverage php artisan test --coverage` | 18 passed (116 assertions), 100% coverage | 1.12s |

## Results by suite

### `tests/Feature/ProductApiTest.php`: endpoints (8 tests)

| Test | What it checks | Result |
|---|---|---|
| login issues token that grants access | Wrong password returns 422; correct login returns a bearer token that works on `/api/products` | ✅ |
| logout revokes the token | Logout returns 204, the token row is deleted, and reusing the token returns 401 | ✅ |
| guests cannot access products | List, create and delete without a token return 401 | ✅ |
| index paginates and formats products | `per_page`, `meta.total`, `links`, resource fields, category embedded | ✅ |
| index filters by category price and stock | Category, price range and stock range together; all three `stock_status` values, including the edge cases (0 units is `out_of_stock`, 1 and 10 units are `low_stock`, 11 and above is `in_stock`); an upper bound on its own; `max < min` returns 422 | ✅ |
| store validates and creates product with suppliers | Invalid input returns 422; valid input returns 201; mutator trims the name; accessor gives `stock_status`; suppliers attached | ✅ |
| show and update product | Show returns 200; partial update keeps the other fields; suppliers synced; unknown ID returns 404 | ✅ |
| destroy soft deletes product | Delete returns 204; `deleted_at` set; product hidden from show (404) and from the list | ✅ |

### `tests/Feature/CachingAndRateLimitTest.php`: bonus features (5 tests)

| Test | What it checks | Result |
|---|---|---|
| product list is served from cache | A raw insert that bypasses Eloquent stays hidden, which shows the cached response was served; different query parameters get separate cache entries | ✅ |
| create update and delete through api invalidate cache | Update, supplier-only update, create and delete through the API each clear the cache | ✅ |
| category rename invalidates cache | A renamed category appears in the product list straight away | ✅ |
| api routes are rate limited | `X-RateLimit-Limit: 60` header, remaining count goes down | ✅ |
| login is rate limited after five attempts | Attempts 1–5 return 422, attempt 6 returns 429 | ✅ |

### `tests/Feature/ApiDocumentationTest.php`: OpenAPI spec (2 tests)

| Test | What it checks | Result |
|---|---|---|
| openapi spec documents bearer auth and public login | `/docs/api.json` declares a bearer security scheme, applies it to every endpoint except `/login`, and lists the product routes | ✅ |
| docs are hidden outside local without permission | `/docs/api.json` returns 403 when not in `local` and the `viewApiDocs` gate does not allow access | ✅ |

### `tests/Feature/ProductRelationshipsTest.php`: models (1 test)

| Test | What it checks | Result |
|---|---|---|
| relationships and delete rules | `belongsTo`, `hasMany`, `belongsToMany` in both directions; decimal price cast; supplier links kept on soft delete and removed on hard delete; a category that still has products can't be deleted | ✅ |

### Laravel defaults (2 tests)

`Tests\Unit\ExampleTest`, `Tests\Feature\ExampleTest`: framework smoke tests. ✅

## Coverage

| Class | Lines |
|---|---|
| `Api\AuthController` | 100% |
| `Api\ProductController` | 100% |
| `LoginRequest`, `ProductIndexRequest`, `ProductRequest` | 100% |
| `ProductResource`, `CategoryResource`, `SupplierResource` | 100% |
| `Product`, `Category`, `Supplier`, `User` | 100% |
| `AppServiceProvider` | 100% |
| **Total** | **100%** |

## Mutation checks

To show the filter tests catch real mistakes, not just run the lines, each edge case was broken on purpose and the suite run again. Every change was caught, and the code was restored afterwards.

| Change made | Result |
|---|---|
| `out_of_stock`: `stock = 0` changed to `stock <= 1` | Test fails, change caught |
| `low_stock`: lower bound 1 changed to 0 | Test fails, change caught |
| `low_stock`: upper bound 10 changed to 9 | Test fails, change caught |
| `in_stock`: `> 10` changed to `>= 10` | Test fails, change caught |
| `logout`: token deletion removed | Logout test fails, change caught |
| Docs: bearer security setup removed from `AppServiceProvider` | Docs test fails, change caught |

## Checks outside the automated suite

These were checked by hand against the Docker stack (PostgreSQL 18, `database` cache store):

- All migrations and the seeder run cleanly on PostgreSQL 18.
- Login, then a filtered and paginated product list, return the expected data.
- Cache invalidation: a stock value was cached, changed with `PATCH`, and the next list request showed the new value immediately.
- Login rate limit: `422 ×5`, then `429`.
- `/docs/api` (OpenAPI UI) and `/docs/api.json` return 200 when `APP_ENV=local`.

## Known limits

- The automated tests run on SQLite, not PostgreSQL. Database-specific behaviour is only covered by the manual checks above.
- `GET /api/products/{id}` is not cached by design, because it is a primary-key lookup. The cache tests cover the list endpoint only.
