<?php
    require_once 'role_check.php';

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
    }

    function getSponsor($supabaseUrl, $supabaseKey, $filters = []) {
        $orderBy = "created_at.desc";
        $sortBy = $filters['sort_by'] ?? 'terbaru';
        
        switch($sortBy) {
            case 'terbaru':
                $orderBy = "created_at.desc";
                break;
            case 'terlama':
                $orderBy = "created_at.asc";
                break;
            case 'nama_asc':
                $orderBy = "nama_sponsor.asc";
                break;
            case 'nama_desc':
                $orderBy = "nama_sponsor.desc";
                break;
            default:
                $orderBy = "created_at.desc";
        }
        
        $url = $supabaseUrl . "/rest/v1/sponsor?select=*&order=" . $orderBy;
        
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

    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    $sortBy = $_GET['sort_by'] ?? 'terbaru';
    
    $filters = [
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'sort_by' => $sortBy
    ];
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 10;

    $allSponsor = getSponsor($supabaseUrl, $supabaseKey, $filters);
    $total = count($allSponsor);
    $start = ($page - 1) * $per_page;
    $paginatedData = array_slice($allSponsor, $start, $per_page);
    $total_pages = ceil($total / $per_page);
    $start_number = $total > 0 ? $start + 1 : 0;
    $end_number = min($start + $per_page, $total);
?>

<div class="table-wrap">
    <div class="table-scroll-wrapper">
        <table class="responsive-table" id="dynamicTable">
            <colgroup>
                <col style="width: 60px;">
                <col style="width: 150px;">
                <col style="width: 120px;">
                <col style="width: 120px;">
                <col style="width: 200px;">
            </colgroup>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Sponsor</th>
                    <th>Kontak</th>
                    <th>Jenis Sponsor</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($paginatedData)): ?>
                    <?php foreach ($paginatedData as $idx => $s): ?>
                    <tr data-id="<?= $s['id_sponsor'] ?>">
                        <td><?= $start_number + $idx ?> </td>
                        <td class="table-cell-content"><?= htmlspecialchars($s['nama_sponsor'] ?? '-') ?> </td>
                        <td class="table-cell-content"><?= htmlspecialchars($s['kontak'] ?? '-') ?> </td>
                        <td class="table-cell-content"><?= htmlspecialchars($s['jenis_sponsor'] ?? '-') ?> </td>
                        <td>
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn-aksi btn-lihat" data-tab="sponsor" data-id="<?= $s['id_sponsor'] ?>">
                                    <i class="bi bi-file-earmark-text"></i> Lihat
                                </button>
                                <button class="btn-aksi btn-edit" data-tab="sponsor" data-id="<?= $s['id_sponsor'] ?>">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                                <button class="btn-aksi btn-hapus" data-tab="sponsor" data-id="<?= $s['id_sponsor'] ?>">
                                    <i class="bi bi-trash3"></i> Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-4" style="color: #6B8A7E;">
                            <i class="bi bi-megaphone" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                            Belum ada data sponsor
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