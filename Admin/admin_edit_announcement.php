<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id   = $_SESSION['user_id'];
$admin_name = $_SESSION['user_name'] ?? 'Admin';
$id         = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: admin_announcement.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "mypawe");
if ($conn->connect_error) {
    die("Sambungan pangkalan data gagal: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['announcement_title'] ?? '');
    $info  = trim($_POST['announcement_info'] ?? '');
    $date  = trim($_POST['announcement_date'] ?? '');

    if (!empty($title) && !empty($info)) {
        // Setting announcement_edited = 1 to trigger the (Edit) label
        $up = $conn->prepare("UPDATE announcement SET announcement_title = ?, announcement_info = ?, announcement_date = ?, announcement_edited = 1 WHERE announcement_id = ?");
        $up->bind_param("sssi", $title, $info, $date, $id);
        $up->execute();
        $up->close();
        $conn->close();

        $_SESSION['admin_msg'] = "Pengumuman berjaya dikemaskini.";
        header("Location: admin_announcement.php");
        exit();
    }
    $error = "Sila lengkapkan semua medan.";
}

$stmt = $conn->prepare("SELECT * FROM announcement WHERE announcement_id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$announcement = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$announcement) {
    header("Location: admin_announcement.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pengumuman - MyPAWE</title>
    <link rel="stylesheet" href="../Style/style_staff_dashboard.css">
    <link rel="stylesheet" href="../Style/style_admin_member.css">
    <link rel="stylesheet" href="../Style/style_admin_staff.css">
    <style>
        .form-card { max-width: 800px; margin: 0 auto 50px; background: #fff; border-radius: 12px; padding: 35px 40px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 700; color: #2d3748; margin-bottom: 6px; font-size: 14px; }
        .form-control { width: 100%; padding: 11px 14px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 15px; outline: none; box-sizing: border-box; }
        .form-control:focus { border-color: #026aa7; }
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
                    <img src="../Images/logo_pawe_pekan.jpg" alt="Logo PAWE Pekan" class="logo-placeholder" onerror="this.src='https://via.placeholder.com/140x60?text=PAWE+Pekan'"/>
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
                        <a href="admin_announcement.php" style="color: #0d1b4c; font-weight: 700; text-decoration: none; font-size: 18px;">&larr;</a>
                        <h2 style="font-size: 26px; color: #0d1b4c; font-weight: 800; text-align: center; margin-top: -10px;">Edit Pengumuman</h2>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div style="background: #fed7d7; color: #9b2c2c; padding: 12px; border-radius: 6px; margin-bottom: 20px;"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form action="admin_edit_announcement.php?id=<?php echo $id; ?>" method="POST">
                        <div class="form-group">
                            <label>Tajuk Pengumuman</label>
                            <input type="text" class="form-control" name="announcement_title" value="<?php echo htmlspecialchars($announcement['announcement_title']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Tarikh</label>
                            <input type="date" class="form-control" name="announcement_date" value="<?php echo htmlspecialchars($announcement['announcement_date']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Kandungan Pengumuman</label>
                            <textarea class="form-control" name="announcement_info" rows="5" required><?php echo htmlspecialchars($announcement['announcement_info']); ?></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-submit">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
</body>
</html>