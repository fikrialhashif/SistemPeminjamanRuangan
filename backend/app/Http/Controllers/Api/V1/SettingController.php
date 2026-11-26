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

        $type = $request->input('type')
            ?? Setting::where('key', $request->input('key'))->value('type')
            ?? 'string';

        Setting::set($request->input('key'), $request->input('value'), $type);

        return response()->json(['message' => 'Setting updated.']);
    }
}
