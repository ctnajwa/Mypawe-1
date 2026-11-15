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

// 1. Handle AJAX completion endpoint from Game 2
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action_type'] ?? '') === 'complete_health_game') {
    if (($_SESSION['member_health_game_user_id'] ?? '') !== $user_id
        || ($_SESSION['member_health_memory_started_for'] ?? '') !== $user_id
        || !isset($_SESSION['memory_game_time'])
        || !isset($_POST['color_time'])
        || !ctype_digit((string)$_POST['color_time'])) {
        http_response_code(400);
        echo "Permainan saringan belum selesai atau sesi tidak sah.";
        $conn->close();
        exit();
    }

    $color_time = (int)$_POST['color_time'];
    $status = 'DRAF';
    $today = date('Y-m-d');
    $memory_time = (int)$_SESSION['memory_game_time'];

    // Save game times immediately to member table
    $stmt_mem = $conn->prepare("INSERT INTO member (public_id, member_status, member_reg_date, memory_game_time, color_game_time, member_approved_by)
        VALUES (?, ?, ?, ?, ?, NULL)
        ON DUPLICATE KEY UPDATE
        memory_game_time = VALUES(memory_game_time),
        color_game_time = VALUES(color_game_time)");
    $stmt_mem->bind_param("sssii", $user_id, $status, $today, $memory_time, $color_time);
    $stmt_mem->execute();
    $stmt_mem->close();

    $_SESSION['color_game_time'] = $color_time;
    $_SESSION['member_health_game_completed_for'] = $user_id;

    $conn->close();
    echo "Success";
    exit();
}

// Helper directory creation
$upload_dir = __DIR__ . '/uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_type  = $_POST['action_type'] ?? 'save_draft';
    $current_step = (int)($_POST['current_step'] ?? 1);

    // Check if user is trying to final submit without playing games (unless already completed previously)
    $already_completed = ($_SESSION['member_health_game_completed_for'] ?? '') === $user_id;
    
    if ($action_type === 'final_submit' && !$already_completed && (!isset($_SESSION['memory_game_time'], $_SESSION['color_game_time']))) {
        $_SESSION['member_error'] = "Sila lengkapkan kedua-dua permainan saringan sebelum menghantar permohonan.";
        $conn->close();
        header("Location: register_member.php?step=5");
        exit();
    }

    if ($action_type === 'start_health_game') {
        // Only clear game sessions if they haven't completed it yet (One-Time-Only enforcement)
        if (!$already_completed) {
            unset(
                $_SESSION['memory_game_time'],
                $_SESSION['color_game_time'],
                $_SESSION['member_health_game_completed_for'],
                $_SESSION['member_health_memory_started_for']
            );
        }
        unset($_SESSION['member_health_game_user_id']);
        unset($_SESSION['member_msg']);
    }

    // 1. File Uploads (Public Picture & IC Proof)
    $picture_path = null;
    $ic_proof_path = null;

    if (!empty($_FILES['public_picture']['name'])) {
        $ext = pathinfo($_FILES['public_picture']['name'], PATHINFO_EXTENSION);
        $picture_name = 'pic_' . $user_id . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['public_picture']['tmp_name'], $upload_dir . $picture_name)) {
            $picture_path = 'uploads/' . $picture_name;
        }
    }

    if (!empty($_FILES['public_ic_proof']['name'])) {
        $ext = pathinfo($_FILES['public_ic_proof']['name'], PATHINFO_EXTENSION);
        $proof_name = 'ic_' . $user_id . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['public_ic_proof']['tmp_name'], $upload_dir . $proof_name)) {
            $ic_proof_path = 'uploads/' . $proof_name;
        }
    }

    // 2. Step 1: Update `public`
    $gender     = trim($_POST['public_gender'] ?? '');
    $education  = trim($_POST['public_education'] ?? '');
    $bangsa     = trim($_POST['public_bangsa'] ?? '');
    $religion   = trim($_POST['public_religion'] ?? '');
    $citizenship = trim($_POST['public_citizenship'] ?? '');
    $phone      = trim($_POST['public_phone'] ?? '');
    $address    = trim($_POST['public_address'] ?? '');
    $birthday   = trim($_POST['public_birthday'] ?? '');

    $sql_pub = "UPDATE public SET 
        public_gender = ?, public_education = ?, public_citizenship = ?, public_bangsa = ?, 
        public_religion = ?, public_phone = ?, public_address = ?";
    $params = [$gender, $education, $citizenship, $bangsa, $religion, $phone, $address];
    $types  = "sssssss";

    if ($picture_path) {
        $sql_pub .= ", public_picture = ?";
        $params[] = $picture_path;
        $types   .= "s";
    }
    if ($ic_proof_path) {
        $sql_pub .= ", public_ic_proof = ?";
        $params[] = $ic_proof_path;
        $types   .= "s";
    }

    $sql_pub .= " WHERE public_id = ?";
    $params[] = $user_id;
    $types   .= "s";

    $stmt = $conn->prepare($sql_pub);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();

    // 3. Step 2: Upsert into `health`
    $health_status = trim($_POST['health_status'] ?? '');
    $health_info   = trim($_POST['health_info'] ?? '');
    $oku_status    = trim($_POST['oku_status'] ?? 'Tidak');
    $oku_number    = (strtoupper($oku_status) === 'YA') ? trim($_POST['oku_number'] ?? '') : '';

    $stmt_hlth = $conn->prepare("INSERT INTO health (public_id, health_status, health_info, oku_status, oku_number)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        health_status = VALUES(health_status), health_info = VALUES(health_info), 
        oku_status = VALUES(oku_status), oku_number = VALUES(oku_number)");
    $stmt_hlth->bind_param("sssss", $user_id, $health_status, $health_info, $oku_status, $oku_number);
    $stmt_hlth->execute();
    $stmt_hlth->close();

    // 4. Step 3: Upsert into `work`
    $work_status     = trim($_POST['work_status'] ?? '');
    $work_jawatan    = trim($_POST['work_jawatan'] ?? '');
    $work_majikan    = trim($_POST['work_majikan'] ?? '');
    $work_experience = trim($_POST['work_experience'] ?? '');
    $work_expertise  = trim($_POST['work_expertise'] ?? '');

    $stmt_wrk = $conn->prepare("INSERT INTO work (public_id, work_status, work_jawatan, work_majikan, work_experience, work_expertise)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        work_status = VALUES(work_status), work_jawatan = VALUES(work_jawatan), 
        work_majikan = VALUES(work_majikan), work_experience = VALUES(work_experience), 
        work_expertise = VALUES(work_expertise)");
    $stmt_wrk->bind_param("ssssss", $user_id, $work_status, $work_jawatan, $work_majikan, $work_experience, $work_expertise);
    $stmt_wrk->execute();
    $stmt_wrk->close();

    // 5. Step 4: Upsert into `volunteer`
    $vol_interest = strtoupper(trim($_POST['volunteer_interest'] ?? ''));
    $v_fields     = $_POST['v_fields'] ?? [];
    $v_fields     = array_map('strtoupper', $v_fields);
    $other_text   = in_array('LAIN-LAIN', $v_fields, true) ? trim($_POST['v_field_other'] ?? '') : '';
    $v_fields     = array_diff($v_fields, ['LAIN-LAIN']);
    $vol_fields_str = implode(', ', $v_fields);
    $vol_status     = ($vol_interest === 'YA') ? 'Daftar Minat' : 'Tidak';

    $stmt_vol = $conn->prepare("INSERT INTO volunteer (public_id, volunteer_interest, volunteer_field, volunteer_lain_info, volunteer_status)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        volunteer_interest = VALUES(volunteer_interest), volunteer_field = VALUES(volunteer_field), 
        volunteer_lain_info = VALUES(volunteer_lain_info),
        volunteer_status = VALUES(volunteer_status)");
    $stmt_vol->bind_param("sssss", $user_id, $vol_interest, $vol_fields_str, $other_text, $vol_status);
    $stmt_vol->execute();
    $stmt_vol->close();

    // 6. Step 5: Upsert into `family`
    $wed_status       = strtoupper(trim($_POST['public_wed_status'] ?? ''));
    $partner_name     = ($wed_status === 'SUDAH BERKAHWIN') ? trim($_POST['public_partner_name'] ?? '') : '';
    $children         = in_array($wed_status, ['SUDAH BERKAHWIN', 'BERCERAI'], true) ? (int)($_POST['public_children'] ?? 0) : 0;
    $housing          = trim($_POST['public_housing'] ?? '');
    $penjaga_name     = trim($_POST['public_penjaga_name'] ?? '');
    $penjaga_relation = trim($_POST['public_penjaga_relation'] ?? '');
    $penjaga_phone    = trim($_POST['public_penjaga_phone'] ?? '');
    $penjaga_address  = trim($_POST['public_penjaga_address'] ?? '');

    $stmt_fam = $conn->prepare("INSERT INTO family (public_id, public_wed_status, public_partner_name, public_children, public_housing, public_penjaga_name, public_penjaga_relation, public_penjaga_phone, public_penjaga_address)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        public_wed_status = VALUES(public_wed_status), public_partner_name = VALUES(public_partner_name), 
        public_children = VALUES(public_children), public_housing = VALUES(public_housing), 
        public_penjaga_name = VALUES(public_penjaga_name), public_penjaga_relation = VALUES(public_penjaga_relation), 
        public_penjaga_phone = VALUES(public_penjaga_phone), public_penjaga_address = VALUES(public_penjaga_address)");
    $stmt_fam->bind_param("sssisssss", $user_id, $wed_status, $partner_name, $children, $housing, $penjaga_name, $penjaga_relation, $penjaga_phone, $penjaga_address);
    $stmt_fam->execute();
    $stmt_fam->close();

    // 7. Step 6: Update or Insert into `member` table (Including Game Times)
    $today = date('Y-m-d');

    $memory_time = isset($_SESSION['memory_game_time']) ? intval($_SESSION['memory_game_time']) : null;
    $color_time  = isset($_SESSION['color_game_time']) ? intval($_SESSION['color_game_time']) : null;

    if ($action_type === 'final_submit') {
        $status = 'SEDANG DIPROSES';
        $_SESSION['member_msg'] = "Permohonan keahlian anda telah dihantar dan sedang diproses.";
    } elseif ($action_type !== 'start_health_game') {
        $status = 'DRAF';
        $_SESSION['member_msg'] = "Maklumat draf anda bagi Langkah $current_step telah berjaya disimpan.";
    } else {
        $status = 'DRAF';
    }

    $stmt_mem = $conn->prepare("INSERT INTO member (public_id, member_status, member_reg_date, memory_game_time, color_game_time, member_approved_by)
        VALUES (?, ?, ?, ?, ?, NULL)
        ON DUPLICATE KEY UPDATE 
        member_status = VALUES(member_status), 
        member_reg_date = VALUES(member_reg_date),
        memory_game_time = COALESCE(VALUES(memory_game_time), memory_game_time),
        color_game_time = COALESCE(VALUES(color_game_time), color_game_time)");
    $stmt_mem->bind_param("sssii", $user_id, $status, $today, $memory_time, $color_time);
    $stmt_mem->execute();
    $stmt_mem->close();

    // Clear sessions after final submit
    if ($action_type === 'final_submit') {
        unset(
            $_SESSION['memory_game_time'],
            $_SESSION['color_game_time'],
            $_SESSION['member_health_game_user_id'],
            $_SESSION['member_health_game_completed_for'],
            $_SESSION['member_health_memory_started_for']
        );
    }

    $conn->close();
    if ($action_type === 'final_submit') {
        header("Location: member_status.php");
    } elseif ($action_type === 'start_health_game') {
        // ONE-TIME CHECK: If already completed, skip games and go directly to next step
        if ($already_completed) {
            header("Location: register_member.php?step=6");
        } else {
            $_SESSION['member_health_game_user_id'] = $user_id;
            header("Location: game_notice.php");
        }
    } else {
        header("Location: register_member.php?step=" . $current_step);
    }
    exit();
}

$conn->close();
header("Location: register_member.php");
exit();