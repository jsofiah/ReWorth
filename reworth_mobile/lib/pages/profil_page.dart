import 'package:flutter/material.dart';

class ProfilPage extends StatelessWidget {
  const ProfilPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Halaman Profil"),
      ),
      body: const Center(
        child: Text("Ini halaman Profil"),
      ),
    );
  }
}