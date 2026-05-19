<?php
    include "db_connect.php";

    $device_code = $_GET['device_code'];

    $getPatient = $conn->query("
        SELECT patient_id FROM device_pairing 
        WHERE device_code='$device_code'
    ");

    $row = $getPatient->fetch_assoc();
    $patient_id = $row['patient_id'];

    $sql = "SELECT medicine_name, medication_hour, medication_minute 
            FROM medication_schedule 
            WHERE patient_id='$patient_id'";

    $result = $conn->query($sql);

    $data = array();

    while($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    echo json_encode($data);
    ?>