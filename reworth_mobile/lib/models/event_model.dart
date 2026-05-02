class EventModel {
  final String idEvent;
  final String? namaEvent;
  final String? deskripsi;
  final String? narasumber;
  final DateTime? tanggal;
  final String? waktu;
  final String? lokasi;
  final String? persyaratan;
  final int? maxPartisipan;
  final String? idPembuat;
  final String? status;
  final String? fotoEvent;
  final double? latitude;
  final double? longitude;
  final DateTime? createdAt;

  final int jumlahPendaftar;

  const EventModel({
    required this.idEvent,
    this.namaEvent,
    this.deskripsi,
    this.narasumber,
    this.tanggal,
    this.waktu,
    this.lokasi,
    this.persyaratan,
    this.maxPartisipan,
    this.idPembuat,
    this.status,
    this.fotoEvent,
    this.latitude,
    this.longitude,
    this.createdAt,
    this.jumlahPendaftar = 0,
  });

  factory EventModel.fromMap(
    Map<String, dynamic> map, {
    int jumlahPendaftar = 0,
  }) {
    return EventModel(
      idEvent: map['id_event'] ?? '',
      namaEvent: map['nama_event'],
      deskripsi: map['deskripsi'],
      narasumber: map['narasumber'],

      tanggal: map['tanggal'] != null
          ? DateTime.tryParse(map['tanggal'].toString())
          : null,

      waktu: map['waktu']?.toString(),

      lokasi: map['lokasi'],
      persyaratan: map['persyaratan'],

      maxPartisipan: map['max_partisipan'] is int
          ? map['max_partisipan']
          : int.tryParse(map['max_partisipan']?.toString() ?? ''),

      idPembuat: map['id_pembuat'],
      status: map['status'],
      fotoEvent: map['foto_event'],

      latitude: (map['latitude'] as num?)?.toDouble(),
      longitude: (map['longitude'] as num?)?.toDouble(),

      createdAt: map['created_at'] != null
          ? DateTime.tryParse(map['created_at'])
          : null,

      jumlahPendaftar: jumlahPendaftar,
    );
  }


  String get kuotaText => '$jumlahPendaftar/${maxPartisipan ?? '∞'}';

  String get tanggalFormatted {
    if (tanggal == null) return '-';

    const months = [
      '', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
      'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des',
    ];

    return '${tanggal!.day} ${months[tanggal!.month]} ${tanggal!.year}';
  }

  String get waktuFormatted {
    if (waktu == null) return '';

    return waktu!.length >= 5 ? waktu!.substring(0, 5) : waktu!;
  }
}