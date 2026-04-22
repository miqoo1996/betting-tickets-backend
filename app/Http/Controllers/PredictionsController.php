<?php

namespace App\Http\Controllers;

use App\Models\GfEvent;
use App\Services\GameForecastService;
use Illuminate\Http\Request;

class PredictionsController extends Controller
{
    public function __construct(private GameForecastService $service) {}

    /**
     * List matches with AI predictions.
     * Filters: league_id (int), team_id (int), page (int), page_size (int)
     */
    public function index(Request $request)
    {
        $filters = array_filter([
            'league_id' => $request->integer('league_id') ?: null,
            'team_id'   => $request->integer('team_id') ?: null,
            'page'      => $request->integer('page', 1),
            'page_size' => $request->integer('page_size', 20),
        ]);

        $result = $this->service->getEvents($filters);

        $data = array_map(fn ($e) => $this->formatEvent($e, false), $result['data']);

        return response()->json([
            'success'      => true,
            'data'         => $data,
            'total'        => $result['total'],
            'current_page' => $result['page'],
            'per_page'     => $result['page_size'],
            'last_page'    => (int) ceil($result['total'] / $result['page_size']),
        ]);
    }

    /**
     * Get a single match with full AI predictions.
     */
    public function show(int $id)
    {
        $event = $this->service->getEvent($id);

        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Match not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatEvent($event, true),
        ]);
    }

    /**
     * List available leagues.
     */
    public function leagues()
    {
        $leagues = $this->service->getLeagues();

        $data = array_map(fn ($l) => [
            'id'           => $l['id'],
            'name'         => $l['name'],
            'country_code' => $l['country_code'] ?? null,
            'type'         => $l['type'] ?? 'league',
        ], $leagues);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * List all teams extracted from cached events (id + name).
     */
    public function teams()
    {
        $index = $this->service->getTeamIndex();

        $data = array_map(
            fn ($id, $name) => ['id' => $id, 'name' => $name],
            array_keys($index),
            array_values($index)
        );

        usort($data, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return response()->json(['success' => true, 'data' => $data]);
    }

    // -------------------------------------------------------------------------

    private function formatEvent(GfEvent $event, bool $full): array
    {
        $base = [
            'id'           => $event->external_id,
            'match'        => "{$event->homeTeam->name} vs {$event->awayTeam->name}",
            'home_team'    => $event->homeTeam->name,
            'home_team_id' => $event->homeTeam->external_id,
            'away_team'    => $event->awayTeam->name,
            'away_team_id' => $event->awayTeam->external_id,
            'league'       => $event->league->name,
            'league_id'    => $event->league->external_id,
            'date'         => $event->start_at?->toISOString(),
            'status'       => $event->status_code,
            'round'        => $event->round,
        ];

        if ($event->prediction) {
            $base['ai_predictions'] = $this->formatPredictions($event->prediction, $full);
        }

        return $base;
    }

    private function formatPredictions(\App\Models\GfPrediction $p, bool $full): array
    {
        $matchResult = $p->match_result ?? [];
        $totalGoals  = $p->total_goals ?? [];
        $btts        = $p->both_teams_score ?? [];

        $result = [
            'match_result' => [
                'home' => [
                    'probability'  => $matchResult['home'] ?? null,
                    'implied_odds' => $this->toImpliedOdds($matchResult['home'] ?? null),
                ],
                'draw' => [
                    'probability'  => $matchResult['draw'] ?? null,
                    'implied_odds' => $this->toImpliedOdds($matchResult['draw'] ?? null),
                ],
                'away' => [
                    'probability'  => $matchResult['away'] ?? null,
                    'implied_odds' => $this->toImpliedOdds($matchResult['away'] ?? null),
                ],
            ],
            'both_teams_score' => [
                'yes' => $btts['yes'] ?? null,
                'no'  => $btts['no'] ?? null,
            ],
            'total_goals' => array_filter([
                'over_0_5'  => $totalGoals['over_0_5'] ?? null,
                'over_1_5'  => $totalGoals['over_1_5'] ?? null,
                'over_2_5'  => $totalGoals['over_2_5'] ?? null,
                'over_3_5'  => $totalGoals['over_3_5'] ?? null,
                'under_2_5' => $totalGoals['under_2_5'] ?? null,
                'under_3_5' => $totalGoals['under_3_5'] ?? null,
            ], fn ($v) => $v !== null),
            'recommended_bets' => $this->formatRecommendedBets($p->recommended_bets ?? []),
            'reasoning'        => $p->reasoning['en'] ?? null,
        ];

        if ($full) {
            $result['home_team_goals'] = $p->home_team_goals;
            $result['away_team_goals'] = $p->away_team_goals;
            $result['exact_score']     = $p->exact_score;
        }

        return $result;
    }

    private function toImpliedOdds(?int $probability): ?float
    {
        if (!$probability || $probability <= 0) {
            return null;
        }

        return round(100 / $probability, 2);
    }

    private function formatRecommendedBets(array $bets): array
    {
        $labels = [
            'matchResult.homeWinProbability' => 'Home Win',
            'matchResult.drawProbability'    => 'Draw',
            'matchResult.awayWinProbability' => 'Away Win',
            'totalGoals.over0_5'             => 'Over 0.5 Goals',
            'totalGoals.over1_5'             => 'Over 1.5 Goals',
            'totalGoals.over2_5'             => 'Over 2.5 Goals',
            'totalGoals.over3_5'             => 'Over 3.5 Goals',
            'totalGoals.under2_5'            => 'Under 2.5 Goals',
            'totalGoals.under3_5'            => 'Under 3.5 Goals',
            'homeTeamGoals.over0_5'          => 'Home Team to Score',
            'homeTeamGoals.over1_5'          => 'Home Team Over 1.5 Goals',
            'awayTeamGoals.over0_5'          => 'Away Team to Score',
            'awayTeamGoals.over1_5'          => 'Away Team Over 1.5 Goals',
            'bothTeamsScore.yes'             => 'Both Teams to Score',
            'bothTeamsScore.no'              => 'Clean Sheet',
        ];

        return array_values(array_map(
            fn ($key) => $labels[$key] ?? $key,
            array_values($bets)
        ));
    }
}
