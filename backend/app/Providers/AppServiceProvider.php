<?php

namespace App\Providers;

use App\Domain\Documents\Contracts\DocumentScanner;
use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Operations\Contracts\NotificationSender;
use App\Infrastructure\Documents\ClamAvDocumentScanner;
use App\Infrastructure\Identity\HttpOtpSender;
use App\Infrastructure\Identity\TsmsOtpSender;
use App\Infrastructure\Operations\HttpNotificationSender;
use App\Infrastructure\Operations\TsmsNotificationSender;
use App\Models\ClinicalDocument;
use App\Models\PatientCase;
use App\Policies\ClinicalDocumentPolicy;
use App\Policies\PatientCasePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DocumentScanner::class, ClamAvDocumentScanner::class);

        $this->app->bind(OtpSender::class, function ($app): OtpSender {
            return match ((string) config('royadarman.sms.provider')) {
                'tsms' => $app->make(TsmsOtpSender::class),
                'http' => $app->make(HttpOtpSender::class),
                default => throw new RuntimeException('Unsupported SMS provider configured.'),
            };
        });
        $this->app->bind(NotificationSender::class, function ($app): NotificationSender {
            return match ((string) config('royadarman.sms.provider')) {
                'tsms' => $app->make(TsmsNotificationSender::class),
                'http' => $app->make(HttpNotificationSender::class),
                default => throw new RuntimeException('Unsupported SMS provider configured.'),
            };
        });
    }

    public function boot(): void
    {
        Gate::policy(PatientCase::class, PatientCasePolicy::class);
        Gate::policy(ClinicalDocument::class, ClinicalDocumentPolicy::class);

        RateLimiter::for('otp-challenge', fn () => Limit::perMinutes(60, 20)->by(request()->ip()));
        RateLimiter::for('otp-verify', fn () => Limit::perMinutes(60, 30)->by(request()->ip()));

        if (! $this->app->routesAreCached()) {
            $this->loadRoutesFrom(base_path('routes/api.php'));
        }
    }
}
