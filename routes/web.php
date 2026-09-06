<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\Sections\AtheerController;
use App\Http\Controllers\Sections\SectionController;
use App\Support\Dashboard\Sections;
use Illuminate\Support\Facades\Route;

// This is a private, single-owner application. The owner-facing dashboard is
// gated behind session auth (see app:create-owner). The only unauthenticated
// internet-facing route is the Telegram webhook in routes/telegram.php.
//
// Framework health check lives at GET /up (configured in bootstrap/app.php).

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Life sections. Only "live" sections (see config/dashboard.php) have a
    // route today; the rest render placeholders in later sub-tracks.
    Route::get('atheer', [AtheerController::class, 'index'])->name('sections.atheer');

    // Generic entries-backed sections (Personal, Health, Budget, ...), one set
    // of routes per live section key from config/dashboard.php.
    foreach (Sections::entryKeys() as $sectionKey) {
        Route::get($sectionKey, [SectionController::class, 'show'])
            ->defaults('section', $sectionKey)
            ->name("sections.{$sectionKey}");

        Route::post("{$sectionKey}/entries", [SectionController::class, 'storeEntry'])
            ->defaults('section', $sectionKey)
            ->name("sections.{$sectionKey}.entries.store");
    }

    Route::delete('entries/{entry}', [SectionController::class, 'destroyEntry'])->name('entries.destroy');

    // Period reports (finance over a selectable day…year window).
    Route::get('reports', [ReportsController::class, 'index'])->name('reports');

    // Web chat with the second brain (same agent as the Telegram bot).
    Route::get('chat', [ChatController::class, 'index'])->name('chat');
    Route::post('chat', [ChatController::class, 'store'])->name('chat.store');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
