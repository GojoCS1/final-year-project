<?php
include 'db.php';

/* 
   English Comment: 
   Select the highest MRN value from the 'patients' table.
   We sort by 'mrn' in descending order to get the last one registered.
*/
$sql = "SELECT mrn FROM patients ORDER BY mrn DESC LIMIT 1";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $last_mrn = $row['mrn']; // This will get "MRN_000000" from your DB

    /* 
       Separate the prefix "MRN_" from the numeric part.
       The prefix is 4 characters long (M, R, N, _).
    */
    $prefix = substr($last_mrn, 0, 4); // Result: "MRN_"
    $number_part = substr($last_mrn, 4); // Result: "000000"
    
    /* Convert the string number to an integer and add 1 */
    $next_number = intval($number_part) + 1;

    /*  
       Format the number back to 6 digits with leading zeros (000001).
    */
    $new_number_string = str_pad($next_number, 6, "0", STR_PAD_LEFT);
    
    echo $prefix . $new_number_string; // Example output: MRN_000001
} else {
    /* 
       If the database table is completely empty, start with the first ID.
    */
    echo "MRN_000000";
}
?>