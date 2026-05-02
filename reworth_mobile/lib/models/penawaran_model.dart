class PenawaranModel {
  final String id;
  final String judul;
  final String subjudul;
  final String? keterangan;
  final String? imageUrl;
  final String warnaBg;

  const PenawaranModel({
    required this.id,
    required this.judul,
    required this.subjudul,
    this.keterangan,
    this.imageUrl,
    required this.warnaBg,
  });

  factory PenawaranModel.fromMap(Map<String, dynamic> map) {
    return PenawaranModel(
      id: map['id'] ?? '',
      judul: map['judul'] ?? '',
      subjudul: map['subjudul'] ?? '',
      keterangan: map['keterangan'],
      imageUrl: map['image_url'],
      warnaBg: map['warna_bg'] ?? '#BBDE2D',
    );
  }
}