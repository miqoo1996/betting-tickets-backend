<?php

namespace Database\Seeders;

use App\Console\Commands\MatchOdd;
use App\Models\OddsSource;
use App\Models\SportsMatch;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SportsMatchesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first odds source (the-odds-api.com)
        $oddsSource = OddsSource::first();

        if (!$oddsSource) {
            return;
        }

        $matches = [
            [
                'home_team' => 'Manchester United',
                'away_team' => 'Liverpool',
                'league' => 'Premier League',
                'commence_time' => Carbon::now()->addDays(3)->setTime(15, 0),
                'odds' => [
                    ['type' => '1', 'value' => 2.10],
                    ['type' => 'X', 'value' => 3.50],
                    ['type' => '2', 'value' => 3.20],
                ],
            ],
            [
                'home_team' => 'Chelsea',
                'away_team' => 'Arsenal',
                'league' => 'Premier League',
                'commence_time' => Carbon::now()->addDays(3)->setTime(17, 30),
                'odds' => [
                    ['type' => '1', 'value' => 2.35],
                    ['type' => 'X', 'value' => 3.25],
                    ['type' => '2', 'value' => 2.95],
                ],
            ],
            [
                'home_team' => 'Manchester City',
                'away_team' => 'Tottenham',
                'league' => 'Premier League',
                'commence_time' => Carbon::now()->addDays(4)->setTime(13, 0),
                'odds' => [
                    ['type' => '1', 'value' => 1.65],
                    ['type' => 'X', 'value' => 4.00],
                    ['type' => '2', 'value' => 5.00],
                ],
            ],
            [
                'home_team' => 'Real Madrid',
                'away_team' => 'Barcelona',
                'league' => 'La Liga',
                'commence_time' => Carbon::now()->addDays(4)->setTime(20, 30),
                'odds' => [
                    ['type' => '1', 'value' => 1.95],
                    ['type' => 'X', 'value' => 3.80],
                    ['type' => '2', 'value' => 3.50],
                ],
            ],
            [
                'home_team' => 'Bayern Munich',
                'away_team' => 'Borussia Dortmund',
                'league' => 'Bundesliga',
                'commence_time' => Carbon::now()->addDays(5)->setTime(15, 30),
                'odds' => [
                    ['type' => '1', 'value' => 1.58],
                    ['type' => 'X', 'value' => 4.20],
                    ['type' => '2', 'value' => 5.50],
                ],
            ],
            [
                'home_team' => 'AC Milan',
                'away_team' => 'Inter Milan',
                'league' => 'Serie A',
                'commence_time' => Carbon::now()->addDays(5)->setTime(18, 0),
                'odds' => [
                    ['type' => '1', 'value' => 2.40],
                    ['type' => 'X', 'value' => 3.30],
                    ['type' => '2', 'value' => 2.90],
                ],
            ],
        ];

        foreach ($matches as $matchData) {
            $odds = $matchData['odds'];
            unset($matchData['odds']);

            // Create match with external_id
            $matchData['external_id'] = uniqid('match_');
            $matchData['status'] = 'scheduled';
            $matchData['synced_at'] = now();

            $match = SportsMatch::create($matchData);

            // Create odds for this match
            foreach ($odds as $odd) {
                MatchOdd::create([
                    'match_id' => $match->id,
                    'odds_source_id' => $oddsSource->id,
                    'odds_type' => $odd['type'],
                    'odds_value' => $odd['value'],
                    'bookmaker_name' => 'Sample Bookmaker',
                ]);
            }
        }
    }
}

