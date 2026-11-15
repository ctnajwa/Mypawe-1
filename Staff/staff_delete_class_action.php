<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Staf') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $del_id = intval($_POST['delete_class_id'] ?? 0);

    if ($del_id > 0) {
        $conn = new mysqli("localhost", "root", "", "mypawe");
        if (!$conn->connect_error) {
            $check_q = $conn->prepare("SELECT class_date FROM class WHERE class_id = ? LIMIT 1");
            $check_q->bind_param("i", $del_id);
            $check_q->execute();
            $c_data = $check_q->get_result()->fetch_assoc();
            $check_q->close();

            if ($c_data && strtotime($c_data['class_date']) >= strtotime(date('Y-m-d'))) {
                $del_stmt = $conn->prepare("DELETE FROM class WHERE class_id = ?");
                $del_stmt->bind_param("i", $del_id);
                $del_stmt->execute();
                $del_stmt->close();
                $_SESSION['staff_msg'] = "Program telah berjaya dibuang.";
            } else {
                $_SESSION['staff_msg'] = "Ralat: Program yang telah lepas tarikh tidak boleh dibuang.";
            }
            $conn->close();
        }
    }
}

header("Location: staff_view_class.php");
exit();