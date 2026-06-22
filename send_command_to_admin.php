<?php
// ነበር vs አሁን — ዋና ልዩነቶች ↓ ኮምሜንት ተደርጓል
include 'db.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $type = $_POST['type'];
    
    // ነበር: Other ብቻ ይቀይር ነበር
    // አሁን: Reset Password ሲምጣ subtype ወስደን ይቀይራل
    if ($type === 'Other' && !empty($_POST['custom_type'])) {
        $type = $_POST['custom_type'];
    }
    // አዲስ — Reset Password ሲልኩ radio selection ወስዶ ያስቀምጣል
    elseif ($type === 'Reset Password' && !empty($_POST['reset_subtype'])) {
        $type = $_POST['reset_subtype']; // "Reset Username" / "Reset Password" / "Reset Username and Password"
    }

    $msg  = $_POST['message'];
    $target = $_POST['target_staff_id'] ?? null;
    $sender = 'staff_admin';

    // አሁን: 3 reset types ሁሉ ያካትታል
    $resetTypes = ['Reset Password', 'Reset Username', 'Reset Username and Password'];
    if ($type === 'Delete Account' || in_array($type, $resetTypes)) {
        
        if (empty($target)) {
            echo "<script>alert('Error: Please provide a Staff ID!'); history.back();</script>";
            exit();
        }

        $check_stmt = $conn->prepare("SELECT staff_id FROM users WHERE staff_id = ?");
        $check_stmt->bind_param("s", $target);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows === 0) {
            echo "<script>alert('Error: Staff ID ($target) not found!'); history.back();</script>";
            exit();
        }
        
        // አዲስ — Reset ሲልኩ radio አልመረጡም ከሆነ ያስቆማል
        if (in_array($_POST['type'], ['Reset Password']) && empty($_POST['reset_subtype'])) {
            echo "<script>alert('Error: Please select what to reset (Username/Password/Both)!'); history.back();</script>";
            exit();
        }
    }

    $sql  = "INSERT INTO admin_commands (sender_role, command_type, message, target_staff_id, is_read, created_at) 
             VALUES (?, ?, ?, ?, 0, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $sender, $type, $msg, $target);

    if ($stmt->execute()) {
        echo "<script>alert('Command sent successfully!'); window.location.href='staff_admin.php?task=command';</script>";
    } else {
        echo "<script>alert('Error: Could not send command.'); history.back();</script>";
    }
}
?>