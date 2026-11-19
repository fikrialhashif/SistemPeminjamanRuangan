import 'package:flutter/material.dart';

import '../formatting/formatters.dart';
import '../theme/app_theme.dart';

class AppPill extends StatelessWidget {
  const AppPill({
    required this.label,
    this.icon,
    this.backgroundColor = AppColors.mintSoft,
    this.foregroundColor = AppColors.primary,
    super.key,
  });

  final String label;
  final IconData? icon;
  final Color backgroundColor;
  final Color foregroundColor;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(30),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 13, color: foregroundColor),
            const SizedBox(width: 4),
          ],
          Text(
            label,
            style: TextStyle(
              color: foregroundColor,
              fontSize: 11,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class StatusPill extends StatelessWidget {
  const StatusPill({required this.status, super.key});

  final Object? status;

  @override
  Widget build(BuildContext context) {
    final value = status?.toString();
    final (background, foreground) = switch (value) {
      'approved' => (AppColors.mintStrong, AppColors.primary),
      'checked_in' => (AppColors.warningSoft, AppColors.warning),
      'pending' => (const Color(0xFFF1F5F3), AppColors.muted),
      'rejected' || 'cancelled' => (AppColors.dangerSoft, AppColors.danger),
      'completed' => (AppColors.mintStrong, AppColors.success),
      _ => (const Color(0xFFF1F5F3), AppColors.muted),
    };
    return AppPill(
      label: statusLabel(status),
      backgroundColor: background,
      foregroundColor: foreground,
    );
  }
}

class AppSectionCard extends StatelessWidget {
  const AppSectionCard({
    required this.child,
    this.padding = const EdgeInsets.all(16),
    super.key,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: padding,
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.border),
      ),
      child: child,
    );
  }
}

class AppIconBox extends StatelessWidget {
  const AppIconBox({
    required this.icon,
    this.size = 44,
    this.backgroundColor = AppColors.mintSoft,
    this.foregroundColor = AppColors.primary,
    super.key,
  });

  final IconData icon;
  final double size;
  final Color backgroundColor;
  final Color foregroundColor;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(size * 0.28),
      ),
      child: Icon(icon, color: foregroundColor, size: size * 0.48),
    );
  }
}

class AppRoundAction extends StatelessWidget {
  const AppRoundAction({
    required this.icon,
    required this.tooltip,
    required this.onPressed,
    this.foregroundColor = AppColors.primary,
    this.backgroundColor = AppColors.mintSoft,
    super.key,
  });

  final IconData icon;
  final String tooltip;
  final VoidCallback? onPressed;
  final Color foregroundColor;
  final Color backgroundColor;

  @override
  Widget build(BuildContext context) {
    return IconButton(
      tooltip: tooltip,
      onPressed: onPressed,
      style: IconButton.styleFrom(
        backgroundColor: backgroundColor,
        foregroundColor: foregroundColor,
        minimumSize: const Size(38, 38),
        maximumSize: const Size(38, 38),
        padding: EdgeInsets.zero,
      ),
      icon: Icon(icon, size: 18),
    );
  }
}
