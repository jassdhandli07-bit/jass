<?php
session_start();
if(!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';

if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php?error=Invalid ID");
    exit();
}

$doctor_id = (int)$_GET['id'];

// ✅ DEBUG: Log the ID
error_log("Attempting to delete doctor ID: " . $doctor_id);

// ✅ Step 1: Check patients
$check_sql = "SELECT COUNT(*) as patient_count FROM patients WHERE assigned_doctor_id = ?";
$check_stmt = $conn->prepare($check_sql);
if(!$check_stmt) {
    header("Location: dashboard.php?error=Prepare failed");
    exit();
}
$check_stmt->bind_param("i", $doctor_id);
$check_stmt->execute();
$patient_result = $check_stmt->get_result()->fetch_assoc();
$patient_count = $patient_result['patient_count'];
$check_stmt->close();

if($patient_count > 0) {
    header("Location: dashboard.php?error=Cannot+delete!+Doctor+has+$patient_count+patients");
    exit();
}

// ✅ Step 2: Get doctor info
$info_sql = "SELECT name, photo_path FROM doctors WHERE doctor_id = ?";
$info_stmt = $conn->prepare($info_sql);
if(!$info_stmt) {
    header("Location: dashboard.php?error=Info+query+failed");
    exit();
}
$info_stmt->bind_param("i", $doctor_id);
$info_stmt->execute();
$info_result = $info_stmt->get_result();

if($info_result->num_rows == 0) {
    header("Location: dashboard.php?error=Doctor+not+found");
    exit();
}

$doctor_info = $info_result->fetch_assoc();
$doctor_name = $doctor_info['name'];
$photo_path = $doctor_info['photo_path'];
$info_stmt->close();

// ✅ Step 3: Delete photo file
$photo_deleted = false;
if(!empty($photo_path)) {
    if(file_exists($photo_path)) {
        $photo_deleted = unlink($photo_path);
    }
}

// ✅ Step 4: Delete doctor record
$sql = "DELETE FROM doctors WHERE doctor_id = ?";
$stmt = $conn->prepare($sql);
if(!$stmt) {
    header("Location: dashboard.php?error=Delete+prepare+failed");
    exit();
}

$stmt->bind_param("i", $doctor_id);
$deleted = $stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

if($deleted && $affected > 0) {
    $msg = "Doctor '$doctor_name' deleted";
    if($photo_deleted) $msg .= " + photo";
    header("Location: dashboard.php?success=" . urlencode($msg));
} else {
    header("Location: dashboard.php?error=Delete+failed+-+no+rows+affected");
}
exit();
?>