<?php
// Start the session listener
session_start();

// Strict Access Guard: Ensure user is logged in as 'Staf' (or 'Admin')
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Staf' && $_SESSION['user_role'] !== 'Admin')) {
    header("Location: ../login.php");
    exit();
}

// Store the logged-in staff's name from the correct session key
$logged_in_name = $_SESSION['user_name'] ?? 'Staf';

$conn_guard = new mysqli("localhost", "root", "", "mypawe");
$g_stmt = $conn_guard->prepare("SELECT staff_change_password FROM staff WHERE staff_id = ? LIMIT 1");
$g_stmt->bind_param("s", $_SESSION['user_id']);
$g_stmt->execute();
$g_res = $g_stmt->get_result()->fetch_assoc();
$g_stmt->close();
$conn_guard->close();

if ($g_res && (int)$g_res['staff_change_password'] === 1) {
    header("Location: staff_change_password.php");
    exit();
}

// --- DATABASE FUNCTIONS & QUERIES ---
$conn = new mysqli("localhost", "root", "", "mypawe");
if ($conn->connect_error) {
    die("Sambungan pangkalan data gagal: " . $conn->connect_error);
}

// --- QUERY 1: Total Members (all registered members) ---
$total_members = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM member WHERE member_status IN ('Aktif', 'Approved', 'LULUS', 'SEDANG DIPROSES', 'Pending')");
if ($res) {
    $total_members = $res->fetch_assoc()['total'];
    $res->free();
}

// --- QUERY 2: Today's Attendance Count ---
$today_attendance = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM attendance WHERE DATE(attendance_time) = CURDATE()");
if ($res) {
    $today_attendance = $res->fetch_assoc()['total'];
    $res->free();
}

// --- QUERY 3: Upcoming Classes/Events (from today onwards) ---
$upcoming_events = [];
$sql = "SELECT class_id, class_name, class_date, class_time, class_place AS class_location 
        FROM class 
        WHERE class_date >= CURDATE() 
        ORDER BY class_date ASC, class_time ASC";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $upcoming_events[] = $row;
    }
    $res->free();
}

// --- BUILD EVENT MAP FOR CALENDAR ---
$event_map = [];
foreach ($upcoming_events as $ev) {
    $date_key = date('Y-m-d', strtotime($ev['class_date']));
    if (!isset($event_map[$date_key])) {
        $event_map[$date_key] = [];
    }
    $event_map[$date_key][] = $ev;
}

// --- CALENDAR LOGIC ---
$month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('n');
$year  = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');

if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$first_day_ts   = mktime(0, 0, 0, $month, 1, $year);
$days_in_month  = (int)date('t', $first_day_ts);
$first_weekday  = (int)date('N', $first_day_ts);

$malay_months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Mac', 4 => 'April',
    5 => 'Mei', 6 => 'Jun', 7 => 'Julai', 8 => 'Ogos',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Disember'
];
$month_label = $malay_months[$month] . ' ' . $year;

$prev_month = $month - 1; $prev_year = $year;
if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
$next_month = $month + 1; $next_year = $year;
if ($next_month > 12) { $next_month = 1; $next_year++; }

$conn->close();
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem MyPAWE - Dashboard Staf</title>
    <link rel="stylesheet" href="../Style/style_staff_dashboard.css">
    <style>
        .main-layout > .top-navbar {
            height: 75px;
            min-height: 75px;
            flex: 0 0 75px;
        }

        /* --- DASHBOARD WIDGET STYLES --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            padding: 30px;
            border-left: 6px solid #0d3b66;
            position: relative;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card.green { border-left-color: #28a745; }
        .stat-card.yellow { border-left-color: #ffc107; }

        .stat-icon {
            position: absolute; top: 25px; right: 25px;
            font-size: 40px; opacity: 0.2;
        }

        .stat-number {
            font-size: 52px; font-weight: bold;
            color: #0a2e52;
            line-height: 1; margin-bottom: 10px;
        }
        .stat-label { font-size: 18px; color: #6c757d; font-weight: 600; }
        .stat-sub { font-size: 15px; margin-top: 12px; font-weight: 600; color: #6c757d; }
        .stat-sub.good { color: #28a745; }
        .stat-sub.warn { color: #fd7e14; }

        /* --- CALENDAR --- */
        .calendar-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            overflow: hidden;
            margin-bottom: 25px;
        }

        .calendar-header {
            background-color: #0a2e52;
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .calendar-header h3 { font-size: 22px; display: flex; align-items: center; gap: 10px; margin: 0; }

        .calendar-nav { display: flex; gap: 10px; }
        .calendar-nav a {
            background-color: rgba(255,255,255,0.15);
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 15px;
            transition: background 0.2s;
        }
        .calendar-nav a:hover { background-color: rgba(255,255,255,0.3); }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            border-top: 1px solid #e0e0e0;
        }

        .calendar-day-header {
            background-color: #f0f4ff;
            padding: 12px 5px;
            text-align: center;
            font-weight: bold;
            font-size: 15px;
            color: #0a2e52;
            border-bottom: 1px solid #e0e0e0;
            border-right: 1px solid #e0e0e0;
        }
        .calendar-day-header:last-child { border-right: none; }

        .calendar-cell {
            min-height: 100px;
            padding: 8px;
            border-right: 1px solid #eee;
            border-bottom: 1px solid #eee;
            position: relative;
            background: white;
        }
        .calendar-cell:nth-child(7n) { border-right: none; }
        .calendar-cell.empty { background-color: #fafafa; }
        .calendar-cell.today {
            background-color: #fff8e1;
            border: 2px solid #ffc107;
        }

        .calendar-date {
            font-size: 16px;
            font-weight: bold;
            color: #212529;
            margin-bottom: 5px;
        }
        .calendar-cell.today .calendar-date { color: #0a2e52; }

        .calendar-event {
            background-color: #0d3b66;
            color: white;
            font-size: 12px;
            padding: 4px 6px;
            border-radius: 4px;
            margin-bottom: 3px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: pointer;
        }
        .calendar-event:hover { background-color: #0a2e52; }

        /* --- UPCOMING LIST --- */
        .upcoming-list {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            overflow: hidden;
        }

        .upcoming-list-header {
            background-color: #f0f4ff;
            padding: 18px 30px;
            font-size: 19px;
            font-weight: bold;
            color: #0a2e52;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .event-row {
            display: flex;
            align-items: center;
            padding: 16px 30px;
            border-bottom: 1px solid #f5f5f5;
            gap: 20px;
        }
        .event-row:last-child { border-bottom: none; }

        .event-date-badge {
            background-color: #ffc107;
            color: #0a2e52;
            border-radius: 8px;
            padding: 8px 12px;
            text-align: center;
            min-width: 65px;
            flex-shrink: 0;
        }
        .event-date-badge .day { font-size: 22px; font-weight: bold; line-height: 1; }
        .event-date-badge .month { font-size: 12px; text-transform: uppercase; font-weight: bold; }

        .event-details { flex: 1; }
        .event-details .name { font-size: 17px; font-weight: bold; color: #212529; margin-bottom: 3px; }
        .event-details .meta { font-size: 14px; color: #6c757d; }

        .event-time-badge {
            background-color: #f0f4ff;
            color: #0a2e52;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            white-space: nowrap;
        }

        .no-events {
            padding: 30px;
            text-align: center;
            color: #6c757d;
            font-size: 16px;
        }

        .dashboard-footer {
            text-align: center;
            padding: 25px;
            color: #6c757d;
            font-size: 15px;
            border-top: 1px solid #e0e0e0;
            margin-top: 30px;
        }
    </style>
</head>
<body>

    <div class="wrapper">
        
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="logo-section">
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

            <div class="sidebar-footer">
                <a href="../logout.php" class="btn-log-keluar">LOG KELUAR</a>
            </div>
        </aside>

        <div class="main-layout">
            
            <header class="top-navbar">
                <div class="notification-icon" style="position: relative; cursor: pointer;">
                    &#128276;
                </div>
                
                <div class="user-profile-badge">
                    <div class="avatar-initial"><?php echo strtoupper(substr($logged_in_name, 0, 1)); ?>.</div>
                    <div class="user-info-text">
                        Welcome,<br>
                        <strong><?php echo htmlspecialchars($logged_in_name); ?></strong>
                    </div>
                </div>
            </header>

            <main class="content-body">

                <!-- PAGE HEADING -->
                <div class="page-heading" style="margin-bottom: 25px;">
                    <h1 style="font-size: 28px; color: #0a2e52; margin-bottom: 5px;">Dashboard</h1>
                    <br>
                </div>

                <!-- STAT CARDS -->
                <div class="stats-grid">
                    <div class="stat-card green">
                        <div class="stat-number"><?php echo $today_attendance; ?></div>
                        <div class="stat-label">Ahli Hadir Hari Ini</div>
                        <div class="stat-sub good">Rekod kehadiran <?php echo date('d/m/Y'); ?></div>
                    </div>
                    <div class="stat-card yellow">
                        <div class="stat-number"><?php echo $total_members; ?></div>
                        <div class="stat-label">Jumlah Ahli</div>
                        <div class="stat-sub">Jumlah keseluruhan ahli berdaftar</div>
                    </div>
                </div>

                <!-- CALENDAR -->
                <div class="calendar-card">
                    <div class="calendar-header">
                        <h3><?php echo $month_label; ?></h3>
                        <div class="calendar-nav">
                            <a href="?m=<?php echo $prev_month; ?>&y=<?php echo $prev_year; ?>">← Sebelum</a>
                            <a href="?m=<?php echo date('n'); ?>&y=<?php echo date('Y'); ?>">Hari Ini</a>
                            <a href="?m=<?php echo $next_month; ?>&y=<?php echo $next_year; ?>">Seterusnya →</a>
                        </div>
                    </div>

                    <div class="calendar-grid">
                        <div class="calendar-day-header">Isnin</div>
                        <div class="calendar-day-header">Selasa</div>
                        <div class="calendar-day-header">Rabu</div>
                        <div class="calendar-day-header">Khamis</div>
                        <div class="calendar-day-header">Jumaat</div>
                        <div class="calendar-day-header">Sabtu</div>
                        <div class="calendar-day-header">Ahad</div>

                        <?php
                        for ($i = 1; $i < $first_weekday; $i++) {
                            echo '<div class="calendar-cell empty"></div>';
                        }

                        $today = date('Y-m-d');
                        for ($day = 1; $day <= $days_in_month; $day++) {
                            $current_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                            $cell_class = 'calendar-cell';
                            if ($current_date === $today) {
                                $cell_class .= ' today';
                            }
                            echo '<div class="' . $cell_class . '">';
                            echo '<div class="calendar-date">' . $day . '</div>';

                            if (isset($event_map[$current_date])) {
                                foreach ($event_map[$current_date] as $ev) {
                                    $time = !empty($ev['class_time']) ? date('g:i A', strtotime($ev['class_time'])) : '';
                                    echo '<div class="calendar-event" title="' . htmlspecialchars($ev['class_name'] . ' - ' . $time) . '">';
                                    echo '• ' . htmlspecialchars($ev['class_name']);
                                    echo '</div>';
                                }
                            }

                            echo '</div>';
                        }

                        $total_cells = $first_weekday - 1 + $days_in_month;
                        $remainder = $total_cells % 7;
                        if ($remainder !== 0) {
                            for ($i = 0; $i < (7 - $remainder); $i++) {
                                echo '<div class="calendar-cell empty"></div>';
                            }
                        }
                        ?>
                    </div>
                </div>

                <!-- UPCOMING EVENTS LIST -->
                <div class="upcoming-list">
                    <div class="upcoming-list-header">
                        Senarai Program Akan Datang
                    </div>

                    <?php if (!empty($upcoming_events)): ?>
                        <?php foreach (array_slice($upcoming_events, 0, 10) as $ev): ?>
                            <?php
                                $ts = strtotime($ev['class_date']);
                                $day_num = date('d', $ts);
                                $month_short = strtoupper(substr($malay_months[(int)date('n', $ts)], 0, 3));
                                $time_str = !empty($ev['class_time']) ? date('g:i A', strtotime($ev['class_time'])) : 'Sepanjang hari';
                            ?>
                            <div class="event-row">
                                <div class="event-date-badge">
                                    <div class="day"><?php echo $day_num; ?></div>
                                    <div class="month"><?php echo $month_short; ?></div>
                                </div>
                                <div class="event-details">
                                    <div class="name"><?php echo htmlspecialchars($ev['class_name']); ?></div>
                                    <div class="meta">
                                        📍 <?php echo htmlspecialchars($ev['class_location'] ?: 'PAWE Pekan'); ?>
                                    </div>
                                </div>
                                <div class="event-time-badge">
                                     <?php echo $time_str; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-events">
                            Tiada program akan datang buat masa ini.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- FOOTER -->
                <div class="dashboard-footer">
                    &copy; <?php echo date('Y'); ?> MyPAWE - Pusat Aktiviti Warga Emas
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