<?php
session_start();

// Strict Access Guard: Only logged-in Staff can access member management
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Staf') {
    header("Location: ../login.php");
    exit();
}

$staff_id   = $_SESSION['user_id'];
$staff_name = $_SESSION['user_name'] ?? 'Staf';

$conn = new mysqli("localhost", "root", "", "mypawe");
if ($conn->connect_error) {
    die("Sambungan pangkalan data gagal: " . $conn->connect_error);
}

// Forced Password Change Guard Clause
$g_stmt = $conn->prepare("SELECT staff_change_password FROM staff WHERE staff_id = ? LIMIT 1");
$g_stmt->bind_param("s", $staff_id);
$g_stmt->execute();
$g_res = $g_stmt->get_result()->fetch_assoc();
$g_stmt->close();

if ($g_res && (int)$g_res['staff_change_password'] === 1) {
    header("Location: change_password.php");
    exit();
}

// Handle search query
$search = trim($_GET['search'] ?? '');

// Fetch ONLY confirmed/active members
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
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Senarai Ahli Sah PAWE Pekan - MyPAWE</title>
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
                <div class="logo-section">
                    <img src="../Images/logo_pawe_pekan.jpg" alt="Logo PAWE Pekan" class="logo-placeholder" onerror="this.src='https://via.placeholder.com/140x60?text=PAWE+Pekan'"/>
                </div>
            </div>

            <ul class="sidebar-menu">
                <li class="menu-item"><a href="staff_dashboard.php" class="menu-link">Dashboard</a></li>
                <li class="menu-item"><a href="staff_personal_profile.php" class="menu-link">Profil Peribadi</a></li>
                <li class="menu-item active"><a href="staff_view_member.php" class="menu-link">Pengurusan Ahli</a></li>
                <li class="menu-item"><a href="staff_view_class.php" class="menu-link">Kelas & Program</a></li>
                <li class="menu-item"><a href="staff_announcement.php" class="menu-link">Pengumuman</a></li>
                
                <li class="menu-item has-dropdown" id="attendanceDropdown">
                    <div class="menu-link" onclick="toggleSubMenu()" aria-expanded="false">
                        <span>Kehadiran Ahli</span>
                        <span id="dropdownArrow" style="font-size: 12px; transition: transform 0.2s;">&#9662;</span>
                    </div>
                    <ul class="sub-menu" id="subKehadiran">
                        <li><a href="staff_daily_attendance.php" style="color: #ffffff;">Kehadiran Hari Ini</a></li>
                        <li><a href="staff_attendance_log.php" style="color: #ffffff;">Log Kehadiran</a></li>
                    </ul>
                </li>

                <li class="menu-item"><a href="staff_report.php" class="menu-link">Laporan</a></li>
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
                    <div class="avatar-initial"><?php echo strtoupper(substr($staff_name, 0, 1)); ?>.</div>
                    <div class="user-info-text">Welcome,<br><strong><?php echo htmlspecialchars($staff_name); ?></strong></div>
                </div>
            </header>

            <main class="content-body">
                <h1 style="font-size: 30px; font-weight: 800; margin-bottom: 25px; text-align: center; color: #0d1b4c;">
                    Senarai Ahli Sah PAWE Pekan
                </h1>

                <div class="action-container">
                    <form action="staff_view_member.php" method="GET" class="search-form">
                        <input type="text" name="search" class="search-input" placeholder="Cari nama, No. IC atau ID Ahli" value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn-cari">CARI</button>
                        <?php if (!empty($search)): ?>
                            <a href="staff_view_member.php" class="btn-reset">Reset</a>
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
                                            <!-- Points to the shared member profile view script -->
                                            <a href="staff_member_profile.php?id=<?php echo urlencode($m['member_id']); ?>" class="link-member">
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
        function toggleSubMenu() {
            var dropdownContainer = document.getElementById('attendanceDropdown');
            var subMenu = document.getElementById('subKehadiran');
            var trigger = document.querySelector('#attendanceDropdown .menu-link');
            var arrow = document.getElementById('dropdownArrow');

            if (!subMenu) return;

            var isOpen = subMenu.classList.contains('open');
            subMenu.classList.toggle('open', !isOpen);
            dropdownContainer.classList.toggle('active', !isOpen);

            if (trigger) {
                trigger.setAttribute('aria-expanded', (!isOpen) ? 'true' : 'false');
            }

            arrow.style.transform = !isOpen ? 'rotate(180deg)' : 'rotate(0deg)';
        }
    </script>
</body>
</html>