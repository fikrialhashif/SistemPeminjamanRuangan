<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\ResourceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreResourceRequest;
use App\Http\Requests\UpdateResourceRequest;
use App\Models\Category;
use App\Models\Resource;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ResourceController extends Controller
{
    public function __construct(
        protected BookingService $bookingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Resource::with(['location', 'room', 'asset', 'rules', 'category']);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('name', $request->input('category')));
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->input('location_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('capacity_min')) {
            $query->whereHas('room', fn ($q) => $q->where('capacity', '>=', $request->input('capacity_min')));
        }

        if ($request->filled('available_from') && $request->filled('available_to')) {
            $from = Carbon::parse($request->input('available_from'));
            $to = Carbon::parse($request->input('available_to'));
            $query->whereDoesntHave('bookings', fn ($q) => $q
                ->whereIn('status', collect(BookingStatus::cases())
                    ->filter(fn ($s) => $s->holdsSlot())
                    ->map(fn ($s) => $s->value)
                    ->all())
                ->where('starts_at', '<', $to)
                ->where('ends_at', '>', $from)
            );
        }

        $user = $request->user();
        if (! $user->isAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->whereDoesntHave('accessRules')
                    ->orWhereHas('accessRules', function ($q) use ($user) {
                        $q->where(function ($q) use ($user) {
                            $q->whereIn('role_id', $user->roles()->pluck('roles.id'))
                                ->orWhere('department_id', $user->department_id);
                        });
                    });
            });
        }

        $resources = $query->paginate($request->input('per_page', 15));
        $resources->getCollection()->transform(fn (Resource $resource) => $this->presentResource($resource));

        return response()->json($resources);
    }

    public function show(Resource $resource): JsonResponse
    {
        $resource->load(['location', 'room', 'asset', 'rules', 'accessRules', 'category']);

        return response()->json($this->presentResource($resource));
    }

    public function store(StoreResourceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $type = $data['type'] instanceof ResourceType ? $data['type']->value : $data['type'];
        $resource = Resource::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'location_id' => $data['location_id'] ?? null,
            'category_id' => $this->resolveCategoryId($data['category'] ?? null, $type),
            'description' => $data['description'] ?? null,
        ]);

        if ($resource->type === ResourceType::ROOM) {
            $resource->room()->create([
                'capacity' => $data['capacity'],
                'facilities' => $data['facilities'] ?? null,
                'open_time' => $data['open_time'] ?? null,
                'close_time' => $data['close_time'] ?? null,
            ]);
        }

        if ($resource->type === ResourceType::ASSET) {
            $resource->asset()->create([
                'code' => $data['code'],
                'condition' => $data['condition'] ?? 'good',
            ]);
        }

        $resource->load(['room', 'asset', 'location', 'category']);

        return response()->json($this->presentResource($resource), 201);
    }

    public function update(UpdateResourceRequest $request, Resource $resource): JsonResponse
    {
        $data = $request->validated();
        $type = $data['type'] ?? $resource->type->value;
        $type = $type instanceof ResourceType ? $type->value : $type;

        $attributes = collect($data)->only(['name', 'type', 'location_id', 'description', 'is_active'])->toArray();

        if (array_key_exists('category', $data)) {
            $attributes['category_id'] = $this->resolveCategoryId($data['category'], $type);
        }

        $resource->update($attributes);

        if ($resource->type === ResourceType::ROOM && $resource->room) {
            $resource->room->update(collect($data)->only(['capacity', 'facilities', 'open_time', 'close_time'])->toArray());
        }

        if ($resource->type === ResourceType::ASSET && $resource->asset) {
            $resource->asset->update(collect($data)->only(['code', 'condition'])->toArray());
        }

        $resource->load(['room', 'asset', 'location', 'category']);

        return response()->json($this->presentResource($resource));
    }

    private function presentResource(Resource $resource): Resource
    {
        $resource->setAttribute('category', $resource->category?->name);
        $resource->unsetRelation('category');

        return $resource;
    }

    private function resolveCategoryId(?string $name, string $type): ?int
    {
        if ($name === null || $name === '') {
            return null;
        }

        $category = Category::where('name', $name)->where('type', $type)->first();

        if (! $category) {
            throw ValidationException::withMessages([
                'category' => 'Kategori tidak ditemukan untuk jenis resource ini.',
            ]);
        }

        return $category->id;
    }

    public function destroy(Request $request, Resource $resource): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $resource->delete();

        return response()->json(['message' => 'Resource deleted.']);
    }

    public function availability(Request $request, Resource $resource): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date'],
        ]);

        $slots = $this->bookingService->getAvailability($resource, Carbon::parse($request->input('date')));

        return response()->json(['slots' => $slots]);
    }
}
