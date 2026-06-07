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
  double _totalHarga = 0;
  int _currentStep = 1;
  
  List<Map<String, dynamic>> _jadwalList = [];
  Map<String, dynamic>? _selectedJadwal;
  String _selectedTanggal = '';
  String _selectedWaktuMulai = '';
  String _selectedWaktuSelesai = '';
  String _selectedHari = '';
  
  String _alamat = '';
  bool _isLoading = true;
  bool _isSubmitting = false;

  
  final Color _darkGreen = AppColors.secondary;
  final Color _lightGreenBg = const Color(0xFFF8FBF4);
  final Color _borderGreen = const Color(0xFFD8E8C0);
  final Color _greyAddress = AppColors.textSecondary;

  int get _totalPoin => 10; 

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    try {
      _validSampah = widget.sampahList ?? [];
      _totalBerat = widget.totalBerat ?? 0;
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
      final now = DateTime.now();
      final year = now.year;
      final month = now.month;
      
      final startDate = '$year-$month-01';
      final nextMonth = month == 12 ? 1 : month + 1;
      final nextYear = month == 12 ? year + 1 : year;
      final endDate = '$nextYear-$nextMonth-01';
      
      final data = await _supabase
          .from('jadwal_ambil')
          .select('id_jadwal, tanggal, waktu_mulai, waktu_selesai, kuota')
          .gte('tanggal', startDate)
          .lt('tanggal', endDate)
          .order('tanggal', ascending: true);
      
      
      final Map<String, List<Map<String, dynamic>>> grouped = {};
      for (var jadwal in data) {
        final tanggal = jadwal['tanggal'];
        if (!grouped.containsKey(tanggal)) {
          grouped[tanggal] = [];
        }
        grouped[tanggal]!.add({
          'id_jadwal': jadwal['id_jadwal'],
          'waktu_mulai': jadwal['waktu_mulai'],
          'waktu_selesai': jadwal['waktu_selesai'],
          'kuota': jadwal['kuota'],
        });
      }
      
      
      _jadwalList = [];
      final lastDay = DateTime(year, month + 1, 0);
      
      for (int day = 1; day <= lastDay.day; day++) {
        final tanggalStr = '$year-${month.toString().padLeft(2, '0')}-${day.toString().padLeft(2, '0')}';
        final hari = _getHariFromDate(tanggalStr);
        var jamList = grouped[tanggalStr] ?? [];
        
        
        if (jamList.isEmpty) {
          jamList = [
            {
              'id_jadwal': 'default_${tanggalStr}_pagi',
              'waktu_mulai': '08:00:00',
              'waktu_selesai': '10:00:00',
              'kuota': 10,
            },
            {
              'id_jadwal': 'default_${tanggalStr}_siang',
              'waktu_mulai': '13:00:00',
              'waktu_selesai': '15:00:00',
              'kuota': 10,
            },
            {
              'id_jadwal': 'default_${tanggalStr}_sore',
              'waktu_mulai': '15:30:00',
              'waktu_selesai': '17:30:00',
              'kuota': 10,
            },
          ];
        }
        
        final hasAvailableQuota = jamList.any((jam) => (jam['kuota'] as int) > 0);
        
        _jadwalList.add({
          'tanggal': tanggalStr,
          'tanggal_display': _formatTanggal(tanggalStr),
          'hari': hari,
          'jam_list': jamList,
          'has_jadwal': true,
          'has_quota': hasAvailableQuota,
          'is_full': jamList.isNotEmpty && !hasAvailableQuota,
        });
      }
      
      
      _selectFirstAvailableDate();
      
    } catch (e) {
      debugPrint('Error load jadwal: $e');
    }
  }

  void _selectFirstAvailableDate() {
    for (var jadwal in _jadwalList) {
      if (jadwal['has_quota']) {
        _selectedTanggal = jadwal['tanggal_display'];
        _selectedHari = jadwal['hari'];
        
        final jamList = jadwal['jam_list'] as List;
        for (var jam in jamList) {
          if ((jam['kuota'] as int) > 0) {
            _selectedJadwal = jam;
            _selectedWaktuMulai = jam['waktu_mulai'].toString().substring(0, 5);
            _selectedWaktuSelesai = jam['waktu_selesai'].toString().substring(0, 5);
            break;
          }
        }
        break;
      }
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

  String _getCurrentMonth() {
    final now = DateTime.now();
    final months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return '${months[now.month - 1]} ${now.year}';
  }

  String _formatRupiah(double value) {
    return value.toStringAsFixed(0).replaceAllMapped(
      RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
      (m) => '${m[1]}.',
    );
  }

  void _nextStep() {
    if (_currentStep == 1) {
      
      if (_selectedJadwal == null) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Pilih tanggal dan jam penjemputan terlebih dahulu')),
        );
        return;
      }
      setState(() => _currentStep = 2);
    }
  }

  void _prevStep() {
    if (_currentStep == 1) {
      
      Navigator.pop(context);
    } else if (_currentStep == 2) {
      
      setState(() => _currentStep = 1);
    }
  }

  Future<void> _submitJemput() async {
    if (_selectedJadwal == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Pilih tanggal dan jam penjemputan terlebih dahulu')),
      );
      return;
    }
    
    final String jadwalId = _selectedJadwal!['id_jadwal'].toString();
    
    // CEK KUOTA: Hitung jumlah transaksi aktif untuk jadwal ini
    final transaksiAktif = await _supabase
        .from('setor_sampah')
        .select('id_setor')
        .eq('id_jadwal', jadwalId)
        .inFilter('status', ['menunggu', 'diproses']);
    
    final int transaksiTerpakai = transaksiAktif.length;
    
    // Ambil kuota maksimal dari jadwal
    final jadwalData = await _supabase
        .from('jadwal_ambil')
        .select('kuota')
        .eq('id_jadwal', jadwalId)
        .single();
    
    final int kuotaMaksimal = (jadwalData['kuota'] as int?) ?? 0;
    final int sisaKuota = kuotaMaksimal - transaksiTerpakai;
    
    if (sisaKuota <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Maaf, kuota untuk jam ini sudah penuh. Silahkan pilih jam lain.'),
          backgroundColor: Colors.orange,
        ),
      );
      return;
    }
    
    // Dialog konfirmasi dengan info sisa kuota
    final shouldSubmit = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Konfirmasi Penjemputan'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Apakah Anda yakin dengan data berikut?'),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFF5F9EE),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFFD8E8C0)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(children: [const Icon(Icons.calendar_today, size: 16, color: AppColors.secondary), const SizedBox(width: 8), Text('$_selectedHari, $_selectedTanggal')]),
                  const SizedBox(height: 6),
                  Row(children: [const Icon(Icons.access_time, size: 16, color: AppColors.secondary), const SizedBox(width: 8), Text('$_selectedWaktuMulai - $_selectedWaktuSelesai WIB')]),
                  const SizedBox(height: 6),
                  Row(children: [const Icon(Icons.location_on, size: 16, color: AppColors.secondary), const SizedBox(width: 8), Expanded(child: Text(_alamat, maxLines: 2))]),
                  const Divider(),
                  Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [const Text('Total Harga:'), Text('Rp${_formatRupiah(_totalHarga)}', style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.secondary))]),
                  Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [const Text('Poin yang didapat:'), Text('+$_totalPoin poin', style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.secondary))]),
                  const Divider(),
                  Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                    const Text('Sisa Kuota:', style: TextStyle(fontSize: 12)),
                    Text('$sisaKuota dari $kuotaMaksimal', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: AppColors.secondary)),
                  ]),
                ],
              ),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Batal', style: TextStyle(color: Colors.red))),
          ElevatedButton(onPressed: () => Navigator.pop(context, true), style: ElevatedButton.styleFrom(backgroundColor: AppColors.secondary), child: const Text('Ya, Konfirmasi', style: TextStyle(color: Colors.white))),
        ],
      ),
    ) ?? false;
    
    if (!shouldSubmit) return;

    setState(() => _isSubmitting = true);
    try {
      final user = AuthService.getCurrentUser();
      if (user == null) throw Exception('User tidak login');
      
      String finalJadwalId = _selectedJadwal!['id_jadwal'].toString();
      
      // Jika menggunakan jadwal default
      if (finalJadwalId.toString().startsWith('default_')) {
        final existingJadwal = await _supabase
            .from('jadwal_ambil')
            .select('id_jadwal, kuota')
            .eq('tanggal', _selectedTanggal)
            .eq('waktu_mulai', _selectedJadwal!['waktu_mulai'])
            .maybeSingle();
        
        if (existingJadwal != null) {
          finalJadwalId = existingJadwal['id_jadwal'];
        } else {
          final newJadwal = await _supabase.from('jadwal_ambil').insert({
            'tanggal': _selectedTanggal,
            'waktu_mulai': _selectedJadwal!['waktu_mulai'],
            'waktu_selesai': _selectedJadwal!['waktu_selesai'],
            'kuota': 10,
            'created_at': DateTime.now().toIso8601String(),
          }).select().single();
          finalJadwalId = newJadwal['id_jadwal'];
        }
      }
      
      // CEK KUOTA SEKALI LAGI (race condition prevention)
      final checkTransaksi = await _supabase
          .from('setor_sampah')
          .select('id_setor')
          .eq('id_jadwal', finalJadwalId)
          .inFilter('status', ['menunggu', 'diproses']);
      
      final checkJadwal = await _supabase
          .from('jadwal_ambil')
          .select('kuota')
          .eq('id_jadwal', finalJadwalId)
          .single();
      
      final currentKuota = (checkJadwal['kuota'] as int?) ?? 0;
      final currentTerpakai = checkTransaksi.length;
      
      if (currentKuota - currentTerpakai <= 0) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Maaf, kuota untuk jam ini sudah penuh. Silahkan pilih jam lain.'), backgroundColor: Colors.orange),
          );
        }
        return;
      }
      
      // Insert ke setor_sampah
      final result = await _supabase.from('setor_sampah').insert({
        'id_pengguna': user.id,
        'alamat': _alamat,
        'id_jadwal': finalJadwalId,
        'total_uang': _totalHarga,
        'status': 'menunggu',
        'created_at': DateTime.now().toIso8601String(),
      }).select().single();
      
      final idSetor = result['id_setor'];
      
      // Insert detail_setor
      for (var s in _validSampah) {
        final harga = (s['harga_per_kg'] ?? 0).toDouble();
        await _supabase.from('detail_setor').insert({
          'id_setor': idSetor,
          'id_jenis': s['id_jenis'],
          'berat': s['berat'],
          'harga_per_kg': harga,
          'subtotal': harga * (s['berat'] as double),
        });
      }
      
      // Insert riwayat_aktivitas
      await _supabase.from('riwayat_aktivitas').insert({
        'id_pengguna': user.id,
        'jenis_aktivitas': 'setor_sampah',
        'id_referensi': idSetor,
        'judul': 'Setor Sampah',
        'deskripsi': 'Anda melakukan setor sampah dengan total ${_totalBerat.toStringAsFixed(2)} kg. Menunggu verifikasi admin.',
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
            'Setor Sampah',
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
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('JADWAL', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: Color(0xFF74942B))),
              Text(_getCurrentMonth(), style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w500, color: Colors.grey)),
            ],
          ),
          const SizedBox(height: 16),
          const Text('Pilih Tanggal', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF555555))),
          const SizedBox(height: 8),
          SizedBox(
            height: 70,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: _jadwalList.length,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (context, index) {
                final item = _jadwalList[index];
                final isSelected = _selectedTanggal == item['tanggal_display'];
                
                Color bgColor;
                if (isSelected) {
                  bgColor = _darkGreen;
                } else {
                  bgColor = _lightGreenBg;
                }
                
                Color borderColor;
                if (isSelected) {
                  borderColor = _darkGreen;
                } else {
                  borderColor = _borderGreen;
                }
                
                Color textColor;
                if (isSelected) {
                  textColor = Colors.white;
                } else {
                  textColor = const Color(0xFF555555);
                }
                
                return GestureDetector(
                  onTap: () {
                    setState(() {
                      _selectedTanggal = item['tanggal_display'];
                      _selectedHari = item['hari'];
                      
                      final jamList = item['jam_list'] as List;
                      if (jamList.isNotEmpty) {
                        for (var jam in jamList) {
                          if ((jam['kuota'] as int) > 0) {
                            _selectedJadwal = jam;
                            _selectedWaktuMulai = jam['waktu_mulai'].toString().substring(0, 5);
                            _selectedWaktuSelesai = jam['waktu_selesai'].toString().substring(0, 5);
                            break;
                          }
                        }
                      } else {
                        _selectedJadwal = null;
                        _selectedWaktuMulai = '';
                        _selectedWaktuSelesai = '';
                      }
                    });
                  },
                  child: Container(
                    width: 52,
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    decoration: BoxDecoration(
                      color: bgColor,
                      border: Border.all(color: borderColor, width: 1),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Column(
                      children: [
                        Text(item['hari'].substring(0, 3), 
                            style: TextStyle(fontSize: 9, fontWeight: FontWeight.w700, color: textColor)),
                        Text(item['tanggal'].substring(8, 10), 
                            style: TextStyle(fontSize: 9, fontWeight: FontWeight.w700, color: textColor)),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
          const SizedBox(height: 16),
          const Text('Pilih Waktu Penjemputan', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF555555))),
          const SizedBox(height: 8),
          _buildJamOptions(),
        ],
      ),
    ),
  );

  Widget _buildJamOptions() {
    final selectedDateData = _jadwalList.firstWhere(
      (item) => item['tanggal_display'] == _selectedTanggal,
      orElse: () => {},
    );
    
    if (selectedDateData.isEmpty) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 20),
        child: Center(
          child: Text('Pilih tanggal terlebih dahulu', style: TextStyle(color: Colors.grey)),
        ),
      );
    }
    
    final jamList = selectedDateData['jam_list'] as List;
    
    if (jamList.isEmpty) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 20),
        child: Center(
          child: Text('Tidak ada jadwal untuk tanggal ini', style: TextStyle(color: Colors.grey)),
        ),
      );
    }
    
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: jamList.map((jam) {
        final waktuMulai = jam['waktu_mulai'].toString().substring(0, 5);
        final waktuSelesai = jam['waktu_selesai'].toString().substring(0, 5);
        final kuota = jam['kuota'] as int;
        final isSelected = _selectedWaktuMulai == waktuMulai;
        final isPenuh = kuota <= 0;
        
        return GestureDetector(
          onTap: isPenuh ? null : () {
            setState(() {
              _selectedJadwal = jam;
              _selectedWaktuMulai = waktuMulai;
              _selectedWaktuSelesai = waktuSelesai;
            });
          },
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            decoration: BoxDecoration(
              color: isPenuh 
                  ? Colors.grey[300] 
                  : (isSelected ? _darkGreen : _lightGreenBg),
              border: Border.all(
                color: isPenuh 
                    ? Colors.grey 
                    : (isSelected ? _darkGreen : _borderGreen),
                width: 1,
              ),
              borderRadius: BorderRadius.circular(100),
            ),
            child: Column(
              children: [
                Text(
                  '$waktuMulai - $waktuSelesai',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                    color: isPenuh 
                        ? Colors.grey 
                        : (isSelected ? Colors.white : const Color(0xFF555555)),
                  ),
                ),
                if (isPenuh)
                  const Text(
                    'Kuota habis',
                    style: TextStyle(fontSize: 8, color: Colors.red),
                  ),
              ],
            ),
          ),
        );
      }).toList(),
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
        _buildKonfirmasiButtons(),
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
              Text(s['nama'] ?? 'Sampah', style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w600)),
              const SizedBox(height: 4),
              Text('${(s['berat'] as double).toStringAsFixed(1)} kg', style: AppTextStyles.caption),
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

  Widget _buildKonfirmasiButtons() => Row(
    children: [
      Expanded(
        child: OutlinedButton(
          onPressed: () => setState(() => _currentStep = 1),
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
          onPressed: _isSubmitting ? null : _submitJemput,
          style: ElevatedButton.styleFrom(
            backgroundColor: AppColors.secondary,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppConstants.radiusXL)),
            padding: const EdgeInsets.symmetric(vertical: 14),
          ),
          child: _isSubmitting
              ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
              : Text('Konfirmasi', style: AppTextStyles.buttonLabel.copyWith(color: Colors.white)),
        ),
      ),
    ],
  );

  Widget _buildInfoRow(String l, String v) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 8),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(l, style: AppTextStyles.body),
      Text(v, style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w600)),
    ]),
  );
}