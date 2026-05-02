class UserModel {
  final String idPengguna;
  final String? namaLengkap;
  final String? email;
  final String? password;
  final String? noTelepon;
  final String? alamatDetail;
  final String? fotoProfil;
  final int poin;
  final double saldoTabungan;
  final String idWilayah;
  final double? latitude;
  final double? longitude;
  final DateTime? createdAt;

  const UserModel({
    required this.idPengguna,
    this.namaLengkap,
    this.email,
    this.password,
    this.noTelepon,
    this.alamatDetail,
    this.fotoProfil,
    this.poin = 0,
    this.saldoTabungan = 0,
    required this.idWilayah,
    this.latitude,
    this.longitude,
    this.createdAt,
  });

  factory UserModel.fromMap(Map<String, dynamic> map) {
    return UserModel(
      idPengguna: map['id_pengguna'] ?? '',
      namaLengkap: map['nama_lengkap'],
      email: map['email'],
      password: map['password'],
      noTelepon: map['no_telepon'],
      alamatDetail: map['alamat_detail'],
      fotoProfil: map['foto_profil'],
      poin: (map['poin'] ?? 0) is int
          ? map['poin']
          : int.tryParse(map['poin'].toString()) ?? 0,
      saldoTabungan:
          double.tryParse(map['saldo_tabungan']?.toString() ?? '0') ?? 0,
      idWilayah: map['id_wilayah'] ?? '',
      latitude: (map['latitude'] as num?)?.toDouble(),
      longitude: (map['longitude'] as num?)?.toDouble(),
      createdAt: map['created_at'] != null
          ? DateTime.parse(map['created_at'])
          : null,
    );
  }

  Map<String, dynamic> toMap() {
    return {
      'id_pengguna': idPengguna,
      'nama_lengkap': namaLengkap,
      'email': email,
      'password': password,
      'no_telepon': noTelepon,
      'alamat_detail': alamatDetail,
      'foto_profil': fotoProfil,
      'poin': poin,
      'saldo_tabungan': saldoTabungan,
      'id_wilayah': idWilayah,
      'latitude': latitude,
      'longitude': longitude,
      'created_at': createdAt?.toIso8601String(),
    };
  }
}