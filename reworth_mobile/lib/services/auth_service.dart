import 'package:supabase_flutter/supabase_flutter.dart';

class AuthService {
  static final _supabase = Supabase.instance.client;

  // REGISTER AUTO LOGIN
  static Future<void> register({
    required String nama,
    required String email,
    required String password,
    required String phone,
    required String alamat,
    required String idWilayah,
    required double latitude,
    required double longitude,
  }) async {
    try {
      print("=== START REGISTER PROCESS ===");
      
      // 1. REGISTER KE SUPABASE AUTH
      final res = await _supabase.auth.signUp(
        email: email,
        password: password,
        data: {
          'nama_lengkap': nama,
        },
      );

      final user = res.user;

      if (user == null) {
        throw Exception("Gagal membuat akun");
      }

      print("User created: ${user.id}");

      // 2. INSERT KE TABEL pengguna
      final insertData = {
        'id_pengguna': user.id,
        'nama_lengkap': nama,
        'email': email,
        'no_telepon': phone,
        'alamat_detail': alamat,
        'id_wilayah': idWilayah,
        'latitude': latitude,
        'longitude': longitude,
        'poin': 0,
        'saldo_tabungan': 0,
      };
      
      print("Insert data: $insertData");
      
      await _supabase.from('pengguna').insert(insertData);
      
      // 3. Verifikasi data sudah masuk
      final checkData = await _supabase
          .from('pengguna')
          .select()
          .eq('id_pengguna', user.id)
          .maybeSingle();
          
      print("Data after insert: $checkData");
      print("=== REGISTER SUCCESS ===");

    } on AuthException catch (e) {
      print("AuthException: ${e.message}");
      throw Exception(e.message);
    } catch (e) {
      print("General error: $e");
      throw Exception("Register gagal: $e");
    }
  }

  // LOGIN
  static Future<void> login({
    required String email,
    required String password,
  }) async {
    try {
      final res = await _supabase.auth.signInWithPassword(
        email: email,
        password: password,
      );

      if (res.user == null) {
        throw Exception("Email atau password salah");
      }

      print("Login success: ${res.user!.id}");
      
    } on AuthException catch (e) {
      print("AuthException: ${e.message}");
      throw Exception(e.message);
    } catch (e) {
      print("Login error: $e");
      throw Exception("Login gagal: $e");
    }
  }

  // LOGOUT
  static Future<void> logout() async {
    try {
      await _supabase.auth.signOut();
      print("Logout success");
    } catch (e) {
      print("Logout error: $e");
      throw Exception("Logout gagal: $e");
    }
  }

  // GET CURRENT USER
  static User? getCurrentUser() {
    return _supabase.auth.currentUser;
  }

  // CHECK LOGIN STATUS
  static bool isLoggedIn() {
    return _supabase.auth.currentSession != null;
  }

  // GET WILAYAH
  static Future<List<Map<String, dynamic>>> getWilayah() async {
    try {
      final data = await _supabase
          .from('wilayah')
          .select('id_wilayah, kecamatan, kelurahan, rw');

      return List<Map<String, dynamic>>.from(data);

    } catch (e) {
      print("Error getWilayah: $e");
      throw Exception("Gagal ambil wilayah: $e");
    }
  }
  
  // GET CURRENT USER DATA
  static Future<Map<String, dynamic>?> getCurrentUserData() async {
    try {
      final user = _supabase.auth.currentUser;
      if (user == null) return null;
      
      final data = await _supabase
          .from('pengguna')
          .select()
          .eq('id_pengguna', user.id)
          .maybeSingle();
          
      return data;
    } catch (e) {
      print("Error getting user data: $e");
      return null;
    }
  }

  // GET UNREAD NOTIFICATION COUNT
  static Future<int> getUnreadNotificationCount() async {
    try {
      final user = _supabase.auth.currentUser;
      if (user == null) return 0;

      // Mengambil semua data dan menghitung length
      final response = await _supabase
          .from('notifikasi')
          .select()
          .eq('id_pengguna', user.id)
          .eq('is_read', false);

      final unreadCount = response.length;
      return unreadCount;
      
    } catch (e) {
      print("Error getting unread count: $e");
      return 0;
    }
  }

  // ================= MARK NOTIFICATION AS READ
  static Future<void> markNotificationAsRead(String notificationId) async {
    try {
      await _supabase
          .from('notifikasi')
          .update({'is_read': true})
          .eq('id', notificationId);
          
      print("Notification marked as read: $notificationId");
    } catch (e) {
      print("Error marking notification as read: $e");
      throw Exception("Gagal update notifikasi: $e");
    }
  }

  // MARK ALL NOTIFICATIONS AS READ =================
  static Future<void> markAllNotificationsAsRead() async {
    try {
      final user = _supabase.auth.currentUser;
      if (user == null) return;

      await _supabase
          .from('notifikasi')
          .update({'is_read': true})
          .eq('id_pengguna', user.id)
          .eq('is_read', false);
          
      print("All notifications marked as read for user: ${user.id}");
    } catch (e) {
      print("Error marking all notifications as read: $e");
      throw Exception("Gagal update notifikasi: $e");
    }
  }

  // GET NOTIFICATIONS
  static Future<List<Map<String, dynamic>>> getNotifications({bool? isRead}) async {
    try {
      final user = _supabase.auth.currentUser;
      if (user == null) return [];

      var query = _supabase
          .from('notifikasi')
          .select()
          .eq('id_pengguna', user.id);
      
      // Filter isRead jika ada
      if (isRead != null) {
        query = query.eq('is_read', isRead);
      }
      
      // Order by created_at
      final data = await query.order('created_at', ascending: false);
      
      return List<Map<String, dynamic>>.from(data);
      
    } catch (e) {
      print("Error getting notifications: $e");
      throw Exception("Gagal ambil notifikasi: $e");
    }
  }

  //  STREAM UNREAD NOTIFICATION COUNT (REALTIME)
  static Stream<int> getUnreadNotificationCountStream() {
    final user = _supabase.auth.currentUser;
    if (user == null) return Stream.value(0);
    
    try {
      // Streaming semua notifikasi dan filter manual
      return _supabase
          .from('notifikasi')
          .stream(primaryKey: ['id'])
          .eq('id_pengguna', user.id)
          .map((event) {
            // Filter manual untuk is_read = false
            final unreadEvents = event.where((item) => item['is_read'] == false).toList();
            return unreadEvents.length;
          });
    } catch (e) {
      print("Error in notification stream: $e");
      return Stream.value(0);
    }
  }

  // UPDATE USER PROFILE
  static Future<void> updateUserProfile({
    String? namaLengkap,
    String? noTelepon,
    String? alamatDetail,
    String? fotoProfil,
  }) async {
    try {
      final user = _supabase.auth.currentUser;
      if (user == null) throw Exception("User tidak login");

      final updateData = <String, dynamic>{};
      if (namaLengkap != null) updateData['nama_lengkap'] = namaLengkap;
      if (noTelepon != null) updateData['no_telepon'] = noTelepon;
      if (alamatDetail != null) updateData['alamat_detail'] = alamatDetail;
      if (fotoProfil != null) updateData['foto_profil'] = fotoProfil;

      if (updateData.isNotEmpty) {
        await _supabase
            .from('pengguna')
            .update(updateData)
            .eq('id_pengguna', user.id);
            
        print("User profile updated: $updateData");
      }
    } catch (e) {
      print("Error updating user profile: $e");
      throw Exception("Gagal update profil: $e");
    }
  }

  // GET USER POINTS
  static Future<int> getUserPoints() async {
    try {
      final user = _supabase.auth.currentUser;
      if (user == null) return 0;
      
      final data = await _supabase
          .from('pengguna')
          .select('poin')
          .eq('id_pengguna', user.id)
          .maybeSingle();
          
      return data?['poin'] ?? 0;
    } catch (e) {
      print("Error getting user points: $e");
      return 0;
    }
  }

  // GET USER SALDO
  static Future<int> getUserSaldo() async {
    try {
      final user = _supabase.auth.currentUser;
      if (user == null) return 0;
      
      final data = await _supabase
          .from('pengguna')
          .select('saldo_tabungan')
          .eq('id_pengguna', user.id)
          .maybeSingle();
          
      return data?['saldo_tabungan'] ?? 0;
    } catch (e) {
      print("Error getting user saldo: $e");
      return 0;
    }
  }
}