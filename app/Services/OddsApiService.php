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
        logger()->info("Creating API service for source: " . $source->name . " with URL: " . $source->api_url);
        // Determine which API service to use based on the source URL or name
        if (str_contains($source->api_url, 'odds-api.io')) {
            logger()->info("Using OddsApiIoService");
            return new OddsApiIoService();
        } elseif (str_contains($source->api_url, 'the-odds-api.com')) {
            logger()->info("Using TheOddsApiService");
            return new TheOddsApiService();
        } elseif (str_contains($source->api_url, 'football.api-sports.io')) {
            logger()->info("Using ApiFootballService");
            return new ApiFootballService();
        } else {
            throw new \InvalidArgumentException("Unsupported API provider: {$source->api_url}");
        }
    }

    /**
     * Fetch and sync football odds from configured API sources
     */
    public function syncFootballOdds(OddsSource $source): array
    {
        logger()->info("OddsApiService.syncFootballOdds called for source: " . $source->name);
        $apiService = self::getApiService($source);
        logger()->info("Got API service, calling syncFootballOdds on it");
        return $apiService->syncFootballOdds($source);
    }
}
