<?php

namespace App\Services;

use App\Models\OddsSource;
use App\Models\SportsMatch;

interface OddsApiInterface
{
    /**
     * Sync football odds from the API provider
     */
    public function syncFootballOdds(OddsSource $source): array;

    /**
     * Fetch data from the API
     */
    public function fetchFromApi(OddsSource $source): array;

    /**
     * Sync a single match
     */
    public function syncMatch(array $apiMatch): SportsMatch;

    /**
     * Sync odds for a match
     */
    public function syncMatchOdds(SportsMatch $sportMatch, array $apiMatch, OddsSource $source): int;

    /**
     * Log sync result
     */
    public function logSyncResult(OddsSource $source, int $matchesSynced, int $oddsSynced, string $status, ?string $errorMessage = null): array;
}