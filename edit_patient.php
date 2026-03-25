<?php
session_start();
if(!isset($_SESSION['user'])) { header("Location: index.php"); exit(); }
include 'db_connect.php';

$success = "";
$error = "";

if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php?error=Invalid+patient+ID");
    exit();
}
$patient_id = (int)$_GET['id'];

// Fetch existing patient
$stmt = $conn->prepare("SELECT * FROM patients WHERE patient_id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
if($result->num_rows == 0) {
    header("Location: dashboard.php?error=Patient+not+found");
    exit();
}
$patient = $result->fetch_assoc();
$stmt->close();

// Fetch doctors for dropdown
$doctors = $conn->query("SELECT doctor_id, name FROM doctors ORDER BY name ASC");

if(isset($_POST['update_patient'])) {
    $name               = trim($_POST['name']);
    $age                = (int)$_POST['age'];
    $gender             = trim($_POST['gender']);
    $disease            = trim($_POST['disease']);
    $treatment          = trim($_POST['treatment']);
    $admission_date     = trim($_POST['admission_date']);
    $discharge_date     = !empty($_POST['discharge_date']) ? trim($_POST['discharge_date']) : null;
    $assigned_doctor_id = !empty($_POST['assigned_doctor_id']) ? (int)$_POST['assigned_doctor_id'] : null;
    $new_room_id        = !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null;
    $old_room_id        = $patient['room_id'] ?? null;

    if(empty($name)) {
        $error = "Name is required!";
    } elseif($age < 0 || $age > 120) {
        $error = "Age must be between 0 and 120!";
    } elseif(!in_array($gender, ['Male','Female','Other'])) {
        $error = "Please select a valid gender!";
    } else {
        if($assigned_doctor_id) {
            $stmt = $conn->prepare("UPDATE patients SET name=?, age=?, gender=?, disease=?, treatment=?, admission_date=?, discharge_date=?, room_id=?, assigned_doctor_id=? WHERE patient_id=? LIMIT 1");
            $stmt->bind_param("sissssssii", $name, $age, $gender, $disease, $treatment, $admission_date, $discharge_date, $new_room_id, $assigned_doctor_id, $patient_id);
        } else {
            $stmt = $conn->prepare("UPDATE patients SET name=?, age=?, gender=?, disease=?, treatment=?, admission_date=?, discharge_date=?, room_id=?, assigned_doctor_id=NULL WHERE patient_id=? LIMIT 1");
            $stmt->bind_param("sissssssi", $name, $age, $gender, $disease, $treatment, $admission_date, $discharge_date, $new_room_id, $patient_id);
        }
        if($stmt->execute()) {
            // Update room statuses
            if($old_room_id && $old_room_id != $new_room_id) {
                $conn->query("UPDATE rooms SET status='Available' WHERE room_id=$old_room_id");
            }
            if($new_room_id) {
                $conn->query("UPDATE rooms SET status='Occupied' WHERE room_id=$new_room_id");
            }
            $success = "Patient updated successfully!";
            $patient = array_merge($patient, compact('name','age','gender','disease','treatment','admission_date','discharge_date','assigned_doctor_id'));
            $patient['room_id'] = $new_room_id;
        } else {
            $error = "Update failed: " . $conn->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Patient</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<div class="navbar">
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="add_doctor.php"><i class="fas fa-user-md"></i> Add Doctor</a>
    <a href="add_patient.php"><i class="fas fa-user-injured"></i> Add Patient</a>
    <a href="search_patient.php"><i class="fas fa-search"></i> Search</a>
    <a href="upload_doctor_photo.php"><i class="fas fa-camera"></i> Upload Photo</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="container">
    <h1><i class="fas fa-user-edit"></i> Edit Patient</h1>

    <?php if($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" action="edit_patient.php?id=<?php echo $patient_id; ?>" class="form-container">
        <div class="form-group">
            <label><i class="fas fa-user"></i> Full Name *</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($patient['name']); ?>" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label><i class="fas fa-calendar"></i> Age *</label>
                <input type="number" name="age" min="0" max="120" value="<?php echo $patient['age']; ?>" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-venus-mars"></i> Gender *</label>
                <select name="gender" required>
                    <option value="">Select Gender</option>
                    <?php foreach(['Male','Female','Other'] as $g): ?>
                        <option value="<?php echo $g; ?>" <?php echo $patient['gender'] == $g ? 'selected' : ''; ?>><?php echo $g; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label><i class="fas fa-virus"></i> Disease</label>
                <input type="text" name="disease" value="<?php echo htmlspecialchars($patient['disease'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label><i class="fas fa-calendar-check"></i> Admission Date</label>
                <input type="date" name="admission_date" value="<?php echo $patient['admission_date'] ?? ''; ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label><i class="fas fa-calendar-times"></i> Discharge Date</label>
                <input type="date" name="discharge_date" value="<?php echo $patient['discharge_date'] ?? ''; ?>">
            </div>
            <div class="form-group">
                <label><i class="fas fa-door-open"></i> Room</label>
                <select name="room_id">
                    <option value="">No Room</option>
                    <?php
                    $rooms_q = $conn->query("SELECT * FROM rooms WHERE status='Available' OR room_id=" . (int)($patient['room_id'] ?? 0) . " ORDER BY room_type, room_number");
                    while($rm = $rooms_q->fetch_assoc()):
                    ?>
                    <option value="<?php echo $rm['room_id']; ?>" <?php echo $patient['room_id'] == $rm['room_id'] ? 'selected' : ''; ?>>
                        Room <?php echo $rm['room_number']; ?> — <?php echo $rm['room_type']; ?> (₹<?php echo number_format($rm['price_per_day'],0); ?>/day)
                        <?php echo $rm['status']=='Occupied' && $rm['room_id']==$patient['room_id'] ? '[Current]' : ''; ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label><i class="fas fa-notes-medical"></i> Treatment / Condition</label>
            <textarea name="treatment" rows="3"><?php echo htmlspecialchars($patient['treatment'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label><i class="fas fa-user-md"></i> Assigned Doctor</label>
            <select name="assigned_doctor_id">
                <option value="">No Doctor Assigned</option>
                <?php $doctors->data_seek(0); while($doc = $doctors->fetch_assoc()): ?>
                    <option value="<?php echo $doc['doctor_id']; ?>"
                        <?php echo $patient['assigned_doctor_id'] == $doc['doctor_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($doc['name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" name="update_patient" class="btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
            <a href="dashboard.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancel
            </a>
        </div>
    </form>
</div>
</body>
</html>
