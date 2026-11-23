<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LoanStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\HandoverRequest;
use App\Http\Requests\LoanOverrideRequest;
use App\Http\Requests\ReturnRequest;
use App\Models\AssetLoan;
use App\Models\ConditionReport;
use App\Models\LoanEvent;
use App\Services\FaceVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LoanController extends Controller
{
    public function __construct(
        protected FaceVerificationService $faceVerificationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = AssetLoan::with(['asset.resource', 'booking', 'user']);

        if (! $user->isAdmin() && ! $user->hasRole('resource_manager')) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json($query->orderByDesc('created_at')->paginate($request->input('per_page', 15)));
    }

    public function handover(HandoverRequest $request, AssetLoan $assetLoan): JsonResponse
    {
        Gate::authorize('handover', $assetLoan);

        if ($assetLoan->status !== LoanStatus::RESERVED) {
            return response()->json(['message' => 'Loan is not in reserved state.'], 422);
        }

        $assetCode = trim((string) $request->input('asset_code'));
        if (! hash_equals($assetLoan->asset->code, $assetCode)) {
            return response()->json(['message' => 'Kode aset tidak sesuai dengan pinjaman ini.'], 422);
        }

        $faceResult = $this->faceVerificationService->verify($request->user(), $request->input('face_image'));

        if (! ($faceResult['match'] ?? false)) {
            return response()->json(['message' => 'Face verification failed.'], 422);
        }

        $assetLoan->update([
            'status' => LoanStatus::HANDED_OVER,
            'handed_over_at' => now(),
        ]);

        LoanEvent::create([
            'asset_loan_id' => $assetLoan->id,
            'event_type' => 'handover',
            'actor_id' => $request->user()->id,
            'data' => [
                'gps_latitude' => $request->input('gps_latitude'),
                'gps_longitude' => $request->input('gps_longitude'),
                'gps_accuracy' => $request->input('gps_accuracy'),
                'asset_code' => $assetCode,
                'scan_source' => 'static_label',
                'asset_matched' => true,
            ],
        ]);

        return response()->json($assetLoan->fresh(['asset', 'events']));
    }

    public function returnAsset(ReturnRequest $request, AssetLoan $assetLoan): JsonResponse
    {
        Gate::authorize('returnAsset', $assetLoan);

        if (! in_array($assetLoan->status, [LoanStatus::HANDED_OVER, LoanStatus::IN_USE])) {
            return response()->json(['message' => 'Loan cannot be returned in current state.'], 422);
        }

        if ($request->filled('asset_code') && ! hash_equals($assetLoan->asset->code, trim((string) $request->input('asset_code')))) {
            return response()->json(['message' => 'Kode aset tidak sesuai dengan pinjaman ini.'], 422);
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('condition-reports', 'public');
        }

        $assetLoan->update([
            'status' => LoanStatus::RETURNED,
            'returned_at' => now(),
        ]);

        ConditionReport::create([
            'asset_loan_id' => $assetLoan->id,
            'reporter_id' => $request->user()->id,
            'condition' => $request->input('condition'),
            'notes' => $request->input('notes'),
            'photo_path' => $photoPath,
        ]);

        LoanEvent::create([
            'asset_loan_id' => $assetLoan->id,
            'event_type' => 'return',
            'actor_id' => $request->user()->id,
            'data' => [
                'condition' => $request->input('condition'),
                ...($request->filled('asset_code') ? [
                    'asset_code' => trim((string) $request->input('asset_code')),
                    'scan_source' => 'static_label',
                    'asset_matched' => true,
                ] : []),
            ],
        ]);

        return response()->json($assetLoan->fresh(['asset', 'conditionReports']));
    }

    public function override(LoanOverrideRequest $request, AssetLoan $assetLoan): JsonResponse
    {
        if (! in_array($assetLoan->status, [LoanStatus::RESERVED, LoanStatus::HANDED_OVER, LoanStatus::IN_USE], true)) {
            return response()->json(['message' => 'Loan cannot be overridden in current state.'], 422);
        }

        DB::transaction(function () use ($request, $assetLoan) {
            $assetLoan->update([
                'status' => LoanStatus::HANDED_OVER,
                'handed_over_at' => $assetLoan->handed_over_at ?? now(),
            ]);

            LoanEvent::create([
                'asset_loan_id' => $assetLoan->id,
                'event_type' => 'verification_override',
                'actor_id' => $request->user()->id,
                'data' => ['reason' => $request->validated('reason')],
            ]);
        });

        return response()->json($assetLoan->fresh(['asset', 'events']));
    }

    public function reportDamage(Request $request, AssetLoan $assetLoan): JsonResponse
    {
        Gate::authorize('reportDamage', $assetLoan);

        $request->validate([
            'notes' => ['required', 'string'],
            'photo' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('condition-reports', 'public');
        }

        $report = ConditionReport::create([
            'asset_loan_id' => $assetLoan->id,
            'reporter_id' => $request->user()->id,
            'condition' => 'damaged',
            'notes' => $request->input('notes'),
            'photo_path' => $photoPath,
        ]);

        LoanEvent::create([
            'asset_loan_id' => $assetLoan->id,
            'event_type' => 'damage_report',
            'actor_id' => $request->user()->id,
            'data' => ['notes' => $request->input('notes')],
        ]);

        return response()->json($report, 201);
    }
}
