import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'pesanan_page.dart';
import '../utils/app_colors.dart';
import '../utils/app_constants.dart';
import '../utils/app_text_styles.dart';
import '../utils/app_image_helper.dart';


class _DetailProduk {
  final String idProduk;
  final String nama;
  final int harga;
  final int stok;
  final String deskripsi;
  final String namaPenjual;
  final String fotoPenjual;
  final double rating;
  final int jumlahTerjual;
  final List<String> fotoList; 

  const _DetailProduk({
    required this.idProduk,
    required this.nama,
    required this.harga,
    required this.stok,
    required this.deskripsi,
    required this.namaPenjual,
    required this.fotoPenjual,
    required this.rating,
    required this.jumlahTerjual,
    required this.fotoList,
  });
}

class BelanjaDetailPage extends StatefulWidget {
  final String idProduk;

  const BelanjaDetailPage({
    super.key,
    required this.idProduk,
  });

  @override
  State<BelanjaDetailPage> createState() => _BelanjaDetailPageState();
}

class _BelanjaDetailPageState extends State<BelanjaDetailPage> {
  final _supabase = Supabase.instance.client;
  final _pageController = PageController();

  _DetailProduk? _produk;
  bool _isLoading = true;
  bool _isBuying = false;

  int _currentPage = 0;
  int _jumlah = 1;

  @override
  void initState() {
    super.initState();
    _loadDetail();
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  Future<void> _loadDetail() async {
    try {
      final response = await _supabase.from('produk').select('''
        id_produk,
        nama_produk,
        harga,
        stok,
        deskripsi_produk,

        penjual (
          nama_penjual,
          foto_profil
        ),

        foto_produk (
          path_foto
        ),

        pesanan (
          status
        )
      ''').eq('id_produk', widget.idProduk).single();

      final fotoList = (response['foto_produk'] as List?)
              ?.map((f) => f['path_foto'] as String? ?? '')
              .where((p) => p.isNotEmpty)
              .toList() ??
          [];

      final penjualData = response['penjual'] as Map<String, dynamic>?;
      final namaPenjual = penjualData?['nama_penjual'] as String? ?? '';
      final fotoPenjual = penjualData?['foto_profil'] as String? ?? '';

      final pesananList = response['pesanan'] as List? ?? [];
      final jumlahTerjual = pesananList
          .where((p) =>
              (p['status'] ?? '').toString().toLowerCase() == 'selesai')
          .length;

      final rating = 4.5 + ((widget.idProduk.length % 5) * 0.1);

      final hargaRaw = response['harga'];
      final harga = hargaRaw is int
          ? hargaRaw
          : int.tryParse(hargaRaw?.toString() ?? '0') ?? 0;

      setState(() {
        _produk = _DetailProduk(
          idProduk: response['id_produk'] as String? ?? '',
          nama: response['nama_produk'] as String? ?? '',
          harga: harga,
          stok: response['stok'] as int? ?? 0,
          // nama kolom di DB adalah deskripsi_produk, bukan deskripsi
          deskripsi: response['deskripsi_produk'] as String? ?? '',
          namaPenjual: namaPenjual,
          fotoPenjual: fotoPenjual,
          rating: rating,
          jumlahTerjual: jumlahTerjual,
          fotoList: fotoList,
        );
        _isLoading = false;
      });
    } catch (e, stack) {
      debugPrint('ERROR LOAD DETAIL: $e');
      debugPrint('STACK: $stack');
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _onBeli() async {
    if (_produk == null) return;

    final userId = _supabase.auth.currentUser?.id;
    if (userId == null) {
      _showSnackBar('Silakan login terlebih dahulu', isError: true);
      return;
    }

    if (_jumlah > _produk!.stok) {
      _showSnackBar('Stok tidak mencukupi', isError: true);
      return;
    }

    setState(() => _isBuying = true);

    try {
      await _supabase.from('pesanan').insert({
        'id_produk': _produk!.idProduk,
        'id_pengguna': userId,
        'jumlah': _jumlah,
        'total_harga': _produk!.harga * _jumlah,
        'status': 'menunggu',
        'created_at': DateTime.now().toIso8601String(),
      });

      if (mounted) {
        _showSnackBar('Pesanan berhasil dibuat!');
        setState(() => _isBuying = false);
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isBuying = false);
        _showSnackBar('Gagal membuat pesanan: $e', isError: true);
      }
    }
  }

  void _showSnackBar(String msg, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(msg),
        backgroundColor: isError ? Colors.redAccent : AppColors.secondary,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(10),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(
        backgroundColor: AppColors.white,
        body: Center(
          child: CircularProgressIndicator(color: AppColors.secondary),
        ),
      );
    }

    if (_produk == null) {
      return Scaffold(
        backgroundColor: AppColors.white,
        appBar: AppBar(
          backgroundColor: AppColors.white,
          elevation: 0,
          leading: _backButton(),
        ),
        body: Center(
          child: Text('Produk tidak ditemukan', style: AppTextStyles.bodyMedium),
        ),
      );
    }

    final p = _produk!;

    return Scaffold(
      backgroundColor: AppColors.white,
      body: Stack(
        children: [
          CustomScrollView(
            slivers: [
              // Hero: foto carousel
              SliverToBoxAdapter(child: _buildCarousel(p)),

              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(
                    AppConstants.paddingL,
                    AppConstants.paddingL,
                    AppConstants.paddingL,
                    0,
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [

                      _buildNamaStok(p),
                      const SizedBox(height: AppConstants.paddingM),

                      _buildCardPenjual(p),
                      const SizedBox(height: AppConstants.paddingL),

                      _buildDeskripsi(p),
                      const SizedBox(height: AppConstants.paddingL),

                      if (p.fotoList.length > 1) ...[
                        _buildFotoGrid(p),
                        const SizedBox(height: AppConstants.paddingL),
                      ],

                      _buildCardHargaStok(p),
                      const SizedBox(height: AppConstants.paddingL),

                      _buildQuantityPicker(p),

                      const SizedBox(height: 100),
                    ],
                  ),
                ),
              ),
            ],
          ),

          SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(AppConstants.paddingM),
              child: _backButton(),
            ),
          ),

          Positioned(
            left: 0,
            right: 0,
            bottom: 0,
            child: _buildBeliButton(p),
          ),
        ],
      ),
    );
  }

  Widget _buildCarousel(_DetailProduk p) {
    final fotos = p.fotoList.isNotEmpty ? p.fotoList : [''];

    // +24 agar badge harga bisa menggantung di antara foto & area putih
    return SizedBox(
      height: 300 + 24,
      child: Stack(
        clipBehavior: Clip.none,
        children: [

          ClipRRect(
            borderRadius: const BorderRadius.only(
              bottomLeft: Radius.circular(32),
              bottomRight: Radius.circular(32),
            ),
            child: SizedBox(
              height: 300,
              child: PageView.builder(
                controller: _pageController,
                itemCount: fotos.length,
                onPageChanged: (i) => setState(() => _currentPage = i),
                itemBuilder: (context, i) {
                  final url = AppImageHelper.fotoProduk(fotos[i]);
                  return url.isEmpty
                      ? _placeholderImage()
                      : Image.network(
                          url,
                          width: double.infinity,
                          fit: BoxFit.cover,
                          loadingBuilder: (ctx, child, progress) {
                            if (progress == null) return child;
                            return Container(
                              color: const Color(0xFFF0F0F0),
                              child: const Center(
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: AppColors.secondary,
                                ),
                              ),
                            );
                          },
                          errorBuilder: (_, __, ___) => _placeholderImage(),
                        );
                },
              ),
            ),
          ),

          Positioned(
            bottom: 24 + 16,
            left: 0,
            right: 0,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: List.generate(fotos.length, (i) {
                final active = i == _currentPage;
                return AnimatedContainer(
                  duration: const Duration(milliseconds: 250),
                  margin: const EdgeInsets.symmetric(horizontal: 3),
                  width: active ? 24 : 8,
                  height: 8,
                  decoration: BoxDecoration(
                    color: active ? AppColors.secondary : Colors.white,
                    borderRadius: BorderRadius.circular(4),
                  ),
                );
              }),
            ),
          ),

          Positioned(
            bottom: 0,
            right: AppConstants.paddingL,
            child: Container(
              padding: const EdgeInsets.symmetric(
                horizontal: AppConstants.paddingM,
                vertical: 10,
              ),
              decoration: BoxDecoration(
                color: AppColors.secondary,
                borderRadius: BorderRadius.circular(AppConstants.radiusXL),
                boxShadow: [
                  BoxShadow(
                    color: AppColors.secondary.withOpacity(0.35),
                    blurRadius: 8,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: Text(
                _rupiah(p.harga),
                style: AppTextStyles.label.copyWith(
                  color: AppColors.white,
                  fontSize: 15,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildNamaStok(_DetailProduk p) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Text(
            p.nama,
            style: AppTextStyles.heading1.copyWith(fontSize: 22),
          ),
        ),
        const SizedBox(width: AppConstants.paddingS),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          decoration: BoxDecoration(
            border: Border.all(color: AppColors.inputBorder),
            borderRadius: BorderRadius.circular(AppConstants.radiusXL),
          ),
          child: Text(
            '${p.stok} stok',
            style: AppTextStyles.caption.copyWith(
              fontWeight: FontWeight.w600,
              color: AppColors.textSecondary,
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildCardPenjual(_DetailProduk p) {
  return Container(
    padding: const EdgeInsets.symmetric(
      horizontal: AppConstants.paddingM,
      vertical: AppConstants.paddingM,
    ),
    decoration: BoxDecoration(
      color: AppColors.white,
      borderRadius: BorderRadius.circular(AppConstants.radiusL),
      border: Border.all(color: AppColors.inputBorder),
    ),
    child: Row(
      children: [
        // Avatar penjual
        ClipOval(
          child: Image.network(
            AppImageHelper.fotoPenjual(p.fotoPenjual),
            width: 48,
            height: 48,
            fit: BoxFit.cover,
            errorBuilder: (_, __, ___) => Container(
              width: 48,
              height: 48,
              color: AppColors.primary.withOpacity(0.2),
              child: const Icon(
                Icons.store_outlined,
                color: AppColors.secondary,
                size: 24,
              ),
            ),
          ),
        ),
        const SizedBox(width: AppConstants.paddingM),

        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                p.namaPenjual,
                style: AppTextStyles.title.copyWith(fontSize: 15),
              ),
              const SizedBox(height: 4),
              Row(
                children: [
                  const Icon(
                    Icons.star_rounded,
                    size: 14,
                    color: AppColors.accent,
                  ),
                  const SizedBox(width: 3),
                  Text(
                    p.rating.toStringAsFixed(1),
                    style: AppTextStyles.small.copyWith(
                      fontWeight: FontWeight.w600,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  const SizedBox(width: 6),
                  Text(
                    '· ${_formatTerjual(p.jumlahTerjual)} terjual',
                    style: AppTextStyles.small.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

  Widget _buildDeskripsi(_DetailProduk p) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Deskripsi Produk', style: AppTextStyles.label),
        const SizedBox(height: AppConstants.paddingS),
        Text(
          p.deskripsi.isNotEmpty ? p.deskripsi : 'Tidak ada deskripsi.',
          style: AppTextStyles.bodyMedium.copyWith(
            color: AppColors.textSecondary,
            height: 1.6,
          ),
        ),
      ],
    );
  }


  Widget _buildFotoGrid(_DetailProduk p) {

    final tampil = p.fotoList.take(4).toList();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Foto Produk', style: AppTextStyles.label),
        const SizedBox(height: AppConstants.paddingS),
        SizedBox(
          height: 72,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: tampil.length,
            separatorBuilder: (_, __) =>
                const SizedBox(width: AppConstants.paddingS),
            itemBuilder: (context, i) {
              final url = AppImageHelper.fotoProduk(tampil[i]);
              final isActive = i == _currentPage;

              return GestureDetector(
                onTap: () {
                  _pageController.animateToPage(
                    i,
                    duration: const Duration(milliseconds: 300),
                    curve: Curves.easeInOut,
                  );
                  setState(() => _currentPage = i);
                },
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  width: 72,
                  height: 72,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(AppConstants.radiusM),
                    border: Border.all(
                      color: isActive
                          ? AppColors.secondary
                          : AppColors.inputBorder,
                      width: isActive ? 2.5 : 1,
                    ),
                  ),
                  child: ClipRRect(
                    borderRadius:
                        BorderRadius.circular(AppConstants.radiusM - 2),
                    child: Image.network(
                      url,
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => Container(
                        color: const Color(0xFFF0F0F0),
                        child: const Icon(
                          Icons.image_not_supported_outlined,
                          color: AppColors.textHint,
                          size: 24,
                        ),
                      ),
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }


  Widget _buildCardHargaStok(_DetailProduk p) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(
        horizontal: AppConstants.paddingL,
        vertical: AppConstants.paddingM,
      ),
      decoration: BoxDecoration(
        color: const Color(0xFFEFF7DC), // hijau sangat muda
        borderRadius: BorderRadius.circular(AppConstants.radiusL),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Harga',
                  style: AppTextStyles.small.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  _rupiah(p.harga),
                  style: AppTextStyles.heading1.copyWith(
                    fontSize: 20,
                    color: AppColors.textPrimary,
                  ),
                ),
              ],
            ),
          ),

          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                'Stok',
                style: AppTextStyles.small.copyWith(
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                '${p.stok} pcs',
                style: AppTextStyles.heading1.copyWith(
                  fontSize: 20,
                  color: AppColors.textPrimary,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }


  Widget _buildQuantityPicker(_DetailProduk p) {
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppConstants.paddingL,
        vertical: AppConstants.paddingM,
      ),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppConstants.radiusL),
        border: Border.all(color: AppColors.inputBorder),
      ),
      child: Row(
        children: [
          Text('Jumlah', style: AppTextStyles.label),
          const Spacer(),

          _qtyButton(
            icon: Icons.remove,
            onTap: () {
              if (_jumlah > 1) setState(() => _jumlah--);
            },
          ),

          Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: AppConstants.paddingM,
            ),
            child: Text(
              '$_jumlah',
              style: AppTextStyles.title.copyWith(fontSize: 16),
            ),
          ),

          _qtyButton(
            icon: Icons.add,
            onTap: () {
              if (_jumlah < p.stok) setState(() => _jumlah++);
            },
            filled: true,
          ),
        ],
      ),
    );
  }

  Widget _qtyButton({
    required IconData icon,
    required VoidCallback onTap,
    bool filled = false,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 36,
        height: 36,
        decoration: BoxDecoration(
          color: filled ? AppColors.secondary : AppColors.white,
          borderRadius: BorderRadius.circular(AppConstants.radiusM),
          border: Border.all(
            color: filled ? AppColors.secondary : AppColors.inputBorder,
          ),
        ),
        child: Icon(
          icon,
          size: 18,
          color: filled ? AppColors.white : AppColors.textPrimary,
        ),
      ),
    );
  }

  Widget _buildBeliButton(_DetailProduk p) {
    final isEmpty = p.stok == 0;
    return Container(
      padding: EdgeInsets.fromLTRB(
        AppConstants.paddingL,
        AppConstants.paddingM,
        AppConstants.paddingL,
        AppConstants.paddingL +
            MediaQuery.of(context).padding.bottom,
      ),
      decoration: const BoxDecoration(
        color: AppColors.white,
        boxShadow: [
          BoxShadow(
            color: Color(0x14000000),
            blurRadius: 12,
            offset: Offset(0, -4),
          ),
        ],
      ),
      child: SizedBox(
        width: double.infinity,
        height: 52,
        child: ElevatedButton(
          onPressed: (isEmpty || _isBuying) ? null : _onBeli,
          style: ElevatedButton.styleFrom(
            backgroundColor:
                isEmpty ? AppColors.inputBorder : AppColors.secondary,
            elevation: 0,
            shape: RoundedRectangleBorder(
              borderRadius:
                  BorderRadius.circular(AppConstants.radiusXL),
            ),
          ),
          child: _isBuying
              ? const SizedBox(
                  width: 22,
                  height: 22,
                  child: CircularProgressIndicator(
                    color: AppColors.white,
                    strokeWidth: 2.5,
                  ),
                )
              : Text(
                  isEmpty ? 'Stok Habis' : 'Beli Sekarang',
                  style: AppTextStyles.buttonLabel,
                ),
        ),
      ),
    );
  }


  Widget _backButton() {
    return GestureDetector(
      onTap: () => Navigator.pop(context),
      child: Container(
        width: 40,
        height: 40,
        decoration: BoxDecoration(
          color: AppColors.white.withOpacity(0.9),
          shape: BoxShape.circle,
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.12),
              blurRadius: 8,
            ),
          ],
        ),
        child: const Icon(
          Icons.arrow_back_ios_new_rounded,
          size: 18,
          color: AppColors.textPrimary,
        ),
      ),
    );
  }

  Widget _placeholderImage() {
    return Container(
      color: const Color(0xFFF0F0F0),
      child: const Center(
        child: Icon(
          Icons.image_not_supported_outlined,
          color: AppColors.textHint,
          size: 48,
        ),
      ),
    );
  }
}

String _rupiah(int angka) {
  final s = angka.toString();
  final buf = StringBuffer('Rp');
  final start = s.length % 3;
  if (start > 0) buf.write(s.substring(0, start));
  for (int i = start; i < s.length; i += 3) {
    if (i > 0 || start > 0) buf.write('.');
    buf.write(s.substring(i, i + 3));
  }
  return buf.toString();
}

String _formatTerjual(int jumlah) {
  if (jumlah >= 1000) {
    final k = (jumlah / 1000).toStringAsFixed(1);
    return '${k}K';
  }
  return '$jumlah';
}