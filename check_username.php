<?php
// check_username.php
header('Content-Type: application/json');

$conn = new mysqli("localhost", "root", "", "mypawe");
if ($conn->connect_error) {
    echo json_encode(['available' => true]);
    exit();
}

$username = trim($_GET['username'] ?? '');

if (empty($username)) {
    echo json_encode(['available' => true]);
    exit();
}

$stmt = $conn->prepare("SELECT user_id FROM user WHERE user_id = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo json_encode(['available' => true]);
} else {
    // Generate suggestion by appending numbers
    $counter = 1;
    $suggested = $username;
    
    $checkStmt = $conn->prepare("SELECT user_id FROM user WHERE user_id = ?");
    do {
        $counter++;
        $suggested = $username . $counter;
        $checkStmt->bind_param("s", $suggested);
        $checkStmt->execute();
        $checkStmt->store_result();
    } while ($checkStmt->num_rows > 0 && $counter < 20);

    echo json_encode([
        'available' => false,
        'suggestion' => $suggested
    ]);
    $checkStmt->close();
}

$stmt->close();
$conn->close();