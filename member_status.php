<?php
session_start();

// Ensure user is logged in as 'Pengguna Awam'
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Pengguna Awam') {
    header("Location: login.php?redirect=" . urlencode(basename(__FILE__)));
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? '';

// Connect to database
$conn = new mysqli("localhost", "root", "", "mypawe");
if ($conn->connect_error) {
    die("Sambungan pangkalan data gagal: " . $conn->connect_error);
}

// Query user, public, member, health, work, volunteer, and family details
$sql = "SELECT 
            u.user_name, u.user_ic, u.user_email, 
            p.public_picture, p.public_phone, p.public_address, 
            p.public_gender, p.public_citizenship, 
            p.public_bangsa, p.public_religion, p.public_education, p.public_ic_proof,
            m.member_id, m.member_status, m.member_reg_date, m.member_reject_reason,
            h.health_status, h.health_info, h.oku_status, h.oku_number,
            w.work_status, w.work_jawatan, w.work_majikan, w.work_experience, w.work_expertise,
            v.volunteer_interest, v.volunteer_field, v.volunteer_lain_info,
            f.public_wed_status, f.public_partner_name, f.public_children, f.public_housing,
            f.public_penjaga_name, f.public_penjaga_relation, f.public_penjaga_phone, f.public_penjaga_address
        FROM user u 
        LEFT JOIN public p ON u.user_id = p.public_id 
        LEFT JOIN member m ON u.user_id = m.public_id 
        LEFT JOIN health h ON u.user_id = h.public_id
        LEFT JOIN work w ON u.user_id = w.public_id
        LEFT JOIN volunteer v ON u.user_id = v.public_id
        LEFT JOIN family f ON u.user_id = f.public_id
        WHERE u.user_id = ? LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

$data['public_birthday'] = '';
$clean_ic = preg_replace('/[^0-9]/', '', $data['user_ic'] ?? '');
if (strlen($clean_ic) >= 6) {
    $yy = (int)substr($clean_ic, 0, 2);
    $mm = substr($clean_ic, 2, 2);
    $dd = substr($clean_ic, 4, 2);
    $full_year = ($yy <= 26) ? (2000 + $yy) : (1900 + $yy);
    $data['public_birthday'] = "$dd/$mm/$full_year";
}

$member_status = $data['member_status'] ?? null;
// Normalize status to uppercase for robust condition matching
$status_upper  = strtoupper(trim($member_status));
$has_registered = !empty($member_status);
$memberLink = 'register_member.php';

// Query recent class attendance history for this member if registered
$attendance_records = [];
if (!empty($data['member_id'])) {
    $att_sql = "SELECT c.class_name, c.class_date, a.attendance_time 
                FROM attendance a 
                JOIN class c ON a.class_id = c.class_id 
                WHERE a.member_id = ? 
                ORDER BY c.class_date DESC LIMIT 5";
    $att_stmt = $conn->prepare($att_sql);
    $att_stmt->bind_param("i", $data['member_id']);
    $att_stmt->execute();
    $att_res = $att_stmt->get_result();
    while ($row = $att_res->fetch_assoc()) {
        $attendance_records[] = $row;
    }
    $att_stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Keahlian - MyPAWE</title>
    <!-- Styles -->
    <link rel="stylesheet" href="Style/style_portal.css?v=4">
    <link rel="stylesheet" href="Style/style_member_status.css?v=3">
</head>
<body>

    <!-- HEADER -->
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

    <!-- HERO SECTION -->
    <section class="hero">
        <div class="hero-text">
            <h1>Status Keahlian</h1>
            <p>Semak maklumat dan pengesahan keahlian anda di PAWE Pekan</p>
        </div>
    </section>

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <?php if ($status_upper === 'APPROVED' || $status_upper === 'LULUS' || $status_upper === 'AKTIF'): ?>
            
            <!-- STATUS BANNER (Approved Member) -->
            <div class="status-banner active">
                <h2>Ahli Aktif</h2>
                <p>Tahniah! Permohonan keahlian anda telah disahkan dan berstatus aktif.</p>
            </div>

            <!-- MEMBER SUMMARY CARD -->
            <div class="summary-card">
                <div class="summary-header">
                    <span>👤</span> Maklumat Keahlian
                </div>
                <div class="summary-body">
                    <div class="summary-row">
                        <span class="summary-label">Nama Penuh</span>
                        <span class="summary-value"><?php echo htmlspecialchars($data['user_name']); ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">No. Kad Pengenalan</span>
                        <span class="summary-value"><?php echo htmlspecialchars($data['user_ic']); ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">No. ID Ahli</span>
                        <span class="summary-value">
                            <span class="member-id-badge"><?php echo 'PAWE-' . str_pad($data['member_id'], 4, '0', STR_PAD_LEFT); ?></span>
                        </span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Tarikh Mendaftar</span>
                        <span class="summary-value"><?php echo htmlspecialchars($data['member_reg_date']); ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">No. Telefon</span>
                        <span class="summary-value"><?php echo htmlspecialchars($data['public_phone'] ?: '-'); ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Emel</span>
                        <span class="summary-value"><?php echo htmlspecialchars($data['user_email']); ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Alamat</span>
                        <span class="summary-value"><?php echo htmlspecialchars($data['public_address'] ?: '-'); ?></span>
                    </div>
                </div>
            </div>

            <!-- ACTIVITY HISTORY -->
            <div class="history-card">
                <div class="history-header">
                     Sejarah Kehadiran Aktiviti
                </div>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Tarikh Aktiviti</th>
                            <th>Nama Kelas / Aktiviti</th>
                            <th>Status Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($attendance_records)): ?>
                            <?php foreach ($attendance_records as $att): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($att['class_date']); ?></td>
                                    <td><?php echo htmlspecialchars($att['class_name']); ?></td>
                                    <td><span style="color: var(--success-green); font-weight: bold;">✅ Hadir</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                    Tiada rekod kehadiran aktiviti direkodkan setakat ini.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($status_upper === 'SEDANG DIPROSES' || $status_upper === 'PENDING' || $status_upper === 'DRAF'): ?>

            <!-- PENDING REVIEW BANNER -->
            <div class="status-banner pending">
                <h2>Sedang Diproses</h2>
                <p>Borang permohonan keahlian anda telah diterima dan sedang dalam semakan pegawai bertugas.</p>
            </div>

            <!-- FULL APPLICATION PREVIEW CARD -->
            <div class="summary-card">
                <div class="summary-header">
                    Butiran Permohonan
                </div>
                <div class="preview-card-body">

                    <div class="preview-section">
                        <div class="preview-section-title">STATUS PERMOHONAN</div>
                        <div class="preview-row">
                            <span class="preview-label">Tarikh Hantar</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['member_reg_date'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Status Semakan</span>
                            <span class="preview-value" style="color: var(--warning-orange);">Dalam Tindakan Pentadbir</span>
                        </div>
                    </div>

                    <!-- LANGKAH 1: MAKLUMAT PERIBADI -->
                    <div class="preview-section">
                        <div class="preview-section-title">LANGKAH 1: MAKLUMAT PERIBADI</div>
                        
                        <div class="profile-pic-preview">
                            <?php if (!empty($data['public_picture']) && file_exists(__DIR__ . '/' . $data['public_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($data['public_picture']); ?>" alt="Foto Profil">
                            <?php else: ?>
                                <div class="profile-pic-placeholder">👤</div>
                            <?php endif; ?>
                        </div>

                        <div class="preview-row">
                            <span class="preview-label">Nama Penuh</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['user_name'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">No. Kad Pengenalan</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['user_ic'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Emel</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['user_email'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Tarikh Lahir</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['public_birthday'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Jantina</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['public_gender'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Warganegara</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['public_citizenship'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Bangsa / Kaum</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['public_bangsa'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Agama</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['public_religion'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Taraf Pendidikan</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['public_education'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">No. Telefon</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['public_phone'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Alamat Surat-Menyurat</span>
                            <span class="preview-value"><?php echo nl2br(htmlspecialchars($data['public_address'] ?: '-')); ?></span>
                        </div>
                    </div>

                    <!-- LANGKAH 2: MAKLUMAT KESIHATAN -->
                    <div class="preview-section">
                        <div class="preview-section-title">LANGKAH 2: MAKLUMAT KESIHATAN</div>
                        <div class="preview-row">
                            <span class="preview-label">Ada Masalah Kesihatan?</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['health_status'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Nyatakan Masalah</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['health_info'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Status OKU?</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['oku_status'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">No. Kad OKU</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['oku_number'] ?: '-'); ?></span>
                        </div>
                    </div>

                    <!-- LANGKAH 3: MAKLUMAT PEKERJAAN -->
                    <div class="preview-section">
                        <div class="preview-section-title">LANGKAH 3: MAKLUMAT PEKERJAAN</div>
                        <div class="preview-row">
                            <span class="preview-label">Status Pekerjaan</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['work_status'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Jawatan</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['work_jawatan'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Nama Majikan</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['work_majikan'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Pengalaman Bekerja</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['work_experience'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Kemahiran / Kepakaran</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['work_expertise'] ?: '-'); ?></span>
                        </div>
                    </div>

                    <!-- LANGKAH 4: MAKLUMAT SUKARELAWAN -->
                    <div class="preview-section">
                        <div class="preview-section-title">LANGKAH 4: MAKLUMAT SUKARELAWAN</div>
                        <div class="preview-row">
                            <span class="preview-label">Minat Menyertai Sukarelawan?</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['volunteer_interest'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Bidang Diminati</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['volunteer_field'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Bidang Lain-lain</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['volunteer_lain_info'] ?: '-'); ?></span>
                        </div>
                    </div>

                    <!-- LANGKAH 5: MAKLUMAT KELUARGA & PENJAGA -->
                    <div class="preview-section">
                        <div class="preview-section-title">LANGKAH 5: MAKLUMAT KELUARGA & PENJAGA</div>
                        <div class="preview-row">
                            <span class="preview-label">Taraf Perkahwinan</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['public_wed_status'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Nama Pasangan</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['public_partner_name'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Bilangan Anak</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['public_children'] ?: '0'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Status Kediaman</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['public_housing'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Nama Penjaga / Waris</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['public_penjaga_name'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Hubungan</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['public_penjaga_relation'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">No. Telefon Penjaga</span>
                            <span class="preview-value"><?php echo htmlspecialchars($data['public_penjaga_phone'] ?: '-'); ?></span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Alamat Penjaga</span>
                            <span class="preview-value"><?php echo nl2br(htmlspecialchars($data['public_penjaga_address'] ?: '-')); ?></span>
                        </div>
                    </div>

                </div>
            </div>

        <?php elseif ($status_upper === 'REJECTED' || $status_upper === 'DITOLAK'): ?>

            <!-- REJECTED BANNER -->
            <div class="status-banner rejected">
                <h2>Permohonan Tidak Berjaya</h2>
                <p><?php echo htmlspecialchars($data['member_reject_reason'] ?: 'Maklumat yang disertakan tidak memenuhi kriteria penubuhan pusat aktiviti.'); ?></p>

            </div>

        <?php else: ?>

            <!-- NOT REGISTERED / DRAFT VIEW -->
            <div class="not-registered">
                <div class="icon"></div>
                <h2>Anda Belum Menghantar Permohonan</h2>
                <a href="register_member.php" class="btn-big">Daftar Keahlian Sekarang</a>
            </div>

        <?php endif; ?>

    </div>

    <!-- FOOTER -->
    <footer>
        <p>&copy; <?php echo date('Y'); ?> MyPAWE - Pusat Aktiviti Warga Emas</p>
    </footer>

    <!-- Dropdown Script -->
    <script>
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