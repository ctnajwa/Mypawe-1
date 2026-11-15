<?php
session_start();

// Access Guard: Admin only
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id   = $_SESSION['user_id'];
$admin_name = $_SESSION['user_name'] ?? 'Admin';

$staff_id = trim($_GET['id'] ?? '');

if (empty($staff_id)) {
    $_SESSION['admin_msg'] = "Sila pilih staf yang sah.";
    header("Location: admin_view_staff.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "mypawe");
if ($conn->connect_error) {
    die("Sambungan pangkalan data gagal: " . $conn->connect_error);
}

// Fetch all staff and user details
$sql = "SELECT u.user_id, u.user_name, u.user_ic, u.user_email, u.user_role,
               s.staff_birthday, s.staff_gender, s.staff_religion, s.staff_kaum,
               s.staff_education, s.staff_edu_info, s.staff_phone, s.staff_address,
               s.staff_wed_status, s.staff_bank_used, s.staff_no_bank, s.staff_picture
        FROM user u
        INNER JOIN staff s ON u.user_id = s.staff_id
        WHERE u.user_id = ? LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$staff = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$staff) {
    $_SESSION['admin_msg'] = "Rekod staf '$staff_id' tidak dijumpai.";
    header("Location: admin_view_staff.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Penuh Staf - <?php echo htmlspecialchars($staff['user_name']); ?></title>
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

        .staff-avatar-box {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #e2e8f0;
            border: 3px solid #ffc107;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .staff-avatar-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .staff-avatar-initial {
            font-size: 32px;
            font-weight: 800;
            color: #0d1b4c;
        }

        .profile-title-text h2 {
            font-size: 22px;
            margin-bottom: 4px;
            color: #ffffff;
        }

        .profile-title-text p {
            font-size: 14px;
            color: #cbd5e0;
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
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-header:first-of-type {
            margin-top: 0;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px 30px;
        }

        .details-item {
            display: flex;
            flex-direction: column;
            padding: 6px 0;
        }

        .details-label {
            font-size: 13px;
            font-weight: 600;
            color: #718096;
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .details-value {
            font-size: 16px;
            font-weight: 400;
            color: #2d3748;
        }

        .details-item-full {
            grid-column: span 2;
        }

        .profile-footer-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 35px;
            padding-top: 20px;
            border-top: 1px solid #edf2f7;
        }

        .btn-back {
            background-color: #e2e8f0;
            color: #2d3748;
            padding: 10px 22px;
            border-radius: 6px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: background-color 0.2s;
        }

        .btn-back:hover {
            background-color: #cbd5e0;
        }

        .btn-delete-staff {
            background-color: #e53e3e;
            color: #ffffff;
            border: none;
            padding: 10px 24px;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.2s;
        }

        .btn-delete-staff:hover {
            background-color: #c53030;
        }

        @media (max-width: 768px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
            .details-item-full {
                grid-column: span 1;
            }
            .profile-header-banner {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

    <div class="wrapper">
        
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-logo-placeholder">
                    <img src="../Images/logo_pawe_pekan.jpg" alt="Logo PAWE Pekan" onerror="this.src='https://via.placeholder.com/140x60?text=PAWE+Pekan'"/>
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
                <div class="profile-container-card">
                    
                    <!-- Header Banner -->
                    <div class="profile-header-banner">
                        <div class="profile-header-info">
                            <div class="staff-avatar-box">
                                <?php if (!empty($staff['staff_picture']) && file_exists(__DIR__ . '/../' . $staff['staff_picture'])): ?>
                                    <img src="../<?php echo htmlspecialchars($staff['staff_picture']); ?>" alt="Foto Staf">
                                <?php else: ?>
                                    <span class="staff-avatar-initial"><?php echo strtoupper(substr($staff['user_name'], 0, 1)); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="profile-title-text">
                                <h2><?php echo htmlspecialchars($staff['user_name']); ?></h2>
                                <p>ID Staf: <strong><?php echo htmlspecialchars($staff['user_id']); ?></strong> | Peranan: <strong><?php echo htmlspecialchars($staff['user_role']); ?></strong></p>
                            </div>
                        </div>
                        <div>
                            <span class="role-badge <?php echo strtolower($staff['user_role']); ?>" style="font-size: 14px; padding: 6px 14px;">
                                <?php echo htmlspecialchars($staff['user_role']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="profile-body">

                        <!-- 1. Maklumat Akaun & Asas -->
                        <div class="section-header"> Maklumat Akaun & Asas</div>
                        <div class="details-grid">
                            <div class="details-item">
                                <span class="details-label">ID Staf</span>
                                <span class="details-value"><?php echo htmlspecialchars($staff['user_id']); ?></span>
                            </div>
                            <div class="details-item">
                                <span class="details-label">Nama Penuh</span>
                                <span class="details-value"><?php echo htmlspecialchars($staff['user_name']); ?></span>
                            </div>
                            <div class="details-item">
                                <span class="details-label">Nombor Kad Pengenalan</span>
                                <span class="details-value"><?php echo htmlspecialchars($staff['user_ic']); ?></span>
                            </div>
                            <div class="details-item">
                                <span class="details-label">Emel Rasmi</span>
                                <span class="details-value"><?php echo htmlspecialchars($staff['user_email']); ?></span>
                            </div>
                        </div>
                        <br>

                        <!-- 2. Maklumat Peribadi Staf -->
                        <div class="section-header"> Butiran Peribadi</div>
                        <div class="details-grid">
                            <div class="details-item">
                                <span class="details-label">Tarikh Lahir</span>
                                <span class="details-value"><?php echo htmlspecialchars($staff['staff_birthday'] ?: '-'); ?></span>
                            </div>
                            <div class="details-item">
                                <span class="details-label">Jantina</span>
                                <span class="details-value"><?php echo htmlspecialchars($staff['staff_gender'] ?: '-'); ?></span>
                            </div>
                            <div class="details-item">
                                <span class="details-label">Kaum / Bangsa</span>
                                <span class="details-value"><?php echo htmlspecialchars($staff['staff_kaum'] ?: '-'); ?></span>
                            </div>
                            <div class="details-item">
                                <span class="details-label">Agama</span>
                                <span class="details-value"><?php echo htmlspecialchars($staff['staff_religion'] ?: '-'); ?></span>
                            </div>
                            <div class="details-item">
                                <span class="details-label">Taraf Perkahwinan</span>
                                <span class="details-value"><?php echo htmlspecialchars($staff['staff_wed_status'] ?: '-'); ?></span>
                            </div>
                            <div class="details-item">
                                <span class="details-label">Nombor Telefon</span>
                                <span class="details-value"><?php echo htmlspecialchars($staff['staff_phone'] ?: '-'); ?></span>
                            </div>
                            <div class="details-item details-item-full">
                                <span class="details-label">Alamat Kediaman</span>
                                <span class="details-value"><?php echo htmlspecialchars($staff['staff_address'] ?: '-'); ?></span>
                            </div>
                        </div>
                        <br>

                        <!-- 3. Pendidikan -->
                        <div class="section-header"> Maklumat Pendidikan</div>
                        <div class="details-grid">
                            <div class="details-item">
                                <span class="details-label">Taraf Pendidikan</span>
                                <span class="details-value"><?php echo htmlspecialchars($staff['staff_education'] ?: '-'); ?></span>
                            </div>
                            <div class="details-item">
                                <span class="details-label">Butiran Pendidikan (Institusi/Bidang)</span>
                                <span class="details-value"><?php echo htmlspecialchars($staff['staff_edu_info'] ?: '-'); ?></span>
                            </div>
                        </div>
                        <br>

                        <!-- 4. Maklumat Perbankan -->
                        <div class="section-header"> Maklumat Perbankan (Gaji / Elaun)</div>
                        <div class="details-grid">
                            <div class="details-item">
                                <span class="details-label">Nama Bank</span>
                                <span class="details-value"><?php echo htmlspecialchars($staff['staff_bank_used'] ?: '-'); ?></span>
                            </div>
                            <div class="details-item">
                                <span class="details-label">Nombor Akaun Bank</span>
                                <span class="details-value"><?php echo htmlspecialchars($staff['staff_no_bank'] ?: '-'); ?></span>
                            </div>
                        </div>
                        <br>

                        <!-- Actions: Back or Delete -->
                        <div class="profile-footer-actions">
                            <a href="admin_view_staff.php" class="btn-back">&larr; Kembali</a>
                            
                            <?php if ($staff['user_id'] !== $admin_id): ?>
                                <form action="admin_delete_staff_action.php" method="POST" onsubmit="return confirm('Adakah anda pasti ingin memadam rekod staf ini? Tindakan ini akan memadam akaun secara kekal dan tidak boleh dikembalikan.');">
                                    <input type="hidden" name="staff_id" value="<?php echo htmlspecialchars($staff['user_id']); ?>">
                                    <button type="submit" class="btn-delete-staff"> Padam Rekod Staf</button>
                                </form>
                            <?php else: ?>
                                <span style="font-size: 13px; color: #718096; font-style: italic;">*Anda tidak boleh memadam akaun anda sendiri.</span>
                            <?php endif; ?>
                        </div>

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