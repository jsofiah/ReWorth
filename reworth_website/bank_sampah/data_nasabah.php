<?php
    require_once 'role_check.php';

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
    }

    $userName = $_SESSION['nama_admin'] ?? 'User';
    $userEmail= $_SESSION['email']      ?? '';
    $userRole = $_SESSION['role']       ?? '';
    $userFoto = $_SESSION['foto_profil']?? '';
    function getPengguna($supabaseUrl, $supabaseKey) {
        $url = $supabaseUrl . "/rest/v1/pengguna?select=*&order=created_at.desc";
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

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 10;

    $allPengguna = getPengguna($supabaseUrl, $supabaseKey);
    $total = count($allPengguna);
    $start = ($page - 1) * $per_page;
    $paginatedData = array_slice($allPengguna, $start, $per_page);
    $total_pages = ceil($total / $per_page);
    $start_number = $total > 0 ? $start + 1 : 0;
    $end_number = min($start + $per_page, $total);
?>
<!DOCTYPE html><html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bank Sampah – Data Nasabah</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style/root.css">
    <link rel="stylesheet" href="style/form.css">
</head><body>
<aside class="sidebar">
        <div class="sidebar-logo">
            <img src="img/logo.png" alt="Logo ReWorth" title="Bank Sampah Kota Malang">
        </div>

        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link-custom">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="transaksi_setor_sampah.php" class="nav-link-custom">
                    <i class="bi bi-recycle"></i>
                    <span>Transaksi Setor Sampah</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="penarikan_saldo.php" class="nav-link-custom">
                    <i class="bi bi-wallet2"></i>
                    <span>Penarikan Saldo</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="event_lingkungan.php" class="nav-link-custom">
                    <i class="bi bi-calendar-event-fill"></i>
                    <span>Event Lingkungan</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="jadwal_ambil_sampah.php" class="nav-link-custom">
                    <i class="bi bi-calendar2-week-fill"></i>
                    <span>Jadwal Ambil Sampah</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="laporan_keuangan.php" class="nav-link-custom">
                    <i class="bi bi-bar-chart-line-fill"></i>
                    <span>Laporan dan Keuangan</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="data_nasabah.php" class="nav-link-custom active">
                    <i class="bi bi-people-fill"></i>
                    <span>Data Nasabah</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="data_sampah.php" class="nav-link-custom">
                    <i class="bi bi-trash-fill"></i>
                    <span>Data Sampah</span>
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
            <h1 class="topbar-title">Daftar Nasabah</h1>
            <div class="topbar-user">
                <div class="topbar-user-info">
                    <div class="topbar-user-name"><?=htmlspecialchars($userName)?></div>
                    <div class="topbar-user-email"><?=htmlspecialchars($userEmail)?></div>
                </div>
                <div class="topbar-avatar">
                    <?php if(!empty($userFoto)):$fu=getSupabaseImageUrl($userFoto);?>
                        <img src="<?=htmlspecialchars($fu)?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <i class="bi bi-person-fill" style="display:none;"></i>
                    <?php else:?><i class="bi bi-person-fill"></i><?php endif;?>
                </div>
            </div>
        </div>
    </div>

    <div class="action-bar-wrap">
        <div class="action-bar">
            <div class="search-wrap">
                <i class="bi bi-search"></i>
                <input type="text" class="search-input" placeholder="Cari nasabah..." id="searchInput">
            </div>
            <div class="filter-dropdown">
                <button class="btn-filter" onclick="toggleFilter()"><i class="bi bi-sliders2"></i> Filter</button>
                <div class="filter-box" id="filterBox">
                    <div class="filter-group">
                        <label>Urutkan</label>
                        <select id="sortOrder">
                            <option value="nama_asc">Nama A–Z</option>
                            <option value="nama_desc">Nama Z–A</option>
                            <option value="poin_desc">Poin Tertinggi</option>
                            <option value="saldo_desc">Saldo Tertinggi</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="button" onclick="resetFilter()">Reset</button>
                        <button type="button" onclick="applyFilter()">Terapkan</button>
                    </div>
                </div>
            </div>
            <?php if(in_array($userRole,['bank sampah','admin','dlh'])):?>
            <button class="btn-tambah" onclick="window.location.href='nasabah_tambah.php'">
                <i class="bi bi-plus-lg"></i> Tambah Nasabah
            </button>
            <?php endif;?>
        </div>
    </div>

    <div class="content-area">
        <div class="card-custom">
            <div class="table-wrap">
                <div class="table-scroll-wrapper">
                    <table class="responsive-table" id="dynamicTable">
                        <colgroup>
                            <col style="width: 60px;">
                            <col style="width: 150px;">
                            <col style="width: 200px;">
                            <col style="width: 80px;">
                            <col style="width: 200px;">
                            <col style="width: 280px;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>No Telepon</th>
                                <th>Alamat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php if (!empty($paginatedData)): ?>
                                <?php foreach ($paginatedData as $idx => $p): ?>
                                <tr data-id="<?= $p['id_pengguna'] ?>">
                                    <td><?= $start_number + $idx ?></td>
                                    <td class="table-cell-content"><?= htmlspecialchars($p['nama_lengkap'] ?? '-') ?></td>
                                    <td class="table-cell-content"><?= htmlspecialchars($p['email'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($p['no_telepon'] ?? '-') ?></td>
                                    <td class="table-cell-content"><?= htmlspecialchars($p['alamat_detail'] ?? '-') ?></td>
                                    <td>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <button class="btn-aksi btn-lihat" onclick="lihatNasabah('<?= $p['id_pengguna'] ?>')">
                                                <i class="bi bi-file-earmark-text"></i> Lihat
                                            </button>
                                            <button class="btn-aksi btn-edit" onclick="editNasabah('<?= $p['id_pengguna'] ?>')">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </button>
                                            <button class="btn-aksi btn-hapus" onclick="hapusNasabah('<?= $p['id_pengguna'] ?>', '<?= addslashes($p['nama_lengkap'] ?? '') ?>')">
                                                <i class="bi bi-trash3"></i> Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4" style="color: #6B8A7E;">
                                        <i class="bi bi-people" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                        Belum ada data pengguna
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
        </div>
    </div>
</div>


<div class="modal-overlay" id="modalHapus">
    <div class="modal-box" style="max-width:400px;">
        <div class="confirm-icon"><i class="bi bi-trash3-fill"></i></div>
        <div class="confirm-text">
            <h3>Hapus Nasabah?</h3>
            <p>Data nasabah akan dihapus secara permanen.</p>
        </div>
        <div class="modal-actions" style="justify-content:center;margin-top:20px;">
            <button class="btn-cancel" onclick="closeModal('modalHapus')">Batal</button>
            <button id="btnConfirmHapus" class="btn-aksi btn-hapus" style="padding:10px 22px;font-size:14px;border-radius:12px;" onclick="confirmHapus()">
                <i class="bi bi-trash3"></i> Ya, Hapus
            </button>
        </div>
    </div>
</div>
<div class="toast-container" id="toastContainer"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let deletingId=null,deletingNama=null;
function openModal(id){document.getElementById(id).classList.add('show');}
function closeModal(id){document.getElementById(id).classList.remove('show');}
document.querySelectorAll('.modal-overlay').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('show');}));

function lihatNasabah(id){window.location.href='nasabah_lihat.php?id='+id;}
function editNasabah(id){window.location.href='nasabah_edit.php?id='+id;}


const allNasabahData = <?= json_encode(array_map(function($p) {
    return [
        'id'        => $p['id_pengguna'] ?? '',
        'nama'      => $p['nama_lengkap'] ?? '-',
        'email'     => $p['email'] ?? '-',
        'telepon'   => $p['no_telepon'] ?? '-',
        'alamat'    => $p['alamat_detail'] ?? '-',
    ];
}, $allPengguna)) ?>;

function renderTable(data) {
    const tbody = document.getElementById('tableBody');
    if (!data.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4" style="color:#6B8A7E;">
            <i class="bi bi-people" style="font-size:32px;display:block;margin-bottom:8px;"></i>
            Tidak ada data ditemukan</td></tr>`;
        return;
    }
    tbody.innerHTML = data.map((p, i) => `
        <tr data-id="${p.id}">
            <td>${i + 1}</td>
            <td class="table-cell-content">${escHtml(p.nama)}</td>
            <td class="table-cell-content">${escHtml(p.email)}</td>
            <td>${escHtml(p.telepon)}</td>
            <td class="table-cell-content">${escHtml(p.alamat)}</td>
            <td>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn-aksi btn-lihat" onclick="lihatNasabah('${p.id}')"><i class="bi bi-file-earmark-text"></i> Lihat</button>
                    <button class="btn-aksi btn-edit"  onclick="editNasabah('${p.id}')"><i class="bi bi-pencil-square"></i> Edit</button>
                    <button class="btn-aksi btn-hapus" onclick="hapusNasabah('${p.id}','${p.nama.replace(/'/g,"\\'")}')"><i class="bi bi-trash3"></i> Hapus</button>
                </div>
            </td>
        </tr>`).join('');
}

function escHtml(s){
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

let searchTimeout = null;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const q = this.value.trim().toLowerCase();
    searchTimeout = setTimeout(() => {
        if (!q) { renderTable(allNasabahData); return; }
        const filtered = allNasabahData.filter(p =>
            p.nama.toLowerCase().includes(q) ||
            p.email.toLowerCase().includes(q) ||
            p.telepon.toLowerCase().includes(q) ||
            p.alamat.toLowerCase().includes(q)
        );
        renderTable(filtered);
    }, 200);
});
function toggleFilter(){document.getElementById('filterBox').classList.toggle('show');}
function applyFilter(){
    const sort = document.getElementById('sortOrder').value;
    const q = document.getElementById('searchInput').value.trim().toLowerCase();
    let data = q ? allNasabahData.filter(p =>
        p.nama.toLowerCase().includes(q) || p.email.toLowerCase().includes(q) ||
        p.telepon.toLowerCase().includes(q) || p.alamat.toLowerCase().includes(q)
    ) : [...allNasabahData];
    data.sort((a,b) => {
        if(sort==='nama_asc')  return a.nama.localeCompare(b.nama);
        if(sort==='nama_desc') return b.nama.localeCompare(a.nama);
        return 0;
    });
    renderTable(data);
    document.getElementById('filterBox').classList.remove('show');
}
function resetFilter(){document.getElementById('sortOrder').value='nama_asc';applyFilter();}
function changePage(p){window.location.href='?page='+p;}
function hapusNasabah(id,nama){deletingId=id;deletingNama=nama;openModal('modalHapus');}
function confirmHapus(){
    if(!deletingId)return;
    const btn=document.getElementById('btnConfirmHapus');
    btn.disabled=true;btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>';
    fetch('nasabah_hapus.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:deletingId,nama:deletingNama})})
    .then(r=>r.json()).then(data=>{
        closeModal('modalHapus');
        if(data.success){showToast('Nasabah berhasil dihapus.','success');setTimeout(()=>location.reload(),800);}
        else{showToast(data.message||'Gagal menghapus.','error');btn.disabled=false;btn.innerHTML='<i class="bi bi-trash3"></i> Ya, Hapus';}
    }).catch(()=>showToast('Kesalahan server.','error'));
}
function showToast(msg,type='success'){
    const icons={success:'bi-check-circle-fill',error:'bi-x-circle-fill'};
    const div=document.createElement('div');div.className=`toast-item ${type}`;
    div.innerHTML=`<i class="bi ${icons[type]} toast-icon"></i><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(div);setTimeout(()=>div.remove(),3500);
}
</script>
</body>
</html>