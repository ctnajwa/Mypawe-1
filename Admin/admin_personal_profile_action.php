<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staff_birthday   = trim($_POST['staff_birthday'] ?? NULL);
    $staff_gender     = trim($_POST['staff_gender'] ?? '');
    $staff_kaum       = trim($_POST['staff_kaum'] ?? '');
    $staff_religion   = trim($_POST['staff_religion'] ?? '');
    $staff_wed_status = trim($_POST['staff_wed_status'] ?? '');
    $staff_phone      = trim($_POST['staff_phone'] ?? '');
    $staff_address    = trim($_POST['staff_address'] ?? '');
    $staff_education  = trim($_POST['staff_education'] ?? '');
    $staff_edu_info   = trim($_POST['staff_edu_info'] ?? '');
    $staff_bank_used  = trim($_POST['staff_bank_used'] ?? '');
    $staff_no_bank    = trim($_POST['staff_no_bank'] ?? '');

    $conn = new mysqli("localhost", "root", "", "mypawe");
    if ($conn->connect_error) {
        die("Sambungan pangkalan data gagal: " . $conn->connect_error);
    }

    $upload_dir = __DIR__ . '/../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $picture_path = null;
    if (!empty($_FILES['staff_picture']['name'])) {
        $ext = pathinfo($_FILES['staff_picture']['name'], PATHINFO_EXTENSION);
        $picture_name = 'staff_' . $admin_id . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['staff_picture']['tmp_name'], $upload_dir . $picture_name)) {
            $picture_path = 'uploads/' . $picture_name;
        }
    }

    // Build dynamic update query for staff table
    $sql = "UPDATE staff SET 
                staff_birthday = ?, staff_gender = ?, staff_kaum = ?, 
                staff_religion = ?, staff_wed_status = ?, staff_phone = ?, 
                staff_address = ?, staff_education = ?, staff_edu_info = ?, 
                staff_bank_used = ?, staff_no_bank = ?";
    
    $params = [
        $staff_birthday ?: NULL, $staff_gender, $staff_kaum, 
        $staff_religion, $staff_wed_status, $staff_phone, 
        $staff_address, $staff_education, $staff_edu_info, 
        $staff_bank_used, $staff_no_bank
    ];
    $types = "sssssssssss";

    if ($picture_path) {
        $sql .= ", staff_picture = ?";
        $params[] = $picture_path;
        $types   .= "s";
    }

    $sql .= " WHERE staff_id = ?";
    $params[] = $admin_id;
    $types   .= "s";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $_SESSION['profile_msg'] = "Profil peribadi anda telah berjaya dikemaskini.";
    } else {
        $_SESSION['profile_error'] = "Gagal mengemaskini profil: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();

    header("Location: admin_personal_profile.php");
    exit();
}

header("Location: admin_personal_profile.php");
exit();