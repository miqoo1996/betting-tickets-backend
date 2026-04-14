# Football Odds Aggregation System

## Overview

This system aggregates real-time football odds from multiple API sources (starting with the-odds-api.com) and stores them in a local database for efficient querying and display to users.

## Database Schema

### Tables

#### 1. **sports_matches**
Stores information about football matches.

**Columns:**
- `id` - Primary key
- `external_id` - Unique identifier from the API (for syncing)
- `league` - League name (e.g., "Premier League", "La Liga")
- `home_team` - Home team name
- `away_team` - Away team name
- `commence_time` - Match start time (UTC)
- `status` - Match status: `scheduled`, `live`, `finished`, `cancelled`
- `synced_at` - Last sync time from API
- `created_at`, `updated_at` - Timestamps

**Indexes:**
- `external_id` (unique) - Fast lookup by API ID
- `league` - Filter by league
- `commence_time` - Sort by match time
- `status` - Filter by match status

#### 2. **odds_sources**
Manages configured API sources for odds data.

**Columns:**
- `id` - Primary key
- `name` - Source identifier (e.g., "the-odds-api.com")
- `api_url` - Base API URL
- `api_key` - API authentication key
- `is_active` - Whether this source is enabled
- `last_synced_at` - Last successful sync timestamp
- `sync_interval_minutes` - Minutes between sync attempts
- `description` - Source details
- `created_at`, `updated_at` - Timestamps

#### 3. **match_odds**
Stores actual betting odds for each match and source.

**Columns:**
- `id` - Primary key
- `match_id` - Foreign key to sports_matches
- `odds_source_id` - Foreign key to odds_sources
- `odds_type` - Bet type: `1` (Home Win), `X` (Draw), `2` (Away Win)
- `odds_value` - Decimal odds (e.g., 2.10, 3.50)
- `bookmaker_name` - Specific bookmaker (optional)
- `created_at`, `updated_at` - Timestamps

**Unique Constraint:**
- `match_id + odds_source_id + odds_type` - Only one odds value per type per source per match

#### 4. **odds_sync_logs**
Tracks sync operations and results for auditing/debugging.

**Columns:**
- `id` - Primary key
- `odds_source_id` - Foreign key to odds_sources
- `total_matches_synced` - Count of matches synced
- `total_odds_synced` - Count of individual odds updated
- `status` - Sync result: `success`, `partial`, `failed`
- `error_message` - Error details if failed
- `synced_at` - When sync was performed
- `created_at`, `updated_at` - Timestamps

## Models

### SportsMatch
```php
// Relationships
$match->odds() // HasMany MatchOdd
```

### OddsSource
```php
// Relationships
$source->matchOdds() // HasMany MatchOdd
$source->syncLogs() // HasMany OddsSyncLog
```

### MatchOdd
```php
// Relationships
$odd->match() // BelongsTo SportsMatch
$odd->source() // BelongsTo OddsSource
```

### OddsSyncLog
```php
// Relationships
$log->source() // BelongsTo OddsSource
```

## API Integration

### the-odds-api.com

**Service:** `app/Services/OddsApiService.php`

**Features:**
- Fetches football odds from the-odds-api.com
- Syncs matches and odds to local database
- Handles data transformation and conflict resolution
- Logs sync operations for auditing

**Supported Markets:**
- `h2h` - Head-to-head (1x2) betting: Home Win (1), Draw (X), Away Win (2)

**Data Points:**
- Match info (teams, league, start time)
- Odds from multiple bookmakers
- Real-time odds updates

### Setup

1. **Get API Key**
   - Sign up at https://the-odds-api.com
   - Get your API key

2. **Configure Environment**
   ```bash
   # In .env
   ODDS_API_KEY=your_actual_api_key_here
   ```

3. **Run Initial Sync**
   ```bash
   php artisan odds:sync
   ```

## Syncing Odds

### Manual Sync

Sync all active sources:
```bash
php artisan odds:sync
```

Sync specific source:
```bash
php artisan odds:sync --source=1
```

### Response Example
```json
{
  "success": true,
  "matches_synced": 50,
  "odds_synced": 150,
  "status": "success",
  "error": null
}
```

### Scheduled Sync (Optional)

Add to `app/Console/Kernel.php`:
```php
$schedule->command('odds:sync')->hourly();
```

## API Endpoints

### List All Football Odds
```
GET /api/odds
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "external_id": "api123456",
      "match": "Manchester United vs Liverpool",
      "home_team": "Manchester United",
      "away_team": "Liverpool",
      "league": "Premier League",
      "date": "2026-04-15 15:00",
      "status": "scheduled",
      "odds": [
        {
          "type": "1",
          "name": "Home Win",
          "odds": 2.10
        },
        {
          "type": "X",
          "name": "Draw",
          "odds": 3.50
        },
        {
          "type": "2",
          "name": "Away Win",
          "odds": 3.20
        }
      ]
    }
  ],
  "total": 50,
  "current_page": 1,
  "per_page": 20
}
```

### Get Single Match Odds
```
GET /api/odds/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "match": "Manchester United vs Liverpool",
    "home_team": "Manchester United",
    "away_team": "Liverpool",
    "league": "Premier League",
    "date": "2026-04-15 15:00",
    "status": "scheduled",
    "odds": [
      {
        "type": "1",
        "name": "Home Win",
        "odds": 2.10,
        "sources": [
          {
            "source": "the-odds-api.com",
            "value": 2.10
          }
        ]
      }
    ]
  }
}
```

## Adding New API Sources

1. **Create OddsSource record:**
   ```php
   OddsSource::create([
       'name' => 'api-name',
       'api_url' => 'https://api.example.com/v1',
       'api_key' => 'api_key_here',
       'is_active' => true,
       'sync_interval_minutes' => 60,
       'description' => 'Description of this API',
   ]);
   ```

2. **Extend OddsApiService:**
   - Create separate service class or method for new API
   - Follow same data structure (convert to sports_matches format)
   - Use same MatchOdd storage

3. **Test sync:**
   ```bash
   php artisan odds:sync --source=2
   ```

## Database Design Highlights

### Normalization
- Separate tables for matches, sources, and odds
- Single match can have odds from multiple sources
- Easy to compare odds between bookmakers

### Performance
- Indexes on frequently queried columns
- Pagination support in API
- Unique constraint prevents duplicates

### Scalability
- Can easily add more API sources
- Sync logs enable monitoring and alerting
- Status tracking for real-time match information

### Data Integrity
- Foreign key constraints with cascading deletes
- Timestamps for all audit trails
- Transaction-safe operations during sync

## Monitoring & Debugging

### View Recent Syncs
```php
OddsSyncLog::latest('synced_at')->first();
```

### Check Active Sources
```php
OddsSource::where('is_active', true)->get();
```

### View Match Statistics
```php
$matchCount = SportsMatch::count();
$oddsCount = MatchOdd::count();
$sourceCount = OddsSource::where('is_active', true)->count();
```

## Future Enhancements

- [ ] Support for more betting markets (over/under, Asian handicap)
- [ ] Support for more API sources (Bet365, Pinnacle, etc.)
- [ ] Real-time WebSocket updates
- [ ] Historical odds tracking for analysis
- [ ] Odds comparison algorithms
- [ ] Arbitrage detection
- [ ] Automated scheduled syncing with Laravel Scheduler
