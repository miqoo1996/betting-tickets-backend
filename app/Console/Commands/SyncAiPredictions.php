<?php

namespace App\Console\Commands;

use App\Services\AiChatBots\GeminiApiService;
use Illuminate\Console\Command;
use App\Models\SportsMatch;
use App\Models\AiPrediction;

class SyncAiPredictions extends Command
{
    protected $signature = 'ai:sync-predictions {--limit= : Number of matches to process} {--featured-only : Only process featured matches}';

    protected $description = 'Sync AI predictions for upcoming matches and save to DB';

    public function handle()
    {
        $limit = $this->option('limit') ? intval($this->option('limit')) : null;
        $featuredOnly = $this->option('featured-only');

        $query = SportsMatch::query()
            ->with(['odds'])
            ->where('status', 'scheduled')
            ->whereDoesntHave('predictions', function ($query) {
                $query
                    ->where('synced_at', '>', now()->subHours(4)->toDateTimeString());
            })
            ->orderByDesc('id');

        if ($featuredOnly) {
            $query->where('commence_time', '>', now());
        }

        if ($limit) {
            $matches = $query->limit($limit)->get();
        } else {
            $matches = $query->get();
        }

        $this->info('Processing ' . $matches->count() . ' matches');

        $service = app(GeminiApiService::class);

        foreach ($matches as $match) {
            $teams = "{$match->home_team} vs {$match->away_team}";
            $eventDate = $match->commence_time->format('Y-m-d H:i');

            // Build bookmakers string
            $bookmakers = $match->odds->map(function ($odd) {
                return ($odd->bookmaker_name ?? 'Unknown') . ': ' . ($odd->odds_value ?? '');
            })->unique()->values()->implode(' | ');

            $prompt = str_replace([
                ':::teams:::',
                ':::event_date:::',
                ':::bookmakers_prediction:::',
            ], [
                $teams,
                $eventDate,
                $bookmakers,
            ], config('ai-bots.prompts.footbal-match-predictions'));

            $this->line("Prompting AI for match: {$teams}");

            $response = $service->prompt($prompt);

            if (empty($response['response'])) {
                $this->error('No response from chat bot.');
                continue;
            }

            if (empty($response['success'])) {
                $this->error('Error: ' . $response['response']);
                continue;
            }

            AiPrediction::query()->updateOrCreate(['sports_match_id' => $match->id], [
                'sports_match_id' => $match->id,
                'prompt' => trim($prompt),
                'response' => trim($response['response']),
                'success' => $response['success'],
                'meta' => $response['raw'] ?? ['error' => $response['error'] ?? null],
                'synced_at' => now()
            ]);
        }

        $this->info('AI sync completed.');

        return 0;
    }
}
