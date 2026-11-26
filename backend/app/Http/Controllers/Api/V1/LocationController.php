<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLocationRequest;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LocationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Location::all());
    }

    public function store(StoreLocationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['open_time'] ??= Location::DEFAULT_OPEN_TIME;
        $data['close_time'] ??= Location::DEFAULT_CLOSE_TIME;

        $location = Location::create($data);

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

        if ($location->resources()->exists()) {
            throw ValidationException::withMessages([
                'location' => 'Lokasi masih dipakai ruangan atau aset. Pindahkan ruangan atau aset terlebih dahulu.',
            ]);
        }

        $location->delete();

        return response()->json(['message' => 'Location deleted.']);
    }
}
