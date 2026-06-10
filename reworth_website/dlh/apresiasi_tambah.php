<?php
    require_once 'role_check.php';

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $userName  = $_SESSION['nama_admin']  ?? 'User';
    $userEmail = $_SESSION['email']       ?? 'user@example.com';
    $userRole  = $_SESSION['role']        ?? '';
    $userFoto  = $_SESSION['foto_profil'] ?? '';

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
    }

    function supabaseGet($url, $key) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["apikey: $key", "Authorization: Bearer $key", "Content-Type: application/json"]);
        $res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        return ($code === 200 && $res) ? (json_decode($res, true) ?: []) : [];
    }


    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');

        $judul      = trim($_POST['judul_apresiasi'] ?? '');
        $id_wilayah = trim($_POST['id_wilayah']     ?? '');
        $periode    = trim($_POST['periode']         ?? '');
        $deskripsi  = trim($_POST['deskripsi']       ?? '');

        if (empty($judul) || empty($id_wilayah) || empty($periode)) {
            echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi']);
            exit;
        }

        $data = [
            'judul_apresiasi' => $judul,
            'id_wilayah'      => $id_wilayah,
            'periode'         => $periode,
            'deskripsi'       => $deskripsi,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $supabaseUrl . "/rest/v1/apresiasi");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json",
            "Prefer: return=minimal"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200 || $code === 201) {
            $wilayahUrl = $supabaseUrl . "/rest/v1/wilayah?id_wilayah=eq." . urlencode($id_wilayah);
            $wilayahData = supabaseGet($wilayahUrl, $supabaseKey);
            $namaWilayah = '';
            if (!empty($wilayahData)) {
                $kec = $wilayahData[0]['kecamatan'] ?? '';
                $kel = $wilayahData[0]['kelurahan'] ?? '';
                $rw = $wilayahData[0]['rw'] ?? '';
                $namaWilayah = "Kec. $kec, Kel. $kel, RW " . str_pad($rw, 2, '0', STR_PAD_LEFT);
            }

            $getUrl = $supabaseUrl . "/rest/v1/apresiasi?select=id_apresiasi&order=created_at.desc&limit=1";
            $getResult = supabaseGet($getUrl, $supabaseKey);
            $newId = $getResult[0]['id_apresiasi'] ?? null;

            $logData = [
                'id_admin' => $_SESSION['id_admin'] ?? '',
                'aktivitas' => 'Menambahkan apresiasi: ' . $judul . ' - ' . $namaWilayah . ' (' . $periode . ')',
                'tabel_terkait' => 'apresiasi',
                'id_data' => $newId,
                'created_at' => date('c')
            ];

            $logCh = curl_init($supabaseUrl . "/rest/v1/log_admin");
            curl_setopt($logCh, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($logCh, CURLOPT_POSTFIELDS, json_encode($logData));
            curl_setopt($logCh, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: application/json",
                "Prefer: return=representation"
            ]);
            curl_setopt($logCh, CURLOPT_RETURNTRANSFER, true);
            $logResponse = curl_exec($logCh);
            $logHttpCode = curl_getinfo($logCh, CURLINFO_HTTP_CODE);
            curl_close($logCh);

            $getKetuaUrl = $supabaseUrl . "/rest/v1/wilayah?id_wilayah=eq." . urlencode($id_wilayah) . "&select=id_ketua_rw";
            $ketuaResult = supabaseGet($getKetuaUrl, $supabaseKey);
            $idKetuaRW = $ketuaResult[0]['id_ketua_rw'] ?? null;

            if ($idKetuaRW) {

                $notifData = [
                    'id_pengguna' => $idKetuaRW,
                    'judul' => 'Selamat! RW Anda Mendapat Apresiasi',
                    'deskripsi' => 'Apresiasi telah diberikan untuk periode ' . $periode . '. Silakan datang ke Kantor DLH untuk informasi lebih lanjut.',
                    'is_read' => false,
                    'created_at' => date('c')
                ];

                $notifCh = curl_init($supabaseUrl . "/rest/v1/notifikasi");
                curl_setopt($notifCh, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($notifCh, CURLOPT_POSTFIELDS, json_encode($notifData));
                curl_setopt($notifCh, CURLOPT_HTTPHEADER, [
                    "apikey: $supabaseKey",
                    "Authorization: Bearer $supabaseKey",
                    "Content-Type: application/json",
                    "Prefer: return=minimal"
                ]);
                curl_setopt($notifCh, CURLOPT_RETURNTRANSFER, true);
                $notifResponse = curl_exec($notifCh);
                $notifHttpCode = curl_getinfo($notifCh, CURLINFO_HTTP_CODE);
                curl_close($notifCh);


                $fcmPayload = [
                    'user_id' => $idKetuaRW,
                    'title' => 'Selamat! RW Anda Mendapat Apresiasi',
                    'body' => 'Apresiasi telah diberikan untuk periode ' . $periode . '. Silakan datang ke Kantor DLH untuk informasi lebih lanjut.'
                ];

                $chFcm = curl_init();
                curl_setopt($chFcm, CURLOPT_URL, "https://rxzrbyqqhkxemdjbcntc.supabase.co/functions/v1/send-user-notification");
                curl_setopt($chFcm, CURLOPT_POST, true);
                curl_setopt($chFcm, CURLOPT_POSTFIELDS, json_encode($fcmPayload));
                curl_setopt($chFcm, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer $supabaseKey",
                    "apikey: $supabaseKey",
                    "Content-Type: application/json"
                ]);
                curl_setopt($chFcm, CURLOPT_RETURNTRANSFER, true);
                $fcmResponse = curl_exec($chFcm);
                $fcmHttpCode = curl_getinfo($chFcm, CURLINFO_HTTP_CODE);
                $fcmError = curl_error($chFcm);
                curl_close($chFcm);
            }

            echo json_encode([
                'success' => true, 
                'message' => 'Apresiasi berhasil ditambahkan!',
                'debug_log' => [
                    'new_id' => $newId,
                    'log_http_code' => $logHttpCode,
                    'log_response' => $logResponse,
                    'ketua_rw_id' => $idKetuaRW,
                    'notif_http_code' => $notifHttpCode,
                    'notif_response' => $notifResponse,
                    'fcm_http_code' => $fcmHttpCode,
                    'fcm_response' => json_decode($fcmResponse ?? '{}', true)
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data', 'debug' => $res]);
        }
        exit;
    }


    $wilayahList = supabaseGet($supabaseUrl . "/rest/v1/wilayah?select=*&order=kecamatan.asc", $supabaseKey);


    $grouped = [];
    foreach ($wilayahList as $w) {
        $kec = $w['kecamatan'] ?? 'Lainnya';
        $kel = $w['kelurahan'] ?? 'Lainnya';
        $grouped[$kec][$kel][] = $w;
    }
    ksort($grouped);


    $preselect = $_GET['id_wilayah'] ?? '';


    $defaultPeriode = date('F Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DLH – Tambah Apresiasi RW</title>
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
        <div class="nav-item"><a href="dashboard.php" class="nav-link-custom"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></div>
        <div class="nav-item"><a href="laporan_sampah.php" class="nav-link-custom"><i class="bi bi-exclamation-diamond-fill"></i><span>Laporan Sampah</span></a></div>
        <div class="nav-item"><a href="apresiasi_rw.php" class="nav-link-custom active"><i class="bi bi-award-fill"></i><span>Apresiasi RW</span></a></div>
        <div class="nav-item"><a href="event_lingkungan.php" class="nav-link-custom"><i class="bi bi-calendar-event-fill"></i><span>Event Lingkungan</span></a></div>
        <div class="nav-item"><a href="laporan_analitik.php" class="nav-link-custom"><i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Analitik</span></a></div>
        <div class="nav-item"><a href="data_petugas.php" class="nav-link-custom"><i class="bi bi-people-fill"></i><span>Data Petugas</span></a></div>
        <div class="nav-item"><a href="pengaturan_akun.php" class="nav-link-custom"><i class="bi bi-gear-fill"></i><span>Pengaturan Akun</span></a></div>
    </nav>
    <div class="sidebar-logout">
        <a class="logout-btn" href="../logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
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
                    <?php if (!empty($userFoto)): ?>
                        <img src="<?= htmlspecialchars(getSupabaseImageUrl($userFoto)) ?>"
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

    <div class="content-area">
    <div class="form-container">
        <div class="form-section">
            <div class="inside-header">
                <h2>Apresiasi RW</h2>
            </div>

            <form id="formApresiasi">
                
                <div class="form-group">
                    <label class="form-label">JUDUL APRESIASI</label>
                    <input type="text" class="form-control-custom" id="judul_apresiasi" name="judul_apresiasi"
                        placeholder="Masukkan judul" required>
                </div>

                
                <div class="row-2cols">
                    <div class="form-group">
                        <label class="form-label">WILAYAH KECAMATAN</label>
                        <select class="form-control-custom" id="selectKecamatan" onchange="updateKelurahan()">
                            <option value="">-- Pilih Kecamatan --</option>
                            <?php foreach ($grouped as $kec => $kels): ?>
                                <option value="<?= htmlspecialchars($kec) ?>"><?= htmlspecialchars($kec) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">WILAYAH KELURAHAN</label>
                        <select class="form-control-custom" id="selectKelurahan" onchange="updateRW()" disabled>
                            <option value="">-- Pilih Kelurahan --</option>
                        </select>
                    </div>
                </div>

                
                <div class="row-2cols">
                    <div class="form-group">
                        <label class="form-label">WILAYAH RW</label>
                        <select class="form-control-custom" id="selectRW" name="id_wilayah" disabled>
                            <option value="">-- Pilih RW --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">PERIODE</label>
                        <input type="text" class="form-control-custom" id="periode" name="periode"
                            value="<?= htmlspecialchars($defaultPeriode) ?>" placeholder="Contoh: April 2026" required>
                    </div>
                </div>

                
                <div class="form-group">
                    <label class="form-label">DESKRIPSI APRESIASI</label>
                    <textarea class="form-control-custom" id="deskripsi" name="deskripsi" rows="4"
                        placeholder="Jelaskan alasan dan pencapaian yang membuat RW ini layak mendapat apresiasi"></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="window.location.href='apresiasi_rw.php'">BATAL</button>
                    <button type="submit" class="btn-submit" id="btnSimpan">
                        <span class="btn-text">SIMPAN DATA</span>
                        <span class="btn-spinner" style="display:none;"><i class="bi bi-arrow-repeat spin"></i></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const grouped = <?= json_encode($grouped) ?>;
    const preselect = '<?= htmlspecialchars($preselect) ?>';

    function updateKelurahan() {
        const kec = document.getElementById('selectKecamatan').value;
        const selKel = document.getElementById('selectKelurahan');
        const selRW  = document.getElementById('selectRW');
        selKel.innerHTML = '<option value="">-- Pilih Kelurahan --</option>';
        selRW.innerHTML  = '<option value="">-- Pilih RW --</option>';
        selRW.disabled   = true;

        if (kec && grouped[kec]) {
            Object.keys(grouped[kec]).sort().forEach(kel => {
                const opt = document.createElement('option');
                opt.value = kel;
                opt.textContent = kel;
                selKel.appendChild(opt);
            });
            selKel.disabled = false;
        } else {
            selKel.disabled = true;
        }
    }

    function updateRW() {
        const kec = document.getElementById('selectKecamatan').value;
        const kel = document.getElementById('selectKelurahan').value;
        const selRW = document.getElementById('selectRW');
        selRW.innerHTML = '<option value="">-- Pilih RW --</option>';

        if (kec && kel && grouped[kec] && grouped[kec][kel]) {
            grouped[kec][kel].forEach(w => {
                const opt = document.createElement('option');
                opt.value = w.id_wilayah;
                opt.textContent = 'RW ' + String(w.rw).padStart(2,'0');
                if (w.id_wilayah === preselect) opt.selected = true;
                selRW.appendChild(opt);
            });
            selRW.disabled = false;
        } else {
            selRW.disabled = true;
        }
    }


    if (preselect) {

        for (const kec in grouped) {
            for (const kel in grouped[kec]) {
                const found = grouped[kec][kel].find(w => w.id_wilayah === preselect);
                if (found) {
                    document.getElementById('selectKecamatan').value = kec;
                    updateKelurahan();
                    document.getElementById('selectKelurahan').value = kel;
                    updateRW();
                    break;
                }
            }
        }
    }

    document.getElementById('formApresiasi').addEventListener('submit', async function(e) {
        e.preventDefault();

        const id_wilayah = document.getElementById('selectRW').value;
        if (!id_wilayah) {
            showToast('Pilih wilayah RW terlebih dahulu', 'error');
            return;
        }

        const btn = document.getElementById('btnSimpan');
        btn.querySelector('.btn-text').style.display = 'none';
        btn.querySelector('.btn-spinner').style.display = 'inline';
        btn.disabled = true;

        const fd = new FormData();
        fd.append('judul_apresiasi', document.getElementById('judul_apresiasi').value);
        fd.append('id_wilayah',      id_wilayah);
        fd.append('periode',         document.getElementById('periode').value);
        fd.append('deskripsi',       document.getElementById('deskripsi').value);

        const res  = await fetch('', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => {
                window.location.href = 'apresiasi_rw.php?toast=tambah';
            }, 1200);
        } else {
            showToast(data.message || 'Terjadi kesalahan', 'error');
            btn.querySelector('.btn-text').style.display = 'inline';
            btn.querySelector('.btn-spinner').style.display = 'none';
            btn.disabled = false;
        }
    });

    function showToast(msg, type = 'success') {
        const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', info: 'bi-info-circle-fill' };
        const div = document.createElement('div');
        div.className = `toast-item ${type}`;
        div.innerHTML = `<i class="bi ${icons[type] || icons.info} toast-icon"></i><span>${msg}</span>`;
        document.getElementById('toastContainer').appendChild(div);
        setTimeout(() => div.remove(), 3500);
    }
</script>
</body>
</html>