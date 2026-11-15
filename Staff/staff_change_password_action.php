<?php
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Staf', 'Admin'], true)) {
    header("Location: ../login.php");
    exit();
}

$user_id =$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pass  =$_POST['new_password'] ?? '';
    $conf_pass =$_POST['confirm_password'] ?? '';

    if (empty($new_pass) || $new_pass !== $conf_pass) {$_SESSION['change_pass_error'] = "Kata laluan baru tidak sepadan. Sila cuba lagi.";
        header("Location: staff_change_password.php");
        exit();
    }

    if (strlen($new_pass) < 6) {$_SESSION['change_pass_error'] = "Kata laluan mestilah sekurang-kurangnya 6 aksara.";
        header("Location: staff_change_password.php");
        exit();
    }

    $conn = new mysqli("localhost", "root", "", "mypawe");
    if ($conn->connect_error) {
        die("Sambungan pangkalan data gagal: " . $conn->connect_error);
    }

    // Hash the new password securely
    $hashed_password = password_hash($new_pass, PASSWORD_BCRYPT);

    $conn->begin_transaction();

    try {
        // 1. Update password in `user` table
        $stmt_user =$conn->prepare("UPDATE user SET user_password = ? WHERE user_id = ?");
        $stmt_user->bind_param("ss", $hashed_password,$user_id);
        $stmt_user->execute();$stmt_user->close();

        // 2. Set staff_change_password flag to 0
        $stmt_staff =$conn->prepare("UPDATE staff SET staff_change_password = 0 WHERE staff_id = ?");
        $stmt_staff->bind_param("s", $user_id);
        $stmt_staff->execute();$stmt_staff->close();

        $conn->commit();$conn->close();

        // Return each role to its own dashboard.
        header("Location: " . ($_SESSION['user_role'] === 'Admin' ? '../Admin/admin_dashboard.php' : 'staff_dashboard.php'));
        exit();

    } catch (Exception $e) {$conn->rollback();
        $conn->close();$_SESSION['change_pass_error'] = "Ralat sistem: " . $e->getMessage();
        header("Location: staff_change_password.php");
        exit();
    }
}

header("Location: staff_change_password.php");
exit();