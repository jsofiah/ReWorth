import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../utils/app_colors.dart';
import '../utils/app_constants.dart';
import '../utils/app_text_styles.dart';

// ─────────────────────────────────────────────
// MODEL
// ─────────────────────────────────────────────
class RewardModel {
  final String id;
  final String namaReward;
  final String fotoReward;
  final int poinDibutuhkan;
  final int stok;
  final String kodeVoucher;

  RewardModel({
    required this.id,
    required this.namaReward,
    required this.fotoReward,
    required this.poinDibutuhkan,
    required this.stok,
    required this.kodeVoucher,
  });

  factory RewardModel.fromMap(Map<String, dynamic> map) {
    return RewardModel(
      id: map['id_reward'] as String,
      namaReward: map['nama_reward'] as String,
      fotoReward: map['foto_reward'] as String,
      poinDibutuhkan: (map['poin_dibutuhkan'] as num).toInt(),
      stok: (map['stok'] as num).toInt(),
      kodeVoucher: map['kode_voucher'] as String,
    );
  }

  bool get isPulsa => namaReward.toLowerCase().contains('pulsa');
  bool get isToken => namaReward.toLowerCase().contains('token');
}

// ─────────────────────────────────────────────
// HALAMAN REWARD
// ─────────────────────────────────────────────
class RewardPage extends StatefulWidget {
  const RewardPage({super.key});

  @override
  State<RewardPage> createState() => _RewardPageState();
}

class _RewardPageState extends State<RewardPage> {
  final _supabase = Supabase.instance.client;

  List<RewardModel> _rewards = [];
  String _filter = 'Semua';
  bool _isLoading = true;
  String? _error;

  // Data user
  String _namaUser = '';
  String _nomorHp = '';
  int _poinUser = 0;
  int _poinMasuk = 0;
  int _poinDitukar = 0;
  String? _fotoUser;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    try {
      setState(() {
        _isLoading = true;
        _error = null;
      });

      final userId = _supabase.auth.currentUser?.id;
      if (userId == null) throw Exception('User tidak ditemukan');

      final results = await Future.wait([
        _supabase
            .from('pengguna')
            .select()
            .eq('id_pengguna', userId)
            .single(),
        _supabase
            .from('reward')
            .select()
            .order('poin_dibutuhkan', ascending: true),
        // Hitung poin_masuk & poin_ditukar dari riwayat_aktivitas
        _supabase
            .from('riwayat_aktivitas')
            .select('perubahan_poin')
            .eq('id_pengguna', userId),
      ]);

      final userData   = results[0] as Map<String, dynamic>;
      final rewardData = results[1] as List<dynamic>;
      final riwayat    = results[2] as List<dynamic>;

      int masuk   = 0;
      int ditukar = 0;
      for (final r in riwayat) {
        final delta = (r['perubahan_poin'] as num?)?.toInt() ?? 0;
        if (delta > 0) masuk   += delta;
        if (delta < 0) ditukar += delta.abs();
      }

      setState(() {
        _namaUser    = userData['nama_lengkap'] as String? ?? '';
        _nomorHp     = userData['no_telepon']   as String? ?? '';
        _poinUser    = (userData['poin']        as num?)?.toInt() ?? 0;
        _fotoUser    = userData['foto_profil']  as String?;
        _poinMasuk   = masuk;
        _poinDitukar = ditukar;
        _rewards = rewardData
            .map((e) => RewardModel.fromMap(e as Map<String, dynamic>))
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

  List<RewardModel> get _filteredRewards {
    if (_filter == 'Pulsa') return _rewards.where((r) => r.isPulsa).toList();
    if (_filter == 'Token') return _rewards.where((r) => r.isToken).toList();
    return _rewards;
  }

  String _getImageUrl(String path) {
    return _supabase.storage.from('media').getPublicUrl(path);
  }

  Future<void> _tukarPoin(RewardModel reward) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppConstants.radiusM),
        ),
        title: Text('Tukar Poin?', style: AppTextStyles.title),
        content: Text(
          'Kamu akan menukarkan ${reward.poinDibutuhkan} poin untuk ${reward.namaReward}.',
          style: AppTextStyles.body,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text('Batal',
                style: AppTextStyles.body
                    .copyWith(color: AppColors.textSecondary)),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text('Tukar',
                style: AppTextStyles.body.copyWith(
                    color: AppColors.primary, fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    try {
      final userId = _supabase.auth.currentUser?.id;
      if (userId == null) throw Exception('User tidak ditemukan');

      // =========================
      // 1. INSERT tukar_poin
      // =========================
      final tukarResult = await _supabase
          .from('tukar_poin')
          .insert({
            'id_pengguna': userId,
            'id_reward'  : reward.id,
          })
          .select()
          .single();

      final idTukar = tukarResult['id_tukar'];

      // =========================
      // 2. UPDATE poin pengguna
      // =========================
      await _supabase
          .from('pengguna')
          .update({'poin': _poinUser - reward.poinDibutuhkan})
          .eq('id_pengguna', userId);

      // =========================
      // 3. UPDATE stok reward
      // =========================
      await _supabase
          .from('reward')
          .update({'stok': reward.stok - 1})
          .eq('id_reward', reward.id);

      // =========================
      // 4. INSERT riwayat_aktivitas
      // =========================
      await _supabase.from('riwayat_aktivitas').insert({
        'id_pengguna'    : userId,
        'jenis_aktivitas': 'tukar_poin',
        'id_referensi'   : idTukar,
        'judul'          : 'Tukar Poin ${reward.namaReward}',
        'deskripsi'      : 'Poin berhasil ditukar dengan ${reward.namaReward}.',
        'status'         : 'selesai',
        'perubahan_poin' : -reward.poinDibutuhkan,
        'perubahan_saldo': null,
        'created_at'     : DateTime.now().toIso8601String(),
      });

      // =========================
      // 5. INSERT notifikasi
      //    (tabel: notifikasi, kolom pesan → deskripsi)
      // =========================
      await _supabase.from('notifikasi').insert({
        'id_pengguna': userId,
        'judul'      : 'Penukaran Reward Berhasil',
        'deskripsi'  : 'Kamu berhasil menukar ${reward.namaReward} menggunakan ${reward.poinDibutuhkan} poin.',
        'created_at' : DateTime.now().toIso8601String(),
        'is_read'    : false,
      });

      // =========================
      // 6. UPDATE UI lokal
      // =========================
      setState(() {
        _poinUser    -= reward.poinDibutuhkan;
        _poinDitukar += reward.poinDibutuhkan;
      });

      if (!mounted) return;
      _showSuccessModal(reward);

    } catch (e) {
      debugPrint('ERROR TUKAR POIN: $e');
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Gagal menukar poin: $e')),
      );
    }
  }

  void _showSuccessModal(RewardModel reward) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _SuccessModal(reward: reward),
    );
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
          Positioned(
            top: 0, left: 0, right: 0,
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
                Expanded(
                  child: Container(
                    decoration: const BoxDecoration(
                      color: AppColors.white,
                      borderRadius: BorderRadius.only(
                        topLeft:  Radius.circular(AppConstants.radiusXL),
                        topRight: Radius.circular(AppConstants.radiusXL),
                      ),
                    ),
                    child: _isLoading
                        ? const Center(
                            child: CircularProgressIndicator(
                                color: AppColors.primary))
                        : _error != null
                            ? _buildError()
                            : _buildContent(),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHeader() {
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
              width: 38, height: 38,
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.55),
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.chevron_left_rounded,
                  color: Color(0xFF1A2800), size: 26),
            ),
          ),
          Expanded(
            child: Text('Reward',
                textAlign: TextAlign.center,
                style: AppTextStyles.namafitur),
          ),
          const SizedBox(width: 38),
        ],
      ),
    );
  }

  Widget _buildContent() {
    return RefreshIndicator(
      color: AppColors.primary,
      onRefresh: _loadData,
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(AppConstants.paddingM),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildUserCard(),
            const SizedBox(height: AppConstants.paddingM),
            _buildFilterTabs(),
            const SizedBox(height: AppConstants.paddingM),
            _buildGrid(),
            const SizedBox(height: AppConstants.paddingL),
          ],
        ),
      ),
    );
  }

  Widget _buildUserCard() {
    return Container(
      padding: const EdgeInsets.all(AppConstants.paddingM),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppConstants.radiusL),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.06),
            blurRadius: 10,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 28,
                backgroundColor: AppColors.primary.withOpacity(0.15),
                backgroundImage: _fotoUser != null
                    ? NetworkImage(_getImageUrl(_fotoUser!))
                    : null,
                child: _fotoUser == null
                    ? const Icon(Icons.person, color: AppColors.primary)
                    : null,
              ),
              const SizedBox(width: AppConstants.paddingM),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Hi, $_namaUser',
                    style: AppTextStyles.title
                        .copyWith(fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 2),
                  Row(
                    children: [
                      const Icon(Icons.phone_outlined,
                          size: 14, color: AppColors.textSecondary),
                      const SizedBox(width: 4),
                      Text(_nomorHp, style: AppTextStyles.caption),
                    ],
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: AppConstants.paddingM),
          _buildPoinBar(),
        ],
      ),
    );
  }

  Widget _buildPoinBar() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFE6FF66), Color(0xFFB5E000)],
          begin: Alignment.centerLeft,
          end: Alignment.centerRight,
        ),
        borderRadius: BorderRadius.circular(AppConstants.radiusM),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Poin Anda',
                    style: AppTextStyles.small
                        .copyWith(color: AppColors.textPrimary)),
                const SizedBox(height: 2),
                Text(
                  '$_poinUser',
                  style: GoogleFonts.poppins(
                    fontSize: 28,
                    fontWeight: FontWeight.bold,
                    color: AppColors.textPrimary,
                  ),
                ),
              ],
            ),
          ),
          Container(width: 1, height: 44,
              color: AppColors.textPrimary.withOpacity(0.18)),
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text('Poin masuk',
                    style: AppTextStyles.small
                        .copyWith(color: AppColors.textPrimary)),
                const SizedBox(height: 6),
                _poinBadge('$_poinMasuk'),
              ],
            ),
          ),
          Container(width: 1, height: 44,
              color: AppColors.textPrimary.withOpacity(0.18)),
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text('Ditukar',
                    style: AppTextStyles.small
                        .copyWith(color: AppColors.textPrimary)),
                const SizedBox(height: 6),
                _poinBadge('$_poinDitukar'),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _poinBadge(String value) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 5),
      decoration: BoxDecoration(
        color: const Color(0xFF013A0C),
        borderRadius: BorderRadius.circular(AppConstants.radiusS),
      ),
      child: Text(
        value,
        style: GoogleFonts.poppins(
          fontSize: 14,
          fontWeight: FontWeight.bold,
          color: AppColors.white,
        ),
      ),
    );
  }

  Widget _buildFilterTabs() {
    const filters = ['Semua', 'Pulsa', 'Token'];
    return Row(
      children: filters.map((f) {
        final selected = _filter == f;
        return Padding(
          padding: const EdgeInsets.only(right: 8),
          child: GestureDetector(
            onTap: () => setState(() => _filter = f),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 180),
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
              decoration: BoxDecoration(
                color: selected ? AppColors.primary : Colors.transparent,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(
                  color: selected ? AppColors.primary : AppColors.inputBorder,
                ),
              ),
              child: Text(
                f,
                style: AppTextStyles.body.copyWith(
                  color: selected ? AppColors.white : AppColors.textSecondary,
                  fontWeight:
                      selected ? FontWeight.w600 : FontWeight.normal,
                ),
              ),
            ),
          ),
        );
      }).toList(),
    );
  }

  Widget _buildGrid() {
    final rewards = _filteredRewards;
    if (rewards.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child:
              Text('Tidak ada reward tersedia', style: AppTextStyles.body),
        ),
      );
    }

    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        crossAxisSpacing: 12,
        mainAxisSpacing: 12,
        childAspectRatio: 0.71,
      ),
      itemCount: rewards.length,
      itemBuilder: (_, i) => _buildRewardCard(rewards[i]),
    );
  }

  Widget _buildRewardCard(RewardModel reward) {
    final canRedeem =
        _poinUser >= reward.poinDibutuhkan && reward.stok > 0;

    return Container(
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppConstants.radiusM),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.06),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: const BorderRadius.only(
              topLeft:  Radius.circular(AppConstants.radiusM),
              topRight: Radius.circular(AppConstants.radiusM),
            ),
            child: Image.network(
              _getImageUrl(reward.fotoReward),
              height: 110,
              width: double.infinity,
              fit: BoxFit.cover,
              errorBuilder: (_, __, ___) => Container(
                height: 110,
                color: const Color(0xFFF0F0F0),
                child: const Center(
                  child: Icon(Icons.image_not_supported_outlined,
                      color: AppColors.textHint, size: 32),
                ),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(10),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  reward.namaReward,
                  style: AppTextStyles.body
                      .copyWith(fontWeight: FontWeight.w600),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 6),
                Container(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: canRedeem
                        ? const Color(0xFFD4F55E)
                        : const Color(0xFFEEEEEE),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    '${reward.poinDibutuhkan} Poin',
                    style: AppTextStyles.small.copyWith(
                      color: canRedeem
                          ? AppColors.textPrimary
                          : AppColors.textSecondary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
                const SizedBox(height: 8),
                SizedBox(
                  width: double.infinity,
                  child: canRedeem
                      ? ElevatedButton(
                          onPressed: () => _tukarPoin(reward),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF1A3A00),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(
                                  AppConstants.radiusS),
                            ),
                            padding:
                                const EdgeInsets.symmetric(vertical: 9),
                            elevation: 0,
                          ),
                          child: Text(
                            'Tukar Poin',
                            style: AppTextStyles.body.copyWith(
                              color: AppColors.white,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        )
                      : Container(
                          padding:
                              const EdgeInsets.symmetric(vertical: 9),
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: const Color(0xFFEEEEEE),
                            borderRadius: BorderRadius.circular(
                                AppConstants.radiusS),
                          ),
                          child: Text(
                            'Butuh ${reward.poinDibutuhkan} pts',
                            style: AppTextStyles.body.copyWith(
                                color: AppColors.textSecondary),
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

  Widget _buildError() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.error_outline,
              color: AppColors.secondary, size: 48),
          const SizedBox(height: AppConstants.paddingS),
          Text('Gagal memuat data', style: AppTextStyles.body),
          const SizedBox(height: AppConstants.paddingS),
          ElevatedButton(
            onPressed: _loadData,
            style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary),
            child:
                Text('Coba Lagi', style: AppTextStyles.buttonLabel),
          ),
        ],
      ),
    );
  }
}

// ─────────────────────────────────────────────
// SUCCESS MODAL
// ─────────────────────────────────────────────
class _SuccessModal extends StatelessWidget {
  final RewardModel reward;
  const _SuccessModal({required this.reward});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
          bottom: MediaQuery.of(context).viewInsets.bottom),
      child: Stack(
        clipBehavior: Clip.none,
        alignment: Alignment.topCenter,
        children: [
          Container(
            margin: const EdgeInsets.only(top: 30),
            decoration: const BoxDecoration(
              color: AppColors.white,
              borderRadius: BorderRadius.only(
                topLeft:  Radius.circular(24),
                topRight: Radius.circular(24),
              ),
            ),
            padding: const EdgeInsets.fromLTRB(20, 56, 20, 40),
            child: SafeArea(
              child: SingleChildScrollView(
                padding: const EdgeInsets.only(bottom: 40),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    // Gradient banner
                    Container(
                      width: double.infinity,
                      padding:
                          const EdgeInsets.fromLTRB(20, 16, 20, 20),
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(
                          colors: [
                            Color(0xFFD4F55E),
                            Color(0xFF96C800)
                          ],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: Column(
                        children: [
                          Text(
                            'Penukaran Berhasil!',
                            style: GoogleFonts.poppins(
                              fontSize: 22,
                              fontWeight: FontWeight.bold,
                              color: const Color(0xFF1A2800),
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'Voucher sudah masuk ke akun Anda',
                            style: AppTextStyles.body.copyWith(
                                color: const Color(0xFF1A2800)),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),

                    // Detail voucher
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF8FFF0),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(
                            color: AppColors.primary.withOpacity(0.25)),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'VOUCHER YANG DITUKARKAN',
                            style: AppTextStyles.small.copyWith(
                              color: AppColors.textSecondary,
                              letterSpacing: 0.6,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            reward.namaReward,
                            style: AppTextStyles.title
                                .copyWith(fontWeight: FontWeight.bold),
                          ),
                          const SizedBox(height: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 12, vertical: 4),
                            decoration: BoxDecoration(
                              color: const Color(0xFFFFD166),
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Text(
                              '${reward.poinDibutuhkan} Poin',
                              style: AppTextStyles.small.copyWith(
                                fontWeight: FontWeight.w600,
                                color: const Color(0xFF1A2800),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),

                    // Kode voucher
                    GestureDetector(
                      onTap: () {
                        Clipboard.setData(
                            ClipboardData(text: reward.kodeVoucher));
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text(
                              'Kode voucher disalin!',
                              style: AppTextStyles.body
                                  .copyWith(color: AppColors.white),
                            ),
                            backgroundColor: AppColors.primary,
                            behavior: SnackBarBehavior.floating,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(
                                  AppConstants.radiusS),
                            ),
                          ),
                        );
                      },
                      child: Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: AppColors.white,
                          borderRadius: BorderRadius.circular(12),
                          border:
                              Border.all(color: AppColors.inputBorder),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'KODE VOUCHER',
                              style: AppTextStyles.small.copyWith(
                                color: AppColors.textSecondary,
                                letterSpacing: 0.6,
                              ),
                            ),
                            const SizedBox(height: 10),
                            Center(
                              child: Container(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 24, vertical: 10),
                                decoration: BoxDecoration(
                                  border: Border.all(
                                    color: const Color(0xFF1A2800),
                                    width: 1.5,
                                  ),
                                  borderRadius:
                                      BorderRadius.circular(8),
                                ),
                                child: Text(
                                  reward.kodeVoucher,
                                  style: GoogleFonts.poppins(
                                    fontSize: 18,
                                    fontWeight: FontWeight.bold,
                                    letterSpacing: 2,
                                    color: const Color(0xFF1A2800),
                                  ),
                                ),
                              ),
                            ),
                            const SizedBox(height: 6),
                            Center(
                              child: Text(
                                'Klik kode untuk menyalin',
                                style: AppTextStyles.small.copyWith(
                                    color: AppColors.textSecondary),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),

          Positioned(
            top: 0,
            child: Container(
              width: 60, height: 60,
              decoration: const BoxDecoration(
                color: AppColors.white,
                shape: BoxShape.circle,
              ),
              child: Center(
                child: Container(
                  width: 50, height: 50,
                  decoration: const BoxDecoration(
                    color: AppColors.primary,
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.check_rounded,
                      color: AppColors.white, size: 28),
                ),
              ),
            ),
          ),

          Positioned(
            bottom: -24,
            child: GestureDetector(
              onTap: () => Navigator.pop(context),
              child: Container(
                width: 44, height: 44,
                decoration: BoxDecoration(
                  color: AppColors.white,
                  shape: BoxShape.circle,
                  border: Border.all(
                      color: AppColors.inputBorder, width: 1.5),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.08),
                      blurRadius: 8,
                    ),
                  ],
                ),
                child: const Icon(Icons.close_rounded, size: 22),
              ),
            ),
          ),
        ],
      ),
    );
  }
}