<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\FieldworkSchedule;
use App\Models\Portfolio;
use Illuminate\Database\Seeder;

class FieldworkSeeder extends Seeder
{
    public function run(): void
    {
        $agents = Agent::query()->pluck('id', 'agent_name');
        $portfolio = Portfolio::query()->pluck('id', 'account_number');

        $schedules = [
            ['title' => 'Check-in: Sps. Gilbert Z. Shitan', 'portfolio_id' => $portfolio['ACC-1001'], 'agent_id' => $agents['Bernie Autida'], 'schedule_date' => now()->addDays(2)->toDateString(), 'status' => 'pending', 'location' => 'Quezon City', 'notes' => 'Review account updates'],
            ['title' => 'Review: Bahayang Mahal', 'portfolio_id' => $portfolio['ACC-1002'], 'agent_id' => $agents['Maria Santos'], 'schedule_date' => now()->addDays(4)->toDateString(), 'status' => 'pending', 'location' => 'Bulacan', 'notes' => 'Resolve verification issue'],
            ['title' => 'Assessment: Riverside Estates', 'portfolio_id' => $portfolio['ACC-1003'], 'agent_id' => $agents['Alden Cruz'], 'schedule_date' => now()->subDays(2)->toDateString(), 'status' => 'completed', 'location' => 'Laguna', 'notes' => 'Assessment completed'],
            ['title' => 'Monitoring: Grand Ocean Foods', 'portfolio_id' => $portfolio['ACC-1004'], 'agent_id' => $agents['Cynthia Reyes'], 'schedule_date' => now()->subDays(12)->toDateString(), 'status' => 'cancelled', 'location' => 'Cebu', 'notes' => 'Rescheduled due to weather'],
            ['title' => 'Portfolio review: SMDC Community', 'portfolio_id' => $portfolio['ACC-1005'], 'agent_id' => $agents['Joel Dumaguing'], 'schedule_date' => now()->addDays(8)->toDateString(), 'status' => 'pending', 'location' => 'Davao', 'notes' => 'Client follow-up'],
            ['title' => 'Visit: Makati Business Center', 'portfolio_id' => $portfolio['ACC-1006'], 'agent_id' => $agents['Bernie Autida'], 'schedule_date' => now()->addDays(11)->toDateString(), 'status' => 'pending', 'location' => 'Makati', 'notes' => 'Client onboarding'],
        ];

        foreach ($schedules as $schedule) {
            FieldworkSchedule::query()->updateOrCreate([
                'portfolio_id' => $schedule['portfolio_id'],
                'agent_id' => $schedule['agent_id'],
                'schedule_date' => $schedule['schedule_date'],
            ], $schedule);
        }
    }
}
