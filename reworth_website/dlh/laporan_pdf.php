<?php
session_start();
if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit;
}

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-t');
$fromTs   = $dateFrom . 'T00:00:00';
$toTs     = $dateTo   . 'T23:59:59';

$labelFrom = date('d F Y', strtotime($dateFrom));
$labelTo   = date('d F Y', strtotime($dateTo));

function sbGet($url, $key, $ep)
{
    $ch = curl_init($url . $ep);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["apikey: $key", "Authorization: Bearer $key", "Content-Type: application/json"]
    ]);
    $r = curl_exec($ch);
    $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $c === 200 ? (json_decode($r, true) ?: []) : [];
}

$allLaporan = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/lapor_sampah?select=id_laporan");
$totalLaporan = count($allLaporan);

$laporanBaru = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/lapor_sampah?select=id_laporan&status=eq.menunggu");
$totalBaru = count($laporanBaru);

$laporanSelesai = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/lapor_sampah?select=id_laporan&status=eq.selesai&created_at=gte." . urlencode($fromTs) . "&created_at=lte." . urlencode($toTs));
$totalSelesai = count($laporanSelesai);
$persenSelesai = $totalLaporan > 0 ? round(($totalSelesai / $totalLaporan) * 100, 1) : 0;

$laporanDitolak = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/lapor_sampah?select=id_laporan&status=eq.ditolak");
$totalDitolak = count($laporanDitolak);
$persenDitolak = $totalLaporan > 0 ? round(($totalDitolak / $totalLaporan) * 100, 1) : 0;

$statusMenunggu = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/lapor_sampah?select=id_laporan&status=eq.menunggu");
$statusDiproses = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/lapor_sampah?select=id_laporan&status=eq.diproses");
$statusSelesaiCount = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/lapor_sampah?select=id_laporan&status=eq.selesai");
$statusDitolakCount = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/lapor_sampah?select=id_laporan&status=eq.ditolak");

$totalSemua = count($statusMenunggu) + count($statusDiproses) + count($statusSelesaiCount) + count($statusDitolakCount);
$persenSelesaiTotal = $totalSemua > 0 ? round((count($statusSelesaiCount) / $totalSemua) * 100) : 0;
$persenDiprosesTotal = $totalSemua > 0 ? round((count($statusDiproses) / $totalSemua) * 100) : 0;
$persenMenungguTotal = $totalSemua > 0 ? round((count($statusMenunggu) / $totalSemua) * 100) : 0;
$persenDitolakTotal = $totalSemua > 0 ? round((count($statusDitolakCount) / $totalSemua) * 100) : 0;

$laporanList = sbGet($supabaseUrl, $supabaseKey, 
    "/rest/v1/lapor_sampah?select=*,pengguna(nama_lengkap),petugas_lapangan(nama_petugas)"
    . "&created_at=gte=" . urlencode($fromTs) 
    . "&created_at=lte=" . urlencode($toTs) 
    . "&order=created_at.desc");

$statusMap = ['menunggu' => 'Menunggu', 'diproses' => 'Diproses', 'selesai' => 'Selesai', 'ditolak' => 'Ditolak'];

function formatNumber($n) { return number_format($n, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan DLH ReWorth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link rel="stylesheet" href="style/form.css">
</head>
<body class="laporan-pdf">

<div class="pdf-container">
    <button class="print-btn" onclick="window.print()">
        <i class="bi bi-printer-fill"></i> Cetak / Simpan PDF
    </button>

    <div class="report-header">
        <div class="org-name">DINAS LINGKUNGAN HIDUP (DLH)</div>
        <div class="org-sub">KOTA MALANG</div>
        <div class="report-title">LAPORAN DAN ANALITIK</div>
        <div class="report-period">Periode <?= $labelFrom ?> s.d <?= $labelTo ?></div>
        <div class="klasifikasi">Dicetak pada: <?= date('d F Y, H:i') ?> WIB</div>
    </div>
    <div class="divider-thick"></div>
    <div class="divider-thin" style="margin-bottom:10px;"></div>

    <div class="section-title">A. RINGKASAN UMUM</div>
    <table class="summary-table">
        <tr class="indent"><td class="label">Total Laporan Terdaftar</td><td class="value"><?= formatNumber($totalLaporan) ?> laporan</td></tr>
        <tr class="indent"><td class="label">Laporan Baru (Menunggu Konfirmasi)</td><td class="value"><?= formatNumber($totalBaru) ?> laporan</td></tr>
        <tr class="indent"><td class="label">Laporan Selesai Ditangani</td><td class="value"><?= formatNumber($totalSelesai) ?> laporan (<?= $persenSelesai ?>%)</td></tr>
        <tr class="indent"><td class="label">Laporan Ditolak</td><td class="value"><?= formatNumber($totalDitolak) ?> laporan (<?= $persenDitolak ?>%)</td></tr>
    </table>
    <div class="divider-thin"></div>

    <div class="section-title">B. STATUS LAPORAN</div>
    <table class="summary-table">
        <tr class="indent"><td class="label">Selesai</td><td class="value"><?= $persenSelesaiTotal ?>%</td></tr>
        <tr class="indent"><td class="label">Diproses</td><td class="value"><?= $persenDiprosesTotal ?>%</td></tr>
        <tr class="indent"><td class="label">Menunggu</td><td class="value"><?= $persenMenungguTotal ?>%</td></tr>
        <tr class="indent"><td class="label">Ditolak</td><td class="value"><?= $persenDitolakTotal ?>%</td></tr>
    </table>
    <div class="divider-thin"></div>

    <div class="section-title">C. DETAIL LAPORAN MASUK</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:40px;">No</th>
                <th>Tanggal Lapor</th>
                <th>Nama Pelapor</th>
                <th>Jenis Sampah</th>
                <th>Lokasi</th>
                <th>Petugas</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($laporanList)): ?>
                <?php $no = 1; foreach ($laporanList as $lp): ?>
                <tr>
                    <td class="center"><?= $no++ ?></td>
                    <td class="center"><?= date('d/m/Y', strtotime($lp['created_at'])) ?></td>
                    <td><?= htmlspecialchars($lp['pengguna']['nama_lengkap'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($lp['jenis_sampah'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($lp['lokasi'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($lp['petugas_lapangan']['nama_petugas'] ?? '-') ?></td>
                    <td class="center"><?= $statusMap[$lp['status']] ?? ucfirst($lp['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr class="empty-row"><td colspan="7">Tidak ada laporan pada periode ini.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="signature-section">
        <div class="sig-box">
            <div class="sig-title">Malang, <?= date('d F Y') ?><br>Mengetahui,<br>Kepala DLH Kota Malang</div>
            <div class="sig-name">( ________________________ )</div>
        </div>
        <div class="sig-box">
            <div class="sig-title">Malang, <?= date('d F Y') ?><br>Dibuat oleh,<br>Petugas/Admin</div>
            <div class="sig-name">( <?= htmlspecialchars($_SESSION['nama_admin'] ?? 'Admin') ?> )</div>
        </div>
    </div>
</div>

</body>
</html>