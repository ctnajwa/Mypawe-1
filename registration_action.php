<?php
session_start();

$conn = new mysqli("localhost", "root", "", "mypawe");

if ($conn->connect_error) {
    die("Sambungan gagal: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id   = trim($_POST['user_id'] ?? '');
    $user_email= trim($_POST['user_email'] ?? '');
    $user_name = trim($_POST['user_name'] ?? '');
    $user_ic   = trim($_POST['user_ic'] ?? '');
    $password  =$_POST['password'] ?? '';
    $confirm   =$_POST['confirm_password'] ?? '';

    // Cache input in session in case of validation failure
    $_SESSION['old_input'] = [
        'user_id'    => $user_id,
        'user_email' => $user_email,
        'user_name'  => $user_name,
        'user_ic'    => $user_ic
    ];

    // 1. Basic empty check
    if (empty($user_id) || empty($user_email) || empty($user_name) || empty($user_ic) || empty($password)) {$_SESSION['reg_error'] = "Sila lengkapkan semua ruangan maklumat.";
        header("Location: registration.php");
        exit();
    }

    // 2. Passwords match check
    if ($password !== $confirm) {$_SESSION['reg_error'] = "Kata laluan dan Sahkan Kata Laluan tidak sepadan.";
        header("Location: registration.php");
        exit();
    }

    // 3. Clean and validate IC (digits only)
    $clean_ic = preg_replace('/[^0-9]/', '',$user_ic);
    if (strlen($clean_ic) !== 12) {$_SESSION['reg_error'] = "Nombor Kad Pengenalan mesti mengandungi 12 digit tanpa tanda sempang (-).";
        header("Location: registration.php");
        exit();
    }

    // 4. Force full name to uppercase
    $user_name = mb_strtoupper($user_name, 'UTF-8');

    // 5. Verify uniqueness of user_id and user_email
    $check_stmt =$conn->prepare("SELECT user_id, user_email FROM user WHERE user_id = ? OR user_email = ? LIMIT 1");
    $check_stmt->bind_param("ss", $user_id, $user_email);$check_stmt->execute();
    $check_res =$check_stmt->get_result();

    if ($check_res->num_rows > 0) {
        $existing =$check_res->fetch_assoc();
        if ($existing['user_id'] === $user_id) {$_SESSION['reg_error'] = "ID Pengguna '$user_id' telah digunakan. Sila pilih ID lain.";
        } else {
            $_SESSION['reg_error'] = "Emel '$user_email' telah berdaftar dalam sistem.";
        }
        $check_stmt->close();
        header("Location: registration.php");
        exit();
    }
    $check_stmt->close();

    // 6. Hash the password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);$role = "Pengguna Awam"; // Strict assignment

    // 7. Insert using a Database Transaction
    $conn->begin_transaction();

    try {
        // Insert into `user` table
        $insert_user =$conn->prepare("INSERT INTO user (user_id, user_email, user_name, user_password, user_ic, user_role) VALUES (?, ?, ?, ?, ?, ?)");
        $insert_user->bind_param("ssssss", $user_id,$user_email, $user_name,$hashed_password, $clean_ic,$role);
        $insert_user->execute();$insert_user->close();

        // Initialize corresponding row in `public` table (to satisfy 1:1 foreign key structure)
        $empty_val = "";
        $insert_public =$conn->prepare("INSERT INTO public (public_id, public_gender, public_citizenship, public_bangsa, public_religion, public_phone, public_address, public_education, public_ic_proof, public_birthday) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert_public->bind_param("ssssssssss", $user_id, $empty_val, $empty_val, $empty_val, $empty_val, $empty_val, $empty_val, $empty_val, $empty_val, $empty_val);
        $insert_public->execute();$insert_public->close();

        $conn->commit();

        // Clear cached input
        unset($_SESSION['old_input']);

        // Redirect back to login with success state
        $_SESSION['saved_username'] =$user_id;
        $_SESSION['login_success'] = "Pendaftaran akaun berjaya! Sila log masuk.";
        header("Location: login.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();$_SESSION['reg_error'] = "Ralat semasa menyimpan pendaftaran: " . $e->getMessage();
        header("Location: registration.php");
        exit();
    }
}

$conn->close();
header("Location: registration.php");
exit();