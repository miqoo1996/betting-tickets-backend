<?php

namespace App\Http\Controllers;

use App\Models\SportsMatch;
use App\Models\MatchOdd;
use Illuminate\Http\Request;

class OddsController extends Controller
{
    /**
     * Get all available football odds
     */
    public function index(Request $request)
    {
        // Get upcoming scheduled matches with their odds
        $matches = SportsMatch::where('status', 'scheduled')
            ->where('league', 'Premier League') // You can parameterize this
            ->orderBy('commence_time', 'asc')
            ->with(['odds' => function ($query) {
                // Get odds from all sources (or latest)
                $query->select('match_odds.*')
                    ->groupBy('match_id', 'odds_type')
                    ->orderBy('created_at', 'desc');
            }])
            ->paginate(20);

        // Transform matches into the format expected by the frontend
        $formattedMatches = $matches->getCollection()->map(function ($match) {
            $odds = $match->odds->groupBy('odds_type')->map(function ($oddsGroup) {
                $latestOdd = $oddsGroup->first();
                return [
                    'type' => $latestOdd->odds_type,
                    'name' => $this->getOddsTypeName($latestOdd->odds_type),
                    'odds' => (float) $latestOdd->odds_value,
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
        $match = SportsMatch::with('odds')->find($id);

        if (!$match) {
            return response()->json([
                'success' => false,
                'message' => 'Match not found',
            ], 404);
        }

        // Group odds by type and get the latest from each source
        $odds = $match->odds->groupBy('odds_type')->map(function ($oddsGroup) {
            $latestOdd = $oddsGroup->first();
            return [
                'type' => $latestOdd->odds_type,
                'name' => $this->getOddsTypeName($latestOdd->odds_type),
                'odds' => (float) $latestOdd->odds_value,
                'sources' => $oddsGroup->map(function ($odd) {
                    return [
                        'source' => $odd->source->name ?? 'Unknown',
                        'value' => (float) $odd->odds_value,
                    ];
                })->values(),
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
}

