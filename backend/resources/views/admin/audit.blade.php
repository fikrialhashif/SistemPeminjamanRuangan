@extends('admin.layout')

@section('content')
<h1 class="text-2xl font-bold mb-6">Audit Logs</h1>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-left">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="px-6 py-3 text-sm font-medium text-gray-500">ID</th>
                <th class="px-6 py-3 text-sm font-medium text-gray-500">User</th>
                <th class="px-6 py-3 text-sm font-medium text-gray-500">Action</th>
                <th class="px-6 py-3 text-sm font-medium text-gray-500">Target</th>
                <th class="px-6 py-3 text-sm font-medium text-gray-500">IP</th>
                <th class="px-6 py-3 text-sm font-medium text-gray-500">Date</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($logs as $log)
                <tr>
                    <td class="px-6 py-4">{{ $log->id }}</td>
                    <td class="px-6 py-4">{{ $log->user->name ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $log->action }}</td>
                    <td class="px-6 py-4 text-sm">{{ class_basename($log->auditable_type ?? '') }} #{{ $log->auditable_id }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $log->ip_address }}</td>
                    <td class="px-6 py-4 text-sm">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">No audit logs found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $logs->links() }}</div>
@endsection
