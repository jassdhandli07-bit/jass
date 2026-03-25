<?php
session_start();
if(!isset($_SESSION['user'])) { header("Location: index.php"); exit(); }
include 'db_connect.php';

if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php?error=Invalid+patient+ID");
    exit();
}
$patient_id = (int)$_GET['id'];

// Verify patient exists
$stmt = $conn->prepare("SELECT name FROM patients WHERE patient_id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
if($result->num_rows == 0) {
    header("Location: dashboard.php?error=Patient+not+found");
    exit();
}
$patient_name = $result->fetch_assoc()['name'];
$stmt->close();

// Free the room if assigned
$room_stmt = $conn->prepare("SELECT room_id FROM patients WHERE patient_id = ?");
$room_stmt->bind_param("i", $patient_id);
$room_stmt->execute();
$room_row = $room_stmt->get_result()->fetch_assoc();
$room_stmt->close();
if(!empty($room_row['room_id'])) {
    $conn->query("UPDATE rooms SET status='Available' WHERE room_id=" . (int)$room_row['room_id']);
}

// Delete patient
$stmt = $conn->prepare("DELETE FROM patients WHERE patient_id = ? LIMIT 1");
$stmt->bind_param("i", $patient_id);
$deleted = $stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

if($deleted && $affected > 0) {
    header("Location: dashboard.php?success=" . urlencode("Patient '$patient_name' deleted successfully"));
} else {
    header("Location: dashboard.php?error=Failed+to+delete+patient");
}
exit();
?>
