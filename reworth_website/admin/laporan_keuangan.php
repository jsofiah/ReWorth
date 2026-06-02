<?php
    session_start();
    if (!isset($_SESSION['role'])) { 
        header("Location: ../login.php"); 
        exit; 
    }

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $userName  = $_SESSION['nama_admin']  ?? 'User';
    $userEmail = $_SESSION['email']       ?? 'user@example.com';
    $userFoto  = $_SESSION['foto_profil'] ?? '';


    $dateFrom = $_GET['date_from'] ?? date('Y-01-01');
    $dateTo   = $_GET['date_to']   ?? date('Y-06-30');
    $fromTs   = $dateFrom . 'T00:00:00';
    $toTs     = $dateTo   . 'T23:59:59';


    $labelFrom = date('d M Y', strtotime($dateFrom));
    $labelTo   = date('d M Y', strtotime($dateTo));

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
    }

    function sbGet($url, $key, $endpoint) {
        $ch = curl_init($url . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "apikey: $key",
                "Authorization: Bearer $key",
                "Content-Type: application/json"
            ]
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $httpCode === 200 ? (json_decode($response, true) ?: []) : [];
    }

    $totalPengguna = count(sbGet($supabaseUrl, $supabaseKey, "/rest/v1/pengguna?select=id_pengguna"));
    $totalAdmin    = count(sbGet($supabaseUrl, $supabaseKey, "/rest/v1/admin?select=id_admin"));
    $totalPenjual  = count(sbGet($supabaseUrl, $supabaseKey, "/rest/v1/penjual?select=id_penjual"));

    $langgananList = sbGet($supabaseUrl, $supabaseKey, 
        "/rest/v1/langganan?select=jumlah_bayar,created_at"
        . "&created_at=gte." . urlencode($fromTs)
        . "&created_at=lte." . urlencode($toTs));
    $totalLangganan = array_sum(array_column($langgananList, 'jumlah_bayar'));
    $jumlahLangganan = count($langgananList);

    $komisiList = sbGet($supabaseUrl, $supabaseKey, 
        "/rest/v1/komisi?select=total_komisi,created_at"
        . "&created_at=gte." . urlencode($fromTs)
        . "&created_at=lte." . urlencode($toTs));
    $totalKomisi = array_sum(array_column($komisiList, 'total_komisi'));
    $jumlahKomisi = count($komisiList);

    $sponsorList = sbGet($supabaseUrl, $supabaseKey, 
        "/rest/v1/kontribusi_sponsor?select=*,sponsor!id_sponsor(nama_sponsor)"
        . "&tanggal=gte." . urlencode($dateFrom)
        . "&tanggal=lte." . urlencode($dateTo));

    $uniqueSponsor = [];
    foreach ($sponsorList as $k) {
        $idSponsor = $k['id_sponsor'] ?? '';
        if (!empty($idSponsor) && !in_array($idSponsor, $uniqueSponsor)) {
            $uniqueSponsor[] = $idSponsor;
        }
    }
    $totalSponsor = count($uniqueSponsor);

    $totalKontribusiSponsor = array_sum(array_column($sponsorList, 'nominal_uang'));

    $totalKontribusiBarang = 0;
    $barangCount = 0;
    foreach ($sponsorList as $k) {
        if ($k['jenis_kontribusi'] == 'Barang') {
            $totalKontribusiBarang += ($k['jumlah_barang'] ?? 0);
            $barangCount++;
        }
    }


    $sponsorGroup = [];
    foreach ($sponsorList as $k) {
        $namaSponsor = $k['sponsor']['nama_sponsor'] ?? 'Unknown';
        if (!isset($sponsorGroup[$namaSponsor])) {
            $sponsorGroup[$namaSponsor] = [
                'Uang' => 0,
                'Barang' => []
            ];
        }
        if ($k['jenis_kontribusi'] == 'Uang') {
            $sponsorGroup[$namaSponsor]['Uang'] += ($k['nominal_uang'] ?? 0);
        } else {
            $sponsorGroup[$namaSponsor]['Barang'][] = [
                'nama_barang' => $k['nama_barang'] ?? '-',
                'jumlah_barang' => $k['jumlah_barang'] ?? 0,
                'keterangan' => $k['keterangan'] ?? ''
            ];
        }
    }

    $pengeluaranList = sbGet($supabaseUrl, $supabaseKey, 
        "/rest/v1/pengeluaran?select=jumlah,created_at"
        . "&created_at=gte." . urlencode($fromTs)
        . "&created_at=lte." . urlencode($toTs));
    $totalPengeluaran = array_sum(array_column($pengeluaranList, 'jumlah'));
    $jumlahPengeluaran = count($pengeluaranList);

    $totalPemasukan = $totalLangganan + $totalKomisi + $totalKontribusiSponsor;
    $saldoAkhir = $totalPemasukan - $totalPengeluaran;

    function fmtRp($n) { 
        return 'Rp ' . number_format((float)$n, 0, ',', '.'); 
    }
    function fmtNum($n) { 
        return number_format((int)$n, 0, ',', '.'); 
    }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan dan Keuangan - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="img/logo.png" alt="Logo ReWorth" title="Admin Kota Malang">
        </div>

        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link-custom">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="kelola_akun.php" class="nav-link-custom">
                    <i class="bi bi-people-fill"></i>
                    <span>Kelola Akun</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="kelola_data_master.php" class="nav-link-custom">
                    <i class="bi bi-database-fill-gear"></i>
                    <span>Kelola Data Master</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="monitor_transaksi.php" class="nav-link-custom">
                    <i class="bi bi-arrow-left-right"></i>
                    <span>Monitor Transaksi</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="pembayaran_komisi.php" class="nav-link-custom">
                    <i class="bi bi-cash-coin"></i>
                    <span>Pembayaran Komisi</span>
                    </a>
            </div>
            <div class="nav-item">
                <a href="aktivitas.php" class="nav-link-custom">
                    <i class="bi bi-activity"></i>
                    <span>Aktivitas</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="sponsor.php" class="nav-link-custom">
                    <i class="bi bi-megaphone-fill"></i>
                    <span>Sponsor</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="laporan_keuangan.php" class="nav-link-custom active">
                    <i class="bi bi-bar-chart-line-fill"></i>
                    <span>Laporan dan Keuangan</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="pengaturan_akun.php" class="nav-link-custom">
                    <i class="bi bi-gear-fill"></i>
                    <span>Pengaturan Akun</span>
                </a>
            </div>
        </nav>

        <div class="sidebar-logout">
            <a class="logout-btn" href="../logout.php">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <div class="main-wrap">
        <div class="topbar">
            <div class="topbar-inner">
                <h1 class="topbar-title">Laporan dan Keuangan</h1>
                <div class="topbar-user">
                    <div class="topbar-user-info">                            <div class="topbar-user-name"><?= htmlspecialchars($userName) ?></div>
                        <div class="topbar-usr-email"><?= htmlspecialchars($userEmail) ?></div>
                    </div>
                    <div class="topbar-avatar">
                        <?php if (!empty($userFoto)): $fotoUrl = getSupabaseImageUrl($userFoto); ?>
                            <img src="<?= htmlspecialchars($fotoUrl) ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <i class="bi bi-person-fill" style="display:none;"></i>
                        <?php else: ?><i class="bi bi-person-fill"></i><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="action-bar-wrap">
            <form method="GET" action="" class="laporan-bar" id="filterForm" style="background:#fff;border-radius:20px;padding:16px 24px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="bi bi-calendar3" style="font-size:22px;color:var(--green);"></i>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" style="padding:8px 12px;border:1px solid #E5E7EB;border-radius:8px;font-family:'Poppins',sans-serif;">
                    <span>–</span>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" style="padding:8px 12px;border:1px solid #E5E7EB;border-radius:8px;font-family:'Poppins',sans-serif;">
                </div>
                <button type="submit" class="btn-generate" style="padding:10px 28px;border:2px solid var(--green);border-radius:12px;background:transparent;color:var(--green);font-weight:700;cursor:pointer;">Generate</button>
                <button type="button" class="btn-export" onclick="exportPDF()" style="display:flex;align-items:center;gap:6px;padding:10px 22px;border:none;border-radius:12px;background:#FFCF00;color:#1A2E24;font-weight:700;cursor:pointer;">
                    <i class="bi bi-file-earmark-pdf"></i> Export PDF
                </button>
            </form>
        </div>

        <div class="content-area">
            <div class="info-banner" style="background:#F0F8F5;border-radius:12px;padding:12px 20px;margin-bottom:24px;display:flex;align-items:center;gap:12px;">
                <i class="bi bi-info-circle-fill" style="color:var(--green);font-size:18px;"></i>
                <span style="font-size:13px;color:#2F5D50;">Menampilkan data periode <strong><?= $labelFrom ?> – <?= $labelTo ?></strong></span>
            </div>

            <div class="section-header">
                <h3><i class="bi bi-people-fill"></i> Jumlah Akun</h3>
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Pengguna</div>
                    <div class="stat-value"><?= fmtNum($totalPengguna) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Admin</div>
                    <div class="stat-value"><?= fmtNum($totalAdmin) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Penjual</div>
                    <div class="stat-value"><?= fmtNum($totalPenjual) ?></div>
                </div>
            </div>

            <div class="section-header">
                <h3><i class="bi bi-arrow-down-circle-fill"></i> Pemasukan</h3>
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Biaya Langganan</div>
                    <div class="stat-value green"><?= fmtRp($totalLangganan) ?></div>
                    <small><?= fmtNum($jumlahLangganan) ?> transaksi</small>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Komisi Penjual</div>
                    <div class="stat-value green"><?= fmtRp($totalKomisi) ?></div>
                    <small><?= fmtNum($jumlahKomisi) ?> transaksi</small>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Kontribusi Sponsor</div>
                    <div class="stat-value green"><?= fmtRp($totalKontribusiSponsor) ?></div>
                    <small><?= fmtNum($totalSponsor) ?> sponsor</small>
                </div>
            </div>

            <div class="section-header">
                <h3><i class="bi bi-arrow-up-circle-fill"></i> Pengeluaran</h3>
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Pengeluaran</div>
                    <div class="stat-value" style="color:#C62828;"><?= fmtRp($totalPengeluaran) ?></div>
                    <small><?= fmtNum($jumlahPengeluaran) ?> transaksi</small>
                </div>
            </div>

            <div class="section-header">
                <h3><i class="bi bi-bar-chart-line-fill"></i> Rekap Keuangan</h3>
            </div>
            <div class="stats-grid">
                <div class="stat-card" style="background:linear-gradient(135deg,#E8F5E9,#C8E6C9);">
                    <div class="stat-label">Total Pemasukan</div>
                    <div class="stat-value" style="color:#2E7D32;"><?= fmtRp($totalPemasukan) ?></div>
                    <small>Langganan + Komisi + Sponsor</small>
                </div>
                <div class="stat-card" style="background:linear-gradient(135deg,#FFEBEE,#FFCDD2);">
                    <div class="stat-label">Total Pengeluaran</div>
                    <div class="stat-value" style="color:#C62828;"><?= fmtRp($totalPengeluaran) ?></div>
                    <small>Periode <?= $labelFrom ?> – <?= $labelTo ?></small>
                </div>
                <div class="stat-card" style="background:linear-gradient(135deg,#E3F2FD,#BBDEFB);">
                    <div class="stat-label">Saldo Akhir</div>
                    <div class="stat-value" style="color:#1565C0;"><?= fmtRp($saldoAkhir) ?></div>
                    <small>Pemasukan - Pengeluaran</small>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr("#dateRangePicker", {
                mode: "range",
                dateFormat: "d M Y",
                allowInput: false,
                disableMobile: true,
                locale: {
                    rangeSeparator: " – ",
                    months: { 
                        shorthand: ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des']
                    },
                    weekdays: { 
                        shorthand: ['Min','Sen','Sel','Rab','Kam','Jum','Sab']
                    },
                    firstDayOfWeek: 1
                },
                defaultDate: ["<?= $dateFrom ?>", "<?= $dateTo ?>"],
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {

                        const fromDate = selectedDates[0];
                        const toDate = selectedDates[1];
                        
                        const fmt = d => {
                            let year = d.getFullYear();
                            let month = String(d.getMonth() + 1).padStart(2, '0');
                            let day = String(d.getDate()).padStart(2, '0');
                            return year + '-' + month + '-' + day;
                        };
                        
                        document.getElementById('date_from').value = fmt(fromDate);
                        document.getElementById('date_to').value = fmt(toDate);
                        


                    }
                }
            });
        });

        function exportPDF() {
            let from = '';
            let to = '';

            const hiddenFrom = document.getElementById('dateFromHidden');
            const hiddenTo = document.getElementById('dateToHidden');
            
            if (hiddenFrom && hiddenTo && hiddenFrom.value && hiddenTo.value) {
                from = hiddenFrom.value;
                to = hiddenTo.value;
            } else {

                const dateFromInput = document.querySelector('input[name="date_from"]');
                const dateToInput = document.querySelector('input[name="date_to"]');
                from = dateFromInput ? dateFromInput.value : '';
                to = dateToInput ? dateToInput.value : '';
            }
            
            if (!from || !to) {
                alert('Pilih rentang tanggal terlebih dahulu!');
                return;
            }
            
            window.open('laporan_pdf.php?date_from=' + from + '&date_to=' + to, '_blank');
        }
    </script>
</body>
</html>