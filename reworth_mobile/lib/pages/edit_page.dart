import 'dart:io';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:image_picker/image_picker.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../models/user_model.dart';
import '../services/auth_service.dart';
import '../utils/app_colors.dart';
import '../utils/app_constants.dart';
import '../utils/app_image_helper.dart';
import '../utils/app_text_styles.dart';

class EditPage extends StatefulWidget {
  const EditPage({super.key});

  @override
  State<EditPage> createState() => _EditPageState();
}

class _EditPageState extends State<EditPage> {
  final _supabase = Supabase.instance.client;
  
  UserModel? _user;
  bool _isLoading = true;
  bool _isSaving = false;
  bool _isLoadingWilayah = true;

  final _nama = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _alamat = TextEditingController();
  final _password = TextEditingController();

  String? _kecamatan, _kelurahan, _rw, _idWilayah;
  List<Map<String, dynamic>> _wilayahList = [];
  List<String> _kecamatanList = [], _kelurahanList = [], _rwList = [];
  bool _isKetuaRW = false;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  @override
  void dispose() {
    _nama.dispose();
    _email.dispose();
    _phone.dispose();
    _alamat.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _loadData() async {
    await _loadWilayah();
    await _loadUser();
  }

  Future<void> _loadWilayah() async {
    setState(() => _isLoadingWilayah = true);
    _wilayahList = await AuthService.getWilayah();
    _kecamatanList = _wilayahList.map((w) => w['kecamatan'].toString()).toSet().toList();
    setState(() => _isLoadingWilayah = false);
  }

  Future<void> _loadUser() async {
    final data = await AuthService.getCurrentUserData();
    if (data != null) {
      _user = UserModel.fromMap(data);
      _nama.text = _user?.namaLengkap ?? '';
      _email.text = _user?.email ?? '';
      _phone.text = _user?.noTelepon ?? '';
      _alamat.text = _user?.alamatDetail ?? '';
      
      final userWilayah = await _supabase
          .from('pengguna')
          .select('id_wilayah')
          .eq('id_pengguna', _user!.idPengguna)
          .maybeSingle();
      _idWilayah = userWilayah?['id_wilayah']?.toString();
      
      if (_idWilayah != null) {
        final w = _wilayahList.firstWhere(
          (w) => w['id_wilayah'] == _idWilayah,
          orElse: () => {},
        );
        if (w.isNotEmpty) {
          _kecamatan = w['kecamatan'];
          _kelurahan = w['kelurahan'];
          _rw = w['rw'].toString();
          _kelurahanList = _wilayahList
              .where((w) => w['kecamatan'] == _kecamatan)
              .map((w) => w['kelurahan'].toString())
              .toSet()
              .toList();
          _rwList = _wilayahList
              .where((w) => w['kecamatan'] == _kecamatan && w['kelurahan'] == _kelurahan)
              .map((w) => w['rw'].toString())
              .toList();
        }
        final wilayahData = await _supabase
            .from('wilayah')
            .select('id_ketua_rw')
            .eq('id_wilayah', _idWilayah!)
            .maybeSingle();
        _isKetuaRW = wilayahData?['id_ketua_rw'] == _user!.idPengguna;
      }
    }
    setState(() => _isLoading = false);
  }

  Future<void> _saveProfile() async {
    setState(() => _isSaving = true);
    try {
      final user = _supabase.auth.currentUser;
      if (user == null) throw Exception("User tidak login");
      
      final update = <String, dynamic>{};
      if (_nama.text.isNotEmpty) update['nama_lengkap'] = _nama.text;
      if (_phone.text.isNotEmpty) update['no_telepon'] = _phone.text;
      if (_alamat.text.isNotEmpty) update['alamat_detail'] = _alamat.text;
      if (_idWilayah != null) update['id_wilayah'] = _idWilayah;
      if (update.isNotEmpty) {
        await _supabase.from('pengguna').update(update).eq('id_pengguna', user.id);
      }
      
      if (_idWilayah != null) {
        if (_isKetuaRW) {
          final existing = await _supabase
              .from('wilayah')
              .select('id_ketua_rw')
              .eq('id_wilayah', _idWilayah!)
              .maybeSingle();
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
              setState(() => _isKetuaRW = false);
              setState(() => _isSaving = false);
              return;
            }
          }
          await _supabase
              .from('wilayah')
              .update({'id_ketua_rw': user.id})
              .eq('id_wilayah', _idWilayah!);
        } else {
          await _supabase
              .from('wilayah')
              .update({'id_ketua_rw': null})
              .eq('id_wilayah', _idWilayah!)
              .eq('id_ketua_rw', user.id);
        }
      }
      
      if (_password.text.isNotEmpty) {
        await _supabase.auth.updateUser(UserAttributes(password: _password.text));
      }
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text("Profil berhasil diperbarui"), backgroundColor: Colors.green)
        );
        Navigator.pop(context, true);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text("Gagal: $e"), backgroundColor: Colors.red)
        );
      }
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  Future<void> _pickImage(ImageSource source) async {
    final picked = await ImagePicker().pickImage(source: source);
    if (picked == null) return;
    setState(() => _isSaving = true);
    try {
      final user = _supabase.auth.currentUser;
      final path = 'pengguna/${user!.id}_${DateTime.now().millisecondsSinceEpoch}.${picked.path.split('.').last}';
      await _supabase.storage.from('media').upload(path, File(picked.path));
      await _supabase.from('pengguna').update({'foto_profil': path}).eq('id_pengguna', user.id);
      await _loadData();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text("Foto berhasil diubah"))
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text("Gagal: $e"))
        );
      }
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  void _showImageOptions() {
    final hasPhoto = _user?.fotoProfil != null && _user!.fotoProfil!.isNotEmpty;
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (c) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const SizedBox(height: 8),
          Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2))),
          const SizedBox(height: 16),
          if (hasPhoto) ListTile(
            leading: const Icon(Icons.visibility),
            title: const Text('Lihat Foto'),
            onTap: () => _showFullPhoto(),
          ),
          ListTile(
            leading: const Icon(Icons.camera_alt),
            title: const Text('Ambil dari Kamera'),
            onTap: () { Navigator.pop(c); _pickImage(ImageSource.camera); },
          ),
          ListTile(
            leading: const Icon(Icons.photo_library),
            title: const Text('Pilih dari Galeri'),
            onTap: () { Navigator.pop(c); _pickImage(ImageSource.gallery); },
          ),
          if (hasPhoto) ListTile(
            leading: const Icon(Icons.delete_outline, color: Colors.red),
            title: const Text('Hapus Foto', style: TextStyle(color: Colors.red)),
            onTap: () { Navigator.pop(c); _deletePhoto(); },
          ),
          ListTile(
            leading: const Icon(Icons.cancel),
            title: const Text('Batal'),
            onTap: () => Navigator.pop(c),
          ),
          const SizedBox(height: 8),
        ]),
      ),
    );
  }

  void _showFullPhoto() {
    showDialog(
      context: context,
      builder: (c) => Dialog(
        backgroundColor: Colors.transparent,
        child: Stack(children: [
          InteractiveViewer(
            minScale: 0.5,
            maxScale: 4,
            child: Image.network(AppImageHelper.fotoPengguna(_user!.fotoProfil!)),
          ),
          Positioned(
            top: 10,
            right: 10,
            child: GestureDetector(
              onTap: () => Navigator.pop(c),
              child: Container(
                decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.5), shape: BoxShape.circle),
                padding: const EdgeInsets.all(8),
                child: const Icon(Icons.close, color: Colors.white, size: 24),
              ),
            ),
          ),
        ]),
      ),
    );
  }

  Future<void> _deletePhoto() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Hapus Foto'),
        content: const Text('Yakin ingin menghapus foto profil?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Batal')),
          ElevatedButton(onPressed: () => Navigator.pop(c, true), child: const Text('Hapus')),
        ],
      ),
    );
    if (confirm != true) return;
    setState(() => _isSaving = true);
    try {
      final user = _supabase.auth.currentUser;
      await _supabase.from('pengguna').update({'foto_profil': null}).eq('id_pengguna', user!.id);
      await _loadData();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text("Foto berhasil dihapus"))
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text("Gagal: $e"))
        );
      }
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final topPad = MediaQuery.of(context).padding.top;
    final bgInput = const Color(0xFFF3F3F3);
    final borderInput = const Color(0xFF6E6E6E);
    
    return Scaffold(
      backgroundColor: const Color(0xFFF2F2F2),
      body: Stack(children: [
        Positioned(
          top: 0,
          left: 0,
          right: 0,
          height: topPad + 110,
          child: Image.asset('assets/gradient.png', fit: BoxFit.cover),
        ),
        SafeArea(
          child: Column(children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: AppConstants.paddingM, vertical: AppConstants.paddingM),
              child: Row(children: [
                GestureDetector(
                  onTap: () => Navigator.pop(context),
                  child: Container(
                    width: 38,
                    height: 38,
                    decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.55), shape: BoxShape.circle),
                    child: const Icon(Icons.chevron_left_rounded, color: Color(0xFF1A2800), size: 26),
                  ),
                ),
                Expanded(child: Center(child: Text('Edit Profil', style: AppTextStyles.namafitur))),
                const SizedBox(width: 38),
              ]),
            ),
            const SizedBox(height: AppConstants.paddingS),
            Expanded(
              child: Container(
                decoration: const BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.only(
                    topLeft: Radius.circular(AppConstants.radiusXL),
                    topRight: Radius.circular(AppConstants.radiusXL),
                  ),
                ),
                child: _isLoading
                    ? const Center(child: CircularProgressIndicator())
                    : SingleChildScrollView(
                        padding: const EdgeInsets.all(AppConstants.paddingL),
                        child: Column(children: [
                          Center(
                            child: Stack(children: [
                              CircleAvatar(
                                radius: 50,
                                backgroundColor: Colors.grey[200],
                                backgroundImage: _user?.fotoProfil != null
                                    ? NetworkImage(AppImageHelper.fotoPengguna(_user!.fotoProfil!))
                                    : null,
                                child: _user?.fotoProfil == null
                                    ? const Icon(Icons.person, size: 50)
                                    : null,
                              ),
                              Positioned(
                                bottom: 0,
                                right: 0,
                                child: GestureDetector(
                                  onTap: _showImageOptions,
                                  child: Container(
                                    width: 32,
                                    height: 32,
                                    decoration: BoxDecoration(
                                      color: AppColors.secondary,
                                      shape: BoxShape.circle,
                                      border: Border.all(color: Colors.white, width: 2),
                                    ),
                                    child: const Icon(Icons.camera_alt, size: 18, color: Colors.white),
                                  ),
                                ),
                              ),
                            ]),
                          ),
                          const SizedBox(height: AppConstants.paddingM),
                          _buildField('Nama Lengkap', _nama, bgInput, borderInput),
                          _buildField('Nomor Telepon', _phone, bgInput, borderInput, keyboard: TextInputType.phone),
                          _buildField('Email', _email, bgInput, borderInput, readOnly: true),
                          _buildField('Ubah Password', _password, bgInput, borderInput, isPassword: true, hint: 'Tulis jika password diperbarui'),
                          _buildField('Alamat Detail', _alamat, bgInput, borderInput, maxLines: 2),
                          const SizedBox(height: AppConstants.paddingS),
                          _buildDropdown('Kecamatan', _kecamatan, _kecamatanList, bgInput, borderInput, (v) => setState(() {
                            _kecamatan = v;
                            _kelurahan = null;
                            _rw = null;
                            _idWilayah = null;
                            _isKetuaRW = false;
                            _kelurahanList = v == null
                                ? []
                                : _wilayahList.where((w) => w['kecamatan'] == v).map((w) => w['kelurahan'].toString()).toSet().toList();
                            _rwList = [];
                          }), isLoading: _isLoadingWilayah),
                          Row(children: [
                            Expanded(
                              flex: 2,
                              child: _buildDropdown('Kelurahan', _kelurahan, _kelurahanList, bgInput, borderInput, (v) => setState(() {
                                _kelurahan = v;
                                _rw = null;
                                _idWilayah = null;
                                _isKetuaRW = false;
                                _rwList = v == null || _kecamatan == null
                                    ? []
                                    : _wilayahList.where((w) => w['kecamatan'] == _kecamatan && w['kelurahan'] == v).map((w) => w['rw'].toString()).toList();
                              }), enabled: _kecamatan != null, removePadding: true),
                            ),
                            const SizedBox(width: AppConstants.paddingM),
                            Expanded(
                              flex: 1,
                              child: _buildDropdown('RW', _rw, _rwList, bgInput, borderInput, (v) => setState(() {
                                _rw = v;
                                _idWilayah = null;
                                _isKetuaRW = false;
                                if (v != null && _kecamatan != null && _kelurahan != null) {
                                  final found = _wilayahList.firstWhere(
                                    (w) => w['kecamatan'] == _kecamatan && w['kelurahan'] == _kelurahan && w['rw'].toString() == v,
                                    orElse: () => {},
                                  );
                                  if (found.isNotEmpty) _idWilayah = found['id_wilayah'];
                                }
                              }), enabled: _kelurahan != null, removePadding: true),
                            ),
                          ]),
                          const SizedBox(height: AppConstants.paddingL),
                          Row(children: [
                            Checkbox(
                              value: _isKetuaRW,
                              onChanged: (v) => setState(() => _isKetuaRW = v ?? false),
                              activeColor: const Color(0xFF7CA73B),
                            ),
                            const SizedBox(width: AppConstants.paddingS),
                            Expanded(
                              child: Text(
                                'Reward warga RW ini akan disalurkan melalui Ketua RW. Centang jika Anda adalah Ketua RW.',
                                style: GoogleFonts.poppins(fontSize: 12),
                              ),
                            ),
                          ]),
                          const SizedBox(height: AppConstants.paddingXL),
                          SizedBox(
                            width: double.infinity,
                            height: 50,
                            child: ElevatedButton(
                              onPressed: _isSaving ? null : _saveProfile,
                              style: ElevatedButton.styleFrom(
                                backgroundColor: const Color(0xFF7CA73B),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(50)),
                              ),
                              child: _isSaving
                                  ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                                  : const Text('Simpan Perubahan', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: Colors.white)),
                            ),
                          ),
                          const SizedBox(height: AppConstants.paddingXL),
                        ]),
                      ),
              ),
            ),
          ]),
        ),
      ]),
    );
  }

  Widget _buildField(String label, TextEditingController c, Color bg, Color border, {TextInputType keyboard = TextInputType.text, bool readOnly = false, bool isPassword = false, int maxLines = 1, String hint = ''}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: AppConstants.paddingM),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label, style: GoogleFonts.poppins(fontSize: 14, fontWeight: FontWeight.w500)),
        const SizedBox(height: 8),
        Focus(
          onFocusChange: (_) => setState(() {}),
          child: Builder(
            builder: (context) {
              final bool hasFocus = Focus.of(context).hasFocus;
              return Container(
                height: 50,
                decoration: BoxDecoration(
                  color: bg,
                  border: Border.all(
                    color: hasFocus ? AppColors.secondary : border,
                    width: hasFocus ? 1.5 : 1,
                  ),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(10),
                  child: TextField(
                    controller: c,
                    readOnly: readOnly,
                    obscureText: isPassword,
                    keyboardType: keyboard,
                    maxLines: maxLines,
                    style: GoogleFonts.poppins(fontSize: 14),
                    decoration: InputDecoration(
                      hintText: hint,
                      hintStyle: GoogleFonts.poppins(fontSize: 14, color: Colors.grey),
                      border: InputBorder.none,
                      filled: true,
                      fillColor: bg,
                      contentPadding: const EdgeInsets.symmetric(horizontal: AppConstants.paddingM, vertical: 14),
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ]),
    );
  }

  Widget _buildDropdown(String label, String? value, List<String> items, Color bg, Color border, Function(String?) onChanged, {bool enabled = true, bool isLoading = false, bool removePadding = false}) {
    return Padding(
      padding: EdgeInsets.only(bottom: removePadding ? 0 : AppConstants.paddingM),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label, style: GoogleFonts.poppins(fontSize: 14, fontWeight: FontWeight.w500)),
        const SizedBox(height: 8),
        Focus(
          onFocusChange: (_) => setState(() {}),
          child: Builder(
            builder: (context) {
              final bool hasFocus = Focus.of(context).hasFocus;
              return Container(
                height: 50,
                decoration: BoxDecoration(
                  color: bg,
                  border: Border.all(
                    color: hasFocus ? AppColors.secondary : border,
                    width: hasFocus ? 1.5 : 1,
                  ),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(10),
                  child: isLoading
                      ? const Center(child: SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)))
                      : DropdownButtonHideUnderline(
                          child: DropdownButton<String>(
                            value: value,
                            isExpanded: true,
                            hint: Padding(
                              padding: const EdgeInsets.symmetric(horizontal: AppConstants.paddingM),
                              child: Text('Pilih $label', style: GoogleFonts.poppins(fontSize: 14, color: Colors.grey)),
                            ),
                            icon: Padding(
                              padding: const EdgeInsets.only(right: AppConstants.paddingM),
                              child: const Icon(Icons.arrow_drop_down, color: Colors.black54),
                            ),
                            items: items.map((o) => DropdownMenuItem(
                              value: o,
                              child: Padding(
                                padding: const EdgeInsets.symmetric(horizontal: AppConstants.paddingM),
                                child: Text(o, style: GoogleFonts.poppins(fontSize: 14)),
                              ),
                            )).toList(),
                            onChanged: enabled ? onChanged : null,
                          ),
                        ),
                ),
              );
            },
          ),
        ),
      ]),
    );
  }
}