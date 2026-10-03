<?php

use App\Domain\Operations\Services\OperationsAnalytics;
use App\Models\PatientCase;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

// Private, disposable MariaDB verification only. Never use the live app or DB.
$base = realpath($argv[1] ?? '') ?: throw new RuntimeException('Isolated backend required');
$socket = realpath($argv[2] ?? '') ?: throw new RuntimeException('Private socket required');
if (is_file($base.'/.env') || is_file($base.'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Refuse an environment file or cached configuration');
}
$parent = dirname($socket);
if (! str_starts_with($parent, '/home/royadarman/checks/codex-analytics-mariadb-')
    || basename($socket) !== 'mysql.sock'
    || (fileperms($parent) & 0777) !== 0700) {
    throw new RuntimeException('Refuse a non-private verification socket');
}
foreach ([
    'APP_ENV' => 'testing', 'APP_URL' => 'http://localhost',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('s', 32)),
    'APP_CONFIG_CACHE' => $base.'/bootstrap/cache/analytics-probe-config.php',
    'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'royadarman_iso_test',
    'DB_SOCKET' => $socket, 'DB_HOST' => 'localhost', 'DB_PORT' => '0',
    'DB_USERNAME' => 'root', 'DB_PASSWORD' => '', 'DB_URL' => '',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
    'BROADCAST_CONNECTION' => 'null', 'INTAKE_ENABLED' => 'false',
    'PANEL_DEMO_ACCESS' => 'false',
    'ROYADARMAN_PHONE_HASH_KEY' => 'synthetic-snapshot-only',
] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
if (is_file($base.'/bootstrap/cache/analytics-probe-config.php')) {
    throw new RuntimeException('Refuse cached probe configuration');
}
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Queue::fake();
Mail::fake();
Notification::fake();

$connection = DB::connection();
$metadata = $connection->selectOne('SELECT @@socket AS socket_name, DATABASE() AS database_name, @@session.tx_isolation AS isolation_name');
if ($metadata->socket_name !== $socket || $metadata->database_name !== 'royadarman_iso_test'
    || $metadata->isolation_name !== 'REPEATABLE-READ' || $connection->transactionLevel() !== 0) {
    throw new RuntimeException('Private repeatable-read target not proven');
}
// Schema comes from the preceding isolated PHPUnit migration/test run.
foreach (['patient_cases', 'support_conversations', 'referral_proposals', 'home_service_requests', 'coordination_tasks'] as $table) {
    if ($connection->table($table)->count() !== 0) {
        throw new RuntimeException('Expected empty disposable cohort tables');
    }
}
$patient = User::factory()->create(['role' => 'patient']);
$at = CarbonImmutable::now('UTC')->subDay()->startOfSecond();
$first = PatientCase::query()->create([
    'public_reference' => 'SNAPSHOT-A-'.Str::random(6),
    'patient_user_id' => $patient->id, 'service_type' => 'guidance_referral',
    'status' => 'submitted', 'patient_mobile' => '09120000001',
    'patient_mobile_hash' => hash('sha256', 'synthetic-snapshot-a'),
    'budget_band' => 'call',
]);
$first->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
$writer = new PDO('mysql:unix_socket='.$socket.';dbname=royadarman_iso_test;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$inserted = false;
$connection->listen(function ($event) use (&$inserted, $writer, $patient, $at): void {
    if ($inserted || ! str_contains($event->sql, 'patient_cases') || ! str_contains(strtolower($event->sql), 'group by')) {
        return;
    }
    // The first report count has been read. Commit another in-range record on
    // a different connection before the original report reads its weekly series.
    $inserted = true;
    $statement = $writer->prepare('INSERT INTO patient_cases (id, public_reference, patient_user_id, service_type, status, patient_mobile, patient_mobile_hash, budget_band, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $statement->execute([
        (string) Str::ulid(), 'SNAPSHOT-B-'.Str::random(6),
        $patient->id, 'guidance_referral', 'submitted', '09120000002',
        hash('sha256', 'synthetic-snapshot-b'), 'call',
        $at->format('Y-m-d H:i:s'), $at->format('Y-m-d H:i:s'),
    ]);
});
$service = $app->make(OperationsAnalytics::class);
$initial = $service->report('30d');
$next = $service->report('30d');
if (! $inserted || $initial['summary']['cases'] !== 1 || $initial['caseTrend']->sum('count') !== 1
    || $next['summary']['cases'] !== 2 || $next['caseTrend']->sum('count') !== 2
    || $connection->transactionLevel() !== 0) {
    throw new RuntimeException('Cross-query snapshot or next-report visibility failed');
}
echo json_encode([
    'probe' => 'analytics_repeatable_read_interleaving', 'state' => 'passed',
    'initial_summary' => 1, 'initial_trend' => 1, 'next_summary' => 2, 'next_trend' => 2,
    'isolation' => $metadata->isolation_name,
    'scope' => 'one controlled committed interleaving; not broad load/race proof',
], JSON_THROW_ON_ERROR)."\n";
