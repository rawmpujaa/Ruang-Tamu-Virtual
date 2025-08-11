<?php
// send_siganis_message.php
// File khusus untuk admin mengirim pesan otomatis SIGANIS ke pemohon

header('Content-Type: application/json');
session_start();

// Simple authentication check
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Konfigurasi database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ruang_tamu_virtual";

$response = array(
    'success' => false,
    'message' => '',
    'data' => null
);

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $permohonan_id = (int)($_POST['permohonan_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($permohonan_id <= 0) {
        throw new Exception('ID permohonan tidak valid');
    }
    
    // Ambil detail permohonan
    $stmt = $conn->prepare("
        SELECT 
            p.*,
            pj.nama as pejabat_nama,
            pj.jabatan_lengkap
        FROM permohonan_tamu p
        JOIN pejabat pj ON p.bertamu_dengan = pj.kode
        WHERE p.id = ?
    ");
    $stmt->execute([$permohonan_id]);
    $permohonan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permohonan) {
        throw new Exception('Data permohonan tidak ditemukan');
    }
    
    // Format tanggal untuk pesan
    $tanggal_kunjungan = date('l, d F Y', strtotime($permohonan['tanggal_kunjungan']));
    
    // Terjemahan hari dan bulan ke Bahasa Indonesia
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
    
    switch ($action) {
        case 'generate_message':
            // Generate pesan SIGANIS
            $siganis_message = generateSiganisMessage($permohonan, $tanggal_kunjungan);
            $response['success'] = true;
            $response['data'] = [
                'message' => $siganis_message,
                'phone' => $permohonan['nomor_hp'],
                'nama' => $permohonan['nama']
            ];
            break;
            
        case 'send_approval':
            // Update status menjadi approved dan generate pesan persetujuan
            $stmt = $conn->prepare("CALL UpdateStatusPermohonan(?, 'approved', ?, ?)");
            $stmt->execute([
                $permohonan_id,
                'Permohonan disetujui dan pesan SIGANIS telah dikirim',
                $_SESSION['admin_name'] ?? 'Admin'
            ]);
            
            $approval_message = generateApprovalMessage($permohonan, $tanggal_kunjungan);
            $response['success'] = true;
            $response['message'] = 'Status updated dan pesan persetujuan siap dikirim';
            $response['data'] = [
                'message' => $approval_message,
                'phone' => $permohonan['nomor_hp'],
                'nama' => $permohonan['nama']
            ];
            break;
            
        case 'send_rejection':
            // Update status menjadi rejected dan generate pesan penolakan
            $rejection_reason = $_POST['rejection_reason'] ?? 'Tidak memenuhi persyaratan';
            
            $stmt = $conn->prepare("CALL UpdateStatusPermohonan(?, 'rejected', ?, ?)");
            $stmt->execute([
                $permohonan_id,
                'Permohonan ditolak: ' . $rejection_reason,
                $_SESSION['admin_name'] ?? 'Admin'
            ]);
            
            $rejection_message = generateRejectionMessage($permohonan, $rejection_reason);
            $response['success'] = true;
            $response['message'] = 'Status updated dan pesan penolakan siap dikirim';
            $response['data'] = [
                'message' => $rejection_message,
                'phone' => $permohonan['nomor_hp'],
                'nama' => $permohonan['nama']
            ];
            break;
            
        case 'send_reschedule':
            // Generate pesan penjadwalan ulang
            $new_date = $_POST['new_date'] ?? '';
            $new_time = $_POST['new_time'] ?? '09:30';
            
            if ($new_date) {
                $stmt = $conn->prepare("UPDATE permohonan_tamu SET tanggal_kunjungan = ?, waktu_pertemuan = ? WHERE id = ?");
                $stmt->execute([$new_date, $new_time, $permohonan_id]);
                
                $new_date_formatted = date('l, d F Y', strtotime($new_date));
                foreach ($days_indo as $en => $id_lang) {
                    $new_date_formatted = str_replace($en, $id_lang, $new_date_formatted);
                }
                foreach ($months_indo as $en => $id_lang) {
                    $new_date_formatted = str_replace($en, $id_lang, $new_date_formatted);
                }
                
                $permohonan['tanggal_kunjungan'] = $new_date;
                $reschedule_message = generateRescheduleMessage($permohonan, $new_date_formatted, $new_time);
                
                $response['success'] = true;
                $response['message'] = 'Jadwal berhasil diubah dan pesan siap dikirim';
                $response['data'] = [
                    'message' => $reschedule_message,
                    'phone' => $permohonan['nomor_hp'],
                    'nama' => $permohonan['nama']
                ];
            } else {
                throw new Exception('Tanggal baru harus diisi');
            }
            break;
            
        default:
            throw new Exception('Action tidak valid');
    }
    
} catch (PDOException $e) {
    $response['message'] = 'Kesalahan database: ' . $e->getMessage();
    error_log('Database error: ' . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
} finally {
    $conn = null;
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Fungsi untuk generate pesan SIGANIS standard
function generateSiganisMessage($permohonan, $tanggal_formatted) {
    $message = "Hi Ini adalah pesan otomatis Aplikasi Sistem Pembinaan Tenaga Teknis (SIGANIS),\n\n";
    $message .= "Berikut adalah detil informasi untuk anda:\n\n";
    $message .= "Kepada Yth:\n";
    $message .= $permohonan['nama'] . "\n\n";
    $message .= "Layanan\n";
    $message .= "Ruang Tamu Virtual Ditbinganis\n\n";
    $message .= "Permohonan tamu virtual a.n. " . $permohonan['nama'] . " telah diterima Aplikasi SIGANIS.\n\n";
    $message .= "Hari/Tanggal : " . $tanggal_formatted . "\n";
    $message .= "Waktu : 09:30 - 09:45 WIB\n";
    $message .= "Perihal : " . $permohonan['keperluan'] . "\n\n";
    $message .= "Penamaan Akun Zoom Saat Pertemuan Wajib : " . $permohonan['nama'] . "\n\n";
    $message .= "Jika penamaan akun zoom saat pertemuan tidak sesuai di atas maka tidak akan diterima\n\n";
    $message .= "Permohonan anda akan diproses dan selanjutnya diinformasikan via WhatsApp pada nomor yang terdaftar pada Aplikasi SIKEP Mahkamah Agung RI";
    
    return $message;
}

// Fungsi untuk generate pesan persetujuan
function generateApprovalMessage($permohonan, $tanggal_formatted) {
    $message = "✅ PERMOHONAN TAMU VIRTUAL DISETUJUI\n\n";
    $message .= "Kepada Yth. " . $permohonan['nama'] . "\n\n";
    $message .= "Permohonan tamu virtual Anda telah DISETUJUI oleh Pengadilan Tinggi Padang.\n\n";
    $message .= "DETAIL PERTEMUAN:\n";
    $message .= "📅 Tanggal: " . $tanggal_formatted . "\n";
    $message .= "🕘 Waktu: 09:30 - 09:45 WIB\n";
    $message .= "👨‍⚖️ Bertemu dengan: " . $permohonan['pejabat_nama'] . "\n";
    $message .= "📝 Perihal: " . $permohonan['keperluan'] . "\n\n";
    $message .= "PETUNJUK ZOOM:\n";
    $message .= "- Link Zoom akan dikirim H-1 sebelum pertemuan\n";
    $message .= "- Nama akun Zoom WAJIB: " . $permohonan['nama'] . "\n";
    $message .= "- Harap bergabung 5 menit sebelum waktu yang ditentukan\n\n";
    $message .= "Terima kasih atas perhatian Anda.\n\n";
    $message .= "🏛️ Pengadilan Tinggi Padang\n";
    $message .= "Sistem SIGANIS";
    
    return $message;
}

// Fungsi untuk generate pesan penolakan
function generateRejectionMessage($permohonan, $reason) {
    $message = "❌ PERMOHONAN TAMU VIRTUAL DITOLAK\n\n";
    $message .= "Kepada Yth. " . $permohonan['nama'] . "\n\n";
    $message .= "Mohon maaf, permohonan tamu virtual Anda tidak dapat disetujui.\n\n";
    $message .= "ALASAN PENOLAKAN:\n";
    $message .= $reason . "\n\n";
    $message .= "Anda dapat mengajukan permohonan ulang dengan melengkapi persyaratan yang diperlukan.\n\n";
    $message .= "Untuk informasi lebih lanjut, silakan hubungi:\n";
    $message .= "📞 Sekretariat Pengadilan Tinggi Padang\n\n";
    $message .= "Terima kasih atas pengertian Anda.\n\n";
    $message .= "🏛️ Pengadilan Tinggi Padang\n";
    $message .= "Sistem SIGANIS";
    
    return $message;
}

// Fungsi untuk generate pesan penjadwalan ulang
function generateRescheduleMessage($permohonan, $new_date_formatted, $new_time) {
    $message = "🔄 PERUBAHAN JADWAL TAMU VIRTUAL\n\n";
    $message .= "Kepada Yth. " . $permohonan['nama'] . "\n\n";
    $message .= "Jadwal pertemuan tamu virtual Anda telah diubah:\n\n";
    $message .= "JADWAL BARU:\n";
    $message .= "📅 Tanggal: " . $new_date_formatted . "\n";
    $message .= "🕘 Waktu: " . date('H:i', strtotime($new_time)) . " - " . date('H:i', strtotime($new_time . ' + 15 minutes')) . " WIB\n";
    $message .= "👨‍⚖️ Bertemu dengan: " . $permohonan['pejabat_nama'] . "\n";
    $message .= "📝 Perihal: " . $permohonan['keperluan'] . "\n\n";
    $message .= "Mohon untuk menyesuaikan jadwal Anda.\n";
    $message .= "Link Zoom akan dikirim sebelum pertemuan.\n\n";
    $message .= "Terima kasih atas pengertian Anda.\n\n";
    $message .= "🏛️ Pengadilan Tinggi Padang\n";
    $message .= "Sistem SIGANIS";
    
    return $message;
}
?>