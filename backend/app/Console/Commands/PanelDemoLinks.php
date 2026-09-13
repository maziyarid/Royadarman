<?php

namespace App\Console\Commands;

use App\Support\PanelDemoRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

final class PanelDemoLinks extends Command
{
    protected $signature = 'royadarman:panel-demo:links
        {--minutes=30 : Link validity in minutes (1-120)}
        {--locale=fa : fa|ar|en}';

    protected $description = 'Generate short-lived signed links for the fixed Royadarman TEST panel identities.';

    public function handle(): int
    {
        if (! (bool) config('royadarman.panel_demo_access')) {
            $this->error('PANEL_DEMO_ACCESS is disabled. No demo links were generated.');

            return self::FAILURE;
        }

        $minutes = filter_var($this->option('minutes'), FILTER_VALIDATE_INT);
        $locale = (string) $this->option('locale');
        $supportedLocales = (array) config('royadarman.supported_locales', ['fa']);

        if ($minutes === false || $minutes < 1 || $minutes > 120) {
            $this->error('The --minutes value must be an integer between 1 and 120.');

            return self::FAILURE;
        }

        if (! in_array($locale, $supportedLocales, true)) {
            $this->error('The --locale value must be one of: '.implode(', ', $supportedLocales).'.');

            return self::FAILURE;
        }

        $expiresAt = now()->addMinutes($minutes);
        $this->warn('These links are temporary bearer credentials. Do not store them in source control, tickets, logs, or long-term memory.');
        $this->line('Expires: '.$expiresAt->toIso8601String());

        foreach (PanelDemoRegistry::aliases() as $alias) {
            $url = URL::temporarySignedRoute(
                'demo.panel.access',
                $expiresAt,
                ['role' => $alias, 'locale' => $locale],
            );
            $this->line($alias.': '.$url);
        }

        return self::SUCCESS;
    }
}
