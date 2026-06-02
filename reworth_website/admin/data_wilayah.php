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

    function getWilayah($supabaseUrl, $supabaseKey, $limit, $offset) {
        $limit = (int)$limit;
        $offset = (int)$offset;
        
        $url = $supabaseUrl . "/rest/v1/wilayah?select=*,pengguna!id_ketua_rw(nama_lengkap,email,no_telepon)&order=kecamatan.asc,kelurahan.asc,rw.asc&limit=$limit&offset=$offset";
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

    function getTotalWilayah($supabaseUrl, $supabaseKey) {
        $url = $supabaseUrl . "/rest/v1/wilayah?select=id_wilayah";
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

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 50;
    $offset = ($page - 1) * $per_page;

    $total = getTotalWilayah($supabaseUrl, $supabaseKey);
    $paginatedData = getWilayah($supabaseUrl, $supabaseKey, $per_page, $offset);
    $total_pages = ceil($total / $per_page);
    $start_number = $total > 0 ? $offset + 1 : 0;
    $end_number = min($offset + $per_page, $total);
?>

<div class="table-wrap">
    <div class="table-scroll-wrapper">
        <table class="responsive-table" id="dynamicTable">
            <colgroup>
                <col style="width: 60px;">
                <col style="width: 150px;">
                <col style="width: 150px;">
                <col style="width: 100px;">
                <col style="width: 150px;">
                <col style="width: 200px;">
                <col style="width: 200px;">
            </colgroup>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kecamatan</th>
                    <th>Kelurahan</th>
                    <th>RW</th>
                    <th>Kota</th>
                    <th>Ketua RW</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($paginatedData)): ?>
                    <?php foreach ($paginatedData as $idx => $w): 
                        $namaKetua = '-';
                        $emailKetua = '';
                        $teleponKetua = '';
                        if (isset($w['pengguna']) && is_array($w['pengguna'])) {
                            $namaKetua = $w['pengguna']['nama_lengkap'] ?? '-';
                            $emailKetua = $w['pengguna']['email'] ?? '';
                            $teleponKetua = $w['pengguna']['no_telepon'] ?? '';
                        }
                    ?>
                    <tr data-id="<?= $w['id_wilayah'] ?>">
                        <td><?= $start_number + $idx ?></td>
                        <td class="table-cell-content"><?= htmlspecialchars($w['kecamatan'] ?? '-') ?></td>
                        <td class="table-cell-content"><?= htmlspecialchars($w['kelurahan'] ?? '-') ?></td>
                        <td class="table-cell-content">
                            <div class="rw-info">
                                <strong>RW <?= htmlspecialchars($w['rw'] ?? '-') ?></strong>
                            </div>
                        </td>
                        <td class="table-cell-content"><?= htmlspecialchars($w['kota'] ?? '-') ?></td>
                        <td class="table-cell-content">
                            <div class="ketua-info">
                                <strong><?= htmlspecialchars($namaKetua) ?></strong>
                                <?php if (!empty($emailKetua)): ?>
                                    <small class="ketua-email">
                                        <i class="bi bi-envelope-fill"></i> <?= htmlspecialchars($emailKetua) ?>
                                    </small>
                                <?php endif; ?>
                                <?php if (!empty($teleponKetua)): ?>
                                    <small class="ketua-telepon">
                                        <i class="bi bi-telephone-fill"></i> <?= htmlspecialchars($teleponKetua) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="table-cell-content">
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn-aksi btn-edit" onclick="editData('wilayah', '<?= $w['id_wilayah'] ?>')">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                                <button class="btn-aksi btn-hapus" onclick="hapusData('wilayah', '<?= $w['id_wilayah'] ?>')">
                                    <i class="bi bi-trash3"></i> Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-4" style="color: #6B8A7E;">
                            <i class="bi bi-map" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                            Belum ada data wilayah
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

<style>
.rw-info {
    font-weight: 600;
}

.ketua-info {
    display: flex;
    flex-direction: column;
}

.ketua-email, .ketua-telepon {
    font-size: 11px;
    color: #6B8A7E;
    margin-top: 2px;
}

.kota-info {
    font-weight: 500;
}
</style>