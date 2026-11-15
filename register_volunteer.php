<?php
session_start();

// Ensure user is logged in as 'Pengguna Awam'
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Pengguna Awam') {
    header("Location: login.php?redirect=" . urlencode(basename(__FILE__)));
    exit();
}

$user_id = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? '';

// Database connection
$conn = new mysqli("localhost", "root", "", "mypawe");
if ($conn->connect_error) {
    die("Sambungan pangkalan data gagal: " . $conn->connect_error);
}

// 1. Fetch immutable identity details from `user` table
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

// 3. Fetch current public and volunteer record
function getTableData($conn, $table, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM `$table` WHERE public_id = ? LIMIT 1");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();$res = $stmt->get_result()->fetch_assoc();$stmt->close();
    return $res ?: [];
}

$pubData = getTableData($conn, 'public', $user_id);$volData = getTableData($conn, 'volunteer',$user_id);

$final_birthday = (!empty($pubData['public_birthday'])) ? $pubData['public_birthday'] :$calc_birthday;
$current_status =$volData['volunteer_status'] ?? 'DRAF';

$conn->close();

$msg =$_SESSION['vol_msg'] ?? '';
unset($_SESSION['vol_msg']);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Sukarelawan - MyPAWE</title>
    <link rel="stylesheet" href="Style/style_portal.css?v=4">
    <link rel="stylesheet" href="Style/style_member_reg.css?v=2">
    <style>
        .uppercase-input {
            text-transform: uppercase;
        }
        .photo-upload-section {
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px dashed #cbd5e0;
        }
        .photo-preview-box {
            width: 130px;
            height: 150px;
            border-radius: 8px;
            border: 2px dashed #a0aec0;
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
        .photo-preview-placeholder {
            font-size: 13px;
            color: #718096;
            text-align: center;
            padding: 10px;
        }
    </style>
</head>
<body>

    <!-- Header identical to portal -->
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
                    <span class="user-display-name"><?php echo htmlspecialchars($userName ?: $user_name); ?></span>
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

    <!-- HERO SECTION -->
    <section class="hero">
        <div class="hero-text">
            <h1>Pendaftaran Sukarelawan</h1>
            <p>Sertai Komuniti Sukarelawan Pusat Aktiviti Warga Emas Pekan</p>
        </div>
    </section>

    <div class="wizard-container">

        <?php if (!empty($msg)): ?>
            <div style="background: #d4edda; color: #155724; padding: 14px 18px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #c3e6cb;">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <?php if ($current_status === 'BERDAFTAR' ||$current_status === 'SEDANG DIPROSES'): ?>
            <div class="form-step-card active" style="text-align: center; padding: 50px 30px;">
                <h2 style="color: var(--primary-blue); margin-bottom: 12px;">Pendaftaran Sukarelawan Diterima</h2>
                <p style="color: #4a5568; font-size: 17px; max-width: 600px; margin: 0 auto 25px;">
                    Terima kasih atas minat anda untuk berbakti kepada komuniti warga emas Pekan. Rekod pendaftaran sukarelawan anda telah disimpan.
                </p>
                <a href="main_portal.php" class="btn-wizard btn-next">Kembali ke Portal</a>
            </div>
        <?php else: ?>

            <!-- Step Progress Indicator (2 Steps Only) -->
            <ul class="step-progressbar">
                <li class="step-item active" data-step="1"><div class="step-circle">1</div><div class="step-label">Peribadi</div></li>
                <li class="step-item" data-step="2"><div class="step-circle">2</div><div class="step-label">Sukarela</div></li>
            </ul>

            <form id="volunteerRegForm" action="register_volunteer_action.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action_type" id="action_type" value="save_draft">
                <input type="hidden" name="current_step" id="form_current_step" value="1">

                <!-- STEP 1: MAKLUMAT PERIBADI -->
                <div class="form-step-card active" id="step-1">
                    <h3 class="step-heading">Langkah 1: Maklumat Peribadi</h3>
                    
                    <!-- 1. Upload Picture -->
                    <div class="photo-upload-section">
                        <div class="photo-preview-box" id="avatarPreviewBox">
                            <?php if (!empty($pubData['public_picture']) && file_exists(__DIR__ . '/' .$pubData['public_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($pubData['public_picture']); ?>" alt="Foto Profil" id="avatarImg">
                            <?php else: ?>
                                <span class="photo-preview-placeholder" id="avatarPlaceholder">Pratonton Gambar Ukuran Pasport</span>
                                <img src="" alt="Foto Profil" id="avatarImg" style="display: none;">
                            <?php endif; ?>
                        </div>
                        <div style="flex: 1;">
                            <label style="font-weight: 700; color: #2d3748; margin-bottom: 6px; display: block;">Muat Naik Gambar Profil (Pilihan Pasport)</label>
                            <input type="file" class="form-control" name="public_picture" id="pictureInput" accept="image/*" onchange="previewAvatar(this);">
                            <span class="helper-text">Format dibenarkan: JPG, PNG. Saiz cadangan: Ukuran pasport latar belakang biru/putih.</span>
                        </div>
                    </div>

                    <!-- 2. Nama Penuh & 3. Nombor IC -->
                    <div class="form-grid-2">
                        <div class="form-group-row">
                            <label>Nama Penuh (Seperti Dalam IC)</label>
                            <input type="text" class="form-control uppercase-input" value="<?php echo htmlspecialchars($user_name); ?>" readonly>
                        </div>
                        <div class="form-group-row">
                            <label>Nombor Kad Pengenalan</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_ic); ?>" readonly>
                        </div>
                    </div>

                    <!-- 4. Emel & 5. Tarikh Lahir -->
                    <div class="form-grid-2">
                        <div class="form-group-row">
                            <label>Emel</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($user_email); ?>" readonly>
                        </div>
                        <div class="form-group-row">
                            <label>Tarikh Lahir (Auto-Ekstrak IC)</label>
                            <input type="text" class="form-control uppercase-input" name="public_birthday" value="<?php echo htmlspecialchars($final_birthday); ?>" readonly>
                        </div>
                    </div>

                    <!-- 6. Jantina & 7. Warganegara -->
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

                    <!-- 8. Bangsa/Kaum & 9. Agama -->
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

                    <!-- 10. Taraf Pendidikan & 11. No. Telefon -->
                    <div class="form-grid-2">
                        <div class="form-group-row">
                            <label>Taraf Pendidikan</label>
                            <select name="public_education" class="form-control uppercase-input" required>
                                <option value="">-- PILIH PENDIDIKAN TERTINGGI --</option>
                                <?php 
                                $edu_list = ["SEKOLAH RENDAH", "PMR/SETARAF", "SPM/SETARAF", "STPM/SETARAF", "SIJIL", "DIPLOMA", "IJAZAH"];
                                foreach ($edu_list as$edu): 
                                ?>
                                    <option value="<?php echo $edu; ?>" <?php echo (($pubData['public_education'] ?? '') === $edu) ? 'selected' : ''; ?>><?php echo$edu; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group-row">
                            <label>No. Telefon</label>
                            <input type="text" class="form-control uppercase-input" name="public_phone" value="<?php echo htmlspecialchars($pubData['public_phone'] ?? ''); ?>" placeholder="CONTOH: 0123456789" oninput="this.value = this.value.toUpperCase();" required>
                        </div>
                    </div>

                    <!-- 12. Alamat Surat-Menyurat -->
                    <div class="form-group-row">
                        <label>Alamat Surat-Menyurat</label>
                        <textarea class="form-control uppercase-input" name="public_address" rows="3" placeholder="MASUKKAN ALAMAT KEDIAMAN LENGKAP" oninput="this.value = this.value.toUpperCase();" required><?php echo htmlspecialchars($pubData['public_address'] ?? ''); ?></textarea>
                    </div>

                    <!-- 13. Salinan IC Proof -->
                    <div class="form-group-row">
                        <label>Salinan Kad Pengenalan (PDF / Imej Gambar Scanned) *</label>
                        <input type="file" class="form-control" name="public_ic_proof" accept="image/*,application/pdf">
                        <?php if (!empty($pubData['public_ic_proof'])): ?>
                            <span class="helper-text">Fail telah dimuat naik: <a href="<?php echo htmlspecialchars($pubData['public_ic_proof']); ?>" target="_blank">Lihat Salinan IC Anda</a></span>
                        <?php endif; ?>
                    </div>

                    <div class="step-actions">
                        <span></span>
                        <div>
                            <button type="button" class="btn-wizard btn-save-draft" onclick="saveDraft(1)">Simpan Draf</button>
                            <button type="button" class="btn-wizard btn-next" onclick="goToStep(2)">Seterusnya &rarr;</button>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: MAKLUMAT SUKARELAWAN -->
                <div class="form-step-card" id="step-2">
                    <h3 class="step-heading">Langkah 2: Minat & Bidang Sukarela</h3>

                    <div class="form-group-row">
                        <label>Adakah anda berminat untuk menyertai program sukarelawan kami?</label>
                        <div class="radio-group">
                            <label class="radio-label">
                                <input type="radio" name="volunteer_interest" value="YA" <?php echo (strtoupper($volData['volunteer_interest'] ?? '') === 'YA' || empty($volData['volunteer_interest'])) ? 'checked' : ''; ?> required> YA
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="volunteer_interest" value="TIDAK" <?php echo (strtoupper($volData['volunteer_interest'] ?? '') === 'TIDAK') ? 'checked' : ''; ?>> TIDAK
                            </label>
                        </div>
                    </div>

                    <?php 
                    $curr_fields = array_map('trim', explode(',', strtoupper($volData['volunteer_field'] ?? '')));$is_other = false;
                    $other_val = '';
                    foreach ($curr_fields as$f) {
                        if (!in_array($f, ['PERTANIAN', 'SUKAN', 'KEROHANIAN', '']) && !empty($f)) {$is_other = true;
                            $other_val =$f;
                        }
                    }
                    ?>
                    <div class="form-group-row">
                        <label>Bidang Sukarela Yang Diminati</label>
                        <div style="display: flex; flex-direction: column; gap: 10px; padding-top: 5px;">
                            <label class="check-label"><input type="checkbox" name="v_fields[]" value="PERTANIAN" <?php echo in_array('PERTANIAN', $curr_fields) ? 'checked' : ''; ?>> PERTANIAN</label>
                            <label class="check-label"><input type="checkbox" name="v_fields[]" value="SUKAN" <?php echo in_array('SUKAN', $curr_fields) ? 'checked' : ''; ?>> SUKAN</label>
                            <label class="check-label"><input type="checkbox" name="v_fields[]" value="KEROHANIAN" <?php echo in_array('KEROHANIAN', $curr_fields) ? 'checked' : ''; ?>> KEROHANIAN</label>
                            <label class="check-label"><input type="checkbox" id="v_other_check" name="v_fields[]" value="LAIN-LAIN" <?php echo $is_other ? 'checked' : ''; ?> onchange="toggleVolunteerOther()"> LAIN-LAIN</label>
                        </div>
                    </div>

                    <div class="form-group-row" id="v_other_wrapper" style="display: <?php echo $is_other ? 'block' : 'none'; ?>;">
                        <label>Sila Nyatakan Bidang Lain-Lain</label>
                        <input type="text" class="form-control uppercase-input" name="v_field_other" id="v_field_other" value="<?php echo htmlspecialchars($other_val); ?>" placeholder="NYATAKAN MINAT ANDA..." oninput="this.value = this.value.toUpperCase();">
                    </div>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-top: 25px;">
                        <label class="check-label" style="font-weight: 700;">
                            <input type="checkbox" id="volunteer_declaration" required> SAYA BERMINAT DAN BERSETUJU UNTUK MENYERTAI AKTIVITI SUKARELAWAN PAWE PEKAN.
                        </label>
                    </div>

                    <div class="step-actions">
                        <button type="button" class="btn-wizard btn-prev" onclick="goToStep(1)">&larr; Kembali</button>
                        <div>
                            <button type="button" class="btn-wizard btn-save-draft" onclick="saveDraft(2)">Simpan Draf</button>
                            <button type="button" class="btn-wizard btn-submit-final" onclick="submitFinalForm()">Hantar Pendaftaran</button>
                        </div>
                    </div>
                </div>

            </form>
        <?php endif; ?>

    </div>

    <!-- FOOTER -->
    <footer>
        <p>&copy; <?php echo date('Y'); ?> MyPAWE - Pusat Aktiviti Warga Emas</p>
      
    </footer>

    <script>
        let currentStep = 1;

        const registrationDropdown = document.querySelector('#dropdown-reg');
        const registrationDropdownToggle = registrationDropdown
            ? registrationDropdown.querySelector('.dropdown-toggle')
            : null;

        if (registrationDropdown && registrationDropdownToggle) {
            registrationDropdownToggle.addEventListener('click', () => {
                const isOpen = registrationDropdown.classList.toggle('is-open');
                registrationDropdownToggle.setAttribute('aria-expanded', String(isOpen));
            });

            document.addEventListener('click', (event) => {
                if (!registrationDropdown.contains(event.target)) {
                    registrationDropdown.classList.remove('is-open');
                    registrationDropdownToggle.setAttribute('aria-expanded', 'false');
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    registrationDropdown.classList.remove('is-open');
                    registrationDropdownToggle.setAttribute('aria-expanded', 'false');
                }
            });
        }

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

        function goToStep(step) {
            document.querySelectorAll('.form-step-card').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.step-item').forEach(el => {
                const s = parseInt(el.getAttribute('data-step'));
                el.classList.remove('active');
                if (s < step) el.classList.add('completed');
                else el.classList.remove('completed');
                if (s === step) el.classList.add('active');
            });

            document.getElementById(`step-${step}`).classList.add('active');
            currentStep = step;
            document.getElementById('form_current_step').value = step;
            window.scrollTo({ top: 120, behavior: 'smooth' });
        }

        function saveDraft(step) {
            document.getElementById('action_type').value = 'save_draft';
            document.getElementById('form_current_step').value = step;
            document.getElementById('volunteerRegForm').submit();
        }

        function submitFinalForm() {
            const check = document.getElementById('volunteer_declaration');
            if (!check.checked) {
                alert('Sila tandakan persetujuan sukarelawan sebelum menghantar pendaftaran.');
                return;
            }
            document.getElementById('action_type').value = 'final_submit';
            document.getElementById('volunteerRegForm').submit();
        }

        function toggleVolunteerOther() {
            const isOther = document.getElementById('v_other_check').checked;
            document.getElementById('v_other_wrapper').style.display = isOther ? 'block' : 'none';
        }
    </script>
</body>
</html>