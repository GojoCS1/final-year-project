<?php
include 'db.php'; 
session_start();

// ለጃቫ ስክሪፕት ምላሽ ለመስጠት
header('Content-Type: application/json');

// 1. ሴሽን መኖሩን ማረጋገጥ
if (!isset($_SESSION['staff_id'])) {
    echo json_encode(["status" => "error", "message" => "Session expired. Please login again."]);
    exit();
}

$staff_id = $_SESSION['staff_id'];
$name = $_POST['name'] ?? '';
$phone = $_POST['phone'] ?? '';
$email = $_POST['email'] ?? '';
$curr_pass = $_POST['curr_pass'] ?? '';
$new_pass = $_POST['new_pass'] ?? '';

// 2. የፓስዎርድ ጥንካሬ ቼክ (አዲስ ፓስዎርድ ከተላከ ብቻ)
if (!empty($new_pass)) {
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.]).{8,}$/', $new_pass)) {
        echo json_encode(["status" => "error", "message" => "New password is too weak! Use 8+ characters with Uppercase, smallcase, numbers, and symbols."]);
        exit();
    }
}

try {
    // 3. የድሮውን ፓስዎርድ ትክክለኛነት ማረጋገጥ
    $check_stmt = $conn->prepare("SELECT password FROM users WHERE staff_id = ?");
    $check_stmt->bind_param("s", $staff_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $user = $result->fetch_assoc();

    // *** አዲሱ ለውጥ፡ password_verify በመጠቀም የድሮውን ፓስወርድ ማረጋገጥ ***
    if (!$user || !password_verify($curr_pass, $user['password'])) {
        echo json_encode(["status" => "error", "message" => "Current password is incorrect. Information not saved."]);
        exit();
    }

    // 4. ዳታቤዝን አፕዴት ማድረግ
    if (!empty($new_pass)) {
        // *** አዲሱ ለውጥ፡ አዲሱን ፓስወርድ ሀሽ ማድረግ ***
        $hashed_new_pass = password_hash($new_pass, PASSWORD_DEFAULT);

        // ፓስዎርድን ጨምሮ አፕዴት ማድረግ
        $update_stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, email = ?, password = ? WHERE staff_id = ?");
        $update_stmt->bind_param("sssss", $name, $phone, $email, $hashed_new_pass, $staff_id);
    } else {
        // ፓስዎርድ ሳይቀየር ሌላውን መረጃ ብቻ አፕዴት ማድረግ
        $update_stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, email = ? WHERE staff_id = ?");
        $update_stmt->bind_param("ssss", $name, $phone, $email, $staff_id);
    }

    if ($update_stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Profile updated successfully!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database update failed."]);
    }

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Server error occurred."]);
}
?>