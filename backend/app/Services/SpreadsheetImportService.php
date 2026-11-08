<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\ClusterReference;
use App\Models\ImportRecord;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class SpreadsheetImportService
{
    private const CLUSTERS = ['NCR', 'NORTH', 'SOUTH', 'VISAYAS', 'MINDANAO'];

    private const REQUIRED_COLUMNS = ['ACCOUNT NUMBER', 'ACCOUNT NAME', 'ADDRESS', 'AREA', 'AGENT'];

    private array $agentsByCluster = [];

    public function __construct(private readonly AddressLocationResolver $locations)
    {
    }

    public function preview(UploadedFile $file, User $user): array
    {
        $this->expireOldPreviews();
        $path = $file->storeAs('imports', Str::uuid().'.'.$file->getClientOriginalExtension(), 'local');
        if (!$path) {
            throw new \RuntimeException('The uploaded workbook could not be stored securely.');
        }
        $absolutePath = Storage::disk('local')->path($path);

        try {
            $sheetInfo = $this->worksheetInfo($absolutePath);
            $data = $this->readDataSheet($absolutePath, $sheetInfo);
            $references = $this->readReferenceSheets($absolutePath, $sheetInfo);
        } catch (\RuntimeException $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);
            Storage::disk('local')->delete($path);
            throw new \RuntimeException('The workbook could not be read. Confirm it is a valid Excel file and PHP ZIP/GD extensions are enabled.');
        }

        $groups = $this->groupRows($data['rows']);
        $counts = [
            'total_rows' => count($data['rows']),
            'valid_rows' => 0,
            'unique_accounts' => count($groups),
            'duplicate_rows' => 0,
            'conflicting_accounts' => 0,
            'invalid_rows' => 0,
            'reference_rows' => array_sum(array_map('count', $references)),
            'matched_accounts' => 0,
            'unmatched_accounts' => 0,
            'agent_conflicts' => 0,
        ];
        $issues = ['invalid' => [], 'duplicates' => [], 'assignment_warnings' => []];

        foreach ($groups as $group) {
            $row = $group['rows'][0];
            if ($group['conflicting']) {
                $counts['conflicting_accounts']++;
                $counts['duplicate_rows'] += count($group['rows']) - 1;
                $issues['duplicates'][] = [
                    'account_number' => $row['account_number'],
                    'rows' => count($group['rows']),
                    'reason' => 'The same account number has conflicting workbook values; this account will be skipped.',
                ];
                continue;
            }

            $counts['duplicate_rows'] += count($group['rows']) - 1;
            $missing = $this->missingRequired($row);
            if ($missing !== []) {
                $counts['invalid_rows'] += count($group['rows']);
                $issues['invalid'][] = [
                    'account_number' => $row['account_number'] ?? null,
                    'missing' => $missing,
                ];
                continue;
            }

            $counts['valid_rows']++;
            $assignment = $this->resolveAssignment($row, $references);
            if ($assignment['reference']) {
                $counts['matched_accounts']++;
                if ($assignment['conflict']) {
                    $counts['agent_conflicts']++;
                    $issues['assignment_warnings'][] = [
                        'account_number' => $row['account_number'],
                        'source_agent' => $row['source_agent_name'],
                        'reference_agent' => $assignment['reference']['assigned_agent_name'],
                        'reason' => 'The workbook agent differs from the exact location/province reference assignment.',
                    ];
                }
            } else {
                $counts['unmatched_accounts']++;
            }
        }

        $record = ImportRecord::query()->create([
            'file_name' => $file->getClientOriginalName(),
            'uploaded_by' => $user->name,
            'user_id' => $user->id,
            'status' => 'previewed',
            'storage_path' => $path,
            'total_rows' => $counts['total_rows'],
            'successful_rows' => 0,
            'duplicate_rows' => $counts['duplicate_rows'],
            'invalid_rows' => $counts['invalid_rows'],
            'skipped_rows' => $counts['conflicting_accounts'],
            'summary' => ['counts' => $counts, 'issues' => $issues],
        ]);

        return [
            'importId' => $record->id,
            'fileName' => $record->file_name,
            'counts' => $counts,
            'issues' => [
                'invalid' => array_slice($issues['invalid'], 0, 100),
                'duplicates' => array_slice($issues['duplicates'], 0, 100),
                'assignmentWarnings' => array_slice($issues['assignment_warnings'], 0, 500),
            ],
            'sampleRows' => array_slice(array_map(
                fn (array $row) => $this->publicRow($row, $this->resolveAssignment($row, $references)),
                array_values(array_map(fn (array $group) => $group['rows'][0], $groups)),
            ), 0, 10),
        ];
    }

    public function confirm(ImportRecord $record, User $user): array
    {
        $this->agentsByCluster = [];
        if ($record->status !== 'previewed' || !$record->storage_path || $record->user_id !== $user->id) {
            throw new \RuntimeException('This import is no longer available for confirmation.');
        }
        if ($record->created_at->lt(now()->subDay())) {
            Storage::disk('local')->delete($record->storage_path);
            $record->update(['status' => 'expired']);
            throw new \RuntimeException('The workbook preview expired after 24 hours. Upload it again to continue.');
        }

        $path = Storage::disk('local')->path($record->storage_path);
        if (!is_file($path)) {
            throw new \RuntimeException('The uploaded workbook has expired. Upload it again to continue.');
        }

        try {
            $sheetInfo = $this->worksheetInfo($path);
            $data = $this->readDataSheet($path, $sheetInfo);
            $references = $this->readReferenceSheets($path, $sheetInfo);
        } catch (\Throwable $exception) {
            report($exception);
            throw new \RuntimeException('The stored workbook could not be read. Upload a valid workbook again.');
        }

        $groups = $this->groupRows($data['rows']);
        $result = ['successful' => 0, 'duplicate' => 0, 'existing_updated' => 0, 'invalid' => 0, 'skipped' => 0, 'matched' => 0, 'unmatched' => 0, 'agent_conflicts' => 0];
        $failedAccounts = [];

        DB::transaction(function () use ($record, $groups, $references, &$result, &$failedAccounts): void {
            $record->update(['status' => 'processing']);
            Portfolio::query()->where('is_current', true)->update(['is_current' => false]);
            $existingPortfolios = Portfolio::query()->get()->groupBy(fn (Portfolio $portfolio) => $this->accountKey($portfolio->account_number));
            ClusterReference::query()->delete();

            foreach ($references as $cluster => $referenceRows) {
                foreach ($referenceRows as $reference) {
                    ClusterReference::query()->create($reference + ['cluster' => $cluster, 'import_id' => $record->id]);
                }
            }
            foreach ($references as $cluster => $referenceRows) {
                $agentNames = array_unique(array_column($referenceRows, 'assigned_agent_name'));
                foreach ($agentNames as $agentName) {
                    if (is_string($agentName) && trim($agentName) !== '') {
                        $this->resolveAgent($agentName, $cluster);
                    }
                }
            }

            foreach ($groups as $group) {
                $row = $group['rows'][0];
                $accountKey = $this->accountKey((string) ($row['account_number'] ?? ''));
                if ($group['conflicting']) {
                    $result['skipped']++;
                    $failedAccounts[] = $accountKey;
                    continue;
                }

                if ($this->missingRequired($row) !== []) {
                    $result['invalid']++;
                    $failedAccounts[] = $accountKey;
                    continue;
                }
                $result['duplicate'] += count($group['rows']) - 1;
                $existingGroup = $existingPortfolios->get($accountKey, collect());
                if ($existingGroup->count() > 1) {
                    $result['skipped']++;
                    $failedAccounts[] = $accountKey;
                    continue;
                }

                $assignment = $this->resolveAssignment($row, $references);
                $agent = $assignment['assigned_agent_name']
                    ? $this->resolveAgent($assignment['assigned_agent_name'], $assignment['cluster'])
                    : null;

                if ($assignment['reference']) {
                    $result['matched']++;
                    if ($assignment['conflict']) {
                        $result['agent_conflicts']++;
                    }
                } else {
                    $result['unmatched']++;
                }

                $portfolio = $existingGroup->first();
                $attributes = $this->portfolioAttributes($row, $assignment, $agent, $record);
                if ($portfolio) {
                    $portfolio->fill($attributes)->save();
                    $result['existing_updated']++;
                } else {
                    Portfolio::query()->create(['account_number' => $row['account_number'], 'account_name' => $row['account_name'], 'address' => $row['address']] + $attributes);
                }
                $result['successful']++;
            }

            if ($failedAccounts !== []) {
                foreach (array_unique($failedAccounts) as $accountKey) {
                    foreach ($existingPortfolios->get($accountKey, collect()) as $portfolio) {
                        Portfolio::query()->whereKey($portfolio->id)->update(['is_current' => true]);
                    }
                }
            }

            $record->update([
                'status' => 'completed',
                'successful_rows' => $result['successful'],
                'duplicate_rows' => $record->duplicate_rows,
                'invalid_rows' => $result['invalid'],
                'skipped_rows' => $result['skipped'],
                'summary' => [
                    'counts' => $result + ['total_rows' => $record->total_rows],
                    'issues' => $record->summary['issues'] ?? [],
                ],
            ]);
        });

        Storage::disk('local')->delete($record->storage_path);
        $record->refresh();

        return ['message' => 'Import completed. Existing account records were updated in place; conflicting duplicate accounts were skipped.', 'record' => $record, 'counts' => $result];
    }

    public function expireOldPreviews(): void
    {
        $cutoff = now()->subDay();
        ImportRecord::query()->where('status', 'previewed')->where('created_at', '<', $cutoff)
            ->orderBy('id')->limit(100)->get()->each(function (ImportRecord $record): void {
                if ($record->storage_path && Storage::disk('local')->exists($record->storage_path)
                    && !Storage::disk('local')->delete($record->storage_path)) {
                    throw new \RuntimeException("Could not remove expired import preview {$record->id} from private storage.");
                }
                $record->update(['status' => 'expired']);
            });
    }

    private function worksheetInfo(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheets = [];
        foreach ($reader->listWorksheetInfo($path) as $info) {
            $sheets[$info['worksheetName']] = $info;
        }

        return $sheets;
    }

    private function readDataSheet(string $path, array $sheetInfo): array
    {
        $dataSheet = collect($sheetInfo)->first(fn (array $info) => Str::upper(trim($info['worksheetName'])) === 'DATA INPUT');
        if (!$dataSheet) {
            throw new \RuntimeException('The workbook must include a Data Input sheet.');
        }

        $headerWorkbook = $this->loadSheetRange($path, $dataSheet['worksheetName'], 1, 12);
        $headerSheet = $headerWorkbook->getSheetByName($dataSheet['worksheetName']);
        [$headers, $headerRow] = $this->findHeaders($headerSheet, self::REQUIRED_COLUMNS);
        $headerWorkbook->disconnectWorksheets();
        unset($headerSheet, $headerWorkbook);

        if (!$headers) {
            throw new \RuntimeException('Could not find the required Data Input headers: Account Number, Account Name, Address, Area, and Agent.');
        }

        $rows = [];
        $columns = array_values($headers);
        $lastRow = (int) $dataSheet['totalRows'];
        for ($startRow = $headerRow + 1; $startRow <= $lastRow; $startRow += 1000) {
            $endRow = min($startRow + 999, $lastRow);
            $workbook = $this->loadSheetRange($path, $dataSheet['worksheetName'], $startRow, $endRow, $columns);
            $sheet = $workbook->getSheetByName($dataSheet['worksheetName']);
            for ($rowIndex = $startRow; $rowIndex <= $endRow; $rowIndex++) {
                $row = [];
                foreach ($headers as $key => $columnIndex) {
                    $cell = $sheet->getCell([$columnIndex + 1, $rowIndex]);
                    $row[$this->fieldName($key)] = $this->valueFor($cell->getValue(), $key);
                }
                $identityFields = ['account_number', 'account_name', 'address', 'imported_area', 'source_agent_name'];
                if (array_filter(array_intersect_key($row, array_flip($identityFields)), fn ($value) => $value !== null && $value !== '') !== []) {
                    $rows[] = $row;
                }
            }
            $workbook->disconnectWorksheets();
            unset($sheet, $workbook);
        }

        return ['rows' => $rows];
    }

    private function readReferenceSheets(string $path, array $sheetInfo): array
    {
        $references = array_fill_keys(self::CLUSTERS, []);
        $nameByCluster = [];
        foreach (array_keys($sheetInfo) as $sheetName) {
            $cluster = $this->normalizeCluster($sheetName);
            if (in_array($cluster, self::CLUSTERS, true)) {
                $nameByCluster[$cluster] = $sheetName;
            }
        }

        $missing = array_values(array_diff(self::CLUSTERS, array_keys($nameByCluster)));
        if ($missing !== []) {
            throw new \RuntimeException('The workbook is missing cluster reference sheets: '.implode(', ', $missing).'.');
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(array_values($nameByCluster));
        $workbook = $reader->load($path);
        try {
            foreach ($nameByCluster as $name => $sheetName) {
                $sheet = $workbook->getSheetByName($sheetName);
                [$headers, $headerRow] = $this->findHeaders($sheet, ['ASSIGN FA']);
                if (!$headers || !isset($headers['ASSIGN FA'])) {
                    throw new \RuntimeException("Could not find the ASSIGN FA column in the {$name} reference sheet.");
                }

                for ($rowIndex = $headerRow + 1; $rowIndex <= $sheet->getHighestDataRow(); $rowIndex++) {
                    $values = [];
                    foreach ($headers as $header => $columnIndex) {
                        $values[$header] = $this->clean($sheet->getCell([$columnIndex + 1, $rowIndex])->getValue());
                    }
                    if (trim((string) ($values['ASSIGN FA'] ?? '')) === '') {
                        continue;
                    }
                    $references[$name][] = [
                        'location' => $values['LOCATION'] ?? null,
                        'province' => $values['CITY PROVINCE'] ?? $values['PROVINCE'] ?? null,
                        'region' => $values['REGION'] ?? null,
                        'coverage' => $values['COVERAGE'] ?? null,
                        'assigned_agent_name' => $values['ASSIGN FA'],
                        'zip' => $values['ZIP'] ?? null,
                    ];
                }
            }
        } finally {
            $workbook->disconnectWorksheets();
        }

        return $references;
    }

    private function loadSheetRange(string $path, string $sheetName, int $startRow, int $endRow, ?array $columns = null)
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([$sheetName]);
        $reader->setReadFilter(new ImportWorksheetReadFilter($sheetName, $startRow, $endRow, $columns));
        return $reader->load($path);
    }

    private function findHeaders($sheet, array $required): array
    {
        $required = array_map(fn ($header) => $this->normalizeHeader($header), $required);
        $maxRow = min(12, $sheet->getHighestDataRow());
        $maxColumn = min(60, Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));
        for ($row = 1; $row <= $maxRow; $row++) {
            $headers = [];
            for ($column = 1; $column <= $maxColumn; $column++) {
                $value = $this->normalizeHeader((string) $sheet->getCell([$column, $row])->getValue());
                if ($value !== '') {
                    $headers[$value] = $column - 1;
                }
            }
            if (count(array_intersect($required, array_keys($headers))) === count($required)) {
                return [$headers, $row];
            }
        }

        return [[], 0];
    }

    private function groupRows(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = $this->accountKey((string) ($row['account_number'] ?? ''));
            if ($key === '') {
                $key = 'missing:'.count($groups);
            }
            $groups[$key]['rows'][] = $row;
        }

        foreach ($groups as &$group) {
            $group['conflicting'] = count(array_unique(array_map(
                fn (array $row) => hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                $group['rows'],
            ))) > 1;
        }

        return $groups;
    }

    private function resolveAssignment(array $row, array $references): array
    {
        $cluster = $this->normalizeCluster((string) ($row['imported_area'] ?? ''));
        $sourceAgent = trim((string) ($row['source_agent_name'] ?? ''));
        if (!in_array($cluster, self::CLUSTERS, true)) {
            return ['cluster' => $cluster, 'reference' => null, 'assigned_agent_name' => null, 'conflict' => false];
        }

        $location = $this->locations->resolve(
            (string) ($row['address'] ?? ''),
            array_map(fn (array $reference) => $reference + ['cluster' => $cluster], $references[$cluster] ?? []),
        );
        if ($location['status'] !== 'resolved') {
            return [
                'cluster' => $cluster,
                'reference' => null,
                'assigned_agent_name' => $sourceAgent !== '' ? $sourceAgent : null,
                'conflict' => false,
            ];
        }

        $referenceAgentNames = $location['reference_agents'];
        $referenceAgentName = count($referenceAgentNames) === 1 ? $referenceAgentNames[0] : null;
        $reference = [
            'location' => $location['location'],
            'province' => $location['province'],
            'region' => $location['region'],
            'assigned_agent_name' => $referenceAgentName,
        ];
        $conflict = $sourceAgent !== ''
            && $referenceAgentName !== null
            && $this->normalizeText($sourceAgent) !== $this->normalizeText($referenceAgentName);

        return [
            'cluster' => $cluster,
            'reference' => $reference,
            'assigned_agent_name' => $sourceAgent !== '' ? $sourceAgent : $referenceAgentName,
            'conflict' => $conflict,
        ];
    }

    private function resolveAgent(string $name, string $cluster): Agent
    {
        $normalized = $this->normalizeText($name);
        $this->agentsByCluster[$cluster] ??= Agent::query()->where('assigned_cluster', $cluster)->get()->all();
        $agent = collect($this->agentsByCluster[$cluster])
            ->first(fn (Agent $candidate) => $this->normalizeText($candidate->agent_name) === $normalized);
        if ($agent) {
            return $agent;
        }

        $agentId = 'IMP-'.strtoupper(substr(hash('sha256', $cluster.'|'.$normalized), 0, 12));
        $agent = Agent::query()->firstOrCreate(
            ['agent_id' => $agentId],
            ['agent_name' => $name, 'assigned_cluster' => $cluster, 'status' => 'active'],
        );
        $this->agentsByCluster[$cluster][] = $agent;

        return $agent;
    }

    private function portfolioAttributes(array $row, array $assignment, ?Agent $agent, ImportRecord $record): array
    {
        return [
            'ob' => $row['ob'] ?? null,
            'days_handled' => $this->integer($row['days_handled'] ?? null),
            'exp_date' => $this->date($row['exp_date'] ?? null),
            'product_line' => $row['product_line'] ?? null,
            'client' => $row['client'] ?? null,
            'service_type' => $row['service_type'] ?? null,
            'classification' => $row['classification'] ?? null,
            'contact' => $row['contact'] ?? null,
            'latest_status' => $row['latest_status'] ?? null,
            'cluster' => $assignment['cluster'] ?: null,
            'region' => $assignment['reference']['region'] ?? null,
            'province' => $assignment['reference']['province'] ?? null,
            'city' => $assignment['reference']['location'] ?? null,
            'verification_status' => $assignment['reference'] ? 'REFERENCE_MATCHED' : 'NEEDS_REVIEW',
            'assigned_agent_id' => $agent?->id,
            'is_active' => true,
            'last_updated_at' => now(),
            'on_hold' => $row['on_hold'] ?? null,
            'has_task' => $row['has_task'] ?? null,
            'date_endorsed' => $this->date($row['date_endorsed'] ?? null),
            'expired' => $row['expired'] ?? null,
            'monthly_amort' => $row['monthly_amort'] ?? null,
            'dpd' => $row['dpd'] ?? null,
            'particulars' => $row['particulars'] ?? null,
            'upcoming_task' => $row['upcoming_task'] ?? null,
            'imported_area' => $row['imported_area'] ?? null,
            'source_agent_name' => $row['source_agent_name'] ?? null,
            'location' => $assignment['reference']['location'] ?? null,
            'assignment_status' => $assignment['reference'] ? ($assignment['conflict'] ? 'agent_conflict' : 'matched') : 'unmatched',
            'match_notes' => $assignment['reference']
                ? ($assignment['conflict'] ? 'Data Input agent was assigned; the location reference lists a different agent.' : null)
                : 'No unique location/province assignment reference was found.',
            'is_current' => true,
            'last_seen_import_id' => $record->id,
        ];
    }

    private function publicRow(array $row, array $assignment): array
    {
        return [
            'account_number' => $row['account_number'] ?? null,
            'account_name' => $row['account_name'] ?? null,
            'cluster' => $assignment['cluster'] ?? null,
            'location' => $assignment['reference']['location'] ?? null,
            'province' => $assignment['reference']['province'] ?? null,
            'source_agent' => $row['source_agent_name'] ?? null,
            'reference_agent' => $assignment['reference']['assigned_agent_name'] ?? null,
            'assignment_status' => $assignment['reference'] ? ($assignment['conflict'] ? 'agent_conflict' : 'matched') : 'unmatched',
        ];
    }

    private function missingRequired(array $row): array
    {
        return array_values(array_filter(self::REQUIRED_COLUMNS, function (string $header) use ($row): bool {
            $field = $this->fieldName($header);
            return trim((string) ($row[$field] ?? '')) === '';
        }));
    }

    private function fieldName(string $header): string
    {
        return match ($this->normalizeHeader($header)) {
            'ACCOUNT NUMBER' => 'account_number',
            'ACCOUNT NAME' => 'account_name',
            'OB' => 'ob',
            'DATE ENDORSED' => 'date_endorsed',
            'DAYS HANDLED' => 'days_handled',
            'EXP DATE' => 'exp_date',
            'LATEST STATUS' => 'latest_status',
            'PRODUCT LINE' => 'product_line',
            'SERVICE TYPE' => 'service_type',
            'CLASSIFICATION' => 'classification',
            'MONTHLY AMORT' => 'monthly_amort',
            'DPD' => 'dpd',
            'PARTICULARS' => 'particulars',
            'ADDRESS' => 'address',
            'CONTACT' => 'contact',
            'UPCOMING TASK' => 'upcoming_task',
            'ON HOLD' => 'on_hold',
            'HAS TASK' => 'has_task',
            'AREA' => 'imported_area',
            'AGENT' => 'source_agent_name',
            default => strtolower(str_replace(' ', '_', $this->normalizeHeader($header))),
        };
    }

    private function valueFor(mixed $value, string $header): ?string
    {
        if (in_array($this->normalizeHeader($header), ['DATE ENDORSED', 'EXP DATE'], true) && is_numeric($value) && (float) $value > 0) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }
        return $this->clean($value);
    }

    private function clean(mixed $value): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        if (is_float($value)) {
            if (is_finite($value) && floor($value) === $value && abs($value) <= PHP_INT_MAX) {
                return (string) (int) $value;
            }
            return sprintf('%.15g', $value);
        }
        return trim((string) $value);
    }

    private function normalizeHeader(string $header): string
    {
        return preg_replace('/\s+/', ' ', strtoupper(trim(str_replace(["\r", "\n"], ' ', $header)))) ?? '';
    }

    private function normalizeCluster(string $area): string
    {
        $area = $this->normalizeHeader($area);
        return match ($area) {
            'NORTH LUZON' => 'NORTH',
            'SOUTH LUZON' => 'SOUTH',
            default => $area,
        };
    }

    private function normalizeText(string $value): string
    {
        $value = Str::ascii(Str::lower(trim($value)));
        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }

    private function containsAddressPhrase(string $address, string $phrase): bool
    {
        $addressWords = preg_split('/[^a-z0-9]+/', Str::ascii(Str::lower($address)), -1, PREG_SPLIT_NO_EMPTY);
        $phraseWords = preg_split('/[^a-z0-9]+/', Str::ascii(Str::lower(trim($phrase))), -1, PREG_SPLIT_NO_EMPTY);
        if ($addressWords === false || $phraseWords === false || $phraseWords === []) {
            return false;
        }

        $phraseLength = count($phraseWords);
        for ($offset = 0; $offset <= count($addressWords) - $phraseLength; $offset++) {
            if (array_slice($addressWords, $offset, $phraseLength) === $phraseWords) {
                return true;
            }
        }

        return false;
    }

    private function accountKey(string $value): string
    {
        return $this->normalizeText($value);
    }

    private function integer(?string $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function date(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse($value)->toDateString();
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

}

final class ImportWorksheetReadFilter implements IReadFilter
{
    public function __construct(
        private readonly string $sheetName,
        private readonly int $startRow,
        private readonly int $endRow,
        private readonly ?array $columns = null,
    ) {
    }

    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
        return $worksheetName === $this->sheetName
            && $row >= $this->startRow
            && $row <= $this->endRow
            && ($this->columns === null || in_array(Coordinate::columnIndexFromString($columnAddress) - 1, $this->columns, true));
    }
}
