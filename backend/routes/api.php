<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CaseController;
use App\Http\Controllers\Api\V1\Clinics\ClinicBranchController;
use App\Http\Controllers\Api\V1\Clinics\ClinicController;
use App\Http\Controllers\Api\V1\Clinics\DentistController;
use App\Http\Controllers\Api\V1\Clinics\ServiceController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\Finance\LedgerController;
use App\Http\Controllers\Api\V1\Finance\OrderController;
use App\Http\Controllers\Api\V1\Finance\PaymentController;
use App\Http\Controllers\Api\V1\Matching\ReferralRequestController;
use App\Http\Controllers\Api\V1\NotificationCallbackController;
use App\Http\Controllers\Api\V1\PolicyController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ReferralController;
use App\Http\Controllers\Api\V1\Scheduling\AppointmentController;
use App\Http\Controllers\Api\V1\StaffCaseController;
use App\Http\Middleware\EnsurePatientIntakeEnabled;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::post('api/v1/notifications/callback', NotificationCallbackController::class);

Route::prefix('api/v1')->middleware(['web', SetLocale::class])->group(function (): void {
    Route::post('/auth/otp/challenge', [AuthController::class, 'challenge'])->middleware('throttle:20,60');
    Route::post('/auth/otp/verify', [AuthController::class, 'verify'])->middleware('throttle:30,60');
    Route::get('/policies/{key}', [PolicyController::class, 'show']);

    Route::middleware('auth')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [ProfileController::class, 'show']);
        Route::patch('/me/preferences', [ProfileController::class, 'update']);

        Route::middleware(EnsurePatientIntakeEnabled::class)->group(function (): void {
            Route::post('/cases/draft', [CaseController::class, 'draft']);
            Route::post('/cases/{case}/submit', [CaseController::class, 'submit']);
            Route::get('/cases/{case}', [CaseController::class, 'show']);
            Route::post('/cases/{case}/documents', [DocumentController::class, 'store']);
            Route::get('/cases/{case}/documents/{document}', [DocumentController::class, 'status']);
            Route::get('/cases/{case}/documents/{document}/content', [DocumentController::class, 'content']);
            Route::post('/cases/{case}/referrals/{proposal}/decision', [ReferralController::class, 'decide']);
            Route::post('/staff/cases/{case}/assignments', [StaffCaseController::class, 'assign']);
            Route::patch('/staff/cases/{case}/status', [StaffCaseController::class, 'status']);
            Route::post('/staff/cases/{case}/referral-proposals', [StaffCaseController::class, 'proposeReferral']);
            Route::post('/staff/cases/{case}/reviews', [StaffCaseController::class, 'createReview']);
            Route::post('/staff/cases/{case}/reviews/{review}/publish', [StaffCaseController::class, 'publishReview']);
        });

        // Matching Routes
        Route::prefix('matching')->group(function (): void {
            Route::post('/referral-requests/draft', [ReferralRequestController::class, 'draft']);
            Route::post('/referral-requests/{referralRequest}/submit', [ReferralRequestController::class, 'submit']);
            Route::get('/referral-requests/{referralRequest}', [ReferralRequestController::class, 'show']);
            Route::get('/referral-requests/{referralRequest}/candidates', [ReferralRequestController::class, 'getCandidates']);
            Route::post('/referral-requests/{referralRequest}/decide', [ReferralRequestController::class, 'decideCandidate']);
        });

        // Scheduling Routes
        Route::prefix('scheduling')->group(function (): void {
            Route::post('/appointments/from-slot/{slot}', [AppointmentController::class, 'createFromSlot']);
            Route::post('/appointments/from-capacity/{clinicBranch}', [AppointmentController::class, 'createFromCapacityWindow']);
            Route::post('/appointments/{appointment}/confirm', [AppointmentController::class, 'confirm']);
            Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
            Route::post('/appointments/{appointment}/check-in', [AppointmentController::class, 'checkIn']);
            Route::post('/appointments/{appointment}/complete', [AppointmentController::class, 'complete']);
            Route::get('/branches/{clinicBranch}/slots/available', [AppointmentController::class, 'getAvailableSlots']);
            Route::post('/slots/{slot}/hold', [AppointmentController::class, 'holdSlot']);
            Route::post('/holds/release', [AppointmentController::class, 'releaseHold']);
            Route::post('/holds/convert', [AppointmentController::class, 'convertHold']);
        });

        // Finance Routes - Orders
        Route::prefix('finance/orders')->group(function (): void {
            Route::post('/{appointment}', [OrderController::class, 'create']);
            Route::get('/{order}', [OrderController::class, 'show']);
            Route::post('/{order}/submit', [OrderController::class, 'submit']);
            Route::post('/{order}/pay', [OrderController::class, 'pay']);
            Route::get('/{order}/verify', [OrderController::class, 'verify']);
            Route::post('/{order}/cancel', [OrderController::class, 'cancel']);
        });

        // Finance Routes - Payments
        Route::prefix('finance/payments')->group(function (): void {
            Route::post('/intents/{order}', [PaymentController::class, 'createIntent']);
            Route::get('/intents/{paymentIntent}', [PaymentController::class, 'show']);
            Route::post('/callback', [PaymentController::class, 'callback']);
            Route::post('/{order}/refunds', [PaymentController::class, 'createRefund']);
            Route::post('/refunds/{refund}/process', [PaymentController::class, 'processRefund']);
            Route::get('/{order}/transactions', [PaymentController::class, 'listTransactions']);
            Route::get('/intents/{paymentIntent}/url', [PaymentController::class, 'getPaymentUrl']);
        });

        // Finance Routes - Ledger
        Route::prefix('finance/ledger')->group(function (): void {
            Route::get('/accounts', [LedgerController::class, 'listAccounts']);
            Route::get('/accounts/{ledgerAccount}', [LedgerController::class, 'showAccount']);
            Route::get('/transactions', [LedgerController::class, 'listTransactions']);
            Route::get('/clinics/{clinic}/balance', [LedgerController::class, 'getClinicBalance']);
            Route::post('/reconcile', [LedgerController::class, 'reconcile']);
            Route::post('/transactions', [LedgerController::class, 'createTransaction']);
        });

        // Clinic Network Routes
        Route::prefix('clinics')->group(function (): void {
            Route::get('/', [ClinicController::class, 'index']);
            Route::post('/', [ClinicController::class, 'store']);
            Route::get('/{clinic}', [ClinicController::class, 'show']);
            Route::patch('/{clinic}', [ClinicController::class, 'update']);
            Route::delete('/{clinic}', [ClinicController::class, 'destroy']);

            Route::prefix('{clinic}/branches')->group(function (): void {
                Route::get('/', [ClinicBranchController::class, 'index']);
                Route::post('/', [ClinicBranchController::class, 'store']);
                Route::get('/{clinicBranch}', [ClinicBranchController::class, 'show']);
                Route::patch('/{clinicBranch}', [ClinicBranchController::class, 'update']);
                Route::delete('/{clinicBranch}', [ClinicBranchController::class, 'destroy']);
            });

            Route::prefix('{clinic}/dentists')->group(function (): void {
                Route::get('/', [DentistController::class, 'index']);
                Route::post('/', [DentistController::class, 'store']);
                Route::get('/{dentist}', [DentistController::class, 'show']);
                Route::patch('/{dentist}', [DentistController::class, 'update']);
                Route::delete('/{dentist}', [DentistController::class, 'destroy']);
            });

            Route::prefix('{clinic}/services')->group(function (): void {
                Route::get('/', [ServiceController::class, 'index']);
                Route::post('/', [ServiceController::class, 'store']);
                Route::get('/{service}', [ServiceController::class, 'show']);
                Route::patch('/{service}', [ServiceController::class, 'update']);
                Route::delete('/{service}', [ServiceController::class, 'destroy']);
            });
        });
    });
});
