<?php

namespace App\Services;

use App\Models\OddsSource;

class OddsApiService
{
    /**
     * Factory method to get the appropriate API service based on source
     */
    public static function getApiService(OddsSource $source): OddsApiInterface
    {
        // Determine which API service to use based on the source URL or name
        if (str_contains($source->api_url, 'odds-api.io')) {
            return new OddsApiIoService();
        } elseif (str_contains($source->api_url, 'the-odds-api.com')) {
            return new TheOddsApiService();
        } else {
            throw new \InvalidArgumentException("Unsupported API provider: {$source->api_url}");
        }
    }

    /**
     * Fetch and sync football odds from configured API sources
     */
    public function syncFootballOdds(OddsSource $source): array
    {
        $apiService = self::getApiService($source);
        return $apiService->syncFootballOdds($source);
    }
}
