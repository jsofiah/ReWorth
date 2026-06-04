<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['id_penjual'])) {
    header("Location: login.php");
    exit;
}

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

$userId = $_SESSION['id_penjual'] ?? '';
$userName = $_SESSION['nama_penjual'] ?? 'User';
$userEmail = $_SESSION['email'] ?? '';

// Filter tanggal
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

$labelFrom = date('d F Y', strtotime($dateFrom));
$labelTo = date('d F Y', strtotime($dateTo));

function curlRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true) ?? [];
}

function fmtRp($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
function fmtNum($n) { return number_format((int)$n, 0, ',', '.'); }

// ========== AMBIL DATA ==========

// Produk
$produkList = curlRequest(
    $supabaseUrl . "/rest/v1/produk?id_penjual=eq.$userId",
    'GET',
    null,
    ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
);
$totalProduk = count($produkList);

// Pesanan selesai
$pesananList = curlRequest(
    $supabaseUrl . "/rest/v1/pesanan?select=*,produk(*)&status=eq.selesai",
    'GET',
    null,
    ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
);

// Filter pesanan milik penjual & sesuai tanggal
$filteredPesanan = [];
$totalPendapatan = 0;
foreach ($pesananList as $p) {
    if ($p['produk'] && $p['produk']['id_penjual'] == $userId) {
        $tgl = substr($p['created_at'], 0, 10);
        if ($tgl >= $dateFrom && $tgl <= $dateTo) {
            $filteredPesanan[] = $p;
            $totalPendapatan += $p['total_bayar'];
        }
    }
}
$totalTransaksi = count($filteredPesanan);

// Komisi
$komisiList = curlRequest(
    $supabaseUrl . "/rest/v1/komisi?id_penjual=eq.$userId",
    'GET',
    null,
    ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
);
$totalKomisi = 0;
foreach ($komisiList as $k) {
    $tgl = substr($k['created_at'], 0, 10);
    if ($tgl >= $dateFrom && $tgl <= $dateTo) {
        $totalKomisi += $k['total_komisi'];
    }
}

$totalBersih = $totalPendapatan - $totalKomisi;

// Pendapatan per jenis produk
$produkPendapatan = [];
foreach ($filteredPesanan as $p) {
    $nama = $p['produk']['nama_produk'] ?? 'Unknown';
    $produkPendapatan[$nama] = ($produkPendapatan[$nama] ?? 0) + $p['total_bayar'];
}
arsort($produkPendapatan);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Keuangan - ReWorth</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            color: #000;
            background: #fff;
            padding: 24px 36px;
        }

        .report-header { text-align: center; margin-bottom: 6px; }
        .report-header .org-name { font-size: 13pt; font-weight: bold; text-transform: uppercase; }
        .report-header .report-title { font-size: 12pt; font-weight: bold; }
        .report-header .report-period { font-size: 10pt; }
        .divider-thick { border-top: 2.5px solid #000; margin: 4px 0; }
        .divider-thin { border-top: 1px solid #000; margin: 4px 0; }

        .section-title {
            font-weight: bold;
            font-size: 11pt;
            background: #d9d9d9;
            padding: 3px 6px;
            margin-top: 10px;
            margin-bottom: 2px;
            border: 1px solid #000;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .summary-table td {
            padding: 3px 6px;
            font-size: 10.5pt;
            vertical-align: top;
        }
        .summary-table td.label { width: 60%; }
        .summary-table td.value { width: 40%; text-align: right; }
        .summary-table tr.total-row td {
            font-weight: bold;
            border-top: 1px solid #000;
            border-bottom: 2px solid #000;
        }
        .summary-table tr.indent td.label { padding-left: 24px; }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0 10px;
            font-size: 10pt;
        }
        .data-table th {
            background: #f2f2f2;
            border: 1px solid #999;
            padding: 4px 6px;
            text-align: center;
            font-weight: bold;
        }
        .data-table td {
            border: 1px solid #ccc;
            padding: 3px 6px;
            vertical-align: top;
        }
        .data-table td.right { text-align: right; }
        .data-table td.center { text-align: center; }
        .data-table tr.total-row td {
            font-weight: bold;
            border-top: 2px solid #000;
            background: #f9f9f9;
        }
        .empty-row td { text-align: center; color: #666; font-style: italic; }

        .signature-section {
            margin-top: 32px;
            display: flex;
            justify-content: flex-end;
            gap: 60px;
        }
        .sig-box { text-align: center; }
        .sig-box .sig-title { font-size: 10pt; margin-bottom: 56px; }
        .sig-box .sig-name { font-weight: bold; font-size: 10pt; border-top: 1px solid #000; padding-top: 3px; min-width: 160px; }

        .print-btn {
            position: fixed; top: 16px; right: 16px;
            background: #00916E; color: #fff;
            border: none; border-radius: 8px;
            padding: 10px 22px; font-size: 13px;
            font-family: Arial, sans-serif; cursor: pointer;
            z-index: 999;
        }
        .print-btn:hover { background: #007a5c; }

        @media print {
            .print-btn { display: none; }
            body { padding: 10mm 14mm; }
            @page { size: A4; margin: 12mm 14mm; }
        }
        .avoid-break { page-break-inside: avoid; }
    </style>
</head>
<body>

<button class="print-btn" onclick="window.print()">🖨 Cetak / Simpan PDF</button>

<!-- HEADER -->
<div class="report-header">
    <div class="org-name">ReWorth</div>
    <div class="report-title">LAPORAN KEUANGAN PENJUAL</div>
    <div class="report-period">Periode <?= $labelFrom ?> s.d <?= $labelTo ?></div>
    <div class="report-period">Dicetak pada: <?= date('d F Y, H:i') ?> WIB</div>
</div>
<div class="divider-thick"></div>
<div class="divider-thin" style="margin-bottom:10px;"></div>

<!-- A. RINGKASAN UMUM -->
<div class="section-title">A. RINGKASAN UMUM</div>
<table class="summary-table">
    <tr class="indent"><td class="label">Nama Penjual</td><td class="value"><?= htmlspecialchars($userName) ?></td></tr>
    <tr class="indent"><td class="label">Email</td><td class="value"><?= htmlspecialchars($userEmail) ?></td></tr>
    <tr class="indent"><td class="label">Total Produk</td><td class="value"><?= fmtNum($totalProduk) ?> produk</td></tr>
    <tr class="indent"><td class="label">Total Transaksi Selesai</td><td class="value"><?= fmtNum($totalTransaksi) ?> transaksi</td></tr>
</table>
<div class="divider-thin"></div>

<!-- B. ARUS KAS -->
<div class="section-title">B. ARUS KAS PENJUAL</div>
<table class="summary-table">
    <tr><td class="label" style="padding-left:6px;font-weight:bold;">Penerimaan</td><td class="value"></td></tr>
    <tr class="indent"><td class="label">Total Pendapatan dari Pesanan</td><td class="value"><?= fmtRp($totalPendapatan) ?></td></tr>
    <tr><td colspan="2" style="padding:2px;"></td></tr>
    <tr><td class="label" style="padding-left:6px;font-weight:bold;">Potongan</td><td class="value"></td></tr>
    <tr class="indent"><td class="label">Komisi Platform (5%)</td><td class="value">(<?= fmtRp($totalKomisi) ?>)</td></tr>
    <tr class="total-row"><td class="label">TOTAL PENERIMAAN BERSIH</td><td class="value"><?= fmtRp($totalBersih) ?></td></tr>
</table>

<!-- C. DETAIL PESANAN -->
<div class="section-title avoid-break">C. DETAIL PESANAN SELESAI</div>
<table class="data-table">
    <thead>
        <tr>
            <th style="width:40px;">No</th>
            <th>Produk</th>
            <th>Total Bayar (Rp)</th>
            <th>Komisi (5%)</th>
            <th>Diterima</th>
            <th>Tanggal</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!empty($filteredPesanan)):
        $grandTotal = 0;
        $grandKomisi = 0;
        $grandDiterima = 0;
        foreach ($filteredPesanan as $idx => $p):
            $komisiItem = $p['total_bayar'] * 0.05;
            $diterima = $p['total_bayar'] - $komisiItem;
            $grandTotal += $p['total_bayar'];
            $grandKomisi += $komisiItem;
            $grandDiterima += $diterima;
    ?>
        <tr>
            <td class="center"><?= $idx+1 ?></td>
            <td><?= htmlspecialchars($p['produk']['nama_produk'] ?? '-') ?></td>
            <td class="right"><?= fmtRp($p['total_bayar']) ?></td>
            <td class="right"><?= fmtRp($komisiItem) ?></td>
            <td class="right"><?= fmtRp($diterima) ?></td>
            <td class="center"><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
        </tr>
    <?php endforeach; ?>
        <tr class="total-row">
            <td colspan="2" style="text-align:right;padding-right:8px;">TOTAL</td>
            <td class="right"><?= fmtRp($grandTotal) ?></td>
            <td class="right"><?= fmtRp($grandKomisi) ?></td>
            <td class="right"><?= fmtRp($grandDiterima) ?></td>
            <td></td>
        </tr>
    <?php else: ?>
        <tr class="empty-row"><td colspan="6">Tidak ada transaksi pada periode ini.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<!-- D. REKAP PRODUK TERLARIS -->
<div class="section-title avoid-break">D. PRODUK TERLARIS (BERDASARKAN PENDAPATAN)</div>
<table class="data-table">
    <thead>
        <tr>
            <th style="width:40px;">No</th>
            <th>Nama Produk</th>
            <th>Total Pendapatan (Rp)</th>
            <th>Persentase</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!empty($produkPendapatan)):
        $grandPendapatan = array_sum($produkPendapatan);
        $no = 1;
        foreach ($produkPendapatan as $nama => $total):
            $pct = $grandPendapatan > 0 ? round(($total / $grandPendapatan) * 100, 1) : 0;
    ?>
        <tr>
            <td class="center"><?= $no++ ?></td>
            <td><?= htmlspecialchars($nama) ?></td>
            <td class="right"><?= fmtRp($total) ?></td>
            <td class="center"><?= $pct ?>%</td>
        </tr>
    <?php endforeach; ?>
        <tr class="total-row">
            <td colspan="2" style="text-align:right;padding-right:8px;">TOTAL</td>
            <td class="right"><?= fmtRp($grandPendapatan) ?></td>
            <td class="center">100%</td>
        </tr>
    <?php else: ?>
        <tr class="empty-row"><td colspan="4">Belum ada data produk terjual.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<!-- REKAP AKHIR -->
<div class="divider-thick" style="margin-top:12px;"></div>
<table class="summary-table" style="margin-top:8px;">
    <tr class="indent"><td class="label">Total Pendapatan Kotor</td><td class="value"><?= fmtRp($totalPendapatan) ?></td></tr>
    <tr class="indent"><td class="label">Total Komisi (5%)</td><td class="value">(<?= fmtRp($totalKomisi) ?>)</td></tr>
    <tr class="total-row"><td class="label">TOTAL PENDAPATAN BERSIH</td><td class="value"><?= fmtRp($totalBersih) ?></td></tr>
</table>

<!-- TANDA TANGAN -->
<div class="signature-section">
    <div class="sig-box">
        <div class="sig-title">Malang, <?= date('d F Y') ?><br>Mengetahui,<br>Penjual</div>
        <div class="sig-name">( <?= htmlspecialchars($userName) ?> )</div>
    </div>
    <div class="sig-box">
        <div class="sig-title">Malang, <?= date('d F Y') ?><br>Dibuat oleh,<br>Sistem</div>
        <div class="sig-name">( ReWorth System )</div>
    </div>
</div>

<script>
    window.onload = function() {
        // Tidak auto print, biar user klik tombol sendiri
    };
</script>
</body>
</html>
