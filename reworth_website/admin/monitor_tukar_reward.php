<?php
    require_once 'role_check.php';

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $limit = 10;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit;

    $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'terbaru';

    $orderBy = ($sort == 'terlama') ? "created_at.asc" : "created_at.desc";

    try {
        $url = $supabaseUrl . "/rest/v1/tukar_poin?select=*,pengguna!id_pengguna(nama_lengkap,email,foto_profil,no_telepon),reward!id_reward(nama_reward,foto_reward,poin_dibutuhkan,stok,kode_voucher)&order=" . $orderBy . "&limit=$limit&offset=$offset";
        
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
        
        $tukarList = [];
        if ($response) {
            $tukarList = json_decode($response, true);
            if (!is_array($tukarList)) {
                $tukarList = [];
            }
        }
        
        $countUrl = $supabaseUrl . "/rest/v1/tukar_poin?select=id_tukar";
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
        $tukarList = [];
        $total = 0;
        $totalPages = 0;
        $start_number = 0;
        $end_number = 0;
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

    function formatPoin($poin) {
        if (empty($poin)) return '0 Poin';
        return number_format($poin, 0, ',', '.') . ' Poin';
    }
?>

<div class="table-wrap">
    <div class="table-scroll-wrapper">
        <table class="responsive-table" id="dynamicTable">
            <colgroup>
                <col style="width: 60px;">
                <col style="width: 200px;">
                <col style="width: 250px;">
                <col style="width: 180px;">
                <col style="width: 150px;">
            </colgroup>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Pengguna</th>
                    <th>Nama Reward</th>
                    <th>Tanggal Tukar</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tukarList)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5" style="color: #6B8A7E;">
                            <i class="bi bi-gift" style="font-size: 48px; display: block; margin-bottom: 12px; opacity: 0.5;"></i>
                            Belum ada data penukaran reward
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $no = $offset + 1;
                    foreach ($tukarList as $tukar): 
                        $namaPengguna = '-';
                        $emailPengguna = '';
                        if (isset($tukar['pengguna']) && is_array($tukar['pengguna'])) {
                            $namaPengguna = $tukar['pengguna']['nama_lengkap'] ?? '-';
                            $emailPengguna = $tukar['pengguna']['email'] ?? '';
                        }
                        
                        $namaReward = '-';
                        $poinDibutuhkan = 0;
                        $kodeVoucher = '';
                        if (isset($tukar['reward']) && is_array($tukar['reward'])) {
                            $namaReward = $tukar['reward']['nama_reward'] ?? '-';
                            $poinDibutuhkan = $tukar['reward']['poin_dibutuhkan'] ?? 0;
                            $kodeVoucher = $tukar['reward']['kode_voucher'] ?? '';
                        }
                        
                        $tanggal = $tukar['created_at'] ?? null;
                        $idTukar = $tukar['id_tukar'] ?? '';
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <div class="pengguna-info">
                                    <?= htmlspecialchars($namaPengguna) ?>
                                </div>
                            </td>
                            <td>
                                <div class="reward-info">
                                    <?= htmlspecialchars($namaReward) ?> <br>
                                    <?php if (!empty($poinDibutuhkan)): ?>
                                        <small class="reward-poin">
                                            <i class="bi bi-coin"></i> <?= formatPoin($poinDibutuhkan) ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if (!empty($kodeVoucher)): ?>
                                        <small class="reward-voucher">
                                            <i class="bi bi-ticket-perforated-fill"></i> Kode: <?= htmlspecialchars($kodeVoucher) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?= formatTanggalIndonesia($tanggal) ?></td>
                            <td>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn-aksi btn-lihat" onclick="lihatDetail('<?= $idTukar ?>', 'tukar_reward')">
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