<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

include 'db.php'; 
header('Content-Type: application/json');

$period = $_GET['period'] ?? 'daily';
$category = $_GET['category'] ?? 'new_patients';
$sub_status = $_GET['sub_status'] ?? '';

// የቀን ገደብ ማስተካከያ
switch ($period) {
    case 'weekly':  $date_limit = "t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; break;
    case 'monthly': $date_limit = "t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"; break;
    case 'yearly':  $date_limit = "t.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)"; break;
    default:        $date_limit = "DATE(t.created_at) = CURDATE()"; break;
}

// ለ New Patients ብቻ የሚሆን ልዩ የቀን ኮለም (reg_date ስለሚጠቀሙ)
if ($category == 'new_patients') {
    switch ($period) {
        case 'weekly':  $date_limit = "reg_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; break;
        case 'monthly': $date_limit = "reg_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)"; break;
        case 'yearly':  $date_limit = "reg_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)"; break;
        default:        $date_limit = "DATE(reg_date) = CURDATE()"; break;
    }
}

$response = ["status" => "success", "data" => []];

try {
    // አጠቃላይ የዕድሜ እና የጾታ ስታቲስቲክስ SQL
    $stats_sql = "COUNT(*) as total, 
        SUM(CASE WHEN p.gender='Male' THEN 1 ELSE 0 END) as male,
        SUM(CASE WHEN p.gender='Female' THEN 1 ELSE 0 END) as female,
        SUM(CASE WHEN p.age <= 5 AND p.gender='Male' THEN 1 ELSE 0 END) as u5m,
        SUM(CASE WHEN p.age <= 5 AND p.gender='Female' THEN 1 ELSE 0 END) as u5f,
        SUM(CASE WHEN p.age <= 5 THEN 1 ELSE 0 END) as u5t,
        SUM(CASE WHEN p.age > 5 AND p.age <= 18 AND p.gender='Male' THEN 1 ELSE 0 END) as a5_18m,
        SUM(CASE WHEN p.age > 5 AND p.age <= 18 AND p.gender='Female' THEN 1 ELSE 0 END) as a5_18f,
        SUM(CASE WHEN p.age > 5 AND p.age <= 18 THEN 1 ELSE 0 END) as a5_18t,
        SUM(CASE WHEN p.age > 18 AND p.age <= 35 AND p.gender='Male' THEN 1 ELSE 0 END) as a18_35m,
        SUM(CASE WHEN p.age > 18 AND p.age <= 35 AND p.gender='Female' THEN 1 ELSE 0 END) as a18_35f,
        SUM(CASE WHEN p.age > 18 AND p.age <= 35 THEN 1 ELSE 0 END) as a18_35t,
        SUM(CASE WHEN p.age > 35 AND p.age <= 60 AND p.gender='Male' THEN 1 ELSE 0 END) as a35_60m,
        SUM(CASE WHEN p.age > 35 AND p.age <= 60 AND p.gender='Female' THEN 1 ELSE 0 END) as a35_60f,
        SUM(CASE WHEN p.age > 35 AND p.age <= 60 THEN 1 ELSE 0 END) as a35_60t,
        SUM(CASE WHEN p.age > 60 AND p.gender='Male' THEN 1 ELSE 0 END) as a60m,
        SUM(CASE WHEN p.age > 60 AND p.gender='Female' THEN 1 ELSE 0 END) as a60f,
        SUM(CASE WHEN p.age > 60 THEN 1 ELSE 0 END) as a60t";

    if ($category == 'lab_test') {
        // የላብራቶሪ ምርመራዎችን በየአይነታቸው ለይቶ የሚያወጣ SQL
        // ማሳሰቢያ፡ በዳታቤዝህ ላይ test_type የሚለው ስም ሌላ ከሆነ (ለምሳሌ test_name) እሱን እዚህ ጋር ቀይረው
        $sql = "SELECT t.test_type as test_name, 
                       COUNT(*) as count, 
                       SUM(CASE WHEN p.gender='Male' THEN 1 ELSE 0 END) as male, 
                       SUM(CASE WHEN p.gender='Female' THEN 1 ELSE 0 END) as female 
                FROM lab_requests t 
                JOIN patients p ON t.mrn = p.mrn 
                WHERE $date_limit 
                GROUP BY t.test_type";
        
        $res = $conn->query($sql);
        if (!$res) throw new Exception($conn->error);
        
        while($row = $res->fetch_assoc()) { 
            $response['data'][] = $row; 
        }
    } 
    elseif ($category == 'referrals') {
        // 1. የሪፈራል ዕድሜ ስታቲስቲክስ
        $age_stats = $conn->query("SELECT $stats_sql FROM referrals t JOIN patients p ON t.mrn = p.mrn WHERE $date_limit")->fetch_assoc();
        
        // 2. የሪፈራል ምክንያቶች በዝርዝር
        $summ_res = $conn->query("SELECT t.reason_for_referral as label, COUNT(*) as total, SUM(CASE WHEN p.gender='Male' THEN 1 ELSE 0 END) as male, SUM(CASE WHEN p.gender='Female' THEN 1 ELSE 0 END) as female FROM referrals t JOIN patients p ON t.mrn = p.mrn WHERE $date_limit GROUP BY t.reason_for_referral");
        
        $summary = []; 
        while($r = $summ_res->fetch_assoc()){ $summary[] = $r; }
        
        $response['data'] = ["age_stats" => $age_stats, "reason_summary" => $summary];
    } 
    else {
        // ለሌሎች (New Patients, Appointments, Follow-ups, Discharges)
        $tables = [
            'new_patients' => 'patients p', 
            'appointments' => 'appointments t JOIN patients p ON t.mrn=p.mrn', 
            'follow_ups'   => 'medication_followup t JOIN patients p ON t.mrn=p.mrn', 
            'discharges'   => 'discharge_summaries t JOIN patients p ON t.mrn=p.mrn'
        ];
        
        $sql = "SELECT $stats_sql FROM ".$tables[$category]." WHERE $date_limit";
        
        if ($category == 'discharges' && !empty($sub_status)) {
            $sql .= " AND t.condition_status = '$sub_status'";
        }
        
        $res = $conn->query($sql);
        if (!$res) throw new Exception($conn->error);
        $response['data'] = $res->fetch_assoc();
    }

    ob_end_clean();
    echo json_encode($response);
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>