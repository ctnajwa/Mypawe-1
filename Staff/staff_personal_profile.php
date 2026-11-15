<?php
session_start();

// Strict Access Guard: Only logged-in Staff can access this personal profile
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Staf') {
    header("Location: ../login.php");
    exit();
}

$staff_id   = $_SESSION['user_id'];
$staff_name = $_SESSION['user_name'] ?? 'Staf';

// Database connection
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

// Fetch staff user and staff table details
$sql = "SELECT u.user_id, u.user_name, u.user_email, u.user_ic, u.user_role,
               s.staff_birthday, s.staff_gender, s.staff_religion, s.staff_kaum,
               s.staff_education, s.staff_edu_info, s.staff_phone, s.staff_address,
               s.staff_wed_status, s.staff_bank_used, s.staff_no_bank, s.staff_picture
        FROM user u 
        LEFT JOIN staff s ON u.user_id = s.staff_id 
        WHERE u.user_id = ? LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

$msg = $_SESSION['profile_msg'] ?? '';
unset($_SESSION['profile_msg']);

$error = $_SESSION['profile_error'] ?? '';
unset($_SESSION['profile_error']);

$mode = ($_GET['mode'] ?? 'view') === 'edit' ? 'edit' : 'view';
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Peribadi Staf - MyPAWE</title>
    <link rel="stylesheet" href="../Style/style_staff_dashboard.css">
    <link rel="stylesheet" href="../Style/style_admin_member.css">
    <link rel="stylesheet" href="../Style/style_admin_staff.css">
    <style>
        .main-layout > .top-navbar {
            height: 75px;
            min-height: 75px;
            flex: 0 0 75px;
        }

        .profile-form-card {
            max-width: 900px;
            margin: 0 auto 50px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
            padding: 35px 40px;
        }

        .profile-title-area {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-title-area h2 {
            font-size: 26px;
            color: #0d1b4c;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .profile-title-area p {
            color: #718096;
            font-size: 15px;
        }

        .photo-upload-section {
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 30px;
            padding-bottom: 25px;
            border-bottom: 1px dashed #cbd5e0;
        }

        .photo-preview-box {
            width: 120px;
            height: 140px;
            border-radius: 8px;
            border: 2px solid #a0aec0;
            background-color: #f7fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }

        .photo-preview-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .photo-placeholder {
            font-size: 13px;
            color: #718096;
            text-align: center;
            padding: 10px;
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group-row {
            margin-bottom: 20px;
        }

        .form-group-row label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 11px 14px;
            font-size: 15px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            outline: none;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: #026aa7;
            box-shadow: 0 0 0 3px rgba(2, 106, 167, 0.15);
        }

        .form-control:disabled, .form-control[readonly] {
            background-color: #edf2f7;
            cursor: not-allowed;
            color: #4a5568;
            font-weight: 600;
        }

        .uppercase-input {
            text-transform: uppercase;
        }

        .section-subheading {
            font-size: 18px;
            font-weight: 700;
            color: #0d1b4c;
            margin: 30px 0 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #edf2f7;
        }

        .alert-box {
            padding: 12px 18px;
            border-radius: 6px;
            font-size: 15px;
            margin-bottom: 25px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #fed7d7;
            color: #9b2c2c;
            border: 1px solid #feb2b2;
        }

        .profile-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 35px;
            padding-top: 20px;
            border-top: 1px solid #edf2f7;
        }

        .btn-save {
            background-color: #026aa7;
            color: #ffffff;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .btn-save:hover {
            background-color: #014c78;
        }

        .btn-edit, .btn-cancel {
            display: inline-block;
            padding: 12px 30px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 16px;
            text-decoration: none;
        }

        .btn-edit {
            background-color: #026aa7;
            color: #ffffff;
        }

        .btn-edit:hover {
            background-color: #014c78;
        }

        .btn-cancel {
            background-color: #e2e8f0;
            color: #2d3748;
        }

        .btn-cancel:hover {
            background-color: #cbd5e0;
        }

        @media (max-width: 768px) {
            .form-grid-2 {
                grid-template-columns: 1fr;
            }
            .profile-form-card {
                padding: 25px 20px;
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
                    <img src="../Images/logo_pawe_pekan.jpg" alt="Logo PAWE Pekan" class="logo-placeholder" onerror="this.src='https://via.placeholder.com/140x60?text=PAWE+Pekan'"/>
                </div>
            </div>

            <ul class="sidebar-menu">
                <li class="menu-item">
                    <a href="staff_dashboard.php" class="menu-link">Dashboard</a>
                </li>
                <li class="menu-item active">
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
                <div class="profile-form-card">
                    <div class="profile-title-area">
                        <h2>Profil Peribadi Staf</h2>
                        <p>Paparan dan kemaskini maklumat akaun rasmi anda</p>
                    </div>

                    <?php if (!empty($msg)): ?>
                        <div class="alert-box alert-success"><?php echo htmlspecialchars($msg); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert-box alert-error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <?php if ($mode === 'view'): ?>
                        <div class="photo-upload-section">
                            <div class="photo-preview-box">
                                <?php if (!empty($profile['staff_picture']) && file_exists(__DIR__ . '/../' . $profile['staff_picture'])): ?>
                                    <img src="../<?php echo htmlspecialchars($profile['staff_picture']); ?>" alt="Foto Profil">
                                <?php else: ?>
                                    <span class="photo-placeholder">Tiada Foto Profil</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <strong><?php echo htmlspecialchars($profile['user_name']); ?></strong>
                                <p style="margin: 6px 0 0; color: #718096;"><?php echo htmlspecialchars($profile['user_role']); ?></p>
                            </div>
                        </div>

                        <h4 class="section-subheading" style="margin-top: 0;">Maklumat Pendaftaran Rasmi</h4>
                        <div class="form-grid-2">
                            <div class="form-group-row"><label>ID Staf</label><input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['user_id']); ?>" readonly></div>
                            <div class="form-group-row"><label>Peranan Sistem</label><input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['user_role']); ?>" readonly></div>
                        </div>
                        <div class="form-grid-2">
                            <div class="form-group-row"><label>Nama Penuh</label><input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['user_name']); ?>" readonly></div>
                            <div class="form-group-row"><label>Nombor Kad Pengenalan</label><input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['user_ic']); ?>" readonly></div>
                        </div>
                        <div class="form-group-row"><label>Emel Rasmi</label><input type="email" class="form-control" value="<?php echo htmlspecialchars($profile['user_email']); ?>" readonly></div>

                        <h4 class="section-subheading">Butiran Peribadi</h4>
                        <div class="form-grid-2">
                            <div class="form-group-row"><label>Tarikh Lahir</label><input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['staff_birthday'] ?: 'Tiada Maklumat'); ?>" readonly></div>
                            <div class="form-group-row"><label>Jantina</label><input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['staff_gender'] ?: 'Tiada Maklumat'); ?>" readonly></div>
                            <div class="form-group-row"><label>Kaum / Bangsa</label><input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['staff_kaum'] ?: 'Tiada Maklumat'); ?>" readonly></div>
                            <div class="form-group-row"><label>Agama</label><input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['staff_religion'] ?: 'Tiada Maklumat'); ?>" readonly></div>
                            <div class="form-group-row"><label>Taraf Perkahwinan</label><input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['staff_wed_status'] ?: 'Tiada Maklumat'); ?>" readonly></div>
                            <div class="form-group-row"><label>Nombor Telefon</label><input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['staff_phone'] ?: 'Tiada Maklumat'); ?>" readonly></div>
                        </div>
                        <div class="form-group-row"><label>Alamat Kediaman</label><textarea class="form-control" rows="3" readonly><?php echo htmlspecialchars($profile['staff_address'] ?: 'Tiada Maklumat'); ?></textarea></div>

                        <h4 class="section-subheading">Maklumat Pendidikan</h4>
                        <div class="form-grid-2">
                            <div class="form-group-row"><label>Taraf Pendidikan</label><input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['staff_education'] ?: 'Tiada Maklumat'); ?>" readonly></div>
                            <div class="form-group-row"><label>Butiran Pendidikan</label><input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['staff_edu_info'] ?: 'Tiada Maklumat'); ?>" readonly></div>
                        </div>

                        <h4 class="section-subheading">Maklumat Perbankan</h4>
                        <div class="form-grid-2">
                            <div class="form-group-row"><label>Nama Bank</label><input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['staff_bank_used'] ?: 'Tiada Maklumat'); ?>" readonly></div>
                            <div class="form-group-row"><label>Nombor Akaun Bank</label><input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['staff_no_bank'] ?: 'Tiada Maklumat'); ?>" readonly></div>
                        </div>

                        <div class="profile-actions">
                            <a href="staff_personal_profile.php?mode=edit" class="btn-edit">Edit Profil</a>
                        </div>
                    <?php else: ?>
                    <form action="staff_personal_profile_action.php" method="POST" enctype="multipart/form-data">
                        
                        <!-- Profile Picture at Top with Preview -->
                        <div class="photo-upload-section">
                            <div class="photo-preview-box" id="avatarPreviewBox">
                                <?php if (!empty($profile['staff_picture']) && file_exists(__DIR__ . '/../' . $profile['staff_picture'])): ?>
                                    <img src="../<?php echo htmlspecialchars($profile['staff_picture']); ?>" alt="Foto Profil" id="avatarImg">
                                <?php else: ?>
                                    <span class="photo-placeholder" id="avatarPlaceholder">Tiada Foto Profil</span>
                                    <img src="" alt="Foto Profil" id="avatarImg" style="display: none;">
                                <?php endif; ?>
                            </div>
                            <div style="flex: 1;">
                                <label style="font-weight: 700; color: #2d3748; margin-bottom: 6px; display: block;">Muat Naik Gambar Profil Baru</label>
                                <input type="file" class="form-control" name="staff_picture" accept="image/*" onchange="previewAvatar(this);">
                                <span style="font-size: 12px; color: #718096; margin-top: 4px; display: block;">Format dibenarkan: JPG, PNG.</span>
                            </div>
                        </div>

                        <!-- Read-Only Registration Info -->
                        <h4 class="section-subheading" style="margin-top: 0;">Maklumat Pendaftaran Rasmi</h4>
                        <div class="form-grid-2">
                            <div class="form-group-row">
                                <label>ID Staf</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['user_id']); ?>" readonly>
                            </div>
                            <div class="form-group-row">
                                <label>Peranan Sistem</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['user_role']); ?>" readonly>
                            </div>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group-row">
                                <label>Nama Penuh</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['user_name']); ?>" readonly>
                            </div>
                            <div class="form-group-row">
                                <label>Nombor Kad Pengenalan</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['user_ic']); ?>" readonly>
                            </div>
                        </div>

                        <div class="form-group-row">
                            <label>Emel Rasmi</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($profile['user_email']); ?>" readonly>
                        </div>

                        <!-- Editable Personal Info -->
                        <h4 class="section-subheading">Butiran Peribadi</h4>
                        <div class="form-grid-2">
                            <div class="form-group-row">
                                <label>Tarikh Lahir</label>
                                <input type="date" class="form-control" name="staff_birthday" value="<?php echo htmlspecialchars($profile['staff_birthday'] ?? ''); ?>">
                            </div>
                            <div class="form-group-row">
                                <label>Jantina</label>
                                <select name="staff_gender" class="form-control uppercase-input">
                                    <option value="">-- PILIH JANTINA --</option>
                                    <option value="LELAKI" <?php echo (strtoupper($profile['staff_gender'] ?? '') === 'LELAKI') ? 'selected' : ''; ?>>LELAKI</option>
                                    <option value="PEREMPUAN" <?php echo (strtoupper($profile['staff_gender'] ?? '') === 'PEREMPUAN') ? 'selected' : ''; ?>>PEREMPUAN</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group-row">
                                <label>Kaum / Bangsa</label>
                                <input type="text" class="form-control uppercase-input" name="staff_kaum" value="<?php echo htmlspecialchars($profile['staff_kaum'] ?? ''); ?>" placeholder="CONTOH: MELAYU" oninput="this.value = this.value.toUpperCase();">
                            </div>
                            <div class="form-group-row">
                                <label>Agama</label>
                                <input type="text" class="form-control uppercase-input" name="staff_religion" value="<?php echo htmlspecialchars($profile['staff_religion'] ?? ''); ?>" placeholder="CONTOH: ISLAM" oninput="this.value = this.value.toUpperCase();">
                            </div>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group-row">
                                <label>Taraf Perkahwinan</label>
                                <select name="staff_wed_status" class="form-control uppercase-input">
                                    <option value="">-- PILIH TARAF PERKAHWINAN --</option>
                                    <option value="SUDAH BERKAHWIN" <?php echo (strtoupper($profile['staff_wed_status'] ?? '') === 'SUDAH BERKAHWIN') ? 'selected' : ''; ?>>SUDAH BERKAHWIN</option>
                                    <option value="BERCERAI" <?php echo (strtoupper($profile['staff_wed_status'] ?? '') === 'BERCERAI') ? 'selected' : ''; ?>>BERCERAI</option>
                                    <option value="TIDAK BERKAHWIN" <?php echo (strtoupper($profile['staff_wed_status'] ?? '') === 'TIDAK BERKAHWIN') ? 'selected' : ''; ?>>TIDAK BERKAHWIN</option>
                                </select>
                            </div>
                            <div class="form-group-row">
                                <label>Nombor Telefon</label>
                                <input type="text" class="form-control" name="staff_phone" value="<?php echo htmlspecialchars($profile['staff_phone'] ?? ''); ?>" placeholder="Contoh: 0123456789">
                            </div>
                        </div>

                        <div class="form-group-row">
                            <label>Alamat Kediaman</label>
                            <textarea class="form-control uppercase-input" name="staff_address" rows="3" placeholder="MASUKKAN ALAMAT KEDIAMAN" oninput="this.value = this.value.toUpperCase();"><?php echo htmlspecialchars($profile['staff_address'] ?? ''); ?></textarea>
                        </div>

                        <!-- Educational Info -->
                        <h4 class="section-subheading">Maklumat Pendidikan</h4>
                        <div class="form-grid-2">
                            <div class="form-group-row">
                                <label>Taraf Pendidikan</label>
                                <select name="staff_education" class="form-control uppercase-input">
                                    <option value="">-- PILIH TAHAP PENDIDIKAN --</option>
                                    <?php 
                                    $edu_list = ["SPM/SETARAF", "STPM/SETARAF", "SIJIL", "DIPLOMA", "IJAZAH SARJANA MUDA", "MASTER", "PHD"];
                                    foreach ($edu_list as $edu): 
                                    ?>
                                        <option value="<?php echo $edu; ?>" <?php echo (strtoupper($profile['staff_education'] ?? '') === $edu) ? 'selected' : ''; ?>><?php echo $edu; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group-row">
                                <label>Butiran Pendidikan (Institusi / Bidang)</label>
                                <input type="text" class="form-control uppercase-input" name="staff_edu_info" value="<?php echo htmlspecialchars($profile['staff_edu_info'] ?? ''); ?>" placeholder="CONTOH: IJAZAH SARJANA MUDA SAINS KOMPUTER" oninput="this.value = this.value.toUpperCase();">
                            </div>
                        </div>

                        <!-- Banking Info -->
                        <h4 class="section-subheading">Maklumat Perbankan</h4>
                        <div class="form-grid-2">
                            <div class="form-group-row">
                                <label>Nama Bank</label>
                                <input type="text" class="form-control uppercase-input" name="staff_bank_used" value="<?php echo htmlspecialchars($profile['staff_bank_used'] ?? ''); ?>" placeholder="CONTOH: MAYBANK" oninput="this.value = this.value.toUpperCase();">
                            </div>
                            <div class="form-group-row">
                                <label>Nombor Akaun Bank</label>
                                <input type="text" class="form-control" name="staff_no_bank" value="<?php echo htmlspecialchars($profile['staff_no_bank'] ?? ''); ?>" placeholder="Contoh: 112233445566">
                            </div>
                        </div>

                        <!-- Save & Cancel Buttons -->
                        <div class="profile-actions">
                            <a href="staff_personal_profile.php" class="btn-cancel">Batal</a>
                            <button type="submit" class="btn-save">Simpan</button>
                        </div>

                    </form>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script>
        function previewAvatar(input) {
            const img = document.getElementById('avatarImg');
            const placeholder = document.getElementById('avatarPlaceholder');
            if (input.files && input.files[0]) {
                const reader = new window.FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                    img.style.display = 'block';
                    if (placeholder) placeholder.style.display = 'none';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

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