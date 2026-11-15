<?php
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
$userName   = $_SESSION['user_name'] ?? '';
$userRole   = $_SESSION['user_role'] ?? '';

$conn = new mysqli("localhost", "root", "", "mypawe");
$announcements = [];
if (!$conn->connect_error) {
    $announcementResult = $conn->query(
        "SELECT announcement_info
         FROM announcement
         WHERE announcement_date <= CURDATE()
           AND announcement_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         ORDER BY announcement_id DESC"
    );
    if ($announcementResult) {
        while ($announcement = $announcementResult->fetch_assoc()) {
            $announcements[] = $announcement['announcement_info'];
        }
    }
    $conn->close();
}

// Set the target destinations depending on session status
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
    <title>MyPAWE - Portal Utama</title>
    <link rel="stylesheet" href="Style/style_portal.css?v=5">
</head>
<body>

    <?php if (isset($_SESSION['eligibility_result'])): ?>
        <script>
            alert(<?php echo json_encode($_SESSION['eligibility_result'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);
        </script>
        <?php
        unset($_SESSION['eligibility_result']);
        unset($_SESSION['eligibility_status']);
        ?>
    <?php endif; ?>

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
                        <li><a href="<?php echo $volunteerLink; ?>">Pendaftaran Sukarelawan</a></li>
                    </ul>
                </li>
            </ul>
        </nav>

        <!-- Dynamic User Auth Header Section -->
        <div class="header-auth-area">
            <?php if ($isLoggedIn && $userRole === 'Pengguna Awam'): ?>
                <!-- Logged in state: Display name with dropdown -->
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
                <!-- Guest state: Default yellow button -->
                <a href="login.php" class="login-btn">Log Masuk</a>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($announcements): ?>
        <!-- ANNOUNCEMENT TICKER -->
        <div class="ticker-wrap">
            <div class="ticker">
                <?php for ($copy = 0; $copy < 2; $copy++): ?>
                    <div class="ticker-group"<?php echo $copy === 1 ? ' aria-hidden="true"' : ''; ?>>
                        <?php foreach ($announcements as $announcementText): ?>
                            <span class="ticker-item">📢 <?php echo htmlspecialchars($announcementText); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- HERO SECTION -->
    <section class="hero">
        <div class="hero-background" aria-hidden="true"></div>
        <button type="button" class="hero-arrow hero-arrow-prev" aria-label="Gambar sebelumnya">&#10094;</button>
        <button type="button" class="hero-arrow hero-arrow-next" aria-label="Gambar seterusnya">&#10095;</button>
        <div class="hero-text">
            <h1>Selamat Datang ke MyPAWE</h1>
            <p>Pusat Aktiviti Warga Emas Pekan, Pahang</p>
        </div>
    </section>

    <!-- QUICK ACTION FORM -->
    <div class="main-container">
        <div class="card-form">
            <h2>Semak Kelayakan Umur</h2>
            <p>Sila masukkan nombor Kad Pengenalan anda untuk semakan pendaftaran.</p>
            <form action="check_eligibility.php" method="POST">
                <div class="form-group">
                    <label for="ic">Nombor Kad Pengenalan (IC)</label>
                    <input type="text" id="ic" name="user_ic" placeholder="Contoh: 550101101234" maxlength="12" autocomplete="off" required>
                </div>
                <button type="submit" class="submit-btn">SEMAK SEKARANG</button>
            </form>

            <?php if (isset($_SESSION['eligibility_msg'])): ?>
                <div style="margin-top: 15px; font-size: 15px; font-weight: 600; text-align: center; color: <?php echo ($_SESSION['eligibility_type'] === 'success') ? '#16a34a' : '#dc2626'; ?>;">
                    <?php 
                        echo $_SESSION['eligibility_msg']; 
                        unset($_SESSION['eligibility_msg']);
                        unset($_SESSION['eligibility_type']);
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- FACEBOOK BANNER -->
    <section class="container volunteer-section">
        <div class="volunteer-banner">
            <div class="volunteer-text">
                <h2>Lihat kami di Facebook</h2>
                <p>Ikuti halaman Facebook kami untuk mendapat info terkini tentang aktiviti dan program yang kami adakan.</p>
            </div>
            <a href="https://www.facebook.com/profile.php?id=100095356512214" class="btn-volunteer" target="_blank" rel="noopener noreferrer">Lihat Halaman</a>
        </div>
    </section>

    <!-- CONTACT & MAP -->
    <section class="container contact-section-wrapper">
        <div class="section-title">
            <h2>Hubungi Kami</h2>
            <p>Kami sedia membantu anda.</p>
        </div>
        
        <div class="contact-section">
            <div class="contact-info">
                <div class="contact-item">
                    <div class="contact-icon">📍</div>
                    <div>
                        <h4>Alamat</h4>
                        <p>Lot 2592, Kampung Geylang,<br>26600 Pekan, Pahang Darul Makmur.</p>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="contact-icon">📞</div>
                    <div>
                        <h4>Telefon / WhatsApp</h4>
                        <p>09-422 3776 (Pejabat)<br>012-345 6789 (Admin)</p>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="contact-icon">✉️</div>
                    <div>
                        <h4>Emel</h4>
                        <p>pawepekan2026@gmail.com</p>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="contact-icon">🕒</div>
                    <div>
                        <h4>Waktu Operasi</h4>
                        <p>Isnin - Khamis: 8:00 AM - 2:00 PM<br>Jumaat: 8:00 AM - 12:30 PM<br>Sabtu & Ahad: Tutup</p>
                    </div>
                </div>
            </div>
            <div class="map-container">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15937.890996275948!2d103.38000000000001!3d3.5300000000000002!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zM8KwMzEnNDguMCJOIDEwM8KwMjInNDguMCJF!5e0!3m2!1sen!2smy!4v1600000000000!5m2!1sen!2smy" allowfullscreen="" loading="lazy"></iframe>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer>
        <p>&copy; <?php echo date('Y'); ?> MyPAWE - Pusat Aktiviti Warga Emas</p>
    </footer>

    <script>
        const heroImages = [
            'Images/pawe2.jpg',
            'Images/pawe.jpeg',
            'Images/pawe1.jpg',
            'Images/pawe7.jpg'
        ];

        const hero = document.querySelector('.hero');
        const heroBackground = document.querySelector('.hero-background');
        const previousButton = document.querySelector('.hero-arrow-prev');
        const nextButton = document.querySelector('.hero-arrow-next');
        let currentHeroImage = 0;

        function showHeroImage(imageIndex) {
            currentHeroImage = (imageIndex + heroImages.length) % heroImages.length;
            heroBackground.style.backgroundImage = `url("${heroImages[currentHeroImage]}")`;
        }

        previousButton.addEventListener('click', () => {
            showHeroImage(currentHeroImage - 1);
        });

        nextButton.addEventListener('click', () => {
            showHeroImage(currentHeroImage + 1);
        });

        showHeroImage(currentHeroImage);

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