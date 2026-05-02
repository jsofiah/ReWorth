import 'package:flutter/material.dart';

class AktivitasPage extends StatelessWidget {
  const AktivitasPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Halaman Aktivitas"),
      ),
      body: const Center(
        child: Text("Ini halaman Aktivitas"),
      ),
    );
  }
}