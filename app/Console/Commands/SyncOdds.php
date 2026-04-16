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
    protected $signature = 'odds:sync {--source= : Specific odds source ID to sync} {--sample : Use sample data instead of API} {--force : Force sync even if recently synced}';

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

        $this->info('🚀 Starting odds synchronization...');
        $this->newLine();

        $service = new OddsApiService();
        $totalMatches = 0;
        $totalOdds = 0;
        $successCount = 0;

        foreach ($sources as $source) {
            $this->info("📡 Syncing from: {$source->name}");
            logger()->info("Processing source: {$source->name} with ID: {$source->id}");

            // Check if using demo key
            if ($source->api_key === 'demo_key') {
                $this->warn('⚠️  Using demo API key. Get a real key from the-odds-api.com for full functionality.');
            }

            $this->line("   URL: {$source->api_url}");
            $this->line("   Last synced: " . ($source->last_synced_at ? $source->last_synced_at->diffForHumans() : 'Never'));

            $startTime = microtime(true);

            try {
                logger()->info("Calling service->syncFootballOdds for {$source->name}");
                $result = $service->syncFootballOdds($source);
                logger()->info("Result for {$source->name}: " . json_encode($result));

                $endTime = microtime(true);
                $duration = round($endTime - $startTime, 2);

                if ($result['status'] === 'success') {
                    $this->info("   ✅ Success! Synced {$result['matches_synced']} matches with {$result['odds_synced']} odds ({$duration}s)");
                    $totalMatches += $result['matches_synced'];
                    $totalOdds += $result['odds_synced'];
                    $successCount++;
                } else {
                    $this->error("   ❌ Failed: {$result['error']} ({$duration}s)");
                }
            } catch (\Exception $e) {
                $endTime = microtime(true);
                $duration = round($endTime - $startTime, 2);
                $this->error("   💥 Error: " . $e->getMessage() . " ({$duration}s)");
                logger()->error("Exception syncing {$source->name}: " . $e->getMessage());
            }

            $this->newLine();
        }

        // Summary
        $this->line('📊 Sync Summary:');
        $this->line("   Sources processed: {$sources->count()}");
        $this->line("   Successful syncs: {$successCount}");
        $this->line("   Total matches: {$totalMatches}");
        $this->line("   Total odds: {$totalOdds}");

        if ($successCount > 0) {
            $this->info('🎉 Synchronization completed successfully!');
            return Command::SUCCESS;
        } else {
            $this->error('❌ All syncs failed. Check your API key and network connection.');
            return Command::FAILURE;
        }
    }
}
