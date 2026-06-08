<?php
    require_once 'role_check.php';

    date_default_timezone_set('Asia/Jakarta');

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $dateFrom = $_GET['date_from'] ?? date('Y-01-01');
    $dateTo   = $_GET['date_to']   ?? date('Y-06-30');
    $fromTs   = $dateFrom . 'T00:00:00';
    $toTs     = $dateTo   . 'T23:59:59';

    $labelFrom = date('d F Y', strtotime($dateFrom));
    $labelTo   = date('d F Y', strtotime($dateTo));

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

    function formatTanggalIndonesia($date) {
        if (empty($date)) return '-';
        
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        
        $hari = [
            'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat',
            'Saturday' => 'Sabtu'
        ];
        
        $timestamp = strtotime($date);
        $namaHari = $hari[date('l', $timestamp)] ?? '';
        $tanggal = date('d', $timestamp);
        $bulanNum = (int)date('m', $timestamp);
        $tahun = date('Y', $timestamp);
        $jam = date('H:i', $timestamp);
        
        return "$namaHari, $tanggal " . $bulan[$bulanNum] . " $tahun - $jam WIB";
    }

    function formatTanggalOnly($date) {
        if (empty($date)) return '-';
        
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        
        $timestamp = strtotime($date);
        $tanggal = date('d', $timestamp);
        $bulanNum = (int)date('m', $timestamp);
        $tahun = date('Y', $timestamp);
        
        return "$tanggal " . $bulan[$bulanNum] . " $tahun";
    }

    function fmtRp($n) { 
        return 'Rp ' . number_format((float)$n, 0, ',', '.'); 
    }
    function fmtNum($n) { 
        return number_format((int)$n, 0, ',', '.'); 
    }

    $totalPengguna = count(sbGet($supabaseUrl, $supabaseKey, "/rest/v1/pengguna?select=id_pengguna"));
    $totalAdmin    = count(sbGet($supabaseUrl, $supabaseKey, "/rest/v1/admin?select=id_admin"));
    $totalPenjual  = count(sbGet($supabaseUrl, $supabaseKey, "/rest/v1/penjual?select=id_penjual"));

    $langgananList = sbGet($supabaseUrl, $supabaseKey, 
        "/rest/v1/langganan?select=jumlah_bayar,created_at,penjual(nama_penjual)"
        . "&created_at=gte." . urlencode($fromTs)
        . "&created_at=lte." . urlencode($toTs));
    $totalLangganan = array_sum(array_column($langgananList, 'jumlah_bayar'));
    $jumlahLangganan = count($langgananList);

    $komisiList = sbGet($supabaseUrl, $supabaseKey, 
        "/rest/v1/komisi?select=total_komisi,created_at,penjual(nama_penjual)"
        . "&created_at=gte." . urlencode($fromTs)
        . "&created_at=lte." . urlencode($toTs));
    $totalKomisi = array_sum(array_column($komisiList, 'total_komisi'));
    $jumlahKomisi = count($komisiList);

    $sponsorList = sbGet($supabaseUrl, $supabaseKey, 
        "/rest/v1/kontribusi_sponsor?select=*,sponsor!id_sponsor(nama_sponsor)"
        . "&tanggal=gte." . urlencode($dateFrom)
        . "&tanggal=lte." . urlencode($dateTo));

    echo "<!-- DEBUG: Jumlah data sponsor: " . count($sponsorList) . " -->\n";
    foreach($sponsorList as $idx => $s){
        echo "<!-- DEBUG Data $idx: " . json_encode($s) . " -->\n";
    }

    $uniqueSponsor = [];
    foreach ($sponsorList as $k) {
        $idSponsor = $k['id_sponsor'] ?? '';
        if (!empty($idSponsor) && !in_array($idSponsor, $uniqueSponsor)) {
            $uniqueSponsor[] = $idSponsor;
        }
    }
    $totalSponsor = count($uniqueSponsor);
    $totalKontribusiSponsor = array_sum(array_column($sponsorList, 'nominal_uang'));

    $sponsorGroup = [];
    foreach ($sponsorList as $k) {
        $namaSponsor = $k['sponsor']['nama_sponsor'] ?? 'Unknown';
        $jenis = $k['jenis_kontribusi'] ?? '';
        $keterangan = $k['keterangan'] ?? '';
        
        if (!isset($sponsorGroup[$namaSponsor])) {
            $sponsorGroup[$namaSponsor] = [
                'Uang' => 0,
                'keterangan_uang' => '',
                'barang' => []
            ];
        }
        
        if ($jenis == 'Uang') {
            $sponsorGroup[$namaSponsor]['Uang'] += ($k['nominal_uang'] ?? 0);
            if (!empty($keterangan)) {
                $sponsorGroup[$namaSponsor]['keterangan_uang'] = $keterangan;
            }
        } elseif ($jenis == 'barang') {
            $sponsorGroup[$namaSponsor]['barang'][] = [
                'nama_barang' => $k['nama_barang'] ?? '-',
                'jumlah_barang' => $k['jumlah_barang'] ?? 0,
                'keterangan' => $keterangan
            ];
        }
    }

    echo "<!-- DEBUG SponsorGroup: " . json_encode($sponsorGroup) . " -->\n";

    $pengeluaranList = sbGet($supabaseUrl, $supabaseKey, 
        "/rest/v1/pengeluaran?select=jumlah,keterangan,created_at"
        . "&created_at=gte." . urlencode($fromTs)
        . "&created_at=lte." . urlencode($toTs));
    $totalPengeluaran = array_sum(array_column($pengeluaranList, 'jumlah'));
    $jumlahPengeluaran = count($pengeluaranList);

    $totalPemasukan = $totalLangganan + $totalKomisi + $totalKontribusiSponsor;
    $saldoAkhir = $totalPemasukan - $totalPengeluaran;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Keuangan ReWorth</title>
    <link rel="stylesheet" href="style/laporan.css">
</head>
<body>
    <button class="print-btn" onclick="window.print()">🖨 Cetak / Simpan PDF</button>

    <div class="report-header">
        <div class="org-name">ReWorth</div>
        <div class="report-title">Laporan Keuangan &amp; Operasional</div>
        <div class="report-period">Periode <?= $labelFrom ?> s.d <?= $labelTo ?></div>
        <div class="klasifikasi">Dicetak pada: <?= formatTanggalIndonesia(date('Y-m-d H:i:s')) ?></div>
    </div>
    <div class="divider-thick"></div>
    <div class="divider-thin" style="margin-bottom:10px;"></div>

    <div class="section-title">A. STATISTIK AKUN</div>
    <table class="summary-table">
        <tr><td class="label">Total Pengguna Terdaftar</td><td class="value"><?= fmtNum($totalPengguna) ?> orang</td></tr>
        <tr><td class="label">Total Admin</td><td class="value"><?= fmtNum($totalAdmin) ?> orang</td></tr>
        <tr><td class="label">Total Penjual</td><td class="value"><?= fmtNum($totalPenjual) ?> orang</td></tr>
    </table>
    <div class="divider-thin"></div>

    <div class="section-title">B. RINCIAN PEMASUKAN</div>
    <table class="summary-table">
        <tr><td class="label" style="font-weight:bold;">A. Biaya Langganan</td><td class="value"></td></tr>
        <tr class="indent"><td class="label">Total Langganan</td><td class="value"><?= fmtRp($totalLangganan) ?></td></tr>
        <tr class="indent"><td class="label">Jumlah Transaksi Langganan</td><td class="value"><?= fmtNum($jumlahLangganan) ?> transaksi</td></tr>
        
        <tr><td colspan="2" style="padding:2px;"></td></tr>
        <tr><td class="label" style="font-weight:bold;">B. Komisi Penjual</td><td class="value"></td></tr>
        <tr class="indent"><td class="label">Total Komisi</td><td class="value"><?= fmtRp($totalKomisi) ?></td></tr>
        <tr class="indent"><td class="label">Jumlah Transaksi Komisi</td><td class="value"><?= fmtNum($jumlahKomisi) ?> transaksi</td></tr>
        
        <tr><td colspan="2" style="padding:2px;"></td></tr>
        <tr><td class="label" style="font-weight:bold;">C. Kontribusi Sponsor</td><td class="value"></td></tr>
        <tr class="indent"><td class="label">Total Kontribusi Sponsor</td><td class="value"><?= fmtRp($totalKontribusiSponsor) ?></td></tr>
        <tr class="indent"><td class="label">Jumlah Sponsor (Unique)</td><td class="value"><?= fmtNum($totalSponsor) ?> sponsor</td></tr>
        
        <tr><td colspan="2" style="padding:4px;"></td></tr>
        <tr class="total-row"><td class="label">TOTAL PEMASUKAN</td><td class="value"><?= fmtRp($totalPemasukan) ?></td></tr>
    </table>
    <div class="divider-thin"></div>

    <div class="section-title">C. RINCIAN PENGELUARAN</div>
    <table class="summary-table">
        <tr class="indent"><td class="label">Total Pengeluaran</td><td class="value"><?= fmtRp($totalPengeluaran) ?></td></tr>
        <tr class="indent"><td class="label">Jumlah Transaksi Pengeluaran</td><td class="value"><?= fmtNum($jumlahPengeluaran) ?> transaksi</td></tr>
    </table>
    <div class="divider-thin"></div>

    <div class="section-title">D. DETAIL KONTRIBUSI SPONSOR</div>
    <?php if(!empty($sponsorList)): 
        $tempGroup = [];
        foreach($sponsorList as $s) {
            $nama = $s['sponsor']['nama_sponsor'] ?? 'Unknown';
            if(!isset($tempGroup[$nama])) {
                $tempGroup[$nama] = [];
            }
            $tempGroup[$nama][] = $s;
        }
    ?>
    <?php foreach($tempGroup as $namaSponsor => $kontribusis): ?>
        <table class="summary-table" style="margin-bottom:12px; border:1px solid #ddd;">
            <tr style="background:#e8e8e8;">
                <td colspan="2" style="font-weight:bold;">Sponsor: <?= htmlspecialchars($namaSponsor) ?></td>
            </tr>
            <?php foreach($kontribusis as $k): 
                $jenis = $k['jenis_kontribusi'] ?? '';
            ?>
                <?php if($jenis == 'Uang'): ?>
                <tr>
                    <td class="label" style="padding-left:28px;">Kontribusi Uang</td>
                    <td class="value" style="font-weight:bold; color:#000;"><?= fmtRp($k['nominal_uang'] ?? 0) ?></td>
                </tr>
                <?php if(!empty($k['keterangan'])): ?>
                <tr>
                    <td colspan="2" style="padding-left:44px; font-size:9pt; color:#555;">
                        Keterangan: <?= htmlspecialchars($k['keterangan']) ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php else: ?>
                <tr>
                    <td class="label" style="padding-left:28px;">Kontribusi Barang: <?= htmlspecialchars($k['nama_barang'] ?? '-') ?> (<?= fmtNum($k['jumlah_barang'] ?? 0) ?> pcs)</td>
                </tr>
                <?php if(!empty($k['keterangan'])): ?>
                <tr>
                    <td colspan="2" style="padding-left:44px; font-size:9pt; color:#555;">
                        Keterangan: <?= htmlspecialchars($k['keterangan']) ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </table>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="padding:8px; font-style:italic;">Tidak ada data kontribusi sponsor.</p>
    <?php endif; ?>

    <div class="section-title">E. DETAIL TRANSAKSI LANGGANAN</div>
    <table class="data-table">
        <thead><tr><th style="width:40px;">No</th><th>Nama Penjual</th><th>Jumlah Bayar</th><th>Tanggal</th></tr></thead>
        <tbody>
        <?php if(!empty($langgananList)):
            $no=1;
            foreach($langgananList as $l):
                $tgl = !empty($l['created_at']) ? formatTanggalOnly($l['created_at']) : '-';
                $namaPenjual = $l['penjual']['nama_penjual'] ?? '-';
        ?>
            <tr><td class="center"><?=$no++?></td><td><?=htmlspecialchars($namaPenjual)?></td><td class="right"><?=fmtRp($l['jumlah_bayar']??0)?></td><td class="center"><?=$tgl?></td></tr>
        <?php endforeach; else:?>
            <tr class="empty-row"><td colspan="4">Tidak ada data langganan.</td></tr>
        <?php endif;?>
        </tbody>
    </table>

    <div class="section-title">F. DETAIL TRANSAKSI KOMISI</div>
    <table class="data-table">
        <thead><tr><th style="width:40px;">No</th><th>Nama Penjual</th><th>Total Komisi</th><th>Tanggal</th></tr></thead>
        <tbody>
        <?php if(!empty($komisiList)):
            $no=1;
            foreach($komisiList as $k):
                $tgl = !empty($k['created_at']) ? formatTanggalOnly($k['created_at']) : '-';
                $namaPenjual = $k['penjual']['nama_penjual'] ?? '-';
        ?>
            <tr><td class="center"><?=$no++?></td><td><?=htmlspecialchars($namaPenjual)?></td><td class="right"><?=fmtRp($k['total_komisi']??0)?></td><td class="center"><?=$tgl?></td></tr>
        <?php endforeach; else:?>
            <tr class="empty-row"><td colspan="4">Tidak ada data komisi.</td></tr>
        <?php endif;?>
        </tbody>
    </table>

    <div class="section-title">G. DETAIL PENGELUARAN</div>
    <table class="data-table">
        <thead><tr><th style="width:40px;">No</th><th>Keterangan</th><th>Jumlah</th><th>Tanggal</th></tr></thead>
        <tbody>
        <?php if(!empty($pengeluaranList)):
            $no=1;
            foreach($pengeluaranList as $p):
                $tgl = !empty($p['created_at']) ? formatTanggalOnly($p['created_at']) : '-';
        ?>
            <tr><td class="center"><?=$no++?></td><td><?=htmlspecialchars($p['keterangan']??'-')?></td><td class="right"><?=fmtRp($p['jumlah']??0)?></td><td class="center"><?=$tgl?></td></tr>
        <?php endforeach; else:?>
            <tr class="empty-row"><td colspan="4">Tidak ada data pengeluaran.</td></tr>
        <?php endif;?>
        </tbody>
    </table>

    <div class="section-title">H. REKAP KEUANGAN</div>
    <table class="summary-table">
        <tr class="indent"><td class="label">Total Pemasukan (Langganan + Komisi + Sponsor)</td><td class="value"><?= fmtRp($totalPemasukan) ?></td></tr>
        <tr class="indent"><td class="label">Total Pengeluaran</td><td class="value"><?= fmtRp($totalPengeluaran) ?></td></tr>
        <tr class="total-row"><td class="label">SALDO AKHIR</td><td class="value"><?= fmtRp($saldoAkhir) ?></td></tr>
    </table>

    <div class="signature-section">
        <div class="sig-box">
            <div class="sig-title">Malang, <?= formatTanggalOnly(date('Y-m-d')) ?><br>Mengetahui,<br>CEO ReWorth</div>            <div class="sig-name">( ________________________ )</div>
        </div>
        <div class="sig-box">
            <div class="sig-title">Malang, <?= formatTanggalOnly(date('Y-m-d')) ?><br>Dibuat oleh,<br>Petugas/Admin</div>
            <div class="sig-name">( <?= htmlspecialchars($_SESSION['nama_admin'] ?? 'Admin') ?> )</div>
        </div>
    </div>

    <script>
        window.onload = function() {};
    </script>
</body>
</html>