<?php
session_start();
if(!isset($_SESSION['user'])) { header("Location: index.php"); exit(); }
include 'db_connect.php';

$error = "";

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name               = trim($_POST['name'] ?? '');
    $age                = (int)($_POST['age'] ?? 0);
    $gender             = trim($_POST['gender'] ?? '');
    $disease            = trim($_POST['disease'] ?? '');
    $treatment          = trim($_POST['treatment'] ?? '');
    $admission_date     = trim($_POST['admission_date'] ?? date('Y-m-d'));
    $assigned_doctor_id = !empty($_POST['assigned_doctor_id']) ? (int)$_POST['assigned_doctor_id'] : null;
    $room_id            = !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null;

    if(empty($name)) {
        $error = "Patient name is required!";
    } elseif($age < 0 || $age > 120) {
        $error = "Age must be between 0 and 120!";
    } elseif(!in_array($gender, ['Male','Female','Other'])) {
        $error = "Please select a valid gender!";
    } else {
        // Build query dynamically based on optional fields
        $fields = "name, age, gender, disease, treatment, admission_date";
        $placeholders = "?, ?, ?, ?, ?, ?";
        $types = "sissss";
        $values = [$name, $age, $gender, $disease, $treatment, $admission_date];

        if($assigned_doctor_id) {
            $fields .= ", assigned_doctor_id";
            $placeholders .= ", ?";
            $types .= "i";
            $values[] = $assigned_doctor_id;
        }
        if($room_id) {
            $fields .= ", room_id";
            $placeholders .= ", ?";
            $types .= "i";
            $values[] = $room_id;
        }

        $stmt = $conn->prepare("INSERT INTO patients ($fields) VALUES ($placeholders)");
        $stmt->bind_param($types, ...$values);

        if($stmt->execute()) {
            // Mark room as occupied
            if($room_id) {
                $conn->query("UPDATE rooms SET status='Occupied' WHERE room_id=$room_id");
            }
            $stmt->close();
            header("Location: add_patient.php?success=1&name=" . urlencode($name));
            exit();
        } else {
            $error = "Failed to add patient: " . $conn->error;
            $stmt->close();
        }
    }
}

$doctors      = $conn->query("SELECT doctor_id, name FROM doctors ORDER BY name ASC");
$avail_rooms  = $conn->query("SELECT * FROM rooms WHERE status='Available' ORDER BY room_type, room_number");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Patient</title>
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
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="container">
    <h1><i class="fas fa-user-injured"></i> Add New Patient</h1>

    <?php if(isset($_GET['success'])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> Patient "<?php echo htmlspecialchars($_GET['name']); ?>" admitted successfully!</div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" class="form-container">
        <div class="form-group">
            <label><i class="fas fa-user"></i> Full Name *</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label><i class="fas fa-calendar"></i> Age *</label>
                <input type="number" name="age" min="0" max="120" value="<?php echo htmlspecialchars($_POST['age'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-venus-mars"></i> Gender *</label>
                <select name="gender" required>
                    <option value="">Select Gender</option>
                    <?php foreach(['Male','Female','Other'] as $g): ?>
                        <option value="<?php echo $g; ?>" <?php echo ($_POST['gender'] ?? '') == $g ? 'selected' : ''; ?>><?php echo $g; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label><i class="fas fa-virus"></i> Disease / Diagnosis</label>
                <input type="text" name="disease" value="<?php echo htmlspecialchars($_POST['disease'] ?? ''); ?>" placeholder="e.g. Diabetes, Hypertension">
            </div>
            <div class="form-group">
                <label><i class="fas fa-calendar-check"></i> Admission Date</label>
                <input type="date" name="admission_date" value="<?php echo htmlspecialchars($_POST['admission_date'] ?? date('Y-m-d')); ?>">
            </div>
        </div>

        <div class="form-group">
            <label><i class="fas fa-notes-medical"></i> Treatment / Condition</label>
            <textarea name="treatment" rows="3" placeholder="Enter treatment details..."><?php echo htmlspecialchars($_POST['treatment'] ?? ''); ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label><i class="fas fa-user-md"></i> Assigned Doctor</label>
                <select name="assigned_doctor_id">
                    <option value="">No Doctor Assigned</option>
                    <?php if($doctors && $doctors->num_rows > 0):
                        $doctors->data_seek(0);
                        while($doc = $doctors->fetch_assoc()): ?>
                        <option value="<?php echo $doc['doctor_id']; ?>"
                            <?php echo ($_POST['assigned_doctor_id'] ?? '') == $doc['doctor_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($doc['name']); ?>
                        </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-door-open"></i> Assign Room</label>
                <select name="room_id" id="room_select">
                    <option value="">No Room Assigned</option>
                    <?php if($avail_rooms && $avail_rooms->num_rows > 0):
                        while($room = $avail_rooms->fetch_assoc()): ?>
                        <option value="<?php echo $room['room_id']; ?>"
                            data-price="<?php echo $room['price_per_day']; ?>"
                            <?php echo ($_POST['room_id'] ?? '') == $room['room_id'] ? 'selected' : ''; ?>>
                            Room <?php echo $room['room_number']; ?> — <?php echo $room['room_type']; ?> (₹<?php echo number_format($room['price_per_day'],0); ?>/day)
                        </option>
                    <?php endwhile; else: ?>
                        <option disabled>No rooms available</option>
                    <?php endif; ?>
                </select>
                <small id="room_price_hint" style="color:var(--primary);font-weight:600;margin-top:4px;display:block;"></small>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary"><i class="fas fa-plus"></i> Admit Patient</button>
            <a href="dashboard.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </form>
</div>
<script>
document.getElementById('room_select').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const price = opt.dataset.price;
    const hint = document.getElementById('room_price_hint');
    hint.textContent = price ? '₹' + parseFloat(price).toLocaleString() + ' per day' : '';
});
</script>
</body>
</html>
