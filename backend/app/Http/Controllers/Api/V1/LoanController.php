<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\VerificationStepOutcome;
use App\Enums\LoanStatus;
use App\Enums\VerificationStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\HandoverRequest;
use App\Http\Requests\LoanOverrideRequest;
use App\Http\Requests\ReturnRequest;
use App\Models\AssetLoan;
use App\Models\ConditionReport;
use App\Models\LoanEvent;
use App\Services\FaceVerificationService;
use App\Services\GpsValidator;
use App\Services\QrTokenService;
use App\Services\VerificationSessionBuilder;
use App\Services\VerificationTimePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LoanController extends Controller
{
    public function __construct(
        protected FaceVerificationService $faceVerificationService,
        protected QrTokenService $qrTokenService,
        protected GpsValidator $gpsValidator,
        protected VerificationTimePolicy $verificationTimePolicy,
        protected VerificationSessionBuilder $verificationSessionBuilder,
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

        $timeOutcome = $assetLoan->booking
            ? $this->verificationTimePolicy->evaluateForBooking($assetLoan->booking)
            : new VerificationStepOutcome(VerificationStage::TIME, false, 'Jadwal peminjaman tidak ditemukan.');
        $locationOutcome = $this->evaluateLocation($assetLoan, $request);

        $tokenResult = $this->qrTokenService->validateForAsset(
            (string) $request->input('asset_token'),
            $assetLoan->asset->code,
        );
        $qrReason = $tokenResult['valid']
            ? null
            : ($tokenResult['reason'] === 'Asset mismatch'
                ? 'Kode aset tidak sesuai dengan pinjaman ini.'
                : 'QR aset tidak valid.');
        $qrOutcome = new VerificationStepOutcome(VerificationStage::QR, $tokenResult['valid'], $qrReason);

        $faceResult = $this->faceVerificationService->verify($request->user(), $request->input('face_image'));
        $faceValid = (bool) ($faceResult['match'] ?? false);
        $faceOutcome = new VerificationStepOutcome(
            VerificationStage::FACE,
            $faceValid,
            $faceValid ? null : 'Verifikasi wajah gagal.',
        );

        $session = $this->verificationSessionBuilder->forHandover($assetLoan->id, [
            'location' => $locationOutcome,
            'qr' => $qrOutcome,
            'face' => $faceOutcome,
            'time' => $timeOutcome,
        ]);

        if (! $session->accepted()) {
            $failure = $session->firstFailure();

            return response()->json([
                'message' => $failure?->reason ?? 'Verifikasi serah-terima gagal.',
                'reason' => $failure?->stage->value,
                'verification' => $session->toArray(),
            ], 422);
        }

        $assetCode = $assetLoan->asset->code;

        DB::transaction(function () use ($assetLoan, $request, $assetCode) {
            $assetLoan->update([
                'status' => LoanStatus::HANDED_OVER,
                'handed_over_at' => now(),
            ]);

            $assetLoan->asset->update(['status' => 'in_use']);

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
        });

        $data = $assetLoan->fresh(['asset', 'events'])->toArray();
        $data['verification'] = $session->toArray();

        return response()->json($data);
    }

    public function returnAsset(ReturnRequest $request, AssetLoan $assetLoan): JsonResponse
    {
        Gate::authorize('returnAsset', $assetLoan);

        if (! in_array($assetLoan->status, [LoanStatus::HANDED_OVER, LoanStatus::IN_USE])) {
            return response()->json(['message' => 'Loan cannot be returned in current state.'], 422);
        }

        $scanData = [];
        if ($request->filled('asset_token')) {
            $tokenResult = $this->qrTokenService->validateForAsset(
                (string) $request->input('asset_token'),
                $assetLoan->asset->code,
            );

            if (! $tokenResult['valid']) {
                return response()->json([
                    'message' => $tokenResult['reason'] === 'Asset mismatch'
                        ? 'Kode aset tidak sesuai dengan pinjaman ini.'
                        : 'QR aset tidak valid.',
                    'reason' => $tokenResult['reason'],
                ], 422);
            }

            $scanData = [
                'asset_code' => $assetLoan->asset->code,
                'scan_source' => 'static_label',
                'asset_matched' => true,
            ];
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('condition-reports', 'public');
        }

        DB::transaction(function () use ($assetLoan, $request, $photoPath, $scanData) {
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
                    ...$scanData,
                ],
            ]);

            $assetLoan->asset->update([
                'condition' => $request->input('condition'),
                'status' => 'available',
            ]);
        });

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

    private function evaluateLocation(AssetLoan $assetLoan, Request $request): VerificationStepOutcome
    {
        $assetLoan->loadMissing('asset.resource.location');
        $location = $assetLoan->asset->resource->location;

        if ($location === null) {
            return new VerificationStepOutcome(
                VerificationStage::LOCATION,
                false,
                'Lokasi serah-terima aset belum tersedia.',
            );
        }

        $result = $this->gpsValidator->validate(
            $location,
            (float) $request->input('gps_latitude'),
            (float) $request->input('gps_longitude'),
            (float) $request->input('gps_accuracy'),
            (string) ($request->input('gps_timestamp') ?? now()->toIso8601String()),
            (bool) $request->input('gps_is_mock', false),
            $request->user()->id,
        );

        return new VerificationStepOutcome(
            VerificationStage::LOCATION,
            $result['valid'],
            $result['valid'] ? null : implode('; ', $result['reasons']),
        );
    }
}
