import 'dart:io';
import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:reworth_mobile/models/location_model2.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../utils/app_colors.dart';
import '../utils/app_constants.dart';
import '../utils/app_text_styles.dart';
import '../utils/app_image_helper.dart';
import '../models/location_model2.dart';

// ─── Model rekening penjual ───────────────────────────────────────────────────

class _RekeningPenjual {
  final String namaBank;   // dari kolom nama_bank di tabel penjual
  final String noRekening; // dari kolom akun_rekening
  final String atasNama;   // dari kolom nama_penjual

  const _RekeningPenjual({
    required this.namaBank,
    required this.noRekening,
    required this.atasNama,
  });

  // Mapping nama_bank → nama file logo di storage media/bank/
  String get namaFile {
    const map = {
      'BCA': 'BCA.webp',
      'BRI': 'BRI.webp',
      'BNI': 'BNI.webp',
      'BSI': 'BSI.webp',
      'Bank Mandiri': 'Bank Mandiri.webp',
      'Bank Danamon': 'Bank Danamon.webp',
      'Bank Mega': 'Bank Mega.webp',
      'Bank Permata': 'Bank Permata.webp',
      'CIMB Niaga': 'CIMB Niaga.webp',
      'SeaBank': 'SeaBank.webp',
    };
    return map[namaBank] ?? '';
  }
}

// ─── Page ─────────────────────────────────────────────────────────────────────

class BelanjaCheckoutPage extends StatefulWidget {
  final String idProduk;
  final String namaProduk;
  final String namaPenjual;
  final String fotoProduk;
  final int harga;
  final int jumlah;

  const BelanjaCheckoutPage({
    super.key,
    required this.idProduk,
    required this.namaProduk,
    required this.namaPenjual,
    required this.fotoProduk,
    required this.harga,
    required this.jumlah,
  });

  @override
  State<BelanjaCheckoutPage> createState() => _BelanjaCheckoutPageState();
}

class _BelanjaCheckoutPageState extends State<BelanjaCheckoutPage> {
  final _supabase = Supabase.instance.client;
  final _picker = ImagePicker();
  final _scrollController = ScrollController();

  LocationData2? _sellerLocation;
  LocationData2? _userLocation;

  bool _isLoadingOngkir = true;
  int _ongkir = 0;
  String? _shippingService;
  String? _shippingEtd;

  int get _totalBayarDenganOngkir => _totalBayar + _ongkir;


  // Step: 0 = Keranjang, 1 = Ringkasan, 2 = Pembayaran
  int _step = 0;

  // Alamat pengguna dari DB — kolom alamat_detail
  String _alamat = '';
  bool _isLoadingAlamat = true;

  // Rekening penjual dari DB
  _RekeningPenjual? _rekening;
  bool _isLoadingRekening = true;

  // Upload bukti
  File? _buktiBayar;
  bool _isUploading = false;
  bool _isConfirming = false;

  // ── Computed ─────────────────────────────────────────────────────────────────

  int get _totalBayar => widget.harga * widget.jumlah;

  // ── Init ─────────────────────────────────────────────────────────────────────

  @override
  void initState() {
    super.initState();
    _loadAlamat();
    _loadRekeningPenjual();
    _loadLocationsAndCalculateOngkir();
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  // ── Load alamat pengguna — kolom alamat_detail ────────────────────────────────

  Future<void> _loadAlamat() async {
    try {
      final userId = _supabase.auth.currentUser?.id;
      if (userId == null) {
        setState(() {
          _alamat = 'Alamat belum tersedia';
          _isLoadingAlamat = false;
        });
        return;
      }

      final res = await _supabase
          .from('pengguna')
          .select('alamat_detail')
          .eq('id_pengguna', userId)
          .maybeSingle();

      setState(() {
        final raw = res?['alamat_detail'] as String? ?? '';
        _alamat = raw.isNotEmpty ? raw : 'Alamat belum diisi di profil';
        _isLoadingAlamat = false;
      });
    } catch (e) {
      debugPrint('ERROR LOAD ALAMAT: $e');
      setState(() {
        _alamat = 'Gagal memuat alamat';
        _isLoadingAlamat = false;
      });
    }
  }

  // ── Load rekening penjual dari tabel penjual via id_produk ────────────────────

  Future<void> _loadRekeningPenjual() async {
    try {
      // Ambil id_penjual dari tabel produk, lalu join ke tabel penjual
      final res = await _supabase
          .from('produk')
          .select('penjual(nama_penjual, akun_rekening, nama_bank)')
          .eq('id_produk', widget.idProduk)
          .single();

      final penjualData = res['penjual'] as Map<String, dynamic>?;

      if (penjualData == null) {
        setState(() => _isLoadingRekening = false);
        return;
      }

      setState(() {
        _rekening = _RekeningPenjual(
          namaBank: penjualData['nama_bank'] as String? ?? '',
          noRekening: penjualData['akun_rekening'] as String? ?? '',
          atasNama: penjualData['nama_penjual'] as String? ?? '',
        );
        _isLoadingRekening = false;
      });
    } catch (e) {
      debugPrint('ERROR LOAD REKENING: $e');
      setState(() => _isLoadingRekening = false);
    }
  }

  Future<void> _loadLocationsAndCalculateOngkir() async {
    setState(() => _isLoadingOngkir = true);
    
    try {
      // 1. Ambil lokasi penjual dari database
      final sellerData = await _supabase
          .from('penjual')
          .select('alamat_penjual, latitude, longitude, nama_penjual')
          .eq('nama_penjual', widget.namaPenjual)
          .maybeSingle();
      
      if (sellerData != null) {
        _sellerLocation = LocationData2(
          latitude: (sellerData['latitude'] as num).toDouble(),
          longitude: (sellerData['longitude'] as num).toDouble(),
          address: sellerData['alamat_penjual'] ?? '',
          districtName: _extractDistrict(sellerData['alamat_penjual'] ?? ''),
        );
      }
      
      // 2. Ambil lokasi pengguna dari database
      final userId = _supabase.auth.currentUser?.id;
      if (userId != null) {
        final userData = await _supabase
            .from('pengguna')
            .select('alamat_detail, latitude, longitude')
            .eq('id_pengguna', userId)
            .maybeSingle();
        
        if (userData != null && userData['latitude'] != null) {
          _userLocation = LocationData2(
            latitude: (userData['latitude'] as num).toDouble(),
            longitude: (userData['longitude'] as num).toDouble(),
            address: userData['alamat_detail'] ?? '',
            districtName: _extractDistrict(userData['alamat_detail'] ?? ''),
          );
        }
      }
      
      // 3. Hitung ongkir jika kedua lokasi ada
      if (_sellerLocation != null && _userLocation != null) {
        await _calculateOngkir();
      }
      
      setState(() => _isLoadingOngkir = false);
    } catch (e) {
      debugPrint('Error loading locations: $e');
      setState(() => _isLoadingOngkir = false);
    }
  }

  String _extractDistrict(String address) {
    final patterns = [
      RegExp(r'Kec\.?\s+([^,]+)', caseSensitive: false),
      RegExp(r'Kecamatan\s+([^,]+)', caseSensitive: false),
    ];
    
    for (var pattern in patterns) {
      final match = pattern.firstMatch(address);
      if (match != null) {
        return match.group(1)?.trim() ?? '';
      }
    }
    
    final parts = address.split(',');
    for (var part in parts) {
      final lowerPart = part.toLowerCase();
      if (lowerPart.contains('kec')) {
        return part.replaceAll(RegExp(r'Kec\.?\s*', caseSensitive: false), '').trim();
      }
    }
    
    return '';
  }

  Future<void> _calculateOngkir() async {
    if (_sellerLocation == null || _userLocation == null) return;
    
    try {
      final weight = widget.jumlah * 250;
      
      // Hitung jarak antar koordinat
      final distance = _calculateDistance(
        _sellerLocation!.latitude, _sellerLocation!.longitude,
        _userLocation!.latitude, _userLocation!.longitude,
      );

      int estimatedOngkir;
      String service;
      String etd;
      
      if (distance <= 5) {
        estimatedOngkir = 10000;
        service = 'JNE REG (Same Day)';
        etd = '1';
      } else if (distance <= 10) {
        estimatedOngkir = 15000;
        service = 'JNE REG (Next Day)';
        etd = '1-2';
      } else if (distance <= 20) {
        estimatedOngkir = 25000;
        service = 'JNE REG';
        etd = '2-3';
      } else {
        estimatedOngkir = 35000;
        service = 'JNE OKE';
        etd = '3-4';
      }
      
      setState(() {
        _ongkir = estimatedOngkir;
        _shippingService = service;
        _shippingEtd = etd;
      });
      
    } catch (e) {
      debugPrint('Error calculate ongkir: $e');
      setState(() {
        _ongkir = 15000;
        _shippingService = 'JNE REG';
        _shippingEtd = '2-3';
      });
    }
  }

  double _calculateDistance(double lat1, double lon1, double lat2, double lon2) {
    const double R = 6371;
    
    final dLat = _toRadians(lat2 - lat1);
    final dLon = _toRadians(lon2 - lon1);
    
    final a = _sin(dLat / 2) * _sin(dLat / 2) +
              _cos(_toRadians(lat1)) * _cos(_toRadians(lat2)) *
              _sin(dLon / 2) * _sin(dLon / 2);
    
    final c = 2 * _atan2(_sqrt(a), _sqrt(1 - a));
    
    return R * c;
  }

  double _toRadians(double degree) => degree * 3.141592653589793 / 180;
  double _sin(double x) => math.sin(x);
  double _cos(double x) => math.cos(x);
  double _sqrt(double x) => math.sqrt(x);
  double _atan2(double y, double x) => math.atan2(y, x);

  // ── Navigation ───────────────────────────────────────────────────────────────

  void _nextStep() {
    if (_step < 2) {
      setState(() => _step++);
      _scrollController.animateTo(0,
          duration: const Duration(milliseconds: 300), curve: Curves.easeOut);
    }
  }

  void _prevStep() {
    if (_step > 0) {
      setState(() => _step--);
    } else {
      Navigator.pop(context);
    }
  }

  // ── Upload & Konfirmasi ───────────────────────────────────────────────────────

  Future<void> _pickBukti() async {
    final picked =
        await _picker.pickImage(source: ImageSource.gallery, imageQuality: 80);
    if (picked != null) setState(() => _buktiBayar = File(picked.path));
  }

  Future<void> _konfirmasiPembayaran() async {
    if (_buktiBayar == null) {
      _snack('Silakan upload bukti pembayaran terlebih dahulu', isError: true);
      return;
    }

    final userId = _supabase.auth.currentUser?.id;
    if (userId == null) {
      _snack('Silakan login terlebih dahulu', isError: true);
      return;
    }

    setState(() => _isConfirming = true);

    try {
      // 1. Upload bukti ke storage
      setState(() => _isUploading = true);
      final fileName = 'bukti_pembayaran/${userId}_${DateTime.now().millisecondsSinceEpoch}.jpg';
      await _supabase.storage.from('media').upload(fileName, _buktiBayar!);
      setState(() => _isUploading = false);

      // 2. Insert pesanan - total_bayar SUDAH termasuk ongkir
      final inserted = await _supabase.from('pesanan').insert({
        'id_produk': widget.idProduk,
        'id_pengguna': userId,
        'alamat_pengiriman': _alamat,
        'total_bayar': _totalBayarDenganOngkir.toString(), // SUDAH termasuk ongkir
        'bukti_pembayaran': fileName,
        'status': 'menunggu',
        'jasa_kirim': _shippingService ?? 'JNE', // TAMBAHKAN jasa kirim
        'created_at': DateTime.now().toIso8601String(),
      }).select('id_pesanan').single();

      final idPesanan = inserted['id_pesanan'] as String;

      // 3. Insert riwayat_aktivitas
      await _supabase.from('riwayat_aktivitas').insert({
        'id_pengguna': userId,
        'jenis_aktivitas': 'pesanan',
        'id_referensi': idPesanan,
        'judul': 'Pesanan ${widget.namaProduk}',
        'deskripsi': 'Pesanan ${widget.namaProduk} dari ${widget.namaPenjual} senilai ${_rupiah(_totalBayarDenganOngkir)} sedang menunggu konfirmasi pembayaran.',
        'status': 'menunggu',
        'perubahan_poin': null,
        'perubahan_saldo': null,
        'created_at': DateTime.now().toIso8601String(),
      });

      if (mounted) {
        _snack('Pesanan berhasil dikonfirmasi!');
        await Future.delayed(const Duration(milliseconds: 800));
        if (mounted) Navigator.pop(context);
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isConfirming = false;
          _isUploading = false;
        });
        _snack('Gagal membuat pesanan: $e', isError: true);
      }
    }
  }

  void _snack(String msg, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(msg),
      backgroundColor: isError ? Colors.redAccent : AppColors.secondary,
      behavior: SnackBarBehavior.floating,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
    ));
  }

  // ── Build ─────────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final topPad = MediaQuery.of(context).padding.top;
    final bottomPad = MediaQuery.of(context).padding.bottom;

    return Scaffold(
      backgroundColor: const Color(0xFFF2F2F2),
      body: Stack(
        children: [
          // ── Gradient identik EventPage ─────────────────────────────────────
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
                _buildAppBar(),
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
                        Padding(
                          padding: const EdgeInsets.fromLTRB(
                            AppConstants.paddingL,
                            AppConstants.paddingL,
                            AppConstants.paddingL,
                            0,
                          ),
                          child: _buildStepIndicator(),
                        ),
                        const SizedBox(height: AppConstants.paddingL),
                        Expanded(
                          child: SingleChildScrollView(
                            controller: _scrollController,
                            padding: EdgeInsets.fromLTRB(
                              AppConstants.paddingL,
                              0,
                              AppConstants.paddingL,
                              (_step == 2 ? 130 : 100) + bottomPad,
                            ),
                            child: _buildStepContent(),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),

          // Tombol bawah fixed
          Positioned(
            left: 0,
            right: 0,
            bottom: 0,
            child: _buildBottomButton(bottomPad),
          ),
        ],
      ),
    );
  }

  // ── AppBar ────────────────────────────────────────────────────────────────────

  Widget _buildAppBar() {
    return Padding(
      padding: const EdgeInsets.symmetric(
        horizontal: AppConstants.paddingM,
        vertical: AppConstants.paddingM,
      ),
      child: Row(
        children: [
          GestureDetector(
            onTap: _prevStep,
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
              'Checkout',
              style: AppTextStyles.namafitur,
              textAlign: TextAlign.center,
            ),
          ),
          const SizedBox(width: 38),
        ],
      ),
    );
  }

  // ── Step Indicator ────────────────────────────────────────────────────────────

  Widget _buildStepIndicator() {
    const labels = ['Keranjang', 'Ringkasan', 'Pembayaran'];
    return Row(
      children: List.generate(labels.length * 2 - 1, (i) {
        if (i.isOdd) {
          final stepIdx = i ~/ 2;
          return Expanded(
            child: Container(
              height: 2,
              color: _step > stepIdx
                  ? AppColors.secondary
                  : AppColors.inputBorder,
            ),
          );
        }
        final idx = i ~/ 2;
        final isDone = _step > idx;
        final isCurrent = _step == idx;
        return Column(
          children: [
            AnimatedContainer(
              duration: const Duration(milliseconds: 300),
              width: 36,
              height: 36,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: isDone
                    ? AppColors.secondary
                    : isCurrent
                        ? AppColors.secondary.withOpacity(0.12)
                        : AppColors.inputBorder,
                border: Border.all(
                  color: (isDone || isCurrent)
                      ? AppColors.secondary
                      : Colors.transparent,
                  width: 2,
                ),
              ),
              child: Center(
                child: isDone
                    ? const Icon(Icons.check_rounded,
                        size: 18, color: AppColors.white)
                    : Text(
                        '${idx + 1}',
                        style: AppTextStyles.label.copyWith(
                          fontSize: 13,
                          color: isCurrent
                              ? AppColors.secondary
                              : AppColors.textHint,
                        ),
                      ),
              ),
            ),
            const SizedBox(height: 6),
            Text(
              labels[idx],
              style: AppTextStyles.small.copyWith(
                fontWeight:
                    (isDone || isCurrent) ? FontWeight.w600 : FontWeight.w400,
                color: (isDone || isCurrent)
                    ? AppColors.secondary
                    : AppColors.textHint,
              ),
            ),
          ],
        );
      }),
    );
  }

  // ── Step Router ───────────────────────────────────────────────────────────────

  Widget _buildStepContent() {
    return switch (_step) {
      0 => _buildStepKeranjang(),
      1 => _buildStepRingkasan(),
      2 => _buildStepPembayaran(),
      _ => const SizedBox(),
    };
  }

  // ════════════════════════════════════════════════════════════════════════════
  // STEP 0 — KERANJANG
  // ════════════════════════════════════════════════════════════════════════════

  Widget _buildStepKeranjang() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Ringkasan Pesanan', style: AppTextStyles.label),
        const SizedBox(height: AppConstants.paddingM),
        _cardProduk(),
        const SizedBox(height: AppConstants.paddingL),
        Divider(color: AppColors.divider, thickness: 1),
        const SizedBox(height: AppConstants.paddingM),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text('Total (${widget.jumlah} item)',
                style: AppTextStyles.bodyMedium),
            Text(_rupiah(_totalBayar), style: AppTextStyles.label),
          ],
        ),
      ],
    );
  }

  // ════════════════════════════════════════════════════════════════════════════
  // STEP 1 — RINGKASAN
  // ════════════════════════════════════════════════════════════════════════════

  Widget _buildStepRingkasan() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Ringkasan Pesanan', style: AppTextStyles.label),
        const SizedBox(height: AppConstants.paddingM),
        _cardProduk(),

        const SizedBox(height: AppConstants.paddingL),

        Text('Alamat Pengiriman', style: AppTextStyles.label),
        const SizedBox(height: AppConstants.paddingM),
        _cardAlamat(),

        const SizedBox(height: AppConstants.paddingL),

        Text('Total Harga', style: AppTextStyles.label),
        const SizedBox(height: AppConstants.paddingM),
        _cardTotal(),
      ],
    );
  }

  Widget _cardAlamat() {
    return Container(
      padding: const EdgeInsets.all(AppConstants.paddingM),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppConstants.radiusL),
        border: Border.all(color: AppColors.inputBorder),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: const Color(0xFFEFF7DC),
              borderRadius: BorderRadius.circular(AppConstants.radiusM),
            ),
            child: const Icon(Icons.location_on_outlined,
                color: AppColors.secondary, size: 20),
          ),
          const SizedBox(width: AppConstants.paddingM),
          Expanded(
            child: _isLoadingAlamat
                ? const SizedBox(
                    height: 20,
                    child: LinearProgressIndicator(
                      color: AppColors.secondary,
                      backgroundColor: Color(0xFFEFF7DC),
                    ),
                  )
                : Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Alamat Utama',
                          style: AppTextStyles.label.copyWith(fontSize: 13)),
                      const SizedBox(height: 4),
                      Text(
                        _alamat,
                        style: AppTextStyles.small.copyWith(
                          color: AppColors.textSecondary,
                          height: 1.6,
                        ),
                      ),
                    ],
                  ),
          ),
        ],
      ),
    );
  }

  Widget _cardTotal() {
    final subtotal = widget.harga * widget.jumlah;
    final total = _totalBayarDenganOngkir;
    
    return Container(
      padding: const EdgeInsets.all(AppConstants.paddingM),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppConstants.radiusL),
        border: Border.all(color: AppColors.inputBorder),
      ),
      child: Column(
        children: [
          _rowBiaya('Subtotal produk (${widget.jumlah} item)', subtotal),
          const SizedBox(height: AppConstants.paddingM),
          
          if (_isLoadingOngkir)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 8),
              child: Center(
                child: SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
              ),
            )
          else ...[
            _rowBiaya('Ongkos Kirim', _ongkir),
            if (_shippingService != null) ...[
              const SizedBox(height: 4),
              Text(
                'Jasa kirim: $_shippingService (estimasi $_shippingEtd hari)',
                style: AppTextStyles.small.copyWith(fontSize: 10, color: Colors.grey),
                textAlign: TextAlign.right,
              ),
            ],
          ],
          
          const SizedBox(height: AppConstants.paddingM),
          
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: AppConstants.paddingM, vertical: 14),
            decoration: BoxDecoration(
              color: AppColors.secondary,
              borderRadius: BorderRadius.circular(AppConstants.radiusM),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('Total Bayar', style: AppTextStyles.buttonLabel.copyWith(fontSize: 14)),
                Text(_rupiah(total), style: AppTextStyles.buttonLabel.copyWith(fontSize: 16)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _rowBiaya(String label, int nominal) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: AppTextStyles.bodyMedium.copyWith(fontSize: 13)),
        Text(_rupiah(nominal),
            style: AppTextStyles.body.copyWith(fontSize: 13)),
      ],
    );
  }

  // ════════════════════════════════════════════════════════════════════════════
  // STEP 2 — PEMBAYARAN
  // ════════════════════════════════════════════════════════════════════════════

  Widget _buildStepPembayaran() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Instruksi Pembayaran', style: AppTextStyles.label),
        const SizedBox(height: AppConstants.paddingM),

        // Info instruksi
        Container(
          padding: const EdgeInsets.all(AppConstants.paddingM),
          decoration: BoxDecoration(
            color: AppColors.white,
            borderRadius: BorderRadius.circular(AppConstants.radiusL),
            border: Border.all(color: AppColors.inputBorder),
          ),
          child: Row(
            children: [
              const Icon(Icons.info_outline_rounded,
                  size: 20, color: AppColors.textSecondary),
              const SizedBox(width: AppConstants.paddingS),
              Expanded(
                child: Text(
                  'Transfer ke rekening penjual berikut',
                  style: AppTextStyles.bodyMedium.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
            ],
          ),
        ),

        const SizedBox(height: AppConstants.paddingM),

        // Card rekening penjual (dari DB)
        _isLoadingRekening
            ? const Center(
                child: Padding(
                  padding: EdgeInsets.all(AppConstants.paddingL),
                  child:
                      CircularProgressIndicator(color: AppColors.secondary),
                ),
              )
            : _rekening == null
                ? Container(
                    padding: const EdgeInsets.all(AppConstants.paddingM),
                    decoration: BoxDecoration(
                      borderRadius:
                          BorderRadius.circular(AppConstants.radiusL),
                      border: Border.all(color: AppColors.inputBorder),
                    ),
                    child: Text(
                      'Data rekening penjual tidak tersedia',
                      style: AppTextStyles.bodyMedium,
                    ),
                  )
                : _cardRekening(_rekening!),

        const SizedBox(height: AppConstants.paddingS),

        // Warning 24 jam
        Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(
              horizontal: AppConstants.paddingM, vertical: 14),
          decoration: BoxDecoration(
            color: const Color(0xFFFFF8E1),
            borderRadius: BorderRadius.circular(AppConstants.radiusM),
            border: Border.all(color: const Color(0xFFFFE082)),
          ),
          child: RichText(
            text: TextSpan(
              style: AppTextStyles.small.copyWith(
                  color: AppColors.textSecondary, fontSize: 12, height: 1.5),
              children: [
                const TextSpan(text: 'Selesaikan pembayaran dalam '),
                TextSpan(
                  text: '24 jam',
                  style: AppTextStyles.small.copyWith(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
                const TextSpan(
                    text: ' atau pesanan akan dibatalkan otomatis.'),
              ],
            ),
          ),
        ),

        const SizedBox(height: AppConstants.paddingL),

        Text('Upload Bukti Pembayaran', style: AppTextStyles.label),
        const SizedBox(height: AppConstants.paddingM),
        _uploadBukti(),
      ],
    );
  }

  Widget _cardRekening(_RekeningPenjual rek) {
    final logoUrl = AppImageHelper.fotoBank(rek.namaFile);

    return Container(
      padding: const EdgeInsets.all(AppConstants.paddingM),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppConstants.radiusL),
        border: Border.all(color: AppColors.secondary, width: 2),
      ),
      child: Row(
        children: [
          // Logo bank
          Container(
            width: 54,
            height: 36,
            decoration: BoxDecoration(
              color: const Color(0xFFF5F5F5),
              borderRadius: BorderRadius.circular(6),
              border: Border.all(color: AppColors.inputBorder),
            ),
            child: logoUrl.isEmpty
                ? const Icon(Icons.account_balance_outlined,
                    size: 18, color: AppColors.textHint)
                : ClipRRect(
                    borderRadius: BorderRadius.circular(5),
                    child: Image.network(
                      logoUrl,
                      fit: BoxFit.contain,
                      errorBuilder: (_, __, ___) => const Icon(
                        Icons.account_balance_outlined,
                        size: 18,
                        color: AppColors.textHint,
                      ),
                    ),
                  ),
          ),
          const SizedBox(width: AppConstants.paddingM),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(rek.namaBank,
                    style: AppTextStyles.small
                        .copyWith(color: AppColors.textSecondary)),
                const SizedBox(height: 2),
                Text(
                  _formatNoRek(rek.noRekening),
                  style: AppTextStyles.title
                      .copyWith(fontSize: 16, letterSpacing: 0.8),
                ),
                Text('a.n. ${rek.atasNama}',
                    style: AppTextStyles.small
                        .copyWith(color: AppColors.textSecondary)),
              ],
            ),
          ),

          // Tombol salin
          GestureDetector(
            onTap: () {
              Clipboard.setData(
                  ClipboardData(text: rek.noRekening.replaceAll(' ', '')));
              _snack('Nomor rekening ${rek.namaBank} disalin');
            },
            child: Container(
              padding:
                  const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              decoration: BoxDecoration(
                color: AppColors.secondary,
                borderRadius: BorderRadius.circular(AppConstants.radiusXL),
              ),
              child: Text(
                'Salin',
                style: AppTextStyles.small.copyWith(
                  color: AppColors.white,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _uploadBukti() {
    final hasFoto = _buktiBayar != null;
    return GestureDetector(
      onTap: _pickBukti,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        width: double.infinity,
        padding: EdgeInsets.symmetric(
            vertical: hasFoto ? AppConstants.paddingM : 32),
        decoration: BoxDecoration(
          color: hasFoto ? const Color(0xFFEFF7DC) : AppColors.white,
          borderRadius: BorderRadius.circular(AppConstants.radiusL),
          border: Border.all(
            color: hasFoto
                ? AppColors.secondary
                : AppColors.secondary.withOpacity(0.45),
            width: 1.5,
            strokeAlign: BorderSide.strokeAlignInside,
          ),
        ),
        child: hasFoto
            ? Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.symmetric(
                        horizontal: AppConstants.paddingM),
                    child: ClipRRect(
                      borderRadius:
                          BorderRadius.circular(AppConstants.radiusM),
                      child: Image.file(_buktiBayar!,
                          height: 180,
                          width: double.infinity,
                          fit: BoxFit.cover),
                    ),
                  ),
                  const SizedBox(height: AppConstants.paddingS),
                  Text('Tap untuk ganti foto',
                      style: AppTextStyles.small.copyWith(
                          color: AppColors.secondary,
                          fontWeight: FontWeight.w600)),
                ],
              )
            : Column(
                children: [
                  Container(
                    width: 56,
                    height: 56,
                    decoration: BoxDecoration(
                      color: const Color(0xFFEFF7DC),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: const Icon(Icons.upload_rounded,
                        color: AppColors.secondary, size: 28),
                  ),
                  const SizedBox(height: AppConstants.paddingM),
                  Text('Tap untuk upload foto',
                      style: AppTextStyles.label
                          .copyWith(color: AppColors.secondary)),
                  const SizedBox(height: 6),
                  Text('JPG atau PNG · Maksimal 5MB',
                      style: AppTextStyles.small
                          .copyWith(color: AppColors.textSecondary)),
                  Text('Pastikan foto jelas dan terbaca',
                      style: AppTextStyles.small
                          .copyWith(color: AppColors.textSecondary)),
                  const SizedBox(height: AppConstants.paddingM),
                  Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 24, vertical: 10),
                    decoration: BoxDecoration(
                      color: AppColors.secondary,
                      borderRadius:
                          BorderRadius.circular(AppConstants.radiusXL),
                    ),
                    child: Text('Pilih Foto',
                        style: AppTextStyles.small.copyWith(
                            color: AppColors.white,
                            fontWeight: FontWeight.w700)),
                  ),
                ],
              ),
      ),
    );
  }

  // ── Shared ────────────────────────────────────────────────────────────────────

  Widget _cardProduk() {
    final url = AppImageHelper.fotoProduk(widget.fotoProduk);
    return Container(
      padding: const EdgeInsets.all(AppConstants.paddingM),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppConstants.radiusL),
        border: Border.all(color: AppColors.inputBorder),
      ),
      child: Row(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(AppConstants.radiusM),
            child: url.isNotEmpty
                ? Image.network(url,
                    width: 72, height: 72, fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => _thumbPlaceholder())
                : _thumbPlaceholder(),
          ),
          const SizedBox(width: AppConstants.paddingM),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(widget.namaProduk,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: AppTextStyles.label.copyWith(fontSize: 13)),
                const SizedBox(height: 4),
                Text('${widget.namaPenjual} · ${widget.jumlah}×',
                    style: AppTextStyles.small
                        .copyWith(color: AppColors.textSecondary)),
              ],
            ),
          ),
          const SizedBox(width: AppConstants.paddingS),
          Text(_rupiah(widget.harga * widget.jumlah),
              style: AppTextStyles.label.copyWith(fontSize: 13)),
        ],
      ),
    );
  }

  Widget _thumbPlaceholder() {
    return Container(
      width: 72,
      height: 72,
      color: const Color(0xFFF0F0F0),
      child: const Center(
        child: Icon(Icons.image_not_supported_outlined,
            color: AppColors.textHint, size: 24),
      ),
    );
  }

  // ── Tombol bawah ──────────────────────────────────────────────────────────────

  Widget _buildBottomButton(double bottomPad) {
    final isLastStep = _step == 2;
    final label = switch (_step) {
      0 => 'Lanjut ke Ringkasan',
      1 => 'Lanjut ke Pembayaran',
      _ => 'Konfirmasi Pembayaran',
    };

    return Container(
      padding: EdgeInsets.fromLTRB(
        AppConstants.paddingL,
        AppConstants.paddingM,
        AppConstants.paddingL,
        AppConstants.paddingL + bottomPad,
      ),
      decoration: const BoxDecoration(
        color: AppColors.white,
        boxShadow: [
          BoxShadow(
              color: Color(0x14000000),
              blurRadius: 12,
              offset: Offset(0, -4)),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox(
            width: double.infinity,
            height: AppConstants.buttonHeight,
            child: ElevatedButton(
              onPressed: _isConfirming
                  ? null
                  : isLastStep
                      ? _konfirmasiPembayaran
                      : _nextStep,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.secondary,
                disabledBackgroundColor:
                    AppColors.secondary.withOpacity(0.6),
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius:
                      BorderRadius.circular(AppConstants.radiusXL),
                ),
              ),
              child: _isConfirming
                  ? Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                              color: AppColors.white, strokeWidth: 2.5),
                        ),
                        const SizedBox(width: 10),
                        Text(
                          _isUploading ? 'Mengupload...' : 'Memproses...',
                          style: AppTextStyles.buttonLabel,
                        ),
                      ],
                    )
                  : Text(label, style: AppTextStyles.buttonLabel),
            ),
          ),
          if (isLastStep) ...[
            const SizedBox(height: 8),
            Text(
              'Pesananmu akan diproses setelah pembayaran dikonfirmasi tim kami',
              textAlign: TextAlign.center,
              style: AppTextStyles.small
                  .copyWith(color: AppColors.textHint, height: 1.4),
            ),
          ],
        ],
      ),
    );
  }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

String _rupiah(int angka) {
  final s = angka.toString();
  final buf = StringBuffer('Rp');
  final start = s.length % 3;
  if (start > 0) buf.write(s.substring(0, start));
  for (int i = start; i < s.length; i += 3) {
    if (i > 0 || start > 0) buf.write('.');
    buf.write(s.substring(i, i + 3));
  }
  return buf.toString();
}

String _formatNoRek(String raw) {
  final clean = raw.replaceAll(' ', '');
  final buf = StringBuffer();
  for (int i = 0; i < clean.length; i++) {
    if (i > 0 && i % 4 == 0) buf.write(' ');
    buf.write(clean[i]);
  }
  return buf.toString();
}