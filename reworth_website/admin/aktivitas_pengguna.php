<?php
    require_once 'role_check.php';

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $jenisAktivitas = isset($_GET['jenis_aktivitas']) ? $_GET['jenis_aktivitas'] : '';
    $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'terbaru';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 10;

    function getRiwayatAktivitas($supabaseUrl, $supabaseKey, $page = 1, $per_page = 10, $filters = []) {
        $offset = ($page - 1) * $per_page;
        
        $sortOrder = ($filters['sort_by'] == 'terlama') ? 'asc' : 'desc';
        
        $url = $supabaseUrl . "/rest/v1/riwayat_aktivitas?select=*,pengguna!id_pengguna(nama_lengkap,email,foto_profil)&order=created_at.$sortOrder&limit=$per_page&offset=$offset";
        
        if (!empty($filters['jenis_aktivitas'])) {
            $url .= "&jenis_aktivitas=eq." . $filters['jenis_aktivitas'];
        }
        if (!empty($filters['date_from'])) {
            $url .= "&created_at=gte." . $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $url .= "&created_at=lte." . $filters['date_to'] . " 23:59:59";
        }
        
        $headers = [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json"
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response !== false) {
            return json_decode($response, true) ?: [];
        }
        return [];
    }
    
    function getTotalRiwayat($supabaseUrl, $supabaseKey, $filters = []) {
        $url = $supabaseUrl . "/rest/v1/riwayat_aktivitas?select=id_riwayat";
        
        if (!empty($filters['jenis_aktivitas'])) {
            $url .= "&jenis_aktivitas=eq." . $filters['jenis_aktivitas'];
        }
        if (!empty($filters['date_from'])) {
            $url .= "&created_at=gte." . $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $url .= "&created_at=lte." . $filters['date_to'] . " 23:59:59";
        }
        
        $headers = [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json"
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $data = json_decode($response, true);
            return is_array($data) ? count($data) : 0;
        }
        return 0;
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

    function getStatusBadgeAktivitas($status) {
        if (empty($status)) {
            return '-';
        }
        
        $status = strtolower($status);
        if ($status == 'selesai' || $status == 'success') {
            return '<span class="status-badge status-selesai">Selesai</span>';
        } elseif ($status == 'diproses' || $status == 'processing') {
            return '<span class="status-badge status-berlangsung">Diproses</span>';
        } elseif ($status == 'ditolak' || $status == 'rejected' || $status == 'failed') {
            return '<span class="status-badge status-akan_datang">Ditolak</span>';
        } elseif ($status == 'pending') {
            return '<span class="status-badge status-akan_datang">Menunggu</span>';
        } else {
            return '<span class="status-badge status-akan_datang">' . htmlspecialchars($status) . '</span>';
        }
    }

    function formatPerubahan($jenisAktivitas, $perubahanPoin, $perubahanSaldo) {
        $html = '';
        if (!empty($perubahanPoin) && $perubahanPoin != 0) {
            $poinClass = $perubahanPoin > 0 ? 'text-success' : 'text-danger';
            $poinSymbol = $perubahanPoin > 0 ? '+' : '';
            $html .= "<span class='$poinClass'><i class='bi bi-star-fill'></i> $poinSymbol$perubahanPoin Poin</span> ";
        }
        if (!empty($perubahanSaldo) && $perubahanSaldo != 0) {
            $saldoClass = $perubahanSaldo > 0 ? 'text-success' : 'text-danger';
            $saldoSymbol = $perubahanSaldo > 0 ? '+' : '';
            $formattedSaldo = number_format(abs($perubahanSaldo), 0, ',', '.');
            $html .= "<span class='$saldoClass'><i class='bi bi-cash-stack'></i> $saldoSymbol Rp $formattedSaldo</span>";
        }
        return $html ?: '-';
    }

    $filters = [
        'jenis_aktivitas' => $jenisAktivitas,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'sort_by' => $sortBy
    ];

    $allRiwayat = getRiwayatAktivitas($supabaseUrl, $supabaseKey, $page, $per_page, $filters);
    $total = getTotalRiwayat($supabaseUrl, $supabaseKey, $filters);
    $total_pages = ceil($total / $per_page);
    $start_number = $total > 0 ? (($page - 1) * $per_page) + 1 : 0;
    $end_number = min($page * $per_page, $total);
?>

<div class="table-wrap">
    <div class="table-scroll-wrapper">
        <table class="responsive-table" id="dynamicTable">
            <colgroup>
                <col style="width: 60px;">
                <col style="width: 180px;">
                <col style="width: 180px;">
                <col style="width: 200px;">
                <col style="width: 150px;">
                <col style="width: 150px;">
                <col style="width: 120px;">
            </colgroup>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Pengguna</th>
                    <th>Jenis Aktivitas</th>
                    <th>Judul / Keterangan</th>
                    <th>Perubahan</th>
                    <th>Tanggal</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allRiwayat)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5" style="color: #6B8A7E;">
                            <i class="bi bi-inbox" style="font-size: 48px; display: block; margin-bottom: 12px; opacity: 0.5;"></i>
                            Belum ada riwayat aktivitas
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $no = $start_number;
                    foreach ($allRiwayat as $riwayat): 
                        $namaPengguna = '-';
                        if (isset($riwayat['pengguna']) && is_array($riwayat['pengguna'])) {
                            $namaPengguna = $riwayat['pengguna']['nama_lengkap'] ?? '-';
                        } elseif (isset($riwayat['nama_pengguna'])) {
                            $namaPengguna = $riwayat['nama_pengguna'];
                        }
                        
                        $jenisAktivitasText = $riwayat['jenis_aktivitas'] ?? '-';
                        $jenisAktivitasDisplay = ucwords(str_replace('_', ' ', $jenisAktivitasText));
                        $judul = $riwayat['judul'] ?? '-';
                        $deskripsi = $riwayat['deskripsi'] ?? '';
                        $perubahanPoin = $riwayat['perubahan_poin'] ?? 0;
                        $perubahanSaldo = $riwayat['perubahan_saldo'] ?? 0;
                        $tanggal = $riwayat['created_at'] ?? null;
                        $status = $riwayat['status'] ?? 'pending';
                        
                        $keterangan = htmlspecialchars($judul);
                        if (!empty($deskripsi)) {
                            $keterangan .= '<br><small class="text-muted">' . htmlspecialchars($deskripsi) . '</small>';
                        }
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <div class="pelapor-info">
                                    <?= htmlspecialchars($namaPengguna) ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($jenisAktivitasDisplay) ?></td>
                            <td><?= $keterangan ?></td>
                            <td><?= formatPerubahan($jenisAktivitasText, $perubahanPoin, $perubahanSaldo) ?></td>
                            <td><?= formatTanggalIndonesia($tanggal) ?></td>
                            <td><?= getStatusBadgeAktivitas($status) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($total_pages > 1): ?>
    <div class="table-footer">
        <div class="showing-text">
            Showing <b><?= $start_number ?></b> to <b><?= $end_number ?></b> of <b><?= $total ?></b> entries
        </div>
        <div class="pagination-custom">
            <?php if ($page > 1): ?>
                <a href="javascript:void(0)" onclick="changePage(<?= $page - 1 ?>)" class="page-btn page-btn-text">Prev</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="javascript:void(0)" onclick="changePage(<?= $i ?>)" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="javascript:void(0)" onclick="changePage(<?= $page + 1 ?>)" class="page-btn page-btn-text">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    function changePage(page) {
        const urlParams = new URLSearchParams(window.location.search);
        const jenisAktivitas = urlParams.get('jenis_aktivitas') || '';
        const dateFrom = urlParams.get('date_from') || '';
        const dateTo = urlParams.get('date_to') || '';
        const sortBy = urlParams.get('sort_by') || 'terbaru';
        
        let queryParams = new URLSearchParams();
        if (jenisAktivitas) queryParams.append('jenis_aktivitas', jenisAktivitas);
        if (dateFrom) queryParams.append('date_from', dateFrom);
        if (dateTo) queryParams.append('date_to', dateTo);
        if (sortBy) queryParams.append('sort_by', sortBy);
        queryParams.append('page', page);
        
        window.location.href = `aktivitas_pengguna.php?${queryParams.toString()}`;
    }
</script>