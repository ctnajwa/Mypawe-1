<?php
session_start();

$error =$_SESSION['reg_error'] ?? '';
unset($_SESSION['reg_error']);

$old =$_SESSION['old_input'] ?? [];
unset($_SESSION['old_input']);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran - MyPAWE</title>
    <link rel="stylesheet" href="Style/style_registration.css">
</head>
<body>

    <div class="reg-card">
        <!-- Center Logo -->
        <img src="Images/logo_pawe_pekan.jpg" alt="Logo PAWE Pekan" class="reg-logo" onerror="this.style.display='none'">

        <h2 class="reg-title">Pendaftaran</h2>

        <?php if (!empty($error)): ?>
            <div class="alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="registration_action.php" method="POST" class="reg-form" onsubmit="return validatePasswords();">
            
            <!-- 1. ID Pengguna -->
            <div class="form-group">
                <label for="user_id">ID Pengguna</label>
                <input 
                    type="text" 
                    id="user_id" 
                    name="user_id" 
                    value="<?php echo htmlspecialchars($old['user_id'] ?? ''); ?>" 
                    required 
                    autocomplete="off"
                >
                <div id="username-msg"></div>
            </div>

            <!-- 2. Emel Pengguna -->
            <div class="form-group">
                <label for="user_email">Emel Pengguna</label>
                <input 
                    type="email" 
                    id="user_email" 
                    name="user_email" 
                    value="<?php echo htmlspecialchars($old['user_email'] ?? ''); ?>" 
                    placeholder="nama@contoh.com" 
                    required
                >
            </div>

            <!-- 3. Nama Pengguna (Full name as per IC, Auto-Caps) -->
            <div class="form-group">
                <label for="user_name">Nama Pengguna</label>
                <input 
                    type="text" 
                    id="user_name" 
                    name="user_name" 
                    class="uppercase-input" 
                    value="<?php echo htmlspecialchars($old['user_name'] ?? ''); ?>" 
                    placeholder="NAMA PENUH SEPERTI DALAM KAD PENGENALAN" 
                    oninput="this.value = this.value.toUpperCase();"
                    required
                >
                <span class="helper-text">*Sila masukkan nama penuh seperti yang tertera dalam Kad Pengenalan.</span>
            </div>

            <!-- 4. IC Pengguna (Without '-') -->
            <div class="form-group">
                <label for="user_ic">IC Pengguna</label>
                <input 
                    type="text" 
                    id="user_ic" 
                    name="user_ic" 
                    placeholder="Contoh: 900405025678" 
                    maxlength="12" 
                    pattern="[0-9]{12}" 
                    title="Sila masukkan 12 digit nombor Kad Pengenalan tanpa tanda (-)" 
                    value="<?php echo htmlspecialchars($old['user_ic'] ?? ''); ?>" 
                    required
                >
            </div>

            <!-- 5. Kata Laluan with Peeking Eye -->
            <div class="form-group">
                <label for="password">Kata Laluan</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required>
                    <button type="button" class="toggle-eye-btn" onclick="togglePassword('password', this)" aria-label="Lihat kata laluan"><span class="eye-icon" aria-hidden="true"></span></button>
                </div>
            </div>

            <!-- 6. Sahkan Kata Laluan with Peeking Eye -->
            <div class="form-group">
                <label for="confirm_password">Sahkan Kata Laluan</label>
                <div class="password-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <button type="button" class="toggle-eye-btn" onclick="togglePassword('confirm_password', this)" aria-label="Lihat kata laluan"><span class="eye-icon" aria-hidden="true"></span></button>
                </div>
                <span id="match-error" class="helper-text" style="color: #e53e3e; display: none;">Kata laluan tidak sepadan!</span>
            </div>

            <!-- Action Buttons -->
            <div class="form-actions">
                <button type="submit" class="btn-daftar">Daftar</button>
                <a href="login.php" class="btn-batal">Batal Pendaftaran</a>
            </div>

        </form>
    </div>

    <!-- Interactive Validations & Suggestions Script -->
    <script>
        // Toggle password visibility
        function togglePassword(fieldId, btn) {
            const field = document.getElementById(fieldId);
            if (field.type === "password") {
                field.type = "text";
                btn.classList.add("is-visible");
                btn.setAttribute("aria-label", "Sembunyikan kata laluan");
            } else {
                field.type = "password";
                btn.classList.remove("is-visible");
                btn.setAttribute("aria-label", "Lihat kata laluan");
            }
        }

        // Live check if username exists
        const usernameInput = document.getElementById('user_id');
        const usernameMsg = document.getElementById('username-msg');

        let debounceTimer;
        usernameInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const username = this.value.trim();

            if (username.length < 3) {
                usernameMsg.innerHTML = "";
                return;
            }

            debounceTimer = setTimeout(() => {
                fetch(`check_username.php?username=${encodeURIComponent(username)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.available) {
                            usernameMsg.className = "msg-success";
                            usernameMsg.innerHTML = "✓ ID Pengguna ini boleh digunakan.";
                        } else {
                            usernameMsg.className = "msg-error";
                            usernameMsg.innerHTML = `⚠️ ID Pengguna telah digunakan. Cadangan: <span class="suggestion-pill" onclick="applySuggestion('${data.suggestion}')">${data.suggestion}</span>`;
                        }
                    })
                    .catch(() => {
                        usernameMsg.innerHTML = "";
                    });
            }, 300);
        });

        function applySuggestion(val) {
            usernameInput.value = val;
            usernameMsg.className = "msg-success";
            usernameMsg.innerHTML = "✓ ID Pengguna ini boleh digunakan.";
        }

        // Password matching validation
        function validatePasswords() {
            const pass = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const errorMsg = document.getElementById('match-error');

            if (pass !== confirm) {
                errorMsg.style.display = 'block';
                return false;
            }
            errorMsg.style.display = 'none';
            return true;
        }
    </script>
</body>
</html>