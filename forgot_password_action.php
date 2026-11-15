<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require __DIR__ . '/PHPMailer/Exception.php';
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';

$conn = new mysqli("localhost", "root", "", "mypawe");
if ($conn->connect_error) {
    die("Sambungan pangkalan data gagal: " . $conn->connect_error);
}

$action = $_REQUEST['action'] ?? '';

// Helper function to mask email for UI display (e.g. ahmad@gmail.com -> ah***@gmail.com)
function maskEmail($email) {
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;
    $name = $parts[0];
    $len = strlen($name);
    $masked = ($len > 2) ? substr($name, 0, 2) . str_repeat('*', $len - 2) : str_repeat('*', $len);
    return $masked . '@' . $parts[1];
}

function sendPasswordResetEmail($recipientEmail, $recipientName, $code) {
    $smtp_username = getenv('MYPAWE_SMTP_USERNAME');
    $smtp_password = getenv('MYPAWE_SMTP_PASSWORD');

    if (!$smtp_username || !$smtp_password) {
        throw new Exception('Konfigurasi emel SMTP belum ditetapkan pada server.');
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp_username;
    $mail->Password   = str_replace(' ', '', $smtp_password);
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom($smtp_username, 'Sistem MyPAWE Pekan');
    $mail->addAddress($recipientEmail, $recipientName);
    $mail->isHTML(true);
    $mail->Subject = 'Kod Semula Kata Laluan MyPAWE';
    $mail->Body = "
        <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
            <h3 style='color: #0d1b4c;'>Permohonan Set Semula Kata Laluan</h3>
            <p>Sila masukkan kod pengesahan berikut untuk meneruskan tetapan semula kata laluan anda:</p>
            <div style='background: #f4f6f9; padding: 15px; font-size: 24px; font-weight: bold; letter-spacing: 2px; text-align: center; color: #113f8c; margin: 20px 0;'>
                {$code}
            </div>
            <p style='font-size: 13px; color: #777;'>Jika anda tidak memohon tetapan semula ini, sila abaikan emel ini.</p>
        </div>
    ";
    $mail->AltBody = "Kod pengesahan anda ialah: {$code}";
    $mail->send();
}

// Resend Code Trigger
if ($action === 'resend' && isset($_SESSION['forgot_user_id'])) {
    $user_id = $_SESSION['forgot_user_id'];
    $stmt = $conn->prepare("SELECT user_email, user_name FROM user WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($res) {
        $code = random_int(100000, 999999);
        $_SESSION['forgot_code'] = $code;

        try {
            sendPasswordResetEmail($res['user_email'], $res['user_name'], $code);
            $_SESSION['forgot_error'] = "Kod baharu telah dihantar semula ke emel anda.";
        } catch (Exception $e) {
            error_log("Gagal menghantar emel tetapan semula: " . $e->getMessage());
            $_SESSION['forgot_error'] = "Emel gagal dihantar. Sila cuba lagi kemudian.";
        }
    }
    header("Location: forgot_password.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // STEP 1: Verify Username
    if ($action === 'verify_username') {
        $username = trim($_POST['username'] ?? '');
        $_SESSION['forgot_username'] = $username;

        if (empty($username)) {
            $_SESSION['forgot_error'] = "Sila masukkan ID Pengguna anda.";
            header("Location: forgot_password.php");
            exit();
        }

        $stmt = $conn->prepare("SELECT user_id, user_email, user_name FROM user WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $_SESSION['forgot_error'] = "ID Pengguna tidak dijumpai dalam sistem.";
            header("Location: forgot_password.php");
            exit();
        }

        // Save user context and generate 6-digit verification code
        $_SESSION['forgot_user_id'] = $user['user_id'];
        $_SESSION['forgot_email']   = $user['user_email'];
        $_SESSION['forgot_masked_email'] = maskEmail($user['user_email']);
        
        $code = random_int(100000, 999999);
        $_SESSION['forgot_code'] = $code;

        try {
            sendPasswordResetEmail($user['user_email'], $user['user_name'], $code);
        } catch (Exception $e) {
            error_log("Gagal menghantar emel tetapan semula: " . $e->getMessage());
            $_SESSION['forgot_error'] = "Emel gagal dihantar. Sila cuba lagi kemudian.";
        }

        // Advance to Step 2
        $_SESSION['forgot_step'] = 2;
        header("Location: forgot_password.php");
        exit();
    }

    // STEP 2: Verify Code
    if ($action === 'verify_code') {
        $entered_code = trim($_POST['code'] ?? '');
        $saved_code   = $_SESSION['forgot_code'] ?? '';

        if ($entered_code !== (string)$saved_code) {
            $_SESSION['forgot_error'] = "Kod pengesahan tidak sah. Sila cuba lagi.";
            header("Location: forgot_password.php");
            exit();
        }

        // Advance to Step 3
        $_SESSION['forgot_step'] = 3;
        header("Location: forgot_password.php");
        exit();
    }

    // STEP 3: Update Password
    if ($action === 'update_password') {
        $new_pass = $_POST['new_password'] ?? '';
        $conf_pass = $_POST['confirm_password'] ?? '';
        $user_id  = $_SESSION['forgot_user_id'] ?? '';

        if (empty($new_pass) || $new_pass !== $conf_pass) {
            $_SESSION['forgot_error'] = "Kata laluan tidak sepadan.";
            $_SESSION['forgot_step'] = 3;
            header("Location: forgot_password.php");
            exit();
        }

        // Securely hash new password
        $hashed = password_hash($new_pass, PASSWORD_BCRYPT);

        $stmt = $conn->prepare("UPDATE user SET user_password = ? WHERE user_id = ?");
        $stmt->bind_param("ss", $hashed, $user_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE staff SET staff_change_password = 0 WHERE staff_id = ?");
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $stmt->close();

        // Clear temporary recovery session tokens and advance to Step 4 (Success)
        unset($_SESSION['forgot_code']);
        unset($_SESSION['forgot_user_id']);
        unset($_SESSION['forgot_email']);
        unset($_SESSION['forgot_masked_email']);
        unset($_SESSION['forgot_username']);

        $_SESSION['forgot_step'] = 4;
        header("Location: forgot_password.php");
        exit();
    }
}

$conn->close();
header("Location: forgot_password.php");
exit();