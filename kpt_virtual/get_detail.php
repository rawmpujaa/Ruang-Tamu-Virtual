<?php
// get_detail.php
// File untuk mengambil detail permohonan via AJAX

// Konfigurasi database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ruang_tamu_virtual";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $id = (int)($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        throw new Exception('ID tidak valid');
    }
    
    // Ambil detail permohonan
    $stmt = $conn->prepare("
        SELECT 
            p.*,
            pj.nama as pejabat_nama,
            pj.jabatan_lengkap,
            pj.email as pejabat_email,
            pj.telepon as pejabat_telepon
        FROM permohonan_tamu p
        JOIN pejabat pj ON p.bertamu_dengan = pj.kode
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $permohonan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permohonan) {
        throw new Exception('Data permohonan tidak ditemukan');
    }
    
    // Ambil log aktivitas
    $stmt = $conn->prepare("
        SELECT * FROM activity_log 
        WHERE permohonan_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format tanggal dan waktu
    $tanggal_kunjungan = date('l, d F Y', strtotime($permohonan['tanggal_kunjungan']));
    $tanggal_permohonan = date('d/m/Y H:i', strtotime($permohonan['created_at']));
    
    // Terjemahan hari ke Bahasa Indonesia
    $days_indo = [
        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
    ];
    $months_indo = [
        'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
        'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
        'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
        'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
    ];
    
    foreach ($days_indo as $en => $id_lang) {
        $tanggal_kunjungan = str_replace($en, $id_lang, $tanggal_kunjungan);
    }
    foreach ($months_indo as $en => $id_lang) {
        $tanggal_kunjungan = str_replace($en, $id_lang, $tanggal_kunjungan);
    }
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<div class="row">
    <div class="col-md-8">
        <h6 class="fw-bold mb-3">Informasi Pemohon</h6>
        <table class="table table-borderless">
            <tr>
                <td width="30%"><strong>Nama Lengkap</strong></td>
                <td><?= htmlspecialchars($permohonan['nama']) ?></td>
            </tr>
            <tr>
                <td><strong>Jabatan</strong></td>
                <td><?= htmlspecialchars($permohonan['jabatan']) ?></td>
            </tr>
            <tr>
                <td><strong>NIP</strong></td>
                <td><?= htmlspecialchars($permohonan['nip']) ?></td>
            </tr>
            <tr>
                <td><strong>Nomor HP</strong></td>
                <td>
                    <?= htmlspecialchars($permohonan['nomor_hp']) ?>
                    <a href="https://wa.me/<?= $permohonan['nomor_hp'] ?>" target="_blank" class="btn btn-sm btn-success ms-2">
                        <i class="fab fa-whatsapp"></i> Chat
                    </a>
                </td>
            </tr>
        </table>
        
        <h6 class="fw-bold mb-3 mt-4">Informasi Pertemuan</h6>
        <table class="table table-borderless">
            <tr>
                <td width="30%"><strong>Bertamu Dengan</strong></td>
                <td>
                    <?= htmlspecialchars($permohonan['pejabat_nama']) ?><br>
                    <small class="text-muted"><?= htmlspecialchars($permohonan['jabatan_lengkap']) ?></small>
                </td>
            </tr>
            <tr>
                <td><strong>Tanggal Kunjungan</strong></td>
                <td><?= $tanggal_kunjungan ?></td>
            </tr>
            <tr>
                <td><strong>Waktu Pertemuan</strong></td>
                <td><?= date('H:i', strtotime($permohonan['waktu_pertemuan'])) ?> - <?= date('H:i', strtotime($permohonan['waktu_pertemuan'] . ' + ' . $permohonan['durasi_menit'] . ' minutes')) ?> WIB</td>
            </tr>
            <tr>
                <td><strong>Keperluan</strong></td>
                <td><?= nl2br(htmlspecialchars($permohonan['keperluan'])) ?></td>
            </tr>
        </table>
        
        <h6 class="fw-bold mb-3 mt-4">Status & Catatan</h6>
        <table class="table table-borderless">
            <tr>
                <td width="30%"><strong>Status Saat Ini</strong></td>
                <td>
                    <span class="status-badge status-<?= $permohonan['status'] ?>">
                        <?= ucfirst($permohonan['status']) ?>
                    </span>
                </td>
            </tr>
            <tr>
                <td><strong>Tanggal Permohonan</strong></td>
                <td><?= $tanggal_permohonan ?></td>
            </tr>
            <?php if ($permohonan['processed_by']): ?>
            <tr>
                <td><strong>Diproses Oleh</strong></td>
                <td><?= htmlspecialchars($permohonan['processed_by']) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($permohonan['notes']): ?>
            <tr>
                <td><strong>Catatan</strong></td>
                <td><?= nl2br(htmlspecialchars($permohonan['notes'])) ?></td>
            </tr>
            <?php endif; ?>
        </table>
        
        <!-- Quick Actions -->
        <div class="mt-4">
            <h6 class="fw-bold mb-3">Aksi Cepat</h6>
            <div class="btn-group" role="group">
                <button class="btn btn-success btn-sm" onclick="updateStatusQuick(<?= $permohonan['id'] ?>, 'approved')">
                    <i class="fas fa-check"></i> Setujui
                </button>
                <button class="btn btn-warning btn-sm" onclick="updateStatusQuick(<?= $permohonan['id'] ?>, 'pending')">
                    <i class="fas fa-clock"></i> Pending
                </button>
                <button class="btn btn-danger btn-sm" onclick="updateStatusQuick(<?= $permohonan['id'] ?>, 'rejected')">
                    <i class="fas fa-times"></i> Tolak
                </button>
                <button class="btn btn-primary btn-sm" onclick="updateStatusQuick(<?= $permohonan['id'] ?>, 'completed')">
                    <i class="fas fa-flag-checkered"></i> Selesai
                </button>
            </div>
        </div>
        
        <!-- Admin Message Center -->
        <div class="mt-4">
            <h6 class="fw-bold mb-3">Pusat Pesan Admin</h6>
            <div class="card bg-light">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <button class="btn btn-success btn-sm mb-2 w-100" onclick="sendApprovalMessage(<?= $permohonan['id'] ?>)">
                                <i class="fas fa-check"></i> Setujui & Kirim Pesan SIGANIS
                            </button>
                        </div>
                        <div class="col-md-6">
                            <button class="btn btn-danger btn-sm mb-2 w-100" onclick="showRejectionModal(<?= $permohonan['id'] ?>)">
                                <i class="fas fa-times"></i> Tolak & Kirim Pesan
                            </button>
                        </div>
                        <div class="col-md-6">
                            <button class="btn btn-warning btn-sm mb-2 w-100" onclick="showRescheduleModal(<?= $permohonan['id'] ?>)">
                                <i class="fas fa-calendar-alt"></i> Reschedule & Kirim Pesan
                            </button>
                        </div>
                        <div class="col-md-6">
                            <button class="btn btn-info btn-sm mb-2 w-100" onclick="generateSiganisMessage(<?= $permohonan['id'] ?>)">
                                <i class="fas fa-comment"></i> Generate Pesan SIGANIS
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Generate WhatsApp Message -->
        <div class="mt-4">
            <h6 class="fw-bold mb-3">Preview Pesan WhatsApp</h6>
            <div class="card bg-light">
                <div class="card-body">
                    <div id="messagePreview" class="border p-3 mb-3" style="font-size: 0.9rem; background: white; display: none;">
                        <!-- Pesan akan muncul di sini -->
                    </div>
                    <div id="messageActions" style="display: none;">
                        <button class="btn btn-success btn-sm" onclick="sendToWhatsApp()">
                            <i class="fab fa-whatsapp"></i> Kirim ke WhatsApp
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="copyMessageToClipboard()">
                            <i class="fas fa-copy"></i> Copy Pesan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <h6 class="fw-bold mb-3">Riwayat Aktivitas</h6>
        <div class="timeline">
            <?php if (empty($logs)): ?>
            <p class="text-muted">Belum ada aktivitas</p>
            <?php else: ?>
            <?php foreach ($logs as $log): ?>
            <div class="timeline-item mb-3">
                <div class="card border-start border-primary border-3">
                    <div class="card-body py-2">
                        <h6 class="card-title mb-1"><?= htmlspecialchars($log['action']) ?></h6>
                        <p class="card-text mb-1"><?= htmlspecialchars($log['description']) ?></p>
                        <small class="text-muted">
                            <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                            <?php if ($log['created_by']): ?>
                            oleh <?= htmlspecialchars($log['created_by']) ?>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal untuk Penolakan -->
<div class="modal fade" id="rejectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tolak Permohonan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Alasan Penolakan</label>
                    <textarea id="rejectionReason" class="form-control" rows="3" placeholder="Masukkan alasan penolakan...">Tidak memenuhi persyaratan administratif</textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" onclick="confirmRejection()">Tolak & Kirim Pesan</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal untuk Reschedule -->
<div class="modal fade" id="rescheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ubah Jadwal Pertemuan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Tanggal Baru</label>
                    <input type="date" id="newDate" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Waktu Baru</label>
                    <select id="newTime" class="form-select">
                        <option value="09:00">09:00 - 09:15 WIB</option>
                        <option value="09:30" selected>09:30 - 09:45 WIB</option>
                        <option value="10:00">10:00 - 10:15 WIB</option>
                        <option value="10:30">10:30 - 10:45 WIB</option>
                        <option value="11:00">11:00 - 11:15 WIB</option>
                        <option value="13:00">13:00 - 13:15 WIB</option>
                        <option value="13:30">13:30 - 13:45 WIB</option>
                        <option value="14:00">14:00 - 14:15 WIB</option>
                        <option value="14:30">14:30 - 14:45 WIB</option>
                        <option value="15:00">15:00 - 15:15 WIB</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-warning" onclick="confirmReschedule()">Ubah Jadwal & Kirim Pesan</button>
            </div>
        </div>
    </div>
</div>

<style>
.status-badge {
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-approved {
    background: #d1edff;
    color: #0c5460;
}

.status-rejected {
    background: #f8d7da;
    color: #721c24;
}

.status-completed {
    background: #d4edda;
    color: #155724;
}

.timeline-item {
    position: relative;
}
</style>

<script>
let currentPermohonanId = <?= $permohonan['id'] ?>;
let currentPhone = '<?= $permohonan['nomor_hp'] ?>';
let currentMessage = '';

function updateStatusQuick(id, status) {
    if (confirm(`Apakah Anda yakin ingin mengubah status menjadi "${status}"?`)) {
        // Create a form and submit
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="permohonan_id" value="${id}">
            <input type="hidden" name="status" value="${status}">
            <input type="hidden" name="notes" value="Diupdate via quick action">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function sendApprovalMessage(id) {
    if (confirm('Apakah Anda yakin ingin menyetujui permohonan ini dan mengirim pesan SIGANIS?')) {
        sendAdminMessage('send_approval', id);
    }
}

function showRejectionModal(id) {
    currentPermohonanId = id;
    new bootstrap.Modal(document.getElementById('rejectionModal')).show();
}

function confirmRejection() {
    const reason = document.getElementById('rejectionReason').value.trim();
    if (!reason) {
        alert('Alasan penolakan harus diisi');
        return;
    }
    
    sendAdminMessage('send_rejection', currentPermohonanId, { rejection_reason: reason });
    bootstrap.Modal.getInstance(document.getElementById('rejectionModal')).hide();
}

function showRescheduleModal(id) {
    currentPermohonanId = id;
    // Set minimum date untuk besok
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('newDate').min = tomorrow.toISOString().split('T')[0];
    
    new bootstrap.Modal(document.getElementById('rescheduleModal')).show();
}

function confirmReschedule() {
    const newDate = document.getElementById('newDate').value;
    const newTime = document.getElementById('newTime').value;
    
    if (!newDate) {
        alert('Tanggal baru harus diisi');
        return;
    }
    
    sendAdminMessage('send_reschedule', currentPermohonanId, { 
        new_date: newDate, 
        new_time: newTime 
    });
    bootstrap.Modal.getInstance(document.getElementById('rescheduleModal')).hide();
}

function generateSiganisMessage(id) {
    sendAdminMessage('generate_message', id);
}

function sendAdminMessage(action, id, extraData = {}) {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('permohonan_id', id);
    
    // Add extra data
    for (const [key, value] of Object.entries(extraData)) {
        formData.append(key, value);
    }
    
    fetch('send_siganis_message.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show message preview
            displayMessagePreview(data.data.message, data.data.phone, data.data.nama);
            
            if (data.message) {
                alert(data.message);
            }
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat memproses permintaan');
    });
}

function displayMessagePreview(message, phone, nama) {
    currentMessage = message;
    currentPhone = phone;
    
    document.getElementById('messagePreview').innerHTML = `
        <strong>Kepada: ${nama} (${phone})</strong><br><br>
        ${message.replace(/\n/g, '<br>')}
    `;
    document.getElementById('messagePreview').style.display = 'block';
    document.getElementById('messageActions').style.display = 'block';
}

function sendToWhatsApp() {
    const whatsappURL = `https://wa.me/${currentPhone}?text=${encodeURIComponent(currentMessage)}`;
    window.open(whatsappURL, '_blank');
}

function copyMessageToClipboard() {
    navigator.clipboard.writeText(currentMessage).then(() => {
        alert('Pesan berhasil disalin ke clipboard');
    }).catch(err => {
        console.error('Error copying message: ', err);
        // Fallback untuk browser lama
        const textarea = document.createElement('textarea');
        textarea.value = currentMessage;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('Pesan berhasil disalin ke clipboard');
    });
}

// Legacy functions untuk kompatibilitas
function sendWhatsAppMessage(phone, encodedMessage) {
    const message = atob(encodedMessage);
    const whatsappURL = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
    window.open(whatsappURL, '_blank');
}

function copyMessage(encodedMessage) {
    const message = atob(encodedMessage);
    copyMessageToClipboard(message);
}
</script>