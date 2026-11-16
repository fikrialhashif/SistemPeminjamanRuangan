<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Strike;
use App\Services\PenaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PenaltyController extends Controller
{
    public function __construct(
        protected PenaltyService $penaltyService,
    ) {}

    public function strikes(Request $request): JsonResponse
    {
        $strikes = $request->user()->strikes()
            ->orderByDesc('created_at')
            ->get();

        return response()->json($strikes);
    }

    public function cancelStrike(Request $request, Strike $strike): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->validate([
            'reason' => ['required', 'string'],
        ]);

        $this->penaltyService->cancelStrike($strike, $request->user(), $request->input('reason'));

        return response()->json(['message' => 'Strike cancelled.']);
    }
}
