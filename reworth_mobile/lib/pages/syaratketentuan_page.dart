import 'package:flutter/material.dart';
import '../utils/app_colors.dart';
import '../utils/app_text_styles.dart';
import '../utils/app_constants.dart';

class SyaratKetentuanPage extends StatelessWidget {
  const SyaratKetentuanPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.white,
      appBar: AppBar(
        title: const Text("Syarat dan Ketentuan"),
        backgroundColor: AppColors.primary,
        elevation: 0,
        centerTitle: false,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new, color: AppColors.black),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(AppConstants.paddingL),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: AppConstants.paddingS),
            
            // 1. Ketentuan Umum
            _buildSection(
              number: '1',
              title: 'Ketentuan Umum',
              points: [
                'Dengan menggunakan aplikasi ReWorth, Anda menyetujui seluruh syarat dan ketentuan yang berlaku.',
                'Aplikasi ini dikelola oleh PT ReWorth Indonesia.',
                'Pengguna wajib mematuhi semua peraturan yang telah ditetapkan.',
                'Pelanggaran akan dikenakan sanksi sesuai peraturan yang berlaku.',
              ],
            ),
            const SizedBox(height: AppConstants.paddingL),
            
            // 2. Pendaftaran Akun
            _buildSection(
              number: '2',
              title: 'Pendaftaran Akun',
              points: [
                'Pengguna wajib mendaftarkan akun dengan data yang benar dan akurat.',
                'Setiap pengguna hanya diperbolehkan memiliki satu akun.',
                'Pengguna bertanggung jawab penuh atas keamanan akun dan kata sandi.',
                'Pihak pengelola berhak menonaktifkan akun jika ditemukan data tidak valid.',
              ],
            ),
            const SizedBox(height: AppConstants.paddingL),
            
            // 3. Poin dan Reward
            _buildSection(
              number: '3',
              title: 'Poin dan Reward',
              points: [
                'Setiap transaksi akan mendapatkan poin sesuai ketentuan yang berlaku.',
                'Poin dapat ditukarkan dengan berbagai reward yang tersedia.',
                'Reward yang sudah ditukarkan tidak dapat dikembalikan menjadi poin.',
                'Poin memiliki masa berlaku 12 bulan dan akan hangus jika tidak digunakan.',
              ],
            ),
            const SizedBox(height: AppConstants.paddingL),
            
            // 4. Privasi dan Keamanan
            _buildSection(
              number: '4',
              title: 'Privasi dan Keamanan',
              points: [
                'Data pribadi pengguna akan dijaga kerahasiaannya.',
                'Data tidak akan disebarluaskan kepada pihak ketiga tanpa izin.',
                'Pengguna disarankan tidak membagikan informasi akun kepada siapapun.',
                'Seluruh data transaksi tersimpan aman dalam sistem database.',
              ],
            ),
            const SizedBox(height: AppConstants.paddingL),
            
            // 5. Perubahan Ketentuan
            _buildSection(
              number: '5',
              title: 'Perubahan Ketentuan',
              points: [
                'Pihak pengelola berhak mengubah syarat dan ketentuan sewaktu-waktu.',
                'Perubahan akan diumumkan melalui aplikasi atau website resmi.',
                'Pengguna diharapkan membaca pembaruan secara berkala.',
                'Dengan tetap menggunakan aplikasi, Anda dianggap menyetujui perubahan.',
              ],
            ),
            const SizedBox(height: AppConstants.paddingL),
            
            // 6. Pembatalan Akun
            _buildSection(
              number: '6',
              title: 'Pembatalan Akun',
              points: [
                'Pengguna dapat membatalkan akun dengan menghubungi customer service.',
                'Poin yang belum ditukarkan akan hangus setelah akun dibatalkan.',
                'Data pengguna akan dihapus dalam waktu maksimal 30 hari.',
                'Pembatalan tidak dapat dilakukan jika masih ada transaksi belum selesai.',
              ],
            ),
            const SizedBox(height: AppConstants.paddingL),
            
            // 7. Ketentuan Lainnya
            _buildSection(
              number: '7',
              title: 'Ketentuan Lainnya',
              points: [
                'Pengguna dilarang melakukan tindakan yang merugikan pengguna lain.',
                'Kecurangan akan dikenakan sanksi berupa pemblokiran akun permanen.',
                'Keputusan pihak pengelola bersifat mutlak dan tidak dapat diganggu gugat.',
                'Informasi lebih lanjut hubungi layanan pelanggan ReWorth.',
              ],
            ),
            const SizedBox(height: AppConstants.paddingXL),
            
            Center(
              child: Text(
                'Terima kasih telah menggunakan ReWorth',
                style: AppTextStyles.caption.copyWith(
                  color: AppColors.textSecondary,
                  fontStyle: FontStyle.italic,
                ),
              ),
            ),
            const SizedBox(height: AppConstants.paddingL),
          ],
        ),
      ),
    );
  }

  Widget _buildSection({
    required String number,
    required String title,
    required List<String> points,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Container(
              width: 28,
              height: 28,
              decoration: BoxDecoration(
                color: AppColors.secondary,
                borderRadius: BorderRadius.circular(6),
              ),
              child: Center(
                child: Text(
                  number,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                  ),
                ),
              ),
            ),
            const SizedBox(width: 12),
            Text(
              title,
              style: AppTextStyles.title.copyWith(
                fontSize: 16,
                fontWeight: FontWeight.w600,
                color: AppColors.textPrimary,
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        ...points.map((point) => _buildBulletPoint(point)),
      ],
    );
  }

  Widget _buildBulletPoint(String text) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '•',
            style: TextStyle(
              fontSize: 14,
              color: AppColors.secondary,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              text,
              style: AppTextStyles.body.copyWith(
                fontSize: 13,
                height: 1.5,
                color: AppColors.textSecondary,
              ),
            ),
          ),
        ],
      ),
    );
  }
}