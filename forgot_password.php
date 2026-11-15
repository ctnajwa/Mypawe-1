<?php
session_start();

$step  = $_SESSION['forgot_step'] ?? 1;
$error = $_SESSION['forgot_error'] ?? '';
unset($_SESSION['forgot_error']);

$masked_email = $_SESSION['forgot_masked_email'] ?? '';
$username_val = $_SESSION['forgot_username'] ?? '';
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Semula Kata Laluan - MyPAWE</title>
    <link rel="stylesheet" href="Style/style_forgot.css">
</head>
<body>

    <div class="forgot-card">
        <!-- Brand Logo -->
        <img src="Images/logo_pawe_pekan.jpg" alt="Logo PAWE Pekan" class="forgot-logo" onerror="this.style.display='none'">

        <?php if (!empty($error)): ?>
            <div class="alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- STEP 1: Enter Username -->
        <?php if ($step === 1): ?>
            <h2 class="forgot-title">Lupa kata laluan?</h2>
            <p class="forgot-subtitle">Sila masukkan ID Pengguna (Username) akaun anda</p>

            <form action="forgot_password_action.php" method="POST" class="forgot-form">
                <input type="hidden" name="action" value="verify_username">
                
                <div class="form-group">
                    <label for="username">ID Pengguna</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-control" 
                        value="<?php echo htmlspecialchars($username_val); ?>" 
                        placeholder="Contoh: S0001 atau ahmad" 
                        required
                    >
                </div>

                <button type="submit" class="btn-primary-action">Set semula kata laluan</button>
            </form>

        <!-- STEP 2: Enter Verification Code -->
        <?php elseif ($step === 2): ?>
            <h2 class="forgot-title">Set semula kata laluan</h2>
            <p class="forgot-subtitle">Kami telah hantar kod kepada <strong><?php echo htmlspecialchars($masked_email); ?></strong></p>

            <form action="forgot_password_action.php" method="POST" class="forgot-form">
                <input type="hidden" name="action" value="verify_code">
                
                <div class="form-group">
                    <label for="code">Kod Pengesahan</label>
                    <input 
                        type="text" 
                        id="code" 
                        name="code" 
                        class="form-control" 
                        placeholder="Masukkan kod di sini" 
                        maxlength="6"
                        required
                    >
                </div>

                <div style="text-align: right; margin-top: -12px; margin-bottom: 20px;">
                    <a href="forgot_password_action.php?action=resend" class="helper-link">Tekan sini</a> <span style="font-size: 13px; color: #718096;">jika tidak dapat emel</span>
                </div>

                <button type="submit" class="btn-primary-action">Seterusnya</button>
            </form>

        <!-- STEP 3: Set New Password -->
        <?php elseif ($step === 3): ?>
            <h2 class="forgot-title">Set kata laluan baharu</h2>
            <p class="forgot-subtitle">Sila cipta kata laluan baharu yang selamat untuk akaun anda.</p>

            <form action="forgot_password_action.php" method="POST" class="forgot-form" onsubmit="return validateNewPasswords();">
                <input type="hidden" name="action" value="update_password">
                
                <div class="form-group">
                    <label for="new_password">Kata Laluan</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" placeholder="*******************" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Mengesahkan kata laluan</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="*******************" required>
                    <span id="pass-match-err" style="color: #e53e3e; font-size: 12px; display: none; margin-top: 4px;">Kata laluan tidak sepadan.</span>
                </div>

                <button type="submit" class="btn-primary-action">Set semula kata laluan</button>
            </form>

        <!-- STEP 4: Success Screen -->
        <?php elseif ($step === 4): ?>
            <h2 class="forgot-title">Set kata laluan baharu</h2>
            <div style="font-size: 32px; font-weight: 800; color: #28a745; margin: 30px 0;">Berjaya!</div>
            <p class="forgot-subtitle">Kata laluan anda telah berjaya dikemaskini. Anda kini boleh log masuk menggunakan kata laluan baharu.</p>
        <?php endif; ?>

        <!-- Back to login link -->
        <a href="login.php" class="back-link-wrap">
            &larr; Kembali ke log masuk
        </a>

        <!-- Progress Indicator Bars matching screenshots -->
        <div class="step-indicators">
            <div class="indicator-bar <?php echo ($step === 1) ? 'active' : ''; ?>"></div>
            <div class="indicator-bar <?php echo ($step === 2) ? 'active' : ''; ?>"></div>
            <div class="indicator-bar <?php echo ($step === 3) ? 'active' : ''; ?>"></div>
            <div class="indicator-bar <?php echo ($step === 4) ? 'active' : ''; ?>"></div>
        </div>
    </div>

    <script>
        function validateNewPasswords() {
            const p1 = document.getElementById('new_password').value;
            const p2 = document.getElementById('confirm_password').value;
            const err = document.getElementById('pass-match-err');

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