import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../utils/app_colors.dart';
import '../utils/app_constants.dart';
import '../utils/app_text_styles.dart';
import '../widgets/app_text_field.dart';
import '../widgets/app_primary_button.dart';

class ResetPasswordPage extends StatefulWidget {
  const ResetPasswordPage({super.key});

  @override
  State<ResetPasswordPage> createState() => _ResetPasswordPageState();
}

class _ResetPasswordPageState extends State<ResetPasswordPage> {
  final _formKey = GlobalKey<FormState>();
  final _passwordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();
  bool _isLoading = false;
  bool _isSuccess = false;

  @override
  void dispose() {
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  Future<void> _onUpdatePassword() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isLoading = true);

    try {
      final user = Supabase.instance.client.auth.currentUser;
      
      if (user == null) {
        throw Exception('User tidak ditemukan. Silakan coba lagi.');
      }

      await Supabase.instance.client.auth.updateUser(
        UserAttributes(password: _passwordController.text),
      );

      if (!mounted) return;

      setState(() {
        _isSuccess = true;
        _isLoading = false;
      });

    } catch (e) {
      if (!mounted) return;
      
      setState(() => _isLoading = false);
      
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Gagal: ${e.toString().replaceAll('Exception:', '')}'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppConstants.paddingL),
          child: _isSuccess
              ? _buildSuccessView()
              : _buildFormView(),
        ),
      ),
    );
  }

  Widget _buildFormView() {
    return Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(
            'Buat Password Baru',
            style: AppTextStyles.heading1,
          ),
          const SizedBox(height: 8),
          Text(
            'Masukkan password baru untuk akun Anda.',
            style: AppTextStyles.bodyMedium,
          ),
          const SizedBox(height: 32),
          Text('Password Baru', style: AppTextStyles.label),
          const SizedBox(height: AppConstants.paddingS),
          AppTextField(
            hint: 'Minimal 6 karakter',
            prefixIcon: Icons.lock_outline_rounded,
            isPassword: true,
            controller: _passwordController,
            validator: (val) {
              if (val == null || val.isEmpty) {
                return 'Password tidak boleh kosong';
              }
              if (val.length < 6) {
                return 'Password minimal 6 karakter';
              }
              return null;
            },
          ),
          const SizedBox(height: AppConstants.paddingM),
          Text('Konfirmasi Password', style: AppTextStyles.label),
          const SizedBox(height: AppConstants.paddingS),
          AppTextField(
            hint: 'Ulangi password baru',
            prefixIcon: Icons.lock_outline_rounded,
            isPassword: true,
            controller: _confirmPasswordController,
            validator: (val) {
              if (val == null || val.isEmpty) {
                return 'Konfirmasi password tidak boleh kosong';
              }
              if (val != _passwordController.text) {
                return 'Password tidak sama';
              }
              return null;
            },
          ),
          const SizedBox(height: 32),
          AppPrimaryButton(
            label: 'Update Password',
            onPressed: _onUpdatePassword,
            isLoading: _isLoading,
          ),
        ],
      ),
    );
  }

  Widget _buildSuccessView() {
    return Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Icon(
          Icons.check_circle_outline,
          size: 80,
          color: AppColors.secondary,
        ),
        const SizedBox(height: 24),
        Text(
          'Password Berhasil Diubah!',
          style: AppTextStyles.heading1,
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 12),
        Text(
          'Silakan login dengan password baru Anda.',
          style: AppTextStyles.bodyMedium,
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 32),
        AppPrimaryButton(
          label: 'Login Sekarang',
          onPressed: () {
            Navigator.pushNamedAndRemoveUntil(
              context,
              '/login',
              (route) => false,
            );
          },
        ),
      ],
    );
  }
}