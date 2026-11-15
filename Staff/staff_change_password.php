<?php
session_start();

// Access Guard: Staff and admins may be required to change their password.
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Staf', 'Admin'], true)) {
    header("Location: ../login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Pengguna';

$conn = new mysqli("localhost", "root", "", "mypawe");
if ($conn->connect_error) {
    die("Sambungan pangkalan data gagal: " . $conn->connect_error);
}

// Verify flag directly from database to prevent session tampering
$stmt = $conn->prepare("SELECT staff_change_password FROM staff WHERE staff_id = ? LIMIT 1");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$data || (int)$data['staff_change_password'] !== 1) {
    header("Location: " . ($_SESSION['user_role'] === 'Admin' ? '../Admin/admin_dashboard.php' : 'staff_dashboard.php'));
    exit();
}

$error = $_SESSION['change_pass_error'] ?? '';
unset($_SESSION['change_pass_error']);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tukar Kata Laluan - MyPAWE</title>
    <style>
        :root {
            --bg-navy: #0d4669;
            --btn-blue: #113f8c;
            --btn-blue-hover: #0d2f6a;
            --text-dark: #1a202c;
            --border-gray: #cbd5e0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--bg-navy);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 15px;
        }

        .change-pass-card {
            background: #ffffff;
            width: 100%;
            max-width: 480px;
            border-radius: 12px;
            padding: 40px 45px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            text-align: center;
        }

        .card-logo {
            width: 70px;
            height: auto;
            margin-bottom: 15px;
        }

        .card-title {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .card-subtitle {
            font-size: 14px;
            color: #4a5568;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .form-group {
            text-align: left;
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            font-size: 15px;
            border: 1px solid var(--border-gray);
            border-radius: 6px;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--btn-blue);
            box-shadow: 0 0 0 3px rgba(17, 63, 140, 0.15);
        }

        .alert-error {
            background-color: #fed7d7;
            color: #9b2c2c;
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 20px;
            border: 1px solid #feb2b2;
            text-align: left;
        }

        .btn-submit {
            display: block;
            width: 100%;
            background-color: var(--btn-blue);
            color: #ffffff;
            font-size: 16px;
            font-weight: 700;
            border: none;
            border-radius: 8px;
            padding: 14px;
            cursor: pointer;
            transition: background-color 0.2s ease;
            margin-top: 25px;
        }

        .btn-submit:hover {
            background-color: var(--btn-blue-hover);
        }
    </style>
</head>
<body>

    <div class="change-pass-card">
        <img src="../Images/logo_pawe_pekan.jpg" alt="Logo PAWE Pekan" class="card-logo" onerror="this.style.display='none'">
        
        <h2 class="card-title">Selamat Datang, <?php echo htmlspecialchars($user_name); ?>!</h2>
        <p class="card-subtitle">Demi keselamatan akaun anda, sila tukar kata laluan sementara anda kepada kata laluan baharu sebelum meneruskan.</p>

        <?php if (!empty($error)): ?>
            <div class="alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="staff_change_password_action.php" method="POST" onsubmit="return validatePasswords();">
            
            <div class="form-group">
                <label for="new_password">Kata Laluan Baharu</label>
                <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Masukkan kata laluan baharu" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Sahkan Kata Laluan Baharu</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Ulang semula kata laluan" required>
                <span id="pass-err" style="color: #e53e3e; font-size: 12px; display: none; margin-top: 4px;">Kata laluan tidak sepadan.</span>
            </div>

            <button type="submit" class="btn-submit">Simpan & Teruskan</button>
        </form>
    </div>

    <script>
        function validatePasswords() {
            const p1 = document.getElementById('new_password').value;
            const p2 = document.getElementById('confirm_password').value;
            const err = document.getElementById('pass-err');

            if (p1 !== p2) {
                err.style.display = 'block';
                return false;
            }
            err.style.display = 'none';
            return true;
        }
    </script>
</body>
</html>