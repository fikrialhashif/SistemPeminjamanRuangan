<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            NotificationLog::query()->where('user_id', $request->user()->id)
                ->latest()->paginate($request->integer('per_page', 20))
        );
    }

    public function registerToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['required', 'in:android,ios'],
        ]);

        $token = DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            ['user_id' => $request->user()->id, 'platform' => $data['platform'], 'last_seen_at' => now()],
        );

        return response()->json(['id' => $token->id, 'registered' => true]);
    }
}
