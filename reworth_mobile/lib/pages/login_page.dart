import 'package:flutter/material.dart';
import 'package:reworth_mobile/pages/home_page.dart';
import 'package:reworth_mobile/pages/register_page.dart';
import '../utils/app_colors.dart';
import '../utils/app_constants.dart';
import '../utils/app_text_styles.dart';
import '../widgets/app_text_field.dart';
import '../widgets/app_primary_button.dart';
import '../services/auth_service.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();

  bool _rememberMe = false;
  bool _isLoading = false;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _onLogin() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isLoading = true);

    try {
      // PANGGIL AUTH SERVICE LOGIN
      await AuthService.login(
        email: _emailController.text,
        password: _passwordController.text,
      );

      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Login berhasil")),
      );

      // Pindah ke homepage
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (_) => const HomePage()),
      );
      
    } catch (e) {
      if (!mounted) return;
      
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text("Error: ${e.toString().replaceAll('Exception:', '')}")),
      );
    }

    if (mounted) setState(() => _isLoading = false);
  }

  void _onForgotPassword() {
    // TODO: Navigate to forgot password page
  }

  void _onDaftar() {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const RegisterPage()),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      body: Stack(
        children: [
          Positioned.fill(
            child: Transform(
              alignment: Alignment.center,
              transform: Matrix4.identity()..scale(1.0, -1.0),
              child: Container(
                decoration: const BoxDecoration(
                  image: DecorationImage(
                    image: AssetImage('assets/gradient.png'),
                    fit: BoxFit.cover,
                  ),
                ),
              ),
            ),
          ),
          
          SafeArea(
            child: Column(
              children: [
                _buildHeader(),
                Expanded(child: _buildFormCard()),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHeader() {
    return Padding(
      padding: const EdgeInsets.all(AppConstants.paddingL),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          SizedBox(
            width: 52,
            height: 52,
            child: Image.asset(
              'assets/logo.png',
              fit: BoxFit.contain,
              errorBuilder: (_, __, ___) => const _PlaceholderIcon(),
            ),
          ),
          const SizedBox(width: 12),
          Text(
            'ReWorth',
            style: AppTextStyles.headline.copyWith(
              fontSize: 30,
            )
          )
        ],
      ),
    );
  }

  Widget _buildFormCard() {
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.only(
          topLeft: Radius.circular(AppConstants.radiusXL),
          topRight: Radius.circular(AppConstants.radiusXL),
        ),
      ),
      child: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(
          horizontal: AppConstants.paddingL,
          vertical: AppConstants.paddingXL,
        ),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Column(
                  children: [
                    Text(
                      'Selamat datang kembali',
                      style: AppTextStyles.heading1,
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: AppConstants.paddingS),
                    Text(
                      'Masukkan email dan kata sandi\nAnda untuk mengakses akun.',
                      style: AppTextStyles.bodyMedium,
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 32),

              Text('Alamat email', style: AppTextStyles.label),
              const SizedBox(height: AppConstants.paddingS),
              AppTextField(
                hint: 'Alamat email',
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

              const SizedBox(height: AppConstants.paddingM),

              Text('Password', style: AppTextStyles.label),
              const SizedBox(height: AppConstants.paddingS),
              AppTextField(
                hint: 'Password',
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

              Row(
                children: [
                  GestureDetector(
                    onTap: () => setState(() => _rememberMe = !_rememberMe),
                    child: Row(
                      children: [
                        _CustomCheckbox(
                          value: _rememberMe,
                          onChanged: (v) =>
                              setState(() => _rememberMe = v ?? false),
                        ),
                        const SizedBox(width: AppConstants.paddingS),
                        Text('Remember me', style: AppTextStyles.rememberMe),
                      ],
                    ),
                  ),
                  const Spacer(),
                  GestureDetector(
                    onTap: _onForgotPassword,
                    child: Text(
                      'Forgot password?',
                      style: AppTextStyles.forgotPassword,
                    ),
                  ),
                ],
              ),

              const SizedBox(height: AppConstants.paddingXL),

              AppPrimaryButton(
                label: 'Log in',
                onPressed: _onLogin,
                isLoading: _isLoading,
              ),

              const SizedBox(height: AppConstants.paddingL),

              const Divider(color: AppColors.divider, thickness: 1),

              const SizedBox(height: AppConstants.paddingM),

              Center(
                child: GestureDetector(
                  onTap: _onDaftar,
                  child: RichText(
                    text: TextSpan(
                      children: [
                        TextSpan(
                          text: 'Belum punya akun? ',
                          style: AppTextStyles.caption,
                        ),
                        TextSpan(
                          text: 'Daftar',
                          style: AppTextStyles.captionBold,
                        ),
                      ],
                    ),
                  ),
                ),
              ),

              const SizedBox(height: AppConstants.paddingM),
            ],
          ),
        ),
      ),
    );
  }
}

class _CustomCheckbox extends StatelessWidget {
  final bool value;
  final ValueChanged<bool?> onChanged;

  const _CustomCheckbox({required this.value, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => onChanged(!value),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        width: 20,
        height: 20,
        decoration: BoxDecoration(
          color: value ? AppColors.secondary : AppColors.white,
          border: Border.all(
            color: value ? AppColors.secondary : AppColors.checkboxBorder,
            width: 1.5,
          ),
          borderRadius: BorderRadius.circular(AppConstants.radiusS / 2),
        ),
        child: value
            ? const Icon(Icons.check, size: 13, color: AppColors.white)
            : null,
      ),
    );
  }
}

class _PlaceholderIcon extends StatelessWidget {
  const _PlaceholderIcon();

  @override
  Widget build(BuildContext context) {
    return CustomPaint(
      size: const Size(52, 52),
      painter: _LeafPainter(),
    );
  }
}

class _LeafPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0xFF4E7A1A)
      ..style = PaintingStyle.fill;

    final w = size.width;
    final h = size.height;

    final leftPath = Path()
      ..moveTo(w * 0.5, h * 0.55)
      ..cubicTo(w * 0.1, h * 0.55, w * 0.05, h * 0.1, w * 0.45, h * 0.08)
      ..cubicTo(w * 0.5, h * 0.08, w * 0.5, h * 0.3, w * 0.5, h * 0.55)
      ..close();

    final rightPath = Path()
      ..moveTo(w * 0.5, h * 0.55)
      ..cubicTo(w * 0.9, h * 0.55, w * 0.95, h * 0.1, w * 0.55, h * 0.08)
      ..cubicTo(w * 0.5, h * 0.08, w * 0.5, h * 0.3, w * 0.5, h * 0.55)
      ..close();

    final stemPath = Path()
      ..moveTo(w * 0.5, h * 0.55)
      ..lineTo(w * 0.35, h * 0.9)
      ..lineTo(w * 0.42, h * 0.9)
      ..lineTo(w * 0.5, h * 0.65)
      ..lineTo(w * 0.58, h * 0.9)
      ..lineTo(w * 0.65, h * 0.9)
      ..lineTo(w * 0.5, h * 0.55)
      ..close();

    canvas.drawPath(leftPath, paint..color = const Color(0xFF6B9E2A));
    canvas.drawPath(rightPath, paint..color = const Color(0xFF4E7A1A));
    canvas.drawPath(stemPath, paint..color = const Color(0xFF3A6010));
  }

  @override
  bool shouldRepaint(_) => false;
}