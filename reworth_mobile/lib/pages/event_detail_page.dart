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

  @override
  void initState() {
    super.initState();
    _loadParticipantData();
    _loadOrganizer();
  }

  Future<void> _loadParticipantData() async {
    try {
      final supabase = Supabase.instance.client;

      final countResponse = await supabase
          .from('pendaftar_event')
          .select('id_pendaftar_event')
          .eq('id_event', widget.event.idEvent);

      if (mounted) {
        setState(() {
          _currentParticipants = (countResponse as List).length;
          _isLoadingParticipants = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isLoadingParticipants = false);
      }
    }
  }

  Future<void> _daftarEvent() async {
    final supabase = Supabase.instance.client;
    final userId = supabase.auth.currentUser?.id;

    if (userId == null) {
      _showSnackBar('Silakan login terlebih dahulu', isError: true);
      return;
    }

    if (_currentParticipants >= (widget.event.maxPartisipan ?? 0)) {
      _showSnackBar('Kuota peserta sudah penuh', isError: true);
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
        _showSnackBar('Berhasil mendaftar ke event!');
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isRegistering = false);
        _showSnackBar('Gagal mendaftar: ${e.toString()}', isError: true);
      }
    }
  }

  void _showSnackBar(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? Colors.red : AppColors.primary,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
    );
  }

  String _formatTanggal(DateTime? date) {
    if (date == null) return '-';
    const bulan = [
      '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
      'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    return '${date.day} ${bulan[date.month]} ${date.year}';
  }

  String _formatWaktu(String? time) {
    if (time == null || time.isEmpty) return '-';

    final parts = time.split(':');

    if (parts.length < 2) return time;

    final jam = parts[0];
    final menit = parts[1];

    return '$jam:$menit WIB';
  }

  @override
  Widget build(BuildContext context) {
    final event = widget.event;
    final isFull = _currentParticipants >= (event.maxPartisipan ?? 0);
    final fotoUrl = AppImageHelper.fotoEvent(event.fotoEvent);

    return Scaffold(
      backgroundColor: Colors.white,
      body: Stack(
        children: [
          CustomScrollView(
            slivers: [
              SliverAppBar(
                expandedHeight: 260,
                pinned: true,
                backgroundColor: AppColors.secondary,
                systemOverlayStyle: SystemUiOverlayStyle.light,
                leading: Padding(
                  padding: const EdgeInsets.all(8),
                  child: CircleAvatar(
                    backgroundColor: Colors.white,
                    child: IconButton(
                      icon: const Icon(Icons.arrow_back_ios_new,
                          color: Colors.black, size: 18),
                      onPressed: () => Navigator.pop(context),
                    ),
                  ),
                ),
                flexibleSpace: FlexibleSpaceBar(
                  background: Stack(
                    fit: StackFit.expand,
                    children: [
                      if (event.fotoEvent != null && event.fotoEvent!.isNotEmpty)
                        Image.network(
                          fotoUrl,
                          fit: BoxFit.cover,
                          errorBuilder: (_, __, ___) => _placeholderImage(),
                        )
                      else
                        _placeholderImage(),

                      const DecoratedBox(
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.topCenter,
                            end: Alignment.bottomCenter,
                            colors: [
                              Colors.transparent,
                              Colors.transparent,
                              AppColors.secondary,
                            ],
                            stops: [0.0, 0.6, 1.0],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        event.namaEvent ?? 'Event',
                        style: const TextStyle(
                          fontSize: 24,
                          fontWeight: FontWeight.bold,
                          color: Colors.black87,
                          height: 1.3,
                        ),
                      ),

                      const SizedBox(height: 12),

                      _isLoadingParticipants
                          ? const SizedBox(
                              height: 32,
                              child: Center(
                                child: SizedBox(
                                  width: 20,
                                  height: 20,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                ),
                              ),
                            )
                          : Row(
                              children: [
                                AvatarStack(
                                  avatars: event.avatarList,
                                ),
                                const Icon(Icons.people_outline,
                                    size: 16, color: AppColors.textSecondary),
                                const SizedBox(width: 6),
                                Text(
                                  event.kuotaText,
                                  style: AppTextStyles.bodyMedium,
                                ),
                              ],
                            ),

                      const SizedBox(height: 24),

                      _sectionTitle('Deskripsi'),

                      const SizedBox(height: 8),

                      Text(
                        event.deskripsi ?? '-',
                        style: TextStyle(
                          fontSize: 14,
                          color: Colors.grey[700],
                          height: 1.6,
                        ),
                      ),

                      const SizedBox(height: 24),

                      _sectionTitle('Narasumber'),

                      const SizedBox(height: 8),

                      Text(
                        event.narasumber ?? '-',
                        style: const TextStyle(
                          fontSize: 14,
                          color: Colors.black87,
                          fontWeight: FontWeight.w500,
                        ),
                      ),

                      const SizedBox(height: 24),

                      _sectionTitle('Diselenggarakan Oleh'),

                      const SizedBox(height: 8),

                      Row(
                        children: [
                          Container(
                            width: 42,
                            height: 42,
                            decoration: BoxDecoration(
                              color: AppColors.secondary.withOpacity(0.12),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: const Icon(
                              Icons.apartment_rounded,
                              color: AppColors.secondary,
                            ),
                          ),

                          const SizedBox(width: 12),

                          Expanded(
                            child: Text(
                              _organizerName ?? 'Penyelenggara',
                              style: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w600,
                                color: Colors.black87,
                              ),
                            ),
                          ),
                        ],
                      ),

                      const SizedBox(height: 24),
                      _sectionTitle('Pelaksanaan'),

                      const SizedBox(height: 12),

                      _infoRow(
                        icon: Icons.calendar_month_outlined,
                        label: 'Tanggal',
                        value: _formatTanggal(event.tanggal),
                      ),

                      const SizedBox(height: 12),

                      _infoRow(
                        icon: Icons.access_time_outlined,
                        label: 'Waktu',
                        value: _formatWaktu(event.waktu),
                      ),

                      const SizedBox(height: 12),

                      _infoRow(
                        icon: Icons.location_on_outlined,
                        label: 'Lokasi',
                        value: event.lokasi ?? '-',
                      ),

                      if (event.latitude != null &&
                          event.longitude != null) ...[
                        const SizedBox(height: 16),

                        GestureDetector(
                          onTap: _openGoogleMaps,
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(12),
                            child: Stack(
                              children: [
                                Image.network(
                                  'https://maps.locationiq.com/v3/staticmap'
                                  '?key=pk.0303d6bc14a39a9d7f120d5475ce902f'
                                  '&center=${event.latitude},${event.longitude}'
                                  '&zoom=15'
                                  '&size=600x300'
                                  '&markers=icon:large-red-cutout|${event.latitude},${event.longitude}',
                                  height: 160,
                                  width: double.infinity,
                                  fit: BoxFit.cover,
                                ),

                                Positioned(
                                  top: 12,
                                  right: 12,
                                  child: Container(
                                    padding: const EdgeInsets.all(8),
                                    decoration: BoxDecoration(
                                      color: Colors.white,
                                      borderRadius: BorderRadius.circular(20),
                                    ),
                                    child: const Icon(
                                      Icons.open_in_new,
                                      size: 18,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],

                      if (event.persyaratan != null &&
                          event.persyaratan!.isNotEmpty) ...[
                        const SizedBox(height: 24),

                        _sectionTitle('Persyaratan'),

                        const SizedBox(height: 8),

                        ..._parsePersyaratan(
                          event.persyaratan!,
                        ).map(
                          (item) => Padding(
                            padding:
                                const EdgeInsets.only(bottom: 6),
                            child: Row(
                              crossAxisAlignment:
                                  CrossAxisAlignment.start,
                              children: [
                                Padding(
                                  padding:
                                      const EdgeInsets.only(top: 5),
                                  child: Container(
                                    width: 6,
                                    height: 6,
                                    decoration: BoxDecoration(
                                      color: AppColors.primary,
                                      shape: BoxShape.circle,
                                    ),
                                  ),
                                ),

                                const SizedBox(width: 10),

                                Expanded(
                                  child: Text(
                                    item,
                                    style: TextStyle(
                                      fontSize: 14,
                                      color: Colors.grey[700],
                                      height: 1.5,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],

                      const SizedBox(height: 32),

                      SizedBox(
                        width: double.infinity,
                        height: 52,
                        child: ElevatedButton(
                          onPressed:
                            (isFull || _isRegistering)
                                ? null
                                : _daftarEvent,
                          style: ElevatedButton.styleFrom(
                            backgroundColor:
                                AppColors.secondary,
                            shape: RoundedRectangleBorder(
                              borderRadius:
                                  BorderRadius.circular(14),
                            ),
                            elevation: 0,
                          ),
                          child: _isRegistering
                              ? const SizedBox(
                                  width: 22,
                                  height: 22,
                                  child:
                                      CircularProgressIndicator(
                                    color: Colors.white,
                                    strokeWidth: 2.5,
                                  ),
                                )
                              : Text(
                                  isFull
                                      ? 'Kuota Penuh'
                                      : 'Daftar sekarang',
                                  style: TextStyle(
                                    fontSize: 16,
                                    fontWeight:
                                        FontWeight.w600,
                                    color:
                                        isFull
                                            ? Colors.grey[600]
                                            : Colors.white,
                                  ),
                                ),
                        ),
                      ),

                      SizedBox(
                        height:
                            MediaQuery.of(context)
                                    .padding
                                    .bottom +
                                24,
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _sectionTitle(String title) {
    return Text(
      title,
      style: const TextStyle(
        fontSize: 16,
        fontWeight: FontWeight.bold,
        color: Colors.black87,
      ),
    );
  }

  Widget _infoRow({
    required IconData icon,
    required String label,
    required String value,
  }) {
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
          child: Icon(
            icon,
            color: AppColors.secondary,
            size: 20,
          ),
        ),

        const SizedBox(width: 12),

        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: TextStyle(
                  fontSize: 12,
                  color: Colors.grey[500],
                ),
              ),

              const SizedBox(height: 2),

              Text(
                value,
                softWrap: true,
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                  color: Colors.black87,
                  height: 1.4,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _placeholderImage() {
    return Container(
      color: AppColors.primary.withOpacity(0.2),
      child: Center(
        child: Icon(Icons.image_outlined,
            size: 64, color: AppColors.primary.withOpacity(0.5)),
      ),
    );
  }

  List<String> _parsePersyaratan(String raw) {
    return raw
        .split(RegExp(r'\n|•|-'))
        .map((e) => e.trim())
        .where((e) => e.isNotEmpty)
        .toList();
  }

  Future<void> _loadOrganizer() async {
    if (widget.event.idPembuat == null) return;

    final name = await _getOrganizerName(
      widget.event.idPembuat!,
    );

    if (mounted) {
      setState(() {
        _organizerName = name;
      });
    }
  }

  Future<String?> _getOrganizerName(String idPembuat) async {
    try {
      final response = await Supabase.instance.client
          .from('admin')
          .select('''
            nama_admin,
            role (
              nama_role
            )
          ''')
          .eq('id_admin', idPembuat)
          .maybeSingle();

      if (response == null) return null;

      final roleData = response['role'];
      final roleName =
          roleData != null ? roleData['nama_role'] : null;

      if (roleName == null) return response['nama_admin'];

      switch (roleName.toString().toLowerCase()) {
        case 'dlh':
          return 'DLH Kota Malang';

        case 'bank sampah':
          return 'Bank Sampah Kota Malang';

        default:
          return roleName;
      }
    } catch (e) {
      debugPrint('Error organizer: $e');
      return null;
    }
  }

  Future<void> _openGoogleMaps() async {
    final lat = widget.event.latitude;
    final lng = widget.event.longitude;

    if (lat == null || lng == null) return;

    final Uri googleMapsUrl = Uri.parse(
      'https://www.google.com/maps/search/?api=1&query=$lat,$lng',
    );

    try {
      await launchUrl(
        googleMapsUrl,
        mode: LaunchMode.externalApplication,
      );
    } catch (e) {
      debugPrint('Error membuka maps: $e');
    }
  }
}
