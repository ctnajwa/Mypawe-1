<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id   = $_SESSION['user_id'];
$admin_name = $_SESSION['user_name'] ?? 'Admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_name    = trim($_POST['class_name'] ?? '');
    $class_date    = trim($_POST['class_date'] ?? '');
    $class_place   = trim($_POST['class_place'] ?? '');
    $class_info    = trim($_POST['class_info'] ?? '');
    $class_involve = trim($_POST['class_involve'] ?? '');
    $org_involved  = trim($_POST['org_involved'] ?? '');
    $class_status  = 'AKTIF';
    $class_time    = '09:00:00'; // Default time slot

    $conn = new mysqli("localhost", "root", "", "mypawe");
    if ($conn->connect_error) {
        die("Sambungan pangkalan data gagal: " . $conn->connect_error);
    }

    // Handle file upload for confirmation letter (class_letter)
    $letter_path = null;
    if (!empty($_FILES['class_letter']['name'])) {
        $upload_dir = __DIR__ . '/../uploads/letters/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
        $ext = pathinfo($_FILES['class_letter']['name'], PATHINFO_EXTENSION);
        $filename = 'letter_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['class_letter']['tmp_name'], $upload_dir . $filename)) {
            $letter_path = 'uploads/letters/' . $filename;
        }
    }

    $stmt = $conn->prepare("INSERT INTO class (staff_id, class_name, class_date, class_place, class_info, class_letter, class_involve, class_status, class_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssss", $admin_id, $class_name, $class_date, $class_place, $class_info, $letter_path, $class_involve, $class_status, $class_time);
    
    if ($stmt->execute()) {
        $_SESSION['admin_msg'] = "Program baru berjaya ditambah.";
        header("Location: admin_view_class.php");
        exit();
    } else {
        $error = "Ralat pangkalan data: " . $stmt->error;
    }
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Program - MyPAWE</title>
    <link rel="stylesheet" href="../Style/style_staff_dashboard.css">
    <link rel="stylesheet" href="../Style/style_admin_member.css">
    <link rel="stylesheet" href="../Style/style_admin_staff.css">
    <style>
        .form-card { max-width: 800px; margin: 0 auto 50px; background: #fff; border-radius: 12px; padding: 35px 40px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 700; color: #2d3748; margin-bottom: 6px; font-size: 14px; }
        .form-control { width: 100%; padding: 11px 14px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 15px; outline: none; box-sizing: border-box; }
        .form-control:focus { border-color: #026aa7; box-shadow: 0 0 0 3px rgba(2, 106, 167, 0.15); }
        .form-actions { display: flex; justify-content: flex-end; margin-top: 15px; }
        .btn-submit { background-color: #0080ff; color: #fff; border: none; padding: 12px 30px; border-radius: 6px; font-weight: 700; font-size: 16px; cursor: pointer; }
        .btn-submit:hover { background-color: #006dd9; }
    </style>
</head>
<body>
    <div class="wrapper">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-logo-placeholder">
                    <img src="../Images/logo_pawe_pekan.jpg" alt="Logo PAWE Pekan" onerror="this.src='https://via.placeholder.com/140x60?text=PAWE+Pekan'"/>
                </div>
            </div>
            <ul class="sidebar-menu">
                <li class="menu-item"><a href="admin_dashboard.php" class="menu-link">Dashboard</a></li>
                <li class="menu-item has-dropdown" id="menuProfil">
                    <div class="menu-link" onclick="toggleMenu('menuProfil', 'subProfil', 'arrowProfil')">
                        <div class="menu-content">
                            <span>Pengurusan Profil</span>
                            <span id="arrowProfil" style="font-size: 12px;">&#9662;</span>
                        </div>
                    </div>
                    <ul class="sub-menu" id="subProfil">
                        <li><a href="admin_personal_profile.php">Profil Peribadi</a></li>
                        <li><a href="admin_view_staff.php">Profil Staf</a></li>
                    </ul>
                </li>
                <li class="menu-item has-dropdown" id="menuAhli">
                    <div class="menu-link" onclick="toggleMenu('menuAhli', 'subAhli', 'arrowAhli')">
                        <div class="menu-content">
                            <span>Pengurusan Ahli</span>
                            <span id="arrowAhli" style="font-size: 12px;">&#9662;</span>
                        </div>
                    </div>
                    <ul class="sub-menu" id="subAhli">
                        <li><a href="admin_view_registration.php">Pendaftaran Ahli</a></li>
                        <li><a href="admin_view_member.php">Profil Ahli</a></li>
                    </ul>
                </li>
                <li class="menu-item active"><a href="admin_view_class.php" class="menu-link">Kelas & Program</a></li>
                <li class="menu-item"><a href="admin_announcement.php" class="menu-link">Pengumuman</a></li>
                <li class="menu-item has-dropdown" id="menuKehadiran">
                    <div class="menu-link" onclick="toggleMenu('menuKehadiran', 'subKehadiran', 'arrowKehadiran')">
                        <div class="menu-content">
                            <span>Kehadiran Ahli</span>
                            <span id="arrowKehadiran" style="font-size: 12px;">&#9662;</span>
                        </div>
                    </div>
                    <ul class="sub-menu" id="subKehadiran">
                        <li><a href="admin_daily_attendance.php">Kehadiran Hari Ini</a></li>
                        <li><a href="admin_attendance_log.php">Log Kehadiran</a></li>
                    </ul>
                </li>
                <li class="menu-item"><a href="admin_report.php" class="menu-link">Laporan</a></li>
            </ul>
            <div class="sidebar-footer"><a href="../logout.php" class="btn-log-keluar">LOG KELUAR</a></div>
        </aside>

        <div class="main-layout">
            <header class="top-navbar">
                <div class="notification-icon">&#128276;</div>
                <div class="user-profile-badge">
                    <div class="avatar-initial"><?php echo strtoupper(substr($admin_name, 0, 1)); ?>.</div>
                    <div class="user-info-text">Welcome,<br><strong><?php echo htmlspecialchars($admin_name); ?></strong></div>
                </div>
            </header>

            <main class="content-body">
                <div class="form-card">
                    <div style="margin-bottom: 25px;">
                        <a href="admin_view_class.php" style="color: #0d1b4c; font-weight: 700; text-decoration: none; font-size: 18px;">&larr;</a>
                        <h2 style="font-size: 26px; color: #0d1b4c; font-weight: 800; text-align: center; margin-top: -10px;">Tambah Program</h2>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div style="background: #fed7d7; color: #9b2c2c; padding: 12px; border-radius: 6px; margin-bottom: 20px;"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form action="admin_add_class.php" method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Nama Program</label>
                            <input type="text" class="form-control" name="class_name" required placeholder="Masukkan nama kelas atau program">
                        </div>
                        <div class="form-group">
                            <label>Tarikh</label>
                            <input type="date" class="form-control" name="class_date" required>
                        </div>
                        <div class="form-group">
                            <label>Tempat</label>
                            <input type="text" class="form-control" name="class_place" required placeholder="Contoh: Dewan PAWE Pekan">
                        </div>
                        <div class="form-group">
                            <label>Maklumat Program</label>
                            <textarea class="form-control" name="class_info" rows="3" placeholder="Penerangan ringkas mengenai program"></textarea>
                        </div>
                        <div class="form-group">
                            <label>PIC Terlibat</label>
                            <input type="text" class="form-control" name="class_involve" placeholder="Nama Pegawai In-Charge">
                        </div>
                        <div class="form-group">
                            <label>Pengajar / Organisasi Terlibat*</label>
                            <input type="text" class="form-control" name="org_involved" placeholder="Nama penceramah atau organisasi luar">
                        </div>
                        <div class="form-group">
                            <label>Muat Naik Surat Pengesahan*</label>
                            <input type="file" class="form-control" name="class_letter" accept=".pdf,.jpg,.png">
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-submit">Tambah Program</button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
</body>
</html>