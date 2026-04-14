# Betting Tickets Backend API

A Laravel 12 REST API for a football betting tickets application with a robust odds aggregation system that pulls real-time odds from multiple API sources.

## Features

- ✅ User authentication with JWT tokens (Laravel Sanctum)
- ✅ Remember me functionality (30-day token persistence)
- ✅ Football odds aggregation from the-odds-api.com
- ✅ Comprehensive database schema for matches, odds, and data sources
- ✅ Sync service for automated odds updates
- ✅ RESTful API endpoints for odds browsing
- ✅ User-friendly validation error messages
- ✅ CORS support for front-end integration
- ✅ Database seeders for quick setup

## Tech Stack

- **Framework**: Laravel 12
- **Authentication**: Laravel Sanctum (Token-based API)
- **Database**: SQLite (configurable to MySQL/PostgreSQL)
- **HTTP Client**: Laravel Http (for API requests)
- **ORM**: Eloquent

## Quick Start

### Prerequisites
- PHP 8.2+
- Composer

### Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=OddsSourceSeeder
php artisan db:seed --class=SportsMatchesSeeder
php artisan serve
```

The API will be available at `http://localhost:8000`

## Getting the-odds-api.com API Key

1. Visit [the-odds-api.com](https://the-odds-api.com)
2. Sign up for a free account
3. Get your API key from your dashboard
4. Add to `.env`: `ODDS_API_KEY=your_key`

## Main Endpoints

### Authentication
- `POST /api/register` - User registration
- `POST /api/login` - User login
- `GET /api/user` - Get current user (requires token)
- `POST /api/change-password` - Change password (requires token)

### Football Odds
- `GET /api/odds` - List all football matches with odds
- `GET /api/odds/{id}` - Get specific match odds

## Database Structure

The system uses 4 main tables:

- **sports_matches** - Football matches 
- **odds_sources** - API sources (the-odds-api.com, etc.)
- **match_odds** - Individual betting odds per match
- **odds_sync_logs** - Audit trail of sync operations

See [ODDS_SYSTEM.md](./ODDS_SYSTEM.md) for full documentation.

## Syncing Odds

Sync all active sources:
```bash
php artisan odds:sync
```

Sync specific source:
```bash
php artisan odds:sync --source=1
```

## Example API Response

### List Football Matches
```
GET /api/odds
```

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "match": "Manchester United vs Liverpool",
      "home_team": "Manchester United",
      "away_team": "Liverpool",
      "league": "Premier League",
      "date": "2026-04-17 15:00",
      "status": "scheduled",
      "odds": [
        {"type": "1", "name": "Home Win", "odds": 2.10},
        {"type": "X", "name": "Draw", "odds": 3.50},
        {"type": "2", "name": "Away Win", "odds": 3.20}
      ]
    }
  ],
  "total": 50,
  "current_page": 1,
  "per_page": 20
}
```

## Architecture

### Service Layer
- **OddsApiService** - Fetches and syncs odds from the-odds-api.com
  - `syncFootballOdds()` - Main sync method
  - `fetchFromApi()` - API communication
  - `syncMatch()` - Creates/updates matches
  - `syncMatchOdds()` - Creates/updates odds

### Models
- **SportsMatch** - Football matches (HasMany odds)
- **OddsSource** - API sources (HasMany matchOdds, syncLogs)
- **MatchOdd** - Betting odds (BelongsTo match, source)
- **OddsSyncLog** - Sync audit trail (BelongsTo source)

## Key Features

✅ **Multi-source aggregation** - Easy to add new API sources
✅ **Automatic syncing** - Keep odds fresh with manual or scheduled syncs
✅ **Conflict resolution** - Handles duplicate data gracefully
✅ **Comprehensive logging** - Audit all sync operations
✅ **Type safety** - Proper casting for dates and decimals
✅ **Relationship management** - Eloquent relationships for efficient queries
✅ **Error handling** - User-friendly validation messages

## Development

### Create New API Source

1. Get API key and documentation
2. Create `OddsSource` record:
   ```php
   OddsSource::create([
       'name' => 'api-name',
       'api_url' => 'https://api.example.com',
       'api_key' => 'key_here',
       'is_active' => true,
   ]);
   ```
3. Create service class or extend OddsApiService
4. Test with: `php artisan odds:sync --source=2`

### Error Handling

All errors return consistent format:
```json
{
  "success": false,
  "message": "Error message",
  "errors": {
    "field": ["Field error"]
  }
}
```

## Security

- API keys stored in `.env` (never committed)
- CORS restricted to approved domains
- Sanctum tokens with configurable expiry
- All inputs validated
- SQL injection protected via Eloquent

## Support

See [ODDS_SYSTEM.md](./ODDS_SYSTEM.md) for comprehensive documentation including:
- Complete database schema
- Data model relationships
- API integration details
- Setup and configuration
- Monitoring and debugging

## License

MIT License
