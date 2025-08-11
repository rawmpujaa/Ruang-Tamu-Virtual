<?php
// process_appointment.php
// File untuk memproses form permohonan tamu virtual

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Konfigurasi database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ruang_tamu_virtual";

// Response default
$response = array(
    'success' => false,
    'message' => '',
    'data' => null
);

try {
    // Buat koneksi database
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Cek apakah request method POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method tidak diizinkan');
    }
    
    // Ambil dan validasi data dari form
    $nama = trim($_POST['nama'] ?? '');
    $jabatan = trim($_POST['jabatan'] ?? '');
    $nip = trim($_POST['nip'] ?? '');
    $bertamu_dengan = $_POST['bertamu_dengan'] ?? '';
    $tanggal = $_POST['tanggal'] ?? '';
    $nomor_hp = trim($_POST['nomor_hp'] ?? '');
    $keperluan = trim($_POST['keperluan'] ?? '');
    
    // Validasi field kosong
    if (empty($nama) || empty($jabatan) || empty($nip) || empty($bertamu_dengan) || 
        empty($tanggal) || empty($nomor_hp) || empty($keperluan)) {
        throw new Exception('Semua field harus diisi');
    }
    
    // Validasi format NIP (harus angka, minimal 18 digit)
    if (!preg_match('/^\d{18}$/', $nip)) {
        throw new Exception('Format NIP tidak valid (harus 18 digit angka)');
    }
    
    // Validasi format nomor HP
    if (!preg_match('/^(\+?62|62|0)[0-9]{8,13}$/', $nomor_hp)) {
        throw new Exception('Format nomor HP tidak valid');
    }
    
    // Normalisasi nomor HP ke format internasional
    if (substr($nomor_hp, 0, 1) === '0') {
        $nomor_hp = '62' . substr($nomor_hp, 1);
    } elseif (substr($nomor_hp, 0, 3) === '+62') {
        $nomor_hp = substr($nomor_hp, 1);
    }
    
    // Validasi tanggal tidak boleh hari ini atau sebelumnya
    $tanggal_kunjungan = new DateTime($tanggal);
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    if ($tanggal_kunjungan <= $today) {
        throw new Exception('Tanggal kunjungan harus minimal H+1 dari hari ini');
    }
    
    // Validasi pejabat yang dituju
    $valid_pejabat = ['Kpt', 'Wkpt', 'Panitera', 'Sekretaris'];
    if (!in_array($bertamu_dengan, $valid_pejabat)) {
        throw new Exception('Pejabat yang dipilih tidak valid');
    }
    
    // Cek apakah sudah ada permohonan di tanggal yang sama untuk pejabat yang sama
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM permohonan_tamu 
        WHERE tanggal_kunjungan = ? 
        AND bertamu_dengan = ? 
        AND status IN ('pending', 'approved')
    ");
    $stmt->execute([$tanggal, $bertamu_dengan]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing['count'] >= 5) { // Maksimal 5 pertemuan per hari per pejabat
        throw new Exception('Jadwal untuk pejabat tersebut pada tanggal yang dipilih sudah penuh');
    }
    
    // Cek duplikasi berdasarkan NIP dan tanggal
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM permohonan_tamu 
        WHERE nip = ? 
        AND tanggal_kunjungan = ? 
        AND status IN ('pending', 'approved')
    ");
    $stmt->execute([$nip, $tanggal]);
    $duplicate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($duplicate['count'] > 0) {
        throw new Exception('Anda sudah memiliki permohonan pada tanggal tersebut');
    }
    
    // Panggil stored procedure untuk create permohonan
    $stmt = $conn->prepare("CALL CreatePermohonan(?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $nama, 
        $jabatan, 
        $nip, 
        $bertamu_dengan, 
        $tanggal, 
        $nomor_hp, 
        $keperluan
    ]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $permohonan_id = $result['permohonan_id'];
    
    // Ambil detail pejabat untuk response
    $stmt = $conn->prepare("SELECT nama, jabatan_lengkap FROM pejabat WHERE kode = ?");
    $stmt->execute([$bertamu_dengan]);
    $pejabat_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Format tanggal untuk response
    $tanggal_formatted = date('l, d F Y', strtotime($tanggal));
    $days_indo = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin', 
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu'
    ];
    $months_indo = [
        'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
        'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
        'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
        'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
    ];
    
    foreach ($days_indo as $en => $id) {
        $tanggal_formatted = str_replace($en, $id, $tanggal_formatted);
    }
    foreach ($months_indo as $en => $id) {
        $tanggal_formatted = str_replace($en, $id, $tanggal_formatted);
    }
    
    // Siapkan data untuk response
    $response['success'] = true;
    $response['message'] = 'Permohonan berhasil dikirim ke Pengadilan Tinggi Padang';
    $response['data'] = [
        'permohonan_id' => $permohonan_id,
        'nama' => $nama,
        'jabatan' => $jabatan,
        'nip' => $nip,
        'pejabat_tujuan' => $pejabat_info['nama'],
        'jabatan_pejabat' => $pejabat_info['jabatan_lengkap'],
        'tanggal_kunjungan' => $tanggal_formatted,
        'keperluan' => $keperluan,
        'nomor_hp' => $nomor_hp,
        'status' => 'pending'
    ];
    
    // Optional: Kirim notifikasi email ke admin
    sendEmailNotification($response['data']);
    
} catch (PDOException $e) {
    $response['message'] = 'Kesalahan database: ' . $e->getMessage();
    error_log('Database error: ' . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
} finally {
    // Tutup koneksi database
    $conn = null;
}

// Kirim response dalam format JSON
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Fungsi untuk generate pesan WhatsApp otomatis
function generateWhatsAppMessage($data) {
    $message = "Hi Ini adalah pesan otomatis Aplikasi Sistem Pembinaan Tenaga Teknis (SIGANIS),\n\n";
    $message .= "Berikut adalah detil informasi untuk anda:\n\n";
    $message .= "Kepada Yth:\n";
    $message .= $data['nama'] . "\n\n";
    $message .= "Layanan\n";
    $message .= "Ruang Tamu Virtual Ditbinganis\n\n";
    $message .= "Permohonan tamu virtual a.n. " . $data['nama'] . " telah diterima Aplikasi SIGANIS.\n\n";
    $message .= "Hari/Tanggal : " . $data['tanggal_kunjungan'] . "\n";
    $message .= "Waktu : 09:30 - 09:45 WIB\n";
    $message .= "Perihal : " . $data['keperluan'] . "\n\n";
    $message .= "Penamaan Akun Zoom Saat Pertemuan Wajib : " . $data['nama'] . "\n\n";
    $message .= "Jika penamaan akun zoom saat pertemuan tidak sesuai di atas maka tidak akan diterima\n\n";
    $message .= "Permohonan anda akan diproses dan selanjutnya diinformasikan via WhatsApp pada nomor yang terdaftar pada Aplikasi SIKEP Mahkamah Agung RI";
    
    return $message;
}

// Fungsi untuk kirim notifikasi email ke admin
function sendEmailNotification($data) {
    // Implementasi pengiriman email ke admin
    $to = "admin@pta-padang.go.id"; // Email admin
    $subject = "🔔 PERMOHONAN TAMU VIRTUAL BARU - " . $data['nama'];
    
    $body = "Ada permohonan tamu virtual baru yang perlu diproses:\n\n";
    $body .= "DETAIL PERMOHONAN:\n";
    $body .= "==================\n";
    $body .= "ID Permohonan: #" . $data['permohonan_id'] . "\n";
    $body .= "Nama: " . $data['nama'] . "\n";
    $body .= "Jabatan: " . $data['jabatan'] . "\n";
    $body .= "NIP: " . $data['nip'] . "\n";
    $body .= "Bertamu dengan: " . $data['pejabat_tujuan'] . "\n";
    $body .= "Tanggal: " . $data['tanggal_kunjungan'] . "\n";
    $body .= "No HP: " . $data['nomor_hp'] . "\n";
    $body .= "Keperluan: " . $data['keperluan'] . "\n\n";
    $body .= "ACTION REQUIRED:\n";
    $body .= "Silakan login ke sistem admin untuk memproses permohonan ini.\n";
    $body .= "Link: " . $_SERVER['HTTP_HOST'] . "/admin_dashboard.php\n\n";
    $body .= "Setelah diproses, kirimkan pesan otomatis SIGANIS kepada pemohon melalui WhatsApp.";
    
    $headers = "From: noreply@pta-padang.go.id\r\n";
    $headers .= "Reply-To: admin@pta-padang.go.id\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // Uncomment untuk mengirim email
    // mail($to, $subject, $body, $headers);
}

// Fungsi untuk validasi tambahan (jika diperlukan)
function validateBusinessRules($data) {
    // Contoh: Validasi hari kerja (Senin-Jumat)
    $dayOfWeek = date('N', strtotime($data['tanggal'])); // 1=Monday, 7=Sunday
    if ($dayOfWeek > 5) {
        throw new Exception('Pertemuan hanya dapat dijadwalkan pada hari kerja (Senin-Jumat)');
    }
    
    // Contoh: Validasi jam kerja
    $hour = (int) date('H');
    if ($hour < 8 || $hour > 16) {
        // Jika pengajuan di luar jam kerja, berikan peringatan
        // (tidak menghentikan proses, hanya informasi)
    }
    
    return true;
}
?>