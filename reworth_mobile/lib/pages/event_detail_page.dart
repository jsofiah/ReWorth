import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../models/event_model.dart';
import '../utils/app_colors.dart';
import '../utils/app_text_styles.dart';
import '../utils/app_constants.dart';
import '../utils/app_image_helper.dart';
import '../widgets/app_avatar_stack.dart';

class EventDetailPage extends StatefulWidget {
  final EventModel event;
  const EventDetailPage({super.key, required this.event});

  @override
  State<EventDetailPage> createState() => _EventDetailPageState();
}

class _EventDetailPageState extends State<EventDetailPage> {
  int _currentParticipants = 0;
  bool _isLoadingParticipants = true;
  bool _isRegistering = false;
  String? _organizerName;

  // Constants
  static const double _avatarSize = 20;
  static const int _maxAvatarDisplay = 3;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    await Future.wait([
      _loadParticipantData(),
      _loadOrganizer(),
    ]);
  }

  Future<void> _loadParticipantData() async {
    try {
      final supabase = Supabase.instance.client;
      final response = await supabase
          .from('pendaftar_event')
          .select('id_pendaftar_event')
          .eq('id_event', widget.event.idEvent);

      if (mounted) {
        setState(() {
          _currentParticipants = response.length;
          _isLoadingParticipants = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _isLoadingParticipants = false);
    }
  }

  Future<void> _loadOrganizer() async {
    if (widget.event.idPembuat == null) return;

    try {
      final response = await Supabase.instance.client
          .from('admin')
          .select('''
            nama_admin,
            role (nama_role)
          ''')
          .eq('id_admin', widget.event.idPembuat!)
          .maybeSingle();

      if (mounted) {
        setState(() {
          _organizerName = _formatOrganizerName(response);
        });
      }
    } catch (e) {
      debugPrint('Error loading organizer: $e');
    }
  }

  String? _formatOrganizerName(Map<String, dynamic>? response) {
    if (response == null) return null;

    final roleData = response['role'];
    final roleName = roleData != null ? roleData['nama_role']?.toString().toLowerCase() : null;

    switch (roleName) {
      case 'dlh':
        return 'DLH Kota Malang';
      case 'bank sampah':
        return 'Bank Sampah Kota Malang';
      default:
        return response['nama_admin'];
    }
  }

  Future<void> _daftarEvent() async {
    final supabase = Supabase.instance.client;
    final userId = supabase.auth.currentUser?.id;

    if (userId == null) {
      _showMessage('Silakan login terlebih dahulu', isError: true);
      return;
    }

    final maxKuota = widget.event.maxPartisipan ?? 0;
    if (_currentParticipants >= maxKuota) {
      _showMessage('Kuota peserta sudah penuh', isError: true);
      return;
    }

    setState(() => _isRegistering = true);

    try {
      await supabase.from('pendaftar_event').insert({
        'id_event': widget.event.idEvent,
        'id_user': userId,
        'created_at': DateTime.now().toIso8601String(),
      });

      if (mounted) {
        setState(() {
          _currentParticipants++;
          _isRegistering = false;
        });
        _showMessage('Berhasil mendaftar ke event!');
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isRegistering = false);
        _showMessage('Gagal mendaftar: ${e.toString()}', isError: true);
      }
    }
  }

  void _showMessage(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? Colors.red : AppColors.primary,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
    );
  }

  Future<void> _openGoogleMaps() async {
    final lat = widget.event.latitude;
    final lng = widget.event.longitude;
    if (lat == null || lng == null) return;

    final url = Uri.parse('https://www.google.com/maps/search/?api=1&query=$lat,$lng');
    try {
      await launchUrl(url, mode: LaunchMode.externalApplication);
    } catch (e) {
      debugPrint('Error opening maps: $e');
    }
  }

  String _formatTanggal(DateTime? date) {
    if (date == null) return '-';
    const bulan = [
      'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
      'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    return '${date.day} ${bulan[date.month - 1]} ${date.year}';
  }

  String _formatWaktu(String? time) {
    if (time == null || time.isEmpty) return '-';
    final parts = time.split(':');
    return parts.length >= 2 ? '${parts[0]}:${parts[1]} WIB' : time;
  }

  List<String> _parsePersyaratan(String raw) {
    return raw
        .split(RegExp(r'\n|•|-'))
        .map((e) => e.trim())
        .where((e) => e.isNotEmpty)
        .toList();
  }

  @override
  Widget build(BuildContext context) {
    final event = widget.event;
    final isFull = _currentParticipants >= (event.maxPartisipan ?? 0);
    final fotoUrl = AppImageHelper.fotoEvent(event.fotoEvent);

    return Scaffold(
      backgroundColor: Colors.white,
      body: CustomScrollView(
        slivers: [
          _buildAppBar(fotoUrl),
          _buildContent(event, isFull, fotoUrl),
        ],
      ),
    );
  }

  Widget _buildAppBar(String fotoUrl) {
    return SliverAppBar(
      expandedHeight: 260,
      pinned: true,
      backgroundColor: AppColors.secondary,
      systemOverlayStyle: SystemUiOverlayStyle.light,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(bottom: Radius.circular(32)),
      ),
      leading: Padding(
        padding: const EdgeInsets.all(8),
        child: CircleAvatar(
          backgroundColor: Colors.white.withOpacity(0.75),
          child: IconButton(
            icon: const Icon(Icons.arrow_back_ios_new, color: Colors.black, size: 18),
            onPressed: () => Navigator.pop(context),
          ),
        ),
      ),
      flexibleSpace: ClipRRect(
        borderRadius: const BorderRadius.only(bottomLeft: Radius.circular(32), bottomRight: Radius.circular(32)),
        child: FlexibleSpaceBar(
          background: Stack(
            fit: StackFit.expand,
            children: [
              if (widget.event.fotoEvent != null && widget.event.fotoEvent!.isNotEmpty)
                Image.network(fotoUrl, fit: BoxFit.cover, errorBuilder: (_, __, ___) => _buildPlaceholder())
              else
                _buildPlaceholder(),
              const DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [Colors.transparent, Colors.transparent, AppColors.secondary],
                    stops: [0.0, 0.6, 1.0],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildContent(EventModel event, bool isFull, String fotoUrl) {
    return SliverToBoxAdapter(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(event.namaEvent ?? 'Event', style: AppTextStyles.heading1.copyWith(fontSize: 24)),
            const SizedBox(height: 12),
            _buildParticipantInfo(),
            const SizedBox(height: 24),
            _buildSectionTitle('Deskripsi'),
            const SizedBox(height: 8),
            Text(event.deskripsi ?? '-', style: AppTextStyles.body),
            const SizedBox(height: 24),
            _buildSectionTitle('Narasumber'),
            const SizedBox(height: 8),
            Text(event.narasumber ?? '-', style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500)),
            const SizedBox(height: 24),
            _buildSectionTitle('Diselenggarakan Oleh'),
            const SizedBox(height: 8),
            _buildOrganizerInfo(),
            const SizedBox(height: 24),
            _buildSectionTitle('Pelaksanaan'),
            const SizedBox(height: 12),
            _buildInfoRow(Icons.calendar_month_outlined, 'Tanggal', _formatTanggal(event.tanggal)),
            const SizedBox(height: 12),
            _buildInfoRow(Icons.access_time_outlined, 'Waktu', _formatWaktu(event.waktu)),
            const SizedBox(height: 12),
            _buildInfoRow(Icons.location_on_outlined, 'Lokasi', event.lokasi ?? '-'),
            if (event.latitude != null && event.longitude != null) ...[
              const SizedBox(height: 16),
              _buildMapPreview(event),
            ],
            if (event.persyaratan != null && event.persyaratan!.isNotEmpty) ...[
              const SizedBox(height: 24),
              _buildSectionTitle('Persyaratan'),
              const SizedBox(height: 8),
              ..._buildRequirements(event.persyaratan!),
            ],
            const SizedBox(height: 32),
            _buildRegisterButton(isFull),
            SizedBox(height: MediaQuery.of(context).padding.bottom + 24),
          ],
        ),
      ),
    );
  }

  Widget _buildParticipantInfo() {
    if (_isLoadingParticipants) {
      return const SizedBox(
        height: 32,
        child: Center(child: SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))),
      );
    }
    return Row(
      children: [
        AvatarStack(avatars: widget.event.avatarList, maxAvatars: _maxAvatarDisplay, avatarSize: _avatarSize),
        const Icon(Icons.people_outline, size: 16, color: AppColors.textSecondary),
        const SizedBox(width: 6),
        Text(widget.event.kuotaText, style: AppTextStyles.bodyMedium),
      ],
    );
  }

  Widget _buildOrganizerInfo() {
    return Row(
      children: [
        Container(
          width: 42,
          height: 42,
          decoration: BoxDecoration(
            color: AppColors.secondary.withOpacity(0.12),
            borderRadius: BorderRadius.circular(12),
          ),
          child: const Icon(Icons.apartment_rounded, color: AppColors.secondary),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Text(
            _organizerName ?? 'Penyelenggara',
            style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
          ),
        ),
      ],
    );
  }

  Widget _buildInfoRow(IconData icon, String label, String value) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 40,
          height: 40,
          decoration: BoxDecoration(
            color: AppColors.secondary.withOpacity(0.12),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(icon, color: AppColors.secondary, size: 20),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: TextStyle(fontSize: 12, color: Colors.grey[500])),
              const SizedBox(height: 2),
              Text(value, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500)),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildMapPreview(EventModel event) {
    return GestureDetector(
      onTap: _openGoogleMaps,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(12),
        child: Stack(
          children: [
            Image.network(
              'https://maps.locationiq.com/v3/staticmap'
              '?key=pk.0303d6bc14a39a9d7f120d5475ce902f'
              '&center=${event.latitude},${event.longitude}'
              '&zoom=15&size=600x300'
              '&markers=icon:large-red-cutout|${event.latitude},${event.longitude}',
              height: 160,
              width: double.infinity,
              fit: BoxFit.cover,
              errorBuilder: (_, __, ___) => Container(
                height: 160,
                color: Colors.grey[200],
                child: const Center(child: Text('Map preview tidak tersedia')),
              ),
            ),
            Positioned(
              top: 12,
              right: 12,
              child: Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(20)),
                child: const Icon(Icons.open_in_new, size: 18),
              ),
            ),
          ],
        ),
      ),
    );
  }

  List<Widget> _buildRequirements(String persyaratan) {
    return _parsePersyaratan(persyaratan).map((item) {
      return Padding(
        padding: const EdgeInsets.only(bottom: 6),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.only(top: 5),
              child: Container(width: 6, height: 6, decoration: BoxDecoration(color: AppColors.primary, shape: BoxShape.circle)),
            ),
            const SizedBox(width: 10),
            Expanded(child: Text(item, style: TextStyle(fontSize: 14, color: Colors.grey[700]))),
          ],
        ),
      );
    }).toList();
  }

  Widget _buildRegisterButton(bool isFull) {
    return SizedBox(
      width: double.infinity,
      height: 52,
      child: ElevatedButton(
        onPressed: (isFull || _isRegistering) ? null : _daftarEvent,
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.secondary,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          elevation: 0,
        ),
        child: _isRegistering
            ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
            : Text(
                isFull ? 'Kuota Penuh' : 'Daftar sekarang',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: isFull ? Colors.grey[600] : Colors.white),
              ),
      ),
    );
  }

  Widget _buildSectionTitle(String title) {
    return Text(title, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold));
  }

  Widget _buildPlaceholder() {
    return Container(
      color: AppColors.primary.withOpacity(0.2),
      child: Center(child: Icon(Icons.image_outlined, size: 64, color: AppColors.primary.withOpacity(0.5))),
    );
  }
}