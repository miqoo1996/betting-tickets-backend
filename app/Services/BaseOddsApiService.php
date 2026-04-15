<?php

namespace App\Services;

use App\Models\MatchOdd;
use App\Models\OdsSource;
use App\Models\OddsSyncLog;
use App\Models\SportsMatch;

abstract class BaseOddsApiService implements OddsApiInterface
{
    /**
     * Sync a single match
     */
    public function syncMatch(array $apiMatch): SportsMatch
    {
        // Extract match data - to be implemented by subclasses
        $matchData = $this->extractMatchData($apiMatch);

        return SportsMatch::updateOrCreate(
            ['external_id' => $matchData['external_id']],
            [
                'match' => $matchData['match'],
                'home_team' => $matchData['home_team'],
                'away_team' => $matchData['away_team'],
                'league' => $matchData['league'],
                'date' => $matchData['date'],
                'status' => $matchData['status'] ?? 'scheduled',
            ]
        );
    }

    /**
     * Sync odds for a match
     */
    public function syncMatchOdds(SportsMatch $sportMatch, array $apiMatch, OddsSource $source): int
    {
        $oddsData = $this->extractOddsData($apiMatch);
        $oddsSynced = 0;

        foreach ($oddsData as $oddData) {
            MatchOdd::updateOrCreate(
                [
                    'match_id' => $sportMatch->id,
                    'odds_source_id' => $source->id,
                    'odds_type' => $oddData['type'],
                    'bookmaker_name' => $oddData['bookmaker'],
                ],
                [
                    'odds_value' => $oddData['odds'],
                ]
            );
            $oddsSynced++;
        }

        return $oddsSynced;
    }

    /**
     * Log sync result
     */
    public function logSyncResult(OddsSource $source, int $matchesSynced, int $oddsSynced, string $status, ?string $errorMessage = null): array
    {
        OddsSyncLog::create([
            'odds_source_id' => $source->id,
            'matches_synced' => $matchesSynced,
            'odds_synced' => $oddsSynced,
            'status' => $status,
            'error_message' => $errorMessage,
            'synced_at' => now(),
        ]);

        return [
            'matches_synced' => $matchesSynced,
            'odds_synced' => $oddsSynced,
            'status' => $status,
            'error' => $errorMessage,
        ];
    }

    /**
     * Extract match data from API response - to be implemented by subclasses
     */
    abstract protected function extractMatchData(array $apiMatch): array;

    /**
     * Extract odds data from API response - to be implemented by subclasses
     */
    abstract protected function extractOddsData(array $apiMatch): array;
}
