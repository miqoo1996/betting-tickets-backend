<?php

namespace App\Services;

use App\Models\MatchOdd;
use App\Models\OddsSource;
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

        if (isset($matchData['date']) && !isset($matchData['commence_time'])) {
            $matchData['commence_time'] = $matchData['date'];
            unset($matchData['date']);
        }

        $validMatchData = array_intersect_key($matchData, array_flip([
            'external_id',
            'home_team',
            'away_team',
            'league',
            'commence_time',
            'status',
            'synced_at',
        ]));

        return SportsMatch::updateOrCreate(
            ['external_id' => $matchData['external_id']],
            $validMatchData
        );
    }

    /**
     * Sync odds for a match
     */
    public function syncMatchOdds(SportsMatch $sportMatch, array $apiMatch, OddsSource $source): int
    {
        $oddsData = $this->extractOddsData($apiMatch);
        $oddsSynced = 0;

        logger()->info("Processing " . count($oddsData) . " odds for match " . $sportMatch->id);

        foreach ($oddsData as $oddData) {
            logger()->info("Creating odd: type={$oddData['type']}, bookmaker={$oddData['bookmaker']}, odds={$oddData['odds']}");
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

        logger()->info("Synced " . $oddsSynced . " odds for match " . $sportMatch->id);
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
