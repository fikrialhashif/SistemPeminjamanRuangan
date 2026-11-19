import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_chrome.dart';
import '../../../core/widgets/app_components.dart';
import '../../../core/widgets/status_views.dart';
import '../../../core/widgets/user_quick_access_sheet.dart';
import '../../auth/presentation/auth_controller.dart';
import 'resource_providers.dart';

class RoomFeaturesPage extends ConsumerWidget {
  const RoomFeaturesPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final rooms = ref.watch(
      resourceListProvider(const ResourceFilter(type: 'room')),
    );
    final showApprovals = ref.watch(approvalAccessProvider);
    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            AppBrandHeader(
              section: 'Fitur Ruang',
              onAvatarTap: () => _openQuickAccess(context),
            ),
            Expanded(
              child: rooms.when(
                loading: () => const Center(
                  child: CircularProgressIndicator(
                    semanticsLabel: 'Memuat fitur ruang',
                  ),
                ),
                error: (error, _) => ErrorView(
                  error: error,
                  onRetry: () => ref.invalidate(
                    resourceListProvider(const ResourceFilter(type: 'room')),
                  ),
                ),
                data: (items) => RefreshIndicator(
                  onRefresh: () => ref.refresh(
                    resourceListProvider(const ResourceFilter(type: 'room'))
                        .future,
                  ),
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(18, 18, 18, 24),
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              'Fitur Ruang',
                              style: Theme.of(context).textTheme.headlineSmall,
                            ),
                          ),
                          IconButton(
                            tooltip: 'Muat ulang',
                            onPressed: () => ref.invalidate(
                              resourceListProvider(
                                const ResourceFilter(type: 'room'),
                              ),
                            ),
                            icon: const Icon(Icons.refresh_rounded),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      const _RoomHero(),
                      const SizedBox(height: 18),
                      Text(
                        'Ruangan tersedia',
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                      const SizedBox(height: 10),
                      if (items.isEmpty)
                        const SizedBox(
                          height: 260,
                          child: EmptyView(
                            message: 'Belum ada ruangan yang tersedia.',
                            icon: Icons.meeting_room_outlined,
                          ),
                        )
                      else
                        for (final (index, item) in items.indexed) ...[
                          _RoomCard(
                            item: item,
                            index: index,
                            onBook: () => _book(context, item),
                          ),
                          const SizedBox(height: 12),
                        ],
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: AppBottomNav(
        currentIndex: 1,
        showApprovals: showApprovals,
      ),
    );
  }

  Future<void> _book(BuildContext context, Map<String, dynamic> item) async {
    final id = item['id']?.toString();
    if (id == null) return;
    await context.push('/rooms/$id/schedule');
  }

  void _openQuickAccess(BuildContext context) {
    showUserQuickAccessSheet(context);
  }
}

class _RoomHero extends StatelessWidget {
  const _RoomHero();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [AppColors.primaryDark, AppColors.primary],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Pilih ruang, atur waktu, mulai kerja.',
                  style: Theme.of(context).textTheme.titleLarge
                      ?.copyWith(color: Colors.white, height: 1.25),
                ),
                const SizedBox(height: 8),
                Text(
                  'Lihat ruangan yang tersedia dan lanjutkan ke jadwal booking.',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Colors.white.withValues(alpha: 0.78),
                    height: 1.4,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Container(
            width: 58,
            height: 58,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.14),
              borderRadius: BorderRadius.circular(18),
            ),
            child: const Icon(
              Icons.meeting_room_rounded,
              color: Colors.white,
              size: 30,
            ),
          ),
        ],
      ),
    );
  }
}

class _RoomCard extends StatelessWidget {
  const _RoomCard({
    required this.item,
    required this.index,
    required this.onBook,
  });

  final Map<String, dynamic> item;
  final int index;
  final VoidCallback onBook;

  @override
  Widget build(BuildContext context) {
    final name = item['name']?.toString() ?? 'Ruangan';
    final location = item['location'];
    final locationName = location is Map
        ? location['name']?.toString()
        : location?.toString();
    final detail = item['room'];
    final capacity = detail is Map ? detail['capacity'] : item['capacity'];
    final gradients = [
      const [Color(0xFF1B6A46), Color(0xFF63B68B)],
      const [Color(0xFF275D57), Color(0xFF7BC4AE)],
      const [Color(0xFF3F5B34), Color(0xFF9EC27E)],
    ];
    final gradient = gradients[index % gradients.length];

    return Container(
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.border),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            height: 112,
            child: Stack(
              fit: StackFit.expand,
              children: [
                DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: gradient,
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                  ),
                ),
                Positioned(
                  right: -12,
                  bottom: -18,
                  child: Icon(
                    Icons.apartment_rounded,
                    size: 132,
                    color: Colors.white.withValues(alpha: 0.16),
                  ),
                ),
                const Positioned(
                  left: 14,
                  top: 14,
                  child: AppPill(
                    label: 'RUANGAN',
                    backgroundColor: Color(0x33FFFFFF),
                    foregroundColor: Colors.white,
                  ),
                ),
                const Positioned(
                  left: 14,
                  bottom: 14,
                  child: Icon(
                    Icons.meeting_room_rounded,
                    color: Colors.white,
                    size: 30,
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(name, style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 4),
                Text(
                  [
                    locationName ?? 'Lokasi belum tersedia',
                    if (capacity != null) 'Kapasitas $capacity',
                  ].join(' • '),
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                const SizedBox(height: 14),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton.icon(
                    onPressed: onBook,
                    icon: const Icon(Icons.calendar_month_outlined, size: 18),
                    label: const Text('Lihat jadwal'),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
