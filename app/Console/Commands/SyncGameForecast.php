<?php

namespace App\Console\Commands;

use App\Models\GfEvent;
use App\Models\GfLeague;
use App\Models\GfPrediction;
use App\Models\GfTeam;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SyncGameForecast extends Command
{
    protected $signature = 'gf:sync
                            {--page= : Fetch only this specific page (omit to fetch all pages)}
                            {--page-size=50 : Events per page (max 50)}';

    protected $description = 'Fetch UPCOMING events & predictions from GameForecastAPI';

    private string $apiKey;
    private array $headers;

    public function handle(): int
    {
        $this->apiKey = config('services.game_forecast.key');
        $this->headers = [
            'X-RapidAPI-Key'  => $this->apiKey,
            'X-RapidAPI-Host' => 'game-forecast-api.p.rapidapi.com',
        ];

        $this->info('Syncing leagues...');
        $leaguesSynced = $this->syncLeagues();
        $this->line("  → {$leaguesSynced} leagues upserted");

        $this->info('Syncing UPCOMING events...');
        [$eventsSynced, $predictionsSynced] = $this->syncEvents();
        $this->line("  → {$eventsSynced} events upserted");
        $this->line("  → {$predictionsSynced} predictions upserted");

        $this->info('Done.');
        return self::SUCCESS;
    }

    private function syncLeagues(): int
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->get('https://game-forecast-api.p.rapidapi.com/leagues');
        } catch (ConnectionException $e) {
            $this->error('Connection failed: ' . $e->getMessage());
            return 0;
        }

        if (!$response->ok()) {
            $this->error('API error ' . $response->status() . ': ' . $response->body());
            return 0;
        }

        $count = 0;
        foreach ($response->json('data', []) as $l) {
            GfLeague::updateOrCreate(
                ['external_id' => $l['id']],
                [
                    'name'         => $l['name'],
                    'country_code' => $l['country_code'] ?? null,
                    'type'         => $l['type'] ?? 'league',
                    'women'        => $l['women'] ?? false,
                ]
            );
            $count++;
        }

        return $count;
    }

    private function syncEvents(): array
    {
        $pageSize   = min(50, (int) $this->option('page-size'));
        $fixedPage  = $this->option('page');

        $totalEvents = 0;
        $totalPreds  = 0;
        $page        = $fixedPage !== null ? (int) $fixedPage : 1;

        do {
            $this->line("  Fetching page {$page}...");

            try {
                $response = Http::withHeaders($this->headers)
                    ->get('https://game-forecast-api.p.rapidapi.com/events', [
                        'page'        => $page,
                        'page_size'   => $pageSize,
                        'from'        => now()->toIso8601String(), // ✅ fetch future
                        'status'      => 'not_started',            // ✅ filter upcoming
                    ]);
            } catch (ConnectionException $e) {
                $this->error('Connection failed: ' . $e->getMessage());
                break;
            }

            if (!$response->ok()) {
                $this->error('API error ' . $response->status() . ': ' . $response->body());
                break;
            }

            $events = $response->json('data', []);

            [$evCount, $prCount] = $this->persistEvents($events);
            $totalEvents += $evCount;
            $totalPreds  += $prCount;

            $hasMore = $fixedPage === null && count($events) === $pageSize;
            $page++;

        } while ($hasMore);

        return [$totalEvents, $totalPreds];
    }

    private function persistEvents(array $events): array
    {
        $eventCount = 0;
        $predCount  = 0;

        foreach ($events as $apiEvent) {

            // ✅ Fallback filter (VERY IMPORTANT)
            if (!empty($apiEvent['start_at']) &&
                strtotime($apiEvent['start_at']) <= now()->timestamp) {
                continue; // skip past/finished games
            }

            $league = GfLeague::firstOrCreate(
                ['external_id' => $apiEvent['league']['id']],
                ['name' => $apiEvent['league']['name']]
            );

            $homeTeam = GfTeam::updateOrCreate(
                ['external_id' => $apiEvent['team_home']['id']],
                ['name' => $apiEvent['team_home']['name']]
            );

            $awayTeam = GfTeam::updateOrCreate(
                ['external_id' => $apiEvent['team_away']['id']],
                ['name' => $apiEvent['team_away']['name']]
            );

            $event = GfEvent::updateOrCreate(
                ['external_id' => $apiEvent['id']],
                [
                    'league_id'      => $league->id,
                    'home_team_id'   => $homeTeam->id,
                    'away_team_id'   => $awayTeam->id,
                    'status_code'    => $apiEvent['status_code'],
                    'round'          => $apiEvent['round'] ?? null,
                    'referee'        => $apiEvent['referee'] ?? null,
                    'start_at'       => $apiEvent['start_at'] ?? null,
                    'score'          => $apiEvent['score'] ?? null,
                    'bookmaker_odds' => $apiEvent['odds'] ?? null,
                    'synced_at'      => now(),
                ]
            );

            $eventCount++;

            $p = $apiEvent['predictions'][0] ?? null;
            if ($p) {
                GfPrediction::updateOrCreate(
                    ['event_id' => $event->id],
                    [
                        'match_result'      => $p['match_result'] ?? null,
                        'total_goals'       => $p['total_goals'] ?? null,
                        'home_team_goals'   => $p['home_team_goals'] ?? null,
                        'away_team_goals'   => $p['away_team_goals'] ?? null,
                        'both_teams_score'  => $p['both_teams_score'] ?? null,
                        'first_half_winner' => $p['first_half_winner'] ?? null,
                        'exact_score'       => $p['exact_score'] ?? null,
                        'recommended_bets'  => $p['recommended_bets'] ?? null,
                        'reasoning'         => $p['reasoning'] ?? null,
                    ]
                );
                $predCount++;
            }
        }

        return [$eventCount, $predCount];
    }
}
