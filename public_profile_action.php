<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Pengguna Awam') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$conn = new mysqli("localhost", "root", "", "mypawe");
if ($conn->connect_error) {
    die("Sambungan pangkalan data gagal: " . $conn->connect_error);
}

$upload_dir = __DIR__ . '/uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gender      = trim($_POST['public_gender'] ?? '');
    $education   = trim($_POST['public_education'] ?? '');
    $bangsa      = trim($_POST['public_bangsa'] ?? '');
    $religion    = trim($_POST['public_religion'] ?? '');
    $citizenship = trim($_POST['public_citizenship'] ?? '');
    $phone       = trim($_POST['public_phone'] ?? '');
    $address     = trim($_POST['public_address'] ?? '');
    $birthday    = trim($_POST['public_birthday'] ?? '');
    $vol_interest= trim($_POST['volunteer_interest'] ?? 'TIDAK');

    // Handle new photo upload if provided
    $picture_path = null;
    if (!empty($_FILES['public_picture']['name'])) {
        $ext = pathinfo($_FILES['public_picture']['name'], PATHINFO_EXTENSION);
        $picture_name = 'pic_' . $user_id . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['public_picture']['tmp_name'], $upload_dir . $picture_name)) {
            $picture_path = 'uploads/' . $picture_name;
        }
    }

    // 1. Update public table[cite: 2]
    $sql_pub = "UPDATE public SET 
        public_gender = ?, public_citizenship = ?, public_bangsa = ?, 
        public_religion = ?, public_education = ?, public_phone = ?, 
        public_address = ?, public_birthday = ?";
    $params = [$gender, $citizenship, $bangsa, $religion, $education, $phone, $address, $birthday];
    $types  = "ssssssss";

    if ($picture_path) {
        $sql_pub .= ", public_picture = ?";
        $params[] = $picture_path;
        $types   .= "s";
    }

    $sql_pub .= " WHERE public_id = ?";
    $params[] = $user_id;
    $types   .= "s";

    $stmt_pub = $conn->prepare($sql_pub);
    $stmt_pub->bind_param($types, ...$params);
    $stmt_pub->execute();
    $stmt_pub->close();

    // 2. Upsert volunteer interest toggle[cite: 2]
    $vol_status = ($vol_interest === 'YA') ? 'Daftar Minat' : 'Tidak';
    $stmt_vol = $conn->prepare("INSERT INTO volunteer (public_id, volunteer_interest, volunteer_field, volunteer_status)
        VALUES (?, ?, '', ?)
        ON DUPLICATE KEY UPDATE 
        volunteer_interest = VALUES(volunteer_interest),
        volunteer_status = VALUES(volunteer_status)");
    $stmt_vol->bind_param("sss", $user_id, $vol_interest, $vol_status);
    $stmt_vol->execute();
    $stmt_vol->close();

    $_SESSION['profile_msg'] = "Maklumat profil anda telah berjaya dikemaskini.";
    $conn->close();

    header("Location: public_profile.php");
    exit();
}

$conn->close();
header("Location: public_profile.php");
exit();