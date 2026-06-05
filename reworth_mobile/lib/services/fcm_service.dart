import 'package:firebase_messaging/firebase_messaging.dart';

class FCMService {
  static Future<void> initialize() async {
    try {
      // Minta izin notifikasi
      await FirebaseMessaging.instance.requestPermission();

      // Subscribe topic broadcast
      await FirebaseMessaging.instance
          .subscribeToTopic('all_users');

      print("FCM initialized");
    } catch (e) {
      print("FCM Init Error: $e");
    }
  }
}