<?php
session_start();

// Database connection configuration
$host = "localhost";
$user = "root";
$pass = "";
$db   = "mypawe";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Sambungan ke pangkalan data gagal: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = trim($_POST['role'] ?? '');

    if (empty($username) || empty($password) || empty($role)) {
        $_SESSION['login_error']    = "Sila lengkapkan semua maklumat.";
        $_SESSION['saved_username'] = $username;
        header("Location: login.php");
        exit();
    }

    // Query user matching user_id and user_role
    $stmt = $conn->prepare("SELECT user_id, user_name, user_email, user_password, user_role, user_ic FROM user WHERE user_id = ? AND user_role = ? LIMIT 1");
    $stmt->bind_param("ss", $username, $role);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $userData = $result->fetch_assoc();

        // Check password hash or raw string (fallback for unhashed dev data)
        if (password_verify($password, $userData['user_password']) || $password === $userData['user_password']) {
            
            // Set session variables
            $_SESSION['user_id']   = $userData['user_id'];
            $_SESSION['user_name'] = $userData['user_name'];
            $_SESSION['user_role'] = $userData['user_role'];
            $_SESSION['user_ic']   = $userData['user_ic'];

            // Newly created staff or admins must change the temporary password first.
            if ($userData['user_role'] === 'Staf' || $userData['user_role'] === 'Admin') {
                $st_stmt = $conn->prepare("SELECT staff_change_password FROM staff WHERE staff_id = ? LIMIT 1");
                $st_stmt->bind_param("s", $userData['user_id']);
                $st_stmt->execute();
                $st_res = $st_stmt->get_result()->fetch_assoc();
                $st_stmt->close();

                if ($st_res && (int)$st_res['staff_change_password'] === 1) {
                    $_SESSION['must_change_password'] = true;
                    header("Location: Staff/staff_change_password.php");
                    exit();
                }
            }

            $redirect = trim($_POST['redirect'] ?? '');

            // Whitelist permitted redirect files to avoid open-redirect vulnerabilities
            $allowedRedirects = ['register_member.php', 'register_volunteer.php'];

            if (!empty($redirect) && in_array($redirect, $allowedRedirects, true) && $userData['user_role'] === 'Pengguna Awam') {
                header("Location: " . $redirect);
                exit();
            }

            // Route based on role
            switch ($userData['user_role']) {
                case 'Admin':
                    header("Location: Admin/admin_dashboard.php");
                    break;
                case 'Staf':
                    header("Location: Staff/staff_dashboard.php");
                    break;
                case 'Pengguna Awam':
                    header("Location: main_portal.php");
                    break;
                default:
                    header("Location: main_portal.php");
                    break;
            }
            exit();

        } else {
            $_SESSION['login_error']    = "Kata laluan tidak sah.";
            $_SESSION['saved_username'] = $username;
            header("Location: login.php");
            exit();
        }
    } else {
        $_SESSION['login_error']    = "ID Pengguna tidak dijumpai atau peranan tidak sepadan.";
        $_SESSION['saved_username'] = $username;
        header("Location: login.php");
        exit();
    }

    $stmt->close();
}

$conn->close();
?>