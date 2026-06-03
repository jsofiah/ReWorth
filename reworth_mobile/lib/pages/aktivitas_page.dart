import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../utils/app_colors.dart';
import '../utils/app_constants.dart';
import '../utils/app_text_styles.dart';
import '../utils/app_image_helper.dart';
import 'package:flutter/services.dart';

enum AktivitasType { laporan, setor, pesanan, tukarPoin, event, reward }
enum AktivitasStatus { diproses, selesai, menunggu, berhasil, dikirim }
RealtimeChannel? _channel;

class AktivitasItem {
  final AktivitasType type;
  final String title;
  final String description;
  final AktivitasStatus status;
  final String? buktiFotoUrl;
  final String? kodeVoucher;
  final String? namaReward;
  final String? idReferensi;

  const AktivitasItem({
    required this.type,
    required this.title,
    required this.description,
    required this.status,
    this.buktiFotoUrl,
    this.kodeVoucher,
    this.namaReward,
    this.idReferensi,
  });
}

class _TypeTheme {
  final Color accent;
  final Color iconBg;
  final Color labelBg;
  const _TypeTheme({required this.accent, required this.iconBg, required this.labelBg});
}

const Map<AktivitasType, _TypeTheme> _kTypeTheme = {
  AktivitasType.laporan:   _TypeTheme(accent: Color(0xFF000000), iconBg: Color(0xAA74942B), labelBg: Color(0xFF74942B)),
  AktivitasType.setor:     _TypeTheme(accent: Color(0xFF000000), iconBg: Color(0xAAE6FF66), labelBg: Color(0xFFE6FF66)),
  AktivitasType.pesanan:   _TypeTheme(accent: Color(0xFF000000), iconBg: Color(0xAABBDE2D), labelBg: Color(0xFFBBDE2D)),
  AktivitasType.tukarPoin: _TypeTheme(accent: Color(0xFF000000), iconBg: Color(0xAAFFDB99), labelBg: Color(0xFFFFDB99)),
  AktivitasType.event:     _TypeTheme(accent: Color(0xFF000000), iconBg: Color(0xAAECC520), labelBg: Color(0xFFECC520)),
  AktivitasType.reward:    _TypeTheme(accent: Color(0xFF000000), iconBg: Color(0xAA74942B), labelBg: Color(0xFF74942B)),
};

class AktivitasPage extends StatefulWidget {
  const AktivitasPage({super.key});
  @override
  State<AktivitasPage> createState() => _AktivitasPageState();
}

class _AktivitasPageState extends State<AktivitasPage> {
  // ── State ──────────────────────────────────────────────
  String _selectedFilter = 'Semua';
  bool _isLoading = true;

  final TextEditingController _searchController = TextEditingController();
  String _searchQuery = '';

  final List<String> _filterTabs = ['Semua', 'Laporan', 'Setor', 'Pesanan', 'Reward', 'Event'];

  Map<String, int> _stats = {
    'Aktivitas': 0, 'Laporan': 0, 'Setor': 0,
    'Pesanan': 0,   'Poin': 0,    'Event': 0,
  };

  Map<String, List<AktivitasItem>> _grouped = {};

  // ── Lifecycle ──────────────────────────────────────────
  @override
  void initState() {
    super.initState();

    _fetchAktivitas();

    final user = Supabase.instance.client.auth.currentUser;

    if (user != null) {
      _channel = Supabase.instance.client
          .channel('riwayat_aktivitas_realtime')
          .onPostgresChanges(
            event: PostgresChangeEvent.all,
            schema: 'public',
            table: 'riwayat_aktivitas',
            filter: PostgresChangeFilter(
              type: PostgresChangeFilterType.eq,
              column: 'id_pengguna',
              value: user.id,
            ),
            callback: (payload) async {
              debugPrint('Realtime update: $payload');

              await _fetchAktivitas();
            },
          )
          .subscribe();
    }
  }

  @override
  void dispose() {
    if (_channel != null) {
      Supabase.instance.client.removeChannel(_channel!);
    }
    super.dispose();
  }

  // ── Fetch ──────────────────────────────────────────────
  Future<void> _fetchAktivitas() async {
    try {
      final user = Supabase.instance.client.auth.currentUser;
      if (user == null) { setState(() => _isLoading = false); return; }

      final response = await Supabase.instance.client
          .from('riwayat_aktivitas')
          .select()
          .eq('id_pengguna', user.id)
          .order('created_at', ascending: false);

      final Map<String, List<AktivitasItem>> grouped = {};
      final now = DateTime.now();
      final today     = DateTime(now.year, now.month, now.day);
      final yesterday = today.subtract(const Duration(days: 1));

      for (final item in response) {
        final createdAt = DateTime.parse(item['created_at']);
        final itemDate  = DateTime(createdAt.year, createdAt.month, createdAt.day);

        final groupLabel = itemDate == today     ? 'Hari Ini'
                        : itemDate == yesterday ? 'Kemarin'
                                                : 'Minggu Lalu';

        String buktiFoto = '';
        String? kodeVoucher;
        String? namaReward;

        if (item['jenis_aktivitas'] == 'lapor_sampah') {
          try {
            final laporan = await Supabase.instance.client
                .from('lapor_sampah')
                .select('id_laporan, bukti_penanganan')
                .eq('id_laporan', item['id_referensi'])
                .maybeSingle();
            if (laporan != null &&
                laporan['bukti_penanganan'] != null &&
                laporan['bukti_penanganan'].toString().isNotEmpty) {
              buktiFoto = AppImageHelper.fotoPenanganan(laporan['bukti_penanganan']);
            }
          } catch (e) { debugPrint('ERROR FOTO: $e'); }
        }
        
        // PERBAIKAN: Ambil kode voucher untuk tukar poin
        if (item['jenis_aktivitas'] == 'tukar_poin') {
          try {
            // Langkah 1: Cari di tabel tukar_poin berdasarkan id_referensi
            final tukarData = await Supabase.instance.client
                .from('tukar_poin')
                .select('id_reward')
                .eq('id_tukar', item['id_referensi'])
                .maybeSingle();
            
            if (tukarData != null && tukarData['id_reward'] != null) {
              // Langkah 2: Ambil data reward berdasarkan id_reward
              final rewardData = await Supabase.instance.client
                  .from('reward')
                  .select('kode_voucher, nama_reward')
                  .eq('id_reward', tukarData['id_reward'])
                  .maybeSingle();
              
              if (rewardData != null) {
                kodeVoucher = rewardData['kode_voucher'];
                namaReward = rewardData['nama_reward'];
                debugPrint('Found voucher: $kodeVoucher for reward: $namaReward');
              }
            }
          } catch (e) { 
            debugPrint('ERROR FETCH REWARD: $e'); 
          }
        }

        grouped.putIfAbsent(groupLabel, () => []);
        grouped[groupLabel]!.add(AktivitasItem(
          type:        _parseType(item['jenis_aktivitas']),
          title:       item['judul']    ?? '-',
          description: item['deskripsi'] ?? '-',
          status:      _parseStatus(item['status']),
          buktiFotoUrl: buktiFoto,
          kodeVoucher: kodeVoucher,
          namaReward: namaReward,
          idReferensi: item['id_referensi'],
        ));
      }

      setState(() {
        _grouped = grouped;
        _stats['Aktivitas'] = response.length;
        _stats['Laporan']   = response.where((e) => e['jenis_aktivitas'] == 'lapor_sampah').length;
        _stats['Setor']     = response.where((e) => e['jenis_aktivitas'] == 'setor_sampah').length;
        _stats['Pesanan']   = response.where((e) => e['jenis_aktivitas'] == 'pesanan').length;
        _stats['Poin']      = response.where((e) => e['jenis_aktivitas'] == 'tukar_poin').length;
        _stats['Event']     = response.where((e) => e['jenis_aktivitas'] == 'pendaftar_event').length;
        _isLoading = false;
      });
    } catch (e) {
      debugPrint('Error fetch aktivitas: $e');
      setState(() => _isLoading = false);
    }
  }

  Future<void> _updatePesananStatus(String idPesanan, String statusBaru) async {
    try {
      await Supabase.instance.client
          .from('pesanan')
          .update({'status': statusBaru})
          .eq('id_pesanan', idPesanan);
      
      // Update juga riwayat aktivitas
      await Supabase.instance.client
          .from('riwayat_aktivitas')
          .update({'status': statusBaru})
          .eq('id_referensi', idPesanan)
          .eq('jenis_aktivitas', 'pesanan');
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Pesanan telah selesai'),
            duration: Duration(seconds: 2),
            backgroundColor: Colors.green,
          ),
        );
        await _fetchAktivitas(); // Refresh data
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Gagal update: $e'),
            duration: Duration(seconds: 2),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  Future<String?> _getPesananStatus(String idPesanan) async {
    try {
      final result = await Supabase.instance.client
          .from('pesanan')
          .select('status')
          .eq('id_pesanan', idPesanan)
          .maybeSingle();
      
      return result?['status'];
    } catch (e) {
      debugPrint('Error get pesanan status: $e');
      return null;
    }
  }

  AktivitasType _parseType(String? v) {
    switch (v) {
      case 'lapor_sampah':     return AktivitasType.laporan;
      case 'setor_sampah':     return AktivitasType.setor;
      case 'pesanan':          return AktivitasType.pesanan;
      case 'tukar_poin':       return AktivitasType.tukarPoin;
      case 'pendaftar_event':  return AktivitasType.event;
      default:                 return AktivitasType.reward;
    }
  }

  AktivitasStatus _parseStatus(String? v) {
    switch (v) {
      case 'diproses': return AktivitasStatus.diproses;
      case 'selesai':  return AktivitasStatus.selesai;
      case 'menunggu': return AktivitasStatus.menunggu;
      case 'dikirim':  return AktivitasStatus.dikirim;
      default:         return AktivitasStatus.berhasil;
    }
  }

  // ── Filter + Search ────────────────────────────────────
  List<AktivitasItem> _filter(List<AktivitasItem> items) {
    // 1. filter tab
    List<AktivitasItem> result;
    switch (_selectedFilter) {
      case 'Laporan': result = items.where((e) => e.type == AktivitasType.laporan).toList();   break;
      case 'Setor':   result = items.where((e) => e.type == AktivitasType.setor).toList();     break;
      case 'Pesanan': result = items.where((e) => e.type == AktivitasType.pesanan).toList();   break;
      case 'Reward':  result = items.where((e) => e.type == AktivitasType.tukarPoin).toList(); break;
      case 'Event':   result = items.where((e) => e.type == AktivitasType.event).toList();     break;
      default:        result = List.from(items);
    }
    // 2. filter search
    if (_searchQuery.isNotEmpty) {
      result = result.where((e) =>
        e.title.toLowerCase().contains(_searchQuery) ||
        e.description.toLowerCase().contains(_searchQuery)
      ).toList();
    }
    return result;
  }

  // ── Helpers ────────────────────────────────────────────
  IconData _typeIcon(AktivitasType t) {
    switch (t) {
      case AktivitasType.laporan:   return Icons.campaign_outlined;
      case AktivitasType.setor:     return Icons.recycling_rounded;
      case AktivitasType.pesanan:   return Icons.storefront_outlined;
      case AktivitasType.tukarPoin: return Icons.card_giftcard_outlined;
      case AktivitasType.event:     return Icons.event_outlined;
      case AktivitasType.reward:    return Icons.star_outline_rounded;
    }
  }

  ({Color bg, Color text, String label}) _statusStyle(AktivitasStatus s) {
    switch (s) {
      case AktivitasStatus.diproses: return (bg: const Color(0xFFB3E7FF), text: const Color(0xFF016BB6), label: 'Diproses');
      case AktivitasStatus.selesai:  return (bg: const Color(0x99B3E5B3), text: const Color(0xFF008000), label: 'Selesai');
      case AktivitasStatus.menunggu: return (bg: const Color(0xFFF2DEB3), text: const Color(0xFFA8760B), label: 'Menunggu');
      case AktivitasStatus.dikirim:  return (bg: const Color(0xFFFFE0B3), text: const Color(0xFFD2691E), label: 'Dikirim');
      case AktivitasStatus.berhasil: return (bg: const Color(0x99B3E5B3), text: const Color(0xFF008000), label: 'Berhasil');
    }
  }

  // ── Build ──────────────────────────────────────────────
  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => FocusScope.of(context).unfocus(), // dismiss keyboard saat tap luar
      child: Scaffold(
        resizeToAvoidBottomInset: false, // keyboard tidak dorong layout
        backgroundColor: AppColors.background,
        body: Stack(
          children: [
            // Gradient background
            Container(
              height: MediaQuery.of(context).size.height * 0.46,
              decoration: const BoxDecoration(
                image: DecorationImage(
                  image: AssetImage('assets/gradient.png'),
                  fit: BoxFit.cover,
                ),
              ),
            ),

            Column(
              children: [
                SafeArea(
                  bottom: false,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(
                      AppConstants.paddingM, AppConstants.paddingS,
                      AppConstants.paddingM, 0,
                    ),
                    child: Column(
                      children: [
                        Text(
                          'Riwayat Aktivitas',
                          style: AppTextStyles.namafitur
                        ),
                        const SizedBox(height: 18),

                        // Search bar
                        Row(
                          children: [
                            Expanded(
                              child: Container(
                                height: 48,
                                decoration: BoxDecoration(
                                  color: const Color(0xFF1A3200),
                                  borderRadius: BorderRadius.circular(AppConstants.radiusM),
                                ),
                                child: TextField(
                                  controller: _searchController,
                                  cursorColor: Colors.white,
                                  style: const TextStyle(color: Colors.white, fontSize: 14),
                                  // onChanged sebagai primary handler — lebih reliable dari addListener
                                  onChanged: (val) => setState(() => _searchQuery = val.toLowerCase().trim()),
                                  decoration: InputDecoration(
                                    filled: true,
                                    fillColor: Colors.transparent,
                                    hintText: 'Cari aktivitas...',
                                    hintStyle: const TextStyle(color: Colors.white60, fontSize: 14),
                                    prefixIcon: const Icon(Icons.search, color: Colors.white70, size: 22),
                                    suffixIcon: _searchQuery.isNotEmpty
                                        ? GestureDetector(
                                            onTap: () => _searchController.clear(),
                                            child: const Icon(Icons.close, color: Colors.white54, size: 18),
                                          )
                                        : null,
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
                                    contentPadding: const EdgeInsets.symmetric(vertical: 14),
                                  ),
                                ),
                              ),
                            ),
                            const SizedBox(width: 10),
                            GestureDetector(
                              onTap: () {
                                showModalBottomSheet(
                                  context: context,
                                  backgroundColor: Colors.white,
                                  shape: const RoundedRectangleBorder(
                                    borderRadius: BorderRadius.vertical(
                                      top: Radius.circular(20),
                                    ),
                                  ),
                                  builder: (context) {
                                    return Padding(
                                      padding: const EdgeInsets.all(20),
                                      child: Column(
                                        mainAxisSize: MainAxisSize.min,
                                        children: _filterTabs.map((filter) {
                                          final isSelected =
                                              _selectedFilter == filter;

                                          return ListTile(
                                            shape: RoundedRectangleBorder(
                                              borderRadius:
                                                  BorderRadius.circular(12),
                                            ),

                                            tileColor: isSelected
                                                ? const Color(0xFFE8FF8A)
                                                : Colors.transparent,

                                            title: Text(
                                              filter,
                                              style: TextStyle(
                                                fontWeight: isSelected
                                                    ? FontWeight.bold
                                                    : FontWeight.normal,
                                              ),
                                            ),

                                            trailing: isSelected
                                                ? const Icon(
                                                    Icons.check_circle,
                                                    color: Colors.green,
                                                  )
                                                : null,

                                            onTap: () {
                                              setState(() {
                                                _selectedFilter = filter;
                                              });

                                              Navigator.pop(context);
                                            },
                                          );
                                        }).toList(),
                                      ),
                                    );
                                  },
                                );
                              },

                              child: Container(
                                width: 48,
                                height: 48,
                                decoration: BoxDecoration(
                                  color: const Color(0xFF1A3200),
                                  borderRadius:
                                      BorderRadius.circular(
                                    AppConstants.radiusM,
                                  ),
                                ),

                                child: const Icon(
                                  Icons.filter_alt_outlined,
                                  color: Colors.white,
                                  size: 22,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 20),
                      ],
                    ),
                  ),
                ),

                const SizedBox(height: 20),

                Expanded(
                  child: Stack(
                    clipBehavior: Clip.none,
                    children: [
                      Positioned(
                        top: 70, left: 0, right: 0, bottom: 0,
                        child: Container(color: AppColors.background),
                      ),
                      Column(
                        children: [
                          Transform.translate(
                            offset: const Offset(0, -20),
                            child: _buildStatsCard(),
                          ),
                          const SizedBox(height: 4),
                          _buildFilterChips(),
                          Expanded(
                            child: Container(
                              color: AppColors.background,
                              child: _isLoading
                                  ? const Center(child: CircularProgressIndicator())
                                  : _buildList(),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  // ── List builder with empty state ──────────────────────
  Widget _buildList() {
    final List<Widget> widgets = [];

    for (final entry in _grouped.entries) {
      final filtered = _filter(entry.value);

      if (filtered.isEmpty) continue;

      widgets.add(_sectionLabel(entry.key));
      widgets.addAll(filtered.map(_buildCard));
    }

    return RefreshIndicator(
      color: AppColors.secondary,
      onRefresh: _fetchAktivitas,
      child: widgets.isEmpty
          ? ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              children: [
                SizedBox(
                  height: MediaQuery.of(context).size.height * 0.5,
                  child: Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          Icons.search_off_rounded,
                          size: 56,
                          color: AppColors.textHint,
                        ),

                        const SizedBox(height: 12),

                        Text(
                          _searchQuery.isNotEmpty
                              ? 'Tidak ada hasil untuk "$_searchQuery"'
                              : 'Belum ada aktivitas',
                          textAlign: TextAlign.center,
                          style: AppTextStyles.body.copyWith(
                            color: AppColors.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            )
          : ListView(
              physics: const AlwaysScrollableScrollPhysics(
                parent: BouncingScrollPhysics(),
              ),
              padding: EdgeInsets.only(
                left: AppConstants.paddingM,
                right: AppConstants.paddingM,
                bottom: MediaQuery.of(context).padding.bottom + 16,
              ),
              children: widgets,
            ),
    );
  }

  // ── Stats Card ─────────────────────────────────────────
  Widget _buildStatsCard() {
    final entries = _stats.entries.toList();
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: AppConstants.paddingM),
      padding: const EdgeInsets.all(AppConstants.paddingM),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(AppConstants.radiusL),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.09), blurRadius: 20, offset: const Offset(0, 8))],
      ),
      child: Column(
        children: [
          Row(children: List.generate(3, (i) => Expanded(child: Padding(
            padding: EdgeInsets.only(right: i < 2 ? 10 : 0),
            child: _statBox(entries[i].key, entries[i].value),
          )))),
          const SizedBox(height: 10),
          Row(children: List.generate(3, (i) => Expanded(child: Padding(
            padding: EdgeInsets.only(right: i < 2 ? 10 : 0),
            child: _statBox(entries[i + 3].key, entries[i + 3].value),
          )))),
        ],
      ),
    );
  }

  Widget _statBox(String label, int value) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 14),
      decoration: BoxDecoration(color: const Color(0xFFE8FF8A), borderRadius: BorderRadius.circular(14)),
      child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        Text('$value', style: AppTextStyles.title.copyWith(fontSize: 22, fontWeight: FontWeight.w800)),
        const SizedBox(height: 4),
        Text(label, style: AppTextStyles.small.copyWith(fontWeight: FontWeight.w600, color: Colors.black87)),
      ]),
    );
  }

  // ── Filter Chips ───────────────────────────────────────
  Widget _buildFilterChips() {
    return Padding(
      padding: const EdgeInsets.only(left: AppConstants.paddingL, bottom: AppConstants.paddingM),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(children: _filterTabs.map(_buildFilterChip).toList()),
      ),
    );
  }

  Widget _buildFilterChip(String label) {
    final isActive = _selectedFilter == label;
    return GestureDetector(
      behavior: HitTestBehavior.opaque, // pastikan tap selalu ke chip, tidak ke outer GestureDetector
      onTap: () => setState(() => _selectedFilter = label),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        margin: const EdgeInsets.only(right: 8),
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
        decoration: BoxDecoration(
          color: isActive ? AppColors.primary : AppColors.white,
          borderRadius: BorderRadius.circular(AppConstants.radiusXL),
          border: Border.all(color: isActive ? AppColors.primary : const Color(0xFFE0E0E0)),
        ),
        child: Text(label, style: AppTextStyles.body.copyWith(
          color: isActive ? const Color(0xFF1A2800) : AppColors.textSecondary,
          fontWeight: isActive ? FontWeight.w700 : FontWeight.w400,
        )),
      ),
    );
  }

  // ── Section Label ──────────────────────────────────────
  Widget _sectionLabel(String label) {
    return Padding(
      padding: const EdgeInsets.only(top: AppConstants.paddingS, bottom: AppConstants.paddingS),
      child: Text(label, style: AppTextStyles.title.copyWith(fontWeight: FontWeight.w700)),
    );
  }

  // ── Activity Card ──────────────────────────────────────
  Widget _buildCard(AktivitasItem item) {
    final theme = _kTypeTheme[item.type]!;
    final st = _statusStyle(item.status);

    return Container(
      margin: const EdgeInsets.only(bottom: AppConstants.paddingS + 2),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppConstants.radiusM),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8, offset: const Offset(0, 2))],
      ),
      child: IntrinsicHeight(
        child: Row(
          children: [
            Container(
              width: 5,
              decoration: BoxDecoration(
                color: theme.labelBg,
                borderRadius: const BorderRadius.only(
                  topLeft: Radius.circular(AppConstants.radiusM),
                  bottomLeft: Radius.circular(AppConstants.radiusM),
                ),
              ),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(AppConstants.paddingM),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(
                          width: 44, height: 44,
                          decoration: BoxDecoration(color: theme.iconBg, borderRadius: BorderRadius.circular(AppConstants.radiusS)),
                          child: Icon(_typeIcon(item.type), color: Colors.black, size: 24),
                        ),
                        const SizedBox(width: AppConstants.paddingS),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                decoration: BoxDecoration(color: theme.labelBg, borderRadius: BorderRadius.circular(AppConstants.radiusS)),
                                child: Text(item.title, style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w700, color: theme.accent, fontSize: 13)),
                              ),
                              const SizedBox(height: 6),
                              Text(item.description, style: AppTextStyles.caption.copyWith(height: 1.45)),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: AppConstants.paddingS),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                          decoration: BoxDecoration(color: st.bg, borderRadius: BorderRadius.circular(AppConstants.radiusS)),
                          child: Text(st.label, style: AppTextStyles.small.copyWith(color: st.text, fontWeight: FontWeight.w600)),
                        ),
                        const SizedBox(width: AppConstants.paddingS),
                        InkWell(
                          borderRadius: BorderRadius.circular(AppConstants.radiusS),
                          onTap: () => _showDetailDialog(context, item, theme),
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 5),
                            decoration: BoxDecoration(color: const Color(0xFFC9F057), borderRadius: BorderRadius.circular(AppConstants.radiusS)),
                            child: Text('Detail', style: AppTextStyles.small.copyWith(color: Colors.black, fontWeight: FontWeight.w600)),
                          ),
                        ),
                      ],
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

  // ── Detail Dialog ──────────────────────────────────────
  void _showDetailDialog(BuildContext context, AktivitasItem item, _TypeTheme theme) {
    final st = _statusStyle(item.status);
    final isLaporan = item.type == AktivitasType.laporan;
    final isTukarPoin = item.type == AktivitasType.tukarPoin;
    final isPesanan = item.type == AktivitasType.pesanan; // TAMBAHKAN
    
    // State untuk tombol loading
    bool isUpdating = false;
    
    showDialog(
      context: context,
      barrierDismissible: true,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return Dialog(
              backgroundColor: Colors.transparent,
              insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 40),
              child: Container(
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(AppConstants.radiusL)),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      padding: const EdgeInsets.fromLTRB(20, 16, 12, 16),
                      decoration: BoxDecoration(
                        color: theme.labelBg.withValues(alpha: 0.6),
                        borderRadius: const BorderRadius.only(
                          topLeft: Radius.circular(AppConstants.radiusL),
                          topRight: Radius.circular(AppConstants.radiusL),
                        ),
                      ),
                      child: Row(
                        children: [
                          Container(
                            width: 38, height: 38,
                            decoration: BoxDecoration(color: theme.iconBg, borderRadius: BorderRadius.circular(AppConstants.radiusS)),
                            child: Icon(_typeIcon(item.type), color: theme.accent, size: 20),
                          ),
                          const SizedBox(width: 10),
                          Expanded(child: Text(item.title, style: AppTextStyles.title.copyWith(fontWeight: FontWeight.w700, color: theme.accent))),
                          GestureDetector(
                            onTap: () => Navigator.pop(ctx),
                            child: Container(
                              width: 32, height: 32,
                              decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.06), shape: BoxShape.circle),
                              child: const Icon(Icons.close, size: 18),
                            ),
                          ),
                        ],
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.all(20),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                            decoration: BoxDecoration(color: st.bg, borderRadius: BorderRadius.circular(AppConstants.radiusS)),
                            child: Text(st.label, style: AppTextStyles.small.copyWith(color: st.text, fontWeight: FontWeight.w700)),
                          ),
                          const SizedBox(height: 12),
                          Text(item.description, style: AppTextStyles.body.copyWith(color: AppColors.textSecondary, height: 1.55)),
                          
                          // Kode voucher untuk tukar poin
                          if (isTukarPoin && item.kodeVoucher != null && item.kodeVoucher!.isNotEmpty) ...[
                            const SizedBox(height: 20),
                            Container(
                              padding: const EdgeInsets.all(16),
                              decoration: BoxDecoration(
                                color: const Color(0xFFE8FF8A).withValues(alpha: 0.3),
                                borderRadius: BorderRadius.circular(AppConstants.radiusM),
                                border: Border.all(color: AppColors.secondary, width: 1.5),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      const Icon(Icons.card_giftcard_outlined, color: AppColors.secondary, size: 20),
                                      const SizedBox(width: 8),
                                      Text(
                                        'Kode Voucher',
                                        style: AppTextStyles.body.copyWith(
                                          fontWeight: FontWeight.w700,
                                          color: AppColors.secondary,
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 12),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                                    decoration: BoxDecoration(
                                      color: Colors.white,
                                      borderRadius: BorderRadius.circular(AppConstants.radiusS),
                                      border: Border.all(color: AppColors.secondary.withValues(alpha: 0.3)),
                                    ),
                                    child: Row(
                                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                      children: [
                                        Expanded(
                                          child: Text(
                                            item.kodeVoucher!,
                                            style: AppTextStyles.title.copyWith(
                                              fontSize: 16,
                                              letterSpacing: 1.5,
                                              color: AppColors.secondary,
                                            ),
                                          ),
                                        ),
                                        GestureDetector(
                                          onTap: () {
                                            Clipboard.setData(ClipboardData(text: item.kodeVoucher!));
                                            ScaffoldMessenger.of(ctx).showSnackBar(
                                              const SnackBar(
                                                content: Text('Kode voucher disalin'),
                                                duration: Duration(seconds: 2),
                                                backgroundColor: Colors.green,
                                              ),
                                            );
                                          },
                                          child: Container(
                                            padding: const EdgeInsets.all(8),
                                            decoration: BoxDecoration(
                                              color: AppColors.secondary,
                                              borderRadius: BorderRadius.circular(AppConstants.radiusS),
                                            ),
                                            child: const Icon(Icons.copy, size: 18, color: Colors.white),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                  const SizedBox(height: 8),
                                  Text(
                                    'Tunjukkan kode ini saat menukarkan reward',
                                    style: AppTextStyles.small.copyWith(color: AppColors.textSecondary, fontSize: 10),
                                  ),
                                ],
                              ),
                            ),
                          ],
                          
                          // Bukti foto untuk laporan
                          if (isLaporan) ...[
                            const SizedBox(height: 16),
                            Text('Bukti Foto Laporan', style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w700)),
                            const SizedBox(height: 8),
                            item.buktiFotoUrl != null && item.buktiFotoUrl!.isNotEmpty
                                ? ClipRRect(
                                    borderRadius: BorderRadius.circular(AppConstants.radiusM),
                                    child: Image.network(
                                      item.buktiFotoUrl!,
                                      width: double.infinity, height: 180, fit: BoxFit.cover,
                                      loadingBuilder: (ctx, child, p) => p == null ? child : _fotoPlaceholder(isError: false, isLoading: true),
                                      errorBuilder: (ctx, e, s) => _fotoPlaceholder(isError: true, isLoading: false),
                                    ),
                                  )
                                : _fotoPlaceholder(isError: false, isLoading: false),
                          ],
                          
                          const SizedBox(height: 20),
                          
                          // Tampilkan tombol Selesai untuk pesanan dengan status tertentu
                          if (isPesanan && item.idReferensi != null) ...[
                            FutureBuilder<String?>(
                              future: _getPesananStatus(item.idReferensi!),
                              builder: (context, snapshot) {
                                final pesananStatus = snapshot.data;
                                
                                // Tampilkan tombol Selesai jika status 'dikirim' atau 'diproses'
                                if (pesananStatus == 'dikirim' || pesananStatus == 'diproses') {
                                  return Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      // KETERANGAN SEDERHANA
                                      Padding(
                                        padding: const EdgeInsets.only(bottom: 8),
                                        child: Text(
                                          'Klik "Selesai" saat paket anda sudah sampai',
                                          style: AppTextStyles.small.copyWith(
                                            color: Colors.orange.shade700,
                                            fontWeight: FontWeight.w500,
                                          ),
                                        ),
                                      ),
                                      // Tombol Selesai
                                      SizedBox(
                                        width: double.infinity,
                                        height: 46,
                                        child: ElevatedButton(
                                          onPressed: isUpdating
                                              ? null
                                              : () async {
                                                  setDialogState(() => isUpdating = true);
                                                  await _updatePesananStatus(item.idReferensi!, 'selesai');
                                                  if (mounted) {
                                                    Navigator.pop(ctx);
                                                  }
                                                },
                                          style: ElevatedButton.styleFrom(
                                            backgroundColor: Colors.green,
                                            elevation: 0,
                                            shape: RoundedRectangleBorder(
                                              borderRadius: BorderRadius.circular(AppConstants.radiusXL),
                                            ),
                                          ),
                                          child: isUpdating
                                              ? const SizedBox(
                                                  width: 20,
                                                  height: 20,
                                                  child: CircularProgressIndicator(
                                                    strokeWidth: 2,
                                                    color: Colors.white,
                                                  ),
                                                )
                                              : Row(
                                                  mainAxisAlignment: MainAxisAlignment.center,
                                                  children: [
                                                    const Icon(Icons.check_circle_outline, size: 20, color: Colors.white),
                                                    const SizedBox(width: 8),
                                                    Text(
                                                      'Selesai',
                                                      style: AppTextStyles.body.copyWith(
                                                        fontWeight: FontWeight.w700,
                                                        color: Colors.white,
                                                      ),
                                                    ),
                                                  ],
                                                ),
                                        ),
                                      ),
                                    ],
                                  );
                                }
                                return const SizedBox.shrink();
                              },
                            ),
                            const SizedBox(height: 12),
                          ],
                          
                          // Tombol Tutup
                          SizedBox(
                            width: double.infinity,
                            height: 46,
                            child: ElevatedButton(
                              onPressed: () => Navigator.pop(ctx),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppColors.primary,
                                elevation: 0,
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(AppConstants.radiusXL),
                                ),
                              ),
                              child: Text(
                                'Tutup',
                                style: AppTextStyles.body.copyWith(
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.secondary,
                                ),
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
          },
        );
      },
    );
  }

  Widget _fotoPlaceholder({required bool isError, required bool isLoading}) {
    return Container(
      width: double.infinity, height: 160,
      decoration: BoxDecoration(
        color: const Color(0xFFE8FF8A).withValues(alpha: 0.5),
        borderRadius: BorderRadius.circular(AppConstants.radiusM),
        border: Border.all(color: AppColors.primary.withValues(alpha: 0.4), width: 1.5),
      ),
      child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        if (isLoading)
          const CircularProgressIndicator(color: Color(0xFF74942B))
        else
          Icon(isError ? Icons.broken_image_outlined : Icons.add_photo_alternate_outlined, color: const Color(0xFF74942B), size: 40),
        const SizedBox(height: 8),
        if (!isLoading)
          Text(isError ? 'Gagal memuat foto' : 'Belum ada bukti foto',
              style: AppTextStyles.caption.copyWith(color: const Color(0xFF74942B))),
      ]),
    );
  }
}
