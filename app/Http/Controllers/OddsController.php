<?php

namespace App\Http\Controllers;

use App\Models\SportsMatch;
use Illuminate\Http\Request;

class OddsController extends Controller
{
    /**
     * Get all available football odds
     */
    public function index(Request $request)
    {
        $league = $request->get('league');
        $search = $request->get('search');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $sortBy = $request->get('sort_by', 'commence_time');
        $sortOrder = $request->get('sort_order', 'asc');
        $limit = $request->get('limit', 20);

        // Get scheduled matches with their odds from all sources and bookmakers
        $query = SportsMatch::where('status', 'scheduled')
            ->with(['odds' => function ($query) {
                // Load all odds with source information, sorted by type and then by odds value
                $query->with('source')
                    ->orderBy('odds_type')
                    ->orderBy('odds_value', 'desc');
            }]);

        // Filter by league if specified
        if ($league) {
            $query->where('league', $league);
        }

        // Advanced search functionality
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('home_team', 'LIKE', "%{$search}%")
                  ->orWhere('away_team', 'LIKE', "%{$search}%")
                  ->orWhere('league', 'LIKE', "%{$search}%");
            });
        }

        // Date range filtering
        if ($dateFrom) {
            $query->whereDate('commence_time', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('commence_time', '<=', $dateTo);
        }

        // Sorting
        $allowedSortFields = ['commence_time', 'home_team', 'away_team', 'league'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('commence_time', 'asc');
        }

        $matches = $query->paginate($limit);

        // Transform matches into the format expected by the frontend
        $formattedMatches = $matches->getCollection()->map(function ($match) {
            // Group all odds by type, showing all bookmakers and sources
            $odds = $match->odds->groupBy('odds_type')->map(function ($oddsGroup) {
                return [
                    'type' => $oddsGroup->first()->odds_type,
                    'name' => $this->getOddsTypeName($oddsGroup->first()->odds_type),
                    'bookmakers' => $oddsGroup->map(function ($odd) {
                        return [
                            'bookmaker' => $odd->bookmaker_name ?? 'Unknown Bookmaker',
                            'odds' => (float) $odd->odds_value,
                            'source' => $odd->source->name ?? 'Unknown Source',
                            'source_id' => $odd->odds_source_id,
                        ];
                    })->unique('bookmaker')->values(),
                    'best_odds' => (float) $oddsGroup->max('odds_value'),
                ];
            })->values();

            return [
                'id' => $match->id,
                'external_id' => $match->external_id,
                'match' => "{$match->home_team} vs {$match->away_team}",
                'home_team' => $match->home_team,
                'away_team' => $match->away_team,
                'league' => $match->league,
                'date' => $match->commence_time->format('Y-m-d H:i'),
                'status' => $match->status,
                'odds' => $odds,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedMatches,
            'total' => $matches->total(),
            'current_page' => $matches->currentPage(),
            'per_page' => $matches->perPage(),
        ]);
    }

    /**
     * Get odds for a specific match
     */
    public function show($id)
    {
        $match = SportsMatch::with(['odds' => function ($query) {
            $query->with('source')->orderBy('odds_type')->orderBy('odds_value', 'desc');
        }])->find($id);

        if (!$match) {
            return response()->json([
                'success' => false,
                'message' => 'Match not found',
            ], 404);
        }

        // Group odds by type, showing all bookmakers from all sources
        $odds = $match->odds->groupBy('odds_type')->map(function ($oddsGroup) {
            return [
                'type' => $oddsGroup->first()->odds_type,
                'name' => $this->getOddsTypeName($oddsGroup->first()->odds_type),
                'best_odds' => (float) $oddsGroup->max('odds_value'),
                'bookmakers' => $oddsGroup->map(function ($odd) {
                    return [
                        'bookmaker' => $odd->bookmaker_name ?? 'Unknown Bookmaker',
                        'odds' => (float) $odd->odds_value,
                        'source' => $odd->source->name ?? 'Unknown Source',
                        'source_id' => $odd->odds_source_id,
                        'updated_at' => $odd->updated_at->format('Y-m-d H:i:s'),
                    ];
                })->unique('bookmaker')->values(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $match->id,
                'match' => "{$match->home_team} vs {$match->away_team}",
                'home_team' => $match->home_team,
                'away_team' => $match->away_team,
                'league' => $match->league,
                'date' => $match->commence_time->format('Y-m-d H:i'),
                'status' => $match->status,
                'odds' => $odds,
            ],
        ]);
    }

    /**
     * Get odds type display name
     */
    private function getOddsTypeName(string $type): string
    {
        return match ($type) {
            '1' => 'Home Win',
            'X' => 'Draw',
            '2' => 'Away Win',
            default => 'Unknown',
        };
    }

    /**
     * Get available leagues
     */
    public function leagues()
    {
        $leagues = SportsMatch::select('league')
            ->where('status', 'scheduled')
            ->distinct()
            ->orderBy('league')
            ->pluck('league')
            ->map(function ($league) {
                return [
                    'name' => $league,
                    'display_name' => $this->getLeagueDisplayName($league),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $leagues,
        ]);
    }

    /**
     * Get league display name
     */
    private function getLeagueDisplayName(string $league): string
    {
        return match ($league) {
            'Premier League' => 'Premier League',
            'La Liga' => 'La Liga',
            'Bundesliga' => 'Bundesliga',
            'Serie A' => 'Serie A',
            'Ligue 1' => 'Ligue 1',
            'Champions League' => 'UEFA Champions League',
            'Europa League' => 'UEFA Europa League',
            default => $league,
        };
    }
}

