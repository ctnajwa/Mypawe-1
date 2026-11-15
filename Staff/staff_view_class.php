<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Staf') {
    header("Location: ../login.php");
    exit();
}

$staff_name = $_SESSION['user_name'] ?? 'Staff';
$conn = new mysqli("localhost", "root", "", "mypawe");
if ($conn->connect_error) {
    die("Sambungan pangkalan data gagal: " . $conn->connect_error);
}

// Fetch all classes
$sql = "SELECT * FROM class ORDER BY class_date DESC";
$result = $conn->query($sql);
$classes = [];
while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}
$conn->close();

$flash_msg = $_SESSION['staff_msg'] ?? '';
unset($_SESSION['staff_msg']);
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengurusan Kelas & Program - MyPAWE Staf</title>
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
        .btn-lihat {
            background-color: #3182ce;
            color: #ffffff;
            padding: 5px 14px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            display: inline-block;
        }
        .btn-lihat:hover { background-color: #2b6cb0; }
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
        .badge-aktif { background: #c6f6d5; color: #22543d; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 700; }
        .badge-selesai { background: #e2e8f0; color: #4a5568; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 700; }
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
                <li class="menu-item active">
                    <a href="staff_dashboard.php" class="menu-link">Dashboard</a>
                </li>
                <li class="menu-item">
                    <a href="staff_personal_profile.php" class="menu-link">Profil Peribadi</a>
                </li>
                <li class="menu-item">
                    <a href="staff_view_member.php" class="menu-link">Pengurusan Ahli</a>
                </li>
                <li class="menu-item">
                    <a href="staff_view_class.php" class="menu-link">Kelas & Program</a>
                </li>
                <li class="menu-item">
                    <a href="staff_announcement.php" class="menu-link">Pengumuman</a>
                </li>
                
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

                <li class="menu-item">
                    <a href="staff_report.php" class="menu-link">Laporan</a>
                </li>
            </ul>
            <div class="sidebar-footer"><a href="../logout.php" class="btn-log-keluar">LOG KELUAR</a></div>
        </aside>

        <div class="main-layout">
            <header class="top-navbar">
                <div class="notification-icon">&#128276;</div>
                <div class="user-profile-badge">
                    <div class="avatar-initial"><?php echo strtoupper(substr($staff_name, 0, 1)); ?>.</div>
                    <div class="user-info-text">Welcome,<br><strong><?php echo htmlspecialchars($staff_name); ?></strong></div>
                </div>
            </header>

            <main class="content-body">
                <h1 class="page-heading">Pengurusan Kelas & Program</h1>

                <?php if (!empty($flash_msg)): ?>
                    <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
                        <?php echo htmlspecialchars($flash_msg); ?>
                    </div>
                <?php endif; ?>

                <div class="action-bar">
                    <a href="staff_add_class.php" class="btn-tambah">+ Tambah Program Baru</a>
                </div>

                <div class="staff-table-wrapper" style="max-width: 100%;">
                    <table class="member-table">
                        <thead>
                            <tr>
                                <th style="width: 60px; text-align: center;">No.</th>
                                <th>Nama Program</th>
                                <th style="width: 130px;">Tarikh</th>
                                <th style="width: 130px; text-align: center;">Status</th>
                                <th style="width: 170px; text-align: center;">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($classes)): ?>
                                <?php foreach ($classes as $index => $c): 
                                    $is_passed = ($c['class_date'] < $today);
                                ?>
                                    <tr>
                                        <td style="text-align: center;"><?php echo $index + 1; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($c['class_name']); ?></strong>
                                            <div style="font-size: 13px; color: #4a5568; margin-top: 2px;"> <?php echo htmlspecialchars($c['class_place']); ?></div>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($c['class_date'])); ?></td>
                                        <td style="text-align: center;">
                                            <?php if ($is_passed): ?>
                                                <span class="badge-selesai">SELESAI</span>
                                            <?php else: ?>
                                                <span class="badge-aktif">AKTIF</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <a href="staff_class_detail.php?id=<?php echo $c['class_id']; ?>" class="btn-lihat">Lihat</a>
                                            <?php if (!$is_passed): ?>
                                                <form action="staff_delete_class_action.php" method="POST" style="display:inline-block;">
                                                    <input type="hidden" name="delete_class_id" value="<?php echo $c['class_id']; ?>">
                                                    <button type="submit" class="btn-buang" onclick="return confirm('Adakah anda pasti ingin membuang program ini?');">Buang</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="empty-state" style="text-align: center; padding: 40px; color: #718096;">
                                        Tiada rekod program dijumpai.
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