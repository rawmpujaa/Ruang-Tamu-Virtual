<?php
// admin_dashboard.php
// Dashboard admin untuk mengelola permohonan tamu virtual

session_start();

// Simple authentication (ganti dengan sistem login yang lebih secure)
if (!isset($_SESSION['admin_logged_in'])) {
    // Redirect ke login page atau tampilkan form login
    // header('Location: login.php');
    // exit;
}

// Konfigurasi database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ruang_tamu_virtual";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Handle form actions
    if ($_POST) {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_status':
                    $stmt = $conn->prepare("CALL UpdateStatusPermohonan(?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['permohonan_id'],
                        $_POST['status'],
                        $_POST['notes'] ?? '',
                        $_SESSION['admin_name'] ?? 'Admin'
                    ]);
                    $success_message = "Status permohonan berhasil diupdate";
                    break;
            }
        }
    }
    
    // Ambil data permohonan dengan pagination
    $page = (int)($_GET['page'] ?? 1);
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $filter_status = $_GET['status'] ?? '';
    $filter_date = $_GET['date'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $where_conditions = [];
    $params = [];
    
    if ($filter_status) {
        $where_conditions[] = "p.status = ?";
        $params[] = $filter_status;
    }
    
    if ($filter_date) {
        $where_conditions[] = "p.tanggal_kunjungan = ?";
        $params[] = $filter_date;
    }
    
    if ($search) {
        $where_conditions[] = "(p.nama LIKE ? OR p.nip LIKE ? OR p.keperluan LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    // Query untuk data permohonan
    $sql = "
        SELECT 
            p.*,
            pj.nama as pejabat_nama,
            pj.jabatan_lengkap
        FROM permohonan_tamu p
        JOIN pejabat pj ON p.bertamu_dengan = pj.kode
        $where_clause
        ORDER BY p.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $permohonan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Query untuk total records (untuk pagination)
    $count_sql = "
        SELECT COUNT(*) as total
        FROM permohonan_tamu p
        JOIN pejabat pj ON p.bertamu_dengan = pj.kode
        $where_clause
    ";
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($params);
    $total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $limit);
    
    // Statistik dashboard
    $stats = [];
    $stat_queries = [
        'total' => "SELECT COUNT(*) as count FROM permohonan_tamu",
        'pending' => "SELECT COUNT(*) as count FROM permohonan_tamu WHERE status = 'pending'",
        'approved' => "SELECT COUNT(*) as count FROM permohonan_tamu WHERE status = 'approved'",
        'today' => "SELECT COUNT(*) as count FROM permohonan_tamu WHERE DATE(tanggal_kunjungan) = CURDATE()"
    ];
    
    foreach ($stat_queries as $key => $query) {
        $stmt = $conn->query($query);
        $stats[$key] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
} catch (PDOException $e) {
    $error_message = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Ruang Tamu Virtual</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #606C38;
            --secondary-color: #283618;
            --accent-color: #DDA15E;
            --light-color: #FEFAE0;
            --warning-color: #BC6C25;
        }
        
        body {
            background: linear-gradient(135deg, var(--light-color) 0%, var(--accent-color) 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link {
            color: var(--light-color);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 5px 10px;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(254, 250, 224, 0.2);
            color: var(--accent-color);
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            color: white;
            text-align: center;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        
        .btn-custom {
            background: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 8px;
        }
        
        .btn-custom:hover {
            background: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
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
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <div class="p-3">
                    <h4 class="text-light mb-4">
                        <i class="fas fa-balance-scale"></i>
                        Admin Panel
                    </h4>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#dashboard">
                                <i class="fas fa-chart-bar"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#permohonan">
                                <i class="fas fa-list"></i>
                                LogOut
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10">
                <div class="p-4">
                    <h2 class="mb-4">Dashboard Admin - Ruang Tamu Virtual</h2>
                    
                    <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= htmlspecialchars($success_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= htmlspecialchars($error_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <i class="fas fa-file-alt fa-2x mb-2"></i>
                                <h3><?= $stats['total'] ?></h3>
                                <p>Total Permohonan</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <i class="fas fa-clock fa-2x mb-2"></i>
                                <h3><?= $stats['pending'] ?></h3>
                                <p>Menunggu Persetujuan</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <h3><?= $stats['approved'] ?></h3>
                                <p>Disetujui</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <i class="fas fa-calendar-day fa-2x mb-2"></i>
                                <h3><?= $stats['today'] ?></h3>
                                <p>Jadwal Hari Ini</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filter and Search -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">Semua Status</option>
                                        <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Approved</option>
                                        <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                        <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Tanggal Kunjungan</label>
                                    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filter_date) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Pencarian</label>
                                    <input type="text" name="search" class="form-control" placeholder="Nama, NIP, atau Keperluan..." value="<?= htmlspecialchars($search) ?>">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-custom text-white w-100">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Data Table -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Daftar Permohonan Tamu Virtual</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nama</th>
                                            <th>Jabatan</th>
                                            <th>Bertamu Dengan</th>
                                            <th>Tanggal Kunjungan</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($permohonan_list)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">Tidak ada data permohonan</td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($permohonan_list as $permohonan): ?>
                                        <tr>
                                            <td><?= $permohonan['id'] ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($permohonan['nama']) ?></strong><br>
                                                <small class="text-muted">NIP: <?= htmlspecialchars($permohonan['nip']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($permohonan['jabatan']) ?></td>
                                            <td>
                                                <?= htmlspecialchars($permohonan['pejabat_nama']) ?><br>
                                                <small class="text-muted"><?= htmlspecialchars($permohonan['jabatan_lengkap']) ?></small>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($permohonan['tanggal_kunjungan'])) ?></td>
                                            <td>
                                                <span class="status-badge status-<?= $permohonan['status'] ?>">
                                                    <?= ucfirst($permohonan['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewDetail(<?= $permohonan['id'] ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success" onclick="updateStatus(<?= $permohonan['id'] ?>, 'approved')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="updateStatus(<?= $permohonan['id'] ?>, 'rejected')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center mt-3">
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&status=<?= urlencode($filter_status) ?>&date=<?= urlencode($filter_date) ?>&search=<?= urlencode($search) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal untuk Detail Permohonan -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Permohonan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal untuk Update Status -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Status Permohonan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="permohonan_id" id="statusPermohonanId">
                        
                        <div class="mb-3">
                            <label class="form-label">Status Baru</label>
                            <select name="status" id="statusSelect" class="form-select" required>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Catatan</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Tambahkan catatan jika diperlukan..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-custom text-white">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewDetail(id) {
            fetch(`get_detail.php?id=${id}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('detailContent').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('detailModal')).show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat memuat detail');
                });
        }
        
        function updateStatus(id, status) {
            document.getElementById('statusPermohonanId').value = id;
            document.getElementById('statusSelect').value = status;
            new bootstrap.Modal(document.getElementById('statusModal')).show();
        }
        
        // Auto refresh every 30 seconds
        setInterval(() => {
            if (!document.querySelector('.modal.show')) {
                location.reload();
            }
        }, 30000);
        
        // Real-time notifications (WebSocket or Server-Sent Events bisa ditambahkan di sini)
    </script>
</body>
</html>