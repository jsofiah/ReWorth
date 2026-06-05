<?php
    session_start();

    if (!isset($_SESSION['role'])) {
        header("Location: ../login.php");
        exit;
    }

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
    }

    function getPenjual($supabaseUrl, $supabaseKey) {
        $url = $supabaseUrl . "/rest/v1/penjual?select=*&order=created_at.desc";
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

    $allPenjual = getPenjual($supabaseUrl, $supabaseKey);
    $total = count($allPenjual);
    $start = ($page - 1) * $per_page;
    $paginatedData = array_slice($allPenjual, $start, $per_page);
    $total_pages = ceil($total / $per_page);
    $start_number = $total > 0 ? $start + 1 : 0;
    $end_number = min($start + $per_page, $total);
?>

<div class="table-wrap">
    <div class="table-scroll-wrapper">
        <table class="responsive-table" id="dynamicTable">
            <colgroup>
                <col style="width: 60px;">
                <col style="width: 80px;">
                <col style="width: 150px;">
                <col style="width: 220px;">
                <col style="width: 130px;">
                <col style="width: 200px;">
            </colgroup>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Foto Profil</th>
                    <th>Nama Toko</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($paginatedData)): ?>
                    <?php foreach ($paginatedData as $idx => $p): ?>
                    <tr data-id="<?= $p['id_penjual'] ?>">
                        <td><?= $start_number + $idx ?></td>
                        <td>
                            <?php if (!empty($p['foto_profil'])): ?>
                                <img src="<?= getSupabaseImageUrl($p['foto_profil']) ?>" 
                                     class="avatar-img" 
                                     alt="Foto Profil"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="avatar-placeholder" style="display: none;">
                                    <?= strtoupper(substr($p['nama_penjual'] ?? 'T', 0, 1)) ?>
                                </div>
                            <?php else: ?>
                                <div class="avatar-placeholder">
                                    <?= strtoupper(substr($p['nama_penjual'] ?? 'T', 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="toko-info"><?= htmlspecialchars($p['nama_penjual'] ?? '-') ?></div>
                        </td>
                        <td>
                            <div class="toko-email"><?= htmlspecialchars($p['email'] ?? '-') ?></div>
                            <?php if (!empty($p['no_telepon'])): ?>
                                <small class="toko-telepon">
                                    <i class="bi bi-telephone-fill"></i> <?= htmlspecialchars($p['no_telepon']) ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $status = $p['status'] ?? 'menunggu_verifikasi';
                            if ($status == 'verified'): 
                            ?>
                                <span class="status-badge status-selesai">
                                    Verified
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-akan_datang">
                                    Menunggu Verifikasi
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn-aksi btn-edit" onclick="editAkun('penjual', '<?= $p['id_penjual'] ?>')">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                                <button class="btn-aksi btn-hapus" onclick="hapusAkun('penjual', '<?= $p['id_penjual'] ?>')">
                                    <i class="bi bi-trash3"></i> Hapus
                                </button>
                                <?php if (($p['status'] ?? 'menunggu_verifikasi') == 'menunggu_verifikasi'): ?>
                                    <button class="btn-aksi btn-lihat" onclick="verifikasiPenjual('<?= $p['id_penjual'] ?>')">
                                        <i class="bi bi-check2-circle"></i> Verifikasi
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-5" style="color: #6B8A7E;">
                            <i class="bi bi-shop" style="font-size: 48px; display: block; margin-bottom: 12px; opacity: 0.5;"></i>
                            Belum ada data penjual
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

<>
<script>
function verifikasiPenjual(idPenjual) {
    console.log('Tombol diklik, ID:', idPenjual);
    
    if (!confirm('Verifikasi penjual ini? Setelah diverifikasi, penjual dapat mengakses semua fitur.')) {
        return;
    }
    
    const btn = event.currentTarget;
    const originalHTML = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Memproses...';
    
    fetch('penjual_verifikasi.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + idPenjual
    })
    .then(response => response.json())
    .then(data => {
        console.log('Response:', data);
        
        if (data.success) {
            showToast(data.message, 'success');
            
            setTimeout(() => {
                if (typeof loadTabContent === 'function') {
                    loadTabContent('penjual', currentPage || 1);
                } else {
                    location.reload();
                }
            }, 1000);
        } else {
            showToast((data.message || 'Gagal verifikasi'), 'error');
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Terjadi kesalahan server', 'error');
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
}

function showToast(message, type) {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
        `;
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    toast.style.cssText = `
        background: ${type === 'success' ? '#10b981' : '#ef4444'};
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        margin-bottom: 10px;
        font-size: 14px;
        font-weight: 500;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideInRight 0.3s ease;
        cursor: pointer;
    `;
    toast.innerHTML = message;
    
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
    
    toast.onclick = () => {
        toast.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    };
}

if (!document.getElementById('toast-animation-style')) {
    const style = document.createElement('style');
    style.id = 'toast-animation-style';
    style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}

document.addEventListener('DOMContentLoaded', function() {
    const verifButtons = document.querySelectorAll('[onclick*="verifikasiPenjual"]');
    console.log('Tombol verifikasi ditemukan:', verifButtons.length);
});
</script>