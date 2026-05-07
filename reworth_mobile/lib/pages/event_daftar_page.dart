import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../models/event_model.dart';
import '../utils/app_colors.dart';
import '../utils/app_text_styles.dart';
import '../widgets/app_text_field.dart';
import '../utils/app_constants.dart';
import '../utils/app_image_helper.dart';

class EventDaftarPage extends StatefulWidget {
  final _supabase = Supabase.instance.client;
  final EventModel event;
  EventDaftarPage({super.key, required this.event});

  @override
  State<EventDaftarPage> createState() => _EventDaftarPageState();
}

class _EventDaftarPageState extends State<EventDaftarPage> {
  final _namaController = TextEditingController();
  final _phoneController = TextEditingController();
  final _emailController = TextEditingController();

  final _formKey = GlobalKey<FormState>();
  bool _isAgree = false;
  bool _isLoading = false;

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
                _buildTitleBar(),
                const SizedBox(height: 12),

                _buildFormCard(),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTitleBar() {
    return Padding(
      padding: const EdgeInsets.symmetric(
        horizontal: AppConstants.paddingM,
        vertical: AppConstants.paddingM,
      ),
      child: Row(
        children: [
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
          Expanded(
            child: Text(
              'Pendaftaran',
              style: AppTextStyles.namafitur,
              textAlign: TextAlign.center,
            ),
          ),
          const SizedBox(width: 38),
        ],
      ),
    );
  }

  Widget _buildFormCard() {
    return Expanded(
      child: Container(
        decoration: const BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.only(
            topLeft: Radius.circular(AppConstants.radiusXL),
            topRight: Radius.circular(AppConstants.radiusXL),
          ),
        ),
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(
            horizontal: AppConstants.paddingL,
            vertical: AppConstants.paddingXL,
          ),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Text(
                    widget.event.namaEvent ?? '-',
                    style: AppTextStyles.title.copyWith(fontSize: 20),
                    textAlign: TextAlign.center,
                  ),
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
                      return 'Nomor telepon tidak boleh diawali 0';
                    }
                    if (!val.startsWith('62')) {
                      return 'Harus diawali 62';
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

                const SizedBox(height: 20),

                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _CustomCheckbox(
                      value: _isAgree,
                      onChanged: (val) {
                        setState(() {
                          _isAgree = val ?? false;
                        });
                      },
                    ),
                    const SizedBox(width: 10),

                    Expanded(
                      child: Text(
                        'Saya telah membaca dan menyetujui syarat dan ketentuan yang berlaku.',
                        style: AppTextStyles.caption,
                      ),
                    ),
                  ],
                ),

                const SizedBox(height: 24),

                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: ElevatedButton(
                    onPressed: _isAgree && !_isLoading
                        ? _submitForm
                        : null,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.secondary,
                      disabledBackgroundColor:
                          AppColors.secondary.withOpacity(0.4),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(
                            AppConstants.radiusXL),
                      ),
                    ),
                    child: _isLoading
                        ? const CircularProgressIndicator(
                            color: Colors.white,
                          )
                        : Text(
                            'Kirim Formulir',
                            style: AppTextStyles.buttonLabel.copyWith(
                              color: Colors.white,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _submitForm() async {
    if (!_formKey.currentState!.validate()) return;

    if (!_isAgree) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Harap setujui syarat terlebih dahulu'),
        ),
      );
      return;
    }

    setState(() => _isLoading = true);

    try {
      final supabase = Supabase.instance.client;

      final currentUser = supabase.auth.currentUser;

      if (currentUser == null) {
        throw Exception('User belum login');
      }

      final emailUser = currentUser.email;

      // cari id_pengguna berdasarkan email login
      final pengguna = await supabase
          .from('pengguna')
          .select('id_pengguna')
          .eq('email', emailUser!)
          .single();

      final idPengguna = pengguna['id_pengguna'];

      final insertedPendaftar = await supabase
        .from('pendaftar_event')
        .insert({
          'id_event': widget.event.idEvent,
          'nama_lengkap': _namaController.text,
          'no_telepon': _phoneController.text,
          'email': _emailController.text,
          'id_pengguna': idPengguna,
        })
        .select()
        .single();

    final idPendaftarEvent =
        insertedPendaftar['id_pendaftar_event'];

    await supabase.from('riwayat_aktivitas').insert({
      'id_pengguna': idPengguna,
      'jenis_aktivitas': 'pendaftar_event',
      'id_referensi': idPendaftarEvent,
      'judul': 'Pendaftaran Event',

      'deskripsi':
          'Anda berhasil mendaftar event ${widget.event.namaEvent} '
          'yang akan dilaksanakan pada '
          '${widget.event.tanggalFormatted} '
          'pukul ${widget.event.waktuFormatted} WIB '
          'di ${widget.event.lokasi}.',

      'status': 'terdaftar',
      'perubahan_poin': 0,
      'perubahan_saldo': null,
    });

      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Berhasil daftar event!'),
        ),
      );

      Navigator.pop(context);

    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Gagal: $e')),
      );
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
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