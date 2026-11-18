import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_error.dart';
import '../../../core/widgets/status_views.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../face/data/face_providers.dart';
import '../../face/data/face_repository.dart';
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
    final faceStatus = ref.watch(faceStatusProvider);
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
          faceStatus.maybeWhen(
            data: (status) {
              final needsFace =
                  status['enabled'] == true && status['enrolled'] != true;
              if (!needsFace) return const SizedBox.shrink();
              return Padding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                child: Material(
                  color: Theme.of(context).colorScheme.secondaryContainer,
                  borderRadius: BorderRadius.circular(16),
                  child: ListTile(
                    leading: const Icon(Icons.face_retouching_natural),
                    title: const Text('Daftarkan wajah dulu'),
                    subtitle: const Text(
                      'Wajah wajib terdaftar sebelum membuat booking ruangan.',
                    ),
                    trailing: FilledButton(
                      onPressed: _openEnrollment,
                      child: const Text('Daftar'),
                    ),
                  ),
                ),
              );
            },
            orElse: () => const SizedBox.shrink(),
          ),
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
                              : () => isRoom
                                    ? _openBooking(id)
                                    : context.push('/bookings/new/$id'),
                        );
                      },
                    ),
            ),
          ),
        ],
      ),
    );
  }

  Future<bool> _openEnrollment() async {
    final result = await context.push<bool>('/face/enrollment');
    ref.invalidate(faceStatusProvider);
    return result == true;
  }

  Future<void> _openBooking(String id) async {
    if (!await _ensureFaceEnrolled()) return;
    if (mounted) context.push('/bookings/new/$id');
  }

  Future<bool> _ensureFaceEnrolled() async {
    try {
      final status = await ref.read(faceRepositoryProvider).status();
      ref.invalidate(faceStatusProvider);
      if (status['enabled'] != true || status['enrolled'] == true) return true;
      if (!mounted) return false;

      final enroll = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          icon: const Icon(Icons.face_retouching_natural),
          title: const Text('Daftarkan wajah terlebih dahulu'),
          content: const Text(
            'Wajah wajib terdaftar sebelum membuat booking ruangan. Daftarkan wajah sekarang?',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(false),
              child: const Text('Nanti'),
            ),
            FilledButton(
              onPressed: () => Navigator.of(dialogContext).pop(true),
              child: const Text('Daftar wajah'),
            ),
          ],
        ),
      );
      if (enroll == true) return await _openEnrollment();
      return false;
    } catch (error) {
      if (!mounted) return false;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(apiErrorMessage(error))));
      return true;
    }
  }

  Future<void> _onMenu(String value) async {
    switch (value) {
      case 'face':
        await _openEnrollment();
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
