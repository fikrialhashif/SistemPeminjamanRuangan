<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">
    <aside class="w-64 bg-gray-900 text-white min-h-screen flex-shrink-0">
        <div class="p-6 text-xl font-bold border-b border-gray-700">Admin Panel</div>
        <nav class="mt-4">
            <a href="{{ route('admin.dashboard') }}" class="block px-6 py-3 hover:bg-gray-800 {{ request()->routeIs('admin.dashboard') ? 'bg-gray-800' : '' }}">Dashboard</a>
            <a href="{{ route('admin.approvals') }}" class="block px-6 py-3 hover:bg-gray-800 {{ request()->routeIs('admin.approvals') ? 'bg-gray-800' : '' }}">Approvals</a>
            <a href="{{ route('admin.resources') }}" class="block px-6 py-3 hover:bg-gray-800 {{ request()->routeIs('admin.resources') ? 'bg-gray-800' : '' }}">Resources</a>
            <a href="{{ route('admin.audit') }}" class="block px-6 py-3 hover:bg-gray-800 {{ request()->routeIs('admin.audit') ? 'bg-gray-800' : '' }}">Audit Logs</a>
            <form method="POST" action="{{ route('admin.logout') }}" class="mt-4">
                @csrf
                <button type="submit" class="block w-full text-left px-6 py-3 hover:bg-gray-800 text-red-400">Logout</button>
            </form>
        </nav>
    </aside>
    <main class="flex-1 p-8">
        @if(session('success'))
            <div class="mb-4 p-4 bg-green-100 text-green-800 rounded">{{ session('success') }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>
