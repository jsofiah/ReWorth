<?php
    require_once 'role_check.php';

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
    }

    function getPengeluaran($supabaseUrl, $supabaseKey, $limit, $offset) {
        $limit = (int)$limit;
        $offset = (int)$offset;
        
        $url = $supabaseUrl . "/rest/v1/pengeluaran?select=*&order=tanggal.desc&limit=$limit&offset=$offset";
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

    function getTotalPengeluaran($supabaseUrl, $supabaseKey) {
        $url = $supabaseUrl . "/rest/v1/pengeluaran?select=id_pengeluaran";
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
        if (!$timestamp || $timestamp === false) return $date;
        
        $tanggal = date('d', $timestamp);
        $bulanNum = (int)date('m', $timestamp);
        $tahun = date('Y', $timestamp);
        
        return "$tanggal " . $bulan[$bulanNum] . " $tahun";
    }

    function formatRupiah($angka) {
        if (empty($angka)) return 'Rp 0';
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;

    $total = getTotalPengeluaran($supabaseUrl, $supabaseKey);
    $paginatedData = getPengeluaran($supabaseUrl, $supabaseKey, $per_page, $offset);
    $total_pages = ceil($total / $per_page);
    $start_number = $total > 0 ? $offset + 1 : 0;
    $end_number = min($offset + $per_page, $total);
    

    $totalSemuaPengeluaran = 0;
    if (!empty($paginatedData)) {
        foreach ($paginatedData as $p) {
            $totalSemuaPengeluaran += $p['jumlah'] ?? 0;
        }
    }
?>

<div class="table-wrap">
    <div class="table-scroll-wrapper">
        <table class="responsive-table" id="dynamicTable">
            <colgroup>
                <col style="width: 60px;">
                <col style="width: 250px;">
                <col style="width: 150px;">
                <col style="width: 150px;">
                <col style="width: 250px;">
            </colgroup>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Keterangan</th>
                    <th>Jumlah</th>
                    <th>Tanggal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($paginatedData)): ?>
                    <?php foreach ($paginatedData as $idx => $p): ?>
                    <tr data-id="<?= $p['id_pengeluaran'] ?>">
                        <td><?= $start_number + $idx ?></td>
                        <td class="table-cell-content">
                            <div class="keterangan-info">
                                <?= htmlspecialchars($p['keterangan'] ?? '-') ?>
                            </div>
                        </td>
                        <td class="table-cell-content">
                            <div class="jumlah-info">
                                <?= formatRupiah($p['jumlah'] ?? 0) ?>
                            </div>
                        </td>
                        <td class="table-cell-content">
                            <div class="tanggal-info">
                                <?= formatTanggalIndonesia($p['tanggal'] ?? '') ?>
                            </div>
                        </td>
                        <td class="table-cell-content">
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn-aksi btn-edit" onclick="editData('pengeluaran', '<?= $p['id_pengeluaran'] ?>')">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                                <button class="btn-aksi btn-hapus" onclick="hapusData('pengeluaran', '<?= $p['id_pengeluaran'] ?>')">
                                    <i class="bi bi-trash3"></i> Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-4" style="color: #6B8A7E;">
                            <i class="bi bi-wallet2" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                            Belum ada data pengeluaran
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($total > 0): ?>
    <div class="table-footer" style="justify-content: space-between;">
        <div class="showing-text">
            Showing <b><?= $start_number ?></b> to <b><?= $end_number ?></b> of <b><?= $total ?></b> entries
        </div>
        <div class="total-pengeluaran" style="background: #f0fdf4; padding: 6px 16px; border-radius: 20px;">
            <span style="font-size: 13px; color: #166534;">Total Pengeluaran Halaman Ini:</span>
            <strong style="color: #166534; font-size: 14px;"><?= formatRupiah($totalSemuaPengeluaran) ?></strong>
        </div>
        <?php if ($total_pages > 1): ?>
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
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>