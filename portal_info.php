<?php
session_start();
$error = '';

$isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
$userName   = $_SESSION['user_name'] ?? '';
$userRole   = $_SESSION['user_role'] ?? '';
$memberLink = $isLoggedIn
    ? 'register_member.php'
    : 'login.php?redirect=register_member.php';

$volunteerLink = $isLoggedIn
    ? 'register_volunteer.php'
    : 'login.php?redirect=register_volunteer.php';
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tentang Kami - MyPAWE</title>
    
    <link rel="stylesheet" href="Style/style_portal.css?v=3">
    <link rel="stylesheet" href="Style/style_info.css?v=2">
</head>
<body>

    <!-- HEADER -->
    <header>
        <a href="main_portal.php" class="logo">
            <img src="Images/logo_pawe_pekan.jpg" alt="Logo MyPAWE">
            <span>MyPAWE</span>
        </a>
        <nav>
            <ul>
                <li><a href="portal_info.php">Tentang Kami</a></li>
                <li><a href="portal_class_info.php">Maklumat Kelas</a></li>
                <li><a href="portal_gallery.php">Galeri Aktiviti</a></li>
                <li class="dropdown" id="dropdown-reg">
                    <button type="button" class="dropdown-toggle" aria-expanded="false" aria-controls="registration-menu">
                        Pendaftaran <span aria-hidden="true">&#9662;</span>
                    </button>
                    <ul class="dropdown-menu" id="registration-menu">
                        <li><a href="<?php echo $memberLink; ?>">Pendaftaran Ahli</a></li>
                        <li><a href="<?php echo $volunteerLink; ?>">Pendaftaran Sukarelawan</a></li>
                    </ul>
                </li>
            </ul>
        </nav>
        <div class="header-auth-area">
            <?php if ($isLoggedIn && $userRole === 'Pengguna Awam'): ?>
                <div class="user-menu-dropdown" id="dropdown-user">
                    <button type="button" class="user-menu-toggle" aria-expanded="false">
                        <span class="welcome-text">Selamat Datang,</span>
                        <span class="user-display-name"><?php echo htmlspecialchars($userName); ?></span>
                        <span class="caret">&#9662;</span>
                    </button>
                    <ul class="user-menu-list">
                        <li>
                            <a href="public_profile.php">
                                <span class="menu-icon"></span> Profil
                            </a>
                        </li>
                        <li>
                            <a href="member_status.php">
                                <span class="menu-icon"></span> Status Keahlian
                            </a>
                        </li>
                        <li class="menu-divider"></li>
                        <li>
                            <a href="logout.php" class="menu-logout">
                                <span class="menu-icon"></span> Log Keluar
                            </a>
                        </li>
                    </ul>
                </div>
            <?php else: ?>
                <a href="login.php" class="login-btn">Log Masuk</a>
            <?php endif; ?>
        </div>
    </header>

    <!-- HERO SECTION -->
    <section class="hero">
        <div class="hero-background info-hero-background" aria-hidden="true"></div>
        <div class="hero-text">
            <h1>Tentang Kami</h1>
            <p>Pusat Aktiviti Warga Emas (PAWE) Pekan Pahang</p>
        </div>
    </section>

    <!-- TOP SECTION: HANDSHAKE + RIGHT COLUMN (WAKTU & VISI MISI) -->
    <div class="container top-overview-container">
        <!-- Left: Handshake Card -->
        <div class="handshake-card">
            <div class="handshake-icon-wrap">
                <img src="Images/handshake.png" alt="Ikon kerjasama" class="handshake-icon">
            </div>
            <p class="handshake-text">
                PAWE Pekan ditubuhkan di bawah <strong>Jabatan Kebajikan Masyarakat (JKM)</strong> untuk warga emas menjalankan aktiviti harian, bersosial dan mendapat perkhidmatan sokongan.
            </p>
        </div>

        <!-- Right: Waktu Operasi & Konsep PAWE -->
        <div class="overview-right-column">
            <!-- Waktu Operasi Card -->
            <div class="overview-waktu-card">
                <h3>Waktu Operasi</h3>
                <div class="waktu-table-wrapper">
                    <div class="waktu-row">
                        <span class="waktu-day">Isnin - Khamis</span>
                        <span class="waktu-time">8.00 am - 2.00 pm</span>
                    </div>
                    <div class="waktu-row">
                        <span class="waktu-day">Jumaat</span>
                        <span class="waktu-time">8.00 am - 12.30 pm</span>
                    </div>
                </div>
            </div>

            <!-- Konsep PAWE -->
            <div class="visi-misi-section">
                <h3 class="visi-misi-title">Konsep PAWE</h3>
                <div class="visi-misi-grid konsep-grid">
                    <div class="vm-card">
                        <p>Memberi kemudahan kepada warga emas untuk menjalankan aktiviti bersama komuniti dengan menekankan elemen Program Berteraskan Komuniti, Intergenerasi dan Interaktif.</p>
                    </div>
                </div>
            </div>

            <!-- Syarat PAWE -->
            <div class="visi-misi-section">
                <h3 class="visi-misi-title">Syarat Pendaftaran</h3>
                <div class="visi-misi-grid konsep-grid">
                    <div class="vm-card">
                        <p>1. Warga emas berusia 60 tahun ke atas.</p>
                        <p>2. Berada dalam keadaan fizikal dan mental yang baik.</p>
                        <p>3. Tinggal di Pekan, Pahang.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- OBJEKTIF SECTION -->
    <section class="container section-padding">
        <div class="section-title">
            <h2>Objektif Penubuhan</h2>
            <p>Matlamat utama penubuhan PAWE Pekan untuk komuniti warga emas</p>
        </div>

        <div class="objectives-grid">
            <div class="objective-card">
                <div class="objective-badge">01</div>
                <h4>Penjagaan Kebajikan</h4>
                <p>Mempelbagai dan memperluaskan kemudahan-kemudahan untuk kesejahteraan dan pembangunan warga emas.</p>
            </div>
            <div class="objective-card">
                <div class="objective-badge">02</div>
                <h4>Penghargaan Kemahiran</h4>
                <p>Menhargai warga emas melalui perkongsian pengalaman dan kepakaran mereka.</p>
            </div>
            <div class="objective-card">
                <div class="objective-badge">03</div>
                <h4>Interaksi Sosial</h4>
                <p>Menggalakkan penglibatan dan kerjasama pelbagai pihak dalam pembangunan warga emas.</p>
            </div>
            <div class="objective-card">
                <div class="objective-badge">04</div>
                <h4>Gaya Hidup Sihat</h4>
                <p>Meningkatkan kualiti hidup warga emas selari dengan konsep warga emas aktif dan produktif.</p>
            </div>
        </div>
    </section>

    <!-- FACILITIES SECTION (Custom Images) -->
    <section class="container section-padding">
        <div class="section-title">
            <h2>Kemudahan di PAWE</h2>
            <p>Ruang dan fasiliti lengkap untuk keselesaan ahli</p>
        </div>

        <div class="facilities-grid">
            <!-- Facility 1 -->
            <div class="facility-card">
                <div class="facility-img-wrapper">
                    <img src="Images/pawe6.jpg" alt="Dewan Serbaguna">
                </div>
                <div class="facility-body">
                    <h3>Dewan Serbaguna</h3>
                    <p>Digunakan untuk senamrobik mingguan, dan majlis keraian bersama ahli.</p>
                </div>
            </div>

            <!-- Facility 2 -->
            <div class="facility-card">
                <div class="facility-img-wrapper">
                    <img src="Images/pawe10.jpg" alt="Bilik Kelas & Komputer" onerror="this.src='https://images.unsplash.com/photo-1524178232363-1fb2b075b655?q=80&w=600&auto=format&fit=crop';">
                </div>
                <div class="facility-body">
                    <h3>Bilik Kelas & Literasi</h3>
                    <p>Ruang selesa untuk kelas agama dan ceramah motivasi.</p>
                </div>
            </div>

            <!-- Facility 3 -->
            <div class="facility-card">
                <div class="facility-img-wrapper">
                    <img src="Images/pawe9.jpg" alt="Bilik Rawatan & Kesihatan" onerror="this.src='https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?q=80&w=600&auto=format&fit=crop';">
                </div>
                <div class="facility-body">
                    <h3>Bilik Senaman</h3>
                    <p>Dilengkapkan dengan peralatan senaman asas sesuai untuk aktiviti kecergasan warga emas.</p>
                </div>
            </div>

            <!-- Facility 4 -->
            <div class="facility-card">
                <div class="facility-img-wrapper">
                    <img src="Images/pawe8.jpg" alt="Sudut Rehat & Santai" onerror="this.src='https://images.unsplash.com/photo-1540555700478-4be289fbecef?q=80&w=600&auto=format&fit=crop';">
                </div>
                <div class="facility-body">
                    <h3>Sudut Rehat & Santai</h3>
                    <p>Kawasan berehat sesuai untuk rehat dan kegiatan sosial.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CONTACT DETAILS SECTION -->
    <section class="container" style="padding-bottom: 60px;">
        <div class="contact-strip-card">
            <div class="contact-strip-header">
                <h3>Pertanyaan & Hubungan</h3>
            </div>
            <div class="contact-strip-grid">
                <div class="contact-strip-item">
                    <div class="cs-icon">📞</div>
                    <div>
                        <h4>Telefon Pejabat</h4>
                        <p>09-422 3776</p>
                    </div>
                </div>
                <div class="contact-strip-item">
                    <div class="cs-icon">✉️</div>
                    <div>
                        <h4>Emel Rasmi</h4>
                        <p>pawepekan2026@gmail.com</p>
                    </div>
                </div>
                <div class="contact-strip-item">
                    <div class="cs-icon">📍</div>
                    <div>
                        <h4>Lokasi</h4>
                        <p>LOT 2592, Kampung Geylang, 26600 Pekan, Pahang</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer>
        <p>&copy; 2026 MyPAWE - Pusat Aktiviti Warga Emas.</p>
    </footer>

    <script>
        const dropdown = document.querySelector('.dropdown');
        const dropdownToggle = document.querySelector('.dropdown-toggle');
        const userDropdown = document.querySelector('#dropdown-user');
        const userDropdownToggle = document.querySelector('.user-menu-toggle');

        dropdownToggle.addEventListener('click', () => {
            const isOpen = dropdown.classList.toggle('is-open');
            dropdownToggle.setAttribute('aria-expanded', isOpen);
        });

        if (userDropdown && userDropdownToggle) {
            userDropdownToggle.addEventListener('click', (event) => {
                event.stopPropagation();
                const isOpen = userDropdown.classList.toggle('is-open');
                userDropdownToggle.setAttribute('aria-expanded', isOpen);
            });
        }

        document.addEventListener('click', (event) => {
            if (!dropdown.contains(event.target)) {
                dropdown.classList.remove('is-open');
                dropdownToggle.setAttribute('aria-expanded', 'false');
            }

            if (userDropdown && !userDropdown.contains(event.target)) {
                userDropdown.classList.remove('is-open');
                userDropdownToggle.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                dropdown.classList.remove('is-open');
                dropdownToggle.setAttribute('aria-expanded', 'false');
                dropdownToggle.focus();

                if (userDropdown) {
                    userDropdown.classList.remove('is-open');
                    userDropdownToggle.setAttribute('aria-expanded', 'false');
                }
            }
        });
    </script>

</body>
</html>