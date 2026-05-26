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

function getDlh($supabaseUrl, $supabaseKey) {
    $url = $supabaseUrl . "/rest/v1/admin?select=*,role!inner(nama_role)&role.nama_role=eq.dlh&order=nama_admin.asc";
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

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;

$allDlh = getDlh($supabaseUrl, $supabaseKey);
$total = count($allDlh);
$start = ($page - 1) * $per_page;
$paginatedData = array_slice($allDlh, $start, $per_page);
$total_pages = ceil($total / $per_page);
$start_number = $total > 0 ? $start + 1 : 0;
$end_number = min($start + $per_page, $total);
?>

<div class="table-wrap">
    <div class="table-scroll-wrapper">
        <table class="responsive-table" id="dynamicTable">
            <colgroup>
                <col style="width: 60px;">
                <col style="width: 80px;">
                <col style="width: 200px;">
                <col style="width: 200px;">
                <col style="width: 150px;">
            </colgroup>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Foto Profil</th>
                    <th>Nama DLH</th>
                    <th>Email</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($paginatedData)): ?>
                    <?php foreach ($paginatedData as $idx => $d): ?>
                    <tr data-id="<?= $d['id_admin'] ?>">
                        <td><?= $start_number + $idx ?> </td>
                        <td>
                            <?php if (!empty($d['foto_profil'])): ?>
                                <img src="<?= getSupabaseImageUrl($d['foto_profil']) ?>" class="avatar-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <?php else: ?>
                                <div class="avatar" ><?= strtoupper(substr($d['nama_admin'] ?? 'D', 0, 1)) ?></div>
                            <?php endif; ?>
                         </td>
                        <td><?= htmlspecialchars($d['nama_admin'] ?? '-') ?> </td>
                        <td><?= htmlspecialchars($d['email'] ?? '-') ?> </td>
                        <td>
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn-aksi btn-edit" onclick="editAkun('dlh', '<?= $d['id_admin'] ?>')">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                                <button class="btn-aksi btn-hapus" onclick="hapusAkun('dlh', '<?= $d['id_admin'] ?>')">
                                    <i class="bi bi-trash3"></i> Hapus
                                </button>
                            </div>
                         </td>
                     </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                     <tr>
                        <td colspan="6" class="text-center py-4" style="color: #6B8A7E;">
                            <i class="bi bi-tree" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                            Belum ada data DLH
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