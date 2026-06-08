import 'package:flutter/material.dart';
import '../utils/app_colors.dart';
import '../utils/app_text_styles.dart';
import '../utils/app_constants.dart';
import '../services/auth_service.dart';
import 'package:supabase_flutter/supabase_flutter.dart';


class SetorAntarPage extends StatefulWidget {
  final List<Map<String, dynamic>> sampahList;
  final List<Map<String, dynamic>> jenisSampahList;

  const SetorAntarPage({
    super.key,
    required this.sampahList,
    required this.jenisSampahList,
  });

  @override
  State<SetorAntarPage> createState() => _SetorAntarPageState();
}

class _SetorAntarPageState extends State<SetorAntarPage> {
  int _currentStep = 1;
  String _selectedHari = '';
  String _selectedJam = '';
  bool _isSubmitting = false;
  final _supabase = Supabase.instance.client;

  static const _hariList = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
  static const _jamList = ['08:00 – 12:00', '13:00 – 16:00'];

  
  String _getJenisNama(String idJenis) {
    final jenis = widget.jenisSampahList.firstWhere(
      (j) => j['id_jenis'] == idJenis,
      orElse: () => {'nama_sampah': ''},
    );
    return jenis['nama_sampah'] ?? '';
  }

  double _getHargaPerKg(String idJenis) {
    final jenis = widget.jenisSampahList.firstWhere(
      (j) => j['id_jenis'] == idJenis,
      orElse: () => {'harga_per_kg': 0},
    );
    return (jenis['harga_per_kg'] ?? 0).toDouble();
  }

  List<Map<String, dynamic>> get _validSampah => widget.sampahList
      .where((s) =>
          s['nama'].toString().isNotEmpty &&
          s['id_jenis'].toString().isNotEmpty &&
          (s['berat'] as double) > 0)
      .toList();

  double get _totalBerat =>
      _validSampah.fold(0.0, (sum, s) => sum + (s['berat'] as double));

  double get _totalHarga => _validSampah.fold(
      0.0,
      (sum, s) =>
          sum + (_getHargaPerKg(s['id_jenis']) * (s['berat'] as double)));

  int get _totalPoin => 10;

  String _formatRupiah(double value) => value
      .toStringAsFixed(0)
      .replaceAllMapped(
          RegExp(r'(\d)(?=(\d{3})+(?!\d))'), (m) => '${m[1]}.');

  
  void _nextStep() {
    if (_currentStep == 1) {
      if (_selectedHari.isEmpty || _selectedJam.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Pilih hari dan jam kedatangan')),
        );
        return;
      }
    }
    setState(() => _currentStep++);
  }

  void _prevStep() {
    if (_currentStep == 1) {
      Navigator.pop(context);
    } else {
      setState(() => _currentStep--);
    }
  }

  
  
  

  Future<void> _submitSetor() async {
    setState(() => _isSubmitting = true);
    try {
      final user = AuthService.getCurrentUser();
      if (user == null) throw Exception('User tidak login');
      
      
      final result = await _supabase.from('setor_sampah').insert({
        'id_pengguna': user.id,
        'alamat': null,
        'id_jadwal': null,
        'total_uang': _totalHarga,
        'status': 'menunggu',
        'created_at': DateTime.now().toIso8601String(),
      }).select().single();
      
      final idSetor = result['id_setor'];
      
      
      for (var s in _validSampah) {
        final harga = _getHargaPerKg(s['id_jenis']);
        await _supabase.from('detail_setor').insert({
          'id_setor': idSetor,
          'id_jenis': s['id_jenis'],
          'berat': s['berat'],
          'harga_per_kg': harga,
          'subtotal': harga * (s['berat'] as double),
        });
      }
      
      
      await _supabase.from('riwayat_aktivitas').insert({
        'id_pengguna': user.id,
        'jenis_aktivitas': 'setor_sampah',
        'id_referensi': idSetor,
        'judul': 'Setor Sampah',
        'deskripsi': 'Anda melakukan setor sampah antar sendiri dengan total ${_totalBerat.toStringAsFixed(2)} kg. Menunggu verifikasi admin.',
        'status': 'menunggu',
        'perubahan_poin': 0,
        'perubahan_saldo': 0,
        'created_at': DateTime.now().toIso8601String(),
      });
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Setor sampah berhasil! Menunggu verifikasi admin.'), backgroundColor: Colors.green),
        );
        Navigator.of(context).popUntil((route) => route.isFirst);
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
            top: 0, left: 0, right: 0, height: topPad + 110,
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
                    child: Column(
                      children: [
                        _buildStepper(),
                        const SizedBox(height: AppConstants.paddingL),
                        Expanded(
                          child: SingleChildScrollView(
                            padding: const EdgeInsets.symmetric(
                                horizontal: AppConstants.paddingL),
                            child: Column(
                              children: [
                                if (_currentStep == 1) _buildStepJadwal(),
                                if (_currentStep == 2) _buildStepKonfirmasi(),
                                const SizedBox(height: AppConstants.paddingXL),
                              ],
                            ),
                          ),
                        ),
                        _buildBottomButtons(),
                        const SizedBox(height: AppConstants.paddingXL),
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

  Widget _buildTitleBar() => Padding(
        padding: const EdgeInsets.symmetric(
            horizontal: AppConstants.paddingM,
            vertical: AppConstants.paddingM),
        child: Row(
          children: [
            GestureDetector(
              onTap: () => Navigator.pop(context),
              child: Container(
                width: 38, height: 38,
                decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.55),
                    shape: BoxShape.circle),
                child: const Icon(Icons.chevron_left_rounded,
                    color: Color(0xFF1A2800), size: 26),
              ),
            ),
            Expanded(
              child: Text('Setor Sampah',
                  style: AppTextStyles.namafitur,
                  textAlign: TextAlign.center),
            ),
            const SizedBox(width: 38),
          ],
        ),
      );

  Widget _buildStepper() => Padding(
        padding: const EdgeInsets.fromLTRB(
            AppConstants.paddingL, AppConstants.paddingXL,
            AppConstants.paddingL, 0),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            _buildStep(1, 'Informasi', false, true),
            Expanded(
              child: Container(
                height: 2,
                margin: const EdgeInsets.only(bottom: 16),
                color: AppColors.secondary,
              ),
            ),
            _buildStep(2, 'Jam Operasional', _currentStep == 1, _currentStep > 1),
            Expanded(
              child: Container(
                height: 2,
                margin: const EdgeInsets.only(bottom: 16),
                color: _currentStep >= 2
                    ? AppColors.secondary
                    : const Color(0xFFE0E0E0),
              ),
            ),
            _buildStep(3, 'Konfirmasi', _currentStep == 2, _currentStep > 2),
          ],
        ),
      );

  Widget _buildStep(int n, String l, bool a, bool c) => Column(
        children: [
          Container(
            width: 32, height: 32,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: a || c ? AppColors.secondary : Colors.white,
              border: Border.all(
                  color: a || c ? AppColors.secondary : const Color(0xFFE0E0E0),
                  width: 1.5),
            ),
            child: Center(
              child: c
                  ? const Icon(Icons.check, size: 18, color: Colors.white)
                  : Text('$n',
                      style: TextStyle(
                          color: a ? Colors.white : const Color(0xFF999999),
                          fontWeight: FontWeight.bold,
                          fontSize: 14)),
            ),
          ),
          const SizedBox(height: 4),
          Text(l,
              style: AppTextStyles.caption.copyWith(
                  color: a || c ? AppColors.secondary : const Color(0xFF999999),
                  fontWeight: a ? FontWeight.w600 : FontWeight.w400)),
        ],
      );

  Widget _buildBottomButtons() => Padding(
    padding: const EdgeInsets.all(AppConstants.paddingL),
    child: Row(
      children: [
        Expanded(
          child: OutlinedButton(
            onPressed: _prevStep,
            style: OutlinedButton.styleFrom(
              side: BorderSide(color: AppColors.secondary),
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(AppConstants.radiusXL)),
              padding: const EdgeInsets.symmetric(vertical: 14),
            ),
            child: Text('Kembali',
                style: AppTextStyles.body.copyWith(
                    color: AppColors.secondary, fontWeight: FontWeight.w600)),
          ),
        ),
        const SizedBox(width: AppConstants.paddingM),
        Expanded(
          child: ElevatedButton(
            onPressed: _currentStep == 2 
                ? (_isSubmitting ? null : _submitSetor)  
                : _nextStep,
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.secondary,
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(AppConstants.radiusXL)),
              padding: const EdgeInsets.symmetric(vertical: 14),
            ),
            child: _currentStep == 2 && _isSubmitting
                ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : Text(
                    _currentStep == 2 ? 'Kirim Setoran' : 'Lanjut',  
                    style: AppTextStyles.buttonLabel.copyWith(color: Colors.white),
                  ),
          ),
        ),
      ],
    ),
  );

  
  Widget _buildStepJadwal() => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(AppConstants.paddingL),
            decoration: BoxDecoration(
              color: AppColors.white,
              borderRadius: BorderRadius.circular(18),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.08),
                  blurRadius: 16,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'JAM OPERASIONAL',
                  style: AppTextStyles.caption.copyWith(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.7,
                    color: AppColors.secondary,
                  ),
                ),
                const SizedBox(height: AppConstants.paddingL),

                Text('Pilih Hari',
                    style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w600)),
                const SizedBox(height: AppConstants.paddingS),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: _hariList.map((hari) {
                    final isSelected = _selectedHari == hari;
                    return GestureDetector(
                      onTap: () => setState(() => _selectedHari = hari),
                      child: Container(
                        width: 46, height: 46,
                        decoration: BoxDecoration(
                          color: isSelected
                              ? AppColors.secondary
                              : const Color(0xFFF5F9EE),
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(
                            color: isSelected
                                ? AppColors.secondary
                                : const Color(0xFFD8EAB0),
                            width: 1.5,
                          ),
                        ),
                        child: Center(
                          child: Text(
                            hari,
                            style: AppTextStyles.body.copyWith(
                              fontWeight: FontWeight.w600,
                              fontSize: 12,
                              color: isSelected
                                  ? Colors.white
                                  : const Color(0xFF555555),
                            ),
                          ),
                        ),
                      ),
                    );
                  }).toList(),
                ),

                const SizedBox(height: AppConstants.paddingL),

                Text('Pilih Waktu Kedatangan',
                    style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w600)),
                const SizedBox(height: AppConstants.paddingS),
                Wrap(
                  spacing: 10,
                  children: _jamList.map((jam) {
                    final isSelected = _selectedJam == jam;
                    return GestureDetector(
                      onTap: () => setState(() => _selectedJam = jam),
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 16, vertical: 10),
                        decoration: BoxDecoration(
                          color: isSelected
                              ? AppColors.secondary
                              : const Color(0xFFF5F9EE),
                          borderRadius:
                              BorderRadius.circular(AppConstants.radiusXL),
                          border: Border.all(
                            color: isSelected
                                ? AppColors.secondary
                                : const Color(0xFFD8EAB0),
                            width: 1.5,
                          ),
                        ),
                        child: Text(
                          jam,
                          style: AppTextStyles.body.copyWith(
                            fontWeight: FontWeight.w600,
                            fontSize: 13,
                            color: isSelected
                                ? Colors.white
                                : const Color(0xFF555555),
                          ),
                        ),
                      ),
                    );
                  }).toList(),
                ),
              ],
            ),
          ),

          const SizedBox(height: AppConstants.paddingL),
          _buildEstimasiCard(),
        ],
      );

  
  Widget _buildStepKonfirmasi() => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('RINGKASAN SETORAN',
              style: AppTextStyles.title.copyWith(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  color: AppColors.secondary)),
          const SizedBox(height: AppConstants.paddingM),

          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(AppConstants.paddingM),
            decoration: BoxDecoration(
              color: const Color(0xFFF5F9EE),
              borderRadius: BorderRadius.circular(AppConstants.radiusM),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.08),
                  blurRadius: 16,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: Column(
              children: [
                _buildInfoRow('Total Berat', '${_totalBerat.toStringAsFixed(2)} kg'),
                const Divider(color: Color(0xFFD8EAB0)),
                _buildInfoRow('Total Poin', '$_totalPoin poin'),
                const Divider(color: Color(0xFFD8EAB0)),
                _buildInfoRow('Total Harga', 'Rp ${_formatRupiah(_totalHarga)}'),
              ],
            ),
          ),

          const SizedBox(height: AppConstants.paddingL),

          Text('DETAIL SAMPAH',
              style: AppTextStyles.title.copyWith(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: AppColors.secondary)),
          const SizedBox(height: AppConstants.paddingM),

          ..._validSampah.map((s) => Padding(
                padding: const EdgeInsets.only(bottom: AppConstants.paddingS),
                child: Container(
                  padding: const EdgeInsets.all(AppConstants.paddingM),
                  decoration: BoxDecoration(
                    border: Border.all(color: const Color(0xFFD8EAB0)),
                    borderRadius: BorderRadius.circular(AppConstants.radiusM),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(s['nama'],
                          style: AppTextStyles.body
                              .copyWith(fontWeight: FontWeight.w600)),
                      const SizedBox(height: 4),
                      Text(
                        '${_getJenisNama(s['id_jenis'])} · ${(s['berat'] as double).toStringAsFixed(1)} kg',
                        style: AppTextStyles.caption,
                      ),
                    ],
                  ),
                ),
              )),

          const SizedBox(height: AppConstants.paddingL),

          Container(
            padding: const EdgeInsets.all(AppConstants.paddingM),
            decoration: BoxDecoration(
              color: AppColors.primary.withOpacity(0.1),
              borderRadius: BorderRadius.circular(AppConstants.radiusM),
            ),
            child: Row(
              children: [
                const Icon(Icons.store_outlined, color: AppColors.secondary),
                const SizedBox(width: 8),
                Expanded(
                  child: Text('Metode: Antar Sendiri',
                      style: AppTextStyles.body
                          .copyWith(fontWeight: FontWeight.w500)),
                ),
              ],
            ),
          ),

          const SizedBox(height: AppConstants.paddingS),

          Container(
            padding: const EdgeInsets.all(AppConstants.paddingM),
            decoration: BoxDecoration(
              color: AppColors.primary.withOpacity(0.1),
              borderRadius: BorderRadius.circular(AppConstants.radiusM),
            ),
            child: Row(
              children: [
                const Icon(Icons.access_time_outlined, color: AppColors.secondary),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Hari $_selectedHari  ·  $_selectedJam',
                    style: AppTextStyles.body
                        .copyWith(fontWeight: FontWeight.w500),
                  ),
                ),
              ],
            ),
          ),
        ],
      );

  Widget _buildEstimasiCard() => Container(
        width: double.infinity,
        padding: const EdgeInsets.all(AppConstants.paddingL),
        decoration: BoxDecoration(
          color: AppColors.secondary,
          borderRadius: BorderRadius.circular(AppConstants.radiusM),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'ESTIMASI PENDAPATAN',
                    style: AppTextStyles.caption.copyWith(
                      color: Colors.white.withOpacity(0.85),
                      fontWeight: FontWeight.w700,
                      letterSpacing: 0.5,
                    ),
                  ),
                  const SizedBox(height: 8),
                  ..._validSampah.map((s) => Text(
                        '• ${_getJenisNama(s['id_jenis'])} ${(s['berat'] as double).toStringAsFixed(1)} kg',
                        style: AppTextStyles.caption
                            .copyWith(color: Colors.white.withOpacity(0.9)),
                      )),
                  const SizedBox(height: 6),
                  Text(
                    'Total: ${_totalBerat.toStringAsFixed(1)} kg sampah',
                    style: AppTextStyles.body.copyWith(
                        color: Colors.white, fontWeight: FontWeight.w700),
                  ),
                ],
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  '= Rp${_formatRupiah(_totalHarga)}',
                  style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w900,
                      fontSize: 22),
                ),
                Text(
                  '+$_totalPoin poin',
                  style: AppTextStyles.caption.copyWith(
                      color: Colors.white.withOpacity(0.85),
                      fontWeight: FontWeight.w600),
                ),
              ],
            ),
          ],
        ),
      );

  Widget _buildInfoRow(String l, String v) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(l, style: AppTextStyles.body),
            Text(v, style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w600)),
          ],
        ),
      );
}