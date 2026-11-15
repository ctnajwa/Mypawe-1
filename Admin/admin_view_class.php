<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id   = $_SESSION['user_id'];
$admin_name = $_SESSION['user_name'] ?? 'Admin';

$conn = new mysqli("localhost", "root", "", "mypawe");
if ($conn->connect_error) {
    die("Sambungan pangkalan data gagal: " . $conn->connect_error);
}

// Handle class deletion (Buang)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_class_id'])) {
    $del_id = intval($_POST['delete_class_id']);
    
    // Check if class date has passed before allowing deletion
    $check_q = $conn->prepare("SELECT class_date FROM class WHERE class_id = ? LIMIT 1");
    $check_q->bind_param("i", $del_id);
    $check_q->execute();
    $c_data = $check_q->get_result()->fetch_assoc();
    $check_q->close();

    if ($c_data && strtotime($c_data['class_date']) >= strtotime(date('Y-m-d'))) {
        $del_stmt = $conn->prepare("DELETE FROM class WHERE class_id = ?");
        $del_stmt->bind_param("i", $del_id);
        $del_stmt->execute();
        $del_stmt->close();
        $_SESSION['admin_msg'] = "Program telah berjaya dibuang.";
    } else {
        $_SESSION['admin_msg'] = "Ralat: Program yang telah lepas tarikh tidak boleh dibuang.";
    }
    header("Location: admin_view_class.php");
    exit();
}

// Handle Search & Pagination
$search = trim($_GET['search'] ?? '');
$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = 5; // 5 classes per page as seen in your mockup
$offset = ($page - 1) * $limit;

// Build query
$where_sql = "WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $where_sql .= " AND (class_name LIKE ? OR class_place LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types   .= "ss";
}

// Count total records for pagination
$count_sql = "SELECT COUNT(*) as total FROM class " . $where_sql;
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = max(1, ceil($total_records / $limit));
$count_stmt->close();

// Fetch paginated records
$sql = "SELECT class_id, class_name, class_date, class_place, class_status FROM class " . $where_sql . " ORDER BY class_date DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $limit;
$types   .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$classes = [];
while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}
$stmt->close();
$conn->close();

$flash_msg = $_SESSION['admin_msg'] ?? '';
unset($_SESSION['admin_msg']);
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelas & Program - MyPAWE</title>
    <link rel="stylesheet" href="../Style/style_staff_dashboard.css">
    <link rel="stylesheet" href="../Style/style_admin_member.css">
    <link rel="stylesheet" href="../Style/style_admin_staff.css">
    <style>
        .content-body { width: 100%; max-width: 1250px; box-sizing: border-box; margin: 0 auto; padding: 30px 30px; }
        .member-table { width: 100%; max-width: none; }
        .page-heading { font-size: 32px; font-weight: 800; color: #0d1b4c; text-align: center; margin-bottom: 25px; }
        
        /* Action container mimicking screenshot style */
        .class-action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            gap: 15px;
        }
        .search-box-wrap {
            display: flex;
            flex: 1;
            max-width: 700px;
            background: #fff;
            border: 1px solid #cbd5e0;
            border-radius: 8px;
            overflow: hidden;
        }
        .search-box-input {
            flex: 1;
            padding: 12px 18px;
            font-size: 15px;
            border: none;
            outline: none;
        }
        .btn-search-submit {
            background-color: #ffffff;
            color: #1a202c;
            border: none;
            border-left: 1px solid #cbd5e0;
            padding: 0 25px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-search-submit:hover { background-color: #f7fafc; }
        
        .btn-tambah-program {
            background-color: #0d4669;
            color: #ffffff;
            padding: 12px 22px;
            border-radius: 8px;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
            transition: background 0.2s;
        }
        .btn-tambah-program:hover { background-color: #09324d; }

        /* Action Buttons in table */
        .btn-lihat {
            background-color: #63b3ed;
            color: #ffffff;
            padding: 5px 14px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            display: inline-block;
        }
        .btn-lihat:hover { background-color: #4299e1; }

        .btn-buang {
            background-color: #fc8181;
            color: #ffffff;
            border: none;
            padding: 5px 14px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            margin-left: 4px;
        }
        .btn-buang:hover { background-color: #e53e3e; }

        /* Pagination styling */
        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 30px;
        }
        .page-num {
            display: inline-flex;
            width: 32px; height: 32px;
            align-items: center; justify-content: center;
            border-radius: 50%;
            background: #e2e8f0;
            color: #2d3748;
            font-weight: 700;
            text-decoration: none;
        }
        .page-num.active {
            background: #0d1b4c;
            color: #fff;
        }
    </style>
</head>
<body>

    <div class="wrapper">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-logo-placeholder">
                    <img src="../Images/logo_pawe_pekan.jpg" alt="Logo PAWE Pekan" class="logo-placeholder" onerror="this.src='https://via.placeholder.com/140x60?text=PAWE+Pekan'"/>
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

            <div class="sidebar-footer">
                <a href="../logout.php" class="btn-log-keluar">LOG KELUAR</a>
            </div>
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
                <h1 class="page-heading">Kelas & Program</h1>

                <?php if (!empty($flash_msg)): ?>
                    <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
                        <?php echo htmlspecialchars($flash_msg); ?>
                    </div>
                <?php endif; ?>

                <div class="class-action-bar">
                    <form action="admin_view_class.php" method="GET" class="search-box-wrap">
                        <input type="text" name="search" class="search-box-input" placeholder="Cari nama kelas/program" value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn-search-submit">CARI</button>
                    </form>
                    <a href="admin_add_class.php" class="btn-tambah-program">+ Tambah Program</a>
                </div>

                <div class="staff-table-wrapper" style="max-width: 100%;">
                    <table class="member-table">
                        <thead>
                            <tr>
                                <th style="width: 70px; text-align: center;">No.</th>
                                <th>Nama Kelas</th>
                                <th style="width: 160px;">Tarikh</th>
                                <th style="width: 180px; text-align: center;">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($classes)): ?>
                                <?php foreach ($classes as $index => $c): ?>
                                    <tr>
                                        <td style="text-align: center;"><?php echo $offset + $index + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($c['class_name']); ?></strong></td>
                                        <td><?php echo date('d/m/Y', strtotime($c['class_date'])); ?></td>
                                        <td style="text-align: center;">
                                            <a href="admin_class_detail.php?id=<?php echo $c['class_id']; ?>" class="btn-lihat">Lihat</a>
                                            
                                            <!-- Show 'Buang' button ONLY if class date has not passed yet -->
                                            <?php if ($c['class_date'] >= $today): ?>
                                                <form action="admin_view_class.php" method="POST" style="display:inline-block;">
                                                    <input type="hidden" name="delete_class_id" value="<?php echo $c['class_id']; ?>">
                                                    <button type="submit" class="btn-buang" onclick="return confirm('Adakah anda pasti ingin membuang program ini?');">Buang</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="empty-state" style="text-align: center; padding: 40px; color: #718096;">
                                        Tiada rekod kelas atau program dijumpai.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Links -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="admin_view_class.php?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="page-num <?php echo ($page === $i) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
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
            if (subMenu.classList.contains('open')) { arrow.style.transform = 'rotate(180deg)'; } else { arrow.style.transform = 'rotate(0deg)'; }
        }
    </script>
</body>
</html>