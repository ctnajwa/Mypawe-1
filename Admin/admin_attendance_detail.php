<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Admin' && $_SESSION['user_role'] !== 'Staf')) {
    header("Location: ../login.php");
    exit();
}

$admin_name = $_SESSION['user_name'] ?? 'Staff/Admin';
$class_id   = intval($_GET['id'] ?? 0);

if ($class_id <= 0) {
    header("Location: admin_attendance_log.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "mypawe");
if ($conn->connect_error) {
    die("Sambungan pangkalan data gagal: " . $conn->connect_error);
}

// Fetch class info
$c_stmt = $conn->prepare("SELECT class_name, class_date FROM class WHERE class_id = ? LIMIT 1");
$c_stmt->bind_param("i", $class_id);
$c_stmt->execute();
$class_info = $c_stmt->get_result()->fetch_assoc();
$c_stmt->close();

if (!$class_info) {
    $conn->close();
    header("Location: admin_attendance_log.php");
    exit();
}

// Fetch attendance list for this class
$att_sql = "SELECT u.user_name, u.user_ic, a.attendance_time 
            FROM attendance a 
            JOIN member m ON a.member_id = m.member_id
            JOIN public p ON m.public_id = p.public_id
            JOIN user u ON p.public_id = u.user_id
            WHERE a.class_id = ? 
            ORDER BY a.attendance_time ASC";
$att_stmt = $conn->prepare($att_sql);
$att_stmt->bind_param("i", $class_id);
$att_stmt->execute();
$att_res = $att_stmt->get_result();

$attendees = [];
while ($row = $att_res->fetch_assoc()) {
    $attendees[] = $row;
}
$att_stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kehadiran - <?php echo htmlspecialchars($class_info['class_name']); ?></title>
    <link rel="stylesheet" href="../Style/style_staff_dashboard.css">
    <link rel="stylesheet" href="../Style/style_admin_member.css">
    <link rel="stylesheet" href="../Style/style_admin_staff.css">
    <style>
        .content-body { width: 100%; max-width: 1250px; box-sizing: border-box; margin: 0 auto; padding: 30px 30px; }
        .member-table { width: 100%; max-width: none; }
        .page-heading { font-size: 30px; font-weight: 800; color: #0d1b4c; text-align: center; margin-bottom: 5px; text-transform: uppercase; }
        .page-subheading { font-size: 16px; font-weight: 700; color: #718096; text-align: center; margin-bottom: 25px; }
        .btn-back { display: inline-block; color: #0d1b4c; font-weight: 700; text-decoration: none; font-size: 18px; margin-bottom: 15px; }
        .btn-back:hover { text-decoration: underline; }
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
                    <ul class="sub-menu open" id="subKehadiran">
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
                <div>
                    <a href="admin_attendance_log.php" class="btn-back">&larr; Kembali</a>
                </div>
                
                <h1 class="page-heading"><?php echo htmlspecialchars($class_info['class_name']); ?></h1>
                <div class="page-subheading"><?php echo date('d/m/Y', strtotime($class_info['class_date'])); ?></div>

                <div class="staff-table-wrapper" style="max-width: 100%;">
                    <table class="member-table">
                        <thead>
                            <tr>
                                <th style="width: 70px; text-align: center;">No.</th>
                                <th>Member Name</th>
                                <th style="width: 220px;">IC Number</th>
                                <th style="width: 180px;">Check-in Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($attendees)): ?>
                                <?php foreach ($attendees as $index => $att): ?>
                                    <tr>
                                        <td style="text-align: center;"><?php echo $index + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($att['user_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($att['user_ic']); ?></td>
                                        <td><?php echo date('h:i A', strtotime($att['attendance_time'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="empty-state" style="text-align: center; padding: 40px; color: #718096;">
                                        Tiada rekod ahli hadir untuk kelas ini.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>
    <script>
        function toggleMenu(parentId, subId, arrowId) {
            var parentItem = document.getElementById(parentId);
            var subMenu = document.getElementById(subId);
            var arrow = document.getElementById(arrowId);
            parentItem.classList.toggle('active');
            subMenu.classList.toggle('open');
            if (subMenu.classList.contains('open')) { arrow.style.transform = 'rotate(180deg)'; } else { arrow.style.transform = 'rotate(0deg)'; }
        }
    </script>
</body>
</html>