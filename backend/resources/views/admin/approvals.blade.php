@extends('admin.layout')

@section('content')
<h1 class="text-2xl font-bold mb-6">Pending Approvals</h1>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-left">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="px-6 py-3 text-sm font-medium text-gray-500">ID</th>
                <th class="px-6 py-3 text-sm font-medium text-gray-500">Booking</th>
                <th class="px-6 py-3 text-sm font-medium text-gray-500">User</th>
                <th class="px-6 py-3 text-sm font-medium text-gray-500">Resource</th>
                <th class="px-6 py-3 text-sm font-medium text-gray-500">Level</th>
                <th class="px-6 py-3 text-sm font-medium text-gray-500">Expires</th>
                <th class="px-6 py-3 text-sm font-medium text-gray-500">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($approvals as $approval)
                <tr>
                    <td class="px-6 py-4">{{ $approval->id }}</td>
                    <td class="px-6 py-4">{{ $approval->booking->title ?? '#'.$approval->booking_id }}</td>
                    <td class="px-6 py-4">{{ $approval->booking->user->name ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $approval->booking->resource->name ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $approval->level }}</td>
                    <td class="px-6 py-4">{{ $approval->expires_at?->format('Y-m-d H:i') ?? '-' }}</td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('admin.approvals.approve', $approval) }}" class="flex items-center gap-1">
                                @csrf
                                <input type="text" name="notes" placeholder="Notes..." class="border rounded px-2 py-1 text-sm w-32">
                                <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.approvals.reject', $approval) }}" class="flex items-center gap-1">
                                @csrf
                                <input type="text" name="notes" placeholder="Notes..." class="border rounded px-2 py-1 text-sm w-32">
                                <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700">Reject</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-gray-500">No pending approvals.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $approvals->links() }}</div>
@endsection
