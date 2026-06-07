<?php
    session_start();

    if (!isset($_SESSION['role'])) {
        header("Location: ../login.php");
        exit;
    }

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
    }

    function getLangganan($supabaseUrl, $supabaseKey, $limit, $offset) {
        $limit = (int)$limit;
        $offset = (int)$offset;
        
        $url = $supabaseUrl . "/rest/v1/langganan?select=*,penjual(id_penjual,nama_penjual,email)&order=id_langganan.desc&limit=$limit&offset=$offset";
        $headers = [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey"
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("Supabase Error: HTTP $httpCode - " . substr($response, 0, 500));
        }
        
        if ($httpCode === 200 && $response !== false) {
            $data = json_decode($response, true);
            return is_array($data) ? $data : [];
        }
        return [];
    }

    function getTotalLangganan($supabaseUrl, $supabaseKey) {
        $url = $supabaseUrl . "/rest/v1/langganan?select=id_langganan";
        $headers = [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey"
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
        
        return "$tanggal " . $bulan[$bulanNum] . " $tahun";
    }

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;

    $total = getTotalLangganan($supabaseUrl, $supabaseKey);
    $paginatedData = getLangganan($supabaseUrl, $supabaseKey, $per_page, $offset);
    $total_pages = ceil($total / $per_page);
    $start_number = $total > 0 ? $offset + 1 : 0;
    $end_number = min($offset + $per_page, $total);
?>

<div class="table-wrap">
    <div class="table-scroll-wrapper">
        <table class="responsive-table" id="dynamicTable">
            <colgroup>
                <col style="width: 40px;">
                <col style="width: 110px;">
                <col style="width: 120px;">
                <col style="width: 120px;">
                <col style="width: 100px;">
                <col style="width: 10px;">
                <col style="width: 250px;">
            </colgroup>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Penjual</th>
                    <th>Tanggal Mulai</th>
                    <th>Tanggal Selesai</th>
                    <th>Jumlah Bayar</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($paginatedData)): ?>
                    <?php foreach ($paginatedData as $idx => $l): 
                        $namaPenjual = '-';
                        $emailPenjual = '';
                        if (isset($l['penjual']) && is_array($l['penjual'])) {
                            $namaPenjual = $l['penjual']['nama_penjual'] ?? '-';
                            $emailPenjual = $l['penjual']['email'] ?? '';
                        }
                        
                        $status = $l['status'] ?? 'menunggu_verifikasi';
                        $statusBadge = '';
                        if ($status == 'aktif') {
                            $statusBadge = '<span class="status-badge status-selesai">Aktif</span>';
                        } elseif ($status == 'expired') {
                            $statusBadge = '<span class="status-badge status-akan_datang">Expired</span>';
                        } elseif ($status == 'menunggu_verifikasi') {
                            $statusBadge = '<span class="status-badge status-berlangsung">Menunggu Verifikasi</span>';
                        } else {
                            $statusBadge = '<span class="status-badge status-berlangsung">' . htmlspecialchars($status) . '</span>';
                        }

                        $tglMulai = !empty($l['tanggal_mulai']) ? formatTanggalIndonesia($l['tanggal_mulai']) : '-';
                        $tglSelesai = !empty($l['tanggal_selesai']) ? formatTanggalIndonesia($l['tanggal_selesai']) : '-';
                    ?>
                    <tr data-id="<?= $l['id_langganan'] ?>">
                        <td><?= $start_number + $idx ?></td>
                        <td class="table-cell-content">
                            <div class="penjual-info">
                                <strong><?= htmlspecialchars($namaPenjual) ?></strong>
                            </div>
                        </td>
                        <td class="table-cell-content"><?= $tglMulai ?></td>
                        <td class="table-cell-content"><?= $tglSelesai ?></td>
                        <td class="table-cell-content"><?= 'Rp ' . number_format($l['jumlah_bayar'] ?? 0, 0, ',', '.') ?></td>
                        <td class="table-cell-content"><?= $statusBadge ?></td>
                        <td class="table-cell-content">
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn-aksi btn-lihat" onclick="lihatData('langganan', '<?= $l['id_langganan'] ?>')">
                                    <i class="bi bi-file-earmark-text"></i> Lihat
                                </button>
                                <button class="btn-aksi btn-edit" onclick="editData('langganan', '<?= $l['id_langganan'] ?>')">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                                <button class="btn-aksi btn-hapus" onclick="hapusData('langganan', '<?= $l['id_langganan'] ?>')">
                                    <i class="bi bi-trash3"></i> Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-4" style="color: #6B8A7E;">
                            <i class="bi bi-journal-bookmark-fill" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                            Belum ada data langganan
                        </td>
                    </tr>
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