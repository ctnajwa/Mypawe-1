<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id   = $_SESSION['user_id'];
$admin_name = $_SESSION['user_name'] ?? 'Admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['announcement_title'] ?? '');
    $info  = trim($_POST['announcement_info'] ?? '');
    $date  = trim($_POST['announcement_date'] ?? date('Y-m-d'));

    if (!empty($title) && !empty($info)) {
        $conn = new mysqli("localhost", "root", "", "mypawe");
        if (!$conn->connect_error) {
            $stmt = $conn->prepare("INSERT INTO announcement (staff_id, announcement_title, announcement_info, announcement_date, announcement_edited) VALUES (?, ?, ?, ?, 0)");
            $stmt->bind_param("ssss", $admin_id, $title, $info, $date);
            $stmt->execute();
            $stmt->close();
            $conn->close();

            $_SESSION['admin_msg'] = "Pengumuman baru berjaya ditambah.";
            header("Location: admin_announcement.php");
            exit();
        }
    }
    $error = "Sila lengkapkan semua medan yang diperlukan.";
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pengumuman - MyPAWE</title>
    <link rel="stylesheet" href="../Style/style_staff_dashboard.css">
    <link rel="stylesheet" href="../Style/style_admin_member.css">
    <link rel="stylesheet" href="../Style/style_admin_staff.css">
    <style>
        .form-card { max-width: 800px; margin: 0 auto 50px; background: #fff; border-radius: 12px; padding: 35px 40px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 700; color: #2d3748; margin-bottom: 6px; font-size: 14px; }
        .form-control { width: 100%; padding: 11px 14px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 15px; outline: none; box-sizing: border-box; }
        .form-control:focus { border-color: #026aa7; }
        .btn-submit { background-color: #0080ff; color: #fff; border: none; padding: 12px 30px; border-radius: 6px; font-weight: 700; font-size: 16px; cursor: pointer; float: right; margin-top: 15px; }
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
                <li class="menu-item"><a href="admin_view_class.php" class="menu-link">Kelas & Program</a></li>
                <li class="menu-item active"><a href="admin_announcement.php" class="menu-link">Pengumuman</a></li>
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
                        <a href="admin_announcement.php" style="color: #0d1b4c; font-weight: 700; text-decoration: none; font-size: 18px;">&larr;</a>
                        <h2 style="font-size: 26px; color: #0d1b4c; font-weight: 800; text-align: center; margin-top: -10px;">Tambah Pengumuman</h2>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div style="background: #fed7d7; color: #9b2c2c; padding: 12px; border-radius: 6px; margin-bottom: 20px;"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form action="admin_add_announcement.php" method="POST">
                        <div class="form-group">
                            <label>Tajuk Pengumuman</label>
                            <input type="text" class="form-control" name="announcement_title" required placeholder="Masukkan tajuk pengumuman">
                        </div>
                        <div class="form-group">
                            <label>Tarikh</label>
                            <input type="date" class="form-control" name="announcement_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Kandungan Pengumuman</label>
                            <textarea class="form-control" name="announcement_info" rows="5" required placeholder="Tulis butiran pengumuman di sini..."></textarea>
                        </div>
                        <div style="clear: both;">
                            <button type="submit" class="btn-submit">Tambah Pengumuman</button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
</body>
</html>