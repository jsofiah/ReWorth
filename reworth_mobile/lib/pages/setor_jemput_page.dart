import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../utils/app_colors.dart';
import '../utils/app_text_styles.dart';
import '../utils/app_constants.dart';
import '../services/auth_service.dart';

class JemputPage extends StatefulWidget {
  final List<Map<String, dynamic>>? sampahList;
  final double? totalBerat;
  final int? totalPoin;
  final double? totalHarga;
  
  const JemputPage({
    super.key,
    this.sampahList,
    this.totalBerat,
    this.totalPoin,
    this.totalHarga,
  });

  @override
  State<JemputPage> createState() => _JemputPageState();
}

class _JemputPageState extends State<JemputPage> {
  final _supabase = Supabase.instance.client;
  
  List<Map<String, dynamic>> _validSampah = [];
  double _totalBerat = 0;
  int _totalPoin = 0;
  double _totalHarga = 0;
  int _currentStep = 1; // Step 2: Alamat & Jadwal
  
  List<Map<String, dynamic>> _jadwalList = [];
  Map<String, dynamic>? _selectedJadwal;
  String _selectedTanggal = '';
  String _selectedWaktuMulai = '';
  String _selectedWaktuSelesai = '';
  String _selectedHari = '';
  
  String _alamat = '';
  bool _isLoading = true;
  bool _isSubmitting = false;

  // Warna khusus Figma
  final Color _darkGreen = const Color(0xFF013A0C);
  final Color _lightGreenBg = const Color(0xFFF8FBF4);
  final Color _borderGreen = const Color(0xFFD8E8C0);
  final Color _greyAddress = const Color(0xFF777777);

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    try {
      _validSampah = widget.sampahList ?? [];
      _totalBerat = widget.totalBerat ?? 0;
      _totalPoin = widget.totalPoin ?? 0;
      _totalHarga = widget.totalHarga ?? 0;
      
      final user = AuthService.getCurrentUser();
      if (user != null) {
        final userData = await _supabase
            .from('pengguna')
            .select('alamat_detail')
            .eq('id_pengguna', user.id)
            .maybeSingle();
        _alamat = userData?['alamat_detail'] ?? 'Jl. Kayutangan 17, Kauman, Klojen, Malang, Jawa Timur 65119';
      }
      
      await _loadJadwal();
      setState(() => _isLoading = false);
    } catch (e) {
      debugPrint('Error: $e');
      setState(() => _isLoading = false);
    }
  }

  Future<void> _loadJadwal() async {
    try {
      final data = await _supabase
          .from('jadwal_ambil')
          .select('id_jadwal, tanggal, waktu_mulai, waktu_selesai, kuota')
          .order('tanggal', ascending: true);
      
      _jadwalList = List<Map<String, dynamic>>.from(data);
      
      if (_jadwalList.isNotEmpty) {
        _selectedJadwal = _jadwalList.first;
        _selectedTanggal = _formatTanggal(_selectedJadwal!['tanggal']);
        _selectedWaktuMulai = _selectedJadwal!['waktu_mulai'].toString().substring(0, 5);
        _selectedWaktuSelesai = _selectedJadwal!['waktu_selesai'].toString().substring(0, 5);
        _selectedHari = _getHariFromDate(_selectedJadwal!['tanggal']);
      }
    } catch (e) {
      debugPrint('Error load jadwal: $e');
    }
  }

  String _formatTanggal(String tanggal) {
    final date = DateTime.parse(tanggal);
    final months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return '${date.day} ${months[date.month - 1]} ${date.year}';
  }

  String _getHariFromDate(String tanggal) {
    final date = DateTime.parse(tanggal);
    final days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    return days[date.weekday - 1];
  }

  String _getJenisNama(String idJenis) {
    return 'Sampah';
  }

  String _formatRupiah(double value) {
    return value.toStringAsFixed(0).replaceAllMapped(
      RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
      (m) => '${m[1]}.',
    );
  }

  void _nextStep() {
    if (_currentStep < 2) {
      setState(() => _currentStep++);
    }
  }

  void _prevStep() {
    if (_currentStep > 0) {
      setState(() => _currentStep--);
    }
  }

  Future<void> _submitJemput() async {
    if (_selectedJadwal == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Pilih jadwal penjemputan terlebih dahulu')),
      );
      return;
    }

    setState(() => _isSubmitting = true);
    try {
      final user = AuthService.getCurrentUser();
      if (user == null) throw Exception('User tidak login');
      
      await _supabase.from('penjemputan').insert({
        'id_pengguna': user.id,
        'id_jadwal': _selectedJadwal!['id_jadwal'],
        'alamat': _alamat,
        'total_berat': _totalBerat,
        'total_poin': _totalPoin,
        'total_harga': _totalHarga,
        'status': 'menunggu',
        'created_at': DateTime.now().toIso8601String(),
      });
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Penjemputan berhasil dijadwalkan!'), backgroundColor: Colors.green),
        );
        Navigator.pop(context);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Gagal: $e'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
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
            child: Image.asset('assets/gradient.png', fit: BoxFit.cover),
          ),
          SafeArea(
            bottom: false,
            child: Column(
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
                    ),
                    child: _isLoading
                        ? const Center(child: CircularProgressIndicator())
                        : (_currentStep == 1 ? _buildStepAlamatJadwal() : _buildStepKonfirmasi()),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTitleBar() => Padding(
    padding: const EdgeInsets.symmetric(horizontal: AppConstants.paddingM, vertical: AppConstants.paddingM),
    child: Row(
      children: [
        GestureDetector(
          onTap: () => Navigator.pop(context),
          child: Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.55), shape: BoxShape.circle),
            child: const Icon(Icons.chevron_left_rounded, color: Color(0xFF1A2800), size: 26),
          ),
        ),
        Expanded(
          child: Text(
            'Alamat & Jadwal',
            style: AppTextStyles.namafitur,
            textAlign: TextAlign.center,
          ),
        ),
        const SizedBox(width: 38),
      ],
    ),
  );

  Widget _buildStepAlamatJadwal() => SingleChildScrollView(
    padding: const EdgeInsets.all(AppConstants.paddingL),
    child: Column(
      children: [
        _buildStepper(),
        const SizedBox(height: AppConstants.paddingL),
        _buildAlamatCard(),
        const SizedBox(height: AppConstants.paddingL),
        _buildJadwalCard(),
        const SizedBox(height: AppConstants.paddingL),
        _buildEstimasiCard(),
        const SizedBox(height: AppConstants.paddingXL),
        _buildBottomButtons(),
        const SizedBox(height: AppConstants.paddingXL),
      ],
    ),
  );

  Widget _buildStepper() => Row(
    children: [
      _buildStep(1, 'Informasi', false, true),
      Expanded(child: Container(height: 2, color: AppColors.secondary)),
      _buildStep(2, 'Alamat & Jadwal', true, false),
      Expanded(child: Container(height: 2, color: AppColors.divider)),
      _buildStep(3, 'Konfirmasi', false, false),
    ],
  );

  Widget _buildStep(int n, String l, bool a, bool c) => Column(
    children: [
      Container(
        width: 32, height: 32,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: a || c ? AppColors.secondary : AppColors.white,
          border: Border.all(color: a || c ? AppColors.secondary : AppColors.divider, width: 1.5),
        ),
        child: Center(
          child: c ? const Icon(Icons.check, size: 18, color: Colors.white) 
                  : Text('$n', style: TextStyle(color: a ? Colors.white : AppColors.textSecondary, fontWeight: FontWeight.bold, fontSize: 14)),
        ),
      ),
      const SizedBox(height: 4),
      Text(l, style: AppTextStyles.caption.copyWith(color: a ? AppColors.secondary : AppColors.textSecondary)),
    ],
  );

  Widget _buildAlamatCard() => Container(
    width: double.infinity,
    decoration: BoxDecoration(
      color: AppColors.white,
      borderRadius: BorderRadius.circular(18),
      boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 11.1)],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Padding(
          padding: EdgeInsets.fromLTRB(16, 16, 16, 0),
          child: Text(
            'PILIH ALAMAT',
            style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: Color(0xFF74942B)),
          ),
        ),
        Padding(
          padding: const EdgeInsets.all(16),
          child: Container(
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [Color(0x14CCE34A), Color(0x1ABBDE2D)],
              ),
              border: Border.all(color: _darkGreen, width: 1),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Row(
              children: [
                Container(
                  width: 38,
                  height: 38,
                  margin: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: const Color(0xFFE4F7CC),
                    border: Border.all(color: _darkGreen, width: 1),
                    borderRadius: BorderRadius.circular(11),
                  ),
                  child: const Icon(Icons.home, color: Color(0xFF013A0C), size: 20),
                ),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Rumah',
                        style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: Color(0xFF0A0A0A)),
                      ),
                      Text(
                        _alamat,
                        style: TextStyle(fontSize: 11, color: _greyAddress, decoration: TextDecoration.underline),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ),
                Container(
                  width: 20,
                  height: 20,
                  margin: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: _darkGreen,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(Icons.check, size: 12, color: Colors.white),
                ),
              ],
            ),
          ),
        ),
      ],
    ),
  );

  Widget _buildJadwalCard() => Container(
    width: double.infinity,
    decoration: BoxDecoration(
      color: AppColors.white,
      borderRadius: BorderRadius.circular(18),
      boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 11.1)],
    ),
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'JADWAL',
            style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: Color(0xFF74942B)),
          ),
          const SizedBox(height: 16),
          const Text(
            'Pilih Tanggal',
            style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF555555)),
          ),
          const SizedBox(height: 8),
          SizedBox(
            height: 58,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: _jadwalList.length,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (context, index) {
                final jadwal = _jadwalList[index];
                final isSelected = _selectedJadwal?['id_jadwal'] == jadwal['id_jadwal'];
                final hari = _getHariFromDate(jadwal['tanggal']).substring(0, 3);
                final tanggal = jadwal['tanggal'].toString().substring(8, 10);
                
                return GestureDetector(
                  onTap: () => setState(() {
                    _selectedJadwal = jadwal;
                    _selectedTanggal = _formatTanggal(jadwal['tanggal']);
                    _selectedWaktuMulai = jadwal['waktu_mulai'].toString().substring(0, 5);
                    _selectedWaktuSelesai = jadwal['waktu_selesai'].toString().substring(0, 5);
                    _selectedHari = _getHariFromDate(jadwal['tanggal']);
                  }),
                  child: Container(
                    width: 52,
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    decoration: BoxDecoration(
                      color: isSelected ? _darkGreen : _lightGreenBg,
                      border: Border.all(color: _borderGreen, width: 1),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Column(
                      children: [
                        Text(
                          hari,
                          style: TextStyle(
                            fontSize: 9,
                            fontWeight: FontWeight.w700,
                            color: isSelected ? Colors.white : const Color(0xFF888888),
                          ),
                        ),
                        Text(
                          tanggal,
                          style: TextStyle(
                            fontSize: 9,
                            fontWeight: FontWeight.w700,
                            color: isSelected ? Colors.white : const Color(0xFF888888),
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
          const SizedBox(height: 16),
          const Text(
            'Pilih Waktu Penjemputan',
            style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF555555)),
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              _buildWaktuOption('07:00 - 09:00', _selectedWaktuMulai == '07:00'),
              const SizedBox(width: 8),
              _buildWaktuOption('09:00 - 11:00', _selectedWaktuMulai == '09:00'),
              const SizedBox(width: 8),
              _buildWaktuOption('13:00 - 15:00', _selectedWaktuMulai == '13:00'),
              const SizedBox(width: 8),
              _buildWaktuOption('15:00 - 17:00', _selectedWaktuMulai == '15:00'),
            ],
          ),
        ],
      ),
    ),
  );

  Widget _buildWaktuOption(String waktu, bool isSelected) {
    return Expanded(
      child: GestureDetector(
        onTap: () {
          setState(() {
            _selectedWaktuMulai = waktu.substring(0, 5);
            _selectedWaktuSelesai = waktu.substring(8, 13);
          });
        },
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 8),
          decoration: BoxDecoration(
            color: isSelected ? _darkGreen : _lightGreenBg,
            border: Border.all(color: _borderGreen, width: 1),
            borderRadius: BorderRadius.circular(100),
          ),
          child: Center(
            child: Text(
              waktu,
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w600,
                color: isSelected ? Colors.white : const Color(0xFF555555),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildEstimasiCard() => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: AppColors.secondary,
      borderRadius: BorderRadius.circular(16),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'ESTIMASI PENDAPATAN',
                style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Colors.white60, letterSpacing: 0.5),
              ),
              const SizedBox(height: 8),
              ..._validSampah.map((s) => Text(
                '• ${s['nama']} ${(s['berat'] as double).toStringAsFixed(1)} kg',
                style: const TextStyle(fontSize: 12, color: Colors.white70),
              )),
              const SizedBox(height: 4),
              Text(
                'Total: ${_totalBerat.toStringAsFixed(1)} kg sampah',
                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Colors.white),
              ),
            ],
          ),
        ),
        Column(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Text(
              '= Rp${_formatRupiah(_totalHarga)}',
              style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w700, color: Colors.white),
            ),
            Text(
              '+$_totalPoin poin',
              style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w800, color: Colors.white),
            ),
          ],
        ),
      ],
    ),
  );

  Widget _buildBottomButtons() => Row(
    children: [
      Expanded(
        child: OutlinedButton(
          onPressed: _prevStep,
          style: OutlinedButton.styleFrom(
            side: BorderSide(color: AppColors.secondary),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppConstants.radiusXL)),
            padding: const EdgeInsets.symmetric(vertical: 14),
          ),
          child: Text('Kembali', style: AppTextStyles.body.copyWith(color: AppColors.secondary, fontWeight: FontWeight.w600)),
        ),
      ),
      const SizedBox(width: AppConstants.paddingM),
      Expanded(
        child: ElevatedButton(
          onPressed: _nextStep,
          style: ElevatedButton.styleFrom(
            backgroundColor: AppColors.secondary,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppConstants.radiusXL)),
            padding: const EdgeInsets.symmetric(vertical: 14),
          ),
          child: Text('Lanjut', style: AppTextStyles.buttonLabel.copyWith(color: Colors.white)),
        ),
      ),
    ],
  );

  Widget _buildStepKonfirmasi() => SingleChildScrollView(
    padding: const EdgeInsets.all(AppConstants.paddingL),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _buildStepperKonfirmasi(),
        const SizedBox(height: AppConstants.paddingL),
        _buildRingkasanCard(),
        const SizedBox(height: AppConstants.paddingL),
        _buildDetailSampahCard(),
        const SizedBox(height: AppConstants.paddingL),
        _buildInfoPenjemputanCard(),
        const SizedBox(height: AppConstants.paddingXL),
        _buildSubmitButton(),
        const SizedBox(height: AppConstants.paddingXL),
      ],
    ),
  );

  Widget _buildStepperKonfirmasi() => Row(
    children: [
      _buildStep(1, 'Informasi', false, true),
      Expanded(child: Container(height: 2, color: AppColors.secondary)),
      _buildStep(2, 'Alamat & Jadwal', false, true),
      Expanded(child: Container(height: 2, color: AppColors.secondary)),
      _buildStep(3, 'Konfirmasi', true, false),
    ],
  );

  Widget _buildRingkasanCard() => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(AppConstants.paddingM),
    decoration: BoxDecoration(
      color: const Color(0xFFF5F9EE),
      borderRadius: BorderRadius.circular(AppConstants.radiusM),
      boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.08), blurRadius: 16, offset: const Offset(0, 4))],
    ),
    child: Column(
      children: [
        _buildInfoRow('Total Berat', '${_totalBerat.toStringAsFixed(2)} kg'),
        Divider(color: _borderGreen),
        _buildInfoRow('Total Poin', '$_totalPoin poin'),
        Divider(color: _borderGreen),
        _buildInfoRow('Total Harga', 'Rp ${_formatRupiah(_totalHarga)}'),
      ],
    ),
  );

  Widget _buildDetailSampahCard() => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text('DETAIL SAMPAH', style: AppTextStyles.title.copyWith(fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.secondary)),
      const SizedBox(height: AppConstants.paddingM),
      ..._validSampah.map((s) => Padding(
        padding: const EdgeInsets.only(bottom: AppConstants.paddingS),
        child: Container(
          padding: const EdgeInsets.all(AppConstants.paddingM),
          decoration: BoxDecoration(border: Border.all(color: _borderGreen), borderRadius: BorderRadius.circular(AppConstants.radiusM)),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(s['nama'], style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w600)),
              const SizedBox(height: 4),
              Text('${_getJenisNama(s['id_jenis'])} · ${(s['berat'] as double).toStringAsFixed(1)} kg', style: AppTextStyles.caption),
            ],
          ),
        ),
      )),
    ],
  );

  Widget _buildInfoPenjemputanCard() => Column(
    children: [
      Container(
        padding: const EdgeInsets.all(AppConstants.paddingM),
        decoration: BoxDecoration(color: AppColors.primary.withOpacity(0.1), borderRadius: BorderRadius.circular(AppConstants.radiusM)),
        child: Row(
          children: [
            const Icon(Icons.location_on_outlined, color: AppColors.secondary),
            const SizedBox(width: 8),
            Expanded(child: Text('Alamat: $_alamat', style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w500), maxLines: 2)),
          ],
        ),
      ),
      const SizedBox(height: AppConstants.paddingS),
      Container(
        padding: const EdgeInsets.all(AppConstants.paddingM),
        decoration: BoxDecoration(color: AppColors.primary.withOpacity(0.1), borderRadius: BorderRadius.circular(AppConstants.radiusM)),
        child: Row(
          children: [
            const Icon(Icons.calendar_today_outlined, color: AppColors.secondary),
            const SizedBox(width: 8),
            Expanded(child: Text('$_selectedHari, $_selectedTanggal', style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w500))),
          ],
        ),
      ),
      const SizedBox(height: AppConstants.paddingS),
      Container(
        padding: const EdgeInsets.all(AppConstants.paddingM),
        decoration: BoxDecoration(color: AppColors.primary.withOpacity(0.1), borderRadius: BorderRadius.circular(AppConstants.radiusM)),
        child: Row(
          children: [
            const Icon(Icons.access_time_outlined, color: AppColors.secondary),
            const SizedBox(width: 8),
            Expanded(child: Text('$_selectedWaktuMulai - $_selectedWaktuSelesai WIB', style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w500))),
          ],
        ),
      ),
    ],
  );

  Widget _buildSubmitButton() => SizedBox(
    width: double.infinity,
    height: 51,
    child: ElevatedButton(
      onPressed: _isSubmitting ? null : _submitJemput,
      style: ElevatedButton.styleFrom(
        backgroundColor: const Color(0xFF7CA73B),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(50)),
      ),
      child: _isSubmitting
          ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
          : const Text('Konfirmasi', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: Colors.white)),
    ),
  );

  Widget _buildInfoRow(String l, String v) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 8),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(l, style: AppTextStyles.body),
      Text(v, style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w600)),
    ]),
  );
}