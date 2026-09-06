<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * The dashboard home: an overview of every life section. Individual sections
 * (Atheer, Personal, ...) get their own controllers in later sub-tracks.
 */
class DashboardController extends Controller
{
    public function index(): Response
    {
        // Sections are shared globally (HandleInertiaRequests) for the sidebar,
        // so the home grid reads them from shared props too.
        return Inertia::render('Dashboard');
    }
}
