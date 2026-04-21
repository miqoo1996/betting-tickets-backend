<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GameForecastService
{
    private const BASE_URL = 'https://game-forecast-api.p.rapidapi.com';
    private const CACHE_TTL = 21600; // 6 hours — BASIC plan has a tight daily quota

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.game_forecast.key');
    }

    /**
     * Fetch and cache all events from the API (one call per TTL).
     * Never caches a failed or empty response — stale data is always preferred over nothing.
     */
    public function getAllEvents(): array
    {
        $cached = Cache::get('gf_all_events');

        // Return valid cached data immediately
        if (!empty($cached)) {
            return $cached;
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->get(self::BASE_URL . '/events', ['page_size' => 50]);
            $data = $response->ok() ? $response->json('data', []) : [];
        } catch (ConnectionException) {
            $data = [];
        }

        // Only cache when we actually have events; never persist an empty/error result
        if (!empty($data)) {
            Cache::put('gf_all_events', $data, self::CACHE_TTL);
        }

        return $data;
    }

    /**
     * Return filtered + paginated events. Filtering happens in PHP on the cached dataset.
     *
     * Supported filters: league_id, team_id, page, page_size
     */
    public function getEvents(array $filters = []): array
    {
        $all = $this->getAllEvents();

        if (!empty($filters['league_id'])) {
            $leagueId = (int) $filters['league_id'];
            $all = array_values(array_filter($all, fn ($e) => ($e['league']['id'] ?? null) === $leagueId));
        }

        if (!empty($filters['team_id'])) {
            $teamId = (int) $filters['team_id'];
            $all = array_values(array_filter($all, fn ($e) =>
                ($e['team_home']['id'] ?? null) === $teamId ||
                ($e['team_away']['id'] ?? null) === $teamId
            ));
        }

        $total    = count($all);
        $page     = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = min(100, max(1, (int) ($filters['page_size'] ?? 20)));
        $paged    = array_slice($all, ($page - 1) * $pageSize, $pageSize);

        return [
            'data'      => $paged,
            'total'     => $total,
            'page'      => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * Find a single event by its ID from the cached dataset.
     */
    public function getEvent(int $id): ?array
    {
        foreach ($this->getAllEvents() as $event) {
            if (($event['id'] ?? null) === $id) {
                return $event;
            }
        }

        return null;
    }

    public function getLeagues(): array
    {
        return Cache::remember('gf_leagues', self::CACHE_TTL, function () {
            $response = Http::withHeaders($this->headers())
                ->get(self::BASE_URL . '/leagues');

            if ($response->failed()) {
                return [];
            }

            return $response->json('data', []);
        });
    }

    /**
     * Build a name→id map of all teams found in the cached events.
     * Used to resolve a team name to its GameForecast team_id.
     */
    public function getTeamIndex(): array
    {
        return Cache::remember('gf_team_index', self::CACHE_TTL, function () {
            $index = [];
            foreach ($this->getAllEvents() as $event) {
                foreach (['team_home', 'team_away'] as $side) {
                    $team = $event[$side] ?? [];
                    if (!empty($team['id']) && !empty($team['name'])) {
                        $index[(int) $team['id']] = $team['name'];
                    }
                }
            }
            return $index;
        });
    }

    private function headers(): array
    {
        return [
            'X-RapidAPI-Key'  => $this->apiKey,
            'X-RapidAPI-Host' => 'game-forecast-api.p.rapidapi.com',
        ];
    }
}
