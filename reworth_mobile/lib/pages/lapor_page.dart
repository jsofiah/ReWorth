import 'package:flutter/material.dart';

class LaporPage extends StatelessWidget {
  const LaporPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Halaman Lapor"),
      ),
      body: const Center(
        child: Text("Ini halaman Lapor"),
      ),
    );
  }
}