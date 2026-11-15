<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id   = $_SESSION['user_id'];
$admin_name = $_SESSION['user_name'] ?? 'Admin';
$class_id   = intval($_GET['id'] ?? 0);

if ($class_id <= 0) {
    header("Location: admin_view_class.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "mypawe");
if ($conn->connect_error) {
    die("Sambungan pangkalan data gagal: " . $conn->connect_error);
}

// Fetch Class Data first to check date
$stmt = $conn->prepare("SELECT * FROM class WHERE class_id = ? LIMIT 1");
$stmt->bind_param("i", $class_id);
$stmt->execute();
$class = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$class) {
    $conn->close();
    header("Location: admin_view_class.php");
    exit();
}

$is_passed = ($class['class_date'] < date('Y-m-d'));

// Handle Form Update (Only allowed if class has NOT passed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_class']) && !$is_passed) {
    $class_name    = trim($_POST['class_name'] ?? '');
    $class_date    = trim($_POST['class_date'] ?? '');
    $class_place   = trim($_POST['class_place'] ?? '');
    $class_info    = trim($_POST['class_info'] ?? '');
    $class_involve = trim($_POST['class_involve'] ?? '');

    $up_stmt = $conn->prepare("UPDATE class SET class_name = ?, class_date = ?, class_place = ?, class_info = ?, class_involve = ? WHERE class_id = ?");
    $up_stmt->bind_param("sssssi", $class_name, $class_date, $class_place, $class_info, $class_involve, $class_id);
    $up_stmt->execute();
    $up_stmt->close();
    $_SESSION['admin_msg'] = "Maklumat program berjaya dikemaskini.";
    header("Location: admin_class_detail.php?id=" . $class_id);
    exit();
}

// Handle Multiple Image Uploads for Past Classes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['class_gallery'])) {
    $gallery_dir = __DIR__ . '/../uploads/gallery/';
    if (!is_dir($gallery_dir)) { mkdir($gallery_dir, 0755, true); }

    foreach ($_FILES['class_gallery']['tmp_name'] as $key => $tmp_name) {
        if (!empty($_FILES['class_gallery']['name'][$key])) {
            $ext = pathinfo($_FILES['class_gallery']['name'][$key], PATHINFO_EXTENSION);
            $filename = 'gallery_' . $class_id . '_' . time() . '_' . $key . '.' . $ext;
            if (move_uploaded_file($tmp_name, $gallery_dir . $filename)) {
                $path = 'uploads/gallery/' . $filename;
                $img_stmt = $conn->prepare("INSERT INTO class_pictures (class_id, picture_path) VALUES (?, ?)");
                $img_stmt->bind_param("is", $class_id, $path);
                $img_stmt->execute();
                $img_stmt->close();
            }
        }
    }
    $_SESSION['admin_msg'] = "Gambar galeri berjaya dimuat naik.";
    header("Location: admin_class_detail.php?id=" . $class_id);
    exit();
}

// Handle Single Picture Deletion from Gallery
if (isset($_GET['delete_pic'])) {
    $pic_id = intval($_GET['delete_pic']);
    $p_qry = $conn->prepare("SELECT picture_path FROM class_pictures WHERE picture_id = ? AND class_id = ? LIMIT 1");
    $p_qry->bind_param("ii", $pic_id, $class_id);
    $p_qry->execute();
    $p_res = $p_qry->get_result()->fetch_assoc();
    $p_qry->close();

    if ($p_res) {
        if (file_exists(__DIR__ . '/../' . $p_res['picture_path'])) {
            @unlink(__DIR__ . '/../' . $p_res['picture_path']);
        }
        $d_qry = $conn->prepare("DELETE FROM class_pictures WHERE picture_id = ?");
        $d_qry->bind_param("i", $pic_id);
        $d_qry->execute();
        $d_qry->close();
    }
    header("Location: admin_class_detail.php?id=" . $class_id);
    exit();
}

// Refresh Class Data if updated
$stmt = $conn->prepare("SELECT * FROM class WHERE class_id = ? LIMIT 1");
$stmt->bind_param("i", $class_id);
$stmt->execute();
$class = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch Uploaded Gallery Pictures
$pic_stmt = $conn->prepare("SELECT * FROM class_pictures WHERE class_id = ?");
$pic_stmt->bind_param("i", $class_id);
$pic_stmt->execute();
$pictures = $pic_stmt->get_result();
$pic_stmt->close();

$conn->close();

$mode = (!$is_passed && ($_GET['mode'] ?? 'view') === 'edit') ? 'edit' : 'view';
$flash_msg = $_SESSION['admin_msg'] ?? '';
unset($_SESSION['admin_msg']);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maklumat Program - <?php echo htmlspecialchars($class['class_name']); ?></title>
    <link rel="stylesheet" href="../Style/style_staff_dashboard.css">
    <link rel="stylesheet" href="../Style/style_admin_member.css">
    <link rel="stylesheet" href="../Style/style_admin_staff.css">
    <style>
        .form-card { max-width: 850px; margin: 0 auto 50px; background: #fff; border-radius: 12px; padding: 35px 40px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 700; color: #2d3748; margin-bottom: 6px; font-size: 14px; }
        .form-control { width: 100%; padding: 11px 14px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 15px; outline: none; background: #f7fafc; box-sizing: border-box; }
        .form-control:focus { background: #fff; border-color: #026aa7; }
        .form-control:disabled, .form-control[readonly] { background-color: #edf2f7; cursor: not-allowed; color: #4a5568; font-weight: 600; }
        
        .btn-simpan { background-color: #026aa7; color: #fff; border: none; padding: 12px 30px; border-radius: 6px; font-weight: 700; font-size: 16px; cursor: pointer; float: right; }
        .btn-simpan:hover { background-color: #014c78; }
        .btn-edit { background-color: #026aa7; color: #fff; padding: 12px 30px; border-radius: 6px; font-weight: 700; font-size: 16px; text-decoration: none; display: inline-block; float: right; }
        .btn-edit:hover { background-color: #014c78; }
        .btn-batal { background-color: #e2e8f0; color: #2d3748; padding: 12px 30px; border-radius: 6px; font-weight: 700; font-size: 16px; text-decoration: none; display: inline-block; margin-right: 15px; float: right; }
        .btn-batal:hover { background-color: #cbd5e0; }

        /* Gallery Grid */
        .gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 15px; margin-top: 15px; }
        .gallery-item { position: relative; border-radius: 8px; overflow: hidden; border: 1px solid #cbd5e0; height: 110px; background: #edf2f7; }
        .gallery-item img { width: 100%; height: 100%; object-fit: cover; }
        .btn-delete-img { position: absolute; top: 5px; right: 5px; background: rgba(229, 62, 62, 0.9); color: #fff; border: none; border-radius: 50%; width: 24px; height: 24px; font-size: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body>
    <div class="wrapper">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-logo-placeholder">
                    <img src="../Images/logo_pawe_pekan.jpg" alt="Logo PAWE Pekan" onerror="this.src='https://via.placeholder.com/140x60?text=PAWE+Pekan'"/>
                </div>
            </div>
            <ul class="sidebar-menu">
                <li class="menu-item"><a href="admin_dashboard.php" class="menu-link">Dashboard</a></li>
                <li class="menu-item has-dropdown" id="menuProfil">
                    <div class="menu-link" onclick="toggleMenu('menuProfil', 'subProfil', 'arrowProfil')">
                        <div class="menu-content">
                            <span>Pengurusan Profil</span>
                            <span id="arrowProfil" style="font-size: 12px;">&#9662;</span>
                        </div>
                    </div>
                    <ul class="sub-menu" id="subProfil">
                        <li><a href="admin_personal_profile.php">Profil Peribadi</a></li>
                        <li><a href="admin_view_staff.php">Profil Staf</a></li>
                    </ul>
                </li>
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
                <li class="menu-item active"><a href="admin_view_class.php" class="menu-link">Kelas & Program</a></li>
                <li class="menu-item"><a href="admin_announcement.php" class="menu-link">Pengumuman</a></li>
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
                <li class="menu-item"><a href="admin_report.php" class="menu-link">Laporan</a></li>
            </ul>
            <div class="sidebar-footer"><a href="../logout.php" class="btn-log-keluar">LOG KELUAR</a></div>
        </aside>

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
                    <div style="margin-bottom: 25px;">
                        <a href="admin_view_class.php" style="color: #0d1b4c; font-weight: 700; text-decoration: none; font-size: 18px;">&larr; </a>
                        <h2 style="font-size: 26px; color: #0d1b4c; font-weight: 800; text-align: center; margin-top: -10px;">Maklumat Program</h2>
                    </div>

                    <?php if (!empty($flash_msg)): ?>
                        <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px;"><?php echo htmlspecialchars($flash_msg); ?></div>
                    <?php endif; ?>

                    <!-- Form View / Edit Mode -->
                    <form action="admin_class_detail.php?id=<?php echo $class_id; ?>" method="POST">
                        <input type="hidden" name="update_class" value="1">
                        
                        <div class="form-group">
                            <label>Nama Program</label>
                            <input type="text" class="form-control" name="class_name" value="<?php echo htmlspecialchars($class['class_name']); ?>" <?php echo ($mode === 'view') ? 'readonly' : 'required'; ?>>
                        </div>
                        <div class="form-group">
                            <label>Tarikh</label>
                            <input type="date" class="form-control" name="class_date" value="<?php echo htmlspecialchars($class['class_date']); ?>" <?php echo ($mode === 'view') ? 'readonly' : 'required'; ?>>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <input type="text" class="form-control" value="<?php echo $is_passed ? 'SELESAI (LEPAS)' : 'AKTIF (AKAN DATANG)'; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Tempat</label>
                            <input type="text" class="form-control" name="class_place" value="<?php echo htmlspecialchars($class['class_place']); ?>" <?php echo ($mode === 'view') ? 'readonly' : 'required'; ?>>
                        </div>
                        <div class="form-group">
                            <label>Maklumat Program</label>
                            <textarea class="form-control" name="class_info" rows="3" <?php echo ($mode === 'view') ? 'readonly' : ''; ?>><?php echo htmlspecialchars($class['class_info']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>PIC / Penceramah Terlibat</label>
                            <input type="text" class="form-control" name="class_involve" value="<?php echo htmlspecialchars($class['class_involve']); ?>" <?php echo ($mode === 'view') ? 'readonly' : ''; ?>>
                        </div>
                        
                        <!-- Show Edit Button ONLY if class has NOT passed -->
                        <?php if (!$is_passed): ?>
                            <div style="clear: both; margin-bottom: 30px;">
                                <?php if ($mode === 'view'): ?>
                                    <a href="admin_class_detail.php?id=<?php echo $class_id; ?>&mode=edit" class="btn-edit">Edit Program</a>
                                <?php else: ?>
                                    <button type="submit" class="btn-simpan">Simpan Perubahan</button>
                                    <a href="admin_class_detail.php?id=<?php echo $class_id; ?>" class="btn-batal">Batal</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </form>

                    <!-- MULTIPLE IMAGE GALLERY SECTION (Active only for passed/past classes) -->
                    <?php if ($is_passed): ?>
                        <hr style="border: 0; border-top: 2px solid #edf2f7; margin: 40px 0 25px;">
                        <h3 style="font-size: 18px; color: #0d1b4c; margin-bottom: 15px;">🖼️ Galeri Gambar Aktiviti Lepas</h3>
                        
                        <form action="admin_class_detail.php?id=<?php echo $class_id; ?>" method="POST" enctype="multipart/form-data" style="margin-bottom: 20px;">
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input type="file" name="class_gallery[]" multiple accept="image/*" class="form-control" style="background:#fff;" required>
                                <button type="submit" style="background: #2b6cb0; color: #fff; border: none; padding: 11px 20px; border-radius: 6px; font-weight: 700; cursor: pointer; white-space: nowrap;">Muat Naik</button>
                            </div>
                        </form>

                        <div class="gallery-grid">
                            <?php if ($pictures->num_rows > 0): ?>
                                <?php while ($pic = $pictures->fetch_assoc()): ?>
                                    <div class="gallery-item">
                                        <img src="../<?php echo htmlspecialchars($pic['picture_path']); ?>" alt="Foto Galeri">
                                        <a href="admin_class_detail.php?id=<?php echo $class_id; ?>&delete_pic=<?php echo $pic['picture_id']; ?>" class="btn-delete-img" onclick="return confirm('Padam gambar ini?');" title="Padam">&times;</a>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p style="grid-column: span 3; color: #718096; font-size: 14px;">Tiada gambar galeri dimuat naik untuk program ini.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </main>
        </div>
    </div>
</body>
</html>