import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/widgets/status_views.dart';
import '../../auth/presentation/auth_controller.dart';
import 'resource_providers.dart';

class ResourcesPage extends ConsumerStatefulWidget {
  const ResourcesPage({super.key});

  @override
  ConsumerState<ResourcesPage> createState() => _ResourcesPageState();
}

class _ResourcesPageState extends ConsumerState<ResourcesPage> {
  String search = '';
  String? type;

  @override
  Widget build(BuildContext context) {
    final resources = ref.watch(
      resourceListProvider(ResourceFilter(search: search, type: type)),
    );
    return Scaffold(
      appBar: AppBar(
        title: const Text('Resource'),
        actions: [
          IconButton(
            tooltip: 'Booking saya',
            onPressed: () => context.push('/bookings'),
            icon: const Icon(Icons.event_note),
          ),
          IconButton(
            tooltip: 'Persetujuan',
            onPressed: () => context.push('/approvals'),
            icon: const Icon(Icons.approval),
          ),
          PopupMenuButton<String>(
            tooltip: 'Menu lainnya',
            onSelected: _onMenu,
            itemBuilder: (context) => const [
              PopupMenuItem(value: 'face', child: Text('Daftar wajah')),
              PopupMenuItem(value: 'notifications', child: Text('Notifikasi')),
              PopupMenuItem(value: 'penalties', child: Text('Status penalti')),
              PopupMenuItem(value: 'loans', child: Text('Peminjaman aset')),
              PopupMenuDivider(),
              PopupMenuItem(value: 'logout', child: Text('Keluar')),
            ],
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Semantics(
              textField: true,
              label: 'Cari resource',
              child: TextField(
                decoration: const InputDecoration(
                  labelText: 'Cari resource',
                  prefixIcon: Icon(Icons.search),
                ),
                onChanged: (value) => setState(() => search = value),
              ),
            ),
          ),
          Wrap(
            spacing: 8,
            children: [
              FilterChip(
                label: const Text('Semua'),
                selected: type == null,
                onSelected: (_) => setState(() => type = null),
              ),
              FilterChip(
                label: const Text('Ruangan'),
                selected: type == 'room',
                onSelected: (_) => setState(() => type = 'room'),
              ),
              FilterChip(
                label: const Text('Aset'),
                selected: type == 'asset',
                onSelected: (_) => setState(() => type = 'asset'),
              ),
            ],
          ),
          Expanded(
            child: resources.when(
              loading: () => const Center(
                child: CircularProgressIndicator(
                  semanticsLabel: 'Memuat resource',
                ),
              ),
              error: (error, _) => ErrorView(
                error: error,
                onRetry: () => ref.invalidate(
                  resourceListProvider(
                    ResourceFilter(search: search, type: type),
                  ),
                ),
              ),
              data: (items) => items.isEmpty
                  ? const EmptyView(
                      message: 'Resource tidak ditemukan.',
                      icon: Icons.meeting_room_outlined,
                    )
                  : ListView.builder(
                      itemCount: items.length,
                      itemBuilder: (_, index) {
                        final item = items[index];
                        final id = item['id']?.toString();
                        final isRoom = item['type'] == 'room';
                        return ListTile(
                          leading: Icon(
                            isRoom ? Icons.meeting_room : Icons.inventory_2,
                          ),
                          title: Text(item['name']?.toString() ?? '-'),
                          subtitle: Text(
                            [
                              isRoom ? 'Ruangan' : 'Aset',
                              if (item['location'] is Map &&
                                  (item['location'] as Map)['name'] != null)
                                (item['location'] as Map)['name'].toString(),
                              if (item['capacity'] != null)
                                'Kapasitas ${item['capacity']}',
                            ].join(' • '),
                          ),
                          trailing: const Icon(Icons.chevron_right),
                          onTap: id == null
                              ? null
                              : () => context.push('/bookings/new/$id'),
                        );
                      },
                    ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _onMenu(String value) async {
    switch (value) {
      case 'face':
        await context.push('/face/enrollment');
      case 'notifications':
        await context.push('/notifications');
      case 'penalties':
        await context.push('/penalties');
      case 'loans':
        await context.push('/manager/loans');
      case 'logout':
        await ref.read(authControllerProvider.notifier).logout();
        if (mounted) context.go('/');
    }
  }
}
