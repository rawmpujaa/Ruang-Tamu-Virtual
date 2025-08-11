<?php
// process_appointment.php - FIXED VERSION
// File untuk memproses form permohonan tamu virtual

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Konfigurasi database - PASTIKAN SESUAI DENGAN XAMPP ANDA
$servername = "localhost";
$username = "root";
$password = ""; // Kosong untuk XAMPP default
$dbname = "ruang_tamu_virtual";

// Response default
$response = array(
    'success' => false,
    'message' => '',
    'data' => null
);

try {
    // Buat koneksi database
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
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
    
    // Validasi format NIP (minimal 8 digit, bisa lebih fleksibel)
    if (!preg_match('/^\d{8,}$/', $nip)) {
        throw new Exception('Format NIP tidak valid (minimal 8 digit angka)');
    }
    
    // Validasi format nomor HP (lebih fleksibel)
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
    
    // GUNAKAN INSERT BIASA JIKA STORED PROCEDURE BELUM DIBUAT
    $stmt = $conn->prepare("
        INSERT INTO permohonan_tamu (
            nama, jabatan, nip, bertamu_dengan, 
            tanggal_kunjungan, nomor_hp, keperluan
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $nama, 
        $jabatan, 
        $nip, 
        $bertamu_dengan, 
        $tanggal, 
        $nomor_hp, 
        $keperluan
    ]);
    
    $permohonan_id = $conn->lastInsertId();
    
    // Log aktivitas secara manual
    $stmt = $conn->prepare("
        INSERT INTO activity_log (permohonan_id, action, description) 
        VALUES (?, 'CREATE', ?)
    ");
    $stmt->execute([$permohonan_id, 'Permohonan dibuat oleh ' . $nama]);
    
    // Ambil detail pejabat untuk response
    $stmt = $conn->prepare("SELECT nama, jabatan_lengkap FROM pejabat WHERE kode = ?");
    $stmt->execute([$bertamu_dengan]);
    $pejabat_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pejabat_info) {
        throw new Exception('Data pejabat tidak ditemukan');
    }
    
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
    
    // Kirim notifikasi email ke admin (opsional)
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

// Fungsi untuk kirim notifikasi email ke admin
function sendEmailNotification($data) {
    // Log notifikasi (bisa dihapus jika tidak diperlukan)
    error_log('Permohonan baru: ' . $data['nama'] . ' - ID: ' . $data['permohonan_id']);
    
    // Implementasi email bisa ditambahkan di sini
    // Untuk sementara, bisa diabaikan jika tidak diperlukan
}
?>