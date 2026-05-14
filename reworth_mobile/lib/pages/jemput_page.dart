import 'package:flutter/material.dart';

class JemputPage extends StatelessWidget {
  const JemputPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Halaman Jemput"),
      ),
      body: const Center(
        child: Text("Ini halaman Jemput"),
      ),
    );
  }
}