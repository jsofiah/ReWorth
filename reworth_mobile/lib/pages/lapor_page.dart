import 'dart:io';
import 'package:flutter/material.dart';
import 'package:camera/camera.dart';
import 'package:geolocator/geolocator.dart';
import 'package:geocoding/geocoding.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:path/path.dart' as path;
import 'package:image/image.dart' as img;
import 'dart:typed_data';
import 'package:flutter_image_compress/flutter_image_compress.dart';
import 'package:path_provider/path_provider.dart';
import '../utils/app_colors.dart';
import '../utils/app_text_styles.dart';
import '../utils/app_constants.dart';

enum _LaporStep { kamera, review, form }

class LaporSampahPage extends StatefulWidget {
  const LaporSampahPage({super.key});

  @override
  State<LaporSampahPage> createState() => _LaporSampahPageState();
}

class _LaporSampahPageState extends State<LaporSampahPage> {
  _LaporStep _step = _LaporStep.kamera;
  File? _fotoFile;

  void _onFotoAmbil(File file) {
    setState(() {
      _fotoFile = file;
      _step = _LaporStep.review;
    });
  }

  void _onPilihFoto() {
    setState(() => _step = _LaporStep.form);
  }

  void _onAmbilUlang() {
    setState(() {
      _fotoFile = null;
      _step = _LaporStep.kamera;
    });
  }

  @override
  Widget build(BuildContext context) {
    return switch (_step) {
      _LaporStep.kamera => _KameraView(onFotoAmbil: _onFotoAmbil),
      _LaporStep.review => _ReviewView(
          fotoFile: _fotoFile!,
          onPilih: _onPilihFoto,
          onUlang: _onAmbilUlang,
        ),
      _LaporStep.form => _FormView(
          fotoFile: _fotoFile!,
          onUlang: _onAmbilUlang,
        ),
    };
  }
}

class _KameraView extends StatefulWidget {
  final void Function(File) onFotoAmbil;
  const _KameraView({required this.onFotoAmbil});

  @override
  State<_KameraView> createState() => _KameraViewState();
}

class _KameraViewState extends State<_KameraView>
    with WidgetsBindingObserver {
  CameraController? _controller;
  List<CameraDescription> _cameras = [];
  bool _isInitialized = false;
  bool _isTakingPhoto = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _initCamera();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _controller?.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (_controller == null || !_controller!.value.isInitialized) return;
    if (state == AppLifecycleState.inactive) {
      _controller?.dispose();
    } else if (state == AppLifecycleState.resumed) {
      _initCamera();
    }
  }

  Future<void> _initCamera() async {
    try {
      _cameras = await availableCameras();
      if (_cameras.isEmpty) return;

      _controller = CameraController(
        _cameras.first,
        ResolutionPreset.high,
        enableAudio: false,
      );

      await _controller!.initialize();
      if (!mounted) return;
      setState(() => _isInitialized = true);
    } catch (e) {
      debugPrint('Kamera error: $e');
    }
  }

  Future<void> _ambilFoto() async {
    if (_controller == null || !_controller!.value.isInitialized) return;
    if (_isTakingPhoto) return;

    setState(() => _isTakingPhoto = true);
    try {
      final xFile = await _controller!.takePicture();
      widget.onFotoAmbil(File(xFile.path));
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Gagal mengambil foto: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _isTakingPhoto = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        fit: StackFit.expand,
        children: [
          if (_isInitialized && _controller != null)
            CameraPreview(_controller!)
          else
            const Center(
              child: CircularProgressIndicator(color: AppColors.primary),
            ),

          if (_isInitialized) _buildFrameOverlay(),

          Align(
            alignment: Alignment.topCenter,
            child: SafeArea(
              bottom: false,
              child: Padding(
                padding: const EdgeInsets.only(
                  left: AppConstants.paddingM,
                  right: AppConstants.paddingM,
                  top: 8,
                ),
              child: Row(
                children: [
                  _circleButton(
                    icon: Icons.chevron_left_rounded,
                    onTap: () => Navigator.pop(context),
                  ),
                  Expanded(
                    child: Text(
                      'Lapor Sampah',
                      textAlign: TextAlign.center,
                      style: AppTextStyles.namafitur.copyWith(
                        color: Colors.white,
                        shadows: [
                          Shadow(color: Colors.black45, blurRadius: 6)
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            )
          )
          ),

          Positioned(
            bottom: MediaQuery.of(context).padding.bottom + 48,
            left: 0,
            right: 0,
            child: Center(
              child: GestureDetector(
                onTap: _ambilFoto,
                child: AnimatedScale(
                  scale: _isTakingPhoto ? 0.88 : 1.0,
                  duration: const Duration(milliseconds: 120),
                  child: Container(
                    width: 68,
                    height: 68,
                    decoration: const BoxDecoration(
                      color: AppColors.primary,
                      shape: BoxShape.circle,
                    ),
                    child: _isTakingPhoto
                        ? const Padding(
                            padding: EdgeInsets.all(18),
                            child: CircularProgressIndicator(
                              strokeWidth: 2.5,
                              color: Colors.white,
                            ),
                          )
                        : const Icon(
                            Icons.camera_alt_outlined,
                            color: Colors.white,
                            size: 30,
                          ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFrameOverlay() {
    return Center(
      child: FractionallySizedBox(
        widthFactor: 0.82,
        heightFactor: 0.65,
        child: CustomPaint(painter: _FramePainter()),
      ),
    );
  }

  Widget _circleButton({
    required IconData icon,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 38,
        height: 38,
        decoration: BoxDecoration(
          color: Colors.white.withOpacity(0.3),
          shape: BoxShape.circle,
        ),
        child: Icon(icon, color: Colors.white, size: 22),
      ),
    );
  }
}

class _FramePainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = Colors.white.withOpacity(0.8)
      ..strokeWidth = 3
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;

    const r = 20.0;
    const len = 40.0;

    final corners = [
      [Offset(0, r + len), Offset(0, r), Offset(r, 0), Offset(r + len, 0)],
      [
        Offset(size.width - r - len, 0),
        Offset(size.width - r, 0),
        Offset(size.width, r),
        Offset(size.width, r + len)
      ],
      [
        Offset(size.width, size.height - r - len),
        Offset(size.width, size.height - r),
        Offset(size.width - r, size.height),
        Offset(size.width - r - len, size.height)
      ],
      [
        Offset(r + len, size.height),
        Offset(r, size.height),
        Offset(0, size.height - r),
        Offset(0, size.height - r - len)
      ],
    ];

    for (final pts in corners) {
      final p = Path()
        ..moveTo(pts[0].dx, pts[0].dy)
        ..lineTo(pts[1].dx, pts[1].dy)
        ..arcToPoint(pts[2], radius: const Radius.circular(r))
        ..lineTo(pts[3].dx, pts[3].dy);
      canvas.drawPath(p, paint);
    }
  }

  @override
  bool shouldRepaint(_) => false;
}

class _ReviewView extends StatelessWidget {
  final File fotoFile;
  final VoidCallback onPilih;
  final VoidCallback onUlang;

  const _ReviewView({
    required this.fotoFile,
    required this.onPilih,
    required this.onUlang,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        fit: StackFit.expand,
        children: [
          Image.file(fotoFile, fit: BoxFit.cover),

          Positioned(
            bottom: 0,
            left: 0,
            right: 0,
            height: 260,
            child: Container(
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [Colors.transparent, Colors.black87],
                ),
              ),
            ),
          ),

          Align(
            alignment: Alignment.topCenter,
            child: SafeArea(
              bottom: false,
              child: Padding(
                padding: const EdgeInsets.only(
                  left: AppConstants.paddingM,
                  right: AppConstants.paddingM,
                  top: 8,
                ),
              child: Row(
                children: [
                  GestureDetector(
                    onTap: onUlang,
                    child: Container(
                      width: 38,
                      height: 38,
                      decoration: BoxDecoration(
                        color: Colors.white.withOpacity(0.3),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.chevron_left_rounded,
                        color: Colors.white,
                        size: 24,
                      ),
                    ),
                  ),
                  Expanded(
                    child: Text(
                      'Lapor Sampah',
                      textAlign: TextAlign.center,
                      style: AppTextStyles.namafitur.copyWith(
                        color: Colors.white,
                        shadows: [
                          Shadow(color: Colors.black45, blurRadius: 6)
                        ],
                    ),
                  ),
                  ),
                  const SizedBox(width: 38),
                ],
              ),
            ),
            )
          ),

          Positioned(
            bottom: MediaQuery.of(context).padding.bottom + 40,
            left: AppConstants.paddingL,
            right: AppConstants.paddingL,
            child: Column(
              children: [
                SizedBox(
                  width: double.infinity,
                  height: 52,
                  child: ElevatedButton(
                    onPressed: onPilih,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      foregroundColor: const Color(0xFF1A2800),
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius:
                            BorderRadius.circular(AppConstants.radiusXL),
                      ),
                    ),
                    child: Text(
                      'Pilih foto',
                      style: AppTextStyles.buttonLabel.copyWith(
                        color: const Color(0xFF1A2800),
                        fontSize: 16,
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 12),

                SizedBox(
                  width: double.infinity,
                  height: 52,
                  child: ElevatedButton(
                    onPressed: onUlang,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.white,
                      foregroundColor: const Color(0xFF1A2800),
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius:
                            BorderRadius.circular(AppConstants.radiusXL),
                      ),
                    ),
                    child: Text(
                      'Ambil ulang foto',
                      style: AppTextStyles.buttonLabel.copyWith(
                        color: const Color(0xFF1A2800),
                        fontSize: 16,
                      ),
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
}

class _FormView extends StatefulWidget {
  final File fotoFile;
  final VoidCallback onUlang;

  const _FormView({required this.fotoFile, required this.onUlang});

  @override
  State<_FormView> createState() => _FormViewState();
}

class _FormViewState extends State<_FormView> {
  final _supabase = Supabase.instance.client;
  final _formKey = GlobalKey<FormState>();
  final _lokasiController = TextEditingController();
  final _deskripsiController = TextEditingController();

  String? _selectedJenis;
  bool _isLoadingLokasi = true;
  bool _isSubmitting = false;
  double? _latitude;
  double? _longitude;

  final List<String> _jenisSampah = [
    'Sampah Organik',
    'Sampah Plastik',
    'Sampah Kertas',
    'Sampah Elektronik',
    'Sampah B3 (berbahaya)',
    'Sampah Campur',
    'Lainnya',
  ];

  @override
  void initState() {
    super.initState();
    _getLokasiOtomatis();
  }

  @override
  void dispose() {
    _lokasiController.dispose();
    _deskripsiController.dispose();
    super.dispose();
  }

  Future<void> _getLokasiOtomatis() async {
    setState(() => _isLoadingLokasi = true);
    try {
      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.deniedForever) {
        _lokasiController.text = 'Izin lokasi ditolak';
        setState(() => _isLoadingLokasi = false);
        return;
      }

      final pos = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );
      _latitude = pos.latitude;
      _longitude = pos.longitude;

      final placemarks = await placemarkFromCoordinates(
        pos.latitude,
        pos.longitude
      );

      if (placemarks.isNotEmpty) {
        final p = placemarks.first;
        final parts = <String>[
          if (p.street?.isNotEmpty == true) p.street!,
          if (p.subLocality?.isNotEmpty == true) p.subLocality!,
          if (p.locality?.isNotEmpty == true) p.locality!,
        ];
        _lokasiController.text = parts.join(', ');
      }
    } catch (e) {
      _lokasiController.text = '';
    } finally {
      if (mounted) setState(() => _isLoadingLokasi = false);
    }
  }

  Future<void> _kirimLaporan() async {
    if (!_formKey.currentState!.validate()) return;

    final userId = _supabase.auth.currentUser?.id;

    if (userId == null) {
      _showSnackBar(
        'Silakan login terlebih dahulu',
        isError: true,
      );
      return;
    }

    setState(() => _isSubmitting = true);

    try {
      final now = DateTime.now();

      final userData = await _supabase
          .from('pengguna')
          .select('nama_lengkap')
          .eq('id_pengguna', userId)
          .single();

      final namaPengguna =
          (userData['nama_lengkap'] ?? 'pengguna')
              .toString();

      final tanggal =
          now.toIso8601String().split('T').first;

      final safeNama = namaPengguna
          .toLowerCase()
          .replaceAll(' ', '_');

      final baseFileName =
          '${tanggal}_$safeNama';

      final storagePath =
          'lapor_sampah/$baseFileName.webp';

      final compressedFile =
          await _compressToWebP(
        widget.fotoFile,
        baseFileName,
      );

      await _supabase.storage
          .from('media')
          .upload(
            storagePath,
            compressedFile,
            fileOptions:
                const FileOptions(upsert: true),
          );

      final laporanResponse = await _supabase
          .from('lapor_sampah')
          .insert({
            'id_pengguna': userId,
            'foto_sampah': storagePath,
            'lokasi':
                _lokasiController.text.trim(),
            'latitude': _latitude,
            'longitude': _longitude,
            'jenis_sampah': _selectedJenis,
            'deskripsi':
                _deskripsiController.text.trim(),
            'status': 'menunggu',
            'created_at': now.toIso8601String(),
          })
          .select()
          .single();

      final idLaporan =
          laporanResponse['id_laporan'];

      await _supabase
          .from('riwayat_aktivitas')
          .insert({
        'id_pengguna': userId,
        'jenis_aktivitas': 'lapor_sampah',
        'id_referensi': idLaporan,
        'judul':
            'Laporan $_selectedJenis',
        'deskripsi':
            'Laporan sampah berhasil dikirim dan sedang menunggu verifikasi petugas.',
        'status': 'menunggu',
        'perubahan_poin': 0,
        'perubahan_saldo': null,
        'created_at': now.toIso8601String(),
      });

      if (!mounted) return;

      _showSnackBar(
        'Laporan berhasil dikirim!',
      );

      await Future.delayed(
        const Duration(milliseconds: 800),
      );

      if (mounted) Navigator.pop(context);
    } catch (e) {
      if (!mounted) return;

      setState(() => _isSubmitting = false);

      _showSnackBar(
        'Gagal mengirim laporan: $e',
        isError: true,
      );
    }
  }

  void _showSnackBar(String msg, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(msg),
        backgroundColor: isError ? Colors.redAccent : AppColors.secondary,
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF2F2F2),
      body: Stack(
        children: [
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            height: MediaQuery.of(context).padding.top + 100,
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
                Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: AppConstants.paddingM,
                    vertical: AppConstants.paddingM,
                  ),
                  child: Row(
                    children: [
                      GestureDetector(
                        onTap: widget.onUlang,
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
                          'Lapor Sampah',
                          textAlign: TextAlign.center,
                          style: AppTextStyles.namafitur,
                        ),
                      ),
                      const SizedBox(width: 38),
                    ],
                  ),
                ),

                Expanded(
                  child: Container(
                    decoration: const BoxDecoration(
                      color: AppColors.white,
                      borderRadius: BorderRadius.only(
                        topLeft: Radius.circular(AppConstants.radiusXL),
                        topRight: Radius.circular(AppConstants.radiusXL),
                      ),
                    ),
                    child: Form(
                      key: _formKey,
                      child: ListView(
                        padding: EdgeInsets.only(
                          left: AppConstants.paddingL,
                          right: AppConstants.paddingL,
                          top: AppConstants.paddingL,
                          bottom: MediaQuery.of(context).padding.bottom + 100,
                        ),
                        children: [
                          _buildLabel('Foto sampah'),
                          const SizedBox(height: AppConstants.paddingS),
                          _buildFotoPreview(),

                          const SizedBox(height: AppConstants.paddingM),

                          _buildLabel('Lokasi'),
                          const SizedBox(height: AppConstants.paddingS),
                          _buildLokasiField(),

                          const SizedBox(height: AppConstants.paddingM),

                          _buildLabel('Jenis sampah'),
                          const SizedBox(height: AppConstants.paddingS),
                          _buildDropdownJenis(),

                          const SizedBox(height: AppConstants.paddingM),

                          _buildLabel('Deskripsi tambahan'),
                          const SizedBox(height: AppConstants.paddingS),
                          _buildDeskripsiField(),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),

          Positioned(
            bottom: 0,
            left: 0,
            right: 0,
            child: Container(
              padding: EdgeInsets.fromLTRB(
                AppConstants.paddingL,
                AppConstants.paddingM,
                AppConstants.paddingL,
                MediaQuery.of(context).padding.bottom + AppConstants.paddingM,
              ),
              decoration: BoxDecoration(
                color: AppColors.white,
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.08),
                    blurRadius: 16,
                    offset: const Offset(0, -4),
                  ),
                ],
              ),
              child: SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton(
                  onPressed: _isSubmitting ? null : _kirimLaporan,
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
                  child: _isSubmitting
                      ? const SizedBox(
                          width: 22,
                          height: 22,
                          child: CircularProgressIndicator(
                            strokeWidth: 2.5,
                            color: AppColors.white,
                          ),
                        )
                      : Text(
                          'Kirim Laporan',
                          style: AppTextStyles.buttonLabel,
                        ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFotoPreview() {
    return ClipRRect(
      borderRadius: BorderRadius.circular(AppConstants.radiusL),
      child: SizedBox(
        width: double.infinity,
        height: 180,
        child: Image.file(widget.fotoFile, fit: BoxFit.cover),
      ),
    );
  }

  Widget _buildLokasiField() {
    return TextFormField(
      controller: _lokasiController,
      style: AppTextStyles.inputText,
      readOnly: _isLoadingLokasi,
      validator: (v) =>
          (v == null || v.trim().isEmpty) ? 'Lokasi wajib diisi' : null,
      decoration: InputDecoration(
        hintText: _isLoadingLokasi ? 'Mendeteksi lokasi...' : 'Masukkan lokasi',
        hintStyle: AppTextStyles.hintText,
        prefixIcon: _isLoadingLokasi
            ? const Padding(
                padding: EdgeInsets.all(12),
                child: SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: AppColors.secondary,
                  ),
                ),
              )
            : const Icon(Icons.location_on_outlined,
                color: AppColors.inputIcon, size: 20),
        suffixIcon: _isLoadingLokasi
            ? null
            : IconButton(
                icon: const Icon(Icons.refresh_rounded,
                    color: AppColors.secondary, size: 20),
                onPressed: _getLokasiOtomatis,
              ),
        filled: true,
        fillColor: const Color(0xFFF5F5F5),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppConstants.radiusXL),
          borderSide: BorderSide.none,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppConstants.radiusXL),
          borderSide: const BorderSide(color: Color(0xFFE0E0E0)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppConstants.radiusXL),
          borderSide: const BorderSide(color: AppColors.secondary, width: 1.5),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppConstants.radiusXL),
          borderSide: const BorderSide(color: Colors.redAccent),
        ),
      ),
    );
  }

  Widget _buildDropdownJenis() {
    return DropdownButtonFormField<String>(
      value: _selectedJenis,
      validator: (v) => v == null ? 'Pilih jenis sampah' : null,
      isExpanded: true,
      icon: const Icon(Icons.keyboard_arrow_down_rounded,
          color: AppColors.inputIcon, size: 20),
      style: AppTextStyles.inputText,
      dropdownColor: AppColors.white,
      borderRadius: BorderRadius.circular(AppConstants.radiusM),
      decoration: InputDecoration(
        hintText: 'Pilih jenis sampah',
        hintStyle: AppTextStyles.hintText,
        filled: true,
        fillColor: const Color(0xFFF5F5F5),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppConstants.radiusXL),
          borderSide: const BorderSide(color: Color(0xFFE0E0E0)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppConstants.radiusXL),
          borderSide: const BorderSide(color: AppColors.secondary, width: 1.5),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppConstants.radiusXL),
          borderSide: const BorderSide(color: Colors.redAccent),
        ),
      ),
      onChanged: (v) => setState(() => _selectedJenis = v),
      items: _jenisSampah
          .map((j) => DropdownMenuItem(
                value: j,
                child: Text(j, style: AppTextStyles.inputText),
              ))
          .toList(),
    );
  }

  Widget _buildDeskripsiField() {
    return TextFormField(
      controller: _deskripsiController,
      style: AppTextStyles.inputText,
      maxLines: 4,
      decoration: InputDecoration(
        hintText: 'Masukkan deskripsi berupa penanda\nlokasi, sampah dll',
        hintStyle: AppTextStyles.hintText,
        filled: true,
        fillColor: const Color(0xFFF5F5F5),
        contentPadding: const EdgeInsets.all(16),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppConstants.radiusL),
          borderSide: const BorderSide(color: Color(0xFFE0E0E0)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppConstants.radiusL),
          borderSide: const BorderSide(color: AppColors.secondary, width: 1.5),
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppConstants.radiusL),
          borderSide: BorderSide.none,
        ),
      ),
    );
  }

  Widget _buildLabel(String text) {
    return Text(text, style: AppTextStyles.label);
  }

  Future<File> _compressToWebP(
    File file,
    String fileName,
  ) async {
    final dir = await getTemporaryDirectory();

    final targetPath = '${dir.path}/$fileName.webp';

    int quality = 85;

    Uint8List? result;

    do {
      result = await FlutterImageCompress.compressWithFile(
        file.absolute.path,
        quality: quality,
        format: CompressFormat.webp,
      );

      quality -= 5;

      if (quality < 20) break;
    } while (
        result != null &&
        result.lengthInBytes > 300 * 1024);

    final compressedFile = File(targetPath);

    await compressedFile.writeAsBytes(result!);

    return compressedFile;
  }
}