import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../models/event_model.dart';
import '../utils/app_colors.dart';
import '../utils/app_text_styles.dart';
import '../utils/app_constants.dart';
import '../utils/app_image_helper.dart';
import '../pages/event_daftar_page.dart';
import '../pages/event_detail_page.dart';
import '../widgets/app_avatar_stack.dart';

class EventPage extends StatefulWidget {
  const EventPage({super.key});

  @override
  State<EventPage> createState() => _EventPageState();
}

class _EventPageState extends State<EventPage> {
  final _supabase = Supabase.instance.client;
  final _searchController = TextEditingController();

  List<EventModel> _allEvents = [];
  List<EventModel> _filteredEvents = [];

  bool _isLoading = true;
  String _selectedFilter = 'Semua';
  String _searchQuery = '';

  final List<String> _filterTabs = ['Semua', 'Terbaru', 'Terdekat', 'Terpopuler'];
  String _selectedCreatorFilter = 'Semua';
  final List<String> _creatorFilters = ['Semua', 'Bank Sampah', 'DLH'];

  @override
  void initState() {
    super.initState();
    _fetchEvents();
    _searchController.addListener(_onSearchChanged);
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _onSearchChanged() {
    setState(() {
      _searchQuery = _searchController.text.toLowerCase();
      _applyFilter();
    });
  }

  Future<void> _fetchEvents() async {
    setState(() => _isLoading = true);
    try {
      // Fetch semua event dengan status != 'selesai'
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
          .neq('status', 'selesai')  // Tambahkan filter ini
          .order('tanggal', ascending: true);

      final events = (data as List)
          .map((e) => EventModel.fromMap(e))
          .toList();

      // Fetch jumlah pendaftar tiap event sekaligus
      final pendaftarData = await _supabase
          .from('pendaftar_event')
          .select('id_event, id_pengguna, pengguna(foto_profil)');

      Map<String, Set<String>> seenMap = {};

      // hitung per event
      Map<String, int> countMap = {};
      Map<String, List<String>> avatarMap = {};

      for (var item in pendaftarData) {
        final idEvent = item['id_event'];
        if (idEvent == null) continue;

        countMap[idEvent] = (countMap[idEvent] ?? 0) + 1;

        final idPengguna = item['id_pengguna']?.toString() ?? '';
        seenMap.putIfAbsent(idEvent, () => {});

        // Skip kalau id_pengguna ini sudah diambil fotonya untuk event ini
        if (seenMap[idEvent]!.contains(idPengguna)) continue;
        seenMap[idEvent]!.add(idPengguna);

        final pengguna = item['pengguna'];
        final foto = pengguna?['foto_profil'];
        print('foto_profil raw: $foto');

        if (foto != null && foto.toString().isNotEmpty) {
          avatarMap.putIfAbsent(idEvent, () => []);
          avatarMap[idEvent]!.add(foto);
        }
      }
      
      final eventsWithCount = events.map((event) {
        final count = countMap[event.idEvent] ?? 0;
        final avatars = avatarMap[event.idEvent] ?? [];

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
          jumlahPendaftar: count,
          namaRole: event.namaRole,
          avatarList: avatars,
          
        );
      }).toList();

      if (!mounted) return;
      setState(() {
        _allEvents = eventsWithCount;
        _applyFilter();
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _isLoading = false);
      _showError('Gagal memuat event: $e');
    }
  }

  void _applyFilter() {
    List<EventModel> result = List.from(_allEvents);

    // Filter tab
    switch (_selectedFilter) {
      case 'Terbaru':
        result.sort((a, b) {
          if (a.createdAt == null && b.createdAt == null) return 0;
          if (a.createdAt == null) return 1;
          if (b.createdAt == null) return -1;
          return b.createdAt!.compareTo(a.createdAt!);
        });
        break;
      case 'Terpopuler':
        result.sort((a, b) => b.jumlahPendaftar.compareTo(a.jumlahPendaftar));
        break;
      case 'Terdekat':
        final now = DateTime.now();
        result.sort((a, b) {
          if (a.tanggal == null && b.tanggal == null) return 0;
          if (a.tanggal == null) return 1;
          if (b.tanggal == null) return -1;

          final isPastA = a.tanggal!.isBefore(now);
          final isPastB = b.tanggal!.isBefore(now);

          if (isPastA != isPastB) {
            return isPastA ? 1 : -1;
          }

          return a.tanggal!.compareTo(b.tanggal!);
        });
        break;

      default:
        result.sort((a, b) {
          final namaA = (a.namaEvent ?? '').toLowerCase();
          final namaB = (b.namaEvent ?? '').toLowerCase();
          return namaA.compareTo(namaB);
        });
    }

    // Filter search query
    if (_searchQuery.isNotEmpty) {
      result = result.where((e) {
        final nama = (e.namaEvent ?? '').toLowerCase();
        final lokasi = (e.lokasi ?? '').toLowerCase();
        final deskripsi = (e.deskripsi ?? '').toLowerCase();
        return nama.contains(_searchQuery) ||
            lokasi.contains(_searchQuery) ||
            deskripsi.contains(_searchQuery);
      }).toList();
    }

    for (var e in _allEvents) {
      print('${e.namaEvent} - ${e.namaRole}');
    }

    if (_selectedCreatorFilter != 'Semua') {
      result = result.where((e) {
        final role = (e.namaRole ?? '').toLowerCase().trim();

        if (_selectedCreatorFilter == 'Bank Sampah') {
          return role == 'bank sampah';
        } else if (_selectedCreatorFilter == 'DLH') {
          return role == 'dlh';
        }
        return true;
      }).toList();
    }

    setState(() {
    _filteredEvents = result;
    });
  }

  void _showError(String msg) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(msg), backgroundColor: Colors.redAccent),
    );
  }

  @override
  Widget build(BuildContext context) {
    final topPad = MediaQuery.of(context).padding.top;
    return Scaffold(
      backgroundColor: const Color(0xFFF2F2F2),
      body: Stack(
        children: [
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            height: topPad + 110,
            child: Image.asset(
              'assets/gradient.png',
              fit: BoxFit.cover,
              errorBuilder: (_, __, ___) =>
                  Container(color: AppColors.primary),
            ),
          ),

          SafeArea(
            bottom: false,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _buildTitleBar(),
                const SizedBox(height: 12),

                Expanded(
                  child: Container(
                    decoration: const BoxDecoration(
                      color: AppColors.white,
                      borderRadius: BorderRadius.only(
                        topLeft: Radius.circular(AppConstants.radiusXL),
                        topRight: Radius.circular(AppConstants.radiusXL),
                      ),
                      boxShadow: [
                        BoxShadow(
                          color: Color(0x14000000),
                          blurRadius: 16,
                          offset: Offset(0, -4),
                        ),
                      ],
                    ),
                    child: Column(
                      children: [
                        _buildSearchBar(),
                        const SizedBox(height: 8),

                        _buildFilterChips(),
                        Expanded(
                          child: RefreshIndicator(
                            color: AppColors.secondary,
                            onRefresh: _fetchEvents,
                            child: _isLoading
                                ? _buildSkeleton()
                                : _buildEventList(),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTitleBar() {
    return Padding(
      padding: const EdgeInsets.symmetric(
        horizontal: AppConstants.paddingM,
        vertical: AppConstants.paddingM,
      ),
      child: Row(
        children: [
          GestureDetector(
            onTap: () => Navigator.pop(context),
            child: Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.55),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.chevron_left_rounded,
                color: Color(0xFF1A2800),
                size: 26,
              ),
            ),
          ),
          Expanded(
            child: Text(
              'Event',
              style: AppTextStyles.namafitur,
              textAlign: TextAlign.center,
            ),
          ),
          const SizedBox(width: 38),
        ],
      ),
    );
  }
  

  Widget _buildSearchBar() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AppConstants.paddingL,
        AppConstants.paddingL,
        AppConstants.paddingL,
        AppConstants.paddingS,
      ),
      child: Row(
        children: [
          Expanded(
            child: SizedBox(
              height: 46,
              child: TextField(
                controller: _searchController,
                style: AppTextStyles.body.copyWith(color: AppColors.white),
                decoration: InputDecoration(
                  hintText: 'Cari event...',
                  hintStyle: AppTextStyles.body.copyWith(
                    color: AppColors.white.withOpacity(0.75),
                  ),
                  prefixIcon: const Icon(
                    Icons.search_rounded,
                    color: AppColors.white,
                    size: 20,
                  ),
                  filled: true,
                  fillColor: AppColors.secondary,
                  contentPadding: const EdgeInsets.symmetric(vertical: 14),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(AppConstants.radiusM),
                    borderSide: BorderSide.none,
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(AppConstants.radiusM),
                    borderSide: BorderSide.none,
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(AppConstants.radiusM),
                    borderSide: BorderSide.none,
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(width: 10),
          GestureDetector(
            onTap: _showFilterSheet,
            child: Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(
                color: AppColors.secondary,
                borderRadius: BorderRadius.circular(AppConstants.radiusM),
              ),
              child: const Icon(
                Icons.tune_rounded,
                color: AppColors.white,
                size: 22,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFilterChips() {
    return Padding(
      padding: const EdgeInsets.only(
        left: AppConstants.paddingL,
        bottom: AppConstants.paddingM,
      ),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: _filterTabs.map(_buildFilterChip).toList(),
        ),
      ),
    );
  }

  Widget _buildEventList() {
    final bottomPadding = MediaQuery.of(context).padding.bottom;

    if (_filteredEvents.isEmpty) return _buildEmptyState();
    
    return ListView.separated(
      padding: EdgeInsets.fromLTRB(
        AppConstants.paddingL,
        0,
        AppConstants.paddingL,
        AppConstants.paddingXL + bottomPadding + 20,
      ),
      itemCount: _filteredEvents.length,
      separatorBuilder: (_, __) => const SizedBox(height: 14),
      itemBuilder: (_, i) => _buildEventCard(_filteredEvents[i]),
    );
  }

  Widget _buildFilterChip(String label) {
    final isActive = _selectedFilter == label;
    return GestureDetector(
      onTap: () {
        setState(() {
          _selectedFilter = label;
          _applyFilter();
        });
      },
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        margin: const EdgeInsets.only(right: 8),
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
        decoration: BoxDecoration(
          color: isActive ? AppColors.primary : AppColors.white,
          borderRadius: BorderRadius.circular(AppConstants.radiusXL),
          border: Border.all(
            color: isActive ? AppColors.primary : const Color(0xFFE0E0E0),
          ),
        ),
        child: Text(
          label,
          style: AppTextStyles.body.copyWith(
            color: isActive ? const Color(0xFF1A2800) : AppColors.textSecondary,
            fontWeight: isActive ? FontWeight.w700 : FontWeight.w400,
          ),
        ),
      ),
    );
  }

  Widget _buildEventCard(EventModel event) {
    final fotoUrl = AppImageHelper.fotoEvent(event.fotoEvent);

    return Container(
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
                    width: 100,
                    height: 115,
                    child: fotoUrl.isNotEmpty
                        ? Image.network(
                            fotoUrl,
                            fit: BoxFit.cover,
                            errorBuilder: (_, __, ___) => _imagePlaceholder(),
                          )
                        : _imagePlaceholder(),
                  ),
                ),
                const SizedBox(width: 12),

                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        event.namaEvent ?? '-',
                        style: AppTextStyles.title.copyWith(fontSize: 15),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 4),

                      Text(
                        event.deskripsi ?? '',
                        style: AppTextStyles.caption.copyWith(fontSize: 12),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 8),

                      Row(
                        children: [
                          AvatarStack(
                            avatars: event.avatarList,
                          ),
                          const Spacer(),
                          const Icon(Icons.people_outline,
                              size: 14, color: AppColors.textSecondary),
                          const SizedBox(width: 4),
                          Text(
                            event.kuotaText,
                            style: AppTextStyles.small,
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
                      size: 13, color: AppColors.textSecondary),
                  const SizedBox(width: 4),
                  Flexible(
                    child: Text(
                      event.lokasi ?? '-',
                      style: AppTextStyles.small,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),

                  const SizedBox(width: 8),
                  _dot(),

                  const SizedBox(width: 8),
                  const Icon(Icons.calendar_today_outlined,
                      size: 13, color: AppColors.textSecondary),
                  const SizedBox(width: 4),
                  Text(event.tanggalFormatted, style: AppTextStyles.small),

                  const SizedBox(width: 8),
                  _dot(),

                  const SizedBox(width: 8),
                  const Icon(Icons.access_time_rounded,
                      size: 13, color: AppColors.textSecondary),
                  const SizedBox(width: 4),
                  Text(event.waktuFormatted, style: AppTextStyles.small),
                ],
              ),
            ),
          ),

          const SizedBox(height: 12),
          Padding(
            padding: const EdgeInsets.only(
              left: AppConstants.paddingM,
              right: AppConstants.paddingM,
              bottom: AppConstants.paddingM,
            ),
            child: Row(
              children: [
                Expanded(
                  child: SizedBox(
                    height: 40,
                    child: OutlinedButton(
                      onPressed: () => _onLihatDetail(event),
                      style: OutlinedButton.styleFrom(
                        side: const BorderSide(color: AppColors.secondary),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(AppConstants.radiusXL),
                        ),
                        padding: EdgeInsets.zero,
                      ),
                      child: Text(
                        'Lihat detail',
                        style: AppTextStyles.body.copyWith(
                          color: AppColors.secondary,
                          fontWeight: FontWeight.w600,
                          fontSize: 13,
                        ),
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 10),

                Expanded(
                  child: SizedBox(
                    height: 40,
                    child: ElevatedButton(
                      onPressed: () => _onDaftar(event),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.secondary,
                        elevation: 0,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(AppConstants.radiusXL),
                        ),
                        padding: EdgeInsets.zero,
                      ),
                      child: Text(
                        'Daftar sekarang',
                        style: AppTextStyles.body.copyWith(
                          color: AppColors.white,
                          fontWeight: FontWeight.w600,
                          fontSize: 13,
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _dot() => Padding(
        padding: const EdgeInsets.symmetric(horizontal: 4),
        child: Text('•',
            style: AppTextStyles.small.copyWith(color: AppColors.textSecondary)),
      );

  Widget _imagePlaceholder() {
    return Container(
      color: AppColors.primary.withOpacity(0.15),
      child: const Center(
        child: Icon(Icons.event_outlined, color: AppColors.secondary, size: 32),
      ),
    );
  }

  Widget _buildSkeleton() {
    return ListView.separated(
      padding: const EdgeInsets.all(AppConstants.paddingL),
      itemCount: 4,
      separatorBuilder: (_, __) => const SizedBox(height: 14),
      itemBuilder: (_, __) => const _SkeletonCard(),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.event_busy_outlined,
              size: 64, color: AppColors.textSecondary.withOpacity(0.4)),
          const SizedBox(height: 12),
          Text(
            _searchQuery.isNotEmpty
                ? 'Event "$_searchQuery" tidak ditemukan'
                : 'Belum ada event tersedia',
            style: AppTextStyles.body
                .copyWith(color: AppColors.textSecondary),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

  void _showFilterSheet() {
    showModalBottomSheet(
      context: context,
      backgroundColor: AppColors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(
          top: Radius.circular(AppConstants.radiusXL),
        ),
      ),
      builder: (_) => Padding(
        padding: const EdgeInsets.all(AppConstants.paddingL),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Filter Pembuat Event', style: AppTextStyles.title),
            const SizedBox(height: AppConstants.paddingM),

            ..._creatorFilters.map(
              (f) => ListTile(
                contentPadding: EdgeInsets.zero,
                leading: Radio<String>(
                  value: f,
                  groupValue: _selectedCreatorFilter,
                  activeColor: AppColors.secondary,
                  onChanged: (v) {
                    setState(() {
                      _selectedCreatorFilter = v!;
                      _applyFilter(); // ✅ filter dijalankan
                    });
                    Navigator.pop(context);
                  },
                ),
                title: Text(f, style: AppTextStyles.body),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _onLihatDetail(EventModel event) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => EventDetailPage(event: event),
      ),
    );
  }

  void _onDaftar(EventModel event) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => EventDaftarPage(event: event),
      ),
    );

  }
}
class _SkeletonCard extends StatefulWidget {
  const _SkeletonCard();

  @override
  State<_SkeletonCard> createState() => _SkeletonCardState();
}

class _SkeletonCardState extends State<_SkeletonCard>
    with SingleTickerProviderStateMixin {
  late AnimationController _ctrl;
  late Animation<double> _anim;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1000),
    )..repeat(reverse: true);
    _anim = Tween<double>(begin: 0.4, end: 0.9).animate(_ctrl);
  }

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  Widget _box(double w, double h, {double radius = 8}) {
    return FadeTransition(
      opacity: _anim,
      child: Container(
        width: w,
        height: h,
        decoration: BoxDecoration(
          color: const Color(0xFFE0E0E0),
          borderRadius: BorderRadius.circular(radius),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppConstants.paddingM),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppConstants.radiusL),
      ),
      child: Column(
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _box(100, 90, radius: 12),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _box(double.infinity, 14),
                    const SizedBox(height: 6),
                    _box(120, 12),
                    const SizedBox(height: 8),
                    _box(double.infinity, 11),
                    const SizedBox(height: 4),
                    _box(160, 11),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          _box(double.infinity, 12),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(child: _box(double.infinity, 36, radius: 20)),
              const SizedBox(width: 10),
              Expanded(child: _box(double.infinity, 36, radius: 20)),
            ],
          ),
        ],
      ),
    );
  }
}