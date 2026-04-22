<?php

namespace App\Services;

use App\Models\GfEvent;
use App\Models\GfLeague;
use App\Models\GfTeam;

class GameForecastService
{
    /**
     * Return filtered + paginated events from the database.
     * Supported filters: league_id, team_id, page, page_size
     */
    public function getEvents(array $filters = []): array
    {
        $query = GfEvent::with(['league', 'homeTeam', 'awayTeam', 'prediction']);

        if (!empty($filters['league_id'])) {
            $query->where('league_id', (int) $filters['league_id']);
        }

        if (!empty($filters['team_id'])) {
            $teamId = (int) $filters['team_id'];
            $query->where(function ($q) use ($teamId) {
                $q->where('home_team_id', $teamId)
                  ->orWhere('away_team_id', $teamId);
            });
        }

        $page     = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = min(100, max(1, (int) ($filters['page_size'] ?? 20)));

        $paginator = $query->orderBy('start_at')->paginate($pageSize, ['*'], 'page', $page);

        return [
            'data'      => $paginator->items(),
            'total'     => $paginator->total(),
            'page'      => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ];
    }

    /**
     * Find a single event by its GameForecast external_id.
     */
    public function getEvent(int $externalId): ?GfEvent
    {
        return GfEvent::with(['league', 'homeTeam', 'awayTeam', 'prediction'])
            ->where('external_id', $externalId)
            ->first();
    }

    public function getLeagues(): array
    {
        return GfLeague::orderBy('name')->get()->toArray();
    }

    public function getTeamIndex(): array
    {
        return GfTeam::pluck('name', 'id')->toArray();
    }
}