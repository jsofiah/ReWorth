// Buat file baru: utils/image_helper.dart
import 'package:supabase_flutter/supabase_flutter.dart';

class ImageHelper {
  static String getFotoProfilUrl(String? fotoPath) {
    if (fotoPath == null || fotoPath.isEmpty) return '';
    
    // Jika sudah URL lengkap, langsung return
    if (fotoPath.startsWith('http')) return fotoPath;
    
    // Jika tidak, bangun URL lengkap dari Supabase
    final supabase = Supabase.instance.client;
    return supabase.storage
        .from('foto_profil')
        .getPublicUrl(fotoPath);
  }
}