<?php
// This file is included in db.php. It does not need a new connection.

// 1. Backup ON ነው ወይ?
$policy_q = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'backup_status'");
if (!$policy_q || $policy_q->num_rows == 0) return;
$policy = $policy_q->fetch_assoc()['setting_value'];
if ($policy != '1') return;

// 2. Last checksum ምን ነበር?
$last_checksum_q = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'last_backup_checksum'");
$last_checksum = ($last_checksum_q && $last_checksum_q->num_rows > 0)
    ? (int)$last_checksum_q->fetch_assoc()['setting_value']
    : -1;

$is_first_time = ($last_checksum === -1);

// 3. First time ካልሆነ — 5 ደቂቃ throttle ይፈትሻል
if (!$is_first_time) {
    $throttle_file = sys_get_temp_dir() . '/backup_check_last_run.txt';
    $last_run = file_exists($throttle_file) ? (int)file_get_contents($throttle_file) : 0;

    if ((time() - $last_run) < 3600) {
        return; // ገና 5 ደቂቃ አልሆነም
    }
    file_put_contents($throttle_file, time());
}

// 4. የሁሉም ጠረጴዛዎች checksum አሁን ምን ይላል?
$tables_q = $conn->query("SELECT table_name FROM information_schema.tables 
                           WHERE table_schema = 'dhrs_db' AND table_type = 'BASE TABLE'");
$current_checksum = 0;
// 4. የሁሉም ጠረጴዛዎች checksum አሁን ምን ይላል? (ሁሉንም ለውጥ እንዲያገኝ)
$tables_q = $conn->query("SELECT table_name FROM information_schema.tables 
                           WHERE table_schema = 'dhrs_db' AND table_type = 'BASE TABLE'");
$current_checksum = 0;

if ($tables_q) {
    while ($row = $tables_q->fetch_assoc()) {
        $table = $row['table_name'];
    
        $res = $conn->query("CHECKSUM TABLE `$table` "); 
        if ($res) {
            $checksum_row = $res->fetch_assoc();
            $current_checksum += (int)$checksum_row['Checksum'];
        }
    }
}

// 5. ዳታ ተቀይሯል ወይ? (ወይም first time)
if ($is_first_time || $current_checksum !== $last_checksum) {

    if (!file_exists('backups')) {
        mkdir('backups', 0777, true);
    }

    $today    = date('Y-m-d');
    $filename = "backup_" . $today . "_" . time() . ".sql";
    $filepath = "backups/" . $filename;

    $command = "C:/xampp/mysql/bin/mysqldump.exe --port=3307 -u root dhrs_db > " . $filepath;
    exec($command);

    // 6. አዲሱን checksum ዳታቤዝ ይጻፋል
    $conn->query("INSERT INTO system_settings (setting_key, setting_value) 
                  VALUES ('last_backup_checksum', '$current_checksum')
                  ON DUPLICATE KEY UPDATE setting_value = '$current_checksum'");

    $conn->query("INSERT INTO system_settings (setting_key, setting_value) 
                  VALUES ('last_backup_date', '$today')
                  ON DUPLICATE KEY UPDATE setting_value = '$today'");

    // Throttle ወዲያው ይጀምራል (first time backup ከሆነ በኋላ 5 ደቂቃ ይጠብቃል)
    $throttle_file = sys_get_temp_dir() . '/backup_check_last_run.txt';
    file_put_contents($throttle_file, time());
}
?>