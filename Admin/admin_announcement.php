<?php
session_start();

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

// Handle Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $del_id = intval($_POST['delete_id']);
    $del_stmt = $conn->prepare("DELETE FROM announcement WHERE announcement_id = ?");
    $del_stmt->bind_param("i", $del_id);
    $del_stmt->execute();
    $del_stmt->close();
    $_SESSION['admin_msg'] = "Pengumuman telah berjaya dibuang.";
    header("Location: admin_announcement.php");
    exit();
}

// Fetch all announcements
$sql = "SELECT * FROM announcement ORDER BY announcement_date DESC, announcement_id DESC";
$result = $conn->query($sql);
$announcements = [];
while ($row = $result->fetch_assoc()) {
    $announcements[] = $row;
}
$conn->close();

$flash_msg = $_SESSION['admin_msg'] ?? '';
unset($_SESSION['admin_msg']);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengurusan Pengumuman - MyPAWE</title>
    <link rel="stylesheet" href="../Style/style_staff_dashboard.css">
    <link rel="stylesheet" href="../Style/style_admin_member.css">
    <link rel="stylesheet" href="../Style/style_admin_staff.css">
    <style>
        .content-body { width: 100%; max-width: 1250px; box-sizing: border-box; margin: 0 auto; padding: 30px 30px; }
        .member-table { width: 100%; max-width: none; }
        .page-heading { font-size: 32px; font-weight: 800; color: #0d1b4c; text-align: center; margin-bottom: 25px; }
        .action-bar { display: flex; justify-content: flex-end; margin-bottom: 25px; }
        .btn-tambah {
            background-color: #0d4669;
            color: #ffffff;
            padding: 12px 22px;
            border-radius: 8px;
            font-weight: 700;
            text-decoration: none;
            transition: background 0.2s;
        }
        .btn-tambah:hover { background-color: #09324d; }
        .btn-edit {
            background-color: #3182ce;
            color: #ffffff;
            padding: 5px 14px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            display: inline-block;
        }
        .btn-edit:hover { background-color: #2b6cb0; }
        .btn-buang {
            background-color: #fc8181;
            color: #ffffff;
            border: none;
            padding: 5px 14px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            margin-left: 4px;
        }
        .btn-buang:hover { background-color: #e53e3e; }
        .edited-tag {
            font-size: 11px;
            font-weight: 700;
            color: #d69e2e;
            background: #fefcbf;
            padding: 2px 6px;
            border-radius: 4px;
            margin-right: 6px;
            display: inline-block;
        }
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
                <h1 class="page-heading">Pengurusan Pengumuman</h1>

                <?php if (!empty($flash_msg)): ?>
                    <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
                        <?php echo htmlspecialchars($flash_msg); ?>
                    </div>
                <?php endif; ?>

                <div class="action-bar">
                    <a href="admin_add_announcement.php" class="btn-tambah">+ Tambah Pengumuman</a>
                </div>

                <div class="staff-table-wrapper" style="max-width: 100%;">
                    <table class="member-table">
                        <thead>
                            <tr>
                                <th style="width: 60px; text-align: center;">No.</th>
                                <th>Tajuk & Kandungan Pengumuman</th>
                                <th style="width: 150px;">Tarikh</th>
                                <th style="width: 170px; text-align: center;">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($announcements)): ?>
                                <?php foreach ($announcements as $index => $a): ?>
                                    <tr>
                                        <td style="text-align: center;"><?php echo $index + 1; ?></td>
                                        <td>
                                            <?php if ((int)$a['announcement_edited'] === 1): ?>
                                                <span class="edited-tag">(Edit)</span>
                                            <?php endif; ?>
                                            <strong><?php echo htmlspecialchars($a['announcement_title']); ?></strong>
                                            <div style="font-size: 13px; color: #4a5568; margin-top: 4px;"><?php echo nl2br(htmlspecialchars($a['announcement_info'])); ?></div>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($a['announcement_date'])); ?></td>
                                        <td style="text-align: center;">
                                            <a href="admin_edit_announcement.php?id=<?php echo $a['announcement_id']; ?>" class="btn-edit">Edit</a>
                                            <form action="admin_announcement.php" method="POST" style="display:inline-block;">
                                                <input type="hidden" name="delete_id" value="<?php echo $a['announcement_id']; ?>">
                                                <button type="submit" class="btn-buang" onclick="return confirm('Adakah anda pasti ingin membuang pengumuman ini?');">Buang</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="empty-state" style="text-align: center; padding: 40px; color: #718096;">
                                        Tiada rekod pengumuman dijumpai.
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