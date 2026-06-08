<?php
    require_once 'role_check.php';

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
    }

    function getRole($supabaseUrl, $supabaseKey, $limit, $offset) {
        $limit = (int)$limit;
        $offset = (int)$offset;
        
        $url = $supabaseUrl . "/rest/v1/role?select=*&limit=$limit&offset=$offset";
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

    function getTotalRole($supabaseUrl, $supabaseKey) {
        $url = $supabaseUrl . "/rest/v1/role?select=id_role";
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

    function getRoleBadge($roleName) {
        $roleName = strtolower($roleName);
        if ($roleName == 'admin') {
            return '<span class="status-badge status-akan_datang">Admin</span>';
        } elseif ($roleName == 'dlh') {
            return '<span class="status-badge status-berlangsung">DLH</span>';
        } elseif ($roleName == 'bank sampah') {
            return '<span class="status-badge status-selesai">Bank Sampah</span>';
        } else {
            return '<span class="status-badge role-default">' . htmlspecialchars($roleName) . '</span>';
        }
    }

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;

    $total = getTotalRole($supabaseUrl, $supabaseKey);
    $paginatedData = getRole($supabaseUrl, $supabaseKey, $per_page, $offset);
    $total_pages = ceil($total / $per_page);
    $start_number = $total > 0 ? $offset + 1 : 0;
    $end_number = min($offset + $per_page, $total);
?>

<div class="table-wrap">
    <div class="table-scroll-wrapper">
        <table class="responsive-table" id="dynamicTable">
            <colgroup>
                <col style="width: 60px;">
                <col style="width: 250px;">
                <col style="width: 250px;">
            </colgroup>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Role</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($paginatedData)): ?>
                    <?php foreach ($paginatedData as $idx => $r): ?>
                    <tr data-id="<?= $r['id_role'] ?>">
                        <td><?= $start_number + $idx ?></td>
                        <td class="table-cell-content">
                            <div class="role-info">
                                <?= getRoleBadge($r['nama_role'] ?? '-') ?>
                            </div>
                        </td>
                        <td class="table-cell-content">
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn-aksi btn-edit" onclick="editData('role', '<?= $r['id_role'] ?>')">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                                <button class="btn-aksi btn-hapus" onclick="hapusData('role', '<?= $r['id_role'] ?>')">
                                    <i class="bi bi-trash3"></i> Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center py-4" style="color: #6B8A7E;">
                            <i class="bi bi-tag" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                            Belum ada data role
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