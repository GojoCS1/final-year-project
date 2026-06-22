<?php
include 'db.php';
header('Content-Type: application/json');

// የዛሬውን ቀን በሳምንት ስም (ለምሳሌ Sunday)
$day_name = date('l'); 

try {
    // 1. በሲስተሙ ውስጥ ያሉትን ሁሉንም ዶክተሮች እና ነርሶች ማውጣት
    $sql = "SELECT staff_id, full_name, role FROM users WHERE role IN ('doctor', 'nurse')";
    $result = $conn->query($sql);

    if (!$result) { throw new Exception("Database Error: " . $conn->error); }

    $staff_workload = [];

    while ($row = $result->fetch_assoc()) {
        $sid = $row['staff_id'];
        $fullname = $row['full_name'];

       // ዶክተሩ ያልታከሙ (Waiting) ታካሚዎችን ብቻ እንዲቆጥር
        $p_sql = "SELECT COUNT(*) as total FROM patients 
                  WHERE assigned_to_dept = '$sid' 
                  AND status = 'Waiting'";
        
        $p_res = $conn->query($p_sql);
        $row['active_patients'] = ($p_res) ? (int)$p_res->fetch_assoc()['total'] : 0;

        // 3. የዛሬ ቀጠሮዎችን (Today's Appt) መቁጠር
         $a_sql = "SELECT COUNT(*) as total FROM appointments 
                  WHERE staff_id = '$sid' 
                  AND DATE(appointment_date) = CURRENT_DATE
                  AND (status IS NULL OR status = 'Pending' OR status != 'Completed')";
        
        $a_res = $conn->query($a_sql);
        $row['today_appointments'] = ($a_res) ? (int)$a_res->fetch_assoc()['total'] : 0;

        // 4. ይህ ሰራተኛ ዛሬ ተረኛ መሆኑን ቼክ ማድረግ
        // ስሙን በ LIKE ስለምንፈልገው 'Kalay Hafte' የሚለው 'Kalay Hafte (doctor)' ከሚለው ጋር ይገጥማል
        $s_sql = "SELECT COUNT(*) as total FROM schedules 
                  WHERE staff_name_id LIKE '" . $conn->real_escape_string($fullname) . "%' 
                  AND shift_day = '$day_name'";
        $s_res = $conn->query($s_sql);
        $is_scheduled = ($s_res) ? (int)$s_res->fetch_assoc()['total'] : 0;

        // ዶክተሩ ዛሬ ተረኛ ከሆነ ወይም ታካሚዎች ካሉት ወይም ቀጠሮ ካለው በዝርዝሩ ውስጥ እንዲታይ እናደርጋለን
        if ($is_scheduled > 0 || $row['active_patients'] > 0 || $row['today_appointments'] > 0) {
            $staff_workload[] = $row;
        }
    }

    echo json_encode($staff_workload);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>