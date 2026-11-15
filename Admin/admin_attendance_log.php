<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Admin' && $_SESSION['user_role'] !== 'Staf')) {
    header("Location: ../login.php");
    exit();
}

$admin_name = $_SESSION['user_name'] ?? 'Staff/Admin';
$conn = new mysqli("localhost", "root", "", "mypawe");
if ($conn->connect_error) {
    die("Sambungan pangkalan data gagal: " . $conn->connect_error);
}

// Pagination & Fetching Past Classes / General Days
$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = 5; 
$offset = ($page - 1) * $limit;
$today  = date('Y-m-d');

// Count total past classes
$count_res = $conn->query("SELECT COUNT(*) as total FROM class WHERE class_date < '$today'");
$total_records = $count_res->fetch_assoc()['total'];
$total_pages = max(1, ceil($total_records / $limit));

// Fetch paginated past classes
$sql = "SELECT c.class_id, c.class_name, c.class_date, c.class_involve 
        FROM class c 
        WHERE c.class_date < '$today' 
        ORDER BY c.class_date DESC 
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $offset, $limit);
$stmt->execute();
$result = $stmt->get_result();

$classes = [];
while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Kehadiran - MyPAWE</title>
    <link rel="stylesheet" href="../Style/style_staff_dashboard.css">
    <link rel="stylesheet" href="../Style/style_admin_member.css">
    <link rel="stylesheet" href="../Style/style_admin_staff.css">
    <style>
        .content-body { width: 100%; max-width: 1250px; box-sizing: border-box; margin: 0 auto; padding: 30px 30px; }
        .member-table { width: 100%; max-width: none; }
        .page-heading { font-size: 32px; font-weight: 800; color: #0d1b4c; text-align: center; margin-bottom: 30px; }
        .class-link { color: #2b6cb0; text-decoration: none; font-weight: 700; }
        .class-link:hover { text-decoration: underline; color: #1a365d; }
        .pagination-container { display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 30px; }
        .page-num { display: inline-flex; width: 32px; height: 32px; align-items: center; justify-content: center; border-radius: 50%; background: #e2e8f0; color: #2d3748; font-weight: 700; text-decoration: none; }
        .page-num.active { background: #0d1b4c; color: #fff; }
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
                <h1 class="page-heading">Log Kehadiran</h1>

                <div class="staff-table-wrapper" style="max-width: 100%;">
                    <table class="member-table">
                        <thead>
                            <tr>
                                <th style="width: 150px;">Tarikh</th>
                                <th>Nama Kelas</th>
                                <th style="width: 250px;">PIC Terlibat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($classes)): ?>
                                <?php foreach ($classes as $c): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($c['class_date'])); ?></td>
                                        <td>
                                            <a href="admin_attendance_detail.php?id=<?php echo $c['class_id']; ?>" class="class-link">
                                                <?php echo htmlspecialchars($c['class_name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($c['class_involve'] ?: '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="empty-state" style="text-align: center; padding: 40px; color: #718096;">
                                        Tiada rekod log kelas lepas dijumpai.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="admin_attendance_log.php?page=<?php echo $i; ?>" class="page-num <?php echo ($page === $i) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
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