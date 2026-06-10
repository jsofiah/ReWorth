import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../utils/app_colors.dart';
import '../utils/app_constants.dart';
import '../utils/app_text_styles.dart';

// Model untuk data leaderboard
class LeaderboardEntry {
  final String idWilayah;
  final String rw;
  final String kelurahan;
  final String kecamatan;
  final int totalPoin;
  final int rank;

  LeaderboardEntry({
    required this.idWilayah,
    required this.rw,
    required this.kelurahan,
    required this.kecamatan,
    required this.totalPoin,
    required this.rank,
  });

  factory LeaderboardEntry.fromMap(Map<String, dynamic> map, int rank) {
    return LeaderboardEntry(
      idWilayah: map['id_wilayah'] ?? '',
      rw: map['rw'] ?? '',
      kelurahan: map['kelurahan'] ?? '',
      kecamatan: map['kecamatan'] ?? '',
      totalPoin: (map['total_poin'] as num?)?.toInt() ?? 0,
      rank: rank,
    );
  }
}

class LeaderboardPage extends StatefulWidget {
  const LeaderboardPage({super.key});

  @override
  State<LeaderboardPage> createState() => _LeaderboardPageState();
}

class _LeaderboardPageState extends State<LeaderboardPage> {
  final _supabase = Supabase.instance.client;

  List<LeaderboardEntry> _leaderboard = [];
  LeaderboardEntry? _currentUserEntry;
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadLeaderboard();
  }

  Future<void> _loadLeaderboard() async {
    try {
      setState(() {
        _isLoading = true;
        _error = null;
      });

      // Query: join pengguna dan wilayah, group by wilayah, sum poin
      final response = await _supabase
          .from('pengguna')
          .select('id_wilayah, poin, wilayah:id_wilayah(rw, kelurahan, kecamatan)')
          .order('poin', ascending: false);

      // Agregasi poin per wilayah
      final Map<String, Map<String, dynamic>> wilayahMap = {};
      for (final row in response as List<dynamic>) {
        final idWilayah = row['id_wilayah'] as String?;
        if (idWilayah == null) continue;

        final wilayah = row['wilayah'] as Map<String, dynamic>?;
        if (wilayah == null) continue;

        if (!wilayahMap.containsKey(idWilayah)) {
          wilayahMap[idWilayah] = {
            'id_wilayah': idWilayah,
            'rw': wilayah['rw'],
            'kelurahan': wilayah['kelurahan'],
            'kecamatan': wilayah['kecamatan'],
            'total_poin': 0,
          };
        }
        wilayahMap[idWilayah]!['total_poin'] =
            (wilayahMap[idWilayah]!['total_poin'] as int) + ((row['poin'] as num?)?.toInt() ?? 0);
      }

      // Sort by total poin
      final sorted = wilayahMap.values.toList()
        ..sort((a, b) => (b['total_poin'] as int).compareTo(a['total_poin'] as int));

      final leaderboard = sorted
          .take(10)
          .toList()
          .asMap()
          .entries
          .map((e) => LeaderboardEntry.fromMap(e.value, e.key + 1))
          .toList();

      // Cari wilayah user yang sedang login
      final currentUser = _supabase.auth.currentUser;
      LeaderboardEntry? currentUserEntry;

      if (currentUser != null) {
        final userRow = await _supabase
            .from('pengguna')
            .select('id_wilayah')
            .eq('id_pengguna', currentUser.id)
            .maybeSingle();

        if (userRow != null) {
          final userWilayahId = userRow['id_wilayah'] as String?;
          if (userWilayahId != null) {
            final idx = leaderboard.indexWhere((e) => e.idWilayah == userWilayahId);
            if (idx >= 0) {
              currentUserEntry = leaderboard[idx];
            }
          }
        }
      }

      setState(() {
        _leaderboard = leaderboard;
        _currentUserEntry = currentUserEntry;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
  final topPad = MediaQuery.of(context).padding.top;

  return Scaffold(
    backgroundColor: const Color(0xFFF2F2F2),
    body: Stack(
      children: [
        // Background gradient atas
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
                  child: _isLoading
                      ? const Center(
                          child: CircularProgressIndicator(
                            color: AppColors.primary,
                          ),
                        )
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
        // tombol back
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

        // title
        Expanded(
          child: Text(
            'Leaderboard',
            textAlign: TextAlign.center,
            style: AppTextStyles.namafitur,
          ),
        ),

        // spacer kanan
        const SizedBox(width: 38),
      ],
    ),
  );
}

  Widget _buildContent() {
    return RefreshIndicator(
      color: AppColors.primary,
      onRefresh: _loadLeaderboard,
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildTopBanner(),
            if (_leaderboard.length >= 3) _buildPodium(),
            const SizedBox(height: AppConstants.paddingM),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: AppConstants.paddingM),
              child: Text(
                'Peringkat 1 - 10',
                style: AppTextStyles.title.copyWith(fontSize: 16),
              ),
            ),
            const SizedBox(height: AppConstants.paddingS),
            ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              padding: const EdgeInsets.symmetric(horizontal: AppConstants.paddingM),
              itemCount: _leaderboard.length,
              separatorBuilder: (_, __) => const SizedBox(height: AppConstants.paddingS),
              itemBuilder: (context, index) {
                final entry = _leaderboard[index];
                final isCurrentUser = _currentUserEntry?.idWilayah == entry.idWilayah;
                return _buildListItem(entry, isCurrentUser);
              },
            ),
            const SizedBox(height: 90),
          ],
        ),
      ),
    );
  }

  Widget _buildTopBanner() {
    final entry = _currentUserEntry;
    return Container(
      margin: const EdgeInsets.all(AppConstants.paddingM),
      padding: const EdgeInsets.all(AppConstants.paddingM),
      decoration: BoxDecoration(
        color: const Color(0xFF013A0C),
        borderRadius: BorderRadius.circular(AppConstants.radiusL),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Peringkat mingguan RW se-Kota Malang',
                  style: AppTextStyles.small.copyWith(color: AppColors.white.withOpacity(0.8)),
                ),
                const SizedBox(height: 4),
                Text(
                  'Top 10 Teraktif',
                  style: AppTextStyles.headline.copyWith(
                    color: AppColors.white,
                    fontSize: 22,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 4),
                if (entry != null) ...[
                  Text(
                    'RW ${entry.rw} ${entry.kelurahan} (Kamu)',
                    style: AppTextStyles.caption.copyWith(color: AppColors.lightAccent),
                  ),
                  Text(
                    '${entry.totalPoin} poin – Minggu ini',
                    style: AppTextStyles.small.copyWith(color: AppColors.white.withOpacity(0.7)),
                  ),
                ] else ...[
                  Text(
                    'Lihat posisi RW kamu!',
                    style: AppTextStyles.caption.copyWith(color: AppColors.lightAccent),
                  ),
                ],
              ],
            ),
          ),
          Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Image.asset(
                ('assets/mask_group.png'),
                width: 120,
                height: 120,
                fit: BoxFit.contain,
                errorBuilder: (_, __, ___) => const Icon(
                  Icons.emoji_events,
                  size: 72,
                  color: Color(0xFFFFD700),
                ),
              ),
              if (entry != null) ...[
                const SizedBox(height: 6),
                Transform.translate(
                  offset: const Offset(0, -12),
                  child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 5),
                  decoration: BoxDecoration(
                    color: AppColors.accent,
                    borderRadius: BorderRadius.circular(AppConstants.radiusS),
                  ),
                  child: Text(
                    '#${entry.rank}',
                    style: GoogleFonts.poppins(
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                      color: AppColors.white,
                    ),
                  ),
                ),
              ),
            ],
          ],
         ),
      ],
    ),
  );
}

  Widget _buildPodium() {
    final first = _leaderboard[0];
    final second = _leaderboard[1];
    final third = _leaderboard[2];

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: AppConstants.paddingM, vertical: AppConstants.paddingL),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          // 2nd place (kiri)
          Expanded(child: _build3DPodiumItem(second, false, false)),
          const SizedBox(width: 8),
          // 1st place (tengah, lebih tinggi)
          Expanded(child: _build3DPodiumItem(first, true, false)),
          const SizedBox(width: 8),
          // 3rd place (kanan)
          Expanded(child: _build3DPodiumItem(third, false, true)),
        ],
      ),
    );
  }

  Widget _build3DPodiumItem(LeaderboardEntry entry, bool isFirst, bool isThird) {
    // Tinggi podium berdasarkan peringkat
    final podiumHeight = isFirst ? 120.0 : isThird ? 70.0 : 90.0;
    final circleSize = isFirst ? 70.0 : 56.0;
    
    final baseColor = isFirst
        ? AppColors.accent
        : isThird
            ? const Color(0xFFC9F057)
            : AppColors.secondary;
    
    final darkerColor = isFirst
        ? const Color(0xFFE6A800)
        : isThird
            ? const Color(0xFFA8C43C)
            : const Color(0xFF2D5A1E);
    
    final darkerColor2 = isFirst
        ? const Color(0xFFCC8F00)
        : isThird
            ? const Color(0xFF8AA32E)
            : const Color(0xFF1E3E14);

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        // Crown untuk juara 1
        if (isFirst)
          Transform.translate(
            offset: const Offset(0, 10),
            child: Stack(
              alignment: Alignment.center,
              children: [
                Icon(
                  Icons.star_rounded,
                  size: 32,
                  color: Colors.amber.shade400,
                  shadows: const [
                    Shadow(blurRadius: 4, color: Colors.black26, offset: Offset(0, 2)),
                  ],
                ),
                Positioned(
                  top: 6,
                  child: Text(
                    '👑',
                    style: TextStyle(
                      fontSize: 20,
                      shadows: [
                        Shadow(blurRadius: 4, color: Colors.black.withOpacity(0.3)),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        
        const SizedBox(height: 8),
        
        // Avatar lingkaran dengan shadow 3D
        Container(
          width: circleSize,
          height: circleSize,
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [
                baseColor,
                darkerColor,
              ],
            ),
            shape: BoxShape.circle,
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.3),
                blurRadius: 12,
                offset: const Offset(0, 6),
              ),
            ],
            border: Border.all(
              color: Colors.white.withOpacity(0.5),
              width: 2,
            ),
          ),
          child: Center(
            child: Text(
              'RW ${entry.rw.padLeft(2, '0')}',
              textAlign: TextAlign.center,
              style: GoogleFonts.poppins(
                fontSize: isFirst ? 15 : 13,
                fontWeight: FontWeight.bold,
                color: Colors.white,
                shadows: [
                  Shadow(
                    blurRadius: 2,
                    color: Colors.black.withOpacity(0.3),
                    offset: const Offset(0, 1),
                  ),
                ],
              ),
            ),
          ),
        ),
        
        const SizedBox(height: 12),
        
        // Info teks
        Text(
          'RW ${entry.rw.padLeft(2, '0')}',
          style: AppTextStyles.small.copyWith(
            fontWeight: FontWeight.w700, 
            color: AppColors.textPrimary,
            fontSize: 12,
          ),
        ),
        Text(
          entry.kelurahan,
          style: AppTextStyles.small.copyWith(
            color: AppColors.textSecondary, 
            fontSize: 10,
          ),
          textAlign: TextAlign.center,
        ),
        Text(
          '${entry.totalPoin} poin',
          style: AppTextStyles.small.copyWith(
            fontSize: 10,
            fontWeight: FontWeight.w600,
            color: baseColor,
          ),
        ),
        
        const SizedBox(height: 10),
        
        // Podium 3D dengan efek gradasi dan shadow
        Container(
          height: podiumHeight,
          width: double.infinity,
          decoration: BoxDecoration(
            borderRadius: const BorderRadius.only(
              topLeft: Radius.circular(16),
              topRight: Radius.circular(16),
            ),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.25),
                blurRadius: 10,
                offset: const Offset(0, -4),
              ),
              BoxShadow(
                color: Colors.black.withOpacity(0.15),
                blurRadius: 8,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Stack(
            children: [
              // Bagian depan podium (efek 3D)
              Positioned.fill(
                child: Container(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [
                        baseColor,
                        darkerColor,
                        darkerColor2,
                      ],
                    ),
                    borderRadius: const BorderRadius.only(
                      topLeft: Radius.circular(16),
                      topRight: Radius.circular(16),
                    ),
                  ),
                ),
              ),
              
              // Efek garis highlight di atas
              Positioned(
                top: 0,
                left: 0,
                right: 0,
                child: Container(
                  height: 10,
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.centerLeft,
                      end: Alignment.centerRight,
                      colors: [
                        Colors.white.withOpacity(0.3),
                        Colors.white.withOpacity(0.6),
                        Colors.white.withOpacity(0.3),
                      ],
                    ),
                    borderRadius: const BorderRadius.only(
                      topLeft: Radius.circular(100),
                      topRight: Radius.circular(100),
                    ),
                  ),
                ),
              ),
              
              // Efek garis vertikal (seperti papan kayu)
              Positioned(
                left: 8,
                right: 8,
                top: 4,
                bottom: 0,
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                  children: List.generate(
                    4,
                    (index) => Container(
                      width: 1.5,
                      height: podiumHeight - 4,
                      color: Colors.white.withOpacity(0.08),
                    ),
                  ),
                ),
              ),
              
              // Nomor peringkat di podium
              Center(
                child: Container(
                  width: isFirst ? 40 : 32,
                  height: isFirst ? 40 : 32,
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.25),
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: Colors.white.withOpacity(0.5),
                      width: 1.5,
                    ),
                  ),
                  child: Center(
                    child: Text(
                      '${entry.rank}',
                      style: GoogleFonts.poppins(
                        fontSize: isFirst ? 22 : 18,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                        shadows: [
                          Shadow(
                            blurRadius: 2,
                            color: Colors.black.withOpacity(0.3),
                            offset: const Offset(0, 1),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
              
              // Efek shadow di bagian bawah podium
              Positioned(
                bottom: -8,
                left: 4,
                right: 4,
                child: Container(
                  height: 8,
                  decoration: BoxDecoration(
                    color: Colors.black.withOpacity(0.15),
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }


  Widget _buildPodiumItem(LeaderboardEntry entry, bool isFirst, bool isThird) {
    final circleColor = isFirst
        ? AppColors.accent
        : isThird
            ? const Color(0xFFC9F057)
            : AppColors.secondary;

    final badgeColor = isFirst
        ? AppColors.accent
        : isThird
            ? const Color(0xFFC9F057)
            : AppColors.secondary;

    final circleSize = isFirst ? 72.0 : 56.0;
    final badgeHeight = isFirst ? 60.0 : isThird ? 40.0 : 50.0;

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (isFirst)
          const Text('👑', style: TextStyle(fontSize: 22)),
        Container(
          width: circleSize,
          height: circleSize,
          decoration: BoxDecoration(
            color: circleColor,
            shape: BoxShape.circle,
          ),
          child: Center(
            child: Text(
              'RW ${entry.rw.padLeft(2, '0')}',
              textAlign: TextAlign.center,
              style: GoogleFonts.poppins(
                fontSize: isFirst ? 15 : 13,
                fontWeight: FontWeight.bold,
                color: AppColors.white,
              ),
            ),
          ),
        ),
        const SizedBox(height: 6),
        Text(
          'RW ${entry.rw.padLeft(2, '0')}',
          style: AppTextStyles.small.copyWith(fontWeight: FontWeight.w700, color: AppColors.textPrimary),
        ),
        Text(
          entry.kelurahan,
          style: AppTextStyles.small.copyWith(color: AppColors.textSecondary, fontSize: 9),
          textAlign: TextAlign.center,
        ),
        Text(
          '${entry.totalPoin} poin',
          style: AppTextStyles.small.copyWith(
            fontSize: 10,
            color: AppColors.textSecondary,
          ),
        ),
        const SizedBox(height: 6),
        Container(
          height: badgeHeight,
          decoration: BoxDecoration(
            color: badgeColor,
            borderRadius: const BorderRadius.only(
              topLeft: Radius.circular(6),
              topRight: Radius.circular(6),
            ),
          ),
          child: Center(
            child: Text(
              '${entry.rank}',
              style: GoogleFonts.poppins(
                fontSize: 20,
                fontWeight: FontWeight.bold,
                color: AppColors.white,
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildListItem(LeaderboardEntry entry, bool isCurrentUser) {
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppConstants.paddingM,
        vertical: AppConstants.paddingM,
      ),
      decoration: BoxDecoration(
        color: isCurrentUser ? AppColors.lightAccent.withOpacity(0.35) : AppColors.white,
        borderRadius: BorderRadius.circular(AppConstants.radiusM),
        border: isCurrentUser
            ? Border.all(color: AppColors.primary, width: 1.5)
            : null,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          SizedBox(
            width: 24,
            child: Text(
              '${entry.rank}',
              style: AppTextStyles.body.copyWith(
                fontWeight: FontWeight.w600,
                color: entry.rank <= 3 ? AppColors.secondary : AppColors.textSecondary,
              ),
            ),
          ),
          const SizedBox(width: AppConstants.paddingS),
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: _getAvatarColor(entry.rank),
              shape: BoxShape.circle,
            ),
            child: Center(
              child: Text(
                'RW\n${entry.rw.padLeft(2, '0')}',
                textAlign: TextAlign.center,
                style: GoogleFonts.poppins(
                  fontSize: 10,
                  fontWeight: FontWeight.bold,
                  color: AppColors.white,
                  height: 1.2,
                ),
              ),
            ),
          ),
          const SizedBox(width: AppConstants.paddingM),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'RW ${entry.rw.padLeft(2, '0')} ${entry.kelurahan}',
                  style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w600),
                ),
                Text(
                  'Kec. ${entry.kecamatan}',
                  style: AppTextStyles.caption,
                ),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                _formatPoin(entry.totalPoin),
                style: AppTextStyles.body.copyWith(
                  fontWeight: FontWeight.bold,
                  color: AppColors.textPrimary,
                ),
              ),
              Text(
                'Poin',
                style: AppTextStyles.small,
              ),
            ],
          ),
        ],
      ),
    );
  }

  Color _getAvatarColor(int rank) {
    return const Color(0xFF7CA73B);
  }

  String _formatPoin(int poin) {
    if (poin >= 1000) {
      return '${(poin / 1000).toStringAsFixed(poin % 1000 == 0 ? 0 : 3).replaceAll(RegExp(r'\.?0+$'), '')}K';
    }
    return poin.toString();
  }

  Widget _buildError() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.error_outline, color: AppColors.secondary, size: 48),
          const SizedBox(height: AppConstants.paddingS),
          Text('Gagal memuat data', style: AppTextStyles.body),
          const SizedBox(height: AppConstants.paddingS),
          ElevatedButton(
            onPressed: _loadLeaderboard,
            style: ElevatedButton.styleFrom(backgroundColor: AppColors.primary),
            child: Text('Coba Lagi', style: AppTextStyles.buttonLabel),
          ),
        ],
      ),
    );
  }
}