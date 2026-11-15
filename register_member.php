<?php
session_start();

// Ensure user is logged in as 'Pengguna Awam'
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Pengguna Awam') {
    header("Location: login.php?redirect=" . urlencode(basename(__FILE__)));
    exit();
}

$user_id = $_SESSION['user_id'];
$isLoggedIn = true;
$userName   = $_SESSION['user_name'] ?? '';
$userRole   = $_SESSION['user_role'] ?? '';

$memberLink = 'register_member.php';
$volunteerLink = 'register_volunteer.php';

// Database connection
$conn = new mysqli("localhost", "root", "", "mypawe");
if ($conn->connect_error) {
    die("Sambungan pangkalan data gagal: " . $conn->connect_error);
}

// 1. Fetch immutable login details from `user` table
$u_stmt = $conn->prepare("SELECT user_name, user_email, user_ic FROM user WHERE user_id = ? LIMIT 1");
$u_stmt->bind_param("s", $user_id);
$u_stmt->execute();
$userData = $u_stmt->get_result()->fetch_assoc();
$u_stmt->close();

$user_name  = $userData['user_name'] ?? '';
$user_email = $userData['user_email'] ?? '';
$user_ic    = $userData['user_ic'] ?? '';

// 2. Clean and extract birth date from IC (first 6 digits)
$clean_ic = preg_replace('/[^0-9]/', '', $user_ic);
$calc_birthday = '';
if (strlen($clean_ic) >= 6) {
    $yy = (int)substr($clean_ic, 0, 2);
    $mm = substr($clean_ic, 2, 2);
    $dd = substr($clean_ic, 4, 2);
    
    // Malaysian NRIC Year cutoff: 26 or below = 2000s, above 26 = 1900s
    $full_year = ($yy <= 26) ? (2000 + $yy) : (1900 + $yy);
    $calc_birthday = "$dd/$mm/$full_year";
}

// 3. Fetch existing draft inputs across tables
function getTableData($conn, $table, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM `$table` WHERE public_id = ? LIMIT 1");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res ?: [];
}

$pubData  = getTableData($conn, 'public', $user_id);
$hlthData = getTableData($conn, 'health', $user_id);
$wrkData  = getTableData($conn, 'work', $user_id);
$volData  = getTableData($conn, 'volunteer', $user_id);
$famData  = getTableData($conn, 'family', $user_id);

// Ensure public_birthday uses calculated IC birthday if existing db field is blank
$final_birthday = (!empty($pubData['public_birthday'])) ? $pubData['public_birthday'] : $calc_birthday;

// Check membership status
$m_stmt = $conn->prepare("SELECT member_status FROM member WHERE public_id = ? LIMIT 1");
$m_stmt->bind_param("s", $user_id);
$m_stmt->execute();
$memData = $m_stmt->get_result()->fetch_assoc();
$m_stmt->close();

$current_status = $memData['member_status'] ?? 'DRAF';
$conn->close();

if ($current_status !== 'DRAF') {
    header("Location: member_status.php");
    exit();
}

$memberLink = 'register_member.php';

$requested_step = (int)($_GET['step'] ?? 1);
$initial_step = max(1, min(6, $requested_step));
$healthGameComplete = ($_SESSION['member_health_game_completed_for'] ?? '') === $user_id
    && isset($_SESSION['memory_game_time'], $_SESSION['color_game_time']);

if ($initial_step > 5 && !$healthGameComplete) {
    header("Location: register_member.php?step=5");
    exit();
}

$msg = $_SESSION['member_msg'] ?? '';
unset($_SESSION['member_msg']);
$error = $_SESSION['member_error'] ?? '';
unset($_SESSION['member_error']);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Keahlian - MyPAWE</title>
    <link rel="stylesheet" href="Style/style_portal.css?v=4">
    <link rel="stylesheet" href="Style/style_member_reg.css?v=2">
    <style>
        .uppercase-input {
            text-transform: uppercase;
        }
        .guardian-section-heading {
            color: var(--primary-blue);
            font-size: 21px;
            margin: 28px 0 14px;
            padding: 12px 14px;
            border-top: 3px solid var(--accent-yellow);
            border-bottom: 1px solid #cbd5e0;
            background: #f8fafc;
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
                        <li><a href="<?php echo $memberLink; ?>">Pendaftaran Ahli</a></li>
                        <li><a href="<?php echo $volunteerLink; ?>">Pendaftaran Sukarelawan</a></li>
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
            <h1>Pendaftaran Ahli PAWE Pekan</h1>
        </div>
    </section>

    <div class="wizard-container">

        <?php if (!empty($msg)): ?>
            <div style="background: #d4edda; color: #155724; padding: 14px 18px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #c3e6cb;">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 14px 18px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #f5c6cb;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($current_status !== 'DRAF'): ?>
            <div class="form-step-card active" style="text-align: center; padding: 50px 30px;">
                <h2 style="color: var(--primary-blue); margin-bottom: 12px;">Permohonan Anda Sedang Diproses</h2>
                <p style="color: #4a5568; font-size: 17px; max-width: 600px; margin: 0 auto 25px;">
                    Status keahlian anda sedang diproses. <a href="member_status.php">Tekan sini untuk lihat.</a>
                </p>
            </div>
        <?php else: ?>

            <!-- Step Progress Indicator -->
            <ul class="step-progressbar">
                <li class="step-item active" data-step="1"><button type="button" class="step-circle step-nav-button" aria-label="Pergi ke Langkah 1: Peribadi">1</button><div class="step-label">Peribadi</div></li>
                <li class="step-item" data-step="2"><button type="button" class="step-circle step-nav-button" aria-label="Pergi ke Langkah 2: Kesihatan">2</button><div class="step-label">Kesihatan</div></li>
                <li class="step-item" data-step="3"><button type="button" class="step-circle step-nav-button" aria-label="Pergi ke Langkah 3: Pekerjaan">3</button><div class="step-label">Pekerjaan</div></li>
                <li class="step-item" data-step="4"><button type="button" class="step-circle step-nav-button" aria-label="Pergi ke Langkah 4: Sukarela">4</button><div class="step-label">Sukarela</div></li>
                <li class="step-item" data-step="5"><button type="button" class="step-circle step-nav-button" aria-label="Pergi ke Langkah 5: Keluarga">5</button><div class="step-label">Keluarga</div></li>
                <li class="step-item" data-step="6"><button type="button" class="step-circle step-nav-button" aria-label="Pergi ke Langkah 6: Perakuan">6</button><div class="step-label">Perakuan</div></li>
            </ul>

            <form id="memberRegForm" action="register_member_action.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action_type" id="action_type" value="save_draft">
                <input type="hidden" name="current_step" id="form_current_step" value="<?php echo $initial_step; ?>">

                <!-- STEP 1: MAKLUMAT PERIBADI -->
                <div class="form-step-card active" id="step-1">
                    <h3 class="step-heading">Langkah 1: Maklumat Peribadi</h3>
                    
                    <!-- 1. Upload Picture  -->
                    <div class="photo-upload-section">
                        <div class="photo-preview-box" id="avatarPreviewBox">
                            <?php if (!empty($pubData['public_picture']) && file_exists(__DIR__ . '/' . $pubData['public_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($pubData['public_picture']); ?>" alt="Foto Profil" id="avatarImg">
                            <?php else: ?>
                                <span class="photo-preview-placeholder" id="avatarPlaceholder"> Gambar </span>
                                <img src="" alt="Foto Profil" id="avatarImg" style="display: none;">
                            <?php endif; ?>
                        </div>
                        <div style="flex: 1;">
                            <label style="font-weight: 700; color: #2d3748; margin-bottom: 6px; display: block;">Muat Naik Gambar Profil</label>
                            <input type="file" class="form-control" name="public_picture" id="pictureInput" accept="image/*" onchange="previewAvatar(this);">
                            <span class="helper-text">Format dibenarkan: JPG, PNG. Saiz cadangan: Ukuran pasport latar belakang biru/putih.</span>
                        </div>
                    </div>

                    <!-- 2. Nama Penuh & 3. Nombor IC -->
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

                    <!-- 4. Emel & 5. Tarikh Lahir -->
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

                    <!-- 6. Jantina  & 7. Warganegara  -->
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
                            <?php 
                            $curr_cit = strtoupper($pubData['public_citizenship'] ?? 'WARGANEGARA');
                            ?>
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
                            <button type="button" class="btn-wizard btn-save-draft" onclick="saveDraft(1)">Simpan </button>
                            <button type="button" class="btn-wizard btn-next" onclick="goToStep(2)">Seterusnya &rarr;</button>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: MAKLUMAT KESIHATAN -->
                <div class="form-step-card" id="step-2">
                    <h3 class="step-heading">Langkah 2: Maklumat Kesihatan</h3>

                    <div class="form-group-row">
                        <label>Adakah anda mempunyai masalah kesihatan?</label>
                        <div class="radio-group">
                            <label class="radio-label"><input type="radio" name="health_status" value="YA" <?php echo (strtoupper($hlthData['health_status'] ?? '') === 'YA') ? 'checked' : ''; ?> required> YA</label>
                            <label class="radio-label"><input type="radio" name="health_status" value="TIDAK" <?php echo (strtoupper($hlthData['health_status'] ?? '') === 'TIDAK') ? 'checked' : ''; ?>> TIDAK</label>
                        </div>
                    </div>

                    <div class="form-group-row">
                        <label>Nyatakan (Jika ada):</label>
                        <textarea class="form-control uppercase-input" name="health_info" rows="3" placeholder="CONTOH: DARAH TINGGI, KENCING MANIS" oninput="this.value = this.value.toUpperCase();"><?php echo htmlspecialchars($hlthData['health_info'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group-row">
                        <label>Adakah anda memegang status Orang Kurang Upaya (OKU)?</label>
                        <div class="radio-group">
                            <label class="radio-label"><input type="radio" name="oku_status" value="YA" id="oku_ya" <?php echo (strtoupper($hlthData['oku_status'] ?? '') === 'YA') ? 'checked' : ''; ?> onchange="toggleOkuField()" required> YA</label>
                            <label class="radio-label"><input type="radio" name="oku_status" value="TIDAK" id="oku_tidak" <?php echo (strtoupper($hlthData['oku_status'] ?? '') === 'TIDAK') ? 'checked' : ''; ?> onchange="toggleOkuField()"> TIDAK</label>
                        </div>
                    </div>

                    <div class="form-group-row" id="oku_number_wrapper" style="display: <?php echo (strtoupper($hlthData['oku_status'] ?? '') === 'YA') ? 'block' : 'none'; ?>;">
                        <label>Nombor Pendaftaran Kad OKU</label>
                        <input type="text" class="form-control uppercase-input" name="oku_number" id="oku_number" value="<?php echo htmlspecialchars($hlthData['oku_number'] ?? ''); ?>" placeholder="CONTOH: LD100123456789" oninput="this.value = this.value.toUpperCase();">
                    </div>

                    <div class="step-actions">
                        <button type="button" class="btn-wizard btn-prev" onclick="goToStep(1)">&larr; Kembali</button>
                        <div>
                            <button type="button" class="btn-wizard btn-save-draft" onclick="saveDraft(2)">Simpan</button>
                            <button type="button" class="btn-wizard btn-next" onclick="goToStep(3)">Seterusnya &rarr;</button>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: PEKERJAAN -->
                <div class="form-step-card" id="step-3">
                    <h3 class="step-heading">Langkah 3: Maklumat Pekerjaan</h3>

                    <div class="form-grid-2">
                        <div class="form-group-row">
                            <label>Status Pekerjaan</label>
                            <select name="work_status" class="form-control uppercase-input" onchange="toggleWorkFields()" required>
                                <option value="">-- PILIH STATUS --</option>
                                <option value="PESARA" <?php echo (strtoupper($wrkData['work_status'] ?? '') === 'PESARA') ? 'selected' : ''; ?>>PESARA</option>
                                <option value="BEKERJA SENDIRI" <?php echo (strtoupper($wrkData['work_status'] ?? '') === 'BEKERJA SENDIRI') ? 'selected' : ''; ?>>BEKERJA SENDIRI</option>
                                <option value="BEKERJA" <?php echo (strtoupper($wrkData['work_status'] ?? '') === 'BEKERJA') ? 'selected' : ''; ?>>BEKERJA</option>
                                <option value="TIDAK BEKERJA" <?php echo (strtoupper($wrkData['work_status'] ?? '') === 'TIDAK BEKERJA') ? 'selected' : ''; ?>>TIDAK BEKERJA</option>
                            </select>
                        </div>
                        <div class="form-group-row" id="work_jawatan_wrapper">
                            <label>Jawatan</label>
                            <input type="text" class="form-control uppercase-input" name="work_jawatan" id="work_jawatan" value="<?php echo htmlspecialchars($wrkData['work_jawatan'] ?? ''); ?>" placeholder="CONTOH: PEGAWAI KERANI" oninput="this.value = this.value.toUpperCase();">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group-row" id="work_majikan_wrapper">
                            <label>Nama Majikan</label>
                            <input type="text" class="form-control uppercase-input" name="work_majikan" id="work_majikan" value="<?php echo htmlspecialchars($wrkData['work_majikan'] ?? ''); ?>" placeholder="CONTOH: JABATAN PENDIDIKAN" oninput="this.value = this.value.toUpperCase();">
                        </div>
                        <div class="form-group-row">
                            <label>Pengalaman Bekerja (Tahun)</label>
                            <input type="text" class="form-control uppercase-input" name="work_experience" value="<?php echo htmlspecialchars($wrkData['work_experience'] ?? ''); ?>" placeholder="CONTOH: 30 TAHUN" oninput="this.value = this.value.toUpperCase();">
                        </div>
                    </div>

                    <div class="form-group-row">
                        <label>Kemahiran / Bidang Kepakaran (Jika Ada)</label>
                        <input type="text" class="form-control uppercase-input" name="work_expertise" value="<?php echo htmlspecialchars($wrkData['work_expertise'] ?? ''); ?>" placeholder="CONTOH: PERTUKANGAN KAYU, JAHITAN" oninput="this.value = this.value.toUpperCase();">
                    </div>

                    <div class="step-actions">
                        <button type="button" class="btn-wizard btn-prev" onclick="goToStep(2)">&larr; Kembali</button>
                        <div>
                            <button type="button" class="btn-wizard btn-save-draft" onclick="saveDraft(3)">Simpan</button>
                            <button type="button" class="btn-wizard btn-next" onclick="goToStep(4)">Seterusnya &rarr;</button>
                        </div>
                    </div>
                </div>

                <!-- STEP 4: SUKARELAWAN -->
                <div class="form-step-card" id="step-4">
                    <h3 class="step-heading">Langkah 4: Maklumat Sukarelawan</h3>

                    <div class="form-group-row">
                        <label>Adakah anda berminat untuk menyertai program sukarelawan kami?</label>
                        <div class="radio-group">
                            <label class="radio-label"><input type="radio" name="volunteer_interest" value="YA" <?php echo (strtoupper($volData['volunteer_interest'] ?? '') === 'YA') ? 'checked' : ''; ?> required> YA</label>
                            <label class="radio-label"><input type="radio" name="volunteer_interest" value="TIDAK" <?php echo (strtoupper($volData['volunteer_interest'] ?? '') === 'TIDAK') ? 'checked' : ''; ?>> TIDAK</label>
                        </div>
                    </div>

                    <?php 
                    $curr_fields = array_map('trim', explode(',', strtoupper($volData['volunteer_field'] ?? '')));
                    $is_other = false;
                    $other_val = $volData['volunteer_lain_info'] ?? '';
                    foreach ($curr_fields as $f) {
                        if (!in_array($f, ['PERTANIAN', 'SUKAN', 'KEROHANIAN', 'LAIN-LAIN', '']) && !empty($f) && empty($other_val)) {
                            $is_other = true;
                            $other_val = $f;
                        }
                    }
                    $is_other = !empty($other_val) || in_array('LAIN-LAIN', $curr_fields, true);
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

                    <div class="step-actions">
                        <button type="button" class="btn-wizard btn-prev" onclick="goToStep(3)">&larr; Kembali</button>
                        <div>
                            <button type="button" class="btn-wizard btn-save-draft" onclick="saveDraft(4)">Simpan</button>
                            <button type="button" class="btn-wizard btn-next" onclick="goToStep(5)">Seterusnya &rarr;</button>
                        </div>
                    </div>
                </div>

                <!-- STEP 5: MAKLUMAT KELUARGA & PENJAGA -->
                <div class="form-step-card" id="step-5">
                    <h3 class="step-heading">Langkah 5: Maklumat Keluarga & Penjaga</h3>

                    <div class="form-grid-2">
                        <div class="form-group-row">
                            <label>Taraf Perkahwinan</label>
                            <select name="public_wed_status" id="public_wed_status" class="form-control uppercase-input" onchange="toggleFamilyFields()" required>
                                <option value="">-- PILIH TARAF PERKAHWINAN --</option>
                                <option value="SUDAH BERKAHWIN" <?php echo (strtoupper($famData['public_wed_status'] ?? '') === 'SUDAH BERKAHWIN') ? 'selected' : ''; ?>>SUDAH BERKAHWIN</option>
                                <option value="BERCERAI" <?php echo (strtoupper($famData['public_wed_status'] ?? '') === 'BERCERAI') ? 'selected' : ''; ?>>BERCERAI</option>
                                <option value="TIDAK BERKAHWIN" <?php echo (strtoupper($famData['public_wed_status'] ?? '') === 'TIDAK BERKAHWIN') ? 'selected' : ''; ?>>TIDAK BERKAHWIN</option>
                            </select>
                        </div>
                        <div class="form-group-row" id="partner_wrapper">
                            <label>Nama Pasangan</label>
                            <input type="text" class="form-control uppercase-input" name="public_partner_name" id="public_partner_name" value="<?php echo htmlspecialchars($famData['public_partner_name'] ?? ''); ?>" oninput="this.value = this.value.toUpperCase();">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group-row" id="children_wrapper">
                            <label>Bilangan Anak</label>
                            <input type="number" class="form-control uppercase-input" name="public_children" id="public_children" min="0" value="<?php echo htmlspecialchars($famData['public_children'] ?? 0); ?>">
                        </div>
                        <div class="form-group-row">
                            <label>Status Kediaman</label>
                            <select name="public_housing" class="form-control uppercase-input" required>
                                <option value="">-- PILIH STATUS KEDIAMAN --</option>
                                <option value="RUMAH SENDIRI" <?php echo (strtoupper($famData['public_housing'] ?? '') === 'RUMAH SENDIRI') ? 'selected' : ''; ?>>RUMAH SENDIRI</option>
                                <option value="SEWA" <?php echo (strtoupper($famData['public_housing'] ?? '') === 'SEWA') ? 'selected' : ''; ?>>SEWA</option>
                                <option value="KELUARGA" <?php echo (strtoupper($famData['public_housing'] ?? '') === 'KELUARGA') ? 'selected' : ''; ?>>KELUARGA</option>
                            </select>
                        </div>
                    </div>

                    <h4 class="guardian-section-heading">Maklumat Penjaga / Waris Terdekat</h4>

                    <div class="form-grid-2">
                        <div class="form-group-row">
                            <label>Nama Penjaga / Waris </label>
                            <input type="text" class="form-control uppercase-input" name="public_penjaga_name" value="<?php echo htmlspecialchars($famData['public_penjaga_name'] ?? ''); ?>" oninput="this.value = this.value.toUpperCase();" required>
                        </div>
                        <div class="form-group-row">
                            <label>Hubungan</label>
                            <input type="text" class="form-control uppercase-input" name="public_penjaga_relation" value="<?php echo htmlspecialchars($famData['public_penjaga_relation'] ?? ''); ?>" placeholder="CONTOH: ANAK / ADIK" oninput="this.value = this.value.toUpperCase();" required>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group-row">
                            <label>No. Telefon Penjaga</label>
                            <input type="text" class="form-control uppercase-input" name="public_penjaga_phone" value="<?php echo htmlspecialchars($famData['public_penjaga_phone'] ?? ''); ?>" placeholder="CONTOH: 0123456789" oninput="this.value = this.value.toUpperCase();" required>
                        </div>
                        <div class="form-group-row">
                            <label>Alamat Penjaga</label>
                            <input type="text" class="form-control uppercase-input" name="public_penjaga_address" value="<?php echo htmlspecialchars($famData['public_penjaga_address'] ?? ''); ?>" oninput="this.value = this.value.toUpperCase();" required>
                        </div>
                    </div>

                    <div class="step-actions">
                        <button type="button" class="btn-wizard btn-prev" onclick="goToStep(4)">&larr; Kembali</button>
                        <div>
                            <button type="button" class="btn-wizard btn-save-draft" onclick="saveDraft(5)">Simpan</button>
                            <button type="button" class="btn-wizard btn-next" onclick="startHealthGame()">Seterusnya &rarr;</button>
                        </div>
                    </div>
                </div>

                <!-- STEP 6: PERAKUAN & HANTAR -->
                <div class="form-step-card" id="step-6">
                    <h3 class="step-heading">Langkah 6: Perakuan Permohonan</h3>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 25px; margin-bottom: 25px;">
                        <h4 style="color: var(--primary-blue); margin-bottom: 10px;">Pengakuan Pemohon:</h4>
                        <p style="color: #4a5568; line-height: 1.7; font-size: 15px;">
                            Saya dengan ini mengakui bahawa segala maklumat yang diberikan di dalam borang ini adalah benar dan tepat. 
                            Sekiranya maklumat yang diberikan didapati palsu, pihak pengurusan PAWE berhak menolak permohonan keahlian saya.
                        </p>
                        <div style="margin-top: 18px;">
                            <label class="check-label" style="font-weight: 700;">
                                <input type="checkbox" id="declaration_check" required> SAYA MENGESAHKAN MAKLUMAT YANG DINYATAKAN ADALAH BENAR.
                            </label>
                        </div>
                    </div>

                    <div class="step-actions">
                        <button type="button" class="btn-wizard btn-prev" onclick="goToStep(5)">&larr; Kembali</button>
                        <div>
                            <button type="button" class="btn-wizard btn-submit-final" onclick="submitFinalForm()">Hantar Permohonan</button>
                        </div>
                    </div>
                </div>

            </form>
        <?php endif; ?>

    </div>

    <script>
        let currentStep = <?php echo $initial_step; ?>;
        const healthGameComplete = <?php echo $healthGameComplete ? 'true' : 'false'; ?>;

        const registrationDropdown = document.querySelector('.dropdown');
        const registrationDropdownToggle = document.querySelector('.dropdown-toggle');
        const userDropdown = document.querySelector('#dropdown-user');
        const userDropdownToggle = document.querySelector('.user-menu-toggle');

        if (registrationDropdown && registrationDropdownToggle) {
            registrationDropdownToggle.addEventListener('click', () => {
                const isOpen = registrationDropdown.classList.toggle('is-open');
                registrationDropdownToggle.setAttribute('aria-expanded', isOpen);
            });
        }

        if (userDropdown && userDropdownToggle) {
            userDropdownToggle.addEventListener('click', (event) => {
                event.stopPropagation();
                const isOpen = userDropdown.classList.toggle('is-open');
                userDropdownToggle.setAttribute('aria-expanded', isOpen);
            });
        }

        document.addEventListener('click', (event) => {
            if (registrationDropdown && !registrationDropdown.contains(event.target)) {
                registrationDropdown.classList.remove('is-open');
                registrationDropdownToggle.setAttribute('aria-expanded', 'false');
            }

            if (userDropdown && !userDropdown.contains(event.target)) {
                userDropdown.classList.remove('is-open');
                userDropdownToggle.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                if (registrationDropdown) {
                    registrationDropdown.classList.remove('is-open');
                    registrationDropdownToggle.setAttribute('aria-expanded', 'false');
                }

                if (userDropdown) {
                    userDropdown.classList.remove('is-open');
                    userDropdownToggle.setAttribute('aria-expanded', 'false');
                }
            }
        });

        // Instant photo preview function
        function previewAvatar(input) {
            const previewBox = document.getElementById('avatarPreviewBox');
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
            if (step > 5 && !healthGameComplete) {
                alert('Sila lengkapkan permainan saringan selepas Langkah 5 sebelum meneruskan.');
                return;
            }

            document.querySelectorAll('.form-step-card').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.step-item').forEach(el => {
                const s = parseInt(el.getAttribute('data-step'));
                el.classList.remove('active');
                if (s < step) el.classList.add('completed');
                else el.classList.remove('completed');
                if (s === step) {
                    el.classList.add('active');
                    el.querySelector('.step-nav-button').setAttribute('aria-current', 'step');
                } else {
                    el.querySelector('.step-nav-button').removeAttribute('aria-current');
                }
            });

            document.getElementById(`step-${step}`).classList.add('active');
            currentStep = step;
            document.getElementById('form_current_step').value = step;
            window.history.replaceState({}, '', `register_member.php?step=${step}`);
            window.scrollTo({ top: 120, behavior: 'smooth' });
        }

        document.querySelectorAll('.step-nav-button').forEach(button => {
            button.addEventListener('click', () => {
                goToStep(Number(button.closest('.step-item').dataset.step));
            });
        });

        function startHealthGame() {
            const requiredFields = document.querySelectorAll('#step-2 :required, #step-5 :required');
            for (const field of requiredFields) {
                if (!field.checkValidity()) {
                    field.reportValidity();
                    return;
                }
            }

            document.getElementById('action_type').value = 'start_health_game';
            document.getElementById('form_current_step').value = 5;
            document.getElementById('memberRegForm').submit();
        }

        function saveDraft(step) {
            document.getElementById('action_type').value = 'save_draft';
            document.getElementById('form_current_step').value = step;
            document.getElementById('memberRegForm').submit();
        }

        function submitFinalForm() {
            const check = document.getElementById('declaration_check');
            if (!check.checked) {
                alert('Sila tandakan kotak perakuan sebelum menghantar borang.');
                return;
            }
            document.getElementById('action_type').value = 'final_submit';
            document.getElementById('memberRegForm').submit();
        }

        function toggleOkuField() {
            const isYa = document.getElementById('oku_ya').checked;
            document.getElementById('oku_number_wrapper').style.display = isYa ? 'block' : 'none';
        }

        function toggleWorkFields() {
            const workStatus = document.querySelector('select[name="work_status"]').value;
            const isWorking = workStatus === 'BEKERJA';
            const jawatanWrapper = document.getElementById('work_jawatan_wrapper');
            const majikanWrapper = document.getElementById('work_majikan_wrapper');
            const jawatanInput = document.getElementById('work_jawatan');
            const majikanInput = document.getElementById('work_majikan');

            jawatanWrapper.style.display = isWorking ? 'block' : 'none';
            majikanWrapper.style.display = isWorking ? 'block' : 'none';
            jawatanInput.required = isWorking;
            majikanInput.required = isWorking;
        }

        function toggleVolunteerOther() {
            const isOther = document.getElementById('v_other_check').checked;
            document.getElementById('v_other_wrapper').style.display = isOther ? 'block' : 'none';
        }

        function toggleFamilyFields() {
            const status = document.getElementById('public_wed_status').value;
            const partnerField = document.getElementById('partner_wrapper');
            const childrenField = document.getElementById('children_wrapper');
            const partnerInput = document.getElementById('public_partner_name');
            const childrenInput = document.getElementById('public_children');
            const isMarried = status === 'SUDAH BERKAHWIN';
            const hasChildrenSection = isMarried || status === 'BERCERAI';

            partnerField.style.display = isMarried ? 'block' : 'none';
            childrenField.style.display = hasChildrenSection ? 'block' : 'none';
            partnerInput.required = isMarried;
            childrenInput.required = hasChildrenSection;
        }

        goToStep(currentStep);
        toggleFamilyFields();
        toggleWorkFields();
    </script>
</body>
</html>