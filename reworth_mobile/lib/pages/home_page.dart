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
import '../pages/belanja_detail_page.dart';
import '../pages/event_page.dart';
import '../pages/event_detail_page.dart';
import '../pages/reward_page.dart';
import '../pages/leaderboard_page.dart';
import '../pages/aktivitas_page.dart';
import '../pages/profil_page.dart';
import '../pages/notification_page.dart';
import '../models/event_model.dart';
import '../utils/app_image_helper.dart';
import '../widgets/app_avatar_stack.dart';

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

  final PageController _bannerController = PageController();
  int _currentBannerIndex = 0;


  static const double _barHeight = 60.0;
  static const double _labelStripHeight = 22.0;

  List<EventModel> _recentEvents = [];
  bool _loadingEvents = false;

  List<Map<String, dynamic>> _topProducts = [];
  bool _loadingProducts = false;


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
    await _fetchRecentEvents();
    await _fetchTopProducts();
    await _loadUnreadNotificationCount();
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

  Future<void> _fetchRecentEvents() async {
    if (_loadingEvents) return;
    setState(() => _loadingEvents = true);
    try {
      final data = await _supabase
          .from('event')
          .select('''
            *,
            admin!inner (
              id_role,
              role!inner (
                nama_role
              )
            )
          ''')
          .order('created_at', ascending: false)
          .limit(3);

      final events = (data as List)
          .map((e) => EventModel.fromMap(e))
          .toList();

      final eventIds = events.map((e) => e.idEvent).toList();
      if (eventIds.isNotEmpty) {
        final pendaftarData = await _supabase
            .from('pendaftar_event')
            .select('id_event, id_pengguna, pengguna(foto_profil)')
            .inFilter('id_event', eventIds);

        Map<String, int> countMap = {};
        Map<String, List<String>> avatarMap = {};

        // Group by event
        for (var eventId in eventIds) {
          final pendaftarForEvent = pendaftarData.where((item) => item['id_event'] == eventId).toList();
          
          // Hitung jumlah
          countMap[eventId] = pendaftarForEvent.length;
          
          // Ambil avatar unik berdasarkan id_pengguna
          final uniqueUsers = <String, String>{};
          for (var item in pendaftarForEvent) {
            final idPengguna = item['id_pengguna']?.toString() ?? '';
            final foto = item['pengguna']?['foto_profil'];
            if (foto != null && foto.toString().isNotEmpty && !uniqueUsers.containsKey(idPengguna)) {
              uniqueUsers[idPengguna] = foto;
            }
          }
          
          // Ambil max 3 avatar
          avatarMap[eventId] = uniqueUsers.values.take(3).toList();
        }

        final eventsWithCount = events.map((event) {
          return EventModel.fromMap(
            {
              'id_event': event.idEvent,
              'nama_event': event.namaEvent,
              'deskripsi': event.deskripsi,
              'narasumber': event.narasumber,
              'tanggal': event.tanggal?.toIso8601String(),
              'waktu': event.waktu,
              'lokasi': event.lokasi,
              'persyaratan': event.persyaratan,
              'max_partisipan': event.maxPartisipan,
              'id_pembuat': event.idPembuat,
              'status': event.status,
              'foto_event': event.fotoEvent,
              'latitude': event.latitude,
              'longitude': event.longitude,
              'created_at': event.createdAt?.toIso8601String(),
            },
            jumlahPendaftar: countMap[event.idEvent] ?? 0,
            namaRole: event.namaRole,
            avatarList: avatarMap[event.idEvent] ?? [],
          );
        }).toList();

        if (mounted) {
          setState(() {
            _recentEvents = eventsWithCount;
            _loadingEvents = false;
          });
        }
      } else {
        if (mounted) setState(() => _loadingEvents = false);
      }
    } catch (e) {
      debugPrint('Error fetching recent events: $e');
      if (mounted) setState(() => _loadingEvents = false);
    }
  }

  Future<void> _fetchTopProducts() async {
    if (_loadingProducts) return;
    setState(() => _loadingProducts = true);
    try {
      // Query produk dengan jumlah terjual terbanyak (dari pesanan status 'selesai')
      final response = await _supabase.from('produk').select('''
        id_produk,
        nama_produk,
        harga,
        penjual (
          nama_penjual,
          foto_profil
        ),
        foto_produk (
          path_foto
        ),
        pesanan (
          status
        )
      ''');

      List<Map<String, dynamic>> productsWithSales = [];

      for (final item in response) {
        final pesananList = item['pesanan'] as List? ?? [];
        final jumlahTerjual = pesananList.where((p) {
          return (p['status'] ?? '').toString().toLowerCase() == 'selesai';
        }).length;

        final fotoList = item['foto_produk'] as List?;
        final String gambarProduk = (fotoList != null && fotoList.isNotEmpty)
            ? (fotoList.first['path_foto'] as String? ?? '')
            : '';

        final penjualData = item['penjual'] as Map<String, dynamic>?;
        
        productsWithSales.add({
          'id_produk': item['id_produk'] as String? ?? '',
          'nama_produk': item['nama_produk'] as String? ?? '',
          'harga': item['harga'] as int? ?? 0,
          'nama_penjual': penjualData?['nama_penjual'] as String? ?? '',
          'foto_penjual': penjualData?['foto_profil'] as String? ?? '',
          'gambar_produk': gambarProduk,
          'terjual': jumlahTerjual,
          'rating': 4.5 + ((productsWithSales.length % 5) * 0.1), // rating dummy
        });
      }

      // Urutkan berdasarkan terjual terbanyak dan ambil 4
      productsWithSales.sort((a, b) => (b['terjual'] as int).compareTo(a['terjual'] as int));
      final top4 = productsWithSales.take(4).toList();

      if (mounted) {
        setState(() {
          _topProducts = top4;
          _loadingProducts = false;
        });
      }
    } catch (e) {
      debugPrint('Error fetching top products: $e');
      if (mounted) setState(() => _loadingProducts = false);
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
    final bottomNavHeight = 30 + _barHeight + _labelStripHeight + MediaQuery.of(context).padding.bottom;
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
                    bottom: -40,
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
                padding: const EdgeInsets.only(top: 56),
                child: Column(
                  children: [
                    _buildMenuGrid(),
                    const SizedBox(height: AppConstants.paddingM),
                    _buildRewardSection(), // MOVED: Reward section first
                    const SizedBox(height: AppConstants.paddingM),
                    _buildEventSection(), // MOVED: Event section second
                    const SizedBox(height: AppConstants.paddingM),
                    _buildProductSection(), // MOVED: Product section third
                    const SizedBox(height: AppConstants.paddingM),
                    const SizedBox(height: 16),
                  ],
                ),
              ),
            ),
            SliverPadding(
              padding: EdgeInsets.only(bottom: bottomNavHeight + 16),
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

  Widget _buildEventSection() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(AppConstants.paddingL, 0, AppConstants.paddingL, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Event Terbaru',
                style: AppTextStyles.title.copyWith(fontSize: 16),
              ),
              GestureDetector(
                onTap: () => _navigate(const EventPage()),
                child: Text(
                  'Lihat semua',
                  style: AppTextStyles.body.copyWith(
                    color: AppColors.secondary,
                    fontWeight: FontWeight.w600,
                    fontSize: 12,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          _loadingEvents
              ? _buildEventSkeleton()
              : _recentEvents.isEmpty
                  ? _buildEmptyEvent()
                  : SizedBox(
                      height: 200, // KURANGI dari 195 ke 180
                      child: ListView.separated(
                        scrollDirection: Axis.horizontal,
                        itemCount: _recentEvents.length,
                        separatorBuilder: (_, __) => const SizedBox(width: 12),
                        itemBuilder: (_, i) => SizedBox(
                          width: MediaQuery.of(context).size.width * 0.75,
                          child: _buildEventCardMini(_recentEvents[i]),
                        ),
                      ),
                    ),
        ],
      ),
    );
  }

  Widget _buildEventCardMini(EventModel event) {
    final fotoUrl = AppImageHelper.fotoEvent(event.fotoEvent);

    return GestureDetector(
      onTap: () => _navigateToEventDetail(event),
      child: Container(
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(AppConstants.radiusL),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.07),
              blurRadius: 10,
              offset: const Offset(0, 3),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.all(AppConstants.paddingM),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(AppConstants.radiusM),
                    child: SizedBox(
                      width: 80,
                      height: 110,
                      child: fotoUrl.isNotEmpty
                          ? Image.network(
                              fotoUrl,
                              fit: BoxFit.cover,
                              errorBuilder: (_, __, ___) => _eventImagePlaceholder(),
                            )
                          : _eventImagePlaceholder(),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          event.namaEvent ?? '-',
                          style: AppTextStyles.title.copyWith(fontSize: 14),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 4),
                        Text(
                          event.deskripsi ?? '',
                          style: AppTextStyles.caption.copyWith(fontSize: 11),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 6),
                        Row(
                          children: [
                            AvatarStack(
                              avatars: event.avatarList,
                              maxAvatars: 3,
                              avatarSize: 20,
                            ),
                            const Spacer(),
                            const Icon(Icons.people_outline,
                                size: 12, color: AppColors.textSecondary),
                            const SizedBox(width: 4),
                            Text(
                              event.kuotaText,
                              style: AppTextStyles.small.copyWith(fontSize: 10),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(
                horizontal: AppConstants.paddingM,
                vertical: AppConstants.paddingS,
              ),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                decoration: BoxDecoration(
                  color: const Color(0xFFF1F1F1),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.location_on_outlined,
                        size: 11, color: AppColors.textSecondary),
                    const SizedBox(width: 4),
                    Flexible(
                      child: Text(
                        event.lokasi ?? '-',
                        style: AppTextStyles.small.copyWith(fontSize: 10),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    const SizedBox(width: 6),
                    Text('•',
                        style: AppTextStyles.small
                            .copyWith(color: AppColors.textSecondary)),
                    const SizedBox(width: 6),
                    const Icon(Icons.calendar_today_outlined,
                        size: 11, color: AppColors.textSecondary),
                    const SizedBox(width: 4),
                    Text(
                      event.tanggalFormatted,
                      style: AppTextStyles.small.copyWith(fontSize: 10),
                    ),
                    const SizedBox(width: 8),
                    Text('•',
                        style: AppTextStyles.small
                            .copyWith(color: AppColors.textSecondary)),
                    const SizedBox(width: 4),
                    const Icon(Icons.access_time_rounded,
                        size: 11, color: AppColors.textSecondary),
                    const SizedBox(width: 4),
                    Text(
                      event.waktuFormatted,
                      style: AppTextStyles.small.copyWith(fontSize: 10),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _eventImagePlaceholder() {
    return Container(
      color: AppColors.primary.withOpacity(0.15),
      child: const Center(
        child: Icon(Icons.event_outlined, color: AppColors.secondary, size: 28),
      ),
    );
  }

  Widget _buildEventSkeleton() {
    return SizedBox(
      height: 225,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: 3,
        separatorBuilder: (_, __) => const SizedBox(width: 12),
        itemBuilder: (_, __) => SizedBox(
          width: MediaQuery.of(context).size.width * 0.75,
          child: Container(
            decoration: BoxDecoration(
              color: AppColors.white,
              borderRadius: BorderRadius.circular(AppConstants.radiusL),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  height: 120,
                  decoration: BoxDecoration(
                    color: const Color(0xFFE0E0E0),
                    borderRadius: const BorderRadius.only(
                      topLeft: Radius.circular(AppConstants.radiusL),
                      topRight: Radius.circular(AppConstants.radiusL),
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(AppConstants.paddingL, 0, AppConstants.paddingL, 12),
                  child: Column(
                    children: [
                      const _ShimmerBox(width: double.infinity, height: 14),
                      const SizedBox(height: 8),
                      const _ShimmerBox(width: 150, height: 10),
                      const SizedBox(height: 6),
                      const _ShimmerBox(width: 200, height: 10),
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          const _ShimmerBox(width: 60, height: 20),
                          const Spacer(),
                          const _ShimmerBox(width: 50, height: 10),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildEmptyEvent() {
    return Container(
      height: 210,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppConstants.radiusL),
      ),
      child: const Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.event_busy_outlined,
                size: 32, color: Color(0xFFCCCCCC)),
            SizedBox(height: 8),
            Text(
              'Belum ada event terbaru',
              style: TextStyle(color: Color(0xFF999999), fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }

  void _navigateToEventDetail(EventModel event) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => EventDetailPage(event: event),
      ),
    );
  }

  Widget _buildRewardSection() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: AppConstants.paddingL),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Tukar Reward',
                style: AppTextStyles.title.copyWith(fontSize: 16),
              ),
              GestureDetector(
                onTap: () => _navigate(const RewardPage()),
                child: Text(
                  'Lihat semua',
                  style: AppTextStyles.body.copyWith(
                    color: AppColors.secondary,
                    fontWeight: FontWeight.w600,
                    fontSize: 12,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          SizedBox(
            height: 160,
            child: PageView.builder(
              controller: _bannerController,
              onPageChanged: (int index) {
                setState(() {
                  _currentBannerIndex = index;
                });
              },
              itemCount: 5, // Jumlah banner
              itemBuilder: (context, index) {
                return Padding(
                  padding: const EdgeInsets.only(right: 16),
                  child: _buildRewardBanner(index),
                );
              },
            ),
          ),
          const SizedBox(height: 8),
          // Dots indicator yang dinamis
          _buildDotsIndicator(5, _currentBannerIndex),
        ],
      ),
    );
  }

  Widget _buildDotsIndicator(int itemCount, int currentIndex) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: List.generate(
        itemCount,
        (index) => GestureDetector(
          onTap: () {
            _bannerController.animateToPage(
              index,
              duration: const Duration(milliseconds: 300),
              curve: Curves.easeInOut,
            );
          },
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 200),
            margin: const EdgeInsets.symmetric(horizontal: 4),
            width: index == currentIndex ? 24 : 8,
            height: 8,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(4),
              color: index == currentIndex 
                  ? AppColors.secondary 
                  : AppColors.secondary.withOpacity(0.3),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildRewardBanner(int index) {
    return GestureDetector(
      onTap: () => _navigate(const RewardPage()),
      child: Container(
        width: MediaQuery.of(context).size.width - (AppConstants.paddingL * 2),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(AppConstants.radiusL),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.1),
              blurRadius: 15,
              offset: const Offset(0, 5),
            ),
          ],
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(AppConstants.radiusL),
          child: Image.asset(
            'assets/banner_reward/${index + 1}.png',
            width: MediaQuery.of(context).size.width - (AppConstants.paddingL * 2),
            height: 150,
            fit: BoxFit.cover,
            errorBuilder: (_, __, ___) => Container(
              width: MediaQuery.of(context).size.width - (AppConstants.paddingL * 2),
              height: 150,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [
                    AppColors.secondary,
                    AppColors.secondary.withOpacity(0.7),
                  ],
                ),
                borderRadius: BorderRadius.circular(AppConstants.radiusL),
              ),
              child: const Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.card_giftcard_outlined, 
                        color: Colors.white, size: 40),
                    SizedBox(height: 8),
                    Text(
                      'Tukar Reward Sekarang!',
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildProductSection() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: AppConstants.paddingL),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Produk Terlaris',
                style: AppTextStyles.title.copyWith(fontSize: 16),
              ),
              GestureDetector(
                onTap: () => _navigate(const BelanjaPage()),
                child: Text(
                  'Lihat semua',
                  style: AppTextStyles.body.copyWith(
                    color: AppColors.secondary,
                    fontWeight: FontWeight.w600,
                    fontSize: 12,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          _loadingProducts
              ? _buildProductSkeleton()
              : _topProducts.isEmpty
                  ? _buildEmptyProduct()
                  : GridView.builder(
                      shrinkWrap: true,
                      padding: EdgeInsets.zero,
                      physics: const NeverScrollableScrollPhysics(),
                      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: 2,
                        mainAxisSpacing: 12,
                        crossAxisSpacing: 12,
                        childAspectRatio: 0.68,
                      ),
                      itemCount: _topProducts.length,
                      itemBuilder: (_, i) => _buildProductCard(_topProducts[i]),
                    ),
        ],
      ),
    );
  }

  Widget _buildProductCard(Map<String, dynamic> product) {
    final fotoUrl = AppImageHelper.fotoProduk(product['gambar_produk']);
    final fotoPenjual = AppImageHelper.fotoPenjual(product['foto_penjual']);

    return GestureDetector(
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => BelanjaDetailPage(
              idProduk: product['id_produk'],
            ),
          ),
        );
      },
      child: Container(
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(AppConstants.radiusL),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.08),
              blurRadius: 15,
              offset: const Offset(0, 5),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Image with badge for best seller
            Stack(
              children: [
                ClipRRect(
                  borderRadius: const BorderRadius.only(
                    topLeft: Radius.circular(AppConstants.radiusL),
                    topRight: Radius.circular(AppConstants.radiusL),
                  ),
                  child: SizedBox(
                    height: 120,
                    width: double.infinity,
                    child: Image.network(
                      fotoUrl,
                      fit: BoxFit.cover,
                      loadingBuilder: (context, child, progress) {
                        if (progress == null) return child;
                        return Container(
                          color: const Color(0xFFF5F5F5),
                          child: const Center(
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: AppColors.secondary,
                            ),
                          ),
                        );
                      },
                      errorBuilder: (_, __, ___) => Container(
                        color: const Color(0xFFF5F5F5),
                        child: const Center(
                          child: Icon(
                            Icons.image_not_supported_outlined,
                            color: AppColors.textHint,
                            size: 32,
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
                Positioned(
                  top: 8,
                  left: 8,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [Colors.amber, Colors.orange],
                      ),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Row(
                      children: [
                        Icon(Icons.emoji_events, size: 12, color: Colors.white),
                        SizedBox(width: 4),
                        Text(
                          'Best Seller',
                          style: TextStyle(
                            fontSize: 10,
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
            // Info Produk
            Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    product['nama_produk'] ?? '-',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: AppTextStyles.body.copyWith(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    _rupiah(product['harga'] ?? 0),
                    style: AppTextStyles.label.copyWith(
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                      color: AppColors.secondary,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      ClipOval(
                        child: Image.network(
                          fotoPenjual,
                          width: 20,
                          height: 20,
                          fit: BoxFit.cover,
                          errorBuilder: (_, __, ___) => Container(
                            width: 20,
                            height: 20,
                            decoration: BoxDecoration(
                              color: AppColors.primary.withOpacity(0.3),
                              shape: BoxShape.circle,
                            ),
                            child: const Icon(
                              Icons.store,
                              size: 12,
                              color: AppColors.secondary,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          product['nama_penjual'] ?? '-',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: AppTextStyles.small.copyWith(fontSize: 11),
                        ),
                      ),
                      const Icon(
                        Icons.star_rounded,
                        size: 14,
                        color: AppColors.accent,
                      ),
                      const SizedBox(width: 2),
                      Text(
                        (product['rating'] as double).toStringAsFixed(1),
                        style: AppTextStyles.small.copyWith(
                          fontWeight: FontWeight.w600,
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: AppColors.primary.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Text(
                      'Terjual ${product['terjual']}',
                      style: TextStyle(
                        fontSize: 9,
                        color: AppColors.secondary,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }


  String _rupiah(int angka) {
    final s = angka.toString();
    final buf = StringBuffer('Rp');
    final start = s.length % 3;
    if (start > 0) buf.write(s.substring(0, start));
    for (int i = start; i < s.length; i += 3) {
      if (i > 0 || start > 0) buf.write('.');
      buf.write(s.substring(i, i + 3));
    }
    return buf.toString();
  }

  Widget _buildProductSkeleton() {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 12,
        crossAxisSpacing: 12,
        childAspectRatio: 0.68,
      ),
      itemCount: 4,
      itemBuilder: (_, __) => Container(
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(AppConstants.radiusL),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              height: 120,
              decoration: BoxDecoration(
                color: const Color(0xFFE0E0E0),
                borderRadius: const BorderRadius.only(
                  topLeft: Radius.circular(AppConstants.radiusL),
                  topRight: Radius.circular(AppConstants.radiusL),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(10),
              child: Column(
                children: [
                  const _ShimmerBox(width: double.infinity, height: 12),
                  const SizedBox(height: 8),
                  const _ShimmerBox(width: 80, height: 14),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      const _ShimmerBox(width: 20, height: 20),
                      const SizedBox(width: 4),
                      const _ShimmerBox(width: 60, height: 10),
                      const Spacer(),
                      const _ShimmerBox(width: 30, height: 10),
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

  Widget _buildEmptyProduct() {
    return Container(
      height: 200,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppConstants.radiusL),
      ),
      child: const Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.store_outlined,
                size: 32, color: Color(0xFFCCCCCC)),
            SizedBox(height: 8),
            Text(
              'Belum ada produk',
              style: TextStyle(color: Color(0xFF999999), fontSize: 12),
            ),
          ],
        ),
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