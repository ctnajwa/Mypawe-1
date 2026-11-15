<?php
session_start();

// Import PHPMailer classes into global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Adjust path according to your folder structure
require __DIR__ . '/../PHPMailer/Exception.php';
require __DIR__ . '/../PHPMailer/PHPMailer.php';
require __DIR__ . '/../PHPMailer/SMTP.php';

// Strict Access Guard: Only Admin can execute this action
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "mypawe");
if ($conn->connect_error) {
    die("Sambungan pangkalan data gagal: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staff_id    = trim($_POST['staff_id'] ?? '');
    $user_name   = trim($_POST['user_name'] ?? '');
    $user_ic     = trim($_POST['user_ic'] ?? '');
    $staff_phone = trim($_POST['staff_phone'] ?? '');
    $user_email  = trim($_POST['user_email'] ?? '');
    $user_role   = trim($_POST['user_role'] ?? 'Staf');

    $_SESSION['add_staff_old'] = [
        'user_name'   => $user_name,
        'user_ic'     => $user_ic,
        'staff_phone' => $staff_phone,
        'user_email'  => $user_email,
        'user_role'   => $user_role
    ];

    // 1. Mandatory Fields Validation
    if (empty($staff_id) || empty($user_name) || empty($user_ic) || empty($staff_phone) || empty($user_email) || empty($user_role)) {
        $_SESSION['add_staff_error'] = "Sila lengkapkan semua maklumat yang bertanda *.";
        header("Location: admin_add_staff.php");
        exit();
    }

    // 2. Validate IC (12 digits only)
    $clean_ic = preg_replace('/[^0-9]/', '',$user_ic);
    if (strlen($clean_ic) !== 12) {
        $_SESSION['add_staff_error'] = "Nombor Kad Pengenalan mestilah 12 digit nombor tanpa tanda (-).";
        header("Location: admin_add_staff.php");
        exit();
    }

    // 3. Extract birth date from IC for staff table[cite: 2]
    $yy = (int)substr($clean_ic, 0, 2);
    $mm = substr($clean_ic, 2, 2);
    $dd = substr($clean_ic, 4, 2);
    $full_year = ($yy <= 26) ? (2000 + $yy) : (1900 +$yy);
    $staff_birthday = "$full_year-$mm-$dd";

    $user_name = mb_strtoupper($user_name, 'UTF-8');

    // 4. Check uniqueness of ID, IC, and Email[cite: 2]
    $check_stmt =$conn->prepare("SELECT user_id, user_email, user_ic FROM user WHERE user_id = ? OR user_email = ? OR user_ic = ? LIMIT 1");
    $check_stmt->bind_param("sss", $staff_id,$user_email, $clean_ic);$check_stmt->execute();
    $check_res =$check_stmt->get_result();

    if ($check_res->num_rows > 0) {
        $existing =$check_res->fetch_assoc();
        if ($existing['user_id'] === $staff_id) {
            $_SESSION['add_staff_error'] = "ID Staf '$staff_id' telah didaftarkan dalam sistem.";
        } elseif ($existing['user_email'] ===$user_email) {
            $_SESSION['add_staff_error'] = "Emel '$user_email' telah digunakan oleh akaun lain.";
        } else {
            $_SESSION['add_staff_error'] = "Nombor Kad Pengenalan '$clean_ic' telah didaftarkan.";
        }
        $check_stmt->close();
        header("Location: admin_add_staff.php");
        exit();
    }
    $check_stmt->close();

    // 5. Generate raw temporary password & separate hash for DB[cite: 2]
    $temp_password   = substr(bin2hex(random_bytes(4)), 0, 6) . rand(10, 99);
    $hashed_password = password_hash($temp_password, PASSWORD_BCRYPT);

    // 6. Database Transaction[cite: 2]
    $conn->begin_transaction();

    try {
        // Insert into `user` table (Stores Hashed Password)[cite: 2]
        $stmt_user =$conn->prepare("INSERT INTO user (user_id, user_email, user_name, user_password, user_ic, user_role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_user->bind_param("ssssss", $staff_id,$user_email, $user_name,$hashed_password, $clean_ic,$user_role);
        $stmt_user->execute();$stmt_user->close();

        // Insert into `staff` table[cite: 2]
        $staff_picture = '';
        $staff_change_password = 1;
        $stmt_staff =$conn->prepare("INSERT INTO staff (staff_id, staff_birthday, staff_phone, staff_picture, staff_change_password) VALUES (?, ?, ?, ?, ?)");
        $stmt_staff->bind_param("ssssi", $staff_id, $staff_birthday, $staff_phone, $staff_picture, $staff_change_password);
        $stmt_staff->execute();$stmt_staff->close();

        $conn->commit();

        // 7. Send Real Email via Gmail SMTP using PHPMailer
        $mail = new PHPMailer(true);$mail_sent = false;

        try {
            // SMTP Settings. Configure these values in PHP/Apache environment variables.
            $smtp_username = getenv('MYPAWE_SMTP_USERNAME');
            $smtp_password = getenv('MYPAWE_SMTP_PASSWORD');
            if (!$smtp_username || !$smtp_password) {
                throw new Exception('Konfigurasi emel SMTP belum ditetapkan pada server.');
            }

            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_username;
            $mail->Password   = str_replace(' ', '', $smtp_password);
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom($smtp_username, 'Sistem MyPAWE Pekan');
            $mail->addAddress($user_email,$user_name);

            // Email Content (Plain-text Temporary Password)
            $mail->isHTML(true);
            $mail->Subject = 'Akaun Staf MyPAWE Pekan - Maklumat Log Masuk';$mail->Body    = "
                <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                    <h2 style='color: #0d1b4c;'>Selamat Datang ke MyPAWE, {$user_name}!</h2>
                    <p>Akaun kakitangan anda telah didaftarkan oleh pihak Pentadbir sistem.</p>
                    <div style='background-color: #f4f6f9; border-left: 4px solid #026aa7; padding: 15px; margin: 20px 0;'>
                        <p style='margin: 0;'><strong>ID Pengguna (Username):</strong> {$staff_id}</p>
                        <p style='margin: 5px 0;'><strong>Kata Laluan Sementara:</strong> <span style='color: #d9534f; font-weight: bold;'>{$temp_password}</span></p>
                        <p style='margin: 0;'><strong>Peranan:</strong> {$user_role}</p>
                    </div>
                    <p>Sila log masuk ke portal dan tukar kata laluan anda dengan kadar segera.</p>
                    <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #777;'>Pusat Aktiviti Warga Emas (PAWE) Pekan, Pahang</p>
                </div>
            ";
            $mail->AltBody = "ID Pengguna: {$staff_id}\nKata Laluan Sementara: {$temp_password}\nPeranan: {$user_role}";

            $mail->send();$mail_sent = true;
        } catch (Exception $e) {
            error_log("Gagal menghantar emel: " . ($mail->ErrorInfo ?: $e->getMessage()));
        }

        unset($_SESSION['add_staff_old'], $_SESSION['add_staff_error']);
        
        if ($mail_sent) {$_SESSION['admin_msg'] = "Staf baru ($staff_id -$user_name) berjaya didaftarkan. Emel log masuk telah dihantar ke $user_email.";
        } else {
            $_SESSION['admin_msg'] = "Staf berjaya didaftarkan, tetapi emel gagal dihantar. Sila semak konfigurasi SMTP atau hubungi pentadbir sistem.";
        }
        
        $conn->close();
        header("Location: admin_view_staff.php");
        exit();

    } catch (\Throwable $e) {
        $conn->rollback();$_SESSION['add_staff_error'] = "Ralat pangkalan data semasa pendaftaran: " . $e->getMessage();$conn->close();
        header("Location: admin_add_staff.php");
        exit();
    }
}

$conn->close();
header("Location: admin_add_staff.php");
exit();