<?php
include 'db.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$key = ""; $val = "";

if ($action == 'backup_on')  { $key = 'backup_status'; $val = '1'; }
if ($action == 'backup_off') { $key = 'backup_status'; $val = '0'; }
if ($action == 'lock_on')    { $key = 'system_lock';   $val = '1'; }
if ($action == 'lock_off')   { $key = 'system_lock';   $val = '0'; }

if ($key === '') {
    echo json_encode(["status" => "error", "message" => "Unknown action"]);
    exit;
}

$stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
$stmt->bind_param("ss", $val, $key);

if ($stmt->execute()) {

    // ✅ Backup ON ሲያደርጉ — reset ያደርጋል (ወዲያውኑ backup እንዲሠራ)
    if ($action == 'backup_on') {
        $conn->query("INSERT INTO system_settings (setting_key, setting_value) 
                      VALUES ('last_backup_checksum', '-1')
                      ON DUPLICATE KEY UPDATE setting_value = '-1'");

        // Throttle file ደምስስ — 5 ደቂቃ እንዳይጠብቅ
        $throttle_file = sys_get_temp_dir() . '/backup_check_last_run.txt';
        if (file_exists($throttle_file)) {
            unlink($throttle_file);
        }
    }

    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => "DB update failed"]);
}
?>