<?php
session_start();

// Redirect user if they are already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    if ($_SESSION['user_role'] === 'Admin') {
        header("Location: Admin/admin_dashboard.php");
    } elseif ($_SESSION['user_role'] === 'Staf') {
        header("Location: Staff/staff_dashboard.php");
    } else {
        header("Location: main_portal.php");
    }
    exit();
}

$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

$success = $_SESSION['login_success'] ?? '';
unset($_SESSION['login_success']);

$saved_username = $_SESSION['saved_username'] ?? '';
unset($_SESSION['saved_username']);

$redirect = $_GET['redirect'] ?? ($_SESSION['redirect_url'] ?? '');
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Masuk - MyPAWE Pekan</title>
    <link rel="stylesheet" href="Style/style_login.css?v=2">
</head>
<body>

    <div class="login-container">
        
        <!-- Left Sidebar: Minimal First-time Registration Prompt -->
        <aside class="login-sidebar">
            <div class="sidebar-register-box">
                <h2>Pengguna Kali Pertama?</h2>
                <a href="registration.php" class="btn-sidebar-register">Daftar Sekarang</a>
            </div>
        </aside>

        <!-- Right Side: Login Form -->
        <main class="login-content">
            <a href="main_portal.php" class="back-portal-link">Kembali ke Portal</a>
            
            <div class="login-form-wrapper">
                
                <div class="logo-placeholder">
                    <img src="Images/logo_pawe_pekan.jpg" alt="Logo PAWE Pekan" />
                </div>

                <div class="welcome-title">Selamat Datang !</div>
                <div class="sub-title">Log masuk ke akaun anda</div>

                <?php if (!empty($success)): ?>
                    <div style="background-color: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 15px; border: 1px solid #c3e6cb;">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php elseif (!empty($error)): ?>
                    <div style="background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 15px; border: 1px solid #f5c6cb;">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form action="login_action.php" method="POST">
                    
                    <!-- Hidden Redirect Destination -->
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">

                    <div class="input-group">
                        <label for="username">ID Pengguna</label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="login-input" 
                            value="<?php echo htmlspecialchars($saved_username); ?>"
                            placeholder="Contoh: S001 atau user123"
                            required
                        >
                    </div>

                    <div class="input-group">
                        <label for="password">Kata Laluan</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="login-input" 
                            placeholder="Masukkan kata laluan"
                            required
                        >
                    </div>

                    <div class="input-group">
                        <label for="role">Peranan</label>
                        <select id="role" name="role" class="login-select" required>
                            <option value="Pengguna Awam">Pengguna Awam</option>
                            <option value="Staf">Staf</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-log-masuk">Log Masuk</button>

                    <a href="forgot_password.php" class="forgot-password-link">Lupa kata kunci?</a>
                </form>

            </div>
        </main>

    </div>

</body>
</html>