<?php
session_start();

if (!isset($_SESSION['user_id'])
    || ($_SESSION['user_role'] ?? '') !== 'Pengguna Awam'
    || ($_SESSION['member_health_game_user_id'] ?? '') !== $_SESSION['user_id']) {
    header("Location: register_member.php?step=5");
    exit();
}

// Handle AJAX score submission from JavaScript
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['member_health_memory_started_for'])
        || $_SESSION['member_health_memory_started_for'] !== $_SESSION['user_id']
        || !isset($_POST['memory_time'])
        || !ctype_digit((string)$_POST['memory_time'])) {
        http_response_code(400);
        echo "Invalid game completion.";
        exit();
    }

    $_SESSION['memory_game_time'] = (int)$_POST['memory_time'];
    echo "Success";
    exit();
}

$_SESSION['member_health_memory_started_for'] = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ujian 1: Permainan Memori - PAWE Pekan</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f4f8; margin: 0; padding: 30px; text-align: center; }
        .game-container { max-width: 500px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
        h1 { color: #0d3b66; font-size: 24px; margin-bottom: 5px; }
        .subtitle { color: #6c757d; font-size: 14px; margin-bottom: 15px; }
        .device-badge { display: inline-block; background: #e2e8f0; color: #2d3748; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; margin-bottom: 20px; }
        .timer { font-size: 20px; font-weight: bold; color: #e53e3e; margin-bottom: 20px; }
        
        /* Memory Grid (4x4) */
        .memory-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
        .memory-card { aspect-ratio: 1; background: #0d3b66; color: white; font-size: 28px; display: flex; align-items: center; justify-content: center; border-radius: 8px; cursor: pointer; user-select: none; transition: background 0.2s; }
        .memory-card.flipped { background: #3182ce; }
        .memory-card.matched { background: #38a169; cursor: default; opacity: 0.8; }
    </style>
</head>
<body>
    <div class="game-container">
        <h1>Ujian 1: Permainan Memori</h1>
        <div class="subtitle">Padankan semua pasangan kad emoji dengan pantas.</div>
        <div id="deviceIndicator" class="device-badge">Mengesan Peranti...</div>
        <div class="timer">Masa: <span id="timeCount">0</span> saat</div>

        <div class="memory-grid" id="memoryGrid"></div>
    </div>

    <script>
        // Automatic Device Detection
        const isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
        const deviceType = isTouchDevice ? 'Touchscreen' : 'Desktop';
        document.getElementById('deviceIndicator').innerText = 'Peranti: ' + (isTouchDevice ? 'Skrin Sentuh (Telefon/Tablet)' : 'Desktop (Komputer)');

        // Game Logic
        const emojis = ['🍎', '🍌', '🍇', '🍉', '🍓', '🍒', '🍍', '🥝'];
        let cards = [...emojis, ...emojis]; // 8 pairs = 16 cards
        cards.sort(() => 0.5 - Math.random());

        const grid = document.getElementById('memoryGrid');
        let flippedCards = [];
        let matchedPairs = 0;
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

        // Render board
        cards.forEach((emoji, index) => {
            const card = document.createElement('div');
            card.classList.add('memory-card');
            card.dataset.emoji = emoji;
            card.addEventListener('click', () => flipCard(card));
            grid.appendChild(card);
        });

        function flipCard(card) {
            startTimer();
            if (card.classList.contains('flipped') || card.classList.contains('matched') || flippedCards.length >= 2) {
                return;
            }

            card.classList.add('flipped');
            card.innerText = card.dataset.emoji;
            flippedCards.push(card);

            if (flippedCards.length === 2) {
                setTimeout(checkMatch, 600);
            }
        }

        function checkMatch() {
            const [card1, card2] = flippedCards;
            if (card1.dataset.emoji === card2.dataset.emoji) {
                card1.classList.add('matched');
                card2.classList.add('matched');
                matchedPairs++;

                if (matchedPairs === emojis.length) {
                    clearInterval(timerInterval);
                    alert(`Tahniah! Ujian Memori selesai dalam masa ${seconds} saat. Seterusnya ke Ujian 2.`);
                    
                    // Save time to session via AJAX and redirect to Color Game
                    saveTimeAndNext(seconds);
                }
            } else {
                card1.classList.remove('flipped');
                card1.innerText = '';
                card2.classList.remove('flipped');
                card2.innerText = '';
            }
            flippedCards = [];
        }

        function saveTimeAndNext(time) {
            const formData = new window.FormData();
            formData.append('memory_time', time);

            fetch('game_memory_reg.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Gagal menyimpan keputusan Ujian Memori.');
                }
                return response.text();
            })
            .then(result => {
                if (result.trim() !== 'Success') {
                    throw new Error('Keputusan Ujian Memori tidak dapat disahkan.');
                }
                window.location.href = 'game_color_reg.php';
            })
            .catch(error => {
                console.error('Ralat:', error);
                alert('Keputusan permainan tidak dapat disimpan. Sila muat semula halaman dan cuba lagi.');
            });
        }
    </script>
</body>
</html>