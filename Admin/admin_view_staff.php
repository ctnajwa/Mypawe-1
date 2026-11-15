<?php
session_start();

// Strict Access Guard: Only logged-in Admin can access this directory
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id   = $_SESSION['user_id'];
$admin_name = $_SESSION['user_name'] ?? 'Admin';

// Database connection
$conn = new mysqli("localhost", "root", "", "mypawe");
if ($conn->connect_error) {
    die("Sambungan pangkalan data gagal: " . $conn->connect_error);
}

// Handle search query
$search = trim($_GET['search'] ?? '');

$sql = "SELECT u.user_id, u.user_name, u.user_email, u.user_role, s.staff_phone, s.staff_education 
        FROM user u 
        LEFT JOIN staff s ON u.user_id = s.staff_id 
        WHERE (u.user_role = 'Staf' OR u.user_role = 'Admin')";

if (!empty($search)) {
    $sql .= " AND (u.user_name LIKE ? OR u.user_id LIKE ?)";
}

$sql .= " ORDER BY u.user_id ASC";

$stmt = $conn->prepare($sql);
if (!empty($search)) {
    $search_param = "%" . $search . "%";
    $stmt->bind_param("ss", $search_param, $search_param);
}

$stmt->execute();
$result = $stmt->get_result();

$staff_list = [];
while ($row = $result->fetch_assoc()) {
    $staff_list[] = $row;
}

$stmt->close();
$conn->close();

$flash_msg = $_SESSION['admin_msg'] ?? '';
unset($_SESSION['admin_msg']);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Senarai Staf PAWE Pekan - MyPAWE</title>
    <link rel="stylesheet" href="../Style/style_staff_dashboard.css">
    <link rel="stylesheet" href="../Style/style_admin_member.css">
    <link rel="stylesheet" href="../Style/style_admin_staff.css">
</head>
<body>

    <div class="wrapper">
        
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-logo-placeholder">
                    <img src="../Images/logo_pawe_pekan.jpg" alt="Logo PAWE Pekan" class="logo-placeholder" onerror="this.src='https://via.placeholder.com/140x60?text=PAWE+Pekan'"/>
                </div>
            </div>

            <ul class="sidebar-menu">
                <li class="menu-item">
                    <a href="admin_dashboard.php" class="menu-link">Dashboard</a>
                </li>
                
                <!-- Pengurusan Profil -->
                <li class="menu-item has-dropdown active" id="menuProfil">
                    <div class="menu-link" onclick="toggleMenu('menuProfil', 'subProfil', 'arrowProfil')">
                        <div class="menu-content">
                            <span>Pengurusan Profil</span>
                            <span id="arrowProfil" style="font-size: 12px; transform: rotate(180deg);">&#9662;</span>
                        </div>
                    </div>
                    <ul class="sub-menu open" id="subProfil">
                        <li><a href="admin_personal_profile.php">Profil Peribadi</a></li>
                        <li><a href="admin_view_staff.php" class="active-sub">Profil Staf</a></li>
                    </ul>
                </li>

                <!-- Pengurusan Ahli -->
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

                <li class="menu-item">
                    <a href="admin_view_class.php" class="menu-link">Kelas & Program</a>
                </li>
                
                <li class="menu-item">
                    <a href="admin_announcement.php" class="menu-link">Pengumuman</a>
                </li>
                
                <!-- Kehadiran Ahli -->
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

                <li class="menu-item">
                    <a href="admin_report.php" class="menu-link">Laporan</a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <a href="../logout.php" class="btn-log-keluar">LOG KELUAR</a>
            </div>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <div class="main-layout">
            <header class="top-navbar">
                <div class="notification-icon">&#128276;</div>
                <div class="user-profile-badge">
                    <div class="avatar-initial"><?php echo strtoupper(substr($admin_name, 0, 1)); ?>.</div>
                    <div class="user-info-text">Welcome,<br><strong><?php echo htmlspecialchars($admin_name); ?></strong></div>
                </div>
            </header>

            <main class="content-body">
                <h1 style="font-size: 30px; font-weight: 800; margin-bottom: 25px; text-align: center; color: #0d1b4c;">
                    Senarai Staf PAWE Pekan
                </h1>

                <?php if (!empty($flash_msg)): ?>
                    <div class="alert-box alert-success">
                        <?php echo htmlspecialchars($flash_msg); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Action Container: Search & Add Staff Button -->
                <div class="action-container">
                    <form action="admin_view_staff.php" method="GET" class="search-form">
                        <input 
                            type="text" 
                            name="search" 
                            class="search-input" 
                            placeholder="Cari mengikut nama atau ID staf"
                            value="<?php echo htmlspecialchars($search); ?>"
                        >
                        <button type="submit" class="btn-cari">CARI</button>
                        <?php if (!empty($search)): ?>
                            <a href="admin_view_staff.php" class="btn-reset">Reset</a>
                        <?php endif; ?>
                    </form>
                    <a href="admin_add_staff.php" class="btn-tambah-staf">+ Tambah Staf Baru</a>
                </div>

                <!-- Staff Table -->
                <div class="staff-table-wrapper">
                    <table class="member-table">
                        <thead>
                            <tr>
                                <th style="width: 70px; text-align: center;">No.</th>
                                <th>Nama Staf</th>
                                <th style="width: 150px;">ID Staf</th>
                                <th style="width: 180px;">No. Telefon</th>
                                <th style="width: 130px; text-align: center;">Peranan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($staff_list)): ?>
                                <?php foreach ($staff_list as $index => $staff): ?>
                                    <tr>
                                        <td style="text-align: center;"><?php echo $index + 1; ?></td>
                                        <td>
                                            <a href="admin_staff_profile.php?id=<?php echo urlencode($staff['user_id']); ?>" class="link-member">
                                                <?php echo htmlspecialchars($staff['user_name']); ?>
                                            </a>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($staff['user_id']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($staff['staff_phone'] ?: '-'); ?></td>
                                        <td style="text-align: center;">
                                            <span class="role-badge <?php echo strtolower($staff['user_role']); ?>">
                                                <?php echo htmlspecialchars($staff['user_role']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="empty-state">
                                        <?php if (!empty($search)): ?>
                                            Tiada staf dijumpai dengan carian "<strong><?php echo htmlspecialchars($search); ?></strong>".
                                        <?php else: ?>
                                            Tiada rekod staf didaftarkan dalam sistem setakat ini.
                                        <?php endif; ?>
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
            if (subMenu.classList.contains('open')) {
                arrow.style.transform = 'rotate(180deg)';
            } else {
                arrow.style.transform = 'rotate(0deg)';
            }
        }
    </script>
</body>
</html>