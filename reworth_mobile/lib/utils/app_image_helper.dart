import 'package:supabase_flutter/supabase_flutter.dart';

class AppImageHelper {
  AppImageHelper._();

  static final _supabase = Supabase.instance.client;

  static String getPublicUrl(String bucket, String? path) {
    if (path == null || path.isEmpty) return '';
    if (path.startsWith('http')) return path;
    return _supabase.storage.from(bucket).getPublicUrl(path);
  }

  // Foto profil LAMA (bucket foto_profil)
  static String fotoProfil(String? path) =>
      getPublicUrl('foto_profil', path);

  // Foto profil dari folder pengguna di bucket media
  static String fotoPengguna(String? path) {
    if (path == null || path.isEmpty) return '';
    if (path.startsWith('http')) return path;
    
    // Jika path sudah mengandung 'pengguna/', langsung gunakan
    if (path.startsWith('pengguna/')) {
      return getPublicUrl('media', path);
    }
    
    // Jika hanya nama file saja
    return getPublicUrl('media', 'pengguna/$path');
  }

  static String fotoEvent(String? path) =>
      getPublicUrl('media', path);

  static String fotoProduk(String? path) =>
      getPublicUrl('media', path);

  /// Foto umum / custom bucket
  static String custom(String? path) =>
      getPublicUrl('media', path);
}