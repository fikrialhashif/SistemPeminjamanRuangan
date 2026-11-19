import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_error.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_chrome.dart';
import '../../../core/widgets/app_components.dart';
import '../../../core/widgets/status_views.dart';
import '../../../core/widgets/user_quick_access_sheet.dart';
import '../../auth/presentation/auth_controller.dart';

class ProfilePage extends ConsumerStatefulWidget {
  const ProfilePage({super.key});

  @override
  ConsumerState<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends ConsumerState<ProfilePage> {
  final _nameFormKey = GlobalKey<FormState>();
  final _passwordFormKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _currentPasswordController = TextEditingController();
  final _newPasswordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();

  bool _savingName = false;
  bool _savingPassword = false;
  bool _obscureCurrent = true;
  bool _obscureNew = true;
  bool _obscureConfirm = true;

  @override
  void initState() {
    super.initState();
    final user = ref.read(authControllerProvider).value?['user'];
    if (user is Map) {
      _nameController.text = user['name']?.toString() ?? '';
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _currentPasswordController.dispose();
    _newPasswordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authControllerProvider).value?['user'];
    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            AppDetailHeader(
              eyebrow: 'AKUN PENGGUNA',
              title: 'Profil saya',
              onBack: () {
                if (context.canPop()) {
                  context.pop();
                } else {
                  context.go('/resources');
                }
              },
              onAvatarTap: () => showUserQuickAccessSheet(context),
            ),
            Expanded(
              child: user is Map
                  ? _ProfileBody(
                      user: Map<String, dynamic>.from(user),
                      nameFormKey: _nameFormKey,
                      passwordFormKey: _passwordFormKey,
                      nameController: _nameController,
                      currentPasswordController: _currentPasswordController,
                      newPasswordController: _newPasswordController,
                      confirmPasswordController: _confirmPasswordController,
                      savingName: _savingName,
                      savingPassword: _savingPassword,
                      obscureCurrent: _obscureCurrent,
                      obscureNew: _obscureNew,
                      obscureConfirm: _obscureConfirm,
                      onToggleCurrent: () =>
                          setState(() => _obscureCurrent = !_obscureCurrent),
                      onToggleNew: () =>
                          setState(() => _obscureNew = !_obscureNew),
                      onToggleConfirm: () =>
                          setState(() => _obscureConfirm = !_obscureConfirm),
                      onSaveName: _saveName,
                      onSavePassword: _savePassword,
                    )
                  : const EmptyView(
                      message:
                          'Data pengguna tidak tersedia. Silakan masuk ulang.',
                      icon: Icons.person_off_outlined,
                    ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _saveName() async {
    if (!_nameFormKey.currentState!.validate()) return;
    setState(() => _savingName = true);
    try {
      final user = await ref
          .read(authControllerProvider.notifier)
          .updateProfile(_nameController.text.trim());
      final name = user['name']?.toString();
      if (name != null) _nameController.text = name;
      _showMessage('Nama berhasil diperbarui.');
    } catch (error) {
      _showMessage(apiErrorMessage(error), isError: true);
    } finally {
      if (mounted) setState(() => _savingName = false);
    }
  }

  Future<void> _savePassword() async {
    if (!_passwordFormKey.currentState!.validate()) return;
    setState(() => _savingPassword = true);
    try {
      await ref
          .read(authControllerProvider.notifier)
          .updatePassword(
            currentPassword: _currentPasswordController.text,
            newPassword: _newPasswordController.text,
            confirmation: _confirmPasswordController.text,
          );
      _currentPasswordController.clear();
      _newPasswordController.clear();
      _confirmPasswordController.clear();
      _showMessage('Password berhasil diperbarui.');
    } catch (error) {
      _showMessage(apiErrorMessage(error), isError: true);
    } finally {
      if (mounted) setState(() => _savingPassword = false);
    }
  }

  void _showMessage(String message, {bool isError = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? AppColors.danger : AppColors.primaryDark,
      ),
    );
  }
}

class _ProfileBody extends StatelessWidget {
  const _ProfileBody({
    required this.user,
    required this.nameFormKey,
    required this.passwordFormKey,
    required this.nameController,
    required this.currentPasswordController,
    required this.newPasswordController,
    required this.confirmPasswordController,
    required this.savingName,
    required this.savingPassword,
    required this.obscureCurrent,
    required this.obscureNew,
    required this.obscureConfirm,
    required this.onToggleCurrent,
    required this.onToggleNew,
    required this.onToggleConfirm,
    required this.onSaveName,
    required this.onSavePassword,
  });

  final Map<String, dynamic> user;
  final GlobalKey<FormState> nameFormKey;
  final GlobalKey<FormState> passwordFormKey;
  final TextEditingController nameController;
  final TextEditingController currentPasswordController;
  final TextEditingController newPasswordController;
  final TextEditingController confirmPasswordController;
  final bool savingName;
  final bool savingPassword;
  final bool obscureCurrent;
  final bool obscureNew;
  final bool obscureConfirm;
  final VoidCallback onToggleCurrent;
  final VoidCallback onToggleNew;
  final VoidCallback onToggleConfirm;
  final VoidCallback onSaveName;
  final VoidCallback onSavePassword;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(18, 8, 18, 28),
      children: [
        _ProfileSummary(user: user),
        const SizedBox(height: 18),
        Text('Data akun', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        AppSectionCard(
          child: Form(
            key: nameFormKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const _FieldLabel('NAMA LENGKAP'),
                const SizedBox(height: 8),
                TextFormField(
                  controller: nameController,
                  textInputAction: TextInputAction.done,
                  autofillHints: const [AutofillHints.name],
                  onFieldSubmitted: (_) => savingName ? null : onSaveName(),
                  decoration: const InputDecoration(
                    hintText: 'Masukkan nama lengkap',
                    prefixIcon: Icon(Icons.badge_outlined),
                  ),
                  validator: (value) {
                    final name = value?.trim() ?? '';
                    if (name.isEmpty) return 'Nama wajib diisi';
                    if (name.length > 255) {
                      return 'Nama maksimal 255 karakter';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 14),
                FilledButton(
                  onPressed: savingName ? null : onSaveName,
                  child: savingName
                      ? const SizedBox.square(
                          dimension: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Text('Simpan nama'),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 18),
        Text('Keamanan', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        AppSectionCard(
          child: Form(
            key: passwordFormKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const _FieldLabel('PASSWORD SAAT INI'),
                const SizedBox(height: 8),
                TextFormField(
                  controller: currentPasswordController,
                  obscureText: obscureCurrent,
                  textInputAction: TextInputAction.next,
                  autofillHints: const [AutofillHints.password],
                  decoration: InputDecoration(
                    hintText: 'Masukkan password saat ini',
                    prefixIcon: const Icon(Icons.lock_outline_rounded),
                    suffixIcon: IconButton(
                      tooltip: obscureCurrent
                          ? 'Tampilkan password'
                          : 'Sembunyikan password',
                      onPressed: onToggleCurrent,
                      icon: Icon(
                        obscureCurrent
                            ? Icons.visibility_outlined
                            : Icons.visibility_off_outlined,
                      ),
                    ),
                  ),
                  validator: (value) => value == null || value.isEmpty
                      ? 'Password saat ini wajib diisi'
                      : null,
                ),
                const SizedBox(height: 14),
                const _FieldLabel('PASSWORD BARU'),
                const SizedBox(height: 8),
                TextFormField(
                  controller: newPasswordController,
                  obscureText: obscureNew,
                  textInputAction: TextInputAction.next,
                  autofillHints: const [AutofillHints.newPassword],
                  decoration: InputDecoration(
                    hintText: 'Minimal 8 karakter',
                    prefixIcon: const Icon(Icons.password_rounded),
                    suffixIcon: IconButton(
                      tooltip: obscureNew
                          ? 'Tampilkan password'
                          : 'Sembunyikan password',
                      onPressed: onToggleNew,
                      icon: Icon(
                        obscureNew
                            ? Icons.visibility_outlined
                            : Icons.visibility_off_outlined,
                      ),
                    ),
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Password baru wajib diisi';
                    }
                    if (value.length < 8) {
                      return 'Password minimal 8 karakter';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 14),
                const _FieldLabel('KONFIRMASI PASSWORD BARU'),
                const SizedBox(height: 8),
                TextFormField(
                  controller: confirmPasswordController,
                  obscureText: obscureConfirm,
                  textInputAction: TextInputAction.done,
                  autofillHints: const [AutofillHints.newPassword],
                  onFieldSubmitted: (_) =>
                      savingPassword ? null : onSavePassword(),
                  decoration: InputDecoration(
                    hintText: 'Ulangi password baru',
                    prefixIcon: const Icon(Icons.lock_reset_rounded),
                    suffixIcon: IconButton(
                      tooltip: obscureConfirm
                          ? 'Tampilkan password'
                          : 'Sembunyikan password',
                      onPressed: onToggleConfirm,
                      icon: Icon(
                        obscureConfirm
                            ? Icons.visibility_outlined
                            : Icons.visibility_off_outlined,
                      ),
                    ),
                  ),
                  validator: (value) => value == newPasswordController.text
                      ? null
                      : 'Konfirmasi password tidak cocok',
                ),
                const SizedBox(height: 14),
                FilledButton(
                  onPressed: savingPassword ? null : onSavePassword,
                  child: savingPassword
                      ? const SizedBox.square(
                          dimension: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Text('Perbarui password'),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: AppColors.mintStrong,
            borderRadius: BorderRadius.circular(14),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Icon(
                Icons.shield_outlined,
                size: 18,
                color: AppColors.primary,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Gunakan password yang kuat dan unik. Setelah password diubah, sesi lain pada akun ini akan diakhiri.',
                  style: Theme.of(context).textTheme.bodySmall
                      ?.copyWith(color: AppColors.primaryDark),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _ProfileSummary extends StatelessWidget {
  const _ProfileSummary({required this.user});

  final Map<String, dynamic> user;

  @override
  Widget build(BuildContext context) {
    final name = user['name']?.toString() ?? '-';
    final email = user['email']?.toString();
    final department = user['department'];
    final departmentName = department is Map
        ? department['name']?.toString()
        : department?.toString();
    final roles = user['roles'];

    return AppSectionCard(
      padding: const EdgeInsets.all(16),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 64,
            height: 64,
            decoration: const BoxDecoration(
              color: AppColors.primary,
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.person_rounded,
              color: Colors.white,
              size: 34,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                if (email != null && email.isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Text(email, style: Theme.of(context).textTheme.bodySmall),
                ],
                if (departmentName != null && departmentName.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  AppPill(label: departmentName, icon: Icons.apartment_rounded),
                ],
                if (roles is List && roles.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: [
                      for (final role in roles)
                        AppPill(
                          label: _roleLabel(role),
                          icon: Icons.verified_user_outlined,
                          backgroundColor: AppColors.warningSoft,
                          foregroundColor: AppColors.warning,
                        ),
                    ],
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _roleLabel(Object? role) {
    final value = role is Map ? role['name']?.toString() : role?.toString();
    if (value == null || value.isEmpty) return 'Anggota';
    return value
        .split('_')
        .map(
          (part) => part.isEmpty
              ? part
              : '${part[0].toUpperCase()}${part.substring(1)}',
        )
        .join(' ');
  }
}

class _FieldLabel extends StatelessWidget {
  const _FieldLabel(this.label);

  final String label;

  @override
  Widget build(BuildContext context) {
    return Text(
      label,
      style: Theme.of(context).textTheme.labelSmall
          ?.copyWith(color: AppColors.text, letterSpacing: 0.8),
    );
  }
}
