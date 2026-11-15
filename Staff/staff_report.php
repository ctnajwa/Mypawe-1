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

$category = $_GET['category'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$is_submitted = isset($_GET['gabung']);

$report_data = [];
$summary_kpi = ['total_count' => 0, 'sub_total' => 0];

if ($is_submitted && !empty($start_date) && !empty($end_date)) {
    if ($category === 'Program') {
        $sql = "SELECT c.class_id, c.class_name, c.class_date, c.class_place, c.class_involve, c.class_info,
                       (SELECT COUNT(*) FROM attendance a WHERE a.class_id = c.class_id) as total_attendees
                FROM class c
                WHERE c.class_date BETWEEN ? AND ?
                ORDER BY c.class_date ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $report_data[] = $row;
            $summary_kpi['total_count']++;
            $summary_kpi['sub_total'] += $row['total_attendees'];
        }
        $stmt->close();
    } 
    elseif ($category === 'Kehadiran') {
        $sql = "SELECT u.user_name, u.user_ic, a.attendance_time, c.class_name 
                FROM attendance a 
                JOIN member m ON a.member_id = m.member_id
                JOIN public p ON m.public_id = p.public_id
                JOIN user u ON p.public_id = u.user_id
                JOIN class c ON a.class_id = c.class_id
                WHERE DATE(a.attendance_time) BETWEEN ? AND ?
                ORDER BY a.attendance_time DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $report_data[] = $row;
            $summary_kpi['total_count']++;
        }
        $stmt->close();
    } 
    elseif ($category === 'Pendaftaran') {
        $sql = "SELECT m.member_id, m.member_reg_date, m.member_status, u.user_name, u.user_ic 
                FROM member m
                JOIN public p ON m.public_id = p.public_id
                JOIN user u ON p.public_id = u.user_id
                WHERE m.member_reg_date BETWEEN ? AND ?
                ORDER BY m.member_reg_date DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $report_data[] = $row;
            $summary_kpi['total_count']++;
        }
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Rasmi - MyPAWE Staf</title>
    <link rel="stylesheet" href="../Style/style_staff_dashboard.css">
    <link rel="stylesheet" href="../Style/style_admin_member.css">
    <link rel="stylesheet" href="../Style/style_admin_staff.css">
    <style>
        .content-body { max-width: 1100px; margin: 0 auto; padding: 30px 20px; }
        .page-heading { font-size: 32px; font-weight: 800; color: #0d1b4c; text-align: center; margin-bottom: 25px; }
        .staff-table-wrapper { width: 100%; max-width: none !important; }
        .member-table { width: 100% !important; max-width: none !important; table-layout: fixed; }
        .member-table th, .member-table td { overflow-wrap: anywhere; }
        
        .filter-card {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            border: 1px solid #cbd5e0;
            margin-bottom: 30px;
        }
        .filter-row { display: flex; gap: 20px; align-items: center; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-group { flex: 1; min-width: 250px; }
        .filter-group label { display: block; font-weight: 700; color: #2d3748; margin-bottom: 8px; font-size: 14px; }
        .form-control { width: 100%; padding: 11px 14px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 15px; outline: none; background: #fff; box-sizing: border-box; }
        
        .btn-gabung {
            background-color: #22c55e;
            color: #ffffff;
            border: none;
            padding: 12px 35px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 16px;
            cursor: pointer;
            display: block;
            margin: 0 auto;
        }
        .btn-gabung:hover { background-color: #16a34a; }

        .kpi-container { display: flex; gap: 20px; margin-bottom: 25px; }
        .kpi-box { flex: 1; background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; text-align: center; }
        .kpi-box h4 { margin: 0; font-size: 14px; color: #718096; text-transform: uppercase; }
        .kpi-box p { margin: 8px 0 0; font-size: 26px; font-weight: 800; color: #0d1b4c; }

        .report-actions { display: flex; justify-content: flex-end; gap: 15px; margin-top: 20px; }
        .btn-pdf { background-color: #0284c7; color: #fff; padding: 10px 24px; border-radius: 6px; font-weight: 700; border: none; cursor: pointer; text-decoration: none; }
        .btn-pdf:hover { background-color: #0369a1; }
        .btn-print { background-color: #475569; color: #fff; padding: 10px 24px; border-radius: 6px; font-weight: 700; border: none; cursor: pointer; }
        .btn-print:hover { background-color: #334155; }

        @media print {
            @page { size: A4 landscape; margin: 12mm; }
            body, .wrapper, .main-layout { display: block !important; width: 100% !important; height: auto !important; overflow: visible !important; background: #fff !important; }
            aside, header, .filter-card, .report-actions { display: none !important; }
            .content-body { width: 100% !important; max-width: none !important; margin: 0 !important; padding: 0 !important; }
            .kpi-container { break-inside: avoid; }
            .staff-table-wrapper { width: 100% !important; max-width: none !important; overflow: visible !important; box-shadow: none !important; }
            .member-table { width: 100% !important; max-width: none !important; font-size: 9pt !important; }
            .member-table th, .member-table td { padding: 6px !important; color: #000 !important; border: 1px solid #999 !important; }
            .member-table tr { break-inside: avoid; }
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
                <h1 class="page-heading">Laporan Rasmi PAWE Pekan</h1>

                <!-- Filter Card Form -->
                <div class="filter-card">
                    <form action="staff_report.php" method="GET">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label>KATEGORI LAPORAN</label>
                                <select name="category" class="form-control" required>
                                    <option value="">-- Sila Pilih Kategori --</option>
                                    <option value="Program" <?php echo ($category === 'Program') ? 'selected' : ''; ?>>Laporan Program / Aktiviti</option>
                                    <option value="Kehadiran" <?php echo ($category === 'Kehadiran') ? 'selected' : ''; ?>>Laporan Kehadiran Harian</option>
                                    <option value="Pendaftaran" <?php echo ($category === 'Pendaftaran') ? 'selected' : ''; ?>>Laporan Pendaftaran Ahli</option>
                                </select>
                            </div>
                        </div>
                        <div class="filter-row">
                            <div class="filter-group">
                                <label>TARIKH MULA</label>
                                <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>" required>
                            </div>
                            <div class="filter-group">
                                <label>TARIKH AKHIR (HINGGA)</label>
                                <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>" required>
                            </div>
                        </div>
                        <button type="submit" name="gabung" value="1" class="btn-gabung">GABUNGKAN</button>
                    </form>
                </div>

                <?php if ($is_submitted): ?>
                    <!-- KPI Summary Cards -->
                    <div class="kpi-container">
                        <div class="kpi-box">
                            <h4>Jumlah Rekod Dijumpai</h4>
                            <p><?php echo $summary_kpi['total_count']; ?></p>
                        </div>
                        <?php if ($category === 'Program'): ?>
                            <div class="kpi-box">
                               <h4>Jumlah Keseluruhan Kehadiran</h4>
                               <p><?php echo $summary_kpi['sub_total']; ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Report Table Result -->
                    <div class="staff-table-wrapper" style="max-width: 100%;">
                        <table class="member-table">
                            <thead>
                                <tr>
                                    <th style="width: 60px; text-align: center;">No.</th>
                                    <?php if ($category === 'Program'): ?>
                                        <th>Nama Kelas / Program</th>
                                        <th style="width: 130px;">Tarikh</th>
                                        <th style="width: 100px; text-align: center;">Peserta</th>
                                        <th style="width: 180px;">PIC Terlibat</th>
                                        <th>Maklumat Program</th>
                                    <?php elseif ($category === 'Kehadiran'): ?>
                                        <th>Nama Ahli</th>
                                        <th style="width: 180px;">No. Kad Pengenalan</th>
                                        <th style="width: 160px;">Masa Kehadiran</th>
                                        <th>Nama Kelas / Program</th>
                                    <?php elseif ($category === 'Pendaftaran'): ?>
                                        <th>Nama Pendaftar</th>
                                        <th style="width: 180px;">No. Kad Pengenalan</th>
                                        <th style="width: 130px;">Tarikh Daftar</th>
                                        <th style="width: 130px; text-align: center;">Status</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($report_data)): ?>
                                    <?php foreach ($report_data as $index => $row): ?>
                                        <tr>
                                            <td style="text-align: center;"><?php echo $index + 1; ?></td>
                                            <?php if ($category === 'Program'): ?>
                                                <td><strong><?php echo htmlspecialchars($row['class_name']); ?></strong><br><span style="font-size:12px; color:#718096;"> <?php echo htmlspecialchars($row['class_place']); ?></span></td>
                                                <td><?php echo date('d/m/Y', strtotime($row['class_date'])); ?></td>
                                                <td style="text-align: center; font-weight: bold; color: #2563eb;"><?php echo $row['total_attendees']; ?></td>
                                                <td><?php echo htmlspecialchars($row['class_involve'] ?: '-'); ?></td>
                                                <td style="font-size: 13px; color: #4a5568;"><?php echo htmlspecialchars($row['class_info'] ?: '-'); ?></td>
                                            <?php elseif ($category === 'Kehadiran'): ?>
                                                <td><strong><?php echo htmlspecialchars($row['user_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($row['user_ic']); ?></td>
                                                <td><?php echo date('d/m/Y h:i A', strtotime($row['attendance_time'])); ?></td>
                                                <td><?php echo htmlspecialchars($row['class_name']); ?></td>
                                            <?php elseif ($category === 'Pendaftaran'): ?>
                                                <td><strong><?php echo htmlspecialchars($row['user_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($row['user_ic']); ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($row['member_reg_date'])); ?></td>
                                                <td style="text-align: center;"><strong><?php echo htmlspecialchars($row['member_status']); ?></strong></td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="empty-state" style="text-align: center; padding: 40px; color: #718096;">
                                            Tiada rekod dijumpai bagi julat tarikh yang dipilih.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="report-actions">
                        <button type="button" class="btn-print" onclick="window.print();">Cetak Laporan</button>
                        <button type="button" class="btn-pdf" onclick="exportReportPdf();">Eksport PDF</button>
                    </div>
                <?php endif; ?>
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

        function exportReportPdf() {
            const originalTitle = document.title;
            const category = document.querySelector('select[name="category"]');
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;
            const categoryName = category ? category.options[category.selectedIndex].text.trim() : 'Laporan';

            document.title = `${categoryName} ${startDate} hingga ${endDate}`;
            window.addEventListener('afterprint', function restoreTitle() {
                document.title = originalTitle;
            }, { once: true });
            window.print();
        }
    </script>
</body>
</html>