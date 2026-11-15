<?php
session_start();

if (!isset($_SESSION['user_id'])
    || ($_SESSION['user_role'] ?? '') !== 'Pengguna Awam'
    || ($_SESSION['member_health_game_user_id'] ?? '') !== $_SESSION['user_id']
    || !isset($_SESSION['memory_game_time'])
    || ($_SESSION['member_health_memory_started_for'] ?? '') !== $_SESSION['user_id']) {
    header("Location: register_member.php?step=5");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(405);
    header('Allow: GET');
    exit();
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ujian 2: Padanan Warna - PAWE Pekan</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f4f8; margin: 0; padding: 30px; text-align: center; }
        .game-container { max-width: 500px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
        h1 { color: #0d3b66; font-size: 24px; margin-bottom: 5px; }
        .subtitle { color: #6c757d; font-size: 14px; margin-bottom: 15px; }
        .device-badge { display: inline-block; background: #e2e8f0; color: #2d3748; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; margin-bottom: 20px; }
        .timer { font-size: 20px; font-weight: bold; color: #e53e3e; margin-bottom: 20px; }
        .question-box { font-size: 18px; font-weight: bold; color: #1a202c; margin-bottom: 20px; background: #f7fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px; }
        .option-btn { padding: 20px; font-size: 18px; font-weight: bold; border: none; border-radius: 8px; cursor: pointer; color: white; transition: transform 0.1s, opacity 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .option-btn:hover { opacity: 0.9; transform: scale(1.02); }
    </style>
</head>
<body>
    <div class="game-container">
        <h1>Ujian 2: Padanan Warna</h1>
        <div class="subtitle">Jawab 3 soalan pantas untuk menguji fokus visual.</div>
        <div id="deviceIndicator" class="device-badge">Mengesan Peranti...</div>
        <div class="timer">Masa: <span id="timeCount">0</span> saat</div>

        <div id="gameArea">
            <div class="question-box" id="questionText">Sila bersedia...</div>
            <div class="options-grid" id="optionsGrid"></div>
        </div>
    </div>

    <script>
        // Automatic Device Detection
        const isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
        document.getElementById('deviceIndicator').innerText = 'Peranti: ' + (isTouchDevice ? 'Skrin Sentuh (Telefon/Tablet)' : 'Desktop (Komputer)');

        // Set of 3 questions
        const questions = [
            {
                instruction: "Tekan butang warna <strong>MERAH</strong>",
                options: [
                    { name: "Merah", color: "#e53e3e", val: "red" },
                    { name: "Biru", color: "#3182ce", val: "blue" },
                    { name: "Hijau", color: "#38a169", val: "green" },
                    { name: "Kuning", color: "#d69e2e", val: "yellow" }
                ],
                correctVal: "red"
            },
            {
                instruction: "Tekan butang warna <strong>HIJAU</strong>",
                options: [
                    { name: "Kuning", color: "#d69e2e", val: "yellow" },
                    { name: "Hijau", color: "#38a169", val: "green" },
                    { name: "Merah", color: "#e53e3e", val: "red" },
                    { name: "Biru", color: "#3182ce", val: "blue" }
                ],
                correctVal: "green"
            },
            {
                instruction: "Tekan butang warna <strong>BIRU</strong>",
                options: [
                    { name: "Hijau", color: "#38a169", val: "green" },
                    { name: "Merah", color: "#e53e3e", val: "red" },
                    { name: "Biru", color: "#3182ce", val: "blue" },
                    { name: "Kuning", color: "#d69e2e", val: "yellow" }
                ],
                correctVal: "blue"
            }
        ];

        let currentQuestion = 0;
        let seconds = 0;
        let timerInterval = null;
        let gameStarted = false;

        function startTimer() {
            if (!gameStarted) {
                gameStarted = true;
                timerInterval = setInterval(() => {
                    seconds++;
                    document.getElementById('timeCount').innerText = seconds;
                }, 1000);
            }
        }

        function loadQuestion() {
            startTimer();
            if (currentQuestion >= questions.length) {
                endGame();
                return;
            }

            const q = questions[currentQuestion];
            document.getElementById('questionText').innerHTML = `Soalan ${currentQuestion + 1}/3: ${q.instruction}`;
            
            const grid = document.getElementById('optionsGrid');
            grid.innerHTML = '';

            q.options.forEach(opt => {
                const btn = document.createElement('button');
                btn.className = 'option-btn';
                btn.style.backgroundColor = opt.color;
                btn.innerText = opt.name;
                btn.onclick = () => checkAnswer(opt.val, q.correctVal);
                grid.appendChild(btn);
            });
        }

        function checkAnswer(selected, correct) {
            if (selected === correct) {
                currentQuestion++;
                loadQuestion();
            } else {
                alert("Salah! Sila cuba pilih butang yang betul.");
            }
        }

        function endGame() {
            clearInterval(timerInterval);
            alert(`Tahniah! Semua ujian saringan telah selesai dalam masa ${seconds} saat. Pendaftaran anda akan disimpan.`);
            saveTimeAndFinish(seconds);
        }

        function saveTimeAndFinish(time) {
            const params = new URLSearchParams({
                action_type: 'complete_health_game',
                color_time: String(time)
            });

            fetch('register_member_action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: params.toString()
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Gagal menyimpan keputusan Ujian Padanan Warna.');
                }
                return response.text();
            })
            .then(result => {
                if (result.trim() !== 'Success') {
                    throw new Error('Keputusan Ujian Padanan Warna tidak dapat disahkan.');
                }
                window.location.href = 'register_member.php?step=6';
            })
            .catch(error => {
                console.error('Ralat:', error);
                alert('Keputusan permainan tidak dapat disimpan. Sila muat semula halaman dan cuba lagi.');
            });
        }

        window.onload = loadQuestion;
    </script>
</body>
</html>