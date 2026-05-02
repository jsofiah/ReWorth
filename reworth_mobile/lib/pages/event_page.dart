import 'package:flutter/material.dart';

class EventPage extends StatelessWidget {
  const EventPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Halaman Event"),
      ),
      body: const Center(
        child: Text("Ini halaman Event"),
      ),
    );
  }
}