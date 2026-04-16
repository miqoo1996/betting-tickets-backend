<?php

namespace App\Services;

use App\Models\OddsSource;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class ApiFootballService extends BaseOddsApiService
{
    /**
     * Fetch and sync football odds from api-football.com
     */
    public function syncFootballOdds(OddsSource $source): array
    {
        try {
            $matches = $this->fetchFromApi($source);
            $matchesSynced = 0;
            $oddsSynced = 0;

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
     * Fetch data from api-football.com
     * First get fixtures, then get odds for each fixture
     */
    public function fetchFromApi(OddsSource $source): array
    {
        $url = $source->api_url;
        $apiKey = $source->api_key;

        try {
            // Get football fixtures for current season
            logger()->info("Fetching football fixtures from api-football.com");

            $fixturesResponse = Http::withHeaders([
                'x-rapidapi-key' => $apiKey,
                'x-rapidapi-host' => 'v3.football.api-sports.io',
            ])->get("{$url}/fixtures", [
                'league' => 39, // Premier League
                'season' => 2024, // Current season
            ]);

            if (!$fixturesResponse->successful()) {
                logger()->error("Failed to fetch fixtures: " . $fixturesResponse->status());
                logger()->error("Response: " . $fixturesResponse->body());
                return [];
            }

            $fixturesData = $fixturesResponse->json();
            $fixtures = $fixturesData['response'] ?? [];

            logger()->info("Found " . count($fixtures) . " football fixtures");

            if (count($fixtures) === 0) {
                return [];
            }

            $allMatches = [];

            // For each fixture, get the odds
            foreach (array_slice($fixtures, 0, 5) as $fixture) { // Limit to 5 for testing
                try {
                    logger()->info("Fetching odds for fixture ID: {$fixture['fixture']['id']}");

                    $oddsResponse = Http::withHeaders([
                        'x-rapidapi-key' => $apiKey,
                        'x-rapidapi-host' => 'v3.football.api-sports.io',
                    ])->get("{$url}/odds", [
                        'fixture' => $fixture['fixture']['id'],
                        // 'bookmaker' => 2, // Try without specific bookmaker to see all
                    ]);

                    if ($oddsResponse->successful()) {
                        $oddsData = $oddsResponse->json();

                        logger()->info("Odds response for fixture {$fixture['fixture']['id']}: " . json_encode($oddsData));

                        // Combine fixture data with odds data
                        $matchData = array_merge($fixture, ['odds_data' => $oddsData]);
                        $allMatches[] = $matchData;

                        logger()->info("Successfully got odds for fixture {$fixture['fixture']['id']}");
                    } else {
                        logger()->warning("Failed to get odds for fixture {$fixture['fixture']['id']}: " . $oddsResponse->status() . " - " . $oddsResponse->body());
                    }

                    // Small delay to avoid rate limiting
                    usleep(200000); // 0.2 seconds

                } catch (\Exception $e) {
                    logger()->error("Error fetching odds for fixture {$fixture['fixture']['id']}: " . $e->getMessage());
                    continue;
                }
            }

            logger()->info("Total matches with odds collected: " . count($allMatches));
            return $allMatches;

        } catch (\Exception $e) {
            logger()->error('Error in fetchFromApi: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Extract match data from api-football.com response
     */
    protected function extractMatchData(array $apiMatch): array
    {
        return [
            'external_id' => (string)$apiMatch['fixture']['id'],
            'home_team' => $apiMatch['teams']['home']['name'],
            'away_team' => $apiMatch['teams']['away']['name'],
            'league' => $apiMatch['league']['name'] ?? 'Unknown League',
            'commence_time' => Carbon::parse($apiMatch['fixture']['date'])->toDateTimeString(),
            'status' => $apiMatch['fixture']['status']['short'] ?? 'scheduled',
        ];
    }

    /**
     * Extract odds data from api-football.com response
     */
    protected function extractOddsData(array $apiMatch): array
    {
        $oddsData = [];

        if (!isset($apiMatch['odds_data']['response'])) {
            return $oddsData;
        }

        foreach ($apiMatch['odds_data']['response'] as $bookmakerData) {
            $bookmakerName = $bookmakerData['bookmaker']['name'] ?? 'Unknown Bookmaker';

            if (!isset($bookmakerData['bets'])) {
                continue;
            }

            // Look for Match Winner bet (1X2)
            foreach ($bookmakerData['bets'] as $bet) {
                if (($bet['name'] ?? '') === 'Match Winner') {
                    foreach ($bet['values'] as $value) {
                        $type = $this->mapOutcomeToType($value['value'], $apiMatch['teams']['home']['name'], $apiMatch['teams']['away']['name']);

                        $oddsData[] = [
                            'type' => $type,
                            'name' => $value['value'],
                            'odds' => $value['odd'],
                            'bookmaker' => $bookmakerName,
                        ];
                    }
                }
            }
        }

        return $oddsData;
    }

    /**
     * Map outcome value to type (1, X, 2)
     */
    private function mapOutcomeToType(string $outcomeValue, string $homeTeam, string $awayTeam): string
    {
        if ($outcomeValue === $homeTeam) {
            return '1';
        } elseif ($outcomeValue === $awayTeam) {
            return '2';
        } elseif (strtolower($outcomeValue) === 'draw') {
            return 'X';
        }

        return 'unknown';
    }
}
