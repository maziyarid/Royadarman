<?php

namespace App\Providers;

use App\Domain\Documents\Contracts\DocumentScanner;
use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Operations\Contracts\NotificationSender;
use App\Domain\Operations\Services\IntegrationSettings;
use App\Http\Responses\PasskeyLoginResponse as AppPasskeyLoginResponse;
use App\Infrastructure\Documents\ClamAvDocumentScanner;
use App\Infrastructure\Identity\HttpOtpSender;
use App\Infrastructure\Identity\TsmsOtpSender;
use App\Infrastructure\Operations\HttpNotificationSender;
use App\Infrastructure\Operations\TsmsNotificationSender;
use App\Models\ClinicalDocument;
use App\Models\Cms\Post;
use App\Models\PatientCase;
use App\Models\SupportConversation;
use App\Models\User;
use App\Policies\ClinicalDocumentPolicy;
use App\Policies\CmsPostPolicy;
use App\Policies\PatientCasePolicy;
use App\Policies\SupportConversationPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Laravel\Passkeys\Events\PasskeyVerified;
use Laravel\Passkeys\Passkeys;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PasskeyLoginResponseContract::class, AppPasskeyLoginResponse::class);

        $this->app->bind(DocumentScanner::class, ClamAvDocumentScanner::class);

        $this->app->bind(OtpSender::class, function ($app): OtpSender {
            $provider = (string) $app->make(IntegrationSettings::class)->value('sms_provider', config('royadarman.sms.provider'));

            return match ($provider) {
                'tsms' => $app->make(TsmsOtpSender::class),
                'http' => $app->make(HttpOtpSender::class),
                default => throw new RuntimeException('Unsupported SMS provider configured.'),
            };
        });
        $this->app->bind(NotificationSender::class, function ($app): NotificationSender {
            $provider = (string) $app->make(IntegrationSettings::class)->value('sms_provider', config('royadarman.sms.provider'));

            return match ($provider) {
                'tsms' => $app->make(TsmsNotificationSender::class),
                'http' => $app->make(HttpNotificationSender::class),
                default => throw new RuntimeException('Unsupported SMS provider configured.'),
            };
        });
    }

    public function boot(): void
    {
        Passkeys::authorizeLoginUsing(static function ($request, $user): bool {
            return $user instanceof User
                && $user->is_active
                && ! (is_string($user->email) && str_ends_with($user->email, '@royadarman.invalid'));
        });

        Event::listen(PasskeyVerified::class, static function (PasskeyVerified $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_authenticated_at' => now()])->save();
            }
        });

        $this->app->make(IntegrationSettings::class)->applyToRuntimeConfig();

        Gate::policy(PatientCase::class, PatientCasePolicy::class);
        Gate::policy(ClinicalDocument::class, ClinicalDocumentPolicy::class);
        Gate::policy(SupportConversation::class, SupportConversationPolicy::class);
        Gate::policy(Post::class, CmsPostPolicy::class);

        RateLimiter::for('otp-challenge', fn () => Limit::perMinutes(
            (int) config('royadarman.sms.otp.ip_limit_window_minutes', 60),
            (int) config('royadarman.sms.otp.ip_limit_count', 10),
        )->by(request()->ip()));
        RateLimiter::for('otp-verify', fn () => Limit::perMinutes(60, 30)->by(request()->ip()));
        RateLimiter::for('password-login', fn ($request) => [
            Limit::perMinute(20)->by('password-login:ip:'.$request->ip()),
            Limit::perMinute(5)->by('password-login:user:'.hash('sha256', mb_strtolower(trim((string) $request->input('username')))).':'.$request->ip()),
        ]);

        if (! $this->app->routesAreCached()) {
            $this->loadRoutesFrom(base_path('routes/api.php'));
        }
    }
}
