<?php
    session_start();

    if (!isset($_SESSION['role'])) {
        header("Location: ../login.php");
        exit;
    }

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    function formatTanggal($dateString) {
        if (empty($dateString)) return '-';
        try {
            $date = new DateTime($dateString);
            $bulan = [
                1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
            ];
            $tahun = $date->format('Y');
            $hari = $date->format('d');
            $bulanIndex = (int)$date->format('n');
            return $hari . ' ' . $bulan[$bulanIndex] . ' ' . $tahun;
        } catch (Exception $e) {
            return '-';
        }
    }

    function getKontribusiSponsor($supabaseUrl, $supabaseKey, $filters = []) {
        $sortBy = $filters['sort_by'] ?? 'terbaru';
        $orderBy = "created_at.desc";
        
        switch($sortBy) {
            case 'terbaru':
                $orderBy = "created_at.desc";
                break;
            case 'terlama':
                $orderBy = "created_at.asc";
                break;
            case 'nama_asc':
                $orderBy = "sponsor(nama_sponsor).asc";
                break;
            case 'nama_desc':
                $orderBy = "sponsor(nama_sponsor).desc";
                break;
            default:
                $orderBy = "created_at.desc";
        }
        
        $url = $supabaseUrl . "/rest/v1/kontribusi_sponsor?select=*,sponsor(nama_sponsor)&order=" . $orderBy;
        
        if (!empty($filters['date_from'])) {
            $url .= "&tanggal=gte." . $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $url .= "&tanggal=lte." . $filters['date_to'];
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

    $allKontribusi = getKontribusiSponsor($supabaseUrl, $supabaseKey, $filters);
    $total = count($allKontribusi);
    $start = ($page - 1) * $per_page;
    $paginatedData = array_slice($allKontribusi, $start, $per_page);
    $total_pages = ceil($total / $per_page);
    $start_number = $total > 0 ? $start + 1 : 0;
    $end_number = min($start + $per_page, $total);
?>

<div class="table-wrap">
    <div class="table-scroll-wrapper">
        <table class="responsive-table" id="dynamicTable">
            <colgroup>
                <col style="width: 60px;">
                <col style="width: 180px;">
                <col style="width: 180px;">
                <col style="width: 120px;">
                <col style="width: 250px;">
            </colgroup>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Sponsor</th>
                    <th>Jenis Kontribusi</th>
                    <th>Tanggal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($paginatedData)): ?>
                    <?php foreach ($paginatedData as $idx => $k): ?>
                    <tr data-id="<?= $k['id_kontribusi'] ?>">
                        <td><?= $start_number + $idx ?> </td>
                        <td class="table-cell-content">
                            <?php 
                                $namaSponsor = '-';
                                if (isset($k['sponsor']) && is_array($k['sponsor'])) {
                                    $namaSponsor = htmlspecialchars($k['sponsor']['nama_sponsor'] ?? '-');
                                } elseif (isset($k['sponsor'])) {
                                    $namaSponsor = htmlspecialchars($k['sponsor'] ?? '-');
                                }
                                echo $namaSponsor;
                            ?>
                        </td>
                        <td class="table-cell-content"><?= htmlspecialchars($k['jenis_kontribusi'] ?? '-') ?> </td>
                        <td class="table-cell-content"><?= formatTanggal($k['tanggal'] ?? '') ?> </td>
                        <td>
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn-aksi btn-lihat" data-tab="kontribusi" data-id="<?= $k['id_kontribusi'] ?>">
                                    <i class="bi bi-file-earmark-text"></i> Lihat
                                </button>
                                <button class="btn-aksi btn-edit" data-tab="kontribusi" data-id="<?= $k['id_kontribusi'] ?>">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                                <button class="btn-aksi btn-hapus" data-tab="kontribusi" data-id="<?= $k['id_kontribusi'] ?>">
                                    <i class="bi bi-trash3"></i> Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-4" style="color: #6B8A7E;">
                            <i class="bi bi-gift" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                            Belum ada data kontribusi sponsor
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