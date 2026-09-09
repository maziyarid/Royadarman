<?php

namespace App\Providers;

use App\Domain\Documents\Contracts\DocumentScanner;
use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Operations\Contracts\NotificationSender;
use App\Infrastructure\Documents\ClamAvDocumentScanner;
use App\Infrastructure\Identity\HttpOtpSender;
use App\Infrastructure\Operations\HttpNotificationSender;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ClinicBranch;
use App\Models\ClinicalDocument;
use App\Models\Dentist;
use App\Models\Order;
use App\Models\PaymentIntent;
use App\Models\PatientCase;
use App\Models\Refund;
use App\Models\Service;
use App\Policies\AppointmentPolicy;
use App\Policies\ClinicBranchPolicy;
use App\Policies\ClinicPolicy;
use App\Policies\ClinicalDocumentPolicy;
use App\Policies\DentistPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentIntentPolicy;
use App\Policies\PatientCasePolicy;
use App\Policies\RefundPolicy;
use App\Policies\ServicePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DocumentScanner::class, ClamAvDocumentScanner::class);
        $this->app->bind(OtpSender::class, HttpOtpSender::class);
        $this->app->bind(NotificationSender::class, HttpNotificationSender::class);
    }

    public function boot(): void
    {
        Gate::policy(PatientCase::class, PatientCasePolicy::class);
        Gate::policy(ClinicalDocument::class, ClinicalDocumentPolicy::class);
        Gate::policy(Clinic::class, ClinicPolicy::class);
        Gate::policy(ClinicBranch::class, ClinicBranchPolicy::class);
        Gate::policy(Dentist::class, DentistPolicy::class);
        Gate::policy(Service::class, ServicePolicy::class);
        Gate::policy(Appointment::class, AppointmentPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(PaymentIntent::class, PaymentIntentPolicy::class);
        Gate::policy(Refund::class, RefundPolicy::class);
        if (! $this->app->routesAreCached()) {
            $this->loadRoutesFrom(base_path('routes/api.php'));
        }
    }
}
