<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json(Setting::all());
    }

    public function update(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->validate([
            'key' => ['required', 'string'],
            'value' => ['required'],
            'type' => ['sometimes', 'string', 'in:string,boolean,integer,float,json,array'],
        ]);

        Setting::set(
            $request->input('key'),
            $request->input('value'),
            $request->input('type', 'string'),
        );

        return response()->json(['message' => 'Setting updated.']);
    }
}
