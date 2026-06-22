<?php
include 'db.php'; // ፖርት 3307 ያለበት ፋይል
session_start();

// ከፎርሙ የመጡ ዳታዎች
$user = isset($_POST['username']) ? trim($_POST['username']) : '';
$pass = isset($_POST['password']) ? trim($_POST['password']) : '';

if (empty($user) || empty($pass)) {
    echo json_encode(["status" => "error", "message" => "Please enter both username and password!"]);
    exit;
}

// 1. ተጠቃሚውን በ Username ብቻ እንፈልጋለን (ፓስወርዱን እዚህ ጋር አንጠይቅም)
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $user);
$stmt->execute();
$result = $stmt->get_result();

// 2. ተጠቃሚው በዛ ስም ከተገኘ
if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $hashed_password_db = $row['password']; // ዳታቤዝ ውስጥ ያለው የተቀየረው (Hash) ፓስወርድ

    // 3. ዋናው ሚስጥር፡- ተጠቃሚው የጻፈው ($pass) ከዳታቤዙ ($hashed_password_db) ጋር መመሳሰሉን ማረጋገጫ
    if (password_verify($pass, $hashed_password_db)) {
        
        // ፓስወርዱ ትክክል ከሆነ ሴሽኖችን እንይዛለን
        $_SESSION['staff_id'] = $row['staff_id'];
        $_SESSION['role'] = $row['role'];
        $db_role = $row['role'];

        echo json_encode([
            "status" => "success", 
            "redirect" => $db_role . ".php" 
        ]);
    } else {
        // ፓስወርዱ ስህተት ከሆነ
        echo json_encode([
            "status" => "error", 
            "message" => "Invalid Username or Password!"
        ]);
    }
} else {
    // ተጠቃሚው በዛ ስም ዳታቤዝ ውስጥ ከሌለ
    echo json_encode([
        "status" => "error", 
        "message" => "Invalid Username or Password!"
    ]);
}
?>