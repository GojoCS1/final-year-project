<?php
include 'db.php';
session_start();

// 1. መብት (Permission) ቼክ ማድረግ
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'system_admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// 2. መረጃዎችን ከ POST መቀበል
$staff_id  = $_POST['staff_id']  ?? '';
$full_name = $_POST['full_name'] ?? '';
$gender    = $_POST['gender']    ?? '';
$age       = $_POST['age']       ?? '';
$phone     = $_POST['phone']     ?? '';
$email     = $_POST['email']     ?? '';

// 3. የግዴታ መሞላት ያለባቸውን ቼክ ማድረግ
if (!$staff_id || !$full_name) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit();
}

// 4. የተስተካከለው SQL Query (ኮማው ተወግዷል)
// bind_param ላይ "ssisss" ሆኗል (6 variables ብቻ ስለሆኑ)
$stmt = $conn->prepare("UPDATE users SET full_name=?, gender=?, age=?, phone=?, email=? WHERE staff_id=?");
$stmt->bind_param("ssisss", $full_name, $gender, $age, $phone, $email, $staff_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
}
?>