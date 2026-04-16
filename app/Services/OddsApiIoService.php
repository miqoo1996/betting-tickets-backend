<?php

namespace App\Services;

use App\Models\OddsSource;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class OddsApiIoService extends BaseOddsApiService
{
    /**
     * Fetch and sync football odds from odds-api.io
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
     * Fetch data from odds-api.io
     * First get events, then get odds for each event
     */
    public function fetchFromApi(OddsSource $source): array
    {
        $url = $source->api_url;
        $apiKey = $source->api_key;

        try {
            // First, get the list of available bookmakers
            logger()->info("Getting list of available bookmakers");

            $bookmakersResponse = Http::get("{$url}/bookmakers", [
                'apiKey' => $apiKey,
            ]);

            $bookmakers = [];
            if ($bookmakersResponse->successful()) {
                $bookmakersData = $bookmakersResponse->json();
                // Get first 5 active bookmakers
                $bookmakers = array_slice(array_column(array_filter($bookmakersData, fn($b) => $b['active'] ?? false), 'name'), 0, 5);
                logger()->info("Found bookmakers: " . implode(', ', $bookmakers));
            } else {
                logger()->warning("Failed to get bookmakers: " . $bookmakersResponse->status());
                // Use some default bookmakers
                $bookmakers = ['Bet365', 'Unibet', 'WilliamHill'];
            }

            // Then get football events
            logger()->info("Fetching football events from odds-api.io");

            $eventsResponse = Http::get("{$url}/events", [
                'apiKey' => $apiKey,
                'sport' => 'football',
                'limit' => 2, // Start with just 2 events to test
            ]);

            logger()->info("Events response status: " . $eventsResponse->status());

            if (!$eventsResponse->successful()) {
                logger()->error("Failed to fetch events: " . $eventsResponse->status());
                logger()->error("Response: " . $eventsResponse->body());
                return [];
            }

            $events = $eventsResponse->json();
            logger()->info("Found " . count($events) . " football events");

            if (count($events) === 0) {
                return [];
            }

            $allMatches = [];

            // For each event, get the odds
            foreach ($events as $event) {
                try {
                    logger()->info("Fetching odds for event ID: {$event['id']}");

                    $oddsResponse = Http::get("{$url}/odds", [
                        'apiKey' => $apiKey,
                        'eventId' => $event['id'],
                        'bookmakers' => implode(',', $bookmakers), // Use the retrieved bookmakers
                    ]);

                    if ($oddsResponse->successful()) {
                        $oddsData = $oddsResponse->json();

                        // Combine event data with odds data
                        $matchData = array_merge($event, ['odds_data' => $oddsData]);
                        $allMatches[] = $matchData;

                        logger()->info("Successfully got odds for event {$event['id']}");
                    } else {
                        logger()->warning("Failed to get odds for event {$event['id']}: " . $oddsResponse->status());
                    }

                    // Small delay to avoid rate limiting
                    usleep(100000); // 0.1 seconds

                } catch (\Exception $e) {
                    logger()->error("Error fetching odds for event {$event['id']}: " . $e->getMessage());
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
     * Extract match data from odds-api.io response
     */
    protected function extractMatchData(array $apiMatch): array
    {
        return [
            'external_id' => (string)$apiMatch['id'],
            'home_team' => $apiMatch['home'],
            'away_team' => $apiMatch['away'],
            'league' => $apiMatch['league']['name'] ?? 'Unknown League',
            'commence_time' => Carbon::parse($apiMatch['date'])->toDateTimeString(),
            'status' => $apiMatch['status'] ?? 'scheduled',
        ];
    }

    /**
     * Extract odds data from odds-api.io response
     */
    protected function extractOddsData(array $apiMatch): array
    {
        $oddsData = [];

        if (!isset($apiMatch['odds_data']['bookmakers'])) {
            return $oddsData;
        }

        foreach ($apiMatch['odds_data']['bookmakers'] as $bookmaker) {
            if (!isset($bookmaker['markets'])) {
                continue;
            }

            // Look for h2h (head-to-head) market
            foreach ($bookmaker['markets'] as $market) {
                if (($market['key'] ?? '') === 'h2h' && isset($market['outcomes'])) {
                    foreach ($market['outcomes'] as $outcome) {
                        $type = $this->mapOutcomeToType($outcome['name'], $apiMatch['home'], $apiMatch['away']);

                        $oddsData[] = [
                            'type' => $type,
                            'name' => $outcome['name'],
                            'odds' => $outcome['price'],
                            'bookmaker' => $bookmaker['name'] ?? 'Unknown Bookmaker',
                        ];
                    }
                }
            }
        }

        return $oddsData;
    }

    /**
     * Map outcome name to type (1, X, 2)
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
