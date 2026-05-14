import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:curved_navigation_bar/curved_navigation_bar.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:intl/intl.dart';
import '../models/user_model.dart';
import '../utils/app_colors.dart';
import '../utils/app_text_styles.dart';
import '../utils/app_constants.dart';
import '../services/auth_service.dart';
import '../pages/lapor_page.dart';
import '../pages/setor_page.dart';
import '../pages/belanja_page.dart';
import '../pages/event_page.dart';
import '../pages/reward_page.dart';
import '../pages/leaderboard_page.dart';
import '../pages/aktivitas_page.dart';
import '../pages/profil_page.dart';
import '../pages/notification_page.dart';

class HomePage extends StatefulWidget {
  const HomePage({super.key});

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  final _supabase = Supabase.instance.client;

  final GlobalKey<CurvedNavigationBarState> _navKey = GlobalKey();

  int _selectedNavIndex = 0;

  UserModel? _user;
  bool _loadingUser = true;
  int _unreadCount = 0;

  static const _navItems = [
    _NavItem(Icons.home_rounded, 'Beranda'),
    _NavItem(Icons.description_outlined, 'Aktivitas'),
    _NavItem(Icons.person_rounded, 'Profil'),
  ];

  static const double _barHeight = 60.0;
  static const double _labelStripHeight = 22.0;

  @override
  void initState() {
    super.initState();
    _fetchAll();
    _loadUnreadNotificationCount();
  }


  void _navigate(Widget page) {
    Navigator.push(context, MaterialPageRoute(builder: (_) => page));
  }


  Future<void> _fetchAll() async {
    await _fetchUser();
  }

  Future<void> _loadUnreadNotificationCount() async {
    try {
      final count = await AuthService.getUnreadNotificationCount();
      if (mounted) setState(() => _unreadCount = count);
    } catch (e) {
      debugPrint('Error loading unread count: $e');
    }
  }

  Future<void> _fetchUser() async {
    try {
      final authUser = _supabase.auth.currentUser;
      if (authUser == null) {
        if (mounted) setState(() => _loadingUser = false);
        return;
      }
      final data = await _supabase
          .from('pengguna')
          .select()
          .eq('id_pengguna', authUser.id)
          .maybeSingle();
      if (!mounted) return;
      setState(() {
        _user = data != null ? UserModel.fromMap(data) : null;
        _loadingUser = false;
      });
    } catch (e) {
      debugPrint('ERROR FETCH USER: $e');
      if (mounted) setState(() => _loadingUser = false);
    }
  }


  @override
  Widget build(BuildContext context) {
    SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
      statusBarIconBrightness: Brightness.dark,
    ));

    final bottomInset = MediaQuery.of(context).padding.bottom;
    final double totalNavHeight =
        30 + _barHeight + _labelStripHeight + bottomInset;

    return Scaffold(
      backgroundColor: AppColors.background,
      extendBody: true,
      body: IndexedStack(
        index: _selectedNavIndex,
        children: [
          // Tab 0 — Beranda
          MediaQuery(
            data: MediaQuery.of(context).copyWith(
              padding: MediaQuery.of(context)
                  .padding
                  .copyWith(bottom: totalNavHeight),
            ),
            child: _buildBeranda(),
          ),
          // Tab 1 — Aktivitas
          MediaQuery(
            data: MediaQuery.of(context).copyWith(
              padding: MediaQuery.of(context)
                  .padding
                  .copyWith(bottom: totalNavHeight),
            ),
            child: const AktivitasPage(),
          ),
          // Tab 2 — Profil
          MediaQuery(
            data: MediaQuery.of(context).copyWith(
              padding: MediaQuery.of(context)
                  .padding
                  .copyWith(bottom: totalNavHeight),
            ),
            child: const ProfilPage(),
          ),
        ],
      ),
      bottomNavigationBar: _buildNavBar(bottomInset),
    );
  }

  Widget _buildNavBar(double bottomInset) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        CurvedNavigationBar(
          key: _navKey,
          index: _selectedNavIndex,
          height: _barHeight,
          color: const Color(0xFF1A2800),
          backgroundColor: Colors.transparent,
          buttonBackgroundColor: AppColors.secondary,
          animationDuration: const Duration(milliseconds: 380),
          animationCurve: Curves.easeInOutCubic,
          onTap: (index) {
            HapticFeedback.lightImpact();
            setState(() => _selectedNavIndex = index);
          },
          items: List.generate(_navItems.length, (i) {
            final isActive = i == _selectedNavIndex;
            return Icon(
              _navItems[i].icon,
              size: 26,
              color: isActive ? Colors.white : Colors.white54,
            );
          }),
        ),

        // Label
        Container(
          height: _labelStripHeight,
          color: const Color(0xFF1A2800),
          child: Row(
            children: List.generate(_navItems.length, (i) {
              final isActive = i == _selectedNavIndex;
              return Expanded(
                child: GestureDetector(
                  behavior: HitTestBehavior.opaque,
                  onTap: () {
                    HapticFeedback.lightImpact();
                    _navKey.currentState?.setPage(i);
                    setState(() => _selectedNavIndex = i);
                  },
                  child: Align(
                    alignment: const Alignment(0, -1),
                    child: AnimatedDefaultTextStyle(
                      duration: const Duration(milliseconds: 200),
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight:
                            isActive ? FontWeight.w700 : FontWeight.w400,
                        color: isActive
                            ? AppColors.primary
                            : Colors.white38,
                        letterSpacing: isActive ? 0.4 : 0,
                      ),
                      child: Text(
                        _navItems[i].label,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        textAlign: TextAlign.center,
                      ),
                    ),
                  ),
                ),
              );
            }),
          ),
        ),

        Container(color: const Color(0xFF1A2800), height: bottomInset),
      ],
    );
  }


  Widget _buildBeranda() {
    return Scaffold(
      backgroundColor: const Color(0xFFF5F5F5),
      body: RefreshIndicator(
        color: AppColors.secondary,
        onRefresh: _fetchAll,
        child: CustomScrollView(
          slivers: [
            SliverToBoxAdapter(
              child: Stack(
                clipBehavior: Clip.none,
                children: [
                  // Header gradient
                  _buildHeader(),

                  // Card tabungan overlap di bawah header
                  Positioned(
                    bottom: -40, // setengah card keluar ke bawah header
                    left: AppConstants.paddingL,
                    right: AppConstants.paddingL,
                    child: _buildTabunganCard(),
                  ),
                ],
              ),
            ),
            SliverToBoxAdapter(
              child: Container(
                color: const Color(0xFFF5F5F5),
                padding: const EdgeInsets.only(top: 56), // ruang untuk card yang overlap
                child: Column(
                  children: [
                    _buildMenuGrid(),
                    const SizedBox(height: AppConstants.paddingM),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return Container(
      decoration: const BoxDecoration(
        image: DecorationImage(
          image: AssetImage('assets/gradient.png'),
          fit: BoxFit.cover,
        ),
      ),
      padding: EdgeInsets.only(
        top: MediaQuery.of(context).padding.top + AppConstants.paddingM,
        left: AppConstants.paddingL,
        right: AppConstants.paddingL,
        bottom: AppConstants.paddingXL + AppConstants.paddingL,
      ),
      child: Row(
        children: [
          CircleAvatar(
            radius: 24,
            backgroundColor: AppColors.white,
            backgroundImage:
                _user?.fotoProfil != null && _user!.fotoProfil!.isNotEmpty
                    ? _getProfileImage(_user!.fotoProfil!)
                    : null,
            child: _user?.fotoProfil == null || _user!.fotoProfil!.isEmpty
                ? const Icon(Icons.person, color: AppColors.secondary, size: 28)
                : null,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: _loadingUser
                ? const _ShimmerBox(width: 120, height: 36)
                : Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Selamat datang',
                        style: AppTextStyles.body.copyWith(
                          color: const Color(0xFF2A3D00),
                          fontSize: 12,
                        ),
                      ),
                      Text(
                        _user?.namaLengkap ?? '',
                        style: AppTextStyles.title.copyWith(
                          color: const Color(0xFF1A2800),
                          fontSize: 17,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
          ),
          _HeaderIconButton(icon: Icons.search_rounded, onTap: () {}),
          const SizedBox(width: 8),
          Stack(
            clipBehavior: Clip.none,
            children: [
              _HeaderIconButton(
                icon: Icons.notifications_none_rounded,
                onTap: () => _navigate(const NotificationPage()),
              ),
              if (_unreadCount > 0)
                Positioned(
                  top: -2,
                  right: -2,
                  child: Container(
                    width: 18,
                    height: 18,
                    decoration: const BoxDecoration(
                      color: AppColors.accent,
                      shape: BoxShape.circle,
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      '$_unreadCount',
                      style: const TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                        color: Colors.white,
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildTabunganCard() {
    final rupiahFormat = NumberFormat.currency(
      locale: 'id_ID',
      symbol: 'Rp. ',
      decimalDigits: 0,
    );

    return Container(
        padding: const EdgeInsets.symmetric(
          horizontal: AppConstants.paddingL,
          vertical: AppConstants.paddingM,
        ),
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(AppConstants.radiusL),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.06),
              blurRadius: 12,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: _loadingUser
            ? const Row(children: [
                Expanded(
                    child: _ShimmerBox(width: double.infinity, height: 48)),
                SizedBox(width: 16),
                Expanded(
                    child: _ShimmerBox(width: double.infinity, height: 48)),
              ])
            : IntrinsicHeight(
                child: Row(
                  children: [
                    Expanded(
                      child: Row(
                        children: [
                          Container(
                            width: 40,
                            height: 40,
                            decoration: BoxDecoration(
                              color: AppColors.primary.withOpacity(0.2),
                              borderRadius:
                                  BorderRadius.circular(AppConstants.radiusS),
                            ),
                            child: const Icon(
                                Icons.account_balance_wallet_outlined,
                                color: AppColors.secondary,
                                size: 20),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text('Tabungan Anda',
                                    style: AppTextStyles.small.copyWith(
                                        color: AppColors.textSecondary)),
                                Text(
                                  rupiahFormat
                                      .format(_user?.saldoTabungan ?? 0),
                                  style:
                                      AppTextStyles.title.copyWith(fontSize: 15),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    const VerticalDivider(
                        width: 24,
                        thickness: 1,
                        color: Color(0xFFE5E5E5)),
                    Expanded(
                      child: Row(
                        children: [
                          Container(
                            width: 40,
                            height: 40,
                            decoration: BoxDecoration(
                              color: AppColors.accent.withOpacity(0.2),
                              borderRadius:
                                  BorderRadius.circular(AppConstants.radiusS),
                            ),
                            child: const Icon(Icons.star_outline_rounded,
                                color: AppColors.accent, size: 20),
                          ),
                          const SizedBox(width: 10),
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('Poin Anda',
                                  style: AppTextStyles.small.copyWith(
                                      color: AppColors.textSecondary)),
                              Text(
                                '${_user?.poin ?? 0}',
                                style: AppTextStyles.title
                                    .copyWith(fontSize: 15),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
    );
  }

  Widget _buildMenuGrid() {
    final menus = [
      _MenuItem(
          icon: Icons.campaign_outlined,
          label: 'Lapor',
          onTap: () => _navigate(const LaporSampahPage())),
      _MenuItem(
          icon: Icons.recycling_rounded,
          label: 'Setor',
          onTap: () => _navigate(const SetorPage())),
      _MenuItem(
          icon: Icons.storefront_outlined,
          label: 'Belanja',
          onTap: () => _navigate(const BelanjaPage())),
      _MenuItem(
          icon: Icons.event_outlined,
          label: 'Event',
          onTap: () => _navigate(const EventPage())),
      _MenuItem(
          icon: Icons.card_giftcard_outlined,
          label: 'Reward',
          onTap: () => _navigate(const RewardPage())),
      _MenuItem(
          icon: Icons.leaderboard_outlined,
          label: 'Leaderboard',
          onTap: () => _navigate(const LeaderboardPage())),
    ];

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: AppConstants.paddingL),
      child: Container(
        padding: const EdgeInsets.symmetric(
          horizontal: AppConstants.paddingM,
          vertical: AppConstants.paddingXL
        ),
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(AppConstants.radiusL),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.06),
              blurRadius: 12,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: menus.sublist(0, 3).map((menu) => Expanded(
                child: _buildMenuTile(menu),
              )).toList(),
            ),
            const SizedBox(height: 24),
            Row(
              children: menus.sublist(3, 6).map((menu) => Expanded(
                child: _buildMenuTile(menu),
              )).toList(),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildMenuTile(_MenuItem menu) {
    return GestureDetector(
      onTap: menu.onTap,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: AppColors.primary.withOpacity(0.15),
              borderRadius: BorderRadius.circular(AppConstants.radiusM),
            ),
            child: Icon(menu.icon, color: AppColors.secondary, size: 24),
          ),
          const SizedBox(height: 4),
          Text(
            menu.label,
            style: AppTextStyles.caption.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }
}


class _NavItem {
  final IconData icon;
  final String label;
  const _NavItem(this.icon, this.label);
}


class _MenuItem {
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  const _MenuItem({required this.icon, required this.label, required this.onTap});
}

ImageProvider? _getProfileImage(String? fotoPath) {
  if (fotoPath == null || fotoPath.isEmpty) return null;
  try {
    final imageUrl = fotoPath.startsWith('http')
        ? fotoPath
        : Supabase.instance.client.storage
            .from('media')
            .getPublicUrl(fotoPath);
    return NetworkImage(imageUrl);
  } catch (e) {
    debugPrint('Error loading image: $e');
    return null;
  }
}

class _HeaderIconButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;
  const _HeaderIconButton({required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 38,
        height: 38,
        decoration: BoxDecoration(
          color: Colors.white.withOpacity(0.3),
          shape: BoxShape.circle,
        ),
        child: Icon(icon, color: const Color(0xFF1A2800), size: 20),
      ),
    );
  }
}

class _ShimmerBox extends StatefulWidget {
  final double width;
  final double height;
  const _ShimmerBox({required this.width, required this.height});

  @override
  State<_ShimmerBox> createState() => _ShimmerBoxState();
}

class _ShimmerBoxState extends State<_ShimmerBox>
    with SingleTickerProviderStateMixin {
  late AnimationController _ctrl;
  late Animation<double> _anim;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1200),
    )..repeat(reverse: true);
    _anim = Tween<double>(begin: 0.4, end: 1.0).animate(_ctrl);
  }

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      opacity: _anim,
      child: Container(
        width: widget.width,
        height: widget.height,
        decoration: BoxDecoration(
          color: const Color(0xFFE0E0E0),
          borderRadius: BorderRadius.circular(AppConstants.radiusL),
        ),
      ),
    );
  }
}