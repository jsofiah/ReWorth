<?php
session_start();
if (!isset($_SESSION['role'])) { header("Location: ../login.php"); exit; }

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-t');
$fromTs   = $dateFrom . 'T00:00:00';
$toTs     = $dateTo   . 'T23:59:59';

$labelFrom = date('d F Y', strtotime($dateFrom));
$labelTo   = date('d F Y', strtotime($dateTo));

function sbGet($url,$key,$ep){
    $ch=curl_init($url.$ep);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>["apikey:$key","Authorization:Bearer $key","Content-Type:application/json"]]);
    $r=curl_exec($ch);$c=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    return $c===200?(json_decode($r,true)?:[]):[];
}
function fmtRp($n){return 'Rp '.number_format((float)$n,0,',','.');}
function fmtKg($n){return number_format((float)$n,1,',','.').' kg';}

/* ══ FETCH ALL DATA ══ */

// Nasabah
$totalNasabah = count(sbGet($supabaseUrl,$supabaseKey,"/rest/v1/pengguna?select=id_pengguna"));

// Setor sampah + detail + pengguna + jadwal
$setorList = sbGet($supabaseUrl,$supabaseKey,
    "/rest/v1/setor_sampah?select=id_setor,total_uang,status,created_at,"
    ."pengguna(nama_lengkap),jadwal_ambil(tanggal,waktu_mulai,waktu_selesai),"
    ."detail_setor(berat,harga_per_kg,subtotal,jenis_sampah(nama_sampah))"
    ."&created_at=gte=".urlencode($fromTs)
    ."&created_at=lte=".urlencode($toTs)
    ."&order=created_at.asc");

$totalNilaiSetor = 0;
$beratTerkumpul  = 0;
$jenisTotals     = [];
$jenisBerat      = [];

foreach($setorList as $s){
    $totalNilaiSetor += (float)($s['total_uang']??0);
    foreach($s['detail_setor']??[] as $d){
        $beratTerkumpul += (float)($d['berat']??0);
        $nm = $d['jenis_sampah']['nama_sampah']??'Lainnya';
        $jenisBerat[$nm]  = ($jenisBerat[$nm]??0)  + (float)($d['berat']??0);
        $jenisTotals[$nm] = ($jenisTotals[$nm]??0) + (float)($d['subtotal']??0);
    }
}
arsort($jenisBerat);

// Penarikan saldo dalam periode
$penarikanList = sbGet($supabaseUrl,$supabaseKey,
    "/rest/v1/penarikan_saldo?select=jumlah,created_at,pengguna(nama_lengkap)"
    ."&created_at=gte=".urlencode($fromTs)
    ."&created_at=lte=".urlencode($toTs)
    ."&order=created_at.asc");
$totalPenarikan = array_sum(array_column($penarikanList,'jumlah'));

// Event aktif
$eventList = sbGet($supabaseUrl,$supabaseKey,
    "/rest/v1/event?select=nama_event,tanggal,status,max_partisipan");
$eventAktifCount = count(array_filter($eventList,fn($e)=>in_array($e['status']??'',['berlangsung','akan_datang'])));
$totalPendaftar  = count(sbGet($supabaseUrl,$supabaseKey,"/rest/v1/pendaftar_event?select=id_pendaftar_event"));

// Tukar poin
$tukarList  = sbGet($supabaseUrl,$supabaseKey,"/rest/v1/tukar_poin?select=id_tukar,reward(nama_reward,poin_dibutuhkan)");
$totalPoin  = 0;
foreach($tukarList as $t){ $totalPoin += (int)($t['reward']['poin_dibutuhkan']??0); }

$saldoBersih = $totalNilaiSetor - $totalPenarikan;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Keuangan Bank Sampah</title>
<style>

* { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Times New Roman', Times, serif;
    font-size: 11pt;
    color: #000;
    background: #fff;
    padding: 24px 36px;
  }

  /* ── HEADER ── */
  .report-header { text-align: center; margin-bottom: 6px; }
  .report-header .org-name { font-size: 13pt; font-weight: bold; text-transform: uppercase; }
  .report-header .report-title { font-size: 12pt; font-weight: bold; }
  .report-header .report-period { font-size: 10pt; }
  .report-header .klasifikasi { font-size: 10pt; }
  .divider-thick { border-top: 2.5px solid #000; margin: 4px 0; }
  .divider-thin  { border-top: 1px solid #000;   margin: 4px 0; }

  /* ── SECTIONS ── */
  .section-title {
    font-weight: bold;
    font-size: 11pt;
    background: #d9d9d9;
    padding: 3px 6px;
    margin-top: 10px;
    margin-bottom: 2px;
    border: 1px solid #000;
  }

  /* ── SUMMARY TABLE ── */
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
  .summary-table tr.subtotal td { font-weight: bold; }
  .summary-table tr.indent td.label { padding-left: 24px; }

  /* ── DATA TABLE ── */
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

  /* ── EMPTY ── */
  .empty-row td { text-align: center; color: #666; font-style: italic; }

  /* ── SIGNATURE ── */
  .signature-section {
    margin-top: 32px;
    display: flex;
    justify-content: flex-end;
    gap: 60px;
  }
  .sig-box { text-align: center; }
  .sig-box .sig-title { font-size: 10pt; margin-bottom: 56px; }
  .sig-box .sig-name  { font-weight: bold; font-size: 10pt; border-top: 1px solid #000; padding-top: 3px; min-width: 160px; }

  /* ── NO PRINT: print button ── */
  .print-btn {
    position: fixed; top: 16px; right: 16px;
    background: #00916E; color: #fff;
    border: none; border-radius: 8px;
    padding: 10px 22px; font-size: 13px;
    font-family: Arial, sans-serif; cursor: pointer;
    box-shadow: 0 3px 10px rgba(0,145,110,.35);
    z-index: 999;
  }
  .print-btn:hover { background: #007a5c; }

  @media print {
    .print-btn { display: none; }
    body { padding: 10mm 14mm; }
    @page { size: A4; margin: 12mm 14mm; }
  }

  .page-break { page-break-before: always; }
  .avoid-break { page-break-inside: avoid; }
</style>

</head>
<body>

<button class="print-btn" onclick="window.print()">🖨 Cetak / Simpan PDF</button>

<!-- ══ HEADER ══ -->
<div class="report-header">
    <div class="org-name">Bank Sampah ReWorth</div>
    <div class="report-title">Laporan Keuangan &amp; Operasional</div>
    <div class="report-period">Periode <?= $labelFrom ?> s.d <?= $labelTo ?></div>
    <div class="klasifikasi">Dicetak pada: <?= date('d F Y, H:i') ?> WIB</div>
</div>
<div class="divider-thick"></div>
<div class="divider-thin" style="margin-bottom:10px;"></div>


<!-- ══ A. RINGKASAN UMUM ══ -->
<div class="section-title">A. RINGKASAN UMUM</div>
<table class="summary-table">
    <tr class="indent"><td class="label">Total Nasabah Terdaftar</td><td class="value"><?= number_format($totalNasabah,0,',','.') ?> nasabah</td></tr>
    <tr class="indent"><td class="label">Total Transaksi Setor Sampah</td><td class="value"><?= number_format(count($setorList),0,',','.') ?> transaksi</td></tr>
    <tr class="indent"><td class="label">Total Berat Sampah Terkumpul</td><td class="value"><?= fmtKg($beratTerkumpul) ?></td></tr>
    <tr class="indent"><td class="label">Event Aktif</td><td class="value"><?= $eventAktifCount ?> event</td></tr>
    <tr class="indent"><td class="label">Total Pendaftar Event</td><td class="value"><?= number_format($totalPendaftar,0,',','.') ?> orang</td></tr>
</table>
<div class="divider-thin"></div>


<!-- ══ B. ARUS KAS OPERASIONAL ══ -->
<div class="section-title">B. ARUS KAS OPERASIONAL</div>
<table class="summary-table">
    <tr><td class="label" style="padding-left:6px;font-weight:bold;">Penerimaan dari Setor Sampah</td><td class="value"></td></tr>
    <?php
    arsort($jenisTotals);
    foreach($jenisTotals as $nm=>$total):
    ?>
    <tr class="indent"><td class="label">– <?= htmlspecialchars($nm) ?></td><td class="value"><?= fmtRp($total) ?></td></tr>
    <?php endforeach; if(empty($jenisTotals)):?>
    <tr class="indent"><td class="label">– (Belum ada data)</td><td class="value">Rp 0</td></tr>
    <?php endif;?>
    <tr class="subtotal"><td class="label" style="padding-left:6px;">Total Penerimaan dari Setor Sampah</td><td class="value"><?= fmtRp($totalNilaiSetor) ?></td></tr>
    <tr><td colspan="2" style="padding:2px;"></td></tr>
    <tr><td class="label" style="padding-left:6px;font-weight:bold;">Pengeluaran (Penarikan Saldo Nasabah)</td><td class="value"></td></tr>
    <tr class="indent"><td class="label">– Total Penarikan Saldo</td><td class="value">(<?= fmtRp($totalPenarikan) ?>)</td></tr>
    <tr class="total-row"><td class="label">SALDO KAS BERSIH OPERASIONAL</td><td class="value"><?= fmtRp($saldoBersih) ?></td></tr>
</table>


<!-- ══ C. DETAIL TRANSAKSI SETOR SAMPAH ══ -->
<div class="section-title avoid-break">C. DETAIL TRANSAKSI SETOR SAMPAH</div>
<table class="data-table">
    <thead>
        <tr>
            <th style="width:40px;">No</th>
            <th>Tanggal</th>
            <th>Nama Nasabah</th>
            <th>Jadwal Pengambilan</th>
            <th>Status</th>
            <th>Total (Rp)</th>
        </tr>
    </thead>
    <tbody>
    <?php if(!empty($setorList)):
        $grandSetor=0;
        foreach($setorList as $idx=>$s):
            $tgl = !empty($s['created_at']) ? date('d/m/Y',strtotime($s['created_at'])) : '-';
            $jadwal = '';
            if(!empty($s['jadwal_ambil']['tanggal'])){
                $jadwal = date('d M Y',strtotime($s['jadwal_ambil']['tanggal']))
                    .' '.substr($s['jadwal_ambil']['waktu_mulai']??'',0,5)
                    .'–'.substr($s['jadwal_ambil']['waktu_selesai']??'',0,5);
            }
            $statusMap=['menunggu'=>'Menunggu','diproses'=>'Diproses','selesai'=>'Selesai','ditolak'=>'Ditolak'];
            $stLabel=$statusMap[$s['status']??'']??ucfirst($s['status']??'-');
            $grandSetor += (float)($s['total_uang']??0);
    ?>
        <tr>
            <td class="center"><?=$idx+1?></td>
            <td class="center"><?=$tgl?></td>
            <td><?=htmlspecialchars($s['pengguna']['nama_lengkap']??'-')?></td>
            <td><?=htmlspecialchars($jadwal)?></td>
            <td class="center"><?=$stLabel?></td>
            <td class="right"><?=fmtRp($s['total_uang']??0)?></td>
        </tr>
    <?php endforeach;?>
        <tr class="total-row">
            <td colspan="5" style="text-align:right;padding-right:8px;">TOTAL</td>
            <td class="right"><?=fmtRp($grandSetor)?></td>
        </tr>
    <?php else:?>
        <tr class="empty-row"><td colspan="6">Tidak ada transaksi pada periode ini.</td></tr>
    <?php endif;?>
    </tbody>
</table>


<!-- ══ D. REKAPITULASI JENIS SAMPAH ══ -->
<div class="section-title avoid-break">D. REKAPITULASI JENIS SAMPAH TERKUMPUL</div>
<table class="data-table">
    <thead>
        <tr>
            <th style="width:40px;">No</th>
            <th>Jenis Sampah</th>
            <th>Berat Terkumpul (kg)</th>
            <th>Total Nilai (Rp)</th>
            <th>Persentase</th>
        </tr>
    </thead>
    <tbody>
    <?php if(!empty($jenisBerat)):
        $grandBerat=array_sum($jenisBerat);
        $grandVal=array_sum($jenisTotals);
        $no=1;
        foreach($jenisBerat as $nm=>$brt):
            $pct = $grandBerat>0 ? round(($brt/$grandBerat)*100,1) : 0;
    ?>
        <tr>
            <td class="center"><?=$no++?></td>
            <td><?=htmlspecialchars($nm)?></td>
            <td class="right"><?=number_format($brt,1,',','.')?> kg</td>
            <td class="right"><?=fmtRp($jenisTotals[$nm]??0)?></td>
            <td class="center"><?=$pct?>%</td>
        </tr>
    <?php endforeach;?>
        <tr class="total-row">
            <td colspan="2" style="text-align:right;padding-right:8px;">TOTAL</td>
            <td class="right"><?=number_format($grandBerat,1,',','.')?> kg</td>
            <td class="right"><?=fmtRp($grandVal)?></td>
            <td class="center">100%</td>
        </tr>
    <?php else:?>
        <tr class="empty-row"><td colspan="5">Tidak ada data sampah pada periode ini.</td></tr>
    <?php endif;?>
    </tbody>
</table>


<!-- ══ E. PENARIKAN SALDO ══ -->
<div class="section-title avoid-break">E. PENARIKAN SALDO NASABAH</div>
<table class="data-table">
    <thead>
        <tr>
            <th style="width:40px;">No</th>
            <th>Tanggal</th>
            <th>Nama Nasabah</th>
            <th>Jumlah Penarikan (Rp)</th>
        </tr>
    </thead>
    <tbody>
    <?php if(!empty($penarikanList)):
        $grandTarik=0;
        foreach($penarikanList as $idx=>$p):
            $tgl=!empty($p['created_at'])?date('d/m/Y',strtotime($p['created_at'])):'-';
            $grandTarik+=(float)($p['jumlah']??0);
    ?>
        <tr>
            <td class="center"><?=$idx+1?></td>
            <td class="center"><?=$tgl?></td>
            <td><?=htmlspecialchars($p['pengguna']['nama_lengkap']??'-')?></td>
            <td class="right">(<?=fmtRp($p['jumlah']??0)?>)</td>
        </tr>
    <?php endforeach;?>
        <tr class="total-row">
            <td colspan="3" style="text-align:right;padding-right:8px;">TOTAL PENARIKAN</td>
            <td class="right">(<?=fmtRp($grandTarik)?>)</td>
        </tr>
    <?php else:?>
        <tr class="empty-row"><td colspan="4">Tidak ada penarikan saldo pada periode ini.</td></tr>
    <?php endif;?>
    </tbody>
</table>


<!-- ══ F. RINGKASAN POIN ══ -->
<div class="section-title avoid-break">F. RINGKASAN POIN NASABAH</div>
<table class="summary-table">
    <tr class="indent"><td class="label">Total Poin Ditukar (Tukar Reward)</td><td class="value"><?= number_format($totalPoin,0,',','.') ?> poin</td></tr>
    <tr class="indent"><td class="label">Jumlah Penukaran Reward</td><td class="value"><?= number_format(count($tukarList),0,',','.') ?> kali</td></tr>
</table>
<div class="divider-thick" style="margin-top:12px;"></div>


<!-- ══ REKAP AKHIR ══ -->
<table class="summary-table" style="margin-top:8px;">
    <tr class="indent"><td class="label">Total Penerimaan Setor Sampah</td><td class="value"><?= fmtRp($totalNilaiSetor) ?></td></tr>
    <tr class="indent"><td class="label">Total Penarikan Saldo Nasabah</td><td class="value">(<?= fmtRp($totalPenarikan) ?>)</td></tr>
    <tr class="total-row"><td class="label">SALDO KAS BERSIH PERIODE INI</td><td class="value"><?= fmtRp($saldoBersih) ?></td></tr>
</table>


<!-- ══ TANDA TANGAN ══ -->
<div class="signature-section">
    <div class="sig-box">
        <div class="sig-title">Malang, <?= date('d F Y') ?><br>Mengetahui,<br>Kepala Unit Bank Sampah</div>
        <div class="sig-name">( ________________________ )</div>
    </div>
    <div class="sig-box">
        <div class="sig-title">Malang, <?= date('d F Y') ?><br>Dibuat oleh,<br>Petugas/Admin</div>
        <div class="sig-name">( <?= htmlspecialchars($_SESSION['nama_admin']??'') ?> )</div>
    </div>
</div>

<script>
// Auto print setelah chart selesai render (sedikit delay)
window.onload = function() {
    // Tidak auto-print, biarkan user klik tombol
    // Uncomment baris di bawah jika mau auto-print:
    // setTimeout(() => window.print(), 800);
};
</script>
</body>
</html>