<?php
    require_once 'role_check.php';

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $userName  = $_SESSION['nama_admin']  ?? 'User';
    $userEmail = $_SESSION['email']       ?? 'user@example.com';
    $userRole  = $_SESSION['role']        ?? '';
    $userFoto  = $_SESSION['foto_profil'] ?? '';
    $userId    = $_SESSION['id_admin']    ?? '';

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        $bucketUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/";
        return $bucketUrl . ltrim($path, '/');
    }

    function supabaseGet($url, $key) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $key",
            "Authorization: Bearer $key",
            "Content-Type: application/json"
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 200 && $response !== false) {
            return json_decode($response, true) ?: [];
        }
        return [];
    }


    $apresiasiUrl = $supabaseUrl . "/rest/v1/apresiasi?select=*,wilayah(rw,kelurahan,kecamatan)&order=created_at.desc";
    $apresiasiList = supabaseGet($apresiasiUrl, $supabaseKey);


    $wilayahUrl = $supabaseUrl . "/rest/v1/wilayah?select=*";
    $wilayahList = supabaseGet($wilayahUrl, $supabaseKey);


    $setorUrl = $supabaseUrl . "/rest/v1/setor_sampah?select=total_uang,pengguna(id_wilayah)&status=eq.selesai";
    $setorList = supabaseGet($setorUrl, $supabaseKey);


    $wilayahPoin = [];
    foreach ($setorList as $s) {
        $idW = $s['pengguna']['id_wilayah'] ?? null;
        if ($idW) {
            $wilayahPoin[$idW] = ($wilayahPoin[$idW] ?? 0) + (float)($s['total_uang'] ?? 0);
        }
    }


    foreach ($wilayahList as &$w) {
        $w['total_poin'] = (int)($wilayahPoin[$w['id_wilayah']] ?? 0);
    }
    unset($w);


    usort($wilayahList, fn($a, $b) => $b['total_poin'] - $a['total_poin']);

    $lb_per_page = 20;  // leaderboard: 20 per halaman (top 3 di podium + 17 di list)
    $rv_per_page = 10;  // riwayat: 10 per halaman


    $current_lb_page = isset($_GET['lb_page']) ? (int)$_GET['lb_page'] : 1;
    $total_lb = count($wilayahList);
    $total_lb_pages = max(1, ceil($total_lb / $lb_per_page));
    $lb_start = ($current_lb_page - 1) * $lb_per_page;
    $current_lb = array_slice($wilayahList, $lb_start, $lb_per_page);
    $lb_from = $total_lb > 0 ? $lb_start + 1 : 0;
    $lb_to = min($lb_start + $lb_per_page, $total_lb);


    $current_rv_page = isset($_GET['rv_page']) ? (int)$_GET['rv_page'] : 1;
    $total_rv = count($apresiasiList);
    $total_rv_pages = max(1, ceil($total_rv / $rv_per_page));
    $rv_start = ($current_rv_page - 1) * $rv_per_page;
    $current_rv = array_slice($apresiasiList, $rv_start, $rv_per_page);
    $rv_from = $total_rv > 0 ? $rv_start + 1 : 0;
    $rv_to = min($rv_start + $rv_per_page, $total_rv);


    $top3 = array_slice($wilayahList, 0, 3);

    $podium = [];
    if (isset($top3[1])) $podium[] = ['rank' => 2, 'data' => $top3[1]];
    if (isset($top3[0])) $podium[] = ['rank' => 1, 'data' => $top3[0]];
    if (isset($top3[2])) $podium[] = ['rank' => 3, 'data' => $top3[2]];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DLH – Apresiasi RW</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link rel="stylesheet" href="style/form.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-logo">
        <img src="img/logo.png" alt="Logo ReWorth" title="DLH Kota Malang">
    </div>
    <nav class="sidebar-nav">
        <div class="nav-item">
            <a href="dashboard.php" class="nav-link-custom">
                <i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="laporan_sampah.php" class="nav-link-custom">
                <i class="bi bi-exclamation-diamond-fill"></i><span>Laporan Sampah</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="apresiasi_rw.php" class="nav-link-custom active">
                <i class="bi bi-award-fill"></i><span>Apresiasi RW</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="event_lingkungan.php" class="nav-link-custom">
                <i class="bi bi-calendar-event-fill"></i><span>Event Lingkungan</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="laporan_analitik.php" class="nav-link-custom">
                <i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Analitik</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="data_petugas.php" class="nav-link-custom">
                <i class="bi bi-people-fill"></i><span>Data Petugas</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="pengaturan_akun.php" class="nav-link-custom">
                <i class="bi bi-gear-fill"></i><span>Pengaturan Akun</span>
            </a>
        </div>
    </nav>
    <div class="sidebar-logout">
        <a class="logout-btn" href="../logout.php">
            <i class="bi bi-box-arrow-right"></i><span>Logout</span>
        </a>
    </div>
</aside>

<div class="main-wrap">
    <div class="topbar">
        <div class="topbar-inner">
            <h1 class="topbar-title">Apresiasi RW</h1>
            <div class="topbar-user">
                <div class="topbar-user-info">
                    <div class="topbar-user-name"><?= htmlspecialchars($userName) ?></div>
                    <div class="topbar-user-email"><?= htmlspecialchars($userEmail) ?></div>
                </div>
                <div class="topbar-avatar">
                    <?php if (!empty($userFoto)): $fotoUrl = getSupabaseImageUrl($userFoto); ?>
                        <img src="<?= htmlspecialchars($fotoUrl) ?>"
                            style="width:100%;height:100%;border-radius:50%;object-fit:cover;"
                            onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <i class="bi bi-person-fill" style="display:none;"></i>
                    <?php else: ?>
                        <i class="bi bi-person-fill"></i>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    
    <div class="action-bar-wrap">
        <div class="action-bar">
            <div class="search-wrap">
                <i class="bi bi-search"></i>
                <input type="text" class="search-input" placeholder="Cari wilayah..." id="searchInput">
            </div>

            <div class="filter-dropdown">
                <button class="btn-filter" onclick="toggleFilter()">
                    <i class="bi bi-sliders2"></i> Filter
                </button>
                <div class="filter-box">
                    <div class="filter-group">
                        <label>Kecamatan</label>
                        <select id="filterKecamatan">
                            <option value="">Semua Kecamatan</option>
                            <?php
                            $kecs = array_unique(array_column($wilayahList, 'kecamatan'));
                            foreach ($kecs as $k) {
                                if ($k) echo '<option value="' . htmlspecialchars(strtolower($k)) . '">' . htmlspecialchars($k) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Urutkan Poin</label>
                        <select id="sortPoin">
                            <option value="desc">Tertinggi</option>
                            <option value="asc">Terendah</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="button" onclick="resetFilter()">Reset</button>
                        <button type="button" onclick="applyFilter()">Terapkan</button>
                    </div>
                </div>
            </div>

            <button class="btn-tambah" onclick="window.location.href='apresiasi_tambah.php'">
                <i class="bi bi-plus-lg"></i> <span>Beri Apresiasi</span>
            </button>
        </div>
    </div>

    
    <div class="content-area">
        <div class="card-custom">
            <div class="tab-header">
                <button class="tab-btn active" onclick="switchTab('leaderboard', this)">Leaderboard RW</button>
                <button class="tab-btn" onclick="switchTab('riwayat', this)">Riwayat Apresiasi</button>
            </div>

            
            <div id="tab-leaderboard">
                <?php if (!empty($podium)): ?>
                <div class="podium-wrap" id="podiumWrap">
                    <?php foreach ($podium as $p):
                        $r = $p['rank'];
                        $d = $p['data'];
                        $avClass = "av$r";
                        $rankClass = "rank-$r r$r";
                        $rwLabel = 'RW ' . str_pad($d['rw'] ?? '?', 2, '0', STR_PAD_LEFT);
                    ?>
                    <div class="podium-card rank-<?= $r ?>">
                        <div class="rank-badge r<?= $r ?>"><?= $r ?></div>
                        <div class="rw-avatar <?= $avClass ?>"><?= $rwLabel ?></div>
                        <div class="kecamatan"><?= htmlspecialchars($d['kelurahan'] ?? '-') ?></div>
                        <div class="poin-value"><?= number_format($d['total_poin']) ?></div>
                        <div class="poin-label">poin</div>
                        <button class="btn-apresiasi"
                            onclick="window.location.href='apresiasi_tambah.php?id_wilayah=<?= $d['id_wilayah'] ?>'">
                            Beri Apresiasi
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="lb-list" id="lbList">
                    <?php if (!empty($current_lb)): ?>
                        <?php foreach ($current_lb as $idx => $w):
                            $rank = $lb_start + $idx + 1;
                            if ($rank <= 3) continue; // Skip top 3 shown in podium
                            $rwLabel = 'RW ' . str_pad($w['rw'] ?? '?', 2, '0', STR_PAD_LEFT);
                        ?>
                        <div class="lb-item"
                            data-kecamatan="<?= strtolower($w['kecamatan'] ?? '') ?>"
                            data-poin="<?= $w['total_poin'] ?>">
                            <div class="lb-rank"><?= $rank ?></div>
                            <div class="lb-avatar-sm"><?= $rwLabel ?></div>
                            <div class="lb-info">
                                <div class="lb-name"><?= $rwLabel ?> <?= htmlspecialchars($w['kelurahan'] ?? '') ?></div>
                                <div class="lb-sub">Kec. <?= htmlspecialchars($w['kecamatan'] ?? '-') ?></div>
                            </div>
                            <div class="lb-poin">
                                <?= number_format($w['total_poin']) ?>
                                <small>Poin</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4" style="color:#6B8A7E;">
                            <i class="bi bi-award" style="font-size:32px;display:block;margin-bottom:8px;"></i>
                            Belum ada data wilayah
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($total_lb_pages > 1): ?>
                <div class="table-footer" id="footer-leaderboard" style="justify-content:flex-end;">
                    <div class="pagination-custom">

                        <?php

                        $lb_range_start = max(1, $current_lb_page - 1);
                        $lb_range_end   = min($total_lb_pages, $current_lb_page + 1);

                        if ($lb_range_end - $lb_range_start < 2) {
                            if ($lb_range_start == 1) $lb_range_end = min(3, $total_lb_pages);
                            else $lb_range_start = max(1, $lb_range_end - 2);
                        }
                        for ($i = $lb_range_start; $i <= $lb_range_end; $i++):
                        ?>
                            <a href="?lb_page=<?= $i ?>" class="page-btn <?= $i == $current_lb_page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($current_lb_page < $total_lb_pages): ?>
                            <a href="?lb_page=<?= $current_lb_page + 1 ?>" class="page-btn page-btn-text">Next</a>
                        <?php else: ?>
                            <span class="page-btn page-btn-text disabled">Next</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            
            <div id="tab-riwayat" style="display:none;">
                <div class="table-scroll-wrapper">
                    <table class="responsive-table" id="riwayatTable">
                        <colgroup>
                            <col style="width:60px;">
                            <col style="width:200px;">
                            <col style="width:200px;">
                            <col style="width:130px;">
                            <col style="width:130px;">
                            <col style="width:120px;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Judul Apresiasi</th>
                                <th>Wilayah</th>
                                <th>Periode</th>
                                <th>Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="riwayatBody">
                            <?php if (!empty($current_rv)): ?>
                                <?php foreach ($current_rv as $idx => $a): ?>
                                <tr data-periode="<?= strtolower($a['periode'] ?? '') ?>">
                                    <td class="td-no"><?= $rv_start + $idx + 1 ?></td>
                                    <td class="td-nama"><?= htmlspecialchars($a['judul_apresiasi'] ?? '-') ?></td>
                                    <td>
                                        <?php
                                        $w = $a['wilayah'] ?? null;
                                        if ($w) {
                                            $rw = 'RW ' . str_pad($w['rw'] ?? '?', 2, '0', STR_PAD_LEFT);
                                            echo htmlspecialchars($rw . ' — ' . ($w['kelurahan'] ?? ''));
                                        } else { echo '-'; }
                                        ?>
                                    </td>
                                    <td><?= htmlspecialchars($a['periode'] ?? '-') ?></td>
                                    <td><?= !empty($a['created_at']) ? date('d M Y', strtotime($a['created_at'])) : '-' ?></td>
                                    <td>
                                        <button class="btn-aksi btn-lihat"
                                            onclick="window.location.href='apresiasi_lihat.php?id=<?= $a['id_apresiasi'] ?>'">
                                            <i class="bi bi-file-earmark-text"></i> Lihat
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4" style="color:#6B8A7E;">
                                        <i class="bi bi-inbox" style="font-size:32px;display:block;margin-bottom:8px;"></i>
                                        Belum ada riwayat apresiasi
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer" id="footer-riwayat">
                    <div class="showing-text">
                        Showing <b><?= $rv_from ?></b> to <b><?= $rv_to ?></b> of <b><?= $total_rv ?></b> entries
                    </div>
                    <div class="pagination-custom">

                        <?php
                        $rv_range_start = max(1, $current_rv_page - 1);
                        $rv_range_end   = min($total_rv_pages, $current_rv_page + 1);
                        if ($rv_range_end - $rv_range_start < 2) {
                            if ($rv_range_start == 1) $rv_range_end = min(3, $total_rv_pages);
                            else $rv_range_start = max(1, $rv_range_end - 2);
                        }
                        for ($i = $rv_range_start; $i <= $rv_range_end; $i++):
                        ?>
                            <a href="?rv_page=<?= $i ?>&tab=riwayat" class="page-btn <?= $i == $current_rv_page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($current_rv_page < $total_rv_pages): ?>
                            <a href="?rv_page=<?= $current_rv_page + 1 ?>&tab=riwayat" class="page-btn page-btn-text">Next</a>
                        <?php else: ?>
                            <span class="page-btn page-btn-text disabled">Next</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function switchTab(tab, el) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        el.classList.add('active');

        document.getElementById('tab-leaderboard').style.display = tab === 'leaderboard' ? '' : 'none';
        document.getElementById('tab-riwayat').style.display     = tab === 'riwayat'     ? '' : 'none';

        const si = document.getElementById('searchInput');
        if (tab === 'leaderboard') {
            si.placeholder = 'Cari wilayah...';
        } else {
            si.placeholder = 'Cari riwayat apresiasi...';
        }
    }

    document.getElementById('searchInput').addEventListener('input', function() {
        const q = this.value.toLowerCase();
        const activeTab = document.querySelector('.tab-btn.active').textContent.trim().toLowerCase();

        if (activeTab.includes('leaderboard')) {
            document.querySelectorAll('#lbList .lb-item').forEach(item => {
                item.style.display = item.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        } else {
            document.querySelectorAll('#riwayatBody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
            updateRowNumbers('#riwayatBody');
        }
    });

    function toggleFilter() {
        document.querySelector('.filter-box').classList.toggle('show');
    }

    function applyFilter() {
        const activeTab = document.querySelector('.tab-btn.active').textContent.trim().toLowerCase();
        const sort = document.getElementById('sortPoin').value;

        if (activeTab.includes('leaderboard')) {
            const kec = document.getElementById('filterKecamatan').value;
            const items = Array.from(document.querySelectorAll('#lbList .lb-item'));

            items.forEach(item => {
                const itemKec = item.dataset.kecamatan || '';
                item.style.display = (!kec || itemKec === kec) ? '' : 'none';
            });

            items.sort((a, b) => {
                const pA = parseInt(a.dataset.poin) || 0;
                const pB = parseInt(b.dataset.poin) || 0;
                return sort === 'asc' ? pA - pB : pB - pA;
            });
            const list = document.getElementById('lbList');
            items.forEach(i => list.appendChild(i));
        } else {
            const rows = Array.from(document.querySelectorAll('#riwayatBody tr'));
            rows.forEach(r => r.style.display = '');
            updateRowNumbers('#riwayatBody');
        }
        document.querySelector('.filter-box').classList.remove('show');
    }

    function resetFilter() {
        document.getElementById('filterKecamatan').value = '';
        document.getElementById('sortPoin').value = 'desc';
        document.querySelectorAll('#lbList .lb-item').forEach(i => i.style.display = '');
        document.querySelectorAll('#riwayatBody tr').forEach(r => r.style.display = '');
        applyFilter();
    }

    function updateRowNumbers(sel) {
        let no = 1;
        document.querySelectorAll(sel + ' tr').forEach(row => {
            if (row.style.display !== 'none') {
                const td = row.querySelector('.td-no');
                if (td) td.textContent = no++;
            }
        });
    }

    function showToast(msg, type = 'success') {
        const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', info: 'bi-info-circle-fill' };
        const div = document.createElement('div');
        div.className = `toast-item ${type}`;
        div.innerHTML = `<i class="bi ${icons[type] || icons.info} toast-icon"></i><span>${msg}</span>`;
        document.getElementById('toastContainer').appendChild(div);
        setTimeout(() => div.remove(), 3500);
    }

    window.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(window.location.search);
        const activeTab = params.get('tab');
        if (activeTab === 'riwayat') {
            const btn = document.querySelectorAll('.tab-btn')[1];
            switchTab('riwayat', btn);
        }


        const toast = params.get('toast');
        if (toast === 'tambah') showToast('Apresiasi berhasil ditambahkan!', 'success');
        if (toast === 'hapus')  showToast('Apresiasi berhasil dihapus.', 'success');
    });
</script>
</body>
</html>