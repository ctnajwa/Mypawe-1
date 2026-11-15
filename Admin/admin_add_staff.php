<?php
session_start();

// Access Guard: Admin only
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id   = $_SESSION['user_id'];
$admin_name = $_SESSION['user_name'] ?? 'Admin';

// Database connection to auto-generate the next Staff ID
$conn = new mysqli("localhost", "root", "", "mypawe");
if ($conn->connect_error) {
    die("Sambungan pangkalan data gagal: " . $conn->connect_error);
}

$used_staff_numbers = [];
$id_query = "
    SELECT staff_id AS existing_id FROM staff WHERE staff_id REGEXP '^S[0-9]{4}$'
    UNION ALL
    SELECT user_id AS existing_id FROM user WHERE user_id REGEXP '^S[0-9]{4}$'
";
$id_result = $conn->query($id_query);

if ($id_result) {
    while ($existing = $id_result->fetch_assoc()) {
        $existing_number = (int)substr($existing['existing_id'], 1);
        $used_staff_numbers[$existing_number] = true;
    }
}

$next_staff_number = 2;
while (isset($used_staff_numbers[$next_staff_number])) {
    $next_staff_number++;
}
$next_staff_id = 'S' . str_pad($next_staff_number, 4, '0', STR_PAD_LEFT);

$conn->close();

$error = $_SESSION['add_staff_error'] ?? '';
unset($_SESSION['add_staff_error']);

$old = $_SESSION['add_staff_old'] ?? [];
unset($_SESSION['add_staff_old']);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Staf Baru - MyPAWE</title>
    <link rel="stylesheet" href="../Style/style_staff_dashboard.css">
    <link rel="stylesheet" href="../Style/style_admin_member.css">
    <link rel="stylesheet" href="../Style/style_admin_staff.css">
    <style>
        .form-card {
            max-width: 800px;
            margin: 0 auto 50px;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
            padding: 35px 40px;
        }

        .form-title {
            font-size: 24px;
            font-weight: 800;
            color: #0d1b4c;
            margin-bottom: 8px;
            text-align: center;
        }

        .form-subtitle {
            font-size: 15px;
            color: #718096;
            margin-bottom: 25px;
            text-align: center;
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
            padding: 10px 14px;
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

        .form-control[readonly] {
            background-color: #edf2f7;
            cursor: not-allowed;
            color: #4a5568;
            font-weight: 700;
        }

        .uppercase-input {
            text-transform: uppercase;
        }

        .helper-text {
            display: block;
            font-size: 12px;
            color: #718096;
            margin-top: 4px;
        }

        .alert-error {
            background-color: #fed7d7;
            color: #9b2c2c;
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 20px;
            border: 1px solid #feb2b2;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #edf2f7;
        }

        .btn-cancel {
            background-color: #e2e8f0;
            color: #2d3748;
            text-decoration: none;
            padding: 10px 24px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
        }

        .btn-cancel:hover {
            background-color: #cbd5e0;
        }

        .btn-submit {
            background-color: #026aa7;
            color: #ffffff;
            border: none;
            padding: 10px 28px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .btn-submit:hover {
            background-color: #014c78;
        }

        @media (max-width: 768px) {
            .form-grid-2 {
                grid-template-columns: 1fr;
            }
            .form-card {
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

        <!-- MAIN CONTENT -->
        <div class="main-layout">
            <header class="top-navbar">
                <div class="notification-icon">&#128276;</div>
                <div class="user-profile-badge">
                    <div class="avatar-initial"><?php echo strtoupper(substr($admin_name, 0, 1)); ?>.</div>
                    <div class="user-info-text">Welcome,<br><strong><?php echo htmlspecialchars($admin_name); ?></strong></div>
                </div>
            </header>

            <main class="content-body">
                <div class="form-card">
                    <h2 class="form-title">Pendaftaran Staf Baru</h2>
                    <p class="form-subtitle">Sila isi butiran staf di bawah.</p>

                    <?php if (!empty($error)): ?>
                        <div class="alert-error">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form action="admin_add_staff_action.php" method="POST" onsubmit="this.querySelector('.btn-submit').disabled = true; this.querySelector('.btn-submit').textContent = 'Mendaftarkan...';">
                        
                        <!-- Staff ID (Auto-Generated)[cite: 2] -->
                        <div class="form-grid-2">
                            <div class="form-group-row">
                                <label for="staff_id">ID Staf</label>
                                <input 
                                    type="text" 
                                    id="staff_id" 
                                    name="staff_id" 
                                    class="form-control" 
                                    value="<?php echo htmlspecialchars($next_staff_id); ?>" 
                                    readonly
                                >
                            </div>

                            <!-- Role Selection[cite: 2] -->
                            <div class="form-group-row">
                                <label for="user_role">Peranan Staf *</label>
                                <select id="user_role" name="user_role" class="form-control" required>
                                    <option value="Staf" <?php echo (($old['user_role'] ?? '') === 'Staf') ? 'selected' : ''; ?>>Staf</option>
                                    <option value="Admin" <?php echo (($old['user_role'] ?? '') === 'Admin') ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                        </div>

                        <!-- Full Name[cite: 2] -->
                        <div class="form-group-row">
                            <label for="user_name">Nama Penuh Staf (Seperti Dalam Kad Pengenalan) *</label>
                            <input 
                                type="text" 
                                id="user_name" 
                                name="user_name" 
                                class="form-control uppercase-input" 
                                value="<?php echo htmlspecialchars($old['user_name'] ?? ''); ?>" 
                                placeholder="MASUKKAN NAMA PENUH" 
                                oninput="this.value = this.value.toUpperCase();"
                                required
                            >
                        </div>

                        <div class="form-grid-2">
                            <!-- IC Number[cite: 2] -->
                            <div class="form-group-row">
                                <label for="user_ic">Nombor Kad Pengenalan *</label>
                                <input 
                                    type="text" 
                                    id="user_ic" 
                                    name="user_ic" 
                                    class="form-control" 
                                    placeholder="Contoh: 950512061234" 
                                    maxlength="12" 
                                    pattern="[0-9]{12}" 
                                    title="Sila masukkan 12 digit nombor Kad Pengenalan tanpa tanda sempang (-)" 
                                    value="<?php echo htmlspecialchars($old['user_ic'] ?? ''); ?>" 
                                    required
                                >
                                <span class="helper-text">*12 digit nombor tanpa tanda '-'</span>
                            </div>

                            <!-- Phone Number[cite: 2] -->
                            <div class="form-group-row">
                                <label for="staff_phone">Nombor Telefon *</label>
                                <input 
                                    type="text" 
                                    id="staff_phone" 
                                    name="staff_phone" 
                                    class="form-control" 
                                    placeholder="Contoh: 0123456789" 
                                    maxlength="15" 
                                    value="<?php echo htmlspecialchars($old['staff_phone'] ?? ''); ?>" 
                                    required
                                >
                            </div>
                        </div>

                        <!-- Email[cite: 2] -->
                        <div class="form-group-row">
                            <label for="user_email">Emel Rasmi Staf *</label>
                            <input 
                                type="email" 
                                id="user_email" 
                                name="user_email" 
                                class="form-control" 
                                placeholder="staf@gmail.com" 
                                value="<?php echo htmlspecialchars($old['user_email'] ?? ''); ?>" 
                                required
                            >
                            <span class="helper-text">*Kata laluan sementara akan dihantar ke emel ini.</span>
                        </div>

                        <div class="form-actions">
                            <a href="admin_view_staff.php" class="btn-cancel">Batal</a>
                            <button type="submit" class="btn-submit">Daftar Staf Baru</button>
                        </div>

                    </form>
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