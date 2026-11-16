@extends('admin.layout')

@section('content')
<h1 class="text-2xl font-bold mb-6">Dashboard</h1>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-sm text-gray-500 uppercase">Total Bookings</div>
        <div class="text-3xl font-bold mt-2">{{ $totalBookings }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-sm text-gray-500 uppercase">Pending Approvals</div>
        <div class="text-3xl font-bold mt-2 text-yellow-600">{{ $pendingApprovals }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-sm text-gray-500 uppercase">Active Resources</div>
        <div class="text-3xl font-bold mt-2 text-green-600">{{ $activeResources }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-sm text-gray-500 uppercase">Total Strikes</div>
        <div class="text-3xl font-bold mt-2 text-red-600">{{ $totalStrikes }}</div>
    </div>
</div>
@endsection
