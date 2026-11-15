<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Pengguna Awam') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];$conn = new mysqli("localhost", "root", "", "mypawe");
if ($conn->connect_error) {
    die("Sambungan pangkalan data gagal: " . $conn->connect_error);
}

$upload_dir = __DIR__ . '/uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_type  =$_POST['action_type'] ?? 'save_draft';
    $current_step = (int)($_POST['current_step'] ?? 1);

    // 1. File Upload Processing
    $picture_path = null;
    $ic_proof_path = null;

    if (!empty($_FILES['public_picture']['name'])) {$ext = pathinfo($_FILES['public_picture']['name'], PATHINFO_EXTENSION);$picture_name = 'pic_' . $user_id . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['public_picture']['tmp_name'], $upload_dir .$picture_name)) {
            $picture_path = 'uploads/' .$picture_name;
        }
    }

    if (!empty($_FILES['public_ic_proof']['name'])) {$ext = pathinfo($_FILES['public_ic_proof']['name'], PATHINFO_EXTENSION);$proof_name = 'ic_' . $user_id . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['public_ic_proof']['tmp_name'], $upload_dir .$proof_name)) {
            $ic_proof_path = 'uploads/' .$proof_name;
        }
    }

    // 2. Update `public` profile information
    $gender      = trim($_POST['public_gender'] ?? '');
    $education   = trim($_POST['public_education'] ?? '');
    $bangsa      = trim($_POST['public_bangsa'] ?? '');
    $religion    = trim($_POST['public_religion'] ?? '');
    $citizenship = trim($_POST['public_citizenship'] ?? '');
    $phone       = trim($_POST['public_phone'] ?? '');
    $address     = trim($_POST['public_address'] ?? '');

    $sql_pub = "UPDATE public SET 
        public_gender = ?, public_citizenship = ?, public_bangsa = ?, 
        public_religion = ?, public_phone = ?, public_address = ?";
    $params = [$gender, $citizenship,$bangsa, $religion,$phone, $address];$types  = "ssssss";

    if ($picture_path) {$sql_pub .= ", public_picture = ?";
        $params[] =$picture_path;
        $types   .= "s";
    }
    if ($ic_proof_path) {$sql_pub .= ", public_ic_proof = ?";
        $params[] =$ic_proof_path;
        $types   .= "s";
    }

    $sql_pub .= " WHERE public_id = ?";
    $params[] =$user_id;
    $types   .= "s";

    $stmt =$conn->prepare($sql_pub);$stmt->bind_param($types, ...$params);
    $stmt->execute();$stmt->close();

    // 3. Upsert into `volunteer` table
    $vol_interest = trim($_POST['volunteer_interest'] ?? 'YA');
    $v_fields     =$_POST['v_fields'] ?? [];
    if (in_array('LAIN-LAIN', $v_fields)) {
        $other_text = trim($_POST['v_field_other'] ?? '');
        $v_fields = array_diff($v_fields, ['LAIN-LAIN']);
        if (!empty($other_text)) $v_fields[] =$other_text;
    }
    $vol_fields_str = implode(', ',$v_fields);

    if ($action_type === 'final_submit') {
        $vol_status = 'BERDAFTAR';$_SESSION['vol_msg'] = "Pendaftaran sukarelawan anda telah berjaya dihantar!";
    } else {
        $vol_status = 'DRAF';$_SESSION['vol_msg'] = "Draf pendaftaran sukarelawan telah berjaya disimpan.";
    }

    $stmt_vol =$conn->prepare("INSERT INTO volunteer (public_id, volunteer_interest, volunteer_field, volunteer_status)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        volunteer_interest = VALUES(volunteer_interest), volunteer_field = VALUES(volunteer_field), 
        volunteer_status = VALUES(volunteer_status)");
    $stmt_vol->bind_param("ssss", $user_id,$vol_interest, $vol_fields_str,$vol_status);
    $stmt_vol->execute();$stmt_vol->close();

    $conn->close();
    header("Location: register_volunteer.php");
    exit();
}

$conn->close();
header("Location: register_volunteer.php");
exit();