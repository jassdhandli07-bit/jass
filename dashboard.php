<?php
session_start();
if(!isset($_SESSION['user'])) { header("Location: index.php"); exit(); }
include 'db_connect.php';

$doctor_count    = $conn->query("SELECT COUNT(*) as c FROM doctors")->fetch_assoc()['c'];
$patient_count   = $conn->query("SELECT COUNT(*) as c FROM patients")->fetch_assoc()['c'];
$total_rooms     = $conn->query("SELECT COUNT(*) as c FROM rooms")->fetch_assoc()['c'];
$available_rooms = $conn->query("SELECT COUNT(*) as c FROM rooms WHERE status='Available'")->fetch_assoc()['c'];
$today_appts     = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE appointment_date=CURDATE() AND status='Scheduled'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<div class="navbar">
    <span class="navbar-brand"><i class="fas fa-hospital"></i> Hospital MS</span>
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="add_doctor.php"><i class="fas fa-user-md"></i> Add Doctor</a>
    <a href="add_patient.php"><i class="fas fa-user-injured"></i> Add Patient</a>
    <a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
    <a href="rooms.php"><i class="fas fa-door-open"></i> Rooms</a>
    <a href="search_patient.php"><i class="fas fa-search"></i> Search</a>
    <a href="upload_doctor_photo.php"><i class="fas fa-camera"></i> Upload Photo</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<?php if(isset($_GET['success'])): ?>
    <div class="flash flash-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?></div>
<?php endif; ?>
<?php if(isset($_GET['error'])): ?>
    <div class="flash flash-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?></div>
<?php endif; ?>

<div class="container">
    <h1><i class="fas fa-hospital-alt"></i> Hospital Management System</h1>

    <!-- Stats -->
    <div class="stats">
        <div class="stat-box">
            <i class="fas fa-user-md stat-icon"></i>
            <h2><?php echo $doctor_count; ?></h2>
            <p>Total Doctors</p>
        </div>
        <div class="stat-box">
            <i class="fas fa-procedures stat-icon"></i>
            <h2><?php echo $patient_count; ?></h2>
            <p>Total Patients</p>
        </div>
        <div class="stat-box">
            <i class="fas fa-door-open stat-icon"></i>
            <h2><?php echo $available_rooms; ?> / <?php echo $total_rooms; ?></h2>
            <p>Rooms Available</p>
        </div>
        <div class="stat-box">
            <i class="fas fa-calendar-day stat-icon"></i>
            <h2><?php echo $today_appts; ?></h2>
            <p>Today's Appointments</p>
        </div>
        <div class="stat-box">
            <i class="fas fa-calendar-day stat-icon"></i>
            <h2><?php echo date('d M'); ?></h2>
            <p><?php echo date('Y'); ?></p>
        </div>
    </div>

    <!-- Doctors -->
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-user-md"></i> Medical Staff</h2>
        <a href="add_doctor.php" class="btn-primary" style="text-decoration:none;padding:8px 18px;">
            <i class="fas fa-plus"></i> Add Doctor
        </a>
    </div>
    <div class="grid-container">
        <?php
        $result = $conn->query("SELECT * FROM doctors");
        if($result->num_rows > 0):
            while($row = $result->fetch_assoc()):
                $photo = !empty($row['photo_path']) && file_exists($row['photo_path']) ? $row['photo_path'] : 'images/ram.jpg';
        ?>
        <div class="card">
            <img src="<?php echo htmlspecialchars($photo); ?>" class="doctor-img" alt="Doctor Photo">
            <h3><?php echo htmlspecialchars($row['name']); ?></h3>
            <p><i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($row['specialization']); ?></p>
            <p><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($row['license_no']); ?></p>
            <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($row['contact']); ?></p>
            <div class="card-actions">
                <a class="btn-edit" href="edit_doctor.php?id=<?php echo $row['doctor_id']; ?>"><i class="fas fa-edit"></i> Edit</a>
                <a class="btn-delete" href="delete_doctor.php?id=<?php echo $row['doctor_id']; ?>"
                   onclick="return confirm('Delete Dr. <?php echo addslashes($row['name']); ?>?')">
                   <i class="fas fa-trash"></i> Delete
                </a>
            </div>
        </div>
        <?php endwhile; else: ?>
            <p class="empty-msg"><i class="fas fa-info-circle"></i> No doctors found. <a href="add_doctor.php">Add one now.</a></p>
        <?php endif; ?>
    </div>

    <!-- Patients -->
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-procedures"></i> Admitted Patients</h2>
        <a href="add_patient.php" class="btn-primary" style="text-decoration:none;padding:8px 18px;">
            <i class="fas fa-plus"></i> Add Patient
        </a>
    </div>
    <div class="grid-container">
        <?php
        $result = $conn->query("
            SELECT p.*, d.name as assigned_doctor_name,
                   r.room_number, r.room_type,
                   b.total_amount, b.payment_status
            FROM patients p
            LEFT JOIN doctors d  ON p.assigned_doctor_id = d.doctor_id
            LEFT JOIN rooms r    ON p.room_id = r.room_id
            LEFT JOIN bills b    ON b.patient_id = p.patient_id
        ");
        if($result->num_rows > 0):
            while($row = $result->fetch_assoc()):
        ?>
        <div class="card">
            <h3><i class="fas fa-user"></i> <?php echo htmlspecialchars($row['name']); ?></h3>
            <span class="patient-id-badge">ID: #<?php echo $row['patient_id']; ?></span>
            <p><i class="fas fa-birthday-cake"></i> <?php echo $row['age']; ?> yrs &nbsp;|&nbsp; <i class="fas fa-venus-mars"></i> <?php echo htmlspecialchars($row['gender']); ?></p>
            <p><i class="fas fa-virus"></i> <?php echo htmlspecialchars($row['disease'] ?? 'N/A'); ?></p>
            <p><i class="fas fa-calendar-check"></i> Admitted: <?php echo $row['admission_date'] ?? 'N/A'; ?></p>
            <p>
                <i class="fas fa-calendar-times" style="color:<?php echo $row['discharge_date'] ? '#10b981' : '#ef4444'; ?>"></i>
                <?php if($row['discharge_date']): ?>
                    <span style="color:#10b981;font-weight:600;">Discharged: <?php echo $row['discharge_date']; ?></span>
                <?php else: ?>
                    <span style="color:#ef4444;font-weight:600;">Not Discharged</span>
                <?php endif; ?>
            </p>
            <p><i class="fas fa-user-md"></i> <?php echo $row['assigned_doctor_name'] ? htmlspecialchars($row['assigned_doctor_name']) : 'Unassigned'; ?></p>
            <p><i class="fas fa-door-open"></i>
                <?php echo $row['room_number'] ? 'Room '.$row['room_number'].' ('.$row['room_type'].')' : 'No Room'; ?>
            </p>
            <?php if($row['total_amount']): ?>
            <p><i class="fas fa-rupee-sign"></i>
                ₹<?php echo number_format($row['total_amount'],2); ?>
                <span class="bill-inline-badge <?php echo $row['payment_status']=='Paid' ? 'bill-paid' : 'bill-pending'; ?>">
                    <?php echo $row['payment_status']; ?>
                </span>
            </p>
            <?php endif; ?>
            <div class="card-actions">
                <a class="btn-edit" href="edit_patient.php?id=<?php echo $row['patient_id']; ?>"><i class="fas fa-edit"></i> Edit</a>
                <a class="btn-bill" href="patient_bill.php?id=<?php echo $row['patient_id']; ?>"><i class="fas fa-file-invoice-dollar"></i> Bill</a>
                <a class="btn-report" href="patient_reports.php?id=<?php echo $row['patient_id']; ?>"><i class="fas fa-file-medical"></i> Reports</a>
                <a class="btn-record" href="medical_record.php?id=<?php echo $row['patient_id']; ?>"><i class="fas fa-notes-medical"></i> Record</a>
                <a class="btn-delete" href="delete_patient.php?id=<?php echo $row['patient_id']; ?>"
                   onclick="return confirm('Delete patient <?php echo addslashes($row['name']); ?>?')">
                   <i class="fas fa-trash"></i>
                </a>
            </div>
        </div>
        <?php endwhile; else: ?>
            <p class="empty-msg"><i class="fas fa-info-circle"></i> No patients found. <a href="add_patient.php">Add one now.</a></p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
