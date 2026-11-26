<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApprovalDecisionRequest;
use App\Models\Approval;
use App\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(
        protected ApprovalService $approvalService,
    ) {}

    public function pending(Request $request): JsonResponse
    {
        if ($forbidden = $this->forbiddenResponse($request, Permission::APPROVALS_VIEW)) {
            return $forbidden;
        }

        $approvals = Approval::actionable()
            ->with(['booking.resource', 'booking.user'])
            ->orderBy('expires_at')
            ->paginate($request->input('per_page', 15));

        return response()->json($approvals);
    }

    public function approve(ApprovalDecisionRequest $request, Approval $approval): JsonResponse
    {
        if ($forbidden = $this->forbiddenResponse($request, Permission::APPROVALS_UPDATE)) {
            return $forbidden;
        }

        $this->approvalService->approve($approval, $request->user(), $request->input('notes'));

        return response()->json(['message' => 'Approved.']);
    }

    public function reject(ApprovalDecisionRequest $request, Approval $approval): JsonResponse
    {
        if ($forbidden = $this->forbiddenResponse($request, Permission::APPROVALS_UPDATE)) {
            return $forbidden;
        }

        $this->approvalService->reject($approval, $request->user(), $request->input('notes'));

        return response()->json(['message' => 'Rejected.']);
    }

    private function forbiddenResponse(Request $request, Permission $permission): ?JsonResponse
    {
        if ($request->user()->hasPermission($permission)) {
            return null;
        }

        return response()->json(['message' => 'Forbidden.'], 403);
    }
}
