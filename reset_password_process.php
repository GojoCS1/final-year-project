<?php
include 'db.php';
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $staff_id = $_POST['staff_id'];
    $new_pass = $_POST['new_password'];

    // 1. የፓስዎርድ ጥንካሬ ቼክ
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.]).{8,}$/', $new_pass)) {
        echo json_encode(["status" => "error", "message" => "New password is too weak! Use 8+ characters with Uppercase, smallcase, numbers, and symbols."]);
        exit();
    }

    // *** አዲሱ ለውጥ እዚህ ጋር ነው፡ ፓስወርዱን ሀሽ እናደርገዋለን ***
    $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE staff_id = ?");
    $stmt->bind_param("ss", $hashed_pass, $staff_id); // እዚህ ጋር $hashed_pass ገባ

    if ($stmt->execute()) {
        $update_cmd = $conn->prepare("UPDATE admin_commands SET reply = 'Password Reset Successfully' WHERE target_staff_id = ? AND command_type = 'Reset Password'");
        $update_cmd->bind_param("s", $staff_id);
        $update_cmd->execute();

        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }
}
?>