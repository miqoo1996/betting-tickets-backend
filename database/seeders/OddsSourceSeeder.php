<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\OddsSource;

class OddsSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        OddsSource::create([
            'name' => 'the-odds-api.com',
            'api_url' => 'https://api.the-odds-api.com/v4',
            'api_key' => env('ODDS_API_KEY', 'demo_key'),
            'is_active' => true,
            'sync_interval_minutes' => 60,
            'description' => 'The Odds API - Provides real-time sports odds from multiple bookmakers',
        ]);
    }
}

