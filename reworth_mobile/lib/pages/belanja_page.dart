import 'package:flutter/material.dart';

class BelanjaPage extends StatelessWidget {
  const BelanjaPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Halaman Belanja"),
      ),
      body: const Center(
        child: Text("Ini halaman Belanja"),
      ),
    );
  }
}