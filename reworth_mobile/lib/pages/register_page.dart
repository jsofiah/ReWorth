import 'package:flutter/material.dart';
import 'package:reworth_mobile/pages/home_page.dart';
import 'package:reworth_mobile/pages/login_page.dart';
import '../utils/app_colors.dart';
import '../utils/app_constants.dart';
import '../utils/app_text_styles.dart';
import '../widgets/app_text_field.dart';
import '../widgets/app_primary_button.dart';
import '../services/location_service.dart';
import '../services/nominatim_service.dart';
import '../services/auth_service.dart';
import '../services/fcm_service.dart';
import '../models/location_model.dart';
import 'dart:math';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

class RegisterPage extends StatefulWidget {
  const RegisterPage({super.key});

  @override
  State<RegisterPage> createState() => _RegisterPageState();
}

class _RegisterPageState extends State<RegisterPage> {
  final _formKey = GlobalKey<FormState>();

  final _namaController = TextEditingController();
  final _phoneController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();
  final _alamatController = TextEditingController();
  final _kecamatanController = TextEditingController();
  final _kelurahanController = TextEditingController();
  final _rwController = TextEditingController();

  double? _latitude;
  double? _longitude;
  List<Map<String, dynamic>> _wilayahList = [];
  String? _selectedWilayahId;

  String? _selectedKecamatan;
  String? _selectedKelurahan;
  String? _selectedRw;

  List<String> _kecamatanList = [];
  List<String> _kelurahanList = [];
  List<String> _rwList = [];

  bool _rememberMe = false;
  bool _isLoading = false;

  bool _isLoadingWilayah = true;
  bool _isKetuaRW = false;

  @override
  void dispose() {
    _namaController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    _alamatController.dispose();
    _kecamatanController.dispose();
    _kelurahanController.dispose();
    _rwController.dispose();
    super.dispose();
  }

  Future<void> _onRegister() async {
    if (!_formKey.currentState!.validate()) return;

    // VALIDASI DULU sebelum loading
    if (_latitude == null || _longitude == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Silakan ambil koordinat terlebih dahulu")),
      );
      return;
    }

    if (_selectedWilayahId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Pilih wilayah terlebih dahulu")),
      );
      return;
    }

    setState(() => _isLoading = true);

    try {
      final token =
          await FirebaseMessaging.instance.getToken();

      print("FCM TOKEN REGISTER: $token");

      await AuthService.register(
        nama: _namaController.text,
        email: _emailController.text,
        password: _passwordController.text,
        phone: _phoneController.text,
        alamat: _alamatController.text,
        idWilayah: _selectedWilayahId!,
        latitude: _latitude!,
        longitude: _longitude!,
        fcmToken: token,
      );

      await FCMService.initialize();

      if (_isKetuaRW) {
        final user = Supabase.instance.client.auth.currentUser;
        if (user != null) {
          // Cek apakah wilayah sudah memiliki ketua RW
          final existing = await Supabase.instance.client
              .from('wilayah')
              .select('id_ketua_rw')
              .eq('id_wilayah', _selectedWilayahId!)
              .maybeSingle();
          
          // Jika sudah ada ketua RW, tanyakan konfirmasi
          if (existing != null && 
              existing['id_ketua_rw'] != null && 
              existing['id_ketua_rw'] != user.id && 
              mounted) {
            final confirm = await showDialog<bool>(
              context: context,
              builder: (c) => AlertDialog(
                title: const Text('Ganti Ketua RW'),
                content: const Text('RW ini sudah memiliki Ketua RW. Ganti?'),
                actions: [
                  TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Batal')),
                  ElevatedButton(onPressed: () => Navigator.pop(c, true), child: const Text('Ganti')),
                ],
              ),
            );
            
            if (confirm != true) {
              setState(() => _isLoading = false);
              return;
            }
          }
          
          // Update wilayah dengan id_ketua_rw
          await Supabase.instance.client
              .from('wilayah')
              .update({'id_ketua_rw': user.id})
              .eq('id_wilayah', _selectedWilayahId!);
        }
      }


      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Register berhasil")),
      );

      // pindah ke homepage
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (_) => const HomePage()),
      );

    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text("Error: $e")),
      );
    }

    if (mounted) setState(() => _isLoading = false);
  }

  void _onMasuk() {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => LoginPage()),
    );
  }

  @override
  void initState() {
    super.initState();
    _loadWilayah();
  }

  Future<void> _loadWilayah() async {
    setState(() => _isLoadingWilayah = true);

    final data = await AuthService.getWilayah();

    final kecamatanSet = data
        .map((e) => e['kecamatan'] as String)
        .toSet()
        .toList();

    setState(() {
      _wilayahList = data;
      _kecamatanList = kecamatanSet;
      _isLoadingWilayah = false;
    });
  }


  void _onKecamatanChanged(String? val) {
    _selectedKecamatan = val;
    _selectedKelurahan = null;
    _selectedRw = null;

    final kelurahanSet = _wilayahList
        .where((w) => w['kecamatan'] == val)
        .map((e) => e['kelurahan'] as String)
        .toSet()
        .toList();

    setState(() {
      _kelurahanList = kelurahanSet;
      _rwList = [];
    });
  }

  void _onKelurahanChanged(String? val) {
    _selectedKelurahan = val;
    _selectedRw = null;

    final rwList = _wilayahList
        .where((w) =>
            w['kecamatan'] == _selectedKecamatan &&
            w['kelurahan'] == val)
        .map((e) => e['rw'] as String)
        .toList();

    setState(() {
      _rwList = rwList;
    });
  }

  void _onRwChanged(String? val) {
    _selectedRw = val;

    final selected = _wilayahList.firstWhere(
      (w) =>
          w['kecamatan'] == _selectedKecamatan &&
          w['kelurahan'] == _selectedKelurahan &&
          w['rw'] == val,
    );

    setState(() {
      _selectedWilayahId = selected['id_wilayah'];
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      body: Stack(
        children: [
          Positioned.fill(
            child: Transform(
              alignment: Alignment.center,
              transform: Matrix4.identity()..scale(1.0, -1.0),
              child: Container(
                decoration: const BoxDecoration(
                  image: DecorationImage(
                    image: AssetImage('assets/gradient.png'),
                    fit: BoxFit.cover,
                  ),
                ),
              ),
            ),
          ),
          SafeArea(
            child: Column(
              children: [
                _buildHeader(),
                Expanded(child: _buildFormCard()),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHeader() {
    return Padding(
      padding: const EdgeInsets.all(AppConstants.paddingL),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Image.asset('assets/logo.png', width: 52, height: 52),
          const SizedBox(width: 12),
          Text(
            'ReWorth',
            style: AppTextStyles.headline.copyWith(fontSize: 30),
          )
        ],
      ),
    );
  }

  Widget _buildFormCard() {
    return Container(
      width: double.infinity,
      decoration: const BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.only(
          topLeft: Radius.circular(AppConstants.radiusXL),
          topRight: Radius.circular(AppConstants.radiusXL),
        ),
      ),
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(AppConstants.paddingL),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Column(
                  children: [
                    Text(
                      'Buat akun baru',
                      style: AppTextStyles.heading1,
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: AppConstants.paddingS),
                    Text(
                      'Masukkan nama, email, dan kata \nsandi untuk mendaftar akun.',
                      style: AppTextStyles.bodyMedium,
                      textAlign: TextAlign.center,
                    ),
                  ],
                )
              ),

              const SizedBox(height: 32),

              Text('Nama lengkap', style: AppTextStyles.label),
              const SizedBox(height: AppConstants.paddingS),
              AppTextField(
                hint: 'Nama lengkap',
                prefixIcon: Icons.person_outline,
                controller: _namaController,
                validator: (val) {
                  if (val == null || val.isEmpty) {
                    return 'Nama tidak boleh kosong';
                  }
                  return null;
                },
              ),

              const SizedBox(height: AppConstants.paddingM),

              Text('Nomor telepon', style: AppTextStyles.label),
              const SizedBox(height: AppConstants.paddingS),
              AppTextField(
                hint: '628xxxxxxxxxx',
                prefixIcon: Icons.phone_outlined,
                controller: _phoneController,
                keyboardType: TextInputType.phone,
                validator: (val) {
                  if (val == null || val.isEmpty) {
                    return 'Nomor telepon tidak boleh kosong';
                  }
                  if (val.startsWith('0')) {
                    return 'Nomor telepon tidak boleh diawali 0, gunakan 62';
                  }
                  if (!val.startsWith('62')) {
                    return 'Nomor telepon harus diawali dengan 62';
                  }
                  return null;
                },

              ),

              const SizedBox(height: AppConstants.paddingM),

              Text('Alamat email', style: AppTextStyles.label),
              const SizedBox(height: AppConstants.paddingS),
              AppTextField(
                hint: 'Alamat email',
                prefixIcon: Icons.mail_outline_rounded,
                controller: _emailController,
                keyboardType: TextInputType.emailAddress,
                validator: (val) {
                  if (val == null || val.isEmpty) {
                    return 'Email tidak boleh kosong';
                  }
                  if (!val.contains('@')) {
                    return 'Format email tidak valid';
                  }
                  return null;
                },
              ),

              const SizedBox(height: AppConstants.paddingM),

              Text('Password', style: AppTextStyles.label),
              const SizedBox(height: AppConstants.paddingS),
              AppTextField(
                hint: 'Password',
                prefixIcon: Icons.lock_outline_rounded,
                isPassword: true,
                controller: _passwordController,
                validator: (val) {
                  if (val == null || val.isEmpty) {
                    return 'Password tidak boleh kosong';
                  }
                  if (val.length < 6) {
                    return 'Minimal 6 karakter';
                  }
                  return null;
                },
              ),

              const SizedBox(height: AppConstants.paddingM),

              Text('Ulangi password', style: AppTextStyles.label),
              const SizedBox(height: AppConstants.paddingS),
              AppTextField(
                hint: 'Ulangi password',
                prefixIcon: Icons.lock_outline_rounded,
                isPassword: true,
                controller: _confirmPasswordController,
                validator: (val) {
                  if (val != _passwordController.text) {
                    return 'Password tidak sama';
                  }
                  return null;
                },
              ),

              const SizedBox(height: AppConstants.paddingM),

              Text('Alamat detail', style: AppTextStyles.label),
              const SizedBox(height: AppConstants.paddingS),

              AppTextField(
                hint: 'Masukkan alamat lengkap (jalan, patokan, dll)',
                prefixIcon: Icons.home_outlined,
                controller: _alamatController,
                maxLines: 2,
                validator: (val) {
                  if (val == null || val.isEmpty) {
                    return 'Alamat tidak boleh kosong';
                  }
                  return null;
                },
              ),

              const SizedBox(height: 8),

              TextButton.icon(
                onPressed: _getCurrentLocation,
                icon: const Icon(Icons.my_location),
                label: const Text("Ambil Koordinat"),
              ),

              if (_latitude != null && _longitude != null)
                Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Text(
                    "Koordinat berhasil diambil",
                    style: AppTextStyles.caption.copyWith(color: Colors.green),
                  ),
                ),

                const SizedBox(height: AppConstants.paddingM),

                
                Text('Kecamatan', style: AppTextStyles.label),
                const SizedBox(height: AppConstants.paddingS),

                _isLoadingWilayah
                    ? const Center(child: CircularProgressIndicator())
                    : DropdownButtonFormField<String>(
                        value: _selectedKecamatan,
                        isExpanded: true,
                        hint: const Text(
                          "Pilih Kecamatan",
                          overflow: TextOverflow.ellipsis,
                        ),
                        decoration: InputDecoration(
                          filled: true,
                          fillColor: AppColors.white,
                          contentPadding: const EdgeInsets.symmetric(
                            horizontal: AppConstants.paddingM,
                            vertical: 16,
                          ),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(AppConstants.radiusXL),
                            borderSide: const BorderSide(color: AppColors.inputBorder),
                          ),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(AppConstants.radiusXL),
                            borderSide: const BorderSide(color: AppColors.inputBorder),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(AppConstants.radiusXL),
                            borderSide: const BorderSide(color: AppColors.secondary, width: 1.5),
                          ),
                        ),
                        items: _kecamatanList
                            .map((k) => DropdownMenuItem(
                                  value: k,
                                  child: Text(
                                    k,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ))
                            .toList(),
                        onChanged: _kecamatanList.isEmpty ? null : _onKecamatanChanged,
                      ),

                const SizedBox(height: AppConstants.paddingM),

                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('Kelurahan', style: AppTextStyles.label),
                          const SizedBox(height: AppConstants.paddingS),
                          
                          DropdownButtonFormField<String>(
                            value: _selectedKelurahan,
                            isExpanded: true,
                            hint: Text(
                              _selectedKecamatan == null
                                  ? "Pilih kecamatan dulu"
                                  : "Pilih Kelurahan",
                              overflow: TextOverflow.ellipsis,
                            ),
                            decoration: InputDecoration(
                              filled: true,
                              fillColor: AppColors.white,
                              contentPadding: const EdgeInsets.symmetric(
                                horizontal: AppConstants.paddingM,
                                vertical: 16,
                              ),
                              border: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(AppConstants.radiusXL),
                                borderSide: const BorderSide(color: AppColors.inputBorder),
                              ),
                              enabledBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(AppConstants.radiusXL),
                                borderSide: const BorderSide(color: AppColors.inputBorder),
                              ),
                              focusedBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(AppConstants.radiusXL),
                                borderSide: const BorderSide(color: AppColors.secondary, width: 1.5),
                              ),
                            ),
                            items: _kelurahanList
                                .map((k) => DropdownMenuItem(
                                      value: k,
                                      child: Text(
                                        k,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ))
                                .toList(),
                            onChanged: _selectedKecamatan == null || _kelurahanList.isEmpty
                                ? null
                                : (val) {
                                    _onKelurahanChanged(val);
                                  },
                          ),
                        ],
                      ),
                    ),
                    
                    const SizedBox(width: 12),
                    
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('RW', style: AppTextStyles.label),
                          const SizedBox(height: AppConstants.paddingS),
                          
                          DropdownButtonFormField<String>(
                            value: _selectedRw,
                            isExpanded: true,
                            hint: Text(
                              _selectedKelurahan == null
                                  ? "Pilih kelurahan dulu"
                                  : "Pilih RW",
                              overflow: TextOverflow.ellipsis,
                            ),
                            decoration: InputDecoration(
                              filled: true,
                              fillColor: AppColors.white,
                              contentPadding: const EdgeInsets.symmetric(
                                horizontal: AppConstants.paddingM,
                                vertical: 16,
                              ),
                              border: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(AppConstants.radiusXL),
                                borderSide: const BorderSide(color: AppColors.inputBorder),
                              ),
                              enabledBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(AppConstants.radiusXL),
                                borderSide: const BorderSide(color: AppColors.inputBorder),
                              ),
                              focusedBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(AppConstants.radiusXL),
                                borderSide: const BorderSide(color: AppColors.secondary, width: 1.5),
                              ),
                            ),
                            items: _rwList
                                .map((r) => DropdownMenuItem(
                                      value: r,
                                      child: Text(
                                        "RW $r",
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ))
                                .toList(),
                            onChanged: _selectedKelurahan == null || _rwList.isEmpty
                                ? null
                                : (val) {
                                    _onRwChanged(val);
                                  },
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),

                Container(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FFF0),
                    borderRadius: BorderRadius.circular(AppConstants.radiusM),
                    border: Border.all(color: AppColors.primary.withOpacity(0.3)),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Checkbox(
                        value: _isKetuaRW,
                        onChanged: (v) => setState(() => _isKetuaRW = v ?? false),
                        activeColor: const Color(0xFF7CA73B),
                        materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                      ),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Padding(
                          padding: const EdgeInsets.only(top: 12, right: 12),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Daftar sebagai Ketua RW',
                                style: AppTextStyles.body.copyWith(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                              if (_isKetuaRW)
                                Padding(
                                  padding: const EdgeInsets.only(top: 4),
                                  child: Text(
                                    'Reward warga RW ini akan disalurkan melalui Ketua RW',
                                    style: AppTextStyles.caption.copyWith(
                                      color: Colors.grey.shade600,
                                      fontSize: 11,
                                    ),
                                  ),
                                ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),

              AppPrimaryButton(
                label: "Sign Up",
                onPressed: _onRegister,
                isLoading: _isLoading,
              ),

              const SizedBox(height: AppConstants.paddingL),

              const Divider(color: AppColors.divider, thickness: 1),

              const SizedBox(height: AppConstants.paddingM),

              Center(
                child: GestureDetector(
                  onTap: _onMasuk,
                  child: RichText(
                    text: TextSpan(
                      children: [
                        TextSpan(
                          text: 'Sudah punya akun? ',
                          style: AppTextStyles.caption,
                        ),
                        TextSpan(
                          text: 'Masuk',
                          style: AppTextStyles.captionBold,
                        ),
                      ],
                    ),
                  ),
                ),
              ),

              const SizedBox(height: AppConstants.paddingM),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _getCurrentLocation() async {
    final result = await LocationService.getCurrentLocation();

    if (result != null) {
      setState(() {
        _latitude = result.latitude;
        _longitude = result.longitude;
      });

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Koordinat berhasil diambil")),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Gagal ambil lokasi")),
      );
    }
  }
}

class _CustomCheckbox extends StatelessWidget {
  final bool value;
  final ValueChanged<bool?> onChanged;

  const _CustomCheckbox({required this.value, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => onChanged(!value),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        width: 20,
        height: 20,
        decoration: BoxDecoration(
          color: value ? AppColors.secondary : AppColors.white,
          border: Border.all(
            color: value ? AppColors.secondary : AppColors.checkboxBorder,
            width: 1.5,
          ),
          borderRadius: BorderRadius.circular(AppConstants.radiusS / 2),
        ),
        child: value
            ? const Icon(Icons.check, size: 13, color: AppColors.white)
            : null,
      ),
    );
  }
}