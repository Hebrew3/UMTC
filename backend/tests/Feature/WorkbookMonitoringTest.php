<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class WorkbookMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_imports_the_entire_workbook_and_skips_conflicting_duplicates(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        Portfolio::query()->create([
            'account_number' => 'A-003',
            'account_name' => 'Existing record is preserved',
            'address' => 'Existing address',
            'is_current' => true,
        ]);
        $file = UploadedFile::fake()->createWithContent('monitoring.xlsx', $this->workbookBytes());

        $preview = $this->actingAs($admin)->postJson('/api/imports/preview', ['file' => $file]);
        $preview->assertCreated()
            ->assertJsonPath('counts.total_rows', 5)
            ->assertJsonPath('counts.valid_rows', 2)
            ->assertJsonPath('counts.conflicting_accounts', 1)
            ->assertJsonPath('counts.matched_accounts', 2);

        $importId = $preview->json('importId');
        $this->postJson("/api/imports/{$importId}/confirm")
            ->assertOk()
            ->assertJsonPath('counts.successful', 2)
            ->assertJsonPath('counts.duplicate', 1)
            ->assertJsonPath('counts.skipped', 1);

        $this->assertDatabaseHas('portfolios', [
            'account_number' => 'A-001',
            'account_name' => 'Account One',
            'cluster' => 'SOUTH',
            'assignment_status' => 'matched',
            'latest_status' => 'NO UPDATES YET',
            'is_current' => true,
        ]);
        $this->assertDatabaseHas('portfolios', [
            'account_number' => 'A-002',
            'account_name' => 'Account Two',
            'is_current' => true,
        ]);
        $this->assertDatabaseHas('portfolios', [
            'account_number' => 'A-003',
            'account_name' => 'Existing record is preserved',
            'is_current' => true,
        ]);
    }

    public function test_data_input_agent_is_assigned_while_address_reference_sets_the_location(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $file = UploadedFile::fake()->createWithContent('monitoring.xlsx', $this->workbookBytes('Data Input Agent', 'Reference Agent'));

        $preview = $this->actingAs($admin)->postJson('/api/imports/preview', ['file' => $file]);
        $preview->assertCreated()
            ->assertJsonPath('counts.agent_conflicts', 2)
            ->assertJsonPath('sampleRows.0.location', 'Test City')
            ->assertJsonPath('sampleRows.0.province', 'Test Province');

        $this->postJson('/api/imports/'.$preview->json('importId').'/confirm')
            ->assertOk()
            ->assertJsonPath('counts.agent_conflicts', 2);

        $portfolio = Portfolio::query()->where('account_number', 'A-001')->with('agent')->firstOrFail();
        $this->assertSame('Data Input Agent', $portfolio->agent->agent_name);
        $this->assertSame('SOUTH', $portfolio->cluster);
        $this->assertSame('Test City', $portfolio->city);
        $this->assertSame('Test Province', $portfolio->province);
        $this->assertSame('agent_conflict', $portfolio->assignment_status);

        $this->getJson('/api/locations/resolve?address=10%20Main%20Street%2C%20Test%20City%2C%20Test%20Province')
            ->assertOk()
            ->assertJsonPath('status', 'resolved')
            ->assertJsonPath('cluster', 'SOUTH')
            ->assertJsonPath('location', 'Test City')
            ->assertJsonPath('province', 'Test Province');
    }

    public function test_reimport_keeps_successful_accounts_current_and_archives_absent_accounts(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        Portfolio::query()->create([
            'account_number' => 'OLD-ACCOUNT',
            'account_name' => 'Not in the workbook',
            'address' => 'Old address',
            'is_current' => true,
        ]);

        foreach (range(1, 2) as $attempt) {
            $file = UploadedFile::fake()->createWithContent('monitoring.xlsx', $this->workbookBytes());
            $preview = $this->actingAs($admin)->postJson('/api/imports/preview', ['file' => $file]);
            $this->postJson('/api/imports/'.$preview->json('importId').'/confirm')->assertOk();
        }

        $this->assertDatabaseHas('portfolios', ['account_number' => 'A-001', 'is_current' => true]);
        $this->assertDatabaseHas('portfolios', ['account_number' => 'A-002', 'is_current' => true]);
        $this->assertDatabaseHas('portfolios', ['account_number' => 'OLD-ACCOUNT', 'is_current' => false]);
    }

    public function test_senior_agent_data_is_limited_to_admin_assigned_agents(): void
    {
        $senior = User::factory()->create(['role' => 'senior_agent']);
        $assigned = Agent::query()->create(['agent_id' => 'FA-1', 'agent_name' => 'Assigned Agent', 'assigned_cluster' => 'SOUTH']);
        $other = Agent::query()->create(['agent_id' => 'FA-2', 'agent_name' => 'Other Agent', 'assigned_cluster' => 'NORTH']);
        $senior->agents()->attach($assigned->id);
        Portfolio::query()->create([
            'account_number' => 'OWN-1',
            'account_name' => 'Assigned account',
            'address' => 'Assigned address',
            'assigned_agent_id' => $assigned->id,
            'is_current' => true,
        ]);
        Portfolio::query()->create([
            'account_number' => 'OTHER-1',
            'account_name' => 'Other account',
            'address' => 'Other address',
            'assigned_agent_id' => $other->id,
            'is_current' => true,
        ]);

        $this->actingAs($senior)->getJson('/api/portfolios')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.account_number', 'OWN-1');
        $this->postJson('/api/imports/preview')
            ->assertForbidden();
    }

    public function test_admin_can_filter_agents_by_cluster_status_and_search_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Agent::query()->create([
            'agent_id' => 'FA-SOUTH-1',
            'agent_name' => 'South Active',
            'assigned_cluster' => 'SOUTH',
            'assigned_area' => 'Laguna',
            'status' => 'active',
        ]);
        Agent::query()->create([
            'agent_id' => 'FA-NORTH-1',
            'agent_name' => 'North Inactive',
            'assigned_cluster' => 'NORTH',
            'assigned_area' => 'Baguio',
            'status' => 'inactive',
        ]);

        $this->actingAs($admin)->getJson('/api/agents?cluster=SOUTH&status=active&search=Laguna')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.agent_id', 'FA-SOUTH-1');
    }

    public function test_session_login_and_inactive_user_enforcement(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('A-strong-password-123'),
            'role' => 'admin',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'admin@example.test',
            'password' => 'A-strong-password-123',
        ])->assertOk()->assertJsonPath('user.role', 'admin');
        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('user.id', $user->id);

        $user->update(['is_active' => false]);
        Auth::forgetGuards();
        $this->getJson('/api/dashboard/stats')->assertForbidden();
    }

    public function test_admin_can_create_and_export_a_portfolio_using_live_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $agent = Agent::query()->create([
            'agent_id' => 'FA-SOUTH-1',
            'agent_name' => 'South Agent',
            'assigned_cluster' => 'SOUTH',
        ]);

        $this->actingAs($admin)->postJson('/api/portfolios', [
            'account_number' => 'NEW-ACCOUNT',
            'account_name' => 'Example Client',
            'address' => '10 Example Street',
            'cluster' => 'SOUTH',
            'assigned_agent_id' => $agent->id,
            'product_line' => 'Finance',
            'latest_status' => 'NO UPDATES YET',
        ])->assertCreated();

        $this->getJson('/api/monitoring/filters')
            ->assertOk()
            ->assertJsonFragment(['SOUTH'])
            ->assertJsonFragment(['agent_name' => 'South Agent']);

        $response = $this->get('/api/portfolios/export?cluster=SOUTH');
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('NEW-ACCOUNT', $csv);
        $this->assertStringContainsString('Example Client', $csv);
    }

    private function workbookBytes(string $dataAgentName = 'South Agent', string $referenceAgentName = 'South Agent'): string
    {
        $spreadsheet = new Spreadsheet();
        $data = $spreadsheet->getActiveSheet();
        $data->setTitle('Data Input');
        $headers = ['ON HOLD', 'AREA', 'AGENT', 'HAS TASK', 'ACCOUNT NUMBER', 'ACCOUNT NAME', 'OB', 'DATE ENDORSED', 'DAYS HANDLED', 'EXP DATE', 'EXPIRED', 'LATEST STATUS', 'PRODUCT LINE', 'CLIENT', 'SERVICE TYPE', 'CLASSIFICATION', 'MONTHLY AMORT', 'DPD', 'PARTICULARS', 'ADDRESS', 'CONTACT', 'UPCOMING TASK'];
        $data->fromArray(['Monitoring data'], null, 'A1');
        $data->fromArray($headers, null, 'A2');
        $rows = [
            ['NO', 'SOUTH LUZON', $dataAgentName, 'YES', 'A-001', 'Account One', null, '2026-01-01', 10, null, null, 'NO UPDATES YET', 'Mortgage', 'Client', 'Standard', 'A', null, 0, null, '10 Main Street, Test City, Test Province', null, null],
            ['NO', 'SOUTH LUZON', $dataAgentName, 'YES', 'A-002', 'Account Two', null, '2026-01-01', 10, null, null, 'NO UPDATES YET', 'Mortgage', 'Client', 'Standard', 'A', null, 0, null, '20 Main Street, Test City, Test Province', null, null],
            ['NO', 'SOUTH LUZON', $dataAgentName, 'YES', 'A-002', 'Account Two', null, '2026-01-01', 10, null, null, 'NO UPDATES YET', 'Mortgage', 'Client', 'Standard', 'A', null, 0, null, '20 Main Street, Test City, Test Province', null, null],
            ['NO', 'SOUTH LUZON', $dataAgentName, 'YES', 'A-003', 'Conflicting Name One', null, '2026-01-01', 10, null, null, 'NO UPDATES YET', 'Mortgage', 'Client', 'Standard', 'A', null, 0, null, '30 Main Street, Test City, Test Province', null, null],
            ['NO', 'SOUTH LUZON', $dataAgentName, 'YES', 'A-003', 'Conflicting Name Two', null, '2026-01-01', 10, null, null, 'NO UPDATES YET', 'Mortgage', 'Client', 'Standard', 'A', null, 0, null, '30 Main Street, Test City, Test Province', null, null],
        ];
        $data->fromArray($rows, null, 'A3');

        foreach (['NCR', 'NORTH', 'SOUTH', 'VISAYAS', 'MINDANAO'] as $cluster) {
            $sheet = $cluster === 'NCR' ? $spreadsheet->createSheet() : $spreadsheet->createSheet();
            $sheet->setTitle($cluster);
            $sheet->fromArray(['LOCATION', 'CITY PROVINCE', 'COVERAGE', 'ASSIGN FA'], null, 'A1');
            if ($cluster === 'SOUTH') {
                $sheet->fromArray(['Test City', 'Test Province', 'Test Coverage', $referenceAgentName], null, 'A2');
            }
        }

        $temporary = tempnam(sys_get_temp_dir(), 'umtc-workbook-');
        try {
            (new Xlsx($spreadsheet))->save($temporary);
            return (string) file_get_contents($temporary);
        } finally {
            @unlink($temporary);
            $spreadsheet->disconnectWorksheets();
        }
    }
}
