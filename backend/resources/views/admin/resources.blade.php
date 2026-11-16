@extends('admin.layout')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold">Resources</h1>
    <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Add Resource</button>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-left">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="px-6 py-3 text-sm font-medium text-gray-500">ID</th>
                <th class="px-6 py-3 text-sm font-medium text-gray-500">Name</th>
                <th class="px-6 py-3 text-sm font-medium text-gray-500">Type</th>
                <th class="px-6 py-3 text-sm font-medium text-gray-500">Location</th>
                <th class="px-6 py-3 text-sm font-medium text-gray-500">Category</th>
                <th class="px-6 py-3 text-sm font-medium text-gray-500">Details</th>
                <th class="px-6 py-3 text-sm font-medium text-gray-500">Active</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($resources as $resource)
                <tr>
                    <td class="px-6 py-4">{{ $resource->id }}</td>
                    <td class="px-6 py-4">{{ $resource->name }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded text-xs font-medium {{ $resource->type->value === 'room' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' }}">
                            {{ $resource->type->value }}
                        </span>
                    </td>
                    <td class="px-6 py-4">{{ $resource->location->name ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $resource->category ?? '-' }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        @if($resource->room)
                            Capacity: {{ $resource->room->capacity }}
                        @elseif($resource->asset)
                            Code: {{ $resource->asset->code }}, {{ $resource->asset->condition }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-block w-3 h-3 rounded-full {{ $resource->is_active ? 'bg-green-500' : 'bg-red-500' }}"></span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-gray-500">No resources found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $resources->links() }}</div>

<div id="createModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Add Resource</h2>
            <button onclick="document.getElementById('createModal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.resources.store') }}">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" name="name" required class="w-full border border-gray-300 rounded px-3 py-2">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                <select name="type" required class="w-full border border-gray-300 rounded px-3 py-2">
                    <option value="room">Room</option>
                    <option value="asset">Asset</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                <select name="location_id" required class="w-full border border-gray-300 rounded px-3 py-2">
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <input type="text" name="category" class="w-full border border-gray-300 rounded px-3 py-2">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="3" class="w-full border border-gray-300 rounded px-3 py-2"></textarea>
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 font-medium">Create</button>
        </form>
    </div>
</div>
@endsection
