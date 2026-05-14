import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:intl/intl.dart';
import '../utils/app_colors.dart';
import '../utils/app_constants.dart';
import '../utils/app_text_styles.dart';
import '../utils/app_image_helper.dart';

// ─────────────────────────────────────────────
// MODEL
// ─────────────────────────────────────────────
class NotifikasiModel {
  final String id;
  final String judul;
  final String deskripsi;
  final DateTime createdAt;
  bool isRead;

  NotifikasiModel({
    required this.id,
    required this.judul,
    required this.deskripsi,
    required this.createdAt,
    required this.isRead,
  });

  factory NotifikasiModel.fromMap(Map<String, dynamic> map) {
    return NotifikasiModel(
      id: map['id_notifikasi'] as String,
      judul: map['judul'] as String,
      deskripsi: map['deskripsi'] as String,
      createdAt: DateTime.parse(map['created_at'] as String).toLocal(),
      isRead: map['is_read'] as bool,
    );
  }
}

// ─────────────────────────────────────────────
// HELPER – icon & warna berdasarkan judul
// ─────────────────────────────────────────────
class _NotifMeta {
  final IconData icon;
  final Color bgColor;
  final Color iconColor;

  const _NotifMeta({
    required this.icon,
    required this.bgColor,
    required this.iconColor,
  });
}

_NotifMeta _getMeta(String judul) {
  final j = judul.toLowerCase();
  if (j.contains('saldo')) {
  return const _NotifMeta(
    icon: Icons.recycling_rounded,
    bgColor: Color(0xFFE6FF66),
    iconColor: Color(0xFF1A2800),
  );
} else if (j.contains('poin')) {
  return const _NotifMeta(
    icon: Icons.card_giftcard_outlined,
    bgColor: Color(0xFFFFDB99),
    iconColor: Color(0xFF1A2800),
    );
  } else if (j.contains('laporan') ||
      j.contains('diverifikasi') ||
      j.contains('ditangani')) {
    return const _NotifMeta(
      icon: Icons.campaign_rounded,
      bgColor: Color(0xFFDFF2B1),
      iconColor: Color(0xFF74942B),
    );
  } else if (j.contains('pesanan') ||
      j.contains('dikirim') ||
      j.contains('diproses')) {
    return const _NotifMeta(
      icon: Icons.storefront_rounded,
      bgColor: Color(0xFFDFF2B1),
      iconColor: Color(0xFF74942B),
    );
  } else if (j.contains('penukaran') || j.contains('hadiah')) {
    return const _NotifMeta(
      icon: Icons.card_giftcard_rounded,
      bgColor: Color(0xFFFFEDD5),
      iconColor: Color(0xFFE88C30),
    );
  } else if (j.contains('event')) {
    return const _NotifMeta(
      icon: Icons.event_rounded,
      bgColor: Color(0xFFFFF8D6),
      iconColor: Color(0xFFECC520),
    );
  } else if (j.contains('setoran')) {
    return const _NotifMeta(
      icon: Icons.recycling_rounded,
      bgColor: Color(0xFFDFF2B1),
      iconColor: Color(0xFF74942B),
    );
  }
  return const _NotifMeta(
    icon: Icons.notifications_rounded,
    bgColor: Color(0xFFDFF2B1),
    iconColor: Color(0xFF74942B),
  );
}

// ─────────────────────────────────────────────
// HALAMAN NOTIFIKASI
// ─────────────────────────────────────────────
class NotificationPage extends StatefulWidget {
  const NotificationPage({super.key});

  @override
  State<NotificationPage> createState() => _NotificationPageState();
}

class _NotificationPageState extends State<NotificationPage> {
  final _supabase = Supabase.instance.client;

  List<NotifikasiModel> _notifikasi = [];
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _fetchNotifikasi();
  }

  // ── Fetch ──────────────────────────────────
  Future<void> _fetchNotifikasi() async {
    try {
      setState(() {
        _isLoading = true;
        _error = null;
      });

      final userId = _supabase.auth.currentUser?.id;
      if (userId == null) throw Exception('User tidak ditemukan');

      final data = await _supabase
          .from('notifikasi')
          .select()
          .eq('id_pengguna', userId)
          .order('created_at', ascending: false);

      setState(() {
        _notifikasi = (data as List)
            .map((e) => NotifikasiModel.fromMap(e as Map<String, dynamic>))
            .toList();
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _isLoading = false;
      });
    }
  }

  // ── Tandai semua sudah dibaca ───────────────
  Future<void> _tandaiSemuaDibaca() async {
    final userId = _supabase.auth.currentUser?.id;
    if (userId == null) return;

    await _supabase
        .from('notifikasi')
        .update({'is_read': true})
        .eq('id_pengguna', userId)
        .eq('is_read', false);

    setState(() {
      for (final n in _notifikasi) {
        n.isRead = true;
      }
    });

    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          'Semua notifikasi telah ditandai dibaca',
          style: AppTextStyles.body.copyWith(color: AppColors.white),
        ),
        backgroundColor: AppColors.secondary,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppConstants.radiusS),
        ),
      ),
    );
  }

  // ── Hapus semua ────────────────────────────
  Future<void> _hapusSemua() async {
    final konfirmasi = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppConstants.radiusM),
        ),
        title: Text('Hapus Semua', style: AppTextStyles.title),
        content: Text(
          'Apakah kamu yakin ingin menghapus semua notifikasi?',
          style: AppTextStyles.body,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text(
              'Batal',
              style: AppTextStyles.body.copyWith(color: AppColors.textSecondary),
            ),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(
              'Hapus',
              style: AppTextStyles.body.copyWith(color: Colors.red),
            ),
          ),
        ],
      ),
    );

    if (konfirmasi != true) return;

    final userId = _supabase.auth.currentUser?.id;
    if (userId == null) return;

    await _supabase
        .from('notifikasi')
        .delete()
        .eq('id_pengguna', userId);

    setState(() => _notifikasi.clear());
  }

  // ── Hapus satu notifikasi ──────────────────
  Future<void> _hapusSatu(String id) async {
    await _supabase
        .from('notifikasi')
        .delete()
        .eq('id_notifikasi', id);

    setState(() => _notifikasi.removeWhere((n) => n.id == id));
  }

  // ── Tandai satu sudah dibaca ───────────────
  Future<void> _tandaiSatuDibaca(NotifikasiModel notif) async {
    if (notif.isRead) return;

    await _supabase
        .from('notifikasi')
        .update({'is_read': true})
        .eq('id_notifikasi', notif.id);

    setState(() => notif.isRead = true);
  }

  // ── Grouping berdasarkan tanggal ───────────
  Map<String, List<NotifikasiModel>> _groupByDate() {
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final yesterday = today.subtract(const Duration(days: 1));
    final Map<String, List<NotifikasiModel>> grouped = {};

    for (final notif in _notifikasi) {
      final date = DateTime(
        notif.createdAt.year,
        notif.createdAt.month,
        notif.createdAt.day,
      );

      String key;
      if (date == today) {
        key = 'Hari Ini';
      } else if (date == yesterday) {
        key = 'Kemarin';
      } else {
        const bulan = [
          '',
          'Januari',
          'Februari',
          'Maret',
          'April',
          'Mei',
          'Juni',
          'Juli',
          'Agustus',
          'September',
          'Oktober',
          'November',
          'Desember',
        ];

        key =
            '${notif.createdAt.day} '
            '${bulan[notif.createdAt.month]} '
            '${notif.createdAt.year}';
      }

      grouped.putIfAbsent(key, () => []).add(notif);
    }

    return grouped;
  }

  // ─────────────────────────────────────────
  // BUILD
  // ─────────────────────────────────────────
  @override
Widget build(BuildContext context) {
  final topPad = MediaQuery.of(context).padding.top;

  return Scaffold(
    backgroundColor: const Color(0xFFF2F2F2),
    body: Stack(
      children: [
        // Background hijau atas
        Positioned(
          top: 0,
          left: 0,
          right: 0,
          height: topPad + 120,
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
            children: [
              _buildHeader(),

              // Background putih cekung
              Expanded(
                child: Container(
                  decoration: const BoxDecoration(
                    color: AppColors.white,
                    borderRadius: BorderRadius.only(
                      topLeft: Radius.circular(
                        AppConstants.radiusXL,
                      ),
                      topRight: Radius.circular(
                        AppConstants.radiusXL,
                      ),
                    ),
                  ),
                  child: _buildBody(),
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

  // ── Header dengan gradient ─────────────────
  Widget _buildHeader() {
  return Padding(
    padding: const EdgeInsets.symmetric(
      horizontal: AppConstants.paddingM,
      vertical: AppConstants.paddingM,
    ),
    child: Row(
      children: [
        // Tombol back
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

        // Judul
        Expanded(
          child: Text(
            'Notifikasi',
            style: AppTextStyles.namafitur,
            textAlign: TextAlign.center,
          ),
        ),

        // Menu titik 3
        PopupMenuButton<String>(
          padding: EdgeInsets.zero,
          icon: const Icon(Icons.more_vert_rounded),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(
              AppConstants.radiusM,
            ),
          ),
          elevation: 4,
          offset: const Offset(0, 2),
          onSelected: (value) {
            if (value == 'read') _tandaiSemuaDibaca();
            if (value == 'delete') _hapusSemua();
          },
          itemBuilder: (_) => [
            PopupMenuItem(
              value: 'read',
              height: 38,
              child: Text(
                'Tandai sudah dibaca',
                style: AppTextStyles.body,
              ),
            ),

          PopupMenuDivider(
            height: 1,
            color: Colors.black.withOpacity(0.08),
          ),

            PopupMenuItem(
              value: 'delete',
              height: 38,
              child: Text(
                'Hapus semua',
                style: AppTextStyles.body.copyWith(
                  color: Colors.red,
                ),
              ),
            ),
          ],
        ),
      ],
    ),
  );
}

  // ── Body ───────────────────────────────────
  Widget _buildBody() {
    if (_isLoading) {
      return const Center(
        child: CircularProgressIndicator(color: AppColors.primary),
      );
    }

    if (_error != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline_rounded, size: 48, color: AppColors.textHint),
            const SizedBox(height: AppConstants.paddingM),
            Text('Gagal memuat notifikasi', style: AppTextStyles.body),
            const SizedBox(height: AppConstants.paddingS),
            ElevatedButton(
              onPressed: _fetchNotifikasi,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(AppConstants.radiusM),
                ),
              ),
              child: Text('Coba lagi', style: AppTextStyles.buttonLabel),
            ),
          ],
        ),
      );
    }

    if (_notifikasi.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.notifications_off_outlined,
                size: 64, color: AppColors.textHint),
            const SizedBox(height: AppConstants.paddingM),
            Text('Tidak ada notifikasi', style: AppTextStyles.body),
          ],
        ),
      );
    }

    final grouped = _groupByDate();

    return RefreshIndicator(
      color: AppColors.primary,
      onRefresh: _fetchNotifikasi,
      child: ListView(
        padding: const EdgeInsets.symmetric(
          horizontal: AppConstants.paddingM,
          vertical: AppConstants.paddingS,
        ),
        children: grouped.entries.expand((entry) {
          return [
            _buildDateHeader(entry.key),
            ...entry.value.map(_buildNotifCard),
            const SizedBox(height: AppConstants.paddingS),
          ];
        }).toList(),
      ),
    );
  }

  // ── Date header ────────────────────────────
  Widget _buildDateHeader(String label) {
    return Padding(
      padding: const EdgeInsets.only(
        top: AppConstants.paddingM,
        bottom: AppConstants.paddingS,
      ),
      child: Text(label, style: AppTextStyles.captionBold),
    );
  }

  // ── Kartu notifikasi ───────────────────────
  Widget _buildNotifCard(NotifikasiModel notif) {
    final meta = _getMeta(notif.judul);
    final timeStr = DateFormat('HH:mm').format(notif.createdAt);

    return Dismissible(
      key: Key(notif.id),
      direction: DismissDirection.endToStart,
      background: _buildSwipeBackground(),
      onDismissed: (_) => _hapusSatu(notif.id),
      child: GestureDetector(
        onTap: () => _tandaiSatuDibaca(notif),
        child: Container(
          margin: const EdgeInsets.only(bottom: AppConstants.paddingS),
          padding: const EdgeInsets.all(AppConstants.paddingM),
          decoration: BoxDecoration(
            color: notif.isRead ? AppColors.white : const Color(0xFFF5FDD6),
            borderRadius: BorderRadius.circular(AppConstants.radiusM),
            border: Border.all(
              color: notif.isRead
                  ? AppColors.inputBorder.withOpacity(0.35)
                  : AppColors.primary.withOpacity(0.18),
              width: 1,
            ),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.04),
                blurRadius: 8,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Icon container
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: meta.bgColor,
                  borderRadius: BorderRadius.circular(AppConstants.radiusS),
                ),
                child: Icon(meta.icon, color: meta.iconColor, size: 26),
              ),
              const SizedBox(width: AppConstants.paddingM),
              // Konten
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      notif.judul,
                      style: notif.isRead
                          ? AppTextStyles.body
                          : AppTextStyles.captionBold,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 2),
                    Text(
                      notif.deskripsi,
                      style: AppTextStyles.caption,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 4),
                    Text(timeStr, style: AppTextStyles.small),
                  ],
                ),
              ),
              // Indikator belum dibaca
              if (!notif.isRead)
                Padding(
                  padding: const EdgeInsets.only(left: 6, top: 2),
                  child: Container(
                    width: 10,
                    height: 10,
                    decoration: const BoxDecoration(
                      color: AppColors.primary,
                      shape: BoxShape.circle,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }

  // ── Background swipe hapus ─────────────────
  Widget _buildSwipeBackground() {
    return Container(
      margin: const EdgeInsets.only(bottom: AppConstants.paddingS),
      alignment: Alignment.centerRight,
      padding: const EdgeInsets.only(right: AppConstants.paddingL),
      decoration: BoxDecoration(
        color: Colors.red,
        borderRadius: BorderRadius.circular(AppConstants.radiusM),
      ),
      child: const Icon(
        Icons.delete_outline_rounded,
        color: AppColors.white,
        size: 28,
      ),
    );
  }
}