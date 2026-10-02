<?php

declare(strict_types=1);

/**
 * G1.4 last-slot proof on a throwaway InnoDB database.
 * Proposal only. Tables exist only in this process and are dropped before exit.
 * Not a migration. Not tenant schema. Not a holiday, fee, or practitioner list.
 *
 * Run: php SchedulingRaceOracle.php <dsn> <user> <pass>
 * The dsn database name must be g14_synthetic and must not use port 3306.
 */

final class SchedulingRaceOracle
{
    public function __construct(private readonly PDO $pdo, private readonly string $php)
    {
    }

    /** @return list<string> */
    public function run(): array
    {
        $failures = [];
        $check = static function (bool $ok, string $message) use (&$failures): void {
            if (!$ok) {
                $failures[] = $message;
            }
        };
        $this->reset();
        $held = $this->blockingHold();
        $check($held['blocked'], 'second transaction was not blocked by SELECT ... FOR UPDATE');
        $check($held['rows'] === 1, 'the race left '.$held['rows'].' consuming rows');
        try {
            $this->place('race-retry', hash('sha256', 'retry'), '2026-04-01 06:30:00', '2026-04-01 07:00:00');
            $check(false, 'a retry took the slot the lock holder already took');
        } catch (RuntimeException $e) {
            $check($e->getMessage() === 'scheduling_capacity_exhausted', 'retry error was '.$e->getMessage());
        }
        $check($this->consuming() === 1, 'exhausted retry inserted a row');

        $this->reset();
        $id = $this->place('idem-1', hash('sha256', 'body-a'), '2026-04-01 06:30:00', '2026-04-01 07:00:00');
        $again = $this->place('idem-1', hash('sha256', 'body-a'), '2026-04-01 06:30:00', '2026-04-01 07:00:00');
        $check($again === $id, 'same idempotency body did not return the same row');
        $check($this->consuming() === 1, 'idempotent replay consumed twice');
        $mismatch = false;
        try {
            $this->place('idem-1', hash('sha256', 'body-b'), '2026-04-01 08:00:00', '2026-04-01 08:30:00');
        } catch (RuntimeException $e) {
            $mismatch = $e->getMessage() === 'scheduling_idempotency_mismatch';
        }
        $check($mismatch, 'a different body reused the idempotency key');
        $check($this->consuming() === 1, 'mismatched replay inserted a row');

        $adjacent = $this->place('idem-2', hash('sha256', 'body-c'), '2026-04-01 07:00:00', '2026-04-01 07:30:00');
        $check($adjacent !== '', 'half-open neighbour was rejected');
        $check($this->consuming() === 2, 'neighbour did not persist');
        $overlap = false;
        try {
            $this->place('idem-3', hash('sha256', 'body-d'), '2026-04-01 06:45:00', '2026-04-01 07:15:00');
        } catch (RuntimeException $e) {
            $overlap = $e->getMessage() === 'scheduling_capacity_exhausted';
        }
        $check($overlap, 'an overlapping interval was accepted');
        $check($this->consuming() === 2, 'overlap inserted a row');

        $uniqueRejected = false;
        try {
            $this->pdo->prepare('INSERT INTO g14_bookings (id, resource_id, starts_at, ends_at, status, idempotency_key, body_hash) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute(['SYN-DUP-1', 'res-a', '2026-04-01 10:00:00', '2026-04-01 10:30:00', 'held', 'idem-1', hash('sha256', 'other')]);
        } catch (PDOException $e) {
            $uniqueRejected = str_contains($e->getMessage(), '1062');
        }
        $check($uniqueRejected, 'the idempotency unique key did not reject a raw duplicate');

        $this->pdo->exec('DROP TABLE IF EXISTS g14_bookings');
        $this->pdo->exec('DROP TABLE IF EXISTS g14_day_locks');

        return $failures;
    }

    /** @return array{blocked:bool,rows:int} */
    private function blockingHold(): array
    {
        $signal = tempnam(sys_get_temp_dir(), 'g14-hold-');
        if ($signal === false) {
            throw new RuntimeException('signal file');
        }
        $command = [
            $this->php, __FILE__, (string) getenv('G14_DSN'), (string) getenv('G14_USER'), (string) getenv('G14_PASS'),
            'hold', $signal,
        ];
        $proc = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            throw new RuntimeException('could not start the lock holder');
        }
        $deadline = time() + 10;
        $seen = '';
        while (time() < $deadline && !str_contains($seen, 'HELD')) {
            $seen = (string) @file_get_contents($signal);
            usleep(50000);
        }
        $blocked = false;
        try {
            $this->pdo->exec('SET innodb_lock_wait_timeout = 1');
            $this->pdo->beginTransaction();
            $this->pdo->query("SELECT capacity FROM g14_day_locks WHERE resource_id = 'res-a' AND local_date = '2026-04-01' FOR UPDATE");
        } catch (PDOException $e) {
            $blocked = str_contains($e->getMessage(), '1205') || str_contains($e->getMessage(), 'Lock wait timeout');
        }
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        $deadline = time() + 10;
        while (time() < $deadline && !str_contains((string) @file_get_contents($signal), 'DONE')) {
            usleep(50000);
        }
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        $exit = proc_close($proc);
        @unlink($signal);
        if ($exit !== 0) {
            throw new RuntimeException('lock holder failed: '.$output);
        }

        return ['blocked' => $blocked, 'rows' => $this->consuming()];
    }

    private function place(string $key, string $body, string $start, string $end): string
    {
        $this->pdo->beginTransaction();
        $lock = $this->pdo->query("SELECT capacity FROM g14_day_locks WHERE resource_id = 'res-a' AND local_date = '2026-04-01' FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
        if (!$lock) {
            $this->pdo->rollBack();
            throw new RuntimeException('scheduling_not_found');
        }
        $existing = $this->pdo->prepare('SELECT id, body_hash FROM g14_bookings WHERE idempotency_key = ?');
        $existing->execute([$key]);
        $row = $existing->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->pdo->commit();
            if ($row['body_hash'] !== $body) {
                throw new RuntimeException('scheduling_idempotency_mismatch');
            }

            return (string) $row['id'];
        }
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM g14_bookings WHERE resource_id = 'res-a' AND status = 'held' AND starts_at < ? AND ? < ends_at");
        $count->execute([$end, $start]);
        if ((int) $count->fetchColumn() >= (int) $lock['capacity']) {
            $this->pdo->rollBack();
            throw new RuntimeException('scheduling_capacity_exhausted');
        }
        $id = substr(strtoupper(bin2hex(random_bytes(13))), 0, 26);
        $insert = $this->pdo->prepare('INSERT INTO g14_bookings (id, resource_id, starts_at, ends_at, status, idempotency_key, body_hash) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([$id, 'res-a', $start, $end, 'held', $key, $body]);
        $this->pdo->commit();

        return $id;
    }

    private function consuming(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM g14_bookings WHERE status = 'held'")->fetchColumn();
    }

    private function reset(): void
    {
        $this->pdo->exec('DROP TABLE IF EXISTS g14_bookings');
        $this->pdo->exec('DROP TABLE IF EXISTS g14_day_locks');
        $this->pdo->exec("CREATE TABLE g14_day_locks (resource_id VARCHAR(64) NOT NULL, local_date DATE NOT NULL, capacity INT NOT NULL, PRIMARY KEY (resource_id, local_date)) ENGINE=InnoDB");
        $this->pdo->exec("CREATE TABLE g14_bookings (id CHAR(26) NOT NULL, resource_id VARCHAR(64) NOT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, status VARCHAR(24) NOT NULL, idempotency_key VARCHAR(160) NOT NULL, body_hash CHAR(64) NOT NULL, PRIMARY KEY (id), UNIQUE KEY g14_idem (idempotency_key)) ENGINE=InnoDB");
        $this->pdo->exec("INSERT INTO g14_day_locks (resource_id, local_date, capacity) VALUES ('res-a', '2026-04-01', 1)");
    }
}

function g14_hold(string $dsn, string $user, string $pass, string $signal): void
{
    $pdo = g14_pdo($dsn, $user, $pass);
    $pdo->beginTransaction();
    $pdo->query("SELECT capacity FROM g14_day_locks WHERE resource_id = 'res-a' AND local_date = '2026-04-01' FOR UPDATE")->fetch();
    file_put_contents($signal, "HELD\n");
    sleep(3);
    $id = substr(strtoupper(bin2hex(random_bytes(13))), 0, 26);
    $pdo->prepare('INSERT INTO g14_bookings (id, resource_id, starts_at, ends_at, status, idempotency_key, body_hash) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$id, 'res-a', '2026-04-01 06:30:00', '2026-04-01 07:00:00', 'held', 'race-winner', hash('sha256', 'winner')]);
    $pdo->commit();
    file_put_contents($signal, "HELD\nDONE\n");
}

function g14_pdo(string $dsn, string $user, string $pass): PDO
{
    if (!str_contains($dsn, 'g14_synthetic') || str_contains($dsn, '3306')) {
        throw new RuntimeException('REFUSED dsn');
    }
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("SET time_zone = '+00:00'");

    return $pdo;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? ($argv[0] ?? '')) === __FILE__) {
    $dsn = $argv[1] ?? '';
    $user = $argv[2] ?? 'root';
    $pass = $argv[3] ?? '';
    if (($argv[4] ?? '') === 'hold') {
        g14_hold($dsn, $user, $pass, $argv[5] ?? '');
        exit(0);
    }
    putenv('G14_DSN='.$dsn);
    putenv('G14_USER='.$user);
    putenv('G14_PASS='.$pass);
    $failures = (new SchedulingRaceOracle(g14_pdo($dsn, $user, $pass), PHP_BINARY))->run();
    if ($failures !== []) {
        fwrite(STDERR, implode("\n", $failures)."\n");
        fwrite(STDOUT, "FAIL scheduling race\nORACLE_EXIT 1\n");
        exit(1);
    }
    fwrite(STDOUT, "PASS scheduling race\nSYNTHETIC_ONLY throwaway database\nORACLE_EXIT 0\n");
}
