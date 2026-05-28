<?php
    session_start();

    if (!isset($_SESSION['role'])) {
        header("Location: ../login.php");
        exit;
    }

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $limit = 10;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit;

    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'terbaru';

    $orderBy = ($sort == 'terlama') ? "created_at.asc" : "created_at.desc";

    try {
        $url = $supabaseUrl . "/rest/v1/setor_sampah?select=*,pengguna!id_pengguna(nama_lengkap,email,foto_profil,no_telepon),jadwal_ambil!id_jadwal(tanggal,waktu_mulai,waktu_selesai,kuota)&order=" . $orderBy . "&limit=$limit&offset=$offset";
        
        if (!empty($status)) {
            $url .= "&status=eq.$status";
        }
        
        if (!empty($dateFrom)) {
            $url .= "&created_at=gte.$dateFrom";
        }
        if (!empty($dateTo)) {
            $url .= "&created_at=lte.$dateTo";
        }
        
        $chData = curl_init();
        curl_setopt_array($chData, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey"
            ]
        ]);
        
        $response = curl_exec($chData);
        curl_close($chData);
        
        $setorList = [];
        if ($response) {
            $setorList = json_decode($response, true);
            if (!is_array($setorList)) {
                $setorList = [];
            }
        }
        
        $countUrl = $supabaseUrl . "/rest/v1/setor_sampah?select=id_setor";
        if (!empty($status)) {
            $countUrl .= "&status=eq.$status";
        }
        if (!empty($dateFrom)) {
            $countUrl .= "&created_at=gte.$dateFrom";
        }
        if (!empty($dateTo)) {
            $countUrl .= "&created_at=lte.$dateTo";
        }
        
        $chCount = curl_init();
        curl_setopt_array($chCount, [
            CURLOPT_URL => $countUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey"
            ]
        ]);
        
        $countResponse = curl_exec($chCount);
        curl_close($chCount);
        
        $total = 0;
        if ($countResponse) {
            $countData = json_decode($countResponse, true);
            $total = is_array($countData) ? count($countData) : 0;
        }
        
        $totalPages = ceil($total / $limit);
        $start_number = $total > 0 ? $offset + 1 : 0;
        $end_number = min($offset + $limit, $total);
        
    } catch (Exception $e) {
        $setorList = [];
        $total = 0;
        $totalPages = 0;
        $start_number = 0;
        $end_number = 0;
    }

    function getStatusBadge($status) {
        $status = strtolower($status);
        if ($status == 'selesai' || $status == 'completed') {
            return '<span class="status-badge status-selesai">Selesai</span>';
        } elseif ($status == 'diproses' || $status == 'processing') {
            return '<span class="status-badge status-berlangsung">Diproses</span>';
        } elseif ($status == 'dibatalkan' || $status == 'cancelled') {
            return '<span class="status-badge status-ditolak">Dibatalkan</span>';
        } elseif ($status == 'diverifikasi' || $status == 'verified') {
            return '<span class="status-badge status-verified">Diverifikasi</span>';
        } else {
            return '<span class="status-badge status-akan_datang">Menunggu Verifikasi</span>';
        }
    }

    function formatTanggalIndonesia($date) {
        if (empty($date)) return '-';
        
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        
        $timestamp = strtotime($date);
        $tanggal = date('d', $timestamp);
        $bulanNum = (int)date('m', $timestamp);
        $tahun = date('Y', $timestamp);
        $jam = date('H:i', $timestamp);
        
        return "$tanggal " . $bulan[$bulanNum] . " $tahun, $jam";
    }

    function formatRupiah($angka) {
        if (empty($angka)) return 'Rp 0';
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }

    function formatJadwal($jadwal) {
        if (empty($jadwal)) return '-';
        
        $tanggal = isset($jadwal['tanggal']) ? formatTanggalIndonesia($jadwal['tanggal']) : '-';
        $waktuMulai = isset($jadwal['waktu_mulai']) ? date('H:i', strtotime($jadwal['waktu_mulai'])) : '-';
        $waktuSelesai = isset($jadwal['waktu_selesai']) ? date('H:i', strtotime($jadwal['waktu_selesai'])) : '-';
        $kuota = isset($jadwal['kuota']) ? $jadwal['kuota'] : '-';
        
        $hariIndonesia = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu'
        ];
        
        $hari = '';
        if (!empty($jadwal['tanggal'])) {
            $dayName = date('l', strtotime($jadwal['tanggal']));
            $hari = $hariIndonesia[$dayName] ?? '';
        }
        
        return "<div class='jadwal-info'>
                    <strong>{$hari}</strong> <br>
                    <small>{$tanggal}</small> <br>
                    <small>{$waktuMulai} - {$waktuSelesai}</small> <br>
                    <small>Kuota: {$kuota}</small>
                </div>";
    }
?>

<div class="table-wrap">
    <div class="table-scroll-wrapper">
        <table class="responsive-table" id="dynamicTable">
            <colgroup>
                <col style="width: 60px;">
                <col style="width: 180px;">
                <col style="width: 200px;">
                <col style="width: 200px;">
                <col style="width: 120px;">
                <col style="width: 120px;">
                <col style="width: 150px;">
            </colgroup>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Penyetor</th>
                    <th>Alamat</th>
                    <th>Jadwal Ambil</th>
                    <th>Total Uang</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($setorList)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5" style="color: #6B8A7E;">
                            <i class="bi bi-recycle" style="font-size: 48px; display: block; margin-bottom: 12px; opacity: 0.5;"></i>
                            Belum ada data setor sampah
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $no = $offset + 1;
                    foreach ($setorList as $setor): 
                        $namaPenyetor = '-';
                        $emailPenyetor = '';
                        $teleponPenyetor = '';
                        if (isset($setor['pengguna']) && is_array($setor['pengguna'])) {
                            $namaPenyetor = $setor['pengguna']['nama_lengkap'] ?? '-';
                            $emailPenyetor = $setor['pengguna']['email'] ?? '';
                            $teleponPenyetor = $setor['pengguna']['no_telepon'] ?? '';
                        }
                        
                        $alamat = $setor['alamat'] ?? '-';
                        $jadwal = $setor['jadwal_ambil'] ?? null;
                        $totalUang = $setor['total_uang'] ?? 0;
                        $status = $setor['status'] ?? 'menunggu_verifikasi';
                        $idSetor = $setor['id_setor'] ?? '';
                        $createdAt = $setor['created_at'] ?? null;
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <div class="penyetor-info">
                                    <?= htmlspecialchars($namaPenyetor) ?>
                                </div>
                            </td>
                            <td>
                                <div class="alamat-info">
                                    <?= htmlspecialchars($alamat) ?>
                                </div>
                            </td>
                            <td><?= formatJadwal($jadwal) ?></td>
                            <td>
                                <div class="total-uang">
                                    <?= formatRupiah($totalUang) ?>
                                </div>
                            </td>
                            <td><?= getStatusBadge($status) ?></td>
                            <td>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn-aksi btn-lihat" onclick="lihatDetail('<?= $idSetor ?>', 'setor_sampah')">
                                        <i class="bi bi-file-earmark-text"></i> Detail
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($totalPages > 1): ?>
    <div class="table-footer">
        <div class="showing-text">
            Showing <b><?= $start_number ?></b> to <b><?= $end_number ?></b> of <b><?= $total ?></b> entries
        </div>
        <div class="pagination-custom">
            <?php if ($page > 1): ?>
                <a href="javascript:void(0)" onclick="changePage(<?= $page - 1 ?>)" class="page-btn page-btn-text">Prev</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="javascript:void(0)" onclick="changePage(<?= $i ?>)" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="javascript:void(0)" onclick="changePage(<?= $page + 1 ?>)" class="page-btn page-btn-text">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    function changePage(page) {
        if (typeof window.parent.changePage === 'function') {
            window.parent.changePage(page);
        } else {
            const url = new URL(window.location.href);
            url.searchParams.set('page', page);
            window.location.href = url.href;
        }
    }

    function showToast(message, type) {
        alert(message);
    }
</script>