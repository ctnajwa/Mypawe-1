<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id   = $_SESSION['user_id'];
$admin_name = $_SESSION['user_name'] ?? 'Admin';
$member_id  = trim($_GET['id'] ?? '');

if (empty($member_id)) {
    header("Location: admin_view_member.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "mypawe");
if ($conn->connect_error) {
    die("Sambungan pangkalan data gagal: " . $conn->connect_error);
}

// Fetch member, user, public, health, work, volunteer, family data
$sql = "SELECT m.*, u.*, p.*, h.*, w.*, v.*, f.*
        FROM member m
        INNER JOIN public p ON m.public_id = p.public_id
        INNER JOIN user u ON p.public_id = u.user_id
        LEFT JOIN health h ON p.public_id = h.public_id
        LEFT JOIN work w ON p.public_id = w.public_id
        LEFT JOIN volunteer v ON p.public_id = v.public_id
        LEFT JOIN family f ON p.public_id = f.public_id
        WHERE m.member_id = ? LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    $_SESSION['admin_msg'] = "Profil ahli tidak dijumpai.";
    header("Location: admin_view_member.php");
    exit();
}

// Fetch class attendance history for this member
$att_sql = "SELECT c.class_name, c.class_date, c.class_place, a.attendance_time 
            FROM attendance a 
            JOIN class c ON a.class_id = c.class_id 
            WHERE a.member_id = ? 
            ORDER BY c.class_date DESC";
$att_stmt = $conn->prepare($att_sql);
$att_stmt->bind_param("i", $member_id);
$att_stmt->execute();
$att_res = $att_stmt->get_result();
$attendance_list = [];
while ($row = $att_res->fetch_assoc()) {
    $attendance_list[] = $row;
}
$att_stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Penuh Ahli - <?php echo htmlspecialchars($data['user_name']); ?></title>
    <link rel="stylesheet" href="../Style/style_staff_dashboard.css">
    <link rel="stylesheet" href="../Style/style_admin_member.css">
    <link rel="stylesheet" href="../Style/style_admin_staff.css">
    <style>
        .profile-container-card {
            max-width: 950px;
            margin: 0 auto 50px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .profile-header-banner {
            background-color: #0d1b4c;
            color: #ffffff;
            padding: 25px 35px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }
        .profile-header-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .member-avatar-box {
            width: 80px;
            height: 95px;
            border-radius: 8px;
            background-color: #e2e8f0;
            border: 2px solid #ffc107;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .member-avatar-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-body {
            padding: 35px 40px;
        }
        .section-header {
            font-size: 17px;
            font-weight: 700;
            color: #0d1b4c;
            margin: 25px 0 15px;
            padding-bottom: 6px;
            border-bottom: 2px solid #edf2f7;
        }
        .section-header:first-of-type { margin-top: 0; }
        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px 30px;
        }
        .details-item { display: flex; flex-direction: column; padding: 4px 0; }
        .details-label { font-size: 13px; font-weight: 600; color: #718096; text-transform: uppercase; margin-bottom: 2px; }
        .details-value { font-size: 16px; font-weight: 400; color: #2d3748; }
        .details-item-full { grid-column: span 2; }
        .btn-back {
            background-color: #e2e8f0;
            color: #2d3748;
            padding: 10px 22px;
            border-radius: 6px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            margin-top: 30px;
        }
        .btn-back:hover { background-color: #cbd5e0; }
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
                <li class="menu-item">
                    <a href="admin_dashboard.php" class="menu-link">Dashboard</a>
                </li>
                
                <!-- Pengurusan Profil -->
                <li class="menu-item has-dropdown" id="menuProfil">
                    <div class="menu-link" onclick="toggleMenu('menuProfil', 'subProfil', 'arrowProfil')">
                        <div class="menu-content">
                            <span>Pengurusan Profil</span>
                            <span id="arrowProfil" style="font-size: 12px; transform: rotate(180deg);">&#9662;</span>
                        </div>
                    </div>
                    <ul class="sub-menu" id="subProfil">
                        <li><a href="admin_personal_profile.php" class="active-sub">Profil Peribadi</a></li>
                        <li><a href="admin_view_staff.php">Profil Staf</a></li>
                    </ul>
                </li>

                <!-- Pengurusan Ahli -->
                <li class="menu-item has-dropdown active" id="menuAhli">
                    <div class="menu-link" onclick="toggleMenu('menuAhli', 'subAhli', 'arrowAhli')">
                        <div class="menu-content">
                            <span>Pengurusan Ahli</span>
                            <span id="arrowAhli" style="font-size: 12px;">&#9662;</span>
                        </div>
                    </div>
                    <ul class="sub-menu open" id="subAhli">
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

        <div class="main-layout">
            <header class="top-navbar">
                <div class="notification-icon">&#128276;</div>
                <div class="user-profile-badge">
                    <div class="avatar-initial"><?php echo strtoupper(substr($admin_name, 0, 1)); ?>.</div>
                    <div class="user-info-text">Welcome,<br><strong><?php echo htmlspecialchars($admin_name); ?></strong></div>
                </div>
            </header>

            <main class="content-body">
                <a href="admin_view_member.php" class="btn-back">&larr; Kembali</a>
                <br><br>
                <div class="profile-container-card">
                    
                    <div class="profile-header-banner">
                        <div class="profile-header-info">
                            <div class="member-avatar-box">
                                <?php if (!empty($data['public_picture']) && file_exists(__DIR__ . '/../' . $data['public_picture'])): ?>
                                    <img src="../<?php echo htmlspecialchars($data['public_picture']); ?>" alt="Foto Ahli">
                                <?php else: ?>
                                    <span style="font-weight: 800; font-size: 24px; color: #0d1b4c;"><?php echo strtoupper(substr($data['user_name'], 0, 1)); ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h2 style="font-size: 22px; color: #fff; margin-bottom: 4px;"><?php echo htmlspecialchars($data['user_name']); ?></h2>
                                <p style="font-size: 14px; color: #cbd5e0;">No. Ahli: <strong>PAWE-<?php echo str_pad($data['member_id'], 4, '0', STR_PAD_LEFT); ?></strong></p>
                            </div>
                        </div>
                        <div>
                            <span style="background: #ffc107; color: #000; padding: 6px 14px; border-radius: 20px; font-weight: 800; font-size: 14px;">
                                <?php echo htmlspecialchars($data['member_status']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="profile-body">

                        <!-- 1. Maklumat Peribadi -->
                        <div class="section-header"> Maklumat Peribadi </div>
                        <div class="details-grid">
                            <div class="details-item"><span class="details-label">Nama Penuh</span><span class="details-value"><?php echo htmlspecialchars($data['user_name']); ?></span></div>
                            <div class="details-item"><span class="details-label">No. Kad Pengenalan</span><span class="details-value"><?php echo htmlspecialchars($data['user_ic']); ?></span></div>
                            <div class="details-item"><span class="details-label">Emel</span><span class="details-value"><?php echo htmlspecialchars($data['user_email']); ?></span></div>
                            <div class="details-item"><span class="details-label">Tarikh Lahir</span><span class="details-value"><?php echo htmlspecialchars($data['public_birthday'] ?: '-'); ?></span></div>
                            <div class="details-item"><span class="details-label">Jantina</span><span class="details-value"><?php echo htmlspecialchars($data['public_gender'] ?: '-'); ?></span></div>
                            <div class="details-item"><span class="details-label">Warganegara</span><span class="details-value"><?php echo htmlspecialchars($data['public_citizenship'] ?: '-'); ?></span></div>
                            <div class="details-item"><span class="details-label">Bangsa / Kaum</span><span class="details-value"><?php echo htmlspecialchars($data['public_bangsa'] ?: '-'); ?></span></div>
                            <div class="details-item"><span class="details-label">Agama</span><span class="details-value"><?php echo htmlspecialchars($data['public_religion'] ?: '-'); ?></span></div>
                            <div class="details-item"><span class="details-label">Taraf Pendidikan</span><span class="details-value"><?php echo htmlspecialchars($data['public_education'] ?: '-'); ?></span></div>
                            <div class="details-item"><span class="details-label">No. Telefon</span><span class="details-value"><?php echo htmlspecialchars($data['public_phone'] ?: '-'); ?></span></div>
                            <div class="details-item details-item-full"><span class="details-label">Alamat Surat Menyurat</span><span class="details-value"><?php echo htmlspecialchars($data['public_address'] ?: '-'); ?></span></div>
                        </div>
                        <br>

                        <!-- 2. Maklumat Kesihatan -->
                        <div class="section-header"> Maklumat Kesihatan</div>
                        <div class="details-grid">
                            <div class="details-item"><span class="details-label">Masalah Kesihatan / Penyakit</span><span class="details-value"><?php echo htmlspecialchars($data['health_status'] ?: '-'); ?> (<?php echo htmlspecialchars($data['health_info'] ?: 'Tiada butiran'); ?>)</span></div>
                            <div class="details-item"><span class="details-label">Status OKU</span><span class="details-value"><?php echo htmlspecialchars($data['oku_status'] ?: 'TIDAK'); ?> <?php echo !empty($data['oku_number']) ? '(No: ' . htmlspecialchars($data['oku_number']) . ')' : ''; ?></span></div>
                        </div>
                        <br>

                        <!-- 3. Maklumat Pekerjaan -->
                        <div class="section-header"> Maklumat Pekerjaan</div>
                        <div class="details-grid">
                            <div class="details-item"><span class="details-label">Status Pekerjaan</span><span class="details-value"><?php echo htmlspecialchars($data['work_status'] ?: '-'); ?></span></div>
                            <div class="details-item"><span class="details-label">Jawatan Terakhir / Semasa</span><span class="details-value"><?php echo htmlspecialchars($data['work_jawatan'] ?: '-'); ?></span></div>
                            <div class="details-item"><span class="details-label">Nama Majikan</span><span class="details-value"><?php echo htmlspecialchars($data['work_majikan'] ?: '-'); ?></span></div>
                            <div class="details-item"><span class="details-label">Pengalaman Bekerja</span><span class="details-value"><?php echo htmlspecialchars($data['work_experience'] ?: '-'); ?></span></div>
                            <div class="details-item details-item-full"><span class="details-label">Kemahiran / Kepakaran</span><span class="details-value"><?php echo htmlspecialchars($data['work_expertise'] ?: '-'); ?></span></div>
                        </div>
                        <br>

                        <!-- 4. Minat Sukarelawan -->
                        <div class="section-header"> Minat Sukarelawan</div>
                        <div class="details-grid">
                            <div class="details-item"><span class="details-label">Berminat Sukarelawan</span><span class="details-value"><?php echo htmlspecialchars($data['volunteer_interest'] ?: '-'); ?></span></div>
                            <div class="details-item"><span class="details-label">Bidang Minat</span><span class="details-value"><?php echo htmlspecialchars($data['volunteer_field'] ?: '-'); ?></span></div>
                        </div>
                        <br>

                        <!-- 5. Keluarga & Penjaga -->
                        <div class="section-header">  Maklumat Keluarga & Waris</div>
                        <div class="details-grid">
                            <div class="details-item"><span class="details-label">Taraf Perkahwinan</span><span class="details-value"><?php echo htmlspecialchars($data['public_wed_status'] ?: '-'); ?></span></div>
                            <div class="details-item"><span class="details-label">Nama Pasangan</span><span class="details-value"><?php echo htmlspecialchars($data['public_partner_name'] ?: '-'); ?></span></div>
                            <div class="details-item"><span class="details-label">Bilangan Anak</span><span class="details-value"><?php echo htmlspecialchars($data['public_children'] ?? '0'); ?></span></div>
                            <div class="details-item"><span class="details-label">Status Kediaman</span><span class="details-value"><?php echo htmlspecialchars($data['public_housing'] ?: '-'); ?></span></div>
                            <div class="details-item"><span class="details-label">Nama Penjaga / Waris</span><span class="details-value"><?php echo htmlspecialchars($data['public_penjaga_name'] ?: '-'); ?></span></div>
                            <div class="details-item"><span class="details-label">Hubungan Waris</span><span class="details-value"><?php echo htmlspecialchars($data['public_penjaga_relation'] ?: '-'); ?></span></div>
                            <div class="details-item"><span class="details-label">No. Tel Penjaga</span><span class="details-value"><?php echo htmlspecialchars($data['public_penjaga_phone'] ?: '-'); ?></span></div>
                            <div class="details-item details-item-full"><span class="details-label">Alamat Penjaga</span><span class="details-value"><?php echo htmlspecialchars($data['public_penjaga_address'] ?: '-'); ?></span></div>
                        </div>
                        <br>

                        <!-- 6. Class Attendance History -->
                        <div class="section-header"> Senarai Kelas & Program Yang Dihadiri</div>
                        <table class="member-table" style="margin-top: 15px;">
                            <thead>
                                <tr>
                                    <th>Tarikh</th>
                                    <th>Nama Kelas / Program</th>
                                    <th>Tempat</th>
                                    <th>Masa Daftar Hadir</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($attendance_list)): ?>
                                    <?php foreach ($attendance_list as $att): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($att['class_date']); ?></td>
                                            <td><strong><?php echo htmlspecialchars($att['class_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($att['class_place']); ?></td>
                                            <td><?php echo htmlspecialchars($att['attendance_time']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: #718096; padding: 25px;">
                                            Ahli ini belum mempunyai sebarang rekod kehadiran kelas atau program.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                    </div>
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