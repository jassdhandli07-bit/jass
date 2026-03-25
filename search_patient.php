<?php
session_start();
if(!isset($_SESSION['user'])) { header("Location: index.php"); exit(); }
include 'db_connect.php';

$patients = [];
$error    = "";
$searched = false;

if(isset($_POST['search'])) {
    $searched = true;
    $query = trim($_POST['query'] ?? '');

    if(empty($query)) {
        $error = "Please enter a name or patient ID to search.";
    } else {
        $sql = "SELECT p.*, d.name as assigned_doctor_name, d.specialization
                FROM patients p
                LEFT JOIN doctors d ON p.assigned_doctor_id = d.doctor_id
                WHERE p.patient_id = ? OR p.name LIKE ?";
        $like = "%" . $query . "%";
        $id   = is_numeric($query) ? (int)$query : 0;
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $id, $like);
        $stmt->execute();
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()) {
            $patients[] = $row;
        }
        $stmt->close();
        if(empty($patients)) {
            $error = "No patient found matching \"" . htmlspecialchars($query) . "\".";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Patient</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<div class="navbar">
    <span class="navbar-brand"><i class="fas fa-hospital"></i> Hospital MS</span>
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="add_doctor.php"><i class="fas fa-user-md"></i> Add Doctor</a>
    <a href="add_patient.php"><i class="fas fa-user-injured"></i> Add Patient</a>
    <a href="search_patient.php"><i class="fas fa-search"></i> Search</a>
    <a href="upload_doctor_photo.php"><i class="fas fa-camera"></i> Upload Photo</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="container">
    <h1><i class="fas fa-search"></i> Search Patient</h1>

    <form method="POST" class="search-form">
        <input type="text" name="query" placeholder="Enter patient name or ID..."
               value="<?php echo htmlspecialchars($_POST['query'] ?? ''); ?>" required>
        <button type="submit" name="search" class="btn-primary">
            <i class="fas fa-search"></i> Search
        </button>
    </form>

    <?php if($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <?php if($searched && !empty($patients)): ?>
        <p class="result-count"><i class="fas fa-check-circle"></i> <?php echo count($patients); ?> result(s) found.</p>
        <div class="grid-container">
        <?php foreach($patients as $p): ?>
            <div class="card">
                <h3><i class="fas fa-user"></i> <?php echo htmlspecialchars($p['name']); ?></h3>
                <span class="patient-id-badge">ID: #<?php echo $p['patient_id']; ?></span>
                <p><i class="fas fa-birthday-cake"></i> <?php echo $p['age']; ?> yrs &nbsp;|&nbsp; <i class="fas fa-venus-mars"></i> <?php echo htmlspecialchars($p['gender']); ?></p>
                <p><i class="fas fa-virus"></i> <?php echo htmlspecialchars($p['disease'] ?? 'N/A'); ?></p>
                <p><i class="fas fa-calendar-check"></i> Admitted: <?php echo $p['admission_date'] ?? 'N/A'; ?></p>
                <p>
                    <i class="fas fa-calendar-times" style="color:<?php echo $p['discharge_date'] ? '#10b981' : '#ef4444'; ?>"></i>
                    <?php if($p['discharge_date']): ?>
                        <span style="color:#10b981;font-weight:600;">Discharged: <?php echo $p['discharge_date']; ?></span>
                    <?php else: ?>
                        <span style="color:#ef4444;font-weight:600;">Not Discharged</span>
                    <?php endif; ?>
                </p>
                <p><i class="fas fa-user-md"></i> <?php echo $p['assigned_doctor_name'] ? htmlspecialchars($p['assigned_doctor_name']) : 'Unassigned'; ?></p>
                <p><i class="fas fa-notes-medical"></i> <?php echo htmlspecialchars($p['treatment'] ?? 'N/A'); ?></p>
                <div class="card-actions">
                    <a class="btn-edit" href="edit_patient.php?id=<?php echo $p['patient_id']; ?>"><i class="fas fa-edit"></i> Edit</a>
                    <a class="btn-bill" href="patient_bill.php?id=<?php echo $p['patient_id']; ?>"><i class="fas fa-file-invoice-dollar"></i> Bill</a>
                    <a class="btn-report" href="patient_reports.php?id=<?php echo $p['patient_id']; ?>"><i class="fas fa-file-medical"></i> Reports</a>
                    <a class="btn-delete" href="delete_patient.php?id=<?php echo $p['patient_id']; ?>"
                       onclick="return confirm('Delete this patient?')"><i class="fas fa-trash"></i> Delete</a>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
