<?php

namespace App\Services;

use App\Models\OddsSource;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class KindeOddsApiService extends BaseOddsApiService
{
    /**
     * Fetch and sync football odds from oddsapi.kinde.com
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
     * Fetch data from oddsapi.kinde.com
     */
    public function fetchFromApi(OddsSource $source): array
    {
        $url = $source->api_url;
        $apiKey = $source->api_key;

        // Football leagues to fetch odds for
        $footballLeagues = [
            'premier-league',
            'la-liga',
            'bundesliga',
            'serie-a',
            'ligue-1',
            'champions-league',
            'europa-league',
        ];

        $allMatches = [];

        foreach ($footballLeagues as $league) {
            try {
                $endpoint = "{$url}/football/{$league}/odds";

                logger()->info("Fetching odds for {$league} from: {$endpoint}");

                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Accept' => 'application/json',
                ])->get($endpoint);

                logger()->info("Response status for {$league}: " . $response->status());
                logger()->info("Response body length for {$league}: " . strlen($response->body()));

                if (!$response->successful()) {
                    logger()->warning("Failed to fetch {$league}: " . $response->status());
                    logger()->warning("Response body: " . $response->body());
                    continue;
                }

                $jsonData = $response->json();
                logger()->info("JSON response keys for {$league}: " . json_encode(array_keys($jsonData)));

                // Assuming the API returns matches in a 'data' array or directly as array
                $matches = $jsonData['data'] ?? (is_array($jsonData) ? $jsonData : []);
                logger()->info("Found " . count($matches) . " matches for {$league}");

                if (count($matches) > 0) {
                    logger()->info("Sample match data: " . json_encode($matches[0]));
                }

                $allMatches = array_merge($allMatches, $matches);

                // Add small delay to avoid rate limiting
                sleep(1);

            } catch (\Exception $e) {
                logger()->error("Error fetching {$league}: " . $e->getMessage());
                continue;
            }
        }

        logger()->info("Total matches collected: " . count($allMatches));
        return $allMatches;
    }

    /**
     * Extract match data from oddsapi.kinde.com response
     * This needs to be adjusted based on the actual API response format
     */
    protected function extractMatchData(array $apiMatch): array
    {
        // Adjust these field names based on the actual API response
        // This is a placeholder implementation
        return [
            'external_id' => $apiMatch['id'] ?? $apiMatch['match_id'] ?? uniqid(),
            'match' => ($apiMatch['home_team'] ?? $apiMatch['home'] ?? 'Unknown') . ' vs ' . ($apiMatch['away_team'] ?? $apiMatch['away'] ?? 'Unknown'),
            'home_team' => $apiMatch['home_team'] ?? $apiMatch['home'] ?? 'Unknown',
            'away_team' => $apiMatch['away_team'] ?? $apiMatch['away'] ?? 'Unknown',
            'league' => $apiMatch['league'] ?? $apiMatch['competition'] ?? 'Unknown League',
            'date' => isset($apiMatch['date']) ? Carbon::parse($apiMatch['date'])->toDateTimeString() : now()->toDateTimeString(),
            'status' => $apiMatch['status'] ?? 'scheduled',
        ];
    }

    /**
     * Extract odds data from oddsapi.kinde.com response
     * This needs to be adjusted based on the actual API response format
     */
    protected function extractOddsData(array $apiMatch): array
    {
        $oddsData = [];

        // Adjust this based on the actual API response structure
        // This is a placeholder implementation
        if (isset($apiMatch['odds']) && is_array($apiMatch['odds'])) {
            foreach ($apiMatch['odds'] as $odds) {
                // Assuming odds structure with home, draw, away
                if (isset($odds['home'])) {
                    $oddsData[] = [
                        'type' => '1',
                        'name' => $apiMatch['home_team'] ?? $apiMatch['home'] ?? 'Home',
                        'odds' => $odds['home'],
                    ];
                }
                if (isset($odds['draw'])) {
                    $oddsData[] = [
                        'type' => 'X',
                        'name' => 'Draw',
                        'odds' => $odds['draw'],
                    ];
                }
                if (isset($odds['away'])) {
                    $oddsData[] = [
                        'type' => '2',
                        'name' => $apiMatch['away_team'] ?? $apiMatch['away'] ?? 'Away',
                        'odds' => $odds['away'],
                    ];
                }
            }
        }

        return $oddsData;
    }
}