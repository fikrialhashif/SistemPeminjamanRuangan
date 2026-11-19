import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/formatting/formatters.dart';
import '../../../core/network/api_error.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_chrome.dart';
import '../../../core/widgets/app_components.dart';
import '../../../core/widgets/status_views.dart';
import '../../../core/widgets/user_quick_access_sheet.dart';
import '../../auth/presentation/auth_controller.dart';
import '../data/approval_repository.dart';
import 'approval_providers.dart';

class ApprovalsPage extends ConsumerWidget {
  const ApprovalsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final approvals = ref.watch(pendingApprovalsProvider);
    final showApprovals = ref.watch(approvalAccessProvider);
    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            AppBrandHeader(
              section: 'Persetujuan',
              onAvatarTap: () => showUserQuickAccessSheet(context),
            ),
            Expanded(
              child: approvals.when(
                loading: () => const Center(
                  child: CircularProgressIndicator(
                    semanticsLabel: 'Memuat persetujuan',
                  ),
                ),
                error: (error, _) => ErrorView(
                  error: error,
                  onRetry: () => ref.invalidate(pendingApprovalsProvider),
                ),
                data: (items) => RefreshIndicator(
                  onRefresh: () => ref.refresh(pendingApprovalsProvider.future),
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(18, 14, 18, 24),
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              'Pengajuan menunggu',
                              style: Theme.of(context).textTheme.headlineSmall,
                            ),
                          ),
                          AppPill(
                            label: '${items.length} Menunggu',
                            backgroundColor: AppColors.warningSoft,
                            foregroundColor: AppColors.warning,
                          ),
                        ],
                      ),
                      const SizedBox(height: 14),
                      if (items.isEmpty)
                        const SizedBox(
                          height: 320,
                          child: EmptyView(
                            message: 'Tidak ada permintaan pending.',
                            icon: Icons.fact_check_outlined,
                          ),
                        )
                      else
                        for (final item in items) ...[
                          _ApprovalTile(approval: item),
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
        currentIndex: 3,
        showApprovals: showApprovals,
      ),
    );
  }
}

class _ApprovalTile extends ConsumerWidget {
  const _ApprovalTile({required this.approval});

  final Map<String, dynamic> approval;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final booking = approval['booking'] as Map?;
    final resource = booking?['resource'] as Map?;
    final user = booking?['user'] as Map?;
    final requester =
        user?['name']?.toString() ?? user?['email']?.toString() ?? 'Pengguna';
    final time = formatWibDateTime(booking?['starts_at']);

    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: () => _showDecision(context, ref),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: AppColors.border),
          ),
          child: Row(
            children: [
              const AppIconBox(
                icon: Icons.description_outlined,
                size: 46,
                backgroundColor: AppColors.warningSoft,
                foregroundColor: AppColors.warning,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      resource?['name']?.toString() ?? 'Booking',
                      style: Theme.of(context).textTheme.titleMedium,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '$requester • $time',
                      style: Theme.of(context).textTheme.bodySmall,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 8),
                    const AppPill(
                      label: 'Menunggu keputusan',
                      icon: Icons.schedule_rounded,
                      backgroundColor: AppColors.warningSoft,
                      foregroundColor: AppColors.warning,
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

  Future<void> _showDecision(BuildContext context, WidgetRef ref) async {
    final id = approval['id']?.toString();
    if (id == null) return;

    final result = await context.push<(bool, String)>(
      '/approvals/$id',
      extra: approval,
    );
    if (result == null || !context.mounted) return;

    final (approve, notes) = result;
    try {
      await ref
          .read(approvalRepositoryProvider)
          .decide(id: id, approve: approve, notes: notes);
      ref.invalidate(pendingApprovalsProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              approve ? 'Pengajuan disetujui.' : 'Pengajuan ditolak.',
            ),
          ),
        );
      }
    } catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(apiErrorMessage(error))));
      }
    }
  }
}
