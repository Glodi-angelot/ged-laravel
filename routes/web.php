<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ProfileController;

use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\FolderController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\WorkflowController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\SearchController;

use App\Http\Controllers\User\UserHomeController;
use App\Http\Controllers\User\UserDocumentController;

/*
|--------------------------------------------------------------------------| Public
|--------------------------------------------------------------------------|
*/
Route::get('/', function () {
    return view('welcome');
})->name('welcome');

/*
|--------------------------------------------------------------------------| ✅ Dashboard pivot (évite les erreurs /dashboard)
|--------------------------------------------------------------------------|
*/
Route::get('/dashboard', function () {
    $user = auth()->user();

    if ($user?->hasRole('admin')) {
        return redirect()->route('admin.dashboard');
    }

    if ($user?->hasRole('user')) {
        return redirect()->route('user.home');
    }

    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login')->with('status', "Compte non autorisé.");
})->middleware(['auth'])->name('dashboard');

/*
|--------------------------------------------------------------------------| Authenticated (profil)
|--------------------------------------------------------------------------|
*/
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------| Admin (auth + role:admin)
|--------------------------------------------------------------------------|
*/
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::resource('services', ServiceController::class);
        Route::resource('users', UserController::class)->except(['show']);
        Route::resource('folders', FolderController::class)->except(['show']);
        Route::resource('documents', DocumentController::class)->except(['show']);

        Route::get('documents/{document}/preview', [DocumentController::class, 'preview'])
            ->name('documents.preview');

        Route::get('documents/{document}/download', [DocumentController::class, 'download'])
            ->name('documents.download');

        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
        Route::delete('audit/{log}', [AuditLogController::class, 'destroy'])->name('audit.destroy');
        Route::delete('audit', [AuditLogController::class, 'clear'])->name('audit.clear');

        Route::get('search', [SearchController::class, 'index'])->name('search.index');

        Route::get('workflow/validation', [WorkflowController::class, 'validation'])->name('workflow.validation');
        Route::patch('documents/{document}/status', [WorkflowController::class, 'updateStatus'])->name('documents.status');
    });

/*
|--------------------------------------------------------------------------| User portal (auth + role:user)
|--------------------------------------------------------------------------|
*/
Route::middleware(['auth', 'role:user'])
    ->prefix('user')
    ->name('user.')
    ->group(function () {

        Route::get('/', [UserHomeController::class, 'index'])->name('home');

        Route::get('/documents', [UserDocumentController::class, 'index'])->name('documents.index');

        Route::get('/documents/{document}/preview', [UserDocumentController::class, 'preview'])
            ->name('documents.preview');

        Route::get('/documents/{document}/download', [UserDocumentController::class, 'download'])
            ->name('documents.download');
    });

require __DIR__ . '/auth.php';