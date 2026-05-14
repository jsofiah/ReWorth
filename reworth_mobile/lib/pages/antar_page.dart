import 'package:flutter/material.dart';

class AntarPage extends StatelessWidget {
  const AntarPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Halaman Antar"),
      ),
      body: const Center(
        child: Text("Ini halaman Antar"),
      ),
    );
  }
}