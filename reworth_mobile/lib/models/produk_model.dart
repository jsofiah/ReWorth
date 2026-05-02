class ProdukModel {
  final String idProduk;
  final String? namaProduk;
  final String? fotoProduk;
  final String? deskripsiProduk;
  final int? stok;
  final double harga;
  final String? idPenjual;
  final DateTime? createdAt;

  // join ke tabel penjual
  final String? namaPenjual;
  final String? fotoProfil;
  final double rating;

  const ProdukModel({
    required this.idProduk,
    this.namaProduk,
    this.deskripsiProduk,
    this.stok,
    this.fotoProduk,
    required this.harga,
    this.idPenjual,
    this.createdAt,
    this.namaPenjual,
    this.fotoProfil,
    this.rating = 0,
  });

  factory ProdukModel.fromMap(Map<String, dynamic> map) {
    final penjual = map['penjual'];
    final fotoList = map['foto_produk'] as List?;

    return ProdukModel(
      idProduk: map['id_produk'] ?? '',
      namaProduk: map['nama_produk'],
      deskripsiProduk: map['deskripsi_produk'],
      stok: map['stok'],
      harga: double.tryParse(map['harga']?.toString() ?? '0') ?? 0,
      idPenjual: map['id_penjual'],
      createdAt: map['created_at'] != null
          ? DateTime.tryParse(map['created_at'])
          : null,

      // ambil foto pertama
      fotoProduk: fotoList != null && fotoList.isNotEmpty
          ? fotoList[0]['path_foto']
          : null,

      // JOIN penjual
      namaPenjual: penjual?['nama_penjual'],
      fotoProfil: penjual?['foto_profil'],
      rating: double.tryParse(penjual?['rating']?.toString() ?? '0') ?? 0,
    );
  }


  String get hargaFormatted {
    final formatted = harga.toStringAsFixed(0).replaceAllMapped(
      RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
      (m) => '${m[1]}.',
    );
    return 'Rp$formatted';
  }
}