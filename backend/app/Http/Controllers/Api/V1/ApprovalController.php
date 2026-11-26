<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApprovalDecisionRequest;
use App\Models\Approval;
use App\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ApprovalController extends Controller
{
    public function __construct(
        protected ApprovalService $approvalService,
    ) {}

    public function pending(Request $request): JsonResponse
    {
        if (! $request->user()->hasPermission(Permission::APPROVALS_VIEW)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $approvals = Approval::actionable()
            ->withinAuthorityOf($request->user(), Permission::APPROVALS_VIEW)
            ->with(['booking.resource', 'booking.user'])
            ->orderBy('expires_at')
            ->paginate($request->input('per_page', 15));

        return response()->json($approvals);
    }

    public function approve(ApprovalDecisionRequest $request, Approval $approval): JsonResponse
    {
        Gate::authorize('decide', $approval);

        $this->approvalService->approve($approval, $request->user(), $request->input('notes'));

        return response()->json(['message' => 'Approved.']);
    }

    public function reject(ApprovalDecisionRequest $request, Approval $approval): JsonResponse
    {
        Gate::authorize('decide', $approval);

        $this->approvalService->reject($approval, $request->user(), $request->input('notes'));

        return response()->json(['message' => 'Rejected.']);
    }
}
