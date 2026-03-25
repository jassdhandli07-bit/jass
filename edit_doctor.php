<?php
session_start();
if(!isset($_SESSION['user'])) { header("Location: index.php"); exit(); }
include 'db_connect.php';

$success = "";
$error = "";

if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php?error=Invalid+doctor+ID");
    exit();
}
$doctor_id = (int)$_GET['id'];

// Fetch existing doctor
$stmt = $conn->prepare("SELECT * FROM doctors WHERE doctor_id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
if($result->num_rows == 0) {
    header("Location: dashboard.php?error=Doctor+not+found");
    exit();
}
$doctor = $result->fetch_assoc();
$stmt->close();

if(isset($_POST['update_doctor'])) {
    $name           = trim($_POST['name']);
    $specialization = trim($_POST['specialization']);
    $license_no     = trim($_POST['license_no']);
    $contact        = trim($_POST['contact']);

    if(empty($name) || empty($specialization) || empty($license_no) || empty($contact)) {
        $error = "All fields are required!";
    } else {
        // Handle photo upload
        $photo_path = $doctor['photo_path'];
        if(isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if(in_array($ext, ['jpg','jpeg','png','gif']) && $_FILES['photo']['size'] <= 2000000) {
                $new_path = "images/" . uniqid() . "_" . time() . "." . $ext;
                if(move_uploaded_file($_FILES['photo']['tmp_name'], $new_path)) {
                    // Delete old photo if not default
                    if($photo_path && $photo_path != 'images/ram.jpg' && file_exists($photo_path)) {
                        unlink($photo_path);
                    }
                    $photo_path = $new_path;
                }
            } else {
                $error = "Invalid image. Use JPG/PNG/GIF under 2MB.";
            }
        }

        if(empty($error)) {
            $stmt = $conn->prepare("UPDATE doctors SET name=?, specialization=?, license_no=?, contact=?, photo_path=? WHERE doctor_id=?");
            $stmt->bind_param("sssssi", $name, $specialization, $license_no, $contact, $photo_path, $doctor_id);
            if($stmt->execute()) {
                $success = "Doctor updated successfully!";
                $doctor = array_merge($doctor, compact('name','specialization','license_no','contact','photo_path'));
            } else {
                $error = "Update failed: " . $conn->error;
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Doctor</title>
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
    <h1><i class="fas fa-user-edit"></i> Edit Doctor</h1>

    <?php if($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="form-container">
        <div class="form-row">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Doctor Name *</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($doctor['name']); ?>" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-stethoscope"></i> Specialization *</label>
                <input type="text" name="specialization" value="<?php echo htmlspecialchars($doctor['specialization']); ?>" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label><i class="fas fa-id-card"></i> License Number *</label>
                <input type="text" name="license_no" value="<?php echo htmlspecialchars($doctor['license_no']); ?>" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-phone"></i> Contact *</label>
                <input type="text" name="contact" value="<?php echo htmlspecialchars($doctor['contact']); ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label><i class="fas fa-camera"></i> Update Photo (optional)</label>
            <div class="photo-upload-box">
                <img src="<?php echo htmlspecialchars($doctor['photo_path'] ?: 'images/ram.jpg'); ?>" 
                     class="doctor-img" id="photoPreview" alt="Doctor Photo">
                <input type="file" name="photo" accept="image/*" id="photoInput">
                <small>JPG/PNG/GIF, max 2MB. Leave empty to keep current photo.</small>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" name="update_doctor" class="btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
            <a href="dashboard.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script>
document.getElementById('photoInput').addEventListener('change', function() {
    const file = this.files[0];
    if(file) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('photoPreview').src = e.target.result;
        reader.readAsDataURL(file);
    }
});
</script>
</body>
</html>
