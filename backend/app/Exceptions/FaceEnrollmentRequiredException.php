<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FaceEnrollmentRequiredException extends HttpException
{
    public function __construct()
    {
        parent::__construct(
            422,
            'Daftarkan wajah Anda terlebih dahulu sebelum membuat booking ruangan.',
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code' => 'face_enrollment_required',
            'message' => $this->getMessage(),
        ], 422);
    }
}
