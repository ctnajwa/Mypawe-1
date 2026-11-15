<?php
session_start();
$conn = new mysqli("localhost", "root", "", "mypawe");
if ($conn->connect_error) {
    die("Sambungan pangkalan data gagal: " . $conn->connect_error);
}

$message = "";
$status_type = "";
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ic = trim($_POST['ic_number'] ?? '');
    $clean_ic = preg_replace('/[^0-9]/', '', $ic);

    if (!empty($clean_ic)) {
        // Find member
        $stmt = $conn->prepare("SELECT m.member_id, u.user_name FROM member m 
                                JOIN public p ON m.public_id = p.public_id 
                                JOIN user u ON p.public_id = u.user_id 
                                WHERE REPLACE(u.user_ic, '-', '') = ? AND (m.member_status = 'Aktif' OR m.member_status = 'Approved') LIMIT 1");
        $stmt->bind_param("s", $clean_ic);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            $member_id = $row['member_id'];
            $member_name = $row['user_name'];

            // Find today's class
            $c_q = $conn->prepare("SELECT class_id, class_name FROM class WHERE class_date = ? LIMIT 1");
            $c_q->bind_param("s", $today);
            $c_q->execute();
            $c_res = $c_q->get_result()->fetch_assoc();
            $c_q->close();

            if ($c_res) {
                $class_id = $c_res['class_id'];
                
                // Check if already checked in
                $chk = $conn->prepare("SELECT attendance_id FROM attendance WHERE member_id = ? AND class_id = ? LIMIT 1");
                $chk->bind_param("ii", $member_id, $class_id);
                $chk->execute();
                if ($chk->get_result()->num_rows === 0) {
                    $ins = $conn->prepare("INSERT INTO attendance (member_id, class_id, attendance_time) VALUES (?, ?, NOW())");
                    $ins->bind_param("ii", $member_id, $class_id);
                    $ins->execute();
                    $ins->close();
                    
                    $message = "Kehadiran Berjaya Direkodkan!<br><strong>Selamat Datang, " . htmlspecialchars($member_name) . "</strong>";
                    $status_type = "success";
                } else {
                    $message = "Perhatian: " . htmlspecialchars($member_name) . ", anda sudah direkodkan hadir hari ini.";
                    $status_type = "warning";
                }
                $chk->close();
            } else {
                $message = "Maaf, tiada kelas atau program rasmi berjadual pada hari ini.";
                $status_type = "error";
            }
        } else {
            $message = "No. KP tidak dijumpai atau status keahlian belum aktif.";
            $status_type = "error";
        }
        $stmt->close();
    } else {
        $message = "Sila masukkan nombor Kad Pengenalan yang sah.";
        $status_type = "error";
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Self-Check-In Kehadiran Ahli - PAWE Pekan</title>
    <style>
        body {
            margin: 0; padding: 0;
            font-family: Arial, sans-serif;
            background-color: #ffffff;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }
        .kiosk-header {
            background-color: #000000;
            color: #ffffff;
            text-align: center;
            padding: 30px;
        }
        .kiosk-header h1 { margin: 0; font-size: 38px; letter-spacing: 1px; }
        .kiosk-header p { margin: 10px 0 0; font-size: 22px; color: #a0aec0; }
        
        .kiosk-body {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #f7fafc;
        }
        .kiosk-card {
            background-color: #fef08a; /* Yellow theme matching user mockup */
            width: 100%;
            max-width: 750px;
            padding: 50px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            border: 4px solid #facc15;
        }
        .kiosk-card h2 {
            font-size: 32px;
            color: #1a202c;
            margin-top: 0;
            margin-bottom: 30px;
            font-weight: 800;
        }
        .kiosk-input {
            width: 90%;
            padding: 20px;
            font-size: 24px;
            text-align: center;
            border: 2px solid #cbd5e0;
            border-radius: 8px;
            outline: none;
            margin-bottom: 25px;
            background: #fff;
        }
        .kiosk-submit {
            background-color: #2563eb;
            color: #ffffff;
            border: none;
            padding: 18px 50px;
            font-size: 22px;
            font-weight: 800;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        .kiosk-submit:hover { background-color: #1d4ed8; }
        
        .alert-box {
            margin-top: 25px;
            padding: 20px;
            border-radius: 8px;
            font-size: 20px;
            font-weight: 700;
        }
        .alert-success { background-color: #d1fae5; color: #065f46; border: 2px solid #34d399; }
        .alert-warning { background-color: #fef3c7; color: #92400e; border: 2px solid #fbbf24; }
        .alert-error { background-color: #fee2e2; color: #991b1b; border: 2px solid #f87171; }
    </style>
</head>
<body>

    <div class="kiosk-header">
        <h1>KEHADIRAN AHLI</h1>
        <p><?php echo date('d/m/Y'); ?></p>
    </div>

    <div class="kiosk-body">
        <div class="kiosk-card">
            <h2>Sila masukkan No. KP anda tanpa “-”</h2>
            
            <form action="member_self_checkin.php" method="POST">
                <input type="text" name="ic_number" class="kiosk-input" placeholder="Contoh: 601212061234" autocomplete="off" autofocus required>
                <div>
                    <button type="submit" class="kiosk-submit">Hantar</button>
                </div>
            </form>

            <?php if (!empty($message)): ?>
                <div class="alert-box alert-<?php echo $status_type; ?>">
                    <?php echo $message; ?>
                </div>
                <script>
                    // Auto clear message and focus back after 4 seconds
                    setTimeout(function() {
                        window.location.href = 'member_self_checkin.php';
                    }, 4000);
                </script>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>