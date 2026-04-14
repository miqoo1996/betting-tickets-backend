<?php

namespace App\Services;

use App\Models\OddsSource;
use App\Models\SportsMatch;
use App\Models\MatchOdd;
use App\Models\OddsSyncLog;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class OddsApiService
{
    /**
     * Fetch and sync football odds from the-odds-api.com
     */
    public function syncFootballOdds(OddsSource $source): array
    {
        try {
            // Fetch data from API
            $matches = $this->fetchFromApi($source);

            if (empty($matches)) {
                return $this->logSyncResult($source, 0, 0, 'failed', 'No data received from API');
            }

            $matchesSynced = 0;
            $oddsSynced = 0;

            // Process each match
            foreach ($matches as $apiMatch) {
                try {
                    $matchesSynced++;
                    $sportMatch = $this->syncMatch($apiMatch);
                    $oddsSynced += $this->syncMatchOdds($sportMatch, $apiMatch, $source);
                } catch (\Exception $e) {
                    logger()->error('Error syncing match: ' . $e->getMessage(), ['match' => $apiMatch]);
                    continue;
                }
            }

            // Update last synced time
            $source->update(['last_synced_at' => now()]);

            return $this->logSyncResult($source, $matchesSynced, $oddsSynced, 'success');
        } catch (\Exception $e) {
            logger()->error('Error syncing odds: ' . $e->getMessage());
            return $this->logSyncResult($source, 0, 0, 'failed', $e->getMessage());
        }
    }

    /**
     * Fetch data from the-odds-api.com
     */
    private function fetchFromApi(OddsSource $source): array
    {
        $url = $source->api_url;
        $apiKey = $source->api_key;

        // Football leagues to fetch odds for
        $footballSports = [
            'soccer_epl',      // Premier League
            'soccer_spain_la_liga',  // La Liga
            'soccer_germany_bundesliga',  // Bundesliga
            'soccer_italy_serie_a',  // Serie A
            'soccer_france_ligue_one',  // Ligue 1
            'soccer_uefa_champs_league',  // Champions League
            'soccer_uefa_europa_league',  // Europa League
        ];

        $allMatches = [];

        foreach ($footballSports as $sport) {
            try {
                $endpoint = "{$url}/sports/{$sport}/odds";

                logger()->info("Fetching odds for {$sport} from: {$endpoint}");

                $response = Http::get($endpoint, [
                    'apiKey' => $apiKey,
                    'regions' => 'eu',
                    'markets' => 'h2h', // Head-to-head markets (1x2)
                    'oddsFormat' => 'decimal',
                ]);

                logger()->info("Response status for {$sport}: " . $response->status());
                logger()->info("Response body length for {$sport}: " . strlen($response->body()));

                if (!$response->successful()) {
                    logger()->warning("Failed to fetch {$sport}: " . $response->status());
                    logger()->warning("Response body: " . $response->body());
                    continue;
                }

                $jsonData = $response->json();
                logger()->info("JSON response keys for {$sport}: " . json_encode(array_keys($jsonData)));

                // The API returns an array of matches directly, not wrapped in a 'data' key
                $matches = is_array($jsonData) ? $jsonData : [];
                logger()->info("Found " . count($matches) . " matches for {$sport}");

                if (count($matches) > 0) {
                    logger()->info("Sample match data: " . json_encode($matches[0]));
                }

                $allMatches = array_merge($allMatches, $matches);

                // Add small delay to avoid rate limiting
                sleep(1);

            } catch (\Exception $e) {
                logger()->error("Error fetching {$sport}: " . $e->getMessage());
                continue;
            }
        }

        logger()->info("Total matches collected: " . count($allMatches));
        return $allMatches;
    }

    /**
     * Sync or create a sports match
     */
    private function syncMatch(array $apiMatch): SportsMatch
    {
        return SportsMatch::updateOrCreate(
            ['external_id' => $apiMatch['id']],
            [
                'league' => $this->mapLeague($apiMatch['sport_key']),
                'home_team' => $apiMatch['home_team'],
                'away_team' => $apiMatch['away_team'],
                'commence_time' => Carbon::parse($apiMatch['commence_time']),
                'status' => $this->mapStatus($apiMatch['status'] ?? 'scheduled'),
                'synced_at' => now(),
            ]
        );
    }

    /**
     * Sync odds for a match from the API data
     */
    private function syncMatchOdds(SportsMatch $match, array $apiMatch, OddsSource $source): int
    {
        $oddsSynced = 0;

        if (!isset($apiMatch['bookmakers'])) {
            return $oddsSynced;
        }

        foreach ($apiMatch['bookmakers'] as $bookmakers) {
            $bookmakerName = $bookmakers['title'] ?? 'Unknown';

            if (!isset($bookmakers['markets'])) {
                continue;
            }

            // Find the h2h market
            $h2hMarket = collect($bookmakers['markets'])
                ->firstWhere('key', 'h2h');

            if (!$h2hMarket) {
                continue;
            }

            // Create odds entries for each outcome (Home Win, Draw, Away Win)
            $outcomes = [
                '1' => 'home',  // Home win
                'X' => 'draw',  // Draw
                '2' => 'away',  // Away win
            ];

            foreach ($h2hMarket['outcomes'] as $index => $outcome) {
                $oddsType = isset($outcomes[$index === 0 ? '1' : ($index === 1 ? 'X' : '2')])
                    ? ($index === 0 ? '1' : ($index === 1 ? 'X' : '2'))
                    : null;

                if (!$oddsType) {
                    continue;
                }

                MatchOdd::updateOrCreate(
                    [
                        'match_id' => $match->id,
                        'odds_source_id' => $source->id,
                        'odds_type' => $oddsType,
                    ],
                    [
                        'odds_value' => $outcome['price'],
                        'bookmaker_name' => $bookmakerName,
                    ]
                );

                $oddsSynced++;
            }
        }

        return $oddsSynced;
    }

    /**
     * Map API status to our status format
     */
    private function mapStatus(string $apiStatus): string
    {
        $statusMap = [
            'scheduled' => 'scheduled',
            'live' => 'live',
            'in_play' => 'live',
            'completed' => 'finished',
            'finished' => 'finished',
        ];

        return $statusMap[$apiStatus] ?? 'scheduled';
    }

    /**
     * Map API sport key to league name
     */
    private function mapLeague(string $sportKey): string
    {
        $leagueMap = [
            'soccer_epl' => 'Premier League',
            'soccer_spain_la_liga' => 'La Liga',
            'soccer_germany_bundesliga' => 'Bundesliga',
            'soccer_italy_serie_a' => 'Serie A',
            'soccer_france_ligue_one' => 'Ligue 1',
            'soccer_uefa_champs_league' => 'Champions League',
            'soccer_uefa_europa_league' => 'Europa League',
        ];

        return $leagueMap[$sportKey] ?? ucfirst(str_replace(['soccer_', '_'], ['', ' '], $sportKey));
    }

    /**
     * Log sync results
     */
    private function logSyncResult(OddsSource $source, int $matchesSynced, int $oddsSynced, string $status, string $errorMessage = null): array
    {
        OddsSyncLog::create([
            'odds_source_id' => $source->id,
            'total_matches_synced' => $matchesSynced,
            'total_odds_synced' => $oddsSynced,
            'status' => $status,
            'error_message' => $errorMessage,
            'synced_at' => now(),
        ]);

        return [
            'success' => $status === 'success',
            'matches_synced' => $matchesSynced,
            'odds_synced' => $oddsSynced,
            'status' => $status,
            'error' => $errorMessage,
        ];
    }
}
