<?php
session_start();

// Strict Access Guard: Only Admin can access registration reviews
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

// -------------------------------------------------------------
// AUTOMATIC SCREENING LOGIC ON PAGE LOAD
// Rule: Only auto-reject if age is strictly below 60. 
// Health conditions ("YA") are kept in review for manual evaluation.
// -------------------------------------------------------------
$pending_check_sql = "SELECT m.member_id, m.member_status, u.user_ic 
                      FROM member m
                      INNER JOIN public p ON m.public_id = p.public_id
                      INNER JOIN user u ON p.public_id = u.user_id
                      WHERE m.member_status = 'SEDANG DIPROSES' OR m.member_status = 'Pending' OR m.member_status = 'DRAF'";
$pending_res = $conn->query($pending_check_sql);

if ($pending_res && $pending_res->num_rows > 0) {
    while ($row = $pending_res->fetch_assoc()) {
        $m_id = $row['member_id'];
        $ic   = $row['user_ic'];

        // Calculate Age accurately from 12-digit IC (YYMMDD...)
        $yy = (int)substr($ic, 0, 2);
        $current_yy = (int)date('y');
        $century = ($yy > $current_yy) ? 1900 : 2000;
        $birth_year = $century + $yy;
        $age = (int)date('Y') - $birth_year;

        // Auto-reject ONLY if age is under 60
        if ($age < 60) {
            $reject_reason = "Tidak memenuhi syarat umur.";
            $rej_stmt = $conn->prepare("UPDATE member SET member_status = 'Ditolak', member_reject_reason = ? WHERE member_id = ?");
            $rej_stmt->bind_param("si", $reject_reason, $m_id);
            $rej_stmt->execute();
            $rej_stmt->close();
        }
    }
}

// Handle action requests (Terima / Tolak)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action_type'] ?? '';
    $member_id = $_POST['member_id'] ?? '';

    if ($action === 'terima' && !empty($member_id)) {
        $up_stmt = $conn->prepare("UPDATE member SET member_status = 'Aktif', member_approved_by = ? WHERE member_id = ?");
        $up_stmt->bind_param("si", $admin_id, $member_id);
        $up_stmt->execute();
        $up_stmt->close();
        $_SESSION['admin_msg'] = "Permohonan ahli telah berjaya diluluskan (Aktif).";
    } 
    elseif ($action === 'tolak' && !empty($member_id)) {
        $reason = trim($_POST['reject_reason'] ?? 'Tidak memenuhi kriteria keahlian.');
        $up_stmt = $conn->prepare("UPDATE member SET member_status = 'Ditolak', member_reject_reason = ?, member_approved_by = ? WHERE member_id = ?");
        $up_stmt->bind_param("ssi", $reason, $admin_id, $member_id);
        $up_stmt->execute();
        $up_stmt->close();
        $_SESSION['admin_msg'] = "Permohonan ahli telah ditolak.";
    }

    header("Location: admin_view_registration.php");
    exit();
}

$tab = $_GET['tab'] ?? 'semakan';

if ($tab === 'ditolak') {
    $sql = "SELECT m.member_id, m.member_reg_date, m.member_reject_reason, u.user_name, u.user_ic 
            FROM member m
            INNER JOIN public p ON m.public_id = p.public_id
            INNER JOIN user u ON p.public_id = u.user_id
            WHERE m.member_status = 'Ditolak'
            ORDER BY m.member_reg_date DESC";
} else {
    $sql = "SELECT m.member_id, m.member_reg_date, u.user_name, u.user_ic 
            FROM member m
            INNER JOIN public p ON m.public_id = p.public_id
            INNER JOIN user u ON p.public_id = u.user_id
            WHERE m.member_status = 'SEDANG DIPROSES' OR m.member_status = 'Pending' OR m.member_status = 'DRAF'
            ORDER BY m.member_reg_date DESC";
}

$result = $conn->query($sql);
$reg_list = [];
while ($row = $result->fetch_assoc()) {
    $reg_list[] = $row;
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
    <title>Pengurusan Pendaftaran Ahli - MyPAWE</title>
    <link rel="stylesheet" href="../Style/style_staff_dashboard.css">
    <link rel="stylesheet" href="../Style/style_admin_member.css">
    <link rel="stylesheet" href="../Style/style_admin_staff.css">
    <style>
        .main-layout {
            min-width: 0;
        }
        .content-body {
            width: 100%;
            max-width: 1300px;
            box-sizing: border-box;
            margin: 0 auto;
            padding: 36px 40px;
        }
        .page-heading {
            font-size: 32px;
            font-weight: 800;
            color: #0d1b4c;
            text-align: center;
            margin-bottom: 25px;
        }
        .tab-container {
            display: flex;
            width: 100%;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #cbd5e0;
            margin-bottom: 30px;
        }
        .tab-btn {
            flex: 1;
            text-align: center;
            padding: 14px;
            font-size: 16px;
            font-weight: 700;
            text-decoration: none;
            background-color: #e2e8f0;
            color: #4a5568;
            transition: all 0.2s ease;
        }
        .tab-btn.active {
            background-color: #ffc107;
            color: #000000;
        }
        .staff-table-wrapper {
            max-width: 100% !important;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            border-radius: 8px;
            overflow: hidden;
        }
        .member-table {
            max-width: none !important;
            width: 100%;
            table-layout: fixed;
        }
        .member-table td {
            overflow-wrap: anywhere;
        }
        .member-table th {
            padding: 20px 24px;
            font-size: 18px;
        }
        .member-table td {
            padding: 22px 24px;
            font-size: 17px;
        }
        .btn-terima {
            background-color: #68d391;
            color: #ffffff;
            border: none;
            padding: 10px 24px;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-terima:hover { background-color: #48bb78; }
        
        .btn-tolak {
            background-color: #fc8181;
            color: #ffffff;
            border: none;
            padding: 10px 24px;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-left: 8px;
        }
        .btn-tolak:hover { background-color: #e53e3e; }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        .modal-card {
            background: #ffffff;
            width: 100%;
            max-width: 600px;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            text-align: center;
        }
        .modal-card h3 {
            font-size: 20px;
            color: #1a202c;
            margin-bottom: 20px;
        }
        .modal-textarea {
            width: 100%;
            height: 140px;
            padding: 12px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            font-size: 15px;
            resize: none;
            outline: none;
            margin-bottom: 25px;
        }
        .modal-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        .btn-modal-batal {
            background-color: #ffffff;
            border: 1px solid #cbd5e0;
            color: #2d3748;
            padding: 8px 25px;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-modal-hantar {
            background-color: #f56565;
            color: #ffffff;
            border: none;
            padding: 8px 25px;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
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
                        <li><a href="admin_view_registration.php" class="active-sub">Pendaftaran Ahli</a></li>
                        <li><a href="admin_view_member.php">Profil Ahli</a></li>
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
                <h1 class="page-heading">Pengurusan Pendaftaran Ahli</h1>

                <?php if (!empty($flash_msg)): ?>
                    <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
                        <?php echo htmlspecialchars($flash_msg); ?>
                    </div>
                <?php endif; ?>

                <!-- Tab Navigation -->
                <div class="tab-container">
                    <a href="admin_view_registration.php?tab=semakan" class="tab-btn <?php echo ($tab === 'semakan') ? 'active' : ''; ?>">Untuk Semakan</a>
                    <a href="admin_view_registration.php?tab=ditolak" class="tab-btn <?php echo ($tab === 'ditolak') ? 'active' : ''; ?>">Ditolak</a>
                </div>

                <!-- Table Container -->
                <div class="staff-table-wrapper">
                    <table class="member-table">
                        <thead>
                            <tr>
                                <th style="width: 70px; text-align: center;">No.</th>
                                <th>Nama Pendaftar</th>
                                <th style="width: 160px;">Tarikh</th>
                                <th style="width: 200px; text-align: center;">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($reg_list)): ?>
                                <?php foreach ($reg_list as $index => $reg): ?>
                                    <tr>
                                        <td style="text-align: center;"><?php echo $index + 1; ?></td>
                                        <td>
                                            <a href="admin_registration_profile.php?id=<?php echo urlencode($reg['member_id']); ?>" class="link-member">
                                                <?php echo htmlspecialchars($reg['user_name']); ?>
                                            </a>
                                            <div style="font-size: 13px; color: #718096; margin-top: 2px;">IC: <?php echo htmlspecialchars($reg['user_ic']); ?></div>
                                            <?php if ($tab === 'ditolak' && !empty($reg['member_reject_reason'])): ?>
                                                <div style="font-size: 13px; color: #e53e3e; margin-top: 4px;">Sebab Tolak: <?php echo htmlspecialchars($reg['member_reject_reason']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($reg['member_reg_date']); ?></td>
                                        <td style="text-align: center;">
                                            <?php if ($tab === 'semakan'): ?>
                                                <form action="admin_view_registration.php" method="POST" style="display:inline-block;">
                                                    <input type="hidden" name="action_type" value="terima">
                                                    <input type="hidden" name="member_id" value="<?php echo $reg['member_id']; ?>">
                                                    <button type="submit" class="btn-terima" onclick="return confirm('Adakah anda pasti ingin meluluskan ahli ini?');">Terima</button>
                                                </form>
                                                <button type="button" class="btn-tolak" onclick="openRejectModal('<?php echo $reg['member_id']; ?>')">Tolak</button>
                                            <?php else: ?>
                                                <span style="font-size: 14px; color: #e53e3e; font-weight: 700;">Permohonan Ditolak</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="empty-state" style="text-align: center; padding: 40px; color: #718096;">
                                        Tiada senarai pendaftaran untuk kategori ini.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <!-- Rejection Reason Modal Popup -->
    <div class="modal-overlay" id="rejectModal">
        <div class="modal-card">
            <h3>Sila nyatakan sebab menolak permohonan:</h3>
            <form action="admin_view_registration.php" method="POST">
                <input type="hidden" name="action_type" value="tolak">
                <input type="hidden" name="member_id" id="modal_member_id" value="">
                <textarea name="reject_reason" class="modal-textarea" placeholder="Nyatakan sebab penolakan di sini..." required></textarea>
                <div class="modal-actions">
                    <button type="button" class="btn-modal-batal" onclick="closeRejectModal()">BATAL</button>
                    <button type="submit" class="btn-modal-hantar">HANTAR</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openRejectModal(memberId) {
            document.getElementById('modal_member_id').value = memberId;
            document.getElementById('rejectModal').style.display = 'flex';
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
        }

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