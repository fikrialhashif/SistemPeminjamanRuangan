import 'package:flutter/material.dart';

enum VerificationStep { location, qr, face, time }

extension VerificationStepLabel on VerificationStep {
  String get label => switch (this) {
    VerificationStep.location => 'Lokasi',
    VerificationStep.qr => 'QR',
    VerificationStep.face => 'Wajah',
    VerificationStep.time => 'Waktu',
  };
}

const List<VerificationStep> verificationSteps = [
  VerificationStep.location,
  VerificationStep.qr,
  VerificationStep.face,
  VerificationStep.time,
];

class VerificationStepHeader extends StatelessWidget {
  const VerificationStepHeader({required this.current, super.key});

  final int current;

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;
    return Row(
      children: [
        for (var index = 0; index < verificationSteps.length; index++) ...[
          Expanded(
            child: Column(
              children: [
                CircleAvatar(
                  radius: 16,
                  backgroundColor: index <= current
                      ? colorScheme.primary
                      : colorScheme.surfaceContainerHighest,
                  child: index < current
                      ? Icon(
                          Icons.check,
                          size: 18,
                          color: colorScheme.onPrimary,
                        )
                      : Text(
                          '${index + 1}',
                          style: TextStyle(
                            color: index <= current
                                ? colorScheme.onPrimary
                                : colorScheme.onSurfaceVariant,
                          ),
                        ),
                ),
                const SizedBox(height: 6),
                Text(
                  verificationSteps[index].label,
                  style: Theme.of(context).textTheme.labelMedium,
                ),
              ],
            ),
          ),
          if (index < verificationSteps.length - 1)
            Container(
              height: 2,
              width: 16,
              color: index < current
                  ? colorScheme.primary
                  : colorScheme.surfaceContainerHighest,
            ),
        ],
      ],
    );
  }
}
