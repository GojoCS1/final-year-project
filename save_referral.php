<?php
include 'db.php';
header('Content-Type: application/json');
session_start();

$mrn = $_POST['mrn'] ?? '';
$staff = $_SESSION['staff_id'] ?? '';

try {
    $sql = "INSERT INTO referrals 
            (mrn, staff_id, referral_date, patient_name, age, gender, phone, 
             chief_complaint, hpi_findings, provisional_diagnosis, 
             treatment_given, reason_for_referral, receiving_hospital, 
             doctor_name, profession) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
    $stmt = $conn->prepare($sql);
    
    // ማስተካከያ፡ 5ኛው ፓራሜትር (Age) ከ 'i' ወደ 's' ተቀይሯል (ጠቅላላ 15 's')
    $stmt->bind_param("sssssssssssssss", 
        $mrn, $staff, $_POST['date'], $_POST['name'], $_POST['age'], 
        $_POST['gender'], $_POST['phone'], $_POST['complaint'], 
        $_POST['hpi'], $_POST['diagnosis'], $_POST['treatment'], 
        $_POST['reason'], $_POST['hospital'], $_POST['doc_name'], $_POST['profession']
    );

    if ($stmt->execute()) {
        // ታካሚው ሪፈር ስለተባለ ከWaiting ሰልፍ እንዲወጣ ስታተስ መቀየር
        $conn->query("UPDATE patients SET status = 'Referred' WHERE mrn = '$mrn'");
        
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>