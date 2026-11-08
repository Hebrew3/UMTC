<?php

namespace Database\Seeders;

use App\Models\Agent;
use Illuminate\Database\Seeder;

class AgentSeeder extends Seeder
{
    public function run(): void
    {
        $agents = [
            ['agent_id' => 'AG-1001', 'agent_name' => 'Bernie Autida', 'contact_number' => '09171234567', 'assigned_cluster' => 'NCR', 'assigned_area' => 'Quezon City', 'status' => 'active', 'date_created' => '2024-01-15'],
            ['agent_id' => 'AG-1002', 'agent_name' => 'Maria Santos', 'contact_number' => '09181234567', 'assigned_cluster' => 'NORTH', 'assigned_area' => 'Bulacan', 'status' => 'active', 'date_created' => '2024-02-20'],
            ['agent_id' => 'AG-1003', 'agent_name' => 'Alden Cruz', 'contact_number' => '09221234567', 'assigned_cluster' => 'SOUTH', 'assigned_area' => 'Laguna', 'status' => 'active', 'date_created' => '2024-03-11'],
            ['agent_id' => 'AG-1004', 'agent_name' => 'Cynthia Reyes', 'contact_number' => '09331234567', 'assigned_cluster' => 'VISAYAS', 'assigned_area' => 'Cebu', 'status' => 'active', 'date_created' => '2024-04-10'],
            ['agent_id' => 'AG-1005', 'agent_name' => 'Joel Dumaguing', 'contact_number' => '09441234567', 'assigned_cluster' => 'MINDANAO', 'assigned_area' => 'Davao', 'status' => 'active', 'date_created' => '2024-05-11'],
        ];

        foreach ($agents as $agent) {
            Agent::query()->updateOrCreate(['agent_id' => $agent['agent_id']], $agent);
        }
    }
}
