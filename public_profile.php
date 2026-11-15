<?php
session_start();

// Ensure user is logged in as 'Pengguna Awam'
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Pengguna Awam') {
    header("Location: login.php?redirect=" . urlencode(basename(__FILE__)));
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? '';

// Database connection
$conn = new mysqli("localhost", "root", "", "mypawe");
if ($conn->connect_error) {
    die("Sambungan pangkalan data gagal: " . $conn->connect_error);
}

// 1. Fetch immutable details from user table
$u_stmt = $conn->prepare("SELECT user_name, user_email, user_ic FROM user WHERE user_id = ? LIMIT 1");
$u_stmt->bind_param("s", $user_id);
$u_stmt->execute();
$userData = $u_stmt->get_result()->fetch_assoc();
$u_stmt->close();

$user_name  = $userData['user_name'] ?? '';
$user_email = $userData['user_email'] ?? '';
$user_ic    = $userData['user_ic'] ?? '';

// 2. Extract birth date from IC
$clean_ic = preg_replace('/[^0-9]/', '', $user_ic);
$calc_birthday = '';
if (strlen($clean_ic) >= 6) {
    $yy = (int)substr($clean_ic, 0, 2);
    $mm = substr($clean_ic, 2, 2);
    $dd = substr($clean_ic, 4, 2);
    $full_year = ($yy <= 26) ? (2000 + $yy) : (1900 + $yy);
    $calc_birthday = "$dd/$mm/$full_year";
}

// 3. Fetch public table details and volunteer interest
$p_stmt = $conn->prepare("SELECT * FROM public WHERE public_id = ? LIMIT 1");
$p_stmt->bind_param("s", $user_id);
$p_stmt->execute();
$pubData = $p_stmt->get_result()->fetch_assoc() ?: [];
$p_stmt->close();

$v_stmt = $conn->prepare("SELECT volunteer_interest FROM volunteer WHERE public_id = ? LIMIT 1");
$v_stmt->bind_param("s", $user_id);
$v_stmt->execute();
$volData = $v_stmt->get_result()->fetch_assoc() ?: [];
$v_stmt->close();

$conn->close();

$final_birthday = (!empty($pubData['public_birthday'])) ? $pubData['public_birthday'] : $calc_birthday;
$curr_volunteer = strtoupper($volData['volunteer_interest'] ?? 'TIDAK');
$mode = ($_GET['mode'] ?? 'view') === 'edit' ? 'edit' : 'view';

$msg = $_SESSION['profile_msg'] ?? '';
unset($_SESSION['profile_msg']);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Pengguna - MyPAWE</title>
    <link rel="stylesheet" href="Style/style_portal.css?v=4">
    <link rel="stylesheet" href="Style/style_public_profile.css?v=1">
</head>
<body>

    <!-- Header identical to portal-->
    <header>
        <a href="main_portal.php" class="logo">
            <img src="Images/logo_pawe_pekan.jpg" alt="Logo MyPAWE" onerror="this.src='https://via.placeholder.com/60/FFC107/000000?text=Logo'">
            <span>MyPAWE</span>
        </a>
        <nav>
            <ul>
                <li><a href="portal_info.php">Tentang Kami</a></li>
                <li><a href="portal_class_info.php">Maklumat Kelas</a></li>
                <li><a href="portal_gallery.php">Galeri Aktiviti</a></li>
                <li class="dropdown" id="dropdown-reg">
                    <button type="button" class="dropdown-toggle" aria-expanded="false">
                        Pendaftaran <span aria-hidden="true">&#9662;</span>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a href="register_member.php">Pendaftaran Ahli</a></li>
                        <li><a href="register_volunteer.php">Pendaftaran Sukarelawan</a></li>
                    </ul>
                </li>
            </ul>
        </nav>
        
        <div class="header-auth-area">
            <div class="user-menu-dropdown" id="dropdown-user">
                <button type="button" class="user-menu-toggle" aria-expanded="false">
                    <span class="welcome-text">Selamat Datang,</span> 
                    <span class="user-display-name"><?php echo htmlspecialchars($user_name); ?></span>
                    <span class="caret">&#9662;</span>
                </button>
                <ul class="user-menu-list">
                    <li><a href="public_profile.php"><span class="menu-icon"></span> Profil</a></li>
                    <li><a href="member_status.php"><span class="menu-icon"></span> Status Keahlian</a></li>
                    <li class="menu-divider"></li>
                    <li><a href="logout.php" class="menu-logout"><span class="menu-icon"></span> Log Keluar</a></li>
                </ul>
            </div>
        </div>
    </header>


    <div class="profile-container">

        <?php if (!empty($msg)): ?>
            <div style="background: #d4edda; color: #155724; padding: 14px 18px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #c3e6cb;">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="profile-card">
            <h3 class="profile-heading">Maklumat Profil</h3>

            <?php if ($mode === 'view'): ?>
                <div class="profile-action-row">
                    <a href="public_profile.php?mode=edit" class="btn-profile btn-kemaskini">Edit Profil</a>
                </div>

                <div class="photo-section">
                    <div class="photo-preview-box">
                        <?php if (!empty($pubData['public_picture']) && file_exists(__DIR__ . '/' . $pubData['public_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($pubData['public_picture']); ?>" alt="Foto Profil">
                        <?php else: ?>
                            <span class="photo-placeholder">Tiada Foto Profil</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="profile-details-grid">
                    <div class="profile-detail"><span>Nama Penuh</span><strong><?php echo htmlspecialchars($user_name); ?></strong></div>
                    <div class="profile-detail"><span>Nombor Kad Pengenalan</span><strong><?php echo htmlspecialchars($user_ic); ?></strong></div>
                    <div class="profile-detail"><span>Emel</span><strong><?php echo htmlspecialchars($user_email); ?></strong></div>
                    <div class="profile-detail"><span>Tarikh Lahir</span><strong><?php echo htmlspecialchars($final_birthday); ?></strong></div>
                    <div class="profile-detail"><span>Jantina</span><strong><?php echo htmlspecialchars($pubData['public_gender'] ?? '-'); ?></strong></div>
                    <div class="profile-detail"><span>Warganegara</span><strong><?php echo htmlspecialchars($pubData['public_citizenship'] ?? '-'); ?></strong></div>
                    <div class="profile-detail"><span>Bangsa / Kaum</span><strong><?php echo htmlspecialchars($pubData['public_bangsa'] ?? '-'); ?></strong></div>
                    <div class="profile-detail"><span>Agama</span><strong><?php echo htmlspecialchars($pubData['public_religion'] ?? '-'); ?></strong></div>
                    <div class="profile-detail"><span>Taraf Pendidikan</span><strong><?php echo htmlspecialchars($pubData['public_education'] ?? '-'); ?></strong></div>
                    <div class="profile-detail"><span>No. Telefon</span><strong><?php echo htmlspecialchars($pubData['public_phone'] ?? '-'); ?></strong></div>
                    <div class="profile-detail profile-detail-full"><span>Alamat Surat-Menyurat</span><strong><?php echo nl2br(htmlspecialchars($pubData['public_address'] ?? '-')); ?></strong></div>
                    <div class="profile-detail"><span>Minat Program Sukarelawan</span><strong><?php echo htmlspecialchars($curr_volunteer); ?></strong></div>
                </div>
            <?php else: ?>
            <form action="public_profile_action.php" method="POST" enctype="multipart/form-data">

                <!-- 1. Profile Picture -->
                <div class="photo-section">
                    <div class="photo-preview-box" id="avatarPreviewBox">
                        <?php if (!empty($pubData['public_picture']) && file_exists(__DIR__ . '/' . $pubData['public_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($pubData['public_picture']); ?>" alt="Foto Profil" id="avatarImg">
                        <?php else: ?>
                            <span class="photo-placeholder" id="avatarPlaceholder">Tiada Foto Profil</span>
                            <img src="" alt="Foto Profil" id="avatarImg" style="display: none;">
                        <?php endif; ?>
                    </div>
                    <div style="flex: 1;">
                        <label style="font-weight: 700; color: #2d3748; margin-bottom: 6px; display: block;">Tukar Gambar Profil (Pilihan Pasport)</label>
                        <input type="file" class="form-control" name="public_picture" accept="image/*" onchange="previewAvatar(this);">
                        <span class="helper-text">Format dibenarkan: JPG, PNG.</span>
                    </div>
                </div>

                <!-- 2. Read-Only Registration Info -->
                <div class="form-grid-2">
                    <div class="form-group-row">
                        <label>Nama Penuh</label>
                        <input type="text" class="form-control uppercase-input" value="<?php echo htmlspecialchars($user_name); ?>" readonly>
                    </div>
                    <div class="form-group-row">
                        <label>Nombor Kad Pengenalan</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_ic); ?>" readonly>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group-row">
                        <label>Emel</label>
                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($user_email); ?>" readonly>
                    </div>
                    <div class="form-group-row">
                        <label>Tarikh Lahir</label>
                        <input type="text" class="form-control uppercase-input" name="public_birthday" value="<?php echo htmlspecialchars($final_birthday); ?>" readonly>
                    </div>
                </div>

                <!-- 3. Editable Personal Attributes -->
                <div class="form-grid-2">
                    <div class="form-group-row">
                        <label>Jantina</label>
                        <div class="radio-group">
                            <label class="radio-label">
                                <input type="radio" name="public_gender" value="LELAKI" <?php echo (strtoupper($pubData['public_gender'] ?? '') === 'LELAKI') ? 'checked' : ''; ?> required> LELAKI
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="public_gender" value="PEREMPUAN" <?php echo (strtoupper($pubData['public_gender'] ?? '') === 'PEREMPUAN') ? 'checked' : ''; ?>> PEREMPUAN
                            </label>
                        </div>
                    </div>
                    <div class="form-group-row">
                        <label>Warganegara</label>
                        <?php $curr_cit = strtoupper($pubData['public_citizenship'] ?? 'WARGANEGARA'); ?>
                        <div class="radio-group">
                            <label class="radio-label">
                                <input type="radio" name="public_citizenship" value="WARGANEGARA" <?php echo ($curr_cit === 'WARGANEGARA') ? 'checked' : ''; ?> required> WARGANEGARA
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="public_citizenship" value="BUKAN WARGANEGARA" <?php echo ($curr_cit === 'BUKAN WARGANEGARA') ? 'checked' : ''; ?>> BUKAN WARGANEGARA
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group-row">
                        <label>Bangsa / Kaum</label>
                        <input type="text" class="form-control uppercase-input" name="public_bangsa" value="<?php echo htmlspecialchars($pubData['public_bangsa'] ?? ''); ?>" placeholder="CONTOH: MELAYU" oninput="this.value = this.value.toUpperCase();" required>
                    </div>
                    <div class="form-group-row">
                        <label>Agama</label>
                        <input type="text" class="form-control uppercase-input" name="public_religion" value="<?php echo htmlspecialchars($pubData['public_religion'] ?? ''); ?>" placeholder="CONTOH: ISLAM" oninput="this.value = this.value.toUpperCase();" required>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group-row">
                        <label>Taraf Pendidikan</label>
                        <select name="public_education" class="form-control uppercase-input" required>
                            <option value="">-- PILIH PENDIDIKAN TERTINGGI --</option>
                            <?php 
                            $edu_list = ["SEKOLAH RENDAH", "PMR/SETARAF", "SPM/SETARAF", "STPM/SETARAF", "SIJIL", "DIPLOMA", "IJAZAH"];
                            foreach ($edu_list as $edu): 
                            ?>
                                <option value="<?php echo $edu; ?>" <?php echo (($pubData['public_education'] ?? '') === $edu) ? 'selected' : ''; ?>><?php echo $edu; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-row">
                        <label>No. Telefon</label>
                        <input type="text" class="form-control uppercase-input" name="public_phone" value="<?php echo htmlspecialchars($pubData['public_phone'] ?? ''); ?>" placeholder="CONTOH: 0123456789" oninput="this.value = this.value.toUpperCase();" required>
                    </div>
                </div>

                <div class="form-group-row">
                    <label>Alamat Surat-Menyurat</label>
                    <textarea class="form-control uppercase-input" name="public_address" rows="3" placeholder="MASUKKAN ALAMAT KEDIAMAN LENGKAP" oninput="this.value = this.value.toUpperCase();" required><?php echo htmlspecialchars($pubData['public_address'] ?? ''); ?></textarea>
                </div>

                <!-- 4. Volunteer Interest Indicator -->
                <h4 class="section-subheading">Minat Program Sukarelawan</h4>
                <div class="form-group-row">
                    <label>Berminat menyertai aktiviti sukarelawan PAWE?</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="volunteer_interest" value="YA" <?php echo ($curr_volunteer === 'YA') ? 'checked' : ''; ?> required> YA
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="volunteer_interest" value="TIDAK" <?php echo ($curr_volunteer === 'TIDAK') ? 'checked' : ''; ?>> TIDAK
                        </label>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="profile-actions">
                    <a href="public_profile.php" class="btn-profile btn-batal-profile">Batal</a>
                    <button type="submit" class="btn-profile btn-simpan-profile">Simpan Perubahan</button>
                </div>

            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- FOOTER -->
    <footer>
        <p>&copy; <?php echo date('Y'); ?> MyPAWE - Pusat Aktiviti Warga Emas</p>
    </footer>

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

        // Dropdown toggle
        function setupDropdown(containerId) {
            const container = document.getElementById(containerId);
            if (!container) return;
            const toggle = container.querySelector('button');
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                document.querySelectorAll('.is-open').forEach(el => {
                    if (el !== container) el.classList.remove('is-open');
                });
                container.classList.toggle('is-open');
            });
        }

        setupDropdown('dropdown-reg');
        setupDropdown('dropdown-user');

        document.addEventListener('click', () => {
            document.querySelectorAll('.is-open').forEach(el => el.classList.remove('is-open'));
        });
    </script>
</body>
</html>