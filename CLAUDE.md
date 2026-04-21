# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Football betting tickets REST API backend built with **Laravel 12** (PHP 8.2+). The system aggregates odds from multiple external APIs, lets authenticated users create betting tickets, and tracks sync operations. Frontend assets are bundled via Vite 7 + Tailwind CSS 4.

## Commands

### Initial Setup
```bash
composer setup        # Full first-time setup (installs deps, migrates, seeds, generates keys)
```

### Development
```bash
composer dev          # Start all services concurrently: artisan serve, queue:listen, pail (logs), Vite dev server
npm run dev           # Vite dev server only
npm run build         # Build frontend assets
```

### Testing
```bash
composer test         # php artisan config:clear && php artisan test
php artisan test --filter=TestClassName   # Run a single test class
```

### Linting
```bash
composer lint-php     # Laravel Pint (PSR-12 style)
```

### Database
```bash
php artisan migrate
php artisan migrate:fresh --seed          # Reset and reseed
php artisan db:seed --class=OddsSourceSeeder
php artisan db:seed --class=SportsMatchesSeeder
```

### Odds Sync
```bash
php artisan odds:sync                     # Sync all active sources
php artisan odds:sync --source=1          # Sync a specific source by ID
php artisan odds:sync --sample            # Use sample/demo data
php artisan odds:sync --force             # Force sync ignoring rate limits
```

## Architecture

### Authentication
JWT tokens via `tymon/jwt-auth` are the primary API guard. Laravel Sanctum is also configured. `User` model implements `JWTSubject`. All protected routes use `auth:api` middleware and expect a `Bearer` token in the `Authorization` header.

### Odds Aggregation
Service layer in `app/Services/`:
- `OddsApiInterface` — contract all providers must implement
- `BaseOddsApiService` — shared normalisation logic (extend this to add new providers)
- `TheOddsApiService`, `OddsApiIoService`, `ApiFootballService` — concrete provider implementations
- `SyncOdds` artisan command orchestrates sync, writes audit records to `odds_sync_logs`

### Key Models & Relationships
| Model | Table | Notes |
|---|---|---|
| `User` | `users` | JWT auth subject |
| `SportsMatch` | `sports_matches` | Has `external_id`; statuses: scheduled/live/finished/cancelled |
| `OddsSource` | `odds_sources` | API provider config (enabled/disabled) |
| `MatchOdd` | `match_odds` | Unique per match + source + bookmaker |
| `OddsSyncLog` | `odds_sync_logs` | Audit trail for every sync run |
| `Ticket` | `tickets` | Bets stored as JSON array; computed `total_odds`, `potential_winnings` |

### API Routes (`routes/api.php`)
Public: `POST /api/register`, `POST /api/login`, `POST /api/forgot-password`, `POST /api/reset-password`

Protected (JWT): `/api/user`, `/api/change-password`, `/api/odds`, `/api/odds/{id}`, `/api/leagues`, `/api/bookmakers`, `/api/tickets` (full CRUD), `/api/tickets-statistics`

### Testing Environment
PHPUnit tests run against an in-memory SQLite database (configured in `phpunit.xml`). The production app defaults to MySQL (`betting-tickets` database).

### CORS
`config/cors.php` allows `localhost:5173` (Vite), `localhost:3000`, and `riverseainsurance.loc`.

## Environment Variables
Copy `.env.example` to `.env`. Key variables beyond standard Laravel:

```
ODDS_API_KEY=          # odds-api.io
THE_ODDS_API_KEY=      # the-odds-api.com
API_FOOTBALL_KEY=      # api-football.com
API_FOOTBALL_PLAN=free
ODDS_API_IO_PLAN=free
JWT_SECRET=            # generated during composer setup
```

### Fontend
path: /var/www/betting-tickets
