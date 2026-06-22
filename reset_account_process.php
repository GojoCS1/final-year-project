<?php
include 'db.php';
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'system_admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$staff_id  = $_POST['staff_id']   ?? '';
$resetType = $_POST['reset_type'] ?? '';
$newUser   = $_POST['new_username'] ?? '';
$newPass   = $_POST['new_password'] ?? '';

if (empty($staff_id) || empty($resetType)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit();
}

// ✅ አዲስ — Server-side password strength check
if (!empty($newPass)) {
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.]).{8,}$/', $newPass)) {
        echo json_encode([
            'status'  => 'error', 
            'message' => 'Password too weak! Must be 8+ characters with uppercase, lowercase, number, and symbol (e.g. Staff@123)'
        ]);
        exit();
    }
}

$reply = '';

if ($resetType === 'Reset Username') {
    if (empty($newUser)) {
        echo json_encode(['status' => 'error', 'message' => 'New username is required']); exit();
    }
    $stmt = $conn->prepare("UPDATE users SET username = ? WHERE staff_id = ?");
    $stmt->bind_param("ss", $newUser, $staff_id);
    $reply = 'Username Reset Successfully';

} elseif ($resetType === 'Reset Password') {
    if (empty($newPass)) {
        echo json_encode(['status' => 'error', 'message' => 'New password is required']); exit();
    }
    $hashed = password_hash($newPass, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE staff_id = ?");
    $stmt->bind_param("ss", $hashed, $staff_id);
    $reply = 'Password Reset Successfully';

} elseif ($resetType === 'Reset Username and Password') {
    if (empty($newUser) || empty($newPass)) {
        echo json_encode(['status' => 'error', 'message' => 'Both username and password are required']); exit();
    }
    $hashed = password_hash($newPass, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET username = ?, password = ? WHERE staff_id = ?");
    $stmt->bind_param("sss", $newUser, $hashed, $staff_id);
    $reply = 'Credentials Reset Successfully';

} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid reset type']);
    exit();
}

if ($stmt->execute()) {
    $r = $conn->prepare("UPDATE admin_commands SET reply = ? WHERE target_staff_id = ? AND command_type = ?");
    $r->bind_param("sss", $reply, $staff_id, $resetType);
    $r->execute();
    echo json_encode(['status' => 'success', 'message' => $reply]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database update failed: ' . $conn->error]);
}
?>