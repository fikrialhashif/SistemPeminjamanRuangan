<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLocationRequest;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Location::all());
    }

    public function store(StoreLocationRequest $request): JsonResponse
    {
        $location = Location::create($request->validated());

        return response()->json($location, 201);
    }

    public function update(StoreLocationRequest $request, Location $location): JsonResponse
    {
        $location->update($request->validated());

        return response()->json($location);
    }

    public function destroy(Request $request, Location $location): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $location->delete();

        return response()->json(['message' => 'Location deleted.']);
    }
}
