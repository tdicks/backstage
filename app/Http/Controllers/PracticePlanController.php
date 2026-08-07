<?php

namespace App\Http\Controllers;

use App\Services\DashboardActionQueueService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PracticePlanController extends Controller
{
    public function __invoke(Request $request, DashboardActionQueueService $queueService): View
    {
        $practiceSets = $queueService->practiceSetsForUser($request->user());

        return view('practice-plan.index', [
            'practiceSets' => $practiceSets,
        ]);
    }
}
