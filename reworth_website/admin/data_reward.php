<?php
    require_once 'role_check.php';

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
    }

    function getReward($supabaseUrl, $supabaseKey, $limit, $offset) {
        $limit = (int)$limit;
        $offset = (int)$offset;
        
        $url = $supabaseUrl . "/rest/v1/reward?select=*&order=created_at.desc&limit=$limit&offset=$offset";
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

    function getTotalReward($supabaseUrl, $supabaseKey) {
        $url = $supabaseUrl . "/rest/v1/reward?select=id_reward";
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

    function formatPoin($poin) {
        if (empty($poin)) return '0 Poin';
        return number_format($poin, 0, ',', '.') . ' Poin';
    }

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;

    $total = getTotalReward($supabaseUrl, $supabaseKey);
    $paginatedData = getReward($supabaseUrl, $supabaseKey, $per_page, $offset);
    $total_pages = ceil($total / $per_page);
    $start_number = $total > 0 ? $offset + 1 : 0;
    $end_number = min($offset + $per_page, $total);
?>

<div class="table-wrap">
    <div class="table-scroll-wrapper">
        <table class="responsive-table" id="dynamicTable">
            <colgroup>
                <col style="width: 60px;">
                <col style="width: 80px;">
                <col style="width: 200px;">
                <col style="width: 120px;">
                <col style="width: 100px;">
                <col style="width: 150px;">
                <col style="width: 200px;">
            </colgroup>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Foto</th>
                    <th>Nama Reward</th>
                    <th>Poin Dibutuhkan</th>
                    <th>Stok</th>
                    <th>Kode Voucher</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($paginatedData)): ?>
                    <?php foreach ($paginatedData as $idx => $r): ?>
                    <tr data-id="<?= $r['id_reward'] ?>">
                        <td><?= $start_number + $idx ?></td>
                        <td class="table-cell-content">
                            <?php if (!empty($r['foto_reward'])): ?>
                                <img src="<?= getSupabaseImageUrl($r['foto_reward']) ?>" 
                                    class="reward-img" 
                                    alt="Foto Reward"
                                    style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="avatar-placeholder" style="display: none; width: 50px; height: 50px; background: #f3f4f6; border-radius: 8px; align-items: center; justify-content: center;">
                                    <i class="bi bi-gift-fill" style="font-size: 24px; color: #9ca3af;"></i>
                                </div>
                            <?php else: ?>
                                <div class="avatar-placeholder" style="width: 50px; height: 50px; background: #f3f4f6; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                    <i class="bi bi-gift-fill" style="font-size: 24px; color: #9ca3af;"></i>
                                </div>
                            <?php endif; ?>
                         </td>
                        <td class="table-cell-content">
                            <div class="reward-info">
                                <strong><?= htmlspecialchars($r['nama_reward'] ?? '-') ?></strong>
                            </div>
                         </td>
                        <td class="table-cell-content">
                            <div class="poin-info">
                                <span class="poin-badge"><?= formatPoin($r['poin_dibutuhkan'] ?? 0) ?></span>
                            </div>
                         </td>
                        <td class="table-cell-content">
                            <div class="stok-info">
                                <?php 
                                $stok = $r['stok'] ?? 0;
                                $stokClass = $stok <= 0 ? 'text-danger' : ($stok <= 10 ? 'text-warning' : 'text-success');
                                ?>
                                <strong class="<?= $stokClass ?>"><?= number_format($stok, 0, ',', '.') ?></strong>
                                <?php if ($stok <= 0): ?>
                                    <small class="badge bg-danger" style="font-size: 10px;">Habis</small>
                                <?php elseif ($stok <= 10): ?>
                                    <small class="badge bg-warning" style="font-size: 10px;">Sisa sedikit</small>
                                <?php endif; ?>
                            </div>
                         </td>
                        <td class="table-cell-content">
                            <div class="voucher-info">
                                <code style="font-size: 12px; background: #f3f4f6; padding: 4px 8px; border-radius: 6px;">
                                    <?= htmlspecialchars($r['kode_voucher'] ?? '-') ?>
                                </code>
                            </div>
                         </td>
                        <td class="table-cell-content">
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn-aksi btn-edit" onclick="editData('reward', '<?= $r['id_reward'] ?>')">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                                <button class="btn-aksi btn-hapus" onclick="hapusData('reward', '<?= $r['id_reward'] ?>')">
                                    <i class="bi bi-trash3"></i> Hapus
                                </button>
                            </div>
                         </td>
                     </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-4" style="color: #6B8A7E;">
                            <i class="bi bi-gift" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                            Belum ada data reward
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