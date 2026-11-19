import 'package:flutter/material.dart';

import '../../../core/formatting/formatters.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_chrome.dart';
import '../../../core/widgets/app_components.dart';

class ApprovalDecisionPage extends StatefulWidget {
  const ApprovalDecisionPage({required this.approval, super.key});

  final Map<String, dynamic> approval;

  @override
  State<ApprovalDecisionPage> createState() => _ApprovalDecisionPageState();
}

class _ApprovalDecisionPageState extends State<ApprovalDecisionPage> {
  String notes = '';

  @override
  Widget build(BuildContext context) {
    final booking = widget.approval['booking'] as Map?;
    final resource = booking?['resource'] as Map?;
    final user = booking?['user'] as Map?;
    final requester =
        user?['name']?.toString() ?? user?['email']?.toString() ?? 'Pengguna';

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            AppDetailHeader(
              title: 'Keputusan Persetujuan',
              onBack: () => Navigator.pop(context),
            ),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(18, 18, 18, 24),
                children: [
                  Row(
                    children: [
                      const AppIconBox(
                        icon: Icons.fact_check_rounded,
                        backgroundColor: AppColors.mintSoft,
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          'KEPUTUSAN PERSETUJUAN',
                          style: Theme.of(context).textTheme.labelSmall
                              ?.copyWith(
                                color: AppColors.text,
                                letterSpacing: 0.6,
                              ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 18),
                  _DetailRow(
                    label: 'Ruangan',
                    value: resource?['name']?.toString() ?? '-',
                  ),
                  _DetailRow(label: 'Pemohon', value: requester),
                  _DetailRow(
                    label: 'Waktu',
                    value: formatWibDateTime(booking?['starts_at']),
                  ),
                  const SizedBox(height: 18),
                  TextField(
                    maxLength: 1000,
                    maxLines: 4,
                    onChanged: (value) => notes = value,
                    decoration: const InputDecoration(
                      labelText: 'Catatan (opsional)',
                      hintText: 'Tambahkan catatan keputusan',
                      alignLabelWithHint: true,
                    ),
                  ),
                  const SizedBox(height: 28),
                  FilledButton(
                    onPressed: () => Navigator.pop(context, (true, notes)),
                    child: const Text('Setujui'),
                  ),
                  const SizedBox(height: 12),
                  OutlinedButton(
                    onPressed: () => Navigator.pop(context, (false, notes)),
                    child: const Text('Tolak'),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 72,
            child: Text(label, style: Theme.of(context).textTheme.bodySmall),
          ),
          Expanded(
            child: Text(value, style: Theme.of(context).textTheme.bodyMedium),
          ),
        ],
      ),
    );
  }
}
