<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sections;

use App\Http\Controllers\Controller;
use App\Services\Atheer\AtheerApiClient;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * The Atheer business section. Renders live figures pulled from the Atheer ERP
 * report API. If the ERP is unreachable the page still renders, degraded, with
 * an "unavailable" banner rather than a hard error.
 */
class AtheerController extends Controller
{
    public function __construct(private readonly AtheerApiClient $atheer) {}

    public function index(): Response
    {
        try {
            $report = $this->atheer->summary();
            $error = null;
        } catch (Throwable $e) {
            report($e);
            $report = null;
            $error = 'Atheer ERP временно недоступен.';
        }

        return Inertia::render('sections/Atheer', [
            'report' => $report,
            'error' => $error,
        ]);
    }
}
