<?php
session_start();

// Strict Access Guard: Only Admin can access member management
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id   = $_SESSION['user_id'];
$admin_name = $_SESSION['user_name'] ?? 'Admin';

$conn = new mysqli("localhost", "root", "", "mypawe");
if ($conn->connect_error) {
    die("Sambungan pangkalan data gagal: " . $conn->connect_error);
}

// Handle search query
$search = trim($_GET['search'] ?? '');

// Updated SQL query to display ONLY confirmed/active members
$sql = "SELECT m.member_id, m.member_status, m.member_reg_date, 
               u.user_id, u.user_name, u.user_email, u.user_ic, 
               p.public_phone, p.public_gender
        FROM member m
        INNER JOIN public p ON m.public_id = p.public_id
        INNER JOIN user u ON p.public_id = u.user_id
        WHERE (m.member_status = 'Aktif' OR m.member_status = 'Lulus' OR m.member_status = 'Approved')";

if (!empty($search)) {
    $sql .= " AND (u.user_name LIKE ? OR m.member_id LIKE ? OR u.user_ic LIKE ?)";
}

$sql .= " ORDER BY m.member_reg_date DESC";

$stmt = $conn->prepare($sql);
if (!empty($search)) {
    $search_param = "%" . $search . "%";
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
}

$stmt->execute();
$result = $stmt->get_result();

$member_list = [];
while ($row = $result->fetch_assoc()) {
    $member_list[] = $row;
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
    <title>Senarai Ahli PAWE Pekan</title>
    <link rel="stylesheet" href="../Style/style_staff_dashboard.css">
    <link rel="stylesheet" href="../Style/style_admin_member.css">
    <link rel="stylesheet" href="../Style/style_admin_staff.css">
    <style>
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
            background-color: #e8f5e9; 
            color: #28a745;
        }
    </style>
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

                <li class="menu-item has-dropdown active" id="menuAhli">
                    <div class="menu-link" onclick="toggleMenu('menuAhli', 'subAhli', 'arrowAhli')">
                        <div class="menu-content">
                            <span>Pengurusan Ahli</span>
                            <span id="arrowAhli" style="font-size: 12px; transform: rotate(180deg);">&#9662;</span>
                        </div>
                    </div>
                    <ul class="sub-menu open" id="subAhli">
                        <li><a href="admin_view_registration.php">Pendaftaran Ahli</a></li>
                        <li><a href="admin_view_member.php" class="active-sub">Profil Ahli</a></li>
                    </ul>
                </li>

                <li class="menu-item"><a href="admin_view_class.php" class="menu-link">Kelas & Program</a></li>
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

            <div class="sidebar-footer">
                <a href="../logout.php" class="btn-log-keluar">LOG KELUAR</a>
            </div>
        </aside>

        <!-- MAIN LAYOUT -->
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
                    Senarai Ahli PAWE Pekan
                </h1>

                <?php if (!empty($flash_msg)): ?>
                    <div class="alert-box alert-success" style="max-width: 950px; margin: 0 auto 20px; padding: 12px; background: #d4edda; color: #155724; border-radius: 6px;"><?php echo htmlspecialchars($flash_msg); ?></div>
                <?php endif; ?>

                <div class="action-container">
                    <form action="admin_view_member.php" method="GET" class="search-form">
                        <input type="text" name="search" class="search-input" placeholder="Cari nama, No. IC atau ID Ahli" value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn-cari">CARI</button>
                        <?php if (!empty($search)): ?>
                            <a href="admin_view_member.php" class="btn-reset">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="staff-table-wrapper">
                    <table class="member-table">
                        <thead>
                            <tr>
                                <th style="width: 60px; text-align: center;">No.</th>
                                <th>Nama Ahli Sah</th>
                                <th style="width: 140px;">No. Ahli</th>
                                <th style="width: 150px;">No. Telefon</th>
                                <th style="width: 140px; text-align: center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($member_list)): ?>
                                <?php foreach ($member_list as $index => $m): ?>
                                    <tr>
                                        <td style="text-align: center;"><?php echo $index + 1; ?></td>
                                        <td>
                                            <a href="admin_member_profile.php?id=<?php echo urlencode($m['member_id']); ?>" class="link-member">
                                                <?php echo htmlspecialchars($m['user_name']); ?>
                                            </a>
                                            <div style="font-size: 12px; color: #718096;">IC: <?php echo htmlspecialchars($m['user_ic']); ?></div>
                                        </td>
                                        <td><strong>PAWE-<?php echo str_pad($m['member_id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                        <td><?php echo htmlspecialchars($m['public_phone'] ?: '-'); ?></td>
                                        <td style="text-align: center;">
                                            <span class="status-badge">
                                                <?php echo htmlspecialchars($m['member_status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="empty-state" style="text-align: center; padding: 40px; color: #718096;">
                                        Tiada rekod ahli yang sah dijumpai.
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