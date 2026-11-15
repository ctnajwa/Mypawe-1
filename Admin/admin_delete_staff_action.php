<?php
session_start();

// Access Guard: Admin only
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$current_admin_id =$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staff_id = trim($_POST['staff_id'] ?? '');

    if (empty($staff_id)) {$_SESSION['admin_msg'] = "ID Staf tidak sah.";
        header("Location: admin_view_staff.php");
        exit();
    }

    // Prevent self-deletion
    if ($staff_id === $current_admin_id) {$_SESSION['admin_msg'] = "Ralat: Anda tidak boleh memadam akaun pentadbir anda sendiri.";
        header("Location: admin_staff_profile.php?id=" . urlencode($staff_id));
        exit();
    }

    $conn = new mysqli("localhost", "root", "", "mypawe");
    if ($conn->connect_error) {
        die("Sambungan pangkalan data gagal: " . $conn->connect_error);
    }

    // Fetch staff photo file if exists to delete physical file
    $p_stmt =$conn->prepare("SELECT staff_picture FROM staff WHERE staff_id = ? LIMIT 1");
    $p_stmt->bind_param("s", $staff_id);
    $p_stmt->execute();$picData = $p_stmt->get_result()->fetch_assoc();$p_stmt->close();

    if (!empty($picData['staff_picture']) && file_exists(__DIR__ . '/../' .$picData['staff_picture'])) {
        @unlink(__DIR__ . '/../' . $picData['staff_picture']);
    }

    // Deleting from `user` cascades and removes from `staff` table automatically
    $stmt =$conn->prepare("DELETE FROM user WHERE user_id = ? AND (user_role = 'Staf' OR user_role = 'Admin')");
    $stmt->bind_param("s", $staff_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {$_SESSION['admin_msg'] = "Rekod staf ($staff_id) telah berjaya dipadam sepenuhnya dari sistem.";
    } else {
        $_SESSION['admin_msg'] = "Gagal memadam staf atau rekod tidak dijumpai.";
    }

    $stmt->close();$conn->close();

    header("Location: admin_view_staff.php");
    exit();
}

header("Location: admin_view_staff.php");
exit();