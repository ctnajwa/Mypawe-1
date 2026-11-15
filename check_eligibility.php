<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ic = trim($_POST['user_ic'] ?? '');
    $clean_ic = preg_replace('/[^0-9]/', '', $ic);

    if (strlen($clean_ic) >= 6) {
        $yy = intval(substr($clean_ic, 0, 2));
        $current_year_2dig = intval(date('y'));
        $birth_year = ($yy <= $current_year_2dig) ? (2000 + $yy) : (1900 + $yy);
        
        $current_year = intval(date('Y'));
        $age = $current_year - $birth_year;

        if ($age >= 60) {
            $_SESSION['eligibility_msg'] = 'Anda layak menjadi ahli PAWE Pekan. <a href="register_member.php" style="color: #2563eb; font-weight: bold; text-decoration: underline;">DAFTAR SEKARANG!</a>';
            $_SESSION['eligibility_type'] = 'success';
        } else {
            $_SESSION['eligibility_msg'] = 'Maaf, anda belum cukup umur untuk mendaftar.';
            $_SESSION['eligibility_type'] = 'error';
        }
    } else {
        $_SESSION['eligibility_msg'] = 'Sila masukkan nombor Kad Pengenalan yang sah (12 digit).';
        $_SESSION['eligibility_type'] = 'error';
    }
}

header("Location: main_portal.php#semak-kelayakan");
exit();