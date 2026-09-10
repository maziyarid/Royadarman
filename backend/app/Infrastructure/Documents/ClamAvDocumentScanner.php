<?php

namespace App\Infrastructure\Documents;

use App\Domain\Documents\Contracts\DocumentScanner;
use App\Domain\Documents\ValueObjects\ScanResult;
use RuntimeException;
use Symfony\Component\Process\Process;

class ClamAvDocumentScanner implements DocumentScanner
{
    public function scan(string $absolutePath): ScanResult
    {
        if (! config('royadarman.opg.scanner.enabled')) {
            throw new RuntimeException('The OPG scanner is disabled; document remains quarantined.');
        }

        $command = (string) config('royadarman.opg.scanner.command');

        if (! is_executable($command)) {
            throw new RuntimeException('The configured OPG scanner is unavailable; document remains quarantined.');
        }

        $process = new Process([$command, '--no-summary', $absolutePath]);
        $process->setTimeout((float) config('royadarman.opg.scanner.timeout_seconds', 60));
        $process->run();

        if ($process->getExitCode() === 0) {
            return new ScanResult(true, 'clamav', hash_file('sha256', $absolutePath));
        }

        if ($process->getExitCode() === 1) {
            return new ScanResult(false, 'clamav', hash_file('sha256', $absolutePath));
        }

        throw new RuntimeException('The OPG scanner failed without a verdict; document remains quarantined.');
    }
}
