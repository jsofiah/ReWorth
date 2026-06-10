import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../utils/app_colors.dart';
import '../utils/app_constants.dart';
import '../utils/app_text_styles.dart';
import '../widgets/app_text_field.dart';
import '../widgets/app_primary_button.dart';

class ForgotPasswordPage extends StatefulWidget {
  const ForgotPasswordPage({super.key});

  @override
  State<ForgotPasswordPage> createState() => _ForgotPasswordPageState();
}

class _ForgotPasswordPageState extends State<ForgotPasswordPage> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  bool _isLoading = false;
  bool _isEmailSent = false;

  @override
  void dispose() {
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _onResetPassword() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isLoading = true);

    try {
      // Kirim email dengan redirect ke deep link app
      await Supabase.instance.client.auth.resetPasswordForEmail(
        _emailController.text.trim(),
        redirectTo: 'reworth://reset-password', // ← Sesuai dengan yang di dashboard
      );

      if (!mounted) return;

      setState(() {
        _isEmailSent = true;
        _isLoading = false;
      });

    } catch (e) {
      // ... error handling
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: AppColors.secondary),
          onPressed: () => Navigator.pop(context),
        ),
        title: Text(
          'Lupa Password',
          style: AppTextStyles.title.copyWith(color: AppColors.secondary),
        ),
        centerTitle: true,
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppConstants.paddingL),
          child: _isEmailSent
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
        children: [
          const SizedBox(height: 20),
          Text(
            'Reset Password',
            style: AppTextStyles.heading1,
          ),
          const SizedBox(height: 8),
          Text(
            'Masukkan email yang terdaftar, kami akan mengirimkan link untuk mereset password Anda.',
            style: AppTextStyles.bodyMedium,
          ),
          const SizedBox(height: 32),
          Text('Alamat email', style: AppTextStyles.label),
          const SizedBox(height: AppConstants.paddingS),
          AppTextField(
            hint: 'Email Anda',
            prefixIcon: Icons.mail_outline_rounded,
            controller: _emailController,
            keyboardType: TextInputType.emailAddress,
            validator: (val) {
              if (val == null || val.isEmpty) {
                return 'Email tidak boleh kosong';
              }
              if (!val.contains('@')) {
                return 'Format email tidak valid';
              }
              return null;
            },
          ),
          const SizedBox(height: 32),
          AppPrimaryButton(
            label: 'Kirim Link Reset',
            onPressed: _onResetPassword,
            isLoading: _isLoading,
          ),
          const SizedBox(height: 16),
          Center(
            child: TextButton(
              onPressed: () => Navigator.pop(context),
              child: Text(
                'Kembali ke Login',
                style: AppTextStyles.body.copyWith(
                  color: AppColors.secondary,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
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
          Icons.email_outlined, // Ganti icon biar lebih bagus
          size: 80,
          color: AppColors.secondary,
        ),
        const SizedBox(height: 24),
        Text(
          'Cek Email Anda',
          style: AppTextStyles.heading1,
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 12),
        Text(
          'Kami telah mengirimkan link reset password ke:',
          style: AppTextStyles.bodyMedium,
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 8),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          decoration: BoxDecoration(
            color: AppColors.primary.withOpacity(0.2),
            borderRadius: BorderRadius.circular(AppConstants.radiusM),
          ),
          child: Text(
            _emailController.text,
            style: AppTextStyles.body.copyWith(
              fontWeight: FontWeight.w600,
              color: AppColors.secondary,
            ),
            textAlign: TextAlign.center,
          ),
        ),
        const SizedBox(height: 16),
        Text(
          'Klik link di email untuk mengatur ulang password Anda.',
          style: AppTextStyles.caption,
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 32),
        AppPrimaryButton(
          label: 'Kembali ke Login',
          onPressed: () => Navigator.pop(context),
        ),
      ],
    );
  }
}