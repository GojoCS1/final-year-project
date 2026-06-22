<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mrn = $_POST['mrn'];
    $name = trim($_POST['full_name']);
    $reg = $_POST['reg_date'];
    $age = $_POST['age'];
    $gender = $_POST['gender'];
    $dob = $_POST['dob'];
    $nat = $_POST['nationality'];
    $region = $_POST['region'];
    $wer = $_POST['wereda'];
    $keb = $_POST['kebele'];
    $ket = $_POST['ketena'];
    $hou = $_POST['house'];
    $pho = $_POST['phone'];

     if (empty($name)) {
        echo "Error: Full Name is required and cannot be empty!";
        exit();
    }

    $sql = "INSERT INTO patients (mrn, full_name, reg_date, age, gender, dob, nationality, region, wereda, kebele, ketena, house_number, phone) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        full_name = VALUES(full_name),
        dob = VALUES(dob),
        age = VALUES(age),
        gender = VALUES(gender),
        nationality = VALUES(nationality),
        region = VALUES(region),
        wereda = VALUES(wereda),
        kebele = VALUES(kebele),
        ketena = VALUES(ketena),
        house_number = VALUES(house_number),
        phone = VALUES(phone)";
    
    $stmt = $conn->prepare($sql);
    // 13 parameters (ያለ complaint)
   $stmt->bind_param("sssssssssssss", $mrn, $name, $reg, $age, $gender, $dob, $nat, $region, $wer, $keb, $ket, $hou, $pho);

    if ($stmt->execute()) { echo "Success"; } 
    else { echo "Error: " . $conn->error; }
}
?>
