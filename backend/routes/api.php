<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CaseController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\NotificationCallbackController;
use App\Http\Controllers\Api\V1\PolicyController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ReferralController;
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
    });
});
