<?php

use App\Http\Controllers\Tenancy\WorkspaceController;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureRecentAuthentication;
use Illuminate\Support\Facades\Route;

// W01 owns inclusion from web.php. This is the same production route module loaded by isolated tests.
Route::prefix('tenancy/v1')->name('tenancy.')->middleware(['web', 'auth', EnsureActiveUser::class])->group(function (): void {
    Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::get('workspace', [WorkspaceController::class, 'current'])->name('workspace.current');
    Route::post('workspace', [WorkspaceController::class, 'select'])->name('workspace.select');
    Route::middleware(EnsureRecentAuthentication::class)->group(function (): void {
        Route::post('organisations', [WorkspaceController::class, 'organisation'])->name('organisations.store');
        Route::post('organisations/{clinic}/branches', [WorkspaceController::class, 'branch'])->whereUlid('clinic')->name('branches.store');
        Route::post('organisations/{clinic}/memberships', [WorkspaceController::class, 'assign'])->whereUlid('clinic')->name('memberships.store');
        Route::delete('organisations/{clinic}/memberships/{membership}', [WorkspaceController::class, 'revoke'])->whereUlid(['clinic', 'membership'])->name('memberships.revoke');
    });
});
