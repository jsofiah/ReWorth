import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'belanja_detail_page.dart';
import '../utils/app_colors.dart';
import '../utils/app_constants.dart';
import '../utils/app_text_styles.dart';
import '../utils/app_image_helper.dart';

class _ProdukItem {
  final String idProduk;
  final String nama;
  final int harga;
  final String penjual;
  final String gambarProduk;  
  final String gambarPenjual; 
  final double rating;
  final int terjual;

  const _ProdukItem({
    required this.idProduk,
    required this.nama,
    required this.harga,
    required this.penjual,
    required this.gambarProduk,
    required this.gambarPenjual,
    required this.rating,
    required this.terjual,
  });
}

const int _pageSize = 10;

class BelanjaPage extends StatefulWidget {
  const BelanjaPage({super.key});

  @override
  State<BelanjaPage> createState() => _BelanjaPageState();
}

class _BelanjaPageState extends State<BelanjaPage> {
  final supabase = Supabase.instance.client;

  final _searchController = TextEditingController();
  final _scrollController = ScrollController();

  List<_ProdukItem> _semuaProduk = [];
  bool _isLoading = true;

  String _searchQuery = '';
  String _sortBy = 'rating';
  int _tampilCount = _pageSize;

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
    _loadProduk();
  }

  @override
  void dispose() {
    _searchController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _loadProduk() async {
    try {
      final today = DateTime.now().toIso8601String().substring(0, 10);
      
      final response = await supabase.from('produk').select('''
        nama_produk,
        harga,
        stok,
        id_produk,

        penjual (
          id_penjual,
          nama_penjual,
          foto_profil,
          langganan (
            status,
            tanggal_mulai,
            tanggal_selesai
          )
        ),

        foto_produk (
          path_foto
        ),

        pesanan (
          status
        )
      ''');

      final List<_ProdukItem> loaded = [];

      for (final item in response) {
        final penjualData = item['penjual'] as Map<String, dynamic>?;
        
        if (penjualData == null) continue;
        
        final langgananList = penjualData['langganan'] as List? ?? [];
        
        bool hasActiveSubscription = false;
        
        for (final langganan in langgananList) {
          final status = (langganan['status'] as String? ?? '').toLowerCase();
          final tanggalSelesai = langganan['tanggal_selesai'] as String? ?? '';
          
          if (status == 'aktif' && tanggalSelesai >= today) {
            hasActiveSubscription = true;
            break;
          }
        }
        
        if (!hasActiveSubscription) {
          continue;
        }

        final namaProduk = item['nama_produk'] as String? ?? '';

        final fotoList = item['foto_produk'] as List?;
        final String gambarProduk = (fotoList != null && fotoList.isNotEmpty)
            ? (fotoList.first['path_foto'] as String? ?? '')
            : '';

        final String namaPenjual = penjualData['nama_penjual'] as String? ?? '';
        final String fotoPenjual = penjualData['foto_profil'] as String? ?? '';
            
        final pesananList = item['pesanan'] as List? ?? [];
        final jumlahTerjual = pesananList.where((p) {
              return (p['status'] ?? '').toString().toLowerCase() == 'selesai';
            }).length;

        loaded.add(
          _ProdukItem(
            idProduk: item['id_produk'] as String? ?? '',
            nama: namaProduk,
            harga: (item['harga'] as num?)?.toInt() ?? 0,
            penjual: namaPenjual,
            gambarProduk: gambarProduk,
            gambarPenjual: fotoPenjual,
            rating: 4.5 + ((loaded.length % 5) * 0.1),
            terjual: jumlahTerjual,
          ),
        );
      }

      setState(() {
        _semuaProduk = loaded;
        _isLoading = false;
      });
    } catch (e) {
      debugPrint('ERROR LOAD PRODUK: $e');
      setState(() => _isLoading = false);
    }
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 200) {
      _loadMore();
    }
  }

  void _loadMore() {
    final total = _filteredProduk.length;
    if (_tampilCount < total) {
      setState(() {
        _tampilCount = (_tampilCount + _pageSize).clamp(0, total);
      });
    }
  }

  void _onSearchChanged(String val) {
    setState(() {
      _searchQuery = val;
      _tampilCount = _pageSize;
    });
  }

  List<_ProdukItem> get _filteredProduk {
    final result = _semuaProduk.where((p) {
      return _searchQuery.isEmpty ||
          p.nama.toLowerCase().contains(_searchQuery.toLowerCase()) ||
          p.penjual.toLowerCase().contains(_searchQuery.toLowerCase());
    }).toList();

    if (_sortBy == 'rating') {
      result.sort((a, b) => b.rating.compareTo(a.rating));
    } else if (_sortBy == 'harga_terendah') {
      result.sort((a, b) => a.harga.compareTo(b.harga));
    } else if (_sortBy == 'harga_tertinggi') {
      result.sort((a, b) => b.harga.compareTo(a.harga));
    }

    return result;
  }

  List<_ProdukItem> get _produkTampil =>
      _filteredProduk.take(_tampilCount).toList();

  bool get _adaLebih => _tampilCount < _filteredProduk.length;

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    return Scaffold(
      backgroundColor: AppColors.background,
      body: Stack(
        children: [
          // Background gradient
          Positioned.fill(
            child: Container(
              decoration: const BoxDecoration(
                image: DecorationImage(
                  image: AssetImage('assets/gradient.png'),
                  fit: BoxFit.cover,
                  alignment: Alignment.topCenter,
                ),
              ),
            ),
          ),

          SafeArea(
            child: Column(
              children: [
                _buildAppBar(),

                Expanded(
                  child: Container(
                    decoration: const BoxDecoration(
                      color: AppColors.white,
                      borderRadius: BorderRadius.only(
                        topLeft: Radius.circular(AppConstants.radiusXL),
                        topRight: Radius.circular(AppConstants.radiusXL),
                      ),
                    ),
                    child: Column(
                      children: [
                        Padding(
                          padding: const EdgeInsets.fromLTRB(
                            AppConstants.paddingL,
                            AppConstants.paddingL,
                            AppConstants.paddingL,
                            0,
                          ),
                          child: _buildSearchBar(),
                        ),
                        const SizedBox(height: 12),

                        Expanded(
                          child: CustomScrollView(
                            controller: _scrollController,
                            slivers: [
                              SliverToBoxAdapter(
                                child: Padding(
                                  padding: const EdgeInsets.all(
                                      AppConstants.paddingL),
                                  child: _buildBanner(),
                                ),
                              ),

                              _filteredProduk.isEmpty
                                  ? SliverToBoxAdapter(
                                      child: Center(
                                        child: Padding(
                                          padding: const EdgeInsets.all(
                                              AppConstants.paddingXL),
                                          child: Text(
                                            'Produk tidak ditemukan',
                                            style: AppTextStyles.bodyMedium,
                                          ),
                                        ),
                                      ),
                                    )
                                  : SliverPadding(
                                      padding: const EdgeInsets.symmetric(
                                          horizontal: AppConstants.paddingL),
                                      sliver: SliverGrid(
                                        gridDelegate:
                                            const SliverGridDelegateWithFixedCrossAxisCount(
                                          crossAxisCount: 2,
                                          mainAxisSpacing: 14,
                                          crossAxisSpacing: 14,
                                          childAspectRatio: 0.68,
                                        ),
                                        delegate: SliverChildBuilderDelegate(
                                          (context, i) =>
                                              _buildCard(_produkTampil[i]),
                                          childCount: _produkTampil.length,
                                        ),
                                      ),
                                    ),

                              SliverToBoxAdapter(
                                child: Padding(
                                  padding: const EdgeInsets.symmetric(
                                      vertical: 24),
                                  child: Center(
                                    child: _adaLebih
                                        ? const CircularProgressIndicator(
                                            strokeWidth: 2,
                                            color: AppColors.secondary,
                                          )
                                        : Text(
                                            'Semua produk sudah ditampilkan',
                                            style: AppTextStyles.small,
                                          ),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildAppBar() {
    return Padding(
      padding: const EdgeInsets.symmetric(
        horizontal: AppConstants.paddingL,
        vertical: AppConstants.paddingM,
      ),
      child: Row(
        children: [
          GestureDetector(
            onTap: () => Navigator.pop(context),
            child: Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: AppColors.white.withOpacity(0.55),
                shape: BoxShape.circle,
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.08),
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
          ),
          Expanded(
            child: Center(
              child: Text(
                'Marketplace',
                style: AppTextStyles.namafitur
              ),
            ),
          ),
          const SizedBox(width: 42),
        ],
      ),
    );
  }

  Widget _buildSearchBar() {
    return Row(
      children: [
        // SEARCH
        Expanded(
          child: Container(
            height: AppConstants.inputHeight,

            decoration: BoxDecoration(
              color: AppColors.secondary,

              borderRadius: BorderRadius.circular(
                AppConstants.radiusM,
              ),

              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.06),
                  blurRadius: 10,
                  offset: const Offset(0, 4),
                ),
              ],
            ),

            child: TextField(
              controller: _searchController,
              onChanged: _onSearchChanged,

              cursorColor: AppColors.white,

              style: AppTextStyles.inputText.copyWith(
                color: AppColors.white,
                fontWeight: FontWeight.w500,
              ),

              decoration: InputDecoration(
                hintText: 'Cari produk...',

                hintStyle: AppTextStyles.hintText.copyWith(
                  color: Colors.white70,
                ),

                prefixIcon: const Icon(
                  Icons.search_rounded,
                  color: AppColors.white,
                  size: 22,
                ),

                border: InputBorder.none,
                enabledBorder: InputBorder.none,
                focusedBorder: InputBorder.none,
                disabledBorder: InputBorder.none,
                errorBorder: InputBorder.none,
                focusedErrorBorder: InputBorder.none,

                filled: false,
                fillColor: Colors.transparent,

                contentPadding: const EdgeInsets.symmetric(
                  vertical: 16,
                ),
              ),
            ),
          ),
        ),

        const SizedBox(width: AppConstants.paddingS),

        Container(
          width: AppConstants.inputHeight,
          height: AppConstants.inputHeight,

          decoration: BoxDecoration(
            color: AppColors.secondary,

            borderRadius: BorderRadius.circular(
              AppConstants.radiusM,
            ),

            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.06),
                blurRadius: 10,
                offset: const Offset(0, 4),
              ),
            ],
          ),

          child: PopupMenuButton<String>(
            tooltip: 'Filter',

            color: AppColors.white,
            elevation: 8,

            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(
                AppConstants.radiusM,
              ),
            ),

            icon: const Icon(
              Icons.tune_rounded,
              color: AppColors.white,
              size: 22,
            ),

            onSelected: (val) {
              setState(() {
                _sortBy = val;
              });
            },

            itemBuilder: (context) => [
              PopupMenuItem(
                value: 'rating',
                child: Text(
                  'Urut Rating',
                  style: AppTextStyles.namaFitur,
                ),
              ),

              PopupMenuItem(
                value: 'harga_terendah',
                child: Text(
                  'Harga Terendah',
                  style: AppTextStyles.namaFitur,
                ),
              ),

              PopupMenuItem(
                value: 'harga_tertinggi',
                child: Text(
                  'Harga Tertinggi',
                  style: AppTextStyles.namaFitur,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildBanner() {
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [AppColors.primary, AppColors.lightAccent],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(AppConstants.radiusL),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.1),
            blurRadius: 15,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(AppConstants.radiusL),
        child: Stack(
          children: [
            // Pola bulat-bulat dekoratif
            ..._buildDecorationCircles(),
            
            // Gambar dari assets (bisa diganti sesuai kebutuhan)
            Positioned(
              right: -20,
              bottom: 0,
              top: 0,
              child: Image.asset(
                'assets/banner_eco.png', // Ganti dengan nama file gambar Anda
                height: 200,
                width: 200,
                fit: BoxFit.contain,
                errorBuilder: (_, __, ___) => const SizedBox.shrink(), // Sembunyikan jika gambar tidak ada
              ),
            ),
            
            // Konten
            Padding(
              padding: const EdgeInsets.all(AppConstants.paddingL),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.45),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      'ECO CAMPAIGN',
                      style: AppTextStyles.small.copyWith(
                        color: AppColors.secondary,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 1,
                      ),
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    'Daur Ulang,\nDapat Reward!',
                    style: AppTextStyles.heading1.copyWith(
                      color: AppColors.textPrimary,
                      fontSize: 24,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Setiap pembelian\nmembantu mengurangi\nsampah dari TPA.',
                    style: AppTextStyles.bodyMedium.copyWith(
                      color: AppColors.textSecondary,
                      fontSize: 12,
                    ),
                  ),
                  const SizedBox(height: 16),
                  Divider(
                    color: AppColors.secondary.withOpacity(0.3),
                    thickness: 1,
                  ),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      _buildStatItem('${_semuaProduk.length}', 'Produk'),
                      const SizedBox(width: 24),
                      _buildStatItem(
                        '${_semuaProduk.map((e) => e.penjual).toSet().length}',
                        'Penjual',
                      ),
                      const SizedBox(width: 24),
                      _buildStatItem(
                        '${_semuaProduk.fold<int>(0, (a, b) => a + b.terjual)}+',
                        'Terjual',
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  List<Widget> _buildDecorationCircles() {
    return [
      // Circle 1 - besar di pojok kiri atas
      Positioned(
        top: -30,
        left: -30,
        child: Container(
          width: 100,
          height: 100,
          decoration: BoxDecoration(
            color: const Color.fromARGB(255, 139, 187, 28).withOpacity(0.5),
            shape: BoxShape.circle,
          ),
        ),
      ),
      
      // Circle 2 - sedang di pojok kanan bawah
      Positioned(
        bottom: -40,
        right: 0,
        child: Container(
          width: 100,
          height: 100,
          decoration: BoxDecoration(
            color: const Color.fromARGB(255, 139, 187, 28).withOpacity(0.4),
            shape: BoxShape.circle,
          ),
        ),
      ),
      
      // Circle 3 - kecil di tengah kanan
      Positioned(
        top: 40,
        right: 180,
        child: Container(
          width: 40,
          height: 40,
          decoration: BoxDecoration(
            color: const Color.fromARGB(255, 139, 187, 28).withOpacity(0.5),
            shape: BoxShape.circle,
          ),
        ),
      ),
      
      // Circle 4 - sangat kecil
      Positioned(
        bottom: 50,
        left: 60,
        child: Container(
          width: 20,
          height: 20,
          decoration: BoxDecoration(
            color: const Color.fromARGB(255, 139, 187, 28).withOpacity(0.3),
            shape: BoxShape.circle,
          ),
        ),
      ),
      
      // Circle 5 - untuk keseimbangan
      Positioned(
        top: 20,
        left: 240,
        child: Container(
          width: 25,
          height: 25,
          decoration: BoxDecoration(
            color: const Color.fromARGB(255, 139, 187, 28).withOpacity(0.2),
            shape: BoxShape.circle,
          ),
        ),
      ),
      
      // Circle 6 - besar transparan di tengah
      Positioned(
        top: 60,
        right: 50,
        child: Container(
          width: 150,
          height: 150,
          decoration: BoxDecoration(
            color: const Color.fromARGB(255, 139, 187, 28).withOpacity(0.3),
            shape: BoxShape.circle,
          ),
        ),
      ),
    ];
  }

  Widget _buildStatItem(String value, String label) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          value,
          style: AppTextStyles.label.copyWith(color: AppColors.secondary),
        ),
        Text(
          label,
          style: AppTextStyles.small.copyWith(color: AppColors.textSecondary),
        ),
      ],
    );
  }

  Widget _buildCard(_ProdukItem item) {
  return GestureDetector(
    onTap: () {
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => BelanjaDetailPage(
            idProduk: item.idProduk,
          ),
        ),
      );
    },

    child: Container(
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppConstants.radiusL),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),

      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            flex: 6,
            child: ClipRRect(
              borderRadius: BorderRadius.only(
                topLeft: Radius.circular(AppConstants.radiusL),
                topRight: Radius.circular(AppConstants.radiusL),
              ),

              child: Image.network(
                AppImageHelper.fotoProduk(item.gambarProduk),
                width: double.infinity,
                fit: BoxFit.cover,

                loadingBuilder: (context, child, progress) {
                  if (progress == null) return child;

                  return Container(
                    color: const Color(0xFFF5F5F5),

                    child: const Center(
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: AppColors.secondary,
                      ),
                    ),
                  );
                },

                errorBuilder: (_, __, ___) => Container(
                  color: const Color(0xFFF5F5F5),

                  child: const Center(
                    child: Icon(
                      Icons.image_not_supported_outlined,
                      color: AppColors.textHint,
                      size: 32,
                    ),
                  ),
                ),
              ),
            ),
          ),

          Expanded(
            flex: 4,

            child: Padding(
              padding: const EdgeInsets.all(10),

              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.spaceBetween,

                children: [
                  Text(
                    item.nama,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,

                    style: AppTextStyles.body.copyWith(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),

                  Text(
                    _rupiah(item.harga),
                    style: AppTextStyles.label.copyWith(fontSize: 13),
                  ),

                  Row(
                    children: [
                      ClipOval(
                        child: Image.network(
                          AppImageHelper.fotoPenjual(
                            item.gambarPenjual,
                          ),

                          width: 20,
                          height: 20,
                          fit: BoxFit.cover,

                          errorBuilder: (_, __, ___) => Container(
                            width: 20,
                            height: 20,

                            decoration: BoxDecoration(
                              color: AppColors.primary.withOpacity(0.3),
                              shape: BoxShape.circle,
                            ),

                            child: const Icon(
                              Icons.store,
                              size: 12,
                              color: AppColors.secondary,
                            ),
                          ),
                        ),
                      ),

                      const SizedBox(width: 4),

                      Expanded(
                        child: Text(
                          item.penjual,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: AppTextStyles.small,
                        ),
                      ),

                      const Icon(
                        Icons.star_rounded,
                        size: 14,
                        color: AppColors.accent,
                      ),

                      const SizedBox(width: 2),

                      Text(
                        item.rating.toStringAsFixed(1),

                        style: AppTextStyles.small.copyWith(
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    ),
  );
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
}

extension on String {
  bool operator >=(String other) {
    return this.compareTo(other) >= 0;
  }
}