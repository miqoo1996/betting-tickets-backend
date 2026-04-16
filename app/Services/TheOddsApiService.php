<?php

namespace App\Services;

use App\Models\OddsSource;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class TheOddsApiService extends BaseOddsApiService
{
    /**
     * Fetch and sync football odds from the-odds-api.com
     */
    public function syncFootballOdds(OddsSource $source): array
    {
        logger()->info("Starting syncFootballOdds for " . $source->name);
        try {
            $matches = $this->fetchFromApi($source);
            $matchesSynced = 0;
            $oddsSynced = 0;

            logger()->info("Fetched " . count($matches) . " matches from API");

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
    public function fetchFromApi(OddsSource $source): array
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
                    // Check if the match has bookmakers data
                    if (isset($matches[0]['bookmakers'])) {
                        logger()->info("Match has bookmakers: " . count($matches[0]['bookmakers']));
                    } else {
                        logger()->warning("Match does NOT have bookmakers data");
                    }
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
     * Extract match data from the-odds-api.com response
     */
    protected function extractMatchData(array $apiMatch): array
    {
        return [
            'external_id' => $apiMatch['id'],
            'home_team' => $apiMatch['home_team'],
            'away_team' => $apiMatch['away_team'],
            'league' => $this->mapSportToLeague($apiMatch['sport_key']),
            'commence_time' => Carbon::parse($apiMatch['commence_time'])->toDateTimeString(),
            'status' => 'scheduled',
        ];
    }

    /**
     * Extract odds data from the-odds-api.com response
     */
    protected function extractOddsData(array $apiMatch): array
    {
        $oddsData = [];

        if (isset($apiMatch['bookmakers']) && is_array($apiMatch['bookmakers'])) {
            logger()->info("Processing " . count($apiMatch['bookmakers']) . " bookmakers for match");
            foreach ($apiMatch['bookmakers'] as $bookmaker) {
                if (isset($bookmaker['markets']) && is_array($bookmaker['markets'])) {
                    foreach ($bookmaker['markets'] as $market) {
                        if ($market['key'] === 'h2h' && isset($market['outcomes']) && is_array($market['outcomes'])) {
                            logger()->info("Found h2h market with " . count($market['outcomes']) . " outcomes for bookmaker " . ($bookmaker['title'] ?? 'unknown'));
                            foreach ($market['outcomes'] as $outcome) {
                                $type = $this->mapOutcomeToType($outcome['name'], $apiMatch['home_team'], $apiMatch['away_team']);

                                $oddsData[] = [
                                    'type' => $type,
                                    'name' => $outcome['name'],
                                    'odds' => $outcome['price'],
                                    'bookmaker' => $bookmaker['title'] ?? 'Unknown Bookmaker',
                                ];
                            }
                        }
                    }
                }
            }
        } else {
            logger()->warning("No bookmakers found in match data");
        }

        logger()->info("Extracted " . count($oddsData) . " odds for match");
        return $oddsData;
    }

    /**
     * Map sport key to league name
     */
    private function mapSportToLeague(string $sportKey): string
    {
        $sportMap = [
            'soccer_epl' => 'Premier League',
            'soccer_spain_la_liga' => 'La Liga',
            'soccer_germany_bundesliga' => 'Bundesliga',
            'soccer_italy_serie_a' => 'Serie A',
            'soccer_france_ligue_one' => 'Ligue 1',
            'soccer_uefa_champs_league' => 'Champions League',
            'soccer_uefa_europa_league' => 'Europa League',
        ];

        return $sportMap[$sportKey] ?? $sportKey;
    }

    /**
     * Map outcome name to type
     */
    private function mapOutcomeToType(string $outcomeName, string $homeTeam, string $awayTeam): string
    {
        if ($outcomeName === $homeTeam) {
            return '1';
        } elseif ($outcomeName === $awayTeam) {
            return '2';
        } elseif (strtolower($outcomeName) === 'draw') {
            return 'X';
        }

        return $outcomeName;
    }
}