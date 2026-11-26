<?php

namespace App\Http\Controllers\Web;

use App\Enums\Permission;
use App\Enums\ResourceType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Facility;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

class MasterDataController extends Controller
{
    public function categories(): View
    {
        $this->ensurePermission(Permission::CATEGORIES_VIEW);

        $categories = Category::withCount('resources')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return view('admin.categories', compact('categories'));
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->ensurePermission(Permission::CATEGORIES_CREATE);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->where('type', $request->input('type')),
            ],
            'type' => ['required', new Enum(ResourceType::class)],
        ]);

        Category::create($validated);

        return back()->with('success', 'Kategori ditambahkan.');
    }

    public function updateCategory(Request $request, Category $category): RedirectResponse
    {
        $this->ensurePermission(Permission::CATEGORIES_UPDATE);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')
                    ->where('type', $request->input('type'))
                    ->ignore($category->id),
            ],
            'type' => ['required', new Enum(ResourceType::class)],
        ]);

        $category->update($validated);

        return back()->with('success', 'Kategori diperbarui.');
    }

    public function destroyCategory(Category $category): RedirectResponse
    {
        $this->ensurePermission(Permission::CATEGORIES_DELETE);

        if ($category->resources()->exists()) {
            return back()->withErrors(['category' => 'Kategori masih dipakai resource. Pindahkan resource terlebih dahulu.']);
        }

        $category->delete();

        return back()->with('success', 'Kategori dihapus.');
    }

    public function facilities(): View
    {
        $this->ensurePermission(Permission::FACILITIES_VIEW);

        $facilities = Facility::orderBy('name')->get();

        return view('admin.facilities', compact('facilities'));
    }

    public function storeFacility(Request $request): RedirectResponse
    {
        $this->ensurePermission(Permission::FACILITIES_CREATE);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:facilities,name'],
        ]);

        Facility::create($validated);

        return back()->with('success', 'Fasilitas ditambahkan.');
    }

    public function updateFacility(Request $request, Facility $facility): RedirectResponse
    {
        $this->ensurePermission(Permission::FACILITIES_UPDATE);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('facilities', 'name')->ignore($facility->id)],
        ]);

        // Rooms store facility names, so a rename has to be carried over to them.
        DB::transaction(function () use ($facility, $validated): void {
            $previousName = $facility->name;
            $facility->update($validated);

            Room::whereJsonContains('facilities', $previousName)->get()->each(
                fn (Room $room) => $room->update([
                    'facilities' => array_map(
                        fn (string $name): string => $name === $previousName ? $facility->name : $name,
                        $room->facilities,
                    ),
                ]),
            );
        });

        return back()->with('success', 'Fasilitas diperbarui.');
    }

    public function destroyFacility(Facility $facility): RedirectResponse
    {
        $this->ensurePermission(Permission::FACILITIES_DELETE);

        if (Room::whereJsonContains('facilities', $facility->name)->exists()) {
            return back()->withErrors(['facility' => 'Fasilitas masih dipakai ruangan. Lepaskan dari ruangan terlebih dahulu.']);
        }

        $facility->delete();

        return back()->with('success', 'Fasilitas dihapus.');
    }
}
