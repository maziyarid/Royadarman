<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CaseController;
use App\Http\Controllers\Api\V1\CmsCategoryController;
use App\Http\Controllers\Api\V1\CmsCommentController;
use App\Http\Controllers\Api\V1\CmsMediaController;
use App\Http\Controllers\Api\V1\CmsMenuController;
use App\Http\Controllers\Api\V1\CmsPostController;
use App\Http\Controllers\Api\V1\CmsRedirectController;
use App\Http\Controllers\Api\V1\CmsSeoMetadataController;
use App\Http\Controllers\Api\V1\CmsTagController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\NotificationCallbackController;
use App\Http\Controllers\Api\V1\PolicyController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ReferralController;
use App\Http\Controllers\Api\V1\StaffCaseController;
use App\Http\Controllers\Api\V1\SupportController;
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

        Route::get('/support', [SupportController::class, 'index']);
        Route::post('/support', [SupportController::class, 'store']);
        Route::get('/support/{conversation}', [SupportController::class, 'show']);
        Route::post('/support/{conversation}/messages', [SupportController::class, 'reply']);
        Route::post('/support/{conversation}/internal-notes', [SupportController::class, 'internalNote']);
        Route::patch('/support/{conversation}/status', [SupportController::class, 'status']);
        Route::post('/support/{conversation}/assignee', [SupportController::class, 'assign']);

        Route::get('/cms/posts', [CmsPostController::class, 'index']);
        Route::post('/cms/posts', [CmsPostController::class, 'store']);
        Route::get('/cms/posts/{post}', [CmsPostController::class, 'show']);
        Route::patch('/cms/posts/{post}', [CmsPostController::class, 'update']);
        Route::post('/cms/posts/{post}/publish', [CmsPostController::class, 'publish']);
        Route::post('/cms/posts/{post}/unpublish', [CmsPostController::class, 'unpublish']);
        Route::delete('/cms/posts/{post}', [CmsPostController::class, 'destroy']);

        Route::get('/cms/categories', [CmsCategoryController::class, 'index']);
        Route::post('/cms/categories', [CmsCategoryController::class, 'store']);
        Route::get('/cms/categories/{category}', [CmsCategoryController::class, 'show']);
        Route::patch('/cms/categories/{category}', [CmsCategoryController::class, 'update']);
        Route::delete('/cms/categories/{category}', [CmsCategoryController::class, 'destroy']);

        Route::get('/cms/tags', [CmsTagController::class, 'index']);
        Route::post('/cms/tags', [CmsTagController::class, 'store']);
        Route::post('/cms/tags/{tag}/merge', [CmsTagController::class, 'merge']);
        Route::delete('/cms/tags/{tag}', [CmsTagController::class, 'destroy']);

        Route::get('/cms/redirects', [CmsRedirectController::class, 'index']);
        Route::post('/cms/redirects', [CmsRedirectController::class, 'store']);
        Route::patch('/cms/redirects/{redirect}', [CmsRedirectController::class, 'update']);
        Route::delete('/cms/redirects/{redirect}', [CmsRedirectController::class, 'destroy']);

        Route::get('/cms/media', [CmsMediaController::class, 'index']);
        Route::post('/cms/media', [CmsMediaController::class, 'store']);
        Route::patch('/cms/media/{media}', [CmsMediaController::class, 'update']);
        Route::delete('/cms/media/{media}', [CmsMediaController::class, 'destroy']);

        Route::get('/cms/menus', [CmsMenuController::class, 'index']);
        Route::post('/cms/menus', [CmsMenuController::class, 'store']);
        Route::patch('/cms/menus/{menu}', [CmsMenuController::class, 'update']);
        Route::delete('/cms/menus/{menu}', [CmsMenuController::class, 'destroy']);
        Route::post('/cms/menus/{menu}/items', [CmsMenuController::class, 'storeItem']);
        Route::patch('/cms/menus/{menu}/items/{item}', [CmsMenuController::class, 'updateItem']);
        Route::delete('/cms/menus/{menu}/items/{item}', [CmsMenuController::class, 'destroyItem']);

        Route::get('/cms/seo', [CmsSeoMetadataController::class, 'index']);
        Route::post('/cms/seo', [CmsSeoMetadataController::class, 'upsert']);
        Route::delete('/cms/seo/{seoMetadata}', [CmsSeoMetadataController::class, 'destroy']);

        Route::get('/cms/comments', [CmsCommentController::class, 'index']);
        Route::patch('/cms/comments/{comment}', [CmsCommentController::class, 'moderate']);
        Route::delete('/cms/comments/{comment}', [CmsCommentController::class, 'destroy']);

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
