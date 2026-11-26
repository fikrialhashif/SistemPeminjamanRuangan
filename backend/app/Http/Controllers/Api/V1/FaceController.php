<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FaceEnrollRequest;
use App\Models\FaceEnrollment;
use App\Models\Setting;
use App\Services\FaceVerificationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FaceController extends Controller
{
    public function __construct(
        protected FaceVerificationService $faceVerificationService,
    ) {}

    public function enroll(FaceEnrollRequest $request): JsonResponse
    {
        $user = $request->user();

        try {
            $result = $this->faceVerificationService->enroll(
                $user,
                $request->input('images'),
                $request->input('poses'),
            );
        } catch (RequestException $error) {
            $detail = $error->response?->json('detail') ?? 'Face enrollment failed.';

            return response()->json([
                'code' => 'face_enrollment_failed',
                'message' => is_string($detail) ? $detail : 'Face enrollment failed.',
            ], 422);
        } catch (\Throwable) {
            return response()->json([
                'code' => 'face_service_unavailable',
                'message' => 'Layanan wajah tidak tersedia. Coba lagi nanti.',
            ], 503);
        }

        $enrollment = FaceEnrollment::withTrashed()->firstOrNew(['user_id' => $user->id]);
        $enrollment->fill([
            'consent_given_at' => now(),
            'status' => 'enrolled',
            'samples_count' => count($request->input('images')),
        ]);
        // A deleted enrollment still occupies the unique user_id, so it is revived.
        $enrollment->deleted_at = null;
        $enrollment->save();

        return response()->json($result, 201);
    }

    public function status(Request $request): JsonResponse
    {
        $enrollment = $request->user()->faceEnrollment;

        return response()->json([
            'enrolled' => $enrollment !== null && $enrollment->status === 'enrolled',
            'enabled' => (bool) Setting::get('face_verification_enabled', true),
            'enrollment' => $enrollment,
        ]);
    }

    public function challenge(Request $request): JsonResponse
    {
        $challenge = $this->faceVerificationService->challenge();
        $type = $challenge['challenge_type'] ?? null;

        if (is_string($type)) {
            Cache::put(
                "face_challenge:{$request->user()->id}",
                $type,
                now()->addMinutes(5),
            );
        }

        return response()->json($challenge);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $deleted = $this->faceVerificationService->deleteUserData($user);
        } catch (ConnectionException) {
            $deleted = false;
        }

        // Keep the local record so the user can retry until the biometric data is really gone.
        if (! $deleted) {
            return response()->json([
                'code' => 'face_service_unavailable',
                'message' => 'Data wajah belum terhapus karena layanan wajah tidak tersedia. Coba lagi nanti.',
            ], 503);
        }

        $user->faceEnrollment?->delete();

        return response()->json(['message' => 'Face data deleted.']);
    }
}
