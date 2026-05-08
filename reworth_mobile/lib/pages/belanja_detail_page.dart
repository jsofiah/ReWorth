import 'package:flutter/material.dart';

class BelanjaDetailPage extends StatelessWidget {
  final String idProduk;

  const BelanjaDetailPage({
    super.key,
    required this.idProduk,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Halaman Detail Belanja"),
      ),
      body: Center(
        child: Text("ID Produk: $idProduk"),
      ),
    );
  }
}