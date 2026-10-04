<?php

use App\Http\Controllers\Web\PatientPortalController;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::prefix('{locale}/panel')->whereIn('locale', ['fa', 'ar', 'en'])
    ->middleware([SetLocale::class, 'auth', EnsureActiveUser::class])->group(function (): void {
        Route::get('/patient-profile', [PatientPortalController::class, 'profile'])->name('patient.profile');
        Route::post('/patient-profile', [PatientPortalController::class, 'saveProfile'])->name('patient.profile.save');
        Route::get('/locations', [PatientPortalController::class, 'locations'])->name('patient.locations');
    });
