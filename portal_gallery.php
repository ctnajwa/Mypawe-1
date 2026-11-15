<?php
session_start();

$isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
$userName   = $_SESSION['user_name'] ?? '';
$userRole   = $_SESSION['user_role'] ?? '';
$memberLink = $isLoggedIn
    ? 'register_member.php'
    : 'login.php?redirect=register_member.php';

$volunteerLink = $isLoggedIn
    ? 'register_volunteer.php'
    : 'login.php?redirect=register_volunteer.php';

// Connect to database to fetch upcoming classes & past classes with gallery pictures
$conn = new mysqli("localhost", "root", "", "mypawe");
$upcoming_classes = [];
$past_classes = [];

if (!$conn->connect_error) {
    $today = date('Y-m-d');

    // 1. Fetch Upcoming Classes
    $up_sql = "SELECT * FROM class WHERE class_date >= ? ORDER BY class_date ASC";
    $up_stmt = $conn->prepare($up_sql);
    $up_stmt->bind_param("s", $today);
    $up_stmt->execute();
    $up_res = $up_stmt->get_result();
    while ($row = $up_res->fetch_assoc()) {
        $upcoming_classes[] = $row;
    }
    $up_stmt->close();

    // 2. Fetch Past Classes & Pictures
    $sql = "SELECT c.class_id, c.class_name, c.class_date, c.class_place, c.class_info, c.class_involve 
            FROM class c 
            WHERE c.class_date < ? 
            ORDER BY c.class_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $c_id = $row['class_id'];
        // Fetch pictures for this class
        $pic_q = $conn->prepare("SELECT picture_path FROM class_pictures WHERE class_id = ?");
        $pic_q->bind_param("i", $c_id);
        $pic_q->execute();
        $pic_res = $pic_q->get_result();
        $pictures = [];
        while ($p = $pic_res->fetch_assoc()) {
            $pictures[] = $p['picture_path'];
        }
        $pic_q->close();

        // If no pictures uploaded, add a fallback placeholder
        if (empty($pictures)) {
            $pictures[] = 'Images/logo_pawe_pekan.jpg';
        }

        $row['pictures'] = $pictures;
        $past_classes[] = $row;
    }
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maklumat Kelas - MyPAWE</title>
    <link rel="stylesheet" href="Style/style_portal.css?v=3">
    <link rel="stylesheet" href="Style/style_class_info.css?v=2">
    <style>
        /* Slider styling for card galleries */
        .card-slider {
            position: relative;
            width: 100%;
            height: 220px;
            overflow: hidden;
            background: #e2e8f0;
        }
        .card-slider img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
        }
        .card-slider img.active-slide {
            display: block;
        }
        .slider-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0,0,0,0.5);
            color: white;
            border: none;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 14px;
            border-radius: 4px;
            z-index: 10;
        }
        .slider-btn.prev { left: 10px; }
        .slider-btn.next { right: 10px; }
        .slider-btn:hover { background: rgba(0,0,0,0.8); }
    </style>
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
                        <li><a href="public_profile.php"><span class="menu-icon"></span> Profil</a></li>
                        <li><a href="member_status.php"><span class="menu-icon"></span> Status Keahlian</a></li>
                        <li class="menu-divider"></li>
                        <li><a href="logout.php" class="menu-logout"><span class="menu-icon"></span> Log Keluar</a></li>
                    </ul>
                </div>
            <?php else: ?>
                <a href="login.php" class="login-btn">Log Masuk</a>
            <?php endif; ?>
        </div>
    </header>

    <!-- HERO SECTION -->
    <section class="hero">
        <div class="hero-text">
            <h1>Galeri Aktiviti</h1>
            <p>Lihat apa yang berlaku di pusat kami.</p>
        </div>
    </section>

    <!-- NEWS & GALLERY (Dynamic Past Classes) -->
    <section class="container news-section-box">
        
        <div class="grid-3">
            <?php if (!empty($past_classes)): ?>
                <?php foreach ($past_classes as $index => $cls): ?>
                    <div class="news-card">
                        <!-- Image Slider Component -->
                        <div class="card-slider" id="slider-<?php echo $index; ?>">
                            <?php foreach ($cls['pictures'] as $p_idx => $pic_path): ?>
                                <img src="<?php echo htmlspecialchars($pic_path); ?>" alt="Foto Aktiviti" class="<?php echo ($p_idx === 0) ? 'active-slide' : ''; ?>">
                            <?php endforeach; ?>
                            
                            <?php if (count($cls['pictures']) > 1): ?>
                                <button class="slider-btn prev" onclick="moveSlide(<?php echo $index; ?>, -1)">&#10094;</button>
                                <button class="slider-btn next" onclick="moveSlide(<?php echo $index; ?>, 1)">&#10095;</button>
                            <?php endif; ?>
                        </div>

                        <div class="news-content">
                            <span class="news-date">📅 <?php echo date('d/m/Y', strtotime($cls['class_date'])); ?> | 📍 <?php echo htmlspecialchars($cls['class_place']); ?></span>
                            <h3><?php echo htmlspecialchars($cls['class_name']); ?></h3>
                            <p><?php echo htmlspecialchars($cls['class_info']); ?></p>
                            <?php if (!empty($cls['class_involve'])): ?>
                                <p style="font-size: 13px; color: #4a5568; margin-top: 6px;"><strong>Tetamu/Penceramah:</strong> <?php echo htmlspecialchars($cls['class_involve']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: span 3; text-align: center; color: #718096; padding: 40px;">
                    Tiada rekod aktiviti lepas atau galeri dijumpai buat masa ini.
                </div>
            <?php endif; ?>
        </div>
    </section>



    <!-- FOOTER -->
    <footer>
        <p>&copy; <?php echo date('Y'); ?> MyPAWE - Pusat Aktiviti Warga Emas</p>
    </footer>

    <!-- DROPDOWN & SLIDER SCRIPT -->
    <script>
        function moveSlide(sliderIndex, direction) {
            const slider = document.getElementById('slider-' + sliderIndex);
            const images = slider.querySelectorAll('img');
            let activeIndex = 0;

            images.forEach((img, idx) => {
                if (img.classList.contains('active-slide')) {
                    activeIndex = idx;
                    img.classList.remove('active-slide');
                }
            });

            let newIndex = activeIndex + direction;
            if (newIndex >= images.length) newIndex = 0;
            if (newIndex < 0) newIndex = images.length - 1;

            images[newIndex].classList.add('active-slide');
        }

        const dropdown = document.querySelector('.dropdown');
        const dropdownToggle = document.querySelector('.dropdown-toggle');
        const userDropdown = document.querySelector('#dropdown-user');
        const userDropdownToggle = document.querySelector('.user-menu-toggle');

        if (dropdown && dropdownToggle) {
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
        }
    </script>

</body>
</html>