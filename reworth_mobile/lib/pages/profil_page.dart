import 'package:flutter/material.dart';
import '../models/user_model.dart';
import '../services/auth_service.dart';
import '../utils/app_colors.dart';
import '../utils/app_text_styles.dart';
import '../utils/app_constants.dart';
import '../utils/app_image_helper.dart';
import '../pages/belanja_page.dart';
import '../pages/reward_page.dart';
import '../pages/aktivitas_page.dart';
import '../pages/notification_page.dart';
import '../pages/syaratketentuan_page.dart';
import '../pages/edit_page.dart';
import 'package:url_launcher/url_launcher.dart';

class ProfilPage extends StatefulWidget {
  const ProfilPage({super.key});

  @override
  State<ProfilPage> createState() => _ProfilPageState();
}

class _ProfilPageState extends State<ProfilPage> {
  UserModel? _user;
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadUser();
  }

  Future<void> _loadUser() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final userData = await AuthService.getCurrentUserData();
      if (userData != null) {
        setState(() {
          _user = UserModel.fromMap(userData);
        });
      } else {
        setState(() {
          _errorMessage = 'Data pengguna tidak ditemukan';
        });
      }
    } catch (error) {
      setState(() {
        _errorMessage = 'Gagal memuat data: $error';
      });
      debugPrint('Error loading user: $error');
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  Future<void> _handleLogout() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (BuildContext dialogContext) => AlertDialog(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppConstants.radiusL),
        ),
        title: Text('Konfirmasi Logout', style: AppTextStyles.title),
        content: Text(
          'Apakah kamu yakin ingin keluar dari akun ini?',
          style: AppTextStyles.body,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(
              'Batal',
              style: AppTextStyles.body.copyWith(color: AppColors.textSecondary),
            ),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.secondary,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(AppConstants.radiusXL),
              ),
            ),
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(
              'Logout',
              style: AppTextStyles.buttonLabel,
            ),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      await AuthService.logout();
      if (mounted) {
        Navigator.pushNamedAndRemoveUntil(context, '/login', (Route route) => false);
      }
    }
  }

  void _navigateTo(Widget page) {
    Navigator.push(context, MaterialPageRoute(builder: (BuildContext context) => page));
  }

  void _openWebToko() async {
    final Uri url = Uri.parse('https://reworth-penjual.netlify.app/');
    if (!await launchUrl(url, mode: LaunchMode.externalApplication)) {
      throw Exception('Tidak bisa membuka $url');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.white,
      body: Stack(
        children: [
          // Background Gradient with better effect
          Positioned(
            left: -1,
            top: 0,
            right: 0,
            child: Image.asset(
              'assets/gradient.png',
              width: 394,
              height: 140,
              fit: BoxFit.cover,
              errorBuilder: (context, error, stackTrace) => Container(
                width: 394,
                height: 140,
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [AppColors.primary, AppColors.secondary],
                  ),
                ),
              ),
            ),
          ),
          
          // Konten Utama
          SafeArea(
            child: _buildBody(),
          ),
        ],
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator(color: AppColors.primary));
    }

    if (_errorMessage != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 50, color: Colors.red),
            const SizedBox(height: 16),
            Text(
              _errorMessage!,
              style: AppTextStyles.body.copyWith(color: Colors.red),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: _loadUser,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.secondary,
              ),
              child: const Text('Coba Lagi'),
            ),
          ],
        ),
      );
    }

    if (_user == null) {
      return const Center(
        child: Text('Data pengguna tidak tersedia'),
      );
    }

    return SingleChildScrollView(
      child: Column(
        children: [
          const SizedBox(height: 20),
          Center(
            child: Text(
              'Profil Saya',
              style: AppTextStyles.namafitur
            ),
          ),
          
          const SizedBox(height: 20),
          
          // Kartu Profil with animation and better design
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 26),
            child: TweenAnimationBuilder(
              tween: Tween<double>(begin: 0, end: 1),
              duration: const Duration(milliseconds: 500),
              builder: (context, double value, child) {
                return Transform.scale(
                  scale: value,
                  child: child,
                );
              },
              child: Stack(
                clipBehavior: Clip.none,
                children: [
                  Container(
                    width: 340,
                    height: 105,
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: [
                          Colors.white,
                          const Color(0xFFF8F9FA),
                        ],
                      ),
                      boxShadow: [
                        BoxShadow(
                          color: AppColors.black.withValues(alpha: 0.15),
                          blurRadius: 15,
                          offset: const Offset(0, 6),
                        ),
                      ],
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(
                        color: Colors.white,
                        width: 1,
                      ),
                    ),
                  ),
                  
                  // Avatar with glow effect
                  Positioned(
                    left: -13,
                    top: -37,
                    child: Container(
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                            color: AppColors.secondary.withOpacity(0.4),
                            blurRadius: 20,
                            spreadRadius: 3,
                          ),
                        ],
                      ),
                      child: Container(
                        width: 100,
                        height: 100,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          border: Border.all(color: const Color.fromARGB(255, 255, 255, 255), width: 3),
                          boxShadow: [
                            BoxShadow(
                              color: const Color.fromARGB(255, 255, 255, 255),
                              // blurRadius: 13.1,
                              // offset: const Offset(0, 4),
                            ),
                          ],
                          image: _user!.fotoProfil != null && _user!.fotoProfil!.isNotEmpty
                              ? DecorationImage(
                                  image: NetworkImage(
                                    AppImageHelper.fotoPengguna(_user!.fotoProfil!),
                                  ),
                                  fit: BoxFit.cover,
                                )
                              : null,
                        ),
                        child: _user!.fotoProfil == null || _user!.fotoProfil!.isEmpty
                            ? Icon(Icons.person, size: 50, color: AppColors.textSecondary)
                            : null,
                      ),
                    ),
                  ),
                  
                  // Tombol Edit with better design
                  Positioned(
                    right: 8,
                    top: 8,
                    child: GestureDetector(
                      onTap: () async {
                        final result = await Navigator.push(
                          context,
                          MaterialPageRoute(builder: (BuildContext context) => const EditPage()),
                        );
                        if (result == true) {
                          _loadUser();
                        }
                      },
                      child: Container(
                        width: 32,
                        height: 32,
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            colors: [Color(0xFFE4E4E4), Color(0xFFD0D0D0)],
                          ),
                          border: Border.all(color: AppColors.secondary.withOpacity(0.3), width: 1),
                          shape: BoxShape.circle,
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.15),
                              blurRadius: 6,
                              offset: const Offset(0, 2),
                            ),
                          ],
                        ),
                        child: const Icon(
                          Icons.edit_outlined,
                          size: 16,
                          color: AppColors.secondary,
                        ),
                      ),
                    ),
                  ),
                  
                  // Info User
                  Positioned(
                    left: 110,
                    top: 13,
                    right: 50,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _user!.namaLengkap ?? '',
                          style: AppTextStyles.title.copyWith(
                            fontSize: 20,
                            fontWeight: FontWeight.w600,
                            color: AppColors.textPrimary,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Row(
                          children: [
                            Icon(Icons.email_outlined, size: 18, color: AppColors.secondary),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                _user!.email ?? '',
                                style: AppTextStyles.caption.copyWith(
                                  fontWeight: FontWeight.w500,
                                  fontSize: 12,
                                  color: AppColors.textSecondary,
                                ),
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 5),
                        Row(
                          children: [
                            Icon(Icons.phone_outlined, size: 18, color: AppColors.secondary),
                            const SizedBox(width: 10),
                            Text(
                              _user!.noTelepon ?? '',
                              style: AppTextStyles.caption.copyWith(
                                fontWeight: FontWeight.w500,
                                fontSize: 12,
                                color: AppColors.textSecondary,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          
          const SizedBox(height: 30),
          
          // Menu Container with better design
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Container(
              width: 360,
              decoration: BoxDecoration(
                color: const Color(0xFFF5F7FA),
                borderRadius: BorderRadius.circular(20),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.05),
                    blurRadius: 10,
                    offset: const Offset(0, -2),
                  ),
                ],
              ),
              child: Column(
                children: [
                  const SizedBox(height: 20),
                  _buildMenuItem(Icons.storefront_outlined, 'Toko Saya', () => _openWebToko()),
                  const SizedBox(height: 10),
                  _buildDivider(),
                  const SizedBox(height: 10),
                  _buildMenuItem(Icons.card_giftcard_outlined, 'Poin & Reward', () => _navigateTo(const RewardPage())),
                  const SizedBox(height: 10),
                  _buildDivider(),
                  const SizedBox(height: 10),
                  _buildMenuItem(Icons.assignment_outlined, 'Aktivitas Saya', () => _navigateTo(const AktivitasPage())),
                  const SizedBox(height: 10),
                  _buildDivider(),
                  const SizedBox(height: 10),
                  _buildMenuItem(Icons.notifications_outlined, 'Notifikasi', () => _navigateTo(const NotificationPage())),
                  const SizedBox(height: 10),
                  _buildDivider(),
                  const SizedBox(height: 10),
                  _buildMenuItem(Icons.privacy_tip_outlined, 'Syarat dan Ketentuan', () => _navigateTo(const SyaratKetentuanPage())),
                  const SizedBox(height: 10),
                  _buildDivider(),
                  const SizedBox(height: 10),
                  _buildMenuItem(Icons.logout_outlined, 'Logout', _handleLogout, isLogout: true),
                  const SizedBox(height: 20),
                ],
              ),
            ),
          ),
          
          const SizedBox(height: 40),
        ],
      ),
    );
  }

  Widget _buildMenuItem(IconData icon, String title, VoidCallback onTap, {bool isLogout = false}) {
    return TweenAnimationBuilder(
      tween: Tween<double>(begin: 0, end: 1),
      duration: Duration(milliseconds: 300),
      builder: (context, double value, child) {
        return Transform.translate(
          offset: Offset(20 * (1 - value), 0),
          child: Opacity(
            opacity: value,
            child: child,
          ),
        );
      },
      child: GestureDetector(
        onTap: onTap,
        child: Container(
          width: 340,
          height: 55,
          decoration: BoxDecoration(
            gradient: isLogout
                ? LinearGradient(
                    begin: Alignment.centerLeft,
                    end: Alignment.centerRight,
                    colors: [
                      Colors.white,
                      const Color(0xFFFFF0F0),
                    ],
                  )
                : LinearGradient(
                    begin: Alignment.centerLeft,
                    end: Alignment.centerRight,
                    colors: [
                      Colors.white,
                      const Color(0xFFF8F9FA),
                    ],
                  ),
            border: Border.all(
              color: isLogout ? Colors.red.withOpacity(0.3) : AppColors.secondary.withOpacity(0.2),
              width: 1,
            ),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.08),
                blurRadius: 8,
                offset: const Offset(0, 2),
              ),
            ],
            borderRadius: BorderRadius.circular(12),
          ),
          child: Row(
            children: [
              Container(
                width: 48,
                height: 48,
                margin: const EdgeInsets.only(left: 4),
                decoration: BoxDecoration(
                  gradient: isLogout
                      ? const LinearGradient(
                          colors: [Color(0xFFD32F2F), Color(0xFFE57373)],
                        )
                      : LinearGradient(
                          colors: [AppColors.secondary, AppColors.primary],
                        ),
                  borderRadius: BorderRadius.circular(10),
                  boxShadow: [
                    BoxShadow(
                      color: isLogout ? Colors.red.withOpacity(0.3) : AppColors.secondary.withOpacity(0.3),
                      blurRadius: 6,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: Icon(icon, color: Colors.white, size: 24),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Text(
                  title,
                  style: AppTextStyles.body.copyWith(
                    fontWeight: FontWeight.w600,
                    fontSize: 14,
                    color: isLogout ? Colors.red : AppColors.textPrimary,
                  ),
                ),
              ),
              Container(
                width: 32,
                height: 32,
                margin: const EdgeInsets.only(right: 12),
                decoration: BoxDecoration(
                  color: isLogout ? Colors.red.withOpacity(0.1) : const Color(0xFFF0F0F0),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  Icons.arrow_forward_ios_rounded,
                  size: 14,
                  color: isLogout ? Colors.red : AppColors.secondary,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildDivider() {
    return Container(
      width: 320,
      height: 1,
      color: const Color(0xFFE5E5E5),
    );
  }
}