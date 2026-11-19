import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_chrome.dart';
import '../../../core/widgets/app_components.dart';
import '../../../core/widgets/status_views.dart';
import '../../../core/widgets/user_quick_access_sheet.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../face/data/face_providers.dart';
import 'resource_entry.dart';
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
    final showApprovals = ref.watch(approvalAccessProvider);
    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            AppBrandHeader(
              section: 'Daftar Resource',
              onNotificationsTap: () => context.push('/notifications'),
              onAvatarTap: () => showUserQuickAccessSheet(context),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 16, 10, 0),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      'Resource',
                      style: Theme.of(context).textTheme.headlineSmall,
                    ),
                  ),
                  IconButton(
                    tooltip: 'Booking saya',
                    onPressed: () => context.push('/bookings'),
                    icon: const Icon(Icons.calendar_month_outlined),
                  ),
                  IconButton(
                    tooltip: 'Muat ulang',
                    onPressed: () => ref.invalidate(
                      resourceListProvider(
                        ResourceFilter(search: search, type: type),
                      ),
                    ),
                    icon: const Icon(Icons.refresh_rounded),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 12, 18, 0),
              child: Semantics(
                textField: true,
                label: 'Cari resource',
                child: TextField(
                  decoration: InputDecoration(
                    hintText: 'Cari resource',
                    prefixIcon: const Icon(Icons.search_rounded),
                    fillColor: AppColors.surface,
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(30),
                      borderSide: const BorderSide(color: AppColors.border),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(30),
                      borderSide: const BorderSide(
                        color: AppColors.primary,
                        width: 1.4,
                      ),
                    ),
                  ),
                  onChanged: (value) => setState(() => search = value),
                ),
              ),
            ),
            const SizedBox(height: 12),
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 18),
              child: Row(
                children: [
                  _ResourceFilterChip(
                    label: 'Semua',
                    selected: type == null,
                    onSelected: () => setState(() => type = null),
                  ),
                  const SizedBox(width: 8),
                  _ResourceFilterChip(
                    label: 'Ruangan',
                    selected: type == 'room',
                    onSelected: () => setState(() => type = 'room'),
                  ),
                  const SizedBox(width: 8),
                  _ResourceFilterChip(
                    label: 'Aset',
                    selected: type == 'asset',
                    onSelected: () => setState(() => type = 'asset'),
                  ),
                ],
              ),
            ),
            Expanded(
              child: Column(
                children: [
                  faceStatus.maybeWhen(
                    data: (status) {
                      final needsFace =
                          status['enabled'] == true &&
                          status['enrolled'] != true;
                      if (!needsFace) return const SizedBox.shrink();
                      return _FaceEnrollmentBanner(
                        onEnroll: () => openFaceEnrollment(context, ref),
                      );
                    },
                    orElse: () => const SizedBox.shrink(),
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
                          : RefreshIndicator(
                              onRefresh: () => ref.refresh(
                                resourceListProvider(
                                  ResourceFilter(search: search, type: type),
                                ).future,
                              ),
                              child: ListView.separated(
                                padding: const EdgeInsets.fromLTRB(
                                  18,
                                  14,
                                  18,
                                  24,
                                ),
                                itemCount: items.length,
                                separatorBuilder: (_, _) =>
                                    const SizedBox(height: 10),
                                itemBuilder: (_, index) => _ResourceCard(
                                  item: items[index],
                                  onTap: () => _openResource(items[index]),
                                ),
                              ),
                            ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: AppBottomNav(
        currentIndex: 0,
        showApprovals: showApprovals,
      ),
    );
  }

  Future<void> _openResource(Map<String, dynamic> item) async {
    final id = item['id']?.toString();
    if (id == null) return;
    if (item['type'] == 'room') {
      await openResourceBooking(context, ref, id);
    } else {
      context.push('/bookings/new/$id');
    }
  }
}

class _ResourceFilterChip extends StatelessWidget {
  const _ResourceFilterChip({
    required this.label,
    required this.selected,
    required this.onSelected,
  });

  final String label;
  final bool selected;
  final VoidCallback onSelected;

  @override
  Widget build(BuildContext context) {
    return FilterChip(
      label: Text(label),
      selected: selected,
      showCheckmark: false,
      onSelected: (_) => onSelected(),
      avatar: selected
          ? const Icon(Icons.check_rounded, size: 15, color: Colors.white)
          : null,
      backgroundColor: AppColors.surface,
      selectedColor: AppColors.primary,
      labelStyle: TextStyle(
        color: selected ? Colors.white : AppColors.text,
        fontWeight: FontWeight.w700,
        fontSize: 12,
      ),
      side: BorderSide(color: selected ? AppColors.primary : AppColors.border),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(30)),
    );
  }
}

class _ResourceCard extends StatelessWidget {
  const _ResourceCard({required this.item, required this.onTap});

  final Map<String, dynamic> item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final isRoom = item['type'] == 'room';
    final location = item['location'];
    final locationName = location is Map
        ? location['name']?.toString()
        : location?.toString();
    final detail = item['room'];
    final capacity = detail is Map ? detail['capacity'] : item['capacity'];
    final subtitle = [
      isRoom ? 'Ruangan' : 'Aset',
      if (locationName != null && locationName.isNotEmpty) locationName,
      if (capacity != null) 'Kapasitas $capacity',
    ].join(' • ');

    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: AppColors.border),
          ),
          child: Row(
            children: [
              AppIconBox(
                icon: isRoom
                    ? Icons.meeting_room_outlined
                    : Icons.inventory_2_outlined,
                size: 46,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item['name']?.toString() ?? '-',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 3),
                    Text(
                      subtitle,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              const Icon(Icons.chevron_right_rounded, color: AppColors.muted),
            ],
          ),
        ),
      ),
    );
  }
}

class _FaceEnrollmentBanner extends StatelessWidget {
  const _FaceEnrollmentBanner({required this.onEnroll});

  final VoidCallback onEnroll;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(18, 14, 18, 0),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColors.primary,
          borderRadius: BorderRadius.circular(18),
        ),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.14),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Icon(
                Icons.face_retouching_natural,
                color: Colors.white,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Daftarkan wajah dulu',
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    'Wajah wajib terdaftar sebelum booking ruangan.',
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.78),
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ),
            TextButton(
              onPressed: onEnroll,
              style: TextButton.styleFrom(
                foregroundColor: AppColors.primaryDark,
                backgroundColor: Colors.white,
              ),
              child: const Text('Daftar'),
            ),
          ],
        ),
      ),
    );
  }
}
