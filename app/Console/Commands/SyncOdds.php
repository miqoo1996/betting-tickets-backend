<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OddsSource;
use App\Services\OddsApiService;

class SyncOdds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'odds:sync {--source= : Specific odds source ID to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync football odds from configured API sources';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sourceId = $this->option('source');

        // Get active sources
        $query = OddsSource::where('is_active', true);

        if ($sourceId) {
            $query->where('id', $sourceId);
        }

        $sources = $query->get();

        if ($sources->isEmpty()) {
            $this->error('No active odds sources found.');
            return Command::FAILURE;
        }

        $service = new OddsApiService();

        foreach ($sources as $source) {
            $this->info("Syncing odds from: {$source->name}");

            try {
                $result = $service->syncFootballOdds($source);

                if ($result['success']) {
                    $this->info("✓ Synced {$result['matches_synced']} matches with {$result['odds_synced']} odds");
                } else {
                    $this->error("✗ Sync failed: {$result['error']}");
                }
            } catch (\Exception $e) {
                $this->error("Error syncing {$source->name}: " . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
