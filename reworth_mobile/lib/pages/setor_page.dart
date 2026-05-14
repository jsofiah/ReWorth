import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../utils/app_colors.dart';
import '../utils/app_text_styles.dart';
import '../utils/app_constants.dart';
import '../services/auth_service.dart';

class JenisSampahModel {
  final String idJenis;
  final String namaJenis;
  final int hargaPerKg;

  JenisSampahModel({
    required this.idJenis,
    required this.namaJenis,
    required this.hargaPerKg,
  });

  factory JenisSampahModel.fromMap(Map<String, dynamic> map) {
    return JenisSampahModel(
      idJenis: map['id_jenis'] ?? '',
      namaJenis: map['nama_jenis'] ?? '',
      hargaPerKg: map['harga_per_kg'] ?? 0,
    );
  }
}

class SetorPage extends StatefulWidget {
  const SetorPage({super.key});

  @override
  State<SetorPage> createState() => _SetorPageState();
}

class _SetorPageState extends State<SetorPage> {
  final _supabase = Supabase.instance.client;
  
  List<Map<String, dynamic>> _sampahList = [];
  List<JenisSampahModel> _jenisSampahList = [];
  String _selectedMetode = 'Dijemput Petugas';
  bool _isLoading = true;
  int _currentStep = 0;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    await _loadJenisSampah();
    _initSampahList();
    setState(() => _isLoading = false);
  }

  Future<void> _loadJenisSampah() async {
    try {
      final data = await _supabase
          .from('jenis_sampah')
          .select('id_jenis, nama_jenis, harga_per_kg')
          .order('nama_jenis');
      
      _jenisSampahList = (data as List)
          .map((e) => JenisSampahModel.fromMap(e))
          .toList();
    } catch (e) {
      debugPrint('Error load jenis sampah: $e');
    }
  }

  void _initSampahList() {
    _sampahList = [
      {
        'nama': 'Botol Kaca dan Minuman',
        'id_jenis': _getJenisIdByName('Kaca'),
        'berat': 2.5,
      },
      {
        'nama': 'Kardus Bekas',
        'id_jenis': _getJenisIdByName('Kertas'),
        'berat': 1.8,
      },
      {
        'nama': '',
        'id_jenis': '',
        'berat': 0.0,
      },
    ];
  }

  String _getJenisIdByName(String nama) {
    final jenis = _jenisSampahList.firstWhere(
      (j) => j.namaJenis == nama,
      orElse: () => JenisSampahModel(idJenis: '', namaJenis: '', hargaPerKg: 0),
    );
    return jenis.idJenis;
  }

  String _getJenisNama(String idJenis) {
    final jenis = _jenisSampahList.firstWhere(
      (j) => j.idJenis == idJenis,
      orElse: () => JenisSampahModel(idJenis: '', namaJenis: '', hargaPerKg: 0),
    );
    return jenis.namaJenis;
  }

  double _getHargaPerKg(String idJenis) {
    final jenis = _jenisSampahList.firstWhere(
      (j) => j.idJenis == idJenis,
      orElse: () => JenisSampahModel(idJenis: '', namaJenis: '', hargaPerKg: 0),
    );
    return jenis.hargaPerKg.toDouble();
  }

  double get _totalBerat {
    double total = 0;
    for (var s in _sampahList) {
      total += s['berat'] as double;
    }
    return total;
  }

  int get _totalPoin {
    return (_totalBerat * 10).toInt();
  }

  double get _totalHarga {
    double total = 0;
    for (var s in _sampahList) {
      final idJenis = s['id_jenis'];
      if (idJenis.toString().isNotEmpty) {
        final harga = _getHargaPerKg(idJenis);
        final berat = s['berat'] as double;
        total += harga * berat;
      }
    }
    return total;
  }

  void _nextStep() {
    if (_currentStep == 0) {
      final validSampah = _sampahList.where((s) => 
        s['nama'].toString().isNotEmpty && 
        s['id_jenis'].toString().isNotEmpty && 
        (s['berat'] as double) > 0
      ).toList();
      
      if (validSampah.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Minimal tambah 1 sampah yang valid')),
        );
        return;
      }
    }
    
    setState(() {
      if (_currentStep < 2) _currentStep++;
    });
  }

  void _prevStep() {
    setState(() {
      if (_currentStep > 0) _currentStep--;
    });
  }

  Future<void> _submitSetor() async {
    setState(() => _isLoading = true);
    
    try {
      final user = AuthService.getCurrentUser();
      if (user == null) throw Exception('User tidak login');

      final validSampah = _sampahList.where((s) => 
        s['nama'].toString().isNotEmpty && 
        s['id_jenis'].toString().isNotEmpty && 
        (s['berat'] as double) > 0
      ).toList();

      final idSetor = user.id;
      
      await _supabase.from('setor').insert({
        'id_setor': idSetor,
        'id_pengguna': user.id,
        'metode_pengambilan': _selectedMetode,
        'status': 'pending',
        'created_at': DateTime.now().toIso8601String(),
      });
      
      for (var sampah in validSampah) {
        final hargaPerKg = _getHargaPerKg(sampah['id_jenis']);
        final berat = sampah['berat'] as double;
        final subtotal = hargaPerKg * berat;
        
        await _supabase.from('detail_setor').insert({
          'id_setor': idSetor,
          'id_jenis': sampah['id_jenis'],
          'berat': berat,
          'harga_per_kg': hargaPerKg,
          'subtotal': subtotal,
        });
      }
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Setor sampah berhasil!'), backgroundColor: Colors.green),
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
      if (mounted) setState(() => _isLoading = false);
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
                        : Column(
                            children: [
                              _buildStepper(),
                              const SizedBox(height: AppConstants.paddingL),
                              Expanded(
                                child: SingleChildScrollView(
                                  padding: const EdgeInsets.symmetric(horizontal: AppConstants.paddingL),
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      if (_currentStep == 0) _buildStepInformasi(),
                                      if (_currentStep == 1) _buildStepAlamatJadwal(),
                                      if (_currentStep == 2) _buildStepKonfirmasi(),
                                      const SizedBox(height: AppConstants.paddingXL),
                                    ],
                                  ),
                                ),
                              ),
                              _buildBottomButtons(),
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
            child: Text('Setor Sampah', style: AppTextStyles.namafitur, textAlign: TextAlign.center),
          ),
          const SizedBox(width: 38),
        ],
      ),
    );
  }

  Widget _buildStepper() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: AppConstants.paddingL),
      child: Row(
        children: [
          _buildStep(1, 'Informasi', _currentStep == 0, _currentStep > 0),
          Expanded(child: Container(height: 2, color: _currentStep >= 1 ? AppColors.secondary : AppColors.divider)),
          _buildStep(2, 'Alamat & Jadwal', _currentStep == 1, _currentStep > 1),
          Expanded(child: Container(height: 2, color: _currentStep >= 2 ? AppColors.secondary : AppColors.divider)),
          _buildStep(3, 'Konfirmasi', _currentStep == 2, _currentStep > 2),
        ],
      ),
    );
  }

  Widget _buildStep(int number, String label, bool isActive, bool isCompleted) {
    Color getColor() {
      if (isActive || isCompleted) return AppColors.secondary;
      return AppColors.divider;
    }
    
    return Column(
      children: [
        Container(
          width: 32,
          height: 32,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: isActive || isCompleted ? AppColors.secondary : AppColors.white,
            border: Border.all(color: getColor(), width: 1.5),
          ),
          child: Center(
            child: isCompleted
                ? const Icon(Icons.check, size: 18, color: Colors.white)
                : Text('$number', style: TextStyle(color: isActive ? Colors.white : AppColors.textSecondary, fontWeight: FontWeight.bold, fontSize: 14)),
          ),
        ),
        const SizedBox(height: 4),
        Text(label, style: AppTextStyles.caption.copyWith(color: getColor(), fontWeight: isActive ? FontWeight.w600 : FontWeight.w400)),
      ],
    );
  }

  Widget _buildBottomButtons() {
    return Padding(
      padding: const EdgeInsets.all(AppConstants.paddingL),
      child: Row(
        children: [
          if (_currentStep > 0)
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
          if (_currentStep > 0) const SizedBox(width: AppConstants.paddingM),
          Expanded(
            child: ElevatedButton(
              onPressed: _currentStep == 2 ? _submitSetor : _nextStep,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.secondary,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppConstants.radiusXL)),
                padding: const EdgeInsets.symmetric(vertical: 14),
              ),
              child: Text(_currentStep == 2 ? 'Kirim Setoran' : 'Lanjut', style: AppTextStyles.buttonLabel.copyWith(color: Colors.white)),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStepInformasi() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('DAFTAR SAMPAH', style: AppTextStyles.title.copyWith(fontSize: 16, fontWeight: FontWeight.w700, color: AppColors.secondary)),
        const SizedBox(height: AppConstants.paddingM),
        ..._sampahList.asMap().entries.map((entry) {
          final index = entry.key;
          final sampah = entry.value;
          final bool isLast = index == _sampahList.length - 1;
          return Padding(
            padding: const EdgeInsets.only(bottom: AppConstants.paddingM),
            child: _buildSampahCard(index: index + 1, sampah: sampah, isLast: isLast),
          );
        }),
        Center(
          child: TextButton.icon(
            onPressed: () => setState(() => _sampahList.add({'nama': '', 'id_jenis': '', 'berat': 0.0})),
            icon: const Icon(Icons.add_circle_outline, color: AppColors.secondary),
            label: Text('Tambah Jenis Sampah Lain', style: AppTextStyles.body.copyWith(color: AppColors.secondary, fontWeight: FontWeight.w600)),
          ),
        ),
        const Divider(color: AppColors.divider),
        const SizedBox(height: AppConstants.paddingL),
        Text('METODE PENGAMBILAN', style: AppTextStyles.title.copyWith(fontSize: 16, fontWeight: FontWeight.w700, color: AppColors.secondary)),
        const SizedBox(height: AppConstants.paddingM),
        Container(
          decoration: BoxDecoration(color: AppColors.inputBorder.withOpacity(0.2), borderRadius: BorderRadius.circular(AppConstants.radiusM)),
          child: Column(
            children: [
              _buildMetodeOption(title: 'Dijemput Petugas', subtitle: 'Petugas datang ke lokasi', value: 'Dijemput Petugas', groupValue: _selectedMetode),
              _buildMetodeOption(title: 'Antar Sendiri', subtitle: 'Bawa ke bank sampah', value: 'Antar Sendiri', groupValue: _selectedMetode),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildStepAlamatJadwal() {
    return Container(
      padding: const EdgeInsets.all(AppConstants.paddingL),
      decoration: BoxDecoration(color: AppColors.inputBorder.withOpacity(0.1), borderRadius: BorderRadius.circular(AppConstants.radiusM)),
      child: Column(
        children: [
          const Icon(Icons.location_on_outlined, size: 48, color: AppColors.secondary),
          const SizedBox(height: AppConstants.paddingM),
          Text('Alamat Penjemputan', style: AppTextStyles.title.copyWith(fontSize: 16, fontWeight: FontWeight.w600)),
          const SizedBox(height: AppConstants.paddingS),
          Text('Alamat akan diisi sesuai data profil Anda', style: AppTextStyles.body.copyWith(color: AppColors.textSecondary), textAlign: TextAlign.center),
          const SizedBox(height: AppConstants.paddingL),
          const Icon(Icons.calendar_today_outlined, size: 48, color: AppColors.secondary),
          const SizedBox(height: AppConstants.paddingM),
          Text('Jadwal Penjemputan', style: AppTextStyles.title.copyWith(fontSize: 16, fontWeight: FontWeight.w600)),
          const SizedBox(height: AppConstants.paddingS),
          Text('Pilih tanggal dan jam penjemputan', style: AppTextStyles.body.copyWith(color: AppColors.textSecondary), textAlign: TextAlign.center),
        ],
      ),
    );
  }

  Widget _buildStepKonfirmasi() {
    final validSampah = _sampahList.where((s) => s['nama'].toString().isNotEmpty && s['id_jenis'].toString().isNotEmpty && (s['berat'] as double) > 0).toList();
    
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('RINGKASAN SETORAN', style: AppTextStyles.title.copyWith(fontSize: 16, fontWeight: FontWeight.w700, color: AppColors.secondary)),
        const SizedBox(height: AppConstants.paddingM),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(AppConstants.paddingM),
          decoration: BoxDecoration(color: AppColors.inputBorder.withOpacity(0.1), borderRadius: BorderRadius.circular(AppConstants.radiusM)),
          child: Column(
            children: [
              _buildInfoRow('Total Berat', '${_totalBerat.toStringAsFixed(2)} kg'),
              const Divider(),
              _buildInfoRow('Total Poin', '${_totalPoin} poin'),
              const Divider(),
              _buildInfoRow('Total Harga', 'Rp ${_totalHarga.toStringAsFixed(0)}'),
            ],
          ),
        ),
        const SizedBox(height: AppConstants.paddingL),
        Text('DETAIL SAMPAH', style: AppTextStyles.title.copyWith(fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.secondary)),
        const SizedBox(height: AppConstants.paddingM),
        ...validSampah.map((s) {
          final jenisNama = _getJenisNama(s['id_jenis']);
          final berat = s['berat'] as double;
          return Padding(
            padding: const EdgeInsets.only(bottom: AppConstants.paddingS),
            child: Container(
              padding: const EdgeInsets.all(AppConstants.paddingM),
              decoration: BoxDecoration(border: Border.all(color: AppColors.inputBorder), borderRadius: BorderRadius.circular(AppConstants.radiusM)),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(s['nama'], style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w600)),
                  const SizedBox(height: 4),
                  Text('$jenisNama · ${berat.toStringAsFixed(1)} kg', style: AppTextStyles.caption),
                ],
              ),
            ),
          );
        }),
        const SizedBox(height: AppConstants.paddingL),
        Container(
          padding: const EdgeInsets.all(AppConstants.paddingM),
          decoration: BoxDecoration(color: AppColors.primary.withOpacity(0.1), borderRadius: BorderRadius.circular(AppConstants.radiusM)),
          child: Row(
            children: [
              const Icon(Icons.local_shipping_outlined, color: AppColors.secondary),
              const SizedBox(width: 8),
              Expanded(child: Text('Metode: $_selectedMetode', style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w500))),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: AppTextStyles.body),
          Text(value, style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }

  Widget _buildSampahCard({required int index, required Map<String, dynamic> sampah, required bool isLast}) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppConstants.radiusM),
        border: Border.all(color: AppColors.inputBorder, width: 1),
      ),
      child: Padding(
        padding: const EdgeInsets.all(AppConstants.paddingM),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 28,
                  height: 28,
                  decoration: BoxDecoration(color: AppColors.secondary, borderRadius: BorderRadius.circular(8)),
                  child: Center(child: Text('$index', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14))),
                ),
                const SizedBox(width: 12),
                Text('Sampah #$index', style: AppTextStyles.title.copyWith(fontSize: 15, fontWeight: FontWeight.w600)),
              ],
            ),
            const SizedBox(height: AppConstants.paddingM),
            Text('Nama Sampah', style: AppTextStyles.caption.copyWith(fontWeight: FontWeight.w500, color: AppColors.textSecondary)),
            const SizedBox(height: 4),
            isLast
                ? Container(
                    decoration: BoxDecoration(color: AppColors.inputBorder.withOpacity(0.15), border: Border.all(color: AppColors.inputBorder, width: 1), borderRadius: BorderRadius.circular(10)),
                    child: TextField(
                      onChanged: (value) => setState(() => sampah['nama'] = value),
                      style: AppTextStyles.body,
                      decoration: InputDecoration(
                        hintText: 'Nama sampah yang ingin disetor...',
                        hintStyle: AppTextStyles.caption.copyWith(color: Colors.grey),
                        border: InputBorder.none,
                        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                      ),
                    ),
                  )
                : Text(sampah['nama'], style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w500)),
            const SizedBox(height: AppConstants.paddingM),
            Row(
              children: [
                Expanded(
                  flex: 2,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Jenis Sampah', style: AppTextStyles.caption.copyWith(fontWeight: FontWeight.w500, color: AppColors.textSecondary)),
                      const SizedBox(height: 4),
                      isLast
                          ? Container(
                              decoration: BoxDecoration(color: AppColors.inputBorder.withOpacity(0.15), border: Border.all(color: AppColors.inputBorder, width: 1), borderRadius: BorderRadius.circular(10)),
                              padding: const EdgeInsets.symmetric(horizontal: 12),
                              child: DropdownButtonHideUnderline(
                                child: DropdownButton<String>(
                                  value: sampah['id_jenis'].toString().isEmpty ? null : sampah['id_jenis'],
                                  isExpanded: true,
                                  hint: Text('Pilih jenis', style: AppTextStyles.caption.copyWith(color: Colors.grey)),
                                  items: _jenisSampahList.map((jenis) => DropdownMenuItem(value: jenis.idJenis, child: Text(jenis.namaJenis, style: AppTextStyles.body))).toList(),
                                  onChanged: (value) => setState(() => sampah['id_jenis'] = value),
                                ),
                              ),
                            )
                          : Text(_getJenisNama(sampah['id_jenis']), style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w500)),
                    ],
                  ),
                ),
                const SizedBox(width: AppConstants.paddingM),
                Expanded(
                  flex: 1,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Berat (kg)', style: AppTextStyles.caption.copyWith(fontWeight: FontWeight.w500, color: AppColors.textSecondary)),
                      const SizedBox(height: 4),
                      isLast
                          ? Container(
                              decoration: BoxDecoration(color: AppColors.inputBorder.withOpacity(0.15), border: Border.all(color: AppColors.inputBorder, width: 1), borderRadius: BorderRadius.circular(10)),
                              child: TextField(
                                onChanged: (value) => setState(() => sampah['berat'] = double.tryParse(value) ?? 0),
                                keyboardType: TextInputType.number,
                                style: AppTextStyles.body,
                                decoration: InputDecoration(
                                  hintText: '0.0',
                                  border: InputBorder.none,
                                  contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                                ),
                              ),
                            )
                          : Text('${sampah['berat']} kg', style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w500)),
                    ],
                  ),
                ),
              ],
            ),
            if (isLast && _sampahList.length > 1)
              Padding(
                padding: const EdgeInsets.only(top: AppConstants.paddingM),
                child: Align(
                  alignment: Alignment.centerRight,
                  child: TextButton.icon(
                    onPressed: () => setState(() => _sampahList.removeLast()),
                    icon: const Icon(Icons.delete_outline, size: 16, color: Colors.red),
                    label: Text('Hapus', style: AppTextStyles.caption.copyWith(color: Colors.red)),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildMetodeOption({required String title, required String subtitle, required String value, required String groupValue}) {
    final isSelected = groupValue == value;
    return GestureDetector(
      onTap: () => setState(() => _selectedMetode = value),
      child: Container(
        padding: const EdgeInsets.all(AppConstants.paddingM),
        decoration: BoxDecoration(color: isSelected ? AppColors.primary.withValues(alpha: 0.1) : Colors.transparent, borderRadius: BorderRadius.circular(AppConstants.radiusM)),
        child: Row(
          children: [
            Radio<String>(
              value: value,
              groupValue: groupValue,
              onChanged: (v) => setState(() => _selectedMetode = v!),
              activeColor: AppColors.secondary,
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w600)),
                  Text(subtitle, style: AppTextStyles.caption.copyWith(color: AppColors.textSecondary)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}