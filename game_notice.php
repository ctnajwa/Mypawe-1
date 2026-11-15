<?php
session_start();

if (!isset($_SESSION['user_id'])
    || ($_SESSION['user_role'] ?? '') !== 'Pengguna Awam'
    || ($_SESSION['member_health_game_user_id'] ?? '') !== $_SESSION['user_id']) {
    header("Location: register_member.php?step=5");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notis Ujian Kesihatan - PAWE Pekan</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f4f8; margin: 0; padding: 40px; display: flex; justify-content: center; align-items: center; min-height: 80vh; }
        .notice-container { background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); max-width: 550px; text-align: center; }
        h1 { color: #0d3b66; font-size: 24px; margin-bottom: 15px; }
        p { color: #0d1b4c; font-size: 16px; line-height: 1.6; margin-bottom: 25px; }
        .steps-box { background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; text-align: left; margin-bottom: 30px; }
        .steps-box h3 { color: #2d3748; font-size: 16px; margin-top: 0; margin-bottom: 10px; }
        .steps-box ul { margin: 0; padding-left: 20px; color: #4a5568; font-size: 15px; }
        .steps-box li { margin-bottom: 8px; }
        .btn-start { background: #2563eb; color: #white; color: white; border: none; padding: 14px 35px; font-size: 16px; font-weight: bold; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: background 0.2s; }
        .btn-start:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="notice-container">
        <h1>Pemeriksaan Kesihatan & Kognitif</h1>
        <p>Terima kasih kerana mendaftar. Sebelum pendaftaran anda dihantar, sila ambil masa sebentar untuk melengkapkan <strong>dua ujian saringan ringkas</strong> bagi menilai daya ingatan dan tindak balas.</p>
        
        <div class="steps-box">
            <h3>Langkah Ujian:</h3>
            <ul>
                <li><strong>Ujian 1:</strong> Permainan Memori (Memory Match) - Padankan pasangan kad emoji.</li>
                <li><strong>Ujian 2:</strong> Ujian Padanan Warna (Color Matching) - Jawab 3 soalan pantas.</li>
            </ul>
        </div>

        <!-- Clicking this starts Game 1 -->
        <a href="game_memory_reg.php" class="btn-start">Mula Ujian Sekarang</a>
    </div>
</body>
</html>