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

$today = date('Y-m-d');

// Handle Manual Check-in by Staff via IC number input
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_ic'])) {
    $ic = trim($_POST['manual_ic']);
    
    // Find member by IC
    $stmt = $conn->prepare("SELECT m.member_id FROM member m 
                            JOIN public p ON m.public_id = p.public_id 
                            JOIN user u ON p.public_id = u.user_id 
                            WHERE u.user_ic = ? AND (m.member_status = 'Aktif' OR m.member_status = 'Approved') LIMIT 1");
    $stmt->bind_param("s", $ic);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        $member_id = $row['member_id'];
        
        // Find today's active class or create a general attendance entry if class_id can be null or defaulted
        $c_q = $conn->prepare("SELECT class_id FROM class WHERE class_date = ? LIMIT 1");
        $c_q->bind_param("s", $today);
        $c_q->execute();
        $c_res = $c_q->get_result()->fetch_assoc();
        $class_id = $c_res ? $c_res['class_id'] : null;
        $c_q->close();

        if ($class_id) {
            // Check if already checked in today for this class
            $chk = $conn->prepare("SELECT attendance_id FROM attendance WHERE member_id = ? AND class_id = ? LIMIT 1");
            $chk->bind_param("ii", $member_id, $class_id);
            $chk->execute();
            if ($chk->get_result()->num_rows === 0) {
                $ins = $conn->prepare("INSERT INTO attendance (member_id, class_id, attendance_time) VALUES (?, ?, NOW())");
                $ins->bind_param("ii", $member_id, $class_id);
                $ins->execute();
                $ins->close();
                $_SESSION['att_msg'] = "Kehadiran berjaya direkodkan.";
            } else {
                $_SESSION['att_msg'] = "Ahli ini telah direkodkan hadir hari ini.";
            }
            $chk->close();
        } else {
            $_SESSION['att_msg'] = "Tiada program atau kelas berjadual pada hari ini untuk didaftar kehadiran.";
        }
    } else {
        $_SESSION['att_msg'] = "Ralat: No. KP tidak dijumpai atau status ahli tidak aktif.";
    }
    $stmt->close();
    header("Location: staff_daily_attendance.php");
    exit();
}

// Fetch live counter attendance log for today
$log_sql = "SELECT u.user_name, u.user_ic, a.attendance_time, c.class_name 
            FROM attendance a 
            JOIN member m ON a.member_id = m.member_id
            JOIN public p ON m.public_id = p.public_id
            JOIN user u ON p.public_id = u.user_id
            JOIN class c ON a.class_id = c.class_id
            WHERE DATE(a.attendance_time) = ? 
            ORDER BY a.attendance_time DESC";
$log_stmt = $conn->prepare($log_sql);
$log_stmt->bind_param("s", $today);
$log_stmt->execute();
$log_result = $log_stmt->get_result();
$attendance_logs = [];
while ($r = $log_result->fetch_assoc()) {
    $attendance_logs[] = $r;
}
$log_stmt->close();
$conn->close();

$flash = $_SESSION['att_msg'] ?? '';
unset($_SESSION['att_msg']);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kehadiran Hari Ini - MyPAWE Staf</title>
    <link rel="stylesheet" href="../Style/style_staff_dashboard.css">
    <link rel="stylesheet" href="../Style/style_admin_member.css">
    <link rel="stylesheet" href="../Style/style_admin_staff.css">
    <style>
        .content-body { width: 100%; max-width: 1250px; box-sizing: border-box; margin: 0 auto; padding: 30px 30px; }
        .member-table { width: 100%; max-width: none; }
        .page-header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-heading { font-size: 32px; font-weight: 800; color: #0d1b4c; margin: 0; }
        .btn-kiosk-banner {
            background-color: #fef08a;
            color: #713f12;
            border: 2px solid #facc15;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 800;
            font-size: 13px;
            text-align: center;
            text-decoration: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: background 0.2s;
        }
        .btn-kiosk-banner:hover { background-color: #fde047; }
        
        .checkin-card {
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            border: 1px solid #cbd5e0;
            margin-bottom: 30px;
        }
        .checkin-form { display: flex; gap: 15px; align-items: center; max-width: 600px; margin-top: 10px; }
        .checkin-input { flex: 1; padding: 12px 15px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 15px; outline: none; }
        .btn-checkin-submit { background-color: #0099ff; color: #fff; border: none; padding: 12px 25px; border-radius: 6px; font-weight: 700; cursor: pointer; }
        .btn-checkin-submit:hover { background-color: #0080dd; }
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
                    <ul class="sub-menu open" id="subKehadiran">
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
                <div class="page-header-row">
                    <div>
                        <h1 class="page-heading">Kehadiran Hari Ini</h1>
                        <p style="color: #718096; margin-top: 4px; font-weight: 600;"><?php echo date('d/m/Y'); ?></p>
                    </div>
                    <a href="../member_self_checkin.php" target="_blank" class="btn-kiosk-banner">
                        TEKAN SINI UNTUK<br>AKSES SELF-CHECK IN AHLI
                    </a>
                </div>

                <?php if (!empty($flash)): ?>
                    <div style="background: #e2e8f0; color: #1a202c; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: 600;">
                        <?php echo htmlspecialchars($flash); ?>
                    </div>
                <?php endif; ?>

                <!-- Manual Input Box -->
                <div class="checkin-card">
                    <h3 style="font-size: 16px; color: #0d1b4c; font-weight: 700; margin-bottom: 5px;">Tambah Kehadiran (Manual)</h3>
                    <form action="staff_daily_attendance.php" method="POST" class="checkin-form">
                        <input type="text" name="manual_ic" class="checkin-input" placeholder="No. KP Ahli (tanpa -)" required>
                        <button type="submit" class="btn-checkin-submit">Tambah</button>
                    </form>
                </div>

                <!-- Live Counter Attendance Log Table -->
                <h3 style="font-size: 20px; color: #0d1b4c; font-weight: 800; margin-bottom: 15px;">LIVE COUNTER ATTENDANCE LOG</h3>
                <div class="staff-table-wrapper" style="max-width: 100%;">
                    <table class="member-table">
                        <thead>
                            <tr>
                                <th style="width: 60px; text-align: center;">No.</th>
                                <th>Member Name</th>
                                <th>IC Number</th>
                                <th>Check-in Time</th>
                                <th>Class/Program</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($attendance_logs)): ?>
                                <?php foreach ($attendance_logs as $index => $log): ?>
                                    <tr>
                                        <td style="text-align: center;"><?php echo $index + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($log['user_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($log['user_ic']); ?></td>
                                        <td><?php echo date('h:i A', strtotime($log['attendance_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($log['class_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="empty-state" style="text-align: center; padding: 40px; color: #718096;">
                                        Tiada rekod kehadiran direkodkan untuk hari ini.
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

        setTimeout(function(){
            window.location.reload();
        }, 10000);

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