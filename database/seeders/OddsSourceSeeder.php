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
        // Update or create odds-api.io source
        OddsSource::updateOrCreate(
            ['name' => 'odds-api.io'],
            [
                'api_url' => 'https://api.odds-api.io/v3',
                'api_key' => env('ODDS_API_KEY', 'demo_key'),
                'is_active' => true,
                'sync_interval_minutes' => 60,
                'description' => 'Odds-API.io - Real-time sports betting odds from 250+ bookmakers',
            ]
        );

        // Update or create the-odds-api.com source
        OddsSource::updateOrCreate(
            ['name' => 'the-odds-api.com'],
            [
                'api_url' => 'https://api.the-odds-api.com/v4',
                'api_key' => env('THE_ODDS_API_KEY', env('ODDS_API_KEY')), // Allow separate key for the-odds-api.com
                'is_active' => true,
                'sync_interval_minutes' => 60,
                'description' => 'The Odds API - Sports betting odds from multiple bookmakers',
            ]
        );

        // Update or create api-football.com source
        OddsSource::updateOrCreate(
            ['name' => 'api-football.com'],
            [
                'api_url' => 'https://v3.football.api-sports.io',
                'api_key' => env('API_FOOTBALL_KEY', 'demo_key'),
                'is_active' => true,
                'sync_interval_minutes' => 60,
                'description' => 'API-Football.com - Football data and betting odds',
            ]
        );
    }
}

