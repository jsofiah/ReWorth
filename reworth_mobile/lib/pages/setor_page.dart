import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:dotted_border/dotted_border.dart';
import '../utils/app_colors.dart';
import '../utils/app_text_styles.dart';
import '../utils/app_constants.dart';
import '../services/auth_service.dart';
import 'jemput_page.dart';  
import 'antar_page.dart';  

class JenisSampahModel {
  final String idJenis;
  final String namaSampah;
  final int hargaPerKg;
  
  JenisSampahModel({required this.idJenis, required this.namaSampah, required this.hargaPerKg});
  
  factory JenisSampahModel.fromMap(Map<String, dynamic> map) => JenisSampahModel(
    idJenis: map['id_jenis'] ?? '',
    namaSampah: map['nama_sampah'] ?? '',
    hargaPerKg: map['harga_per_kg'] ?? 0,
  );
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
    _sampahList = [
      {'nama': '', 'id_jenis': '', 'berat': 0.0},
    ];
    setState(() => _isLoading = false);
  }

  Future<void> _loadJenisSampah() async {
    final data = await _supabase
        .from('jenis_sampah')
        .select('id_jenis, nama_sampah, harga_per_kg')
        .order('nama_sampah');
    _jenisSampahList = (data as List).map((e) => JenisSampahModel.fromMap(e)).toList();
  }

  String _getJenisNama(String idJenis) => _jenisSampahList.firstWhere(
    (j) => j.idJenis == idJenis, 
    orElse: () => JenisSampahModel(idJenis: '', namaSampah: '', hargaPerKg: 0)
  ).namaSampah;
  
  double _getHargaPerKg(String idJenis) => _jenisSampahList.firstWhere(
    (j) => j.idJenis == idJenis, 
    orElse: () => JenisSampahModel(idJenis: '', namaSampah: '', hargaPerKg: 0)
  ).hargaPerKg.toDouble();
  
  double get _totalBerat => _sampahList.fold(0.0, (s, e) => s + (e['berat'] as double));
  int get _totalPoin => (_totalBerat * 10).toInt();
  double get _totalHarga => _sampahList.fold(0.0, (s, e) => s + (_getHargaPerKg(e['id_jenis']) * (e['berat'] as double)));

  void _nextStep() {
    if (_currentStep == 0) {
      final valid = _sampahList.where((s) => 
        s['nama'].toString().isNotEmpty && 
        s['id_jenis'].toString().isNotEmpty && 
        s['berat'] > 0
      ).toList();
      
      if (valid.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Minimal isi 1 sampah'))
        );
        return;
      }
      
      // Navigasi berdasarkan metode yang dipilih
      if (_selectedMetode == 'Dijemput Petugas') {
        Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => const JemputPage()),
        );
      } else {
        // Antar Sendiri - langsung ke AntarPage
        Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => const AntarPage()),
        );
      }
    } else if (_currentStep < 2) {
      setState(() => _currentStep++);
    }
  }
  // ========== END ==========

  void _prevStep() { if (_currentStep > 0) setState(() => _currentStep--); }

  Future<void> _submitSetor() async {
    final valid = _sampahList.where((s) => s['nama'].toString().isNotEmpty && s['id_jenis'].toString().isNotEmpty && s['berat'] > 0).toList();
    if (valid.isEmpty) return;
    setState(() => _isLoading = true);
    try {
      final user = AuthService.getCurrentUser();
      if (user == null) throw Exception('User tidak login');
      await _supabase.from('setor').insert({
        'id_setor': user.id, 
        'id_pengguna': user.id, 
        'metode_pengambilan': _selectedMetode, 
        'status': 'pending', 
        'created_at': DateTime.now().toIso8601String()
      });
      for (var s in valid) {
        final harga = _getHargaPerKg(s['id_jenis']);
        await _supabase.from('detail_setor').insert({
          'id_setor': user.id, 
          'id_jenis': s['id_jenis'], 
          'berat': s['berat'], 
          'harga_per_kg': harga, 
          'subtotal': harga * s['berat']
        });
      }
      await _supabase.from('riwayat_aktivitas').insert({
        'id_pengguna': user.id, 
        'jenis_aktivitas': 'setor_sampah', 
        'id_referensi': user.id,
        'judul': 'Setor Sampah', 
        'deskripsi': 'Anda melakukan setor sampah dengan total ${_totalBerat.toStringAsFixed(2)} kg',
        'status': 'diproses', 
        'perubahan_poin': _totalPoin, 
        'perubahan_saldo': _totalHarga,
        'created_at': DateTime.now().toIso8601String(),
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Setor sampah berhasil!'), backgroundColor: Colors.green));
        Navigator.pop(context);
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Gagal: $e'), backgroundColor: Colors.red));
    } finally { if (mounted) setState(() => _isLoading = false); }
  }

  @override
  Widget build(BuildContext context) {
    final topPad = MediaQuery.of(context).padding.top;
    return Scaffold(
      backgroundColor: const Color(0xFFF2F2F2),
      body: Stack(children: [
        Positioned(top: 0, left: 0, right: 0, height: topPad + 110, child: Image.asset('assets/gradient.png', fit: BoxFit.cover)),
        SafeArea(bottom: false, child: Column(children: [
          _buildTitleBar(),
          const SizedBox(height: 12),
          Expanded(child: Container(
            decoration: const BoxDecoration(color: AppColors.white, borderRadius: BorderRadius.only(topLeft: Radius.circular(AppConstants.radiusXL), topRight: Radius.circular(AppConstants.radiusXL))),
            child: _isLoading ? const Center(child: CircularProgressIndicator()) : Column(children: [
              _buildStepper(),
              const SizedBox(height: AppConstants.paddingL),
              Expanded(child: SingleChildScrollView(padding: const EdgeInsets.symmetric(horizontal: AppConstants.paddingL), child: Column(
                children: [
                  if (_currentStep == 0) _buildStepInformasi(),
                  if (_currentStep == 1) _buildStepAlamatJadwal(),
                  if (_currentStep == 2) _buildStepKonfirmasi(),
                  const SizedBox(height: AppConstants.paddingXL),
                ],
              ))),
              _buildBottomButtons(),
            ]),
          )),
        ])),
      ]),
    );
  }

  Widget _buildTitleBar() => Padding(padding: const EdgeInsets.symmetric(horizontal: AppConstants.paddingM, vertical: AppConstants.paddingM), child: Row(children: [
    GestureDetector(onTap: () => Navigator.pop(context), child: Container(width: 38, height: 38, decoration: BoxDecoration(color: Colors.white.withOpacity(0.55), shape: BoxShape.circle), child: const Icon(Icons.chevron_left_rounded, color: Color(0xFF1A2800), size: 26))),
    Expanded(child: Text('Setor Sampah', style: AppTextStyles.namafitur, textAlign: TextAlign.center)),
    const SizedBox(width: 38),
  ]));

  Widget _buildStepper() => Padding(
    padding: const EdgeInsets.fromLTRB(
      AppConstants.paddingL, 
      AppConstants.paddingXL,
      AppConstants.paddingL, 
      0
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        _buildStep(1, 'Informasi', _currentStep == 0, _currentStep > 0),
        Expanded(
          child: Container(
            height: 2,
            margin: const EdgeInsets.only(bottom: 16),
            color: _currentStep >= 1 ? AppColors.secondary : const Color(0xFFE0E0E0),
          ),
        ),
        _buildStep(2, 'Alamat & Jadwal', _currentStep == 1, _currentStep > 1),
        Expanded(
          child: Container(
            height: 2,
            margin: const EdgeInsets.only(bottom: 16),
            color: _currentStep >= 2 ? AppColors.secondary : const Color(0xFFE0E0E0),
          ),
        ),
        _buildStep(3, 'Konfirmasi', _currentStep == 2, _currentStep > 2),
      ],
    ),
  );

  Widget _buildStep(int n, String l, bool a, bool c) => Column(children: [
    Container(width: 32, height: 32, decoration: BoxDecoration(shape: BoxShape.circle, color: a || c ? AppColors.secondary : Colors.white, border: Border.all(color: a || c ? AppColors.secondary : const Color(0xFFE0E0E0), width: 1.5)),
      child: Center(child: c ? const Icon(Icons.check, size: 18, color: Colors.white) : Text('$n', style: TextStyle(color: a ? Colors.white : const Color(0xFF999999), fontWeight: FontWeight.bold, fontSize: 14)))),
    const SizedBox(height: 4),
    Text(l, style: AppTextStyles.caption.copyWith(color: a || c ? AppColors.secondary : const Color(0xFF999999), fontWeight: a ? FontWeight.w600 : FontWeight.w400)),
  ]);

  Widget _buildBottomButtons() => Padding(
    padding: const EdgeInsets.all(AppConstants.paddingL),
    child: Row(children: [
      if (_currentStep > 0)
        Expanded(child: OutlinedButton(
          onPressed: _prevStep,
          style: OutlinedButton.styleFrom(
            side: BorderSide(color: AppColors.secondary),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppConstants.radiusXL)),
            padding: const EdgeInsets.symmetric(vertical: 14),
          ),
          child: Text('Kembali', style: AppTextStyles.body.copyWith(color: AppColors.secondary, fontWeight: FontWeight.w600)),
        )),
      if (_currentStep > 0) const SizedBox(width: AppConstants.paddingM),
      Expanded(
        child: ElevatedButton(
          onPressed: _currentStep == 2 ? _submitSetor : _nextStep,
          style: ElevatedButton.styleFrom(
            backgroundColor: AppColors.secondary,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppConstants.radiusXL)),
            padding: const EdgeInsets.symmetric(vertical: 14),
          ),
          child: Text(
            _currentStep == 2 ? 'Kirim Setoran' : 'Lanjut ke Alamat & Jadwal',
            style: AppTextStyles.buttonLabel.copyWith(color: Colors.white),
          ),
        ),
      ),
    ]),
  );

  Widget _buildStepInformasi() => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.08),
              blurRadius: 16,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('DAFTAR SAMPAH', style: AppTextStyles.title.copyWith(fontSize: 16, fontWeight: FontWeight.w700, color: AppColors.secondary)),
              const SizedBox(height: AppConstants.paddingM),
              ..._sampahList.asMap().entries.map((e) => Padding(
                padding: const EdgeInsets.only(bottom: AppConstants.paddingM),
                child: _buildSampahCard(index: e.key + 1, sampah: e.value, isLast: e.key == _sampahList.length - 1),
              )),
              Center(
                child: InkWell(
                  onTap: () => setState(() => _sampahList.add({'nama': '', 'id_jenis': '', 'berat': 0.0})),
                  child: DottedBorder(
                    color: AppColors.secondary,
                    strokeWidth: 1.5,
                    dashPattern: const [6, 4],
                    borderType: BorderType.RRect,
                    radius: const Radius.circular(14),
                    child: Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(Icons.add, color: AppColors.secondary, size: 20),
                          const SizedBox(width: 8),
                          Text('Tambah Jenis Sampah Lain', style: AppTextStyles.body.copyWith(color: AppColors.secondary, fontWeight: FontWeight.w600)),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
      const SizedBox(height: AppConstants.paddingL),
      Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.08),
              blurRadius: 16,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Padding(padding: const EdgeInsets.fromLTRB(16, 16, 16, 0), child: Text('METODE PENGAMBILAN', style: AppTextStyles.caption.copyWith(fontSize: 11, fontWeight: FontWeight.w700, letterSpacing: 0.7, color: AppColors.secondary))),
          const SizedBox(height: 15),
          Padding(padding: const EdgeInsets.fromLTRB(16, 0, 16, 16), child: Row(children: [
            _buildMetodeCard('Dijemput Petugas', 'Petugas datang ke lokasi', _selectedMetode == 'Dijemput Petugas'),
            const SizedBox(width: 12),
            _buildMetodeCard('Antar Sendiri', 'Bawa ke bank sampah', _selectedMetode == 'Antar Sendiri'),
          ])),
        ]),
      ),
    ],
  );

  Widget _buildMetodeCard(String title, String subtitle, bool isSelected) => Expanded(child: GestureDetector(
    onTap: () => setState(() => _selectedMetode = title),
    child: Container(
      height: 100,
      decoration: BoxDecoration(
        color: isSelected ? null : const Color(0xFFF8F9F6),
        gradient: isSelected ? const LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [Color(0x14CCE34A), Color(0x1ABBDE2D)]) : null,
        border: Border.all(color: isSelected ? AppColors.secondary : const Color(0xFFE0EAD0), width: 1),
        borderRadius: BorderRadius.circular(13),
      ),
      child: Padding(padding: const EdgeInsets.all(13), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(title, style: AppTextStyles.body.copyWith(fontSize: 12, fontWeight: FontWeight.w700, color: isSelected ? AppColors.secondary : const Color(0xFF555555))),
        const SizedBox(height: 4),
        Text(subtitle, style: AppTextStyles.caption.copyWith(fontSize: 10, color: isSelected ? AppColors.secondary.withOpacity(0.7) : const Color(0xFFAAAAAA))),
      ])),
    ),
  ));

  Widget _buildStepAlamatJadwal() => Container(
    padding: const EdgeInsets.all(AppConstants.paddingL),
    decoration: BoxDecoration(color: AppColors.inputBorder.withOpacity(0.1), borderRadius: BorderRadius.circular(AppConstants.radiusM)),
    child: Column(children: [
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
    ]),
  );

  Widget _buildStepKonfirmasi() {
    final valid = _sampahList.where((s) => s['nama'].toString().isNotEmpty && s['id_jenis'].toString().isNotEmpty && s['berat'] > 0).toList();
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text('RINGKASAN SETORAN', style: AppTextStyles.title.copyWith(fontSize: 16, fontWeight: FontWeight.w700, color: AppColors.secondary)),
      const SizedBox(height: AppConstants.paddingM),
      Container(
        width: double.infinity,
        padding: const EdgeInsets.all(AppConstants.paddingM),
        decoration: BoxDecoration(
          color: AppColors.inputBorder.withOpacity(0.1),
          borderRadius: BorderRadius.circular(AppConstants.radiusM),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.08),
              blurRadius: 16,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(children: [
          _buildInfoRow('Total Berat', '${_totalBerat.toStringAsFixed(2)} kg'),
          const Divider(),
          _buildInfoRow('Total Poin', '${_totalPoin} poin'),
          const Divider(),
          _buildInfoRow('Total Harga', 'Rp ${_totalHarga.toStringAsFixed(0)}'),
        ]),
      ),
      const SizedBox(height: AppConstants.paddingL),
      Text('DETAIL SAMPAH', style: AppTextStyles.title.copyWith(fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.secondary)),
      const SizedBox(height: AppConstants.paddingM),
      ...valid.map((s) => Padding(padding: const EdgeInsets.only(bottom: AppConstants.paddingS), child: Container(
        padding: const EdgeInsets.all(AppConstants.paddingM),
        decoration: BoxDecoration(border: Border.all(color: AppColors.inputBorder), borderRadius: BorderRadius.circular(AppConstants.radiusM)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(s['nama'], style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w600)),
          const SizedBox(height: 4),
          Text('${_getJenisNama(s['id_jenis'])} · ${(s['berat'] as double).toStringAsFixed(1)} kg', style: AppTextStyles.caption),
        ]),
      ))),
      const SizedBox(height: AppConstants.paddingL),
      Container(padding: const EdgeInsets.all(AppConstants.paddingM), decoration: BoxDecoration(color: AppColors.primary.withOpacity(0.1), borderRadius: BorderRadius.circular(AppConstants.radiusM)),
        child: Row(children: [const Icon(Icons.local_shipping_outlined, color: AppColors.secondary), const SizedBox(width: 8), Expanded(child: Text('Metode: $_selectedMetode', style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w500)))]),
      ),
    ]);
  }

  Widget _buildInfoRow(String l, String v) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 8),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(l, style: AppTextStyles.body),
      Text(v, style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w600)),
    ]),
  );

  Widget _buildSampahCard({required int index, required Map<String, dynamic> sampah, required bool isLast}) {
    final bool isEmpty = sampah['nama'].toString().isEmpty && sampah['id_jenis'].toString().isEmpty;
    
    Widget cardContent = Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Container(padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 4), decoration: BoxDecoration(color: const Color(0xFFE8F5D0), border: Border.all(color: const Color(0xFF013A0C), width: 1), borderRadius: BorderRadius.circular(100)),
              child: Text('Sampah #$index', style: AppTextStyles.small.copyWith(fontWeight: FontWeight.w700, color: const Color(0xFF013A0C)))),
            if (!isLast) GestureDetector(onTap: () => setState(() => _sampahList.removeAt(index - 1)), child: Container(width: 26, height: 26, decoration: BoxDecoration(color: Colors.white, border: Border.all(color: const Color(0xFFF0D8D8), width: 1), borderRadius: BorderRadius.circular(8)), child: const Icon(Icons.close, size: 14, color: Colors.red))),
          ]),
          const SizedBox(height: 16),
          Text('Nama Sampah', style: AppTextStyles.caption.copyWith(fontWeight: FontWeight.w600, color: const Color(0xFF555555))),
          const SizedBox(height: 6),
          Container(
            decoration: BoxDecoration(
              border: Border.all(color: AppColors.secondary, width: 1),
              borderRadius: BorderRadius.circular(8),
            ),
            child: TextField(
              onChanged: (v) => setState(() => sampah['nama'] = v),
              style: AppTextStyles.body,
              decoration: InputDecoration(
                hintText: 'Nama sampah yang ingin disetor...',
                hintStyle: AppTextStyles.caption,
                border: InputBorder.none,
                isDense: true,
                filled: true,
                fillColor: Colors.white,
                contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
              ),
            ),
          ),
          const SizedBox(height: 16),
          Row(children: [
            Expanded(flex: 2, child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('Jenis Sampah', style: AppTextStyles.caption.copyWith(fontWeight: FontWeight.w600, color: const Color(0xFF555555))),
              const SizedBox(height: 6),
              Container(
                decoration: BoxDecoration(
                  border: Border.all(color: AppColors.secondary, width: 1),
                  borderRadius: BorderRadius.circular(8),
                ),
                padding: const EdgeInsets.symmetric(horizontal: 8),
                child: DropdownButton<String>(
                  value: sampah['id_jenis'].toString().isEmpty ? null : sampah['id_jenis'],
                  isExpanded: true,
                  hint: Text('Pilih jenis', style: AppTextStyles.caption.copyWith(color: const Color(0xFFAAAAAA))),
                  underline: const SizedBox(),
                  icon: const Icon(Icons.arrow_drop_down, color: AppColors.secondary, size: 20),
                  items: _jenisSampahList.map((j) => DropdownMenuItem(value: j.idJenis, child: Text(j.namaSampah, style: AppTextStyles.body))).toList(),
                  onChanged: (v) => setState(() => sampah['id_jenis'] = v),
                ),
              ),
            ])),
            const SizedBox(width: 12),
            Expanded(flex: 1, child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('Berat (kg)', style: AppTextStyles.caption.copyWith(fontWeight: FontWeight.w600, color: const Color(0xFF555555))),
              const SizedBox(height: 6),
              Container(
                decoration: BoxDecoration(
                  border: Border.all(color: AppColors.secondary, width: 1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: TextField(
                  onChanged: (v) => setState(() => sampah['berat'] = double.tryParse(v) ?? 0),
                  keyboardType: TextInputType.number,
                  style: AppTextStyles.body,
                  decoration: InputDecoration(
                    hintText: '0.0',
                    hintStyle: AppTextStyles.caption,
                    border: InputBorder.none,
                    isDense: true,
                    filled: true,
                    fillColor: Colors.white,
                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                  ),
                ),
              ),
            ])),
          ]),
        ],
      ),
    );
    
    if (isEmpty) {
      return Container(
        decoration: BoxDecoration(
          color: const Color(0xFFF5F9EE),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFFD8EAB0), width: 1),
        ),
        child: cardContent,
      );
    }
    
    return Container(
      decoration: BoxDecoration(
        color: const Color(0xFFF5F9EE),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFD8EAB0), width: 1),
      ),
      child: cardContent,
    );
  }
}