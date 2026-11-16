<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FaceEnrollRequest;
use App\Services\FaceVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaceController extends Controller
{
    public function __construct(
        protected FaceVerificationService $faceVerificationService,
    ) {}

    public function enroll(FaceEnrollRequest $request): JsonResponse
    {
        $user = $request->user();
        $result = $this->faceVerificationService->enroll($user, $request->input('images'));

        $user->faceEnrollment()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'consent_given_at' => now(),
                'status' => 'enrolled',
                'samples_count' => count($request->input('images')),
            ],
        );

        return response()->json($result, 201);
    }

    public function status(Request $request): JsonResponse
    {
        $enrollment = $request->user()->faceEnrollment;

        return response()->json([
            'enrolled' => $enrollment !== null && $enrollment->status === 'enrolled',
            'enrollment' => $enrollment,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->faceVerificationService->deleteUserData($user);
        $user->faceEnrollment?->delete();

        return response()->json(['message' => 'Face data deleted.']);
    }
}
