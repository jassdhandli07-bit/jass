<?php
session_start();
if(!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';

$success = "";
$error = "";
$selected_doctor = null;

if(isset($_POST['upload'])) {
    $doctor_id = $_POST['doctor_id'];
    $target_dir = "images/";
    
    // ✅ Generate unique filename to prevent conflicts
    $file_extension = pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION);
    $target_file = $target_dir . uniqid() . "_" . time() . "." . $file_extension;
    $imageFileType = strtolower($file_extension);
    
    // ✅ File size limit (2MB)
    $max_size = 2000000;
    
    // ✅ Validate image
    $check = getimagesize($_FILES["photo"]["tmp_name"]);
    if($check !== false) {
        // ✅ Check file size
        if($_FILES["photo"]["size"] <= $max_size) {
            // ✅ Check file type
            if(in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
                // ✅ Move uploaded file
                if(move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
                    // ✅ Update database
                    $sql = "UPDATE doctors SET photo_path = ? WHERE doctor_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("si", $target_file, $doctor_id);
                    
                    if($stmt->execute()) {
                        $success = "✅ Photo uploaded successfully!<br>File: " . basename($target_file);
                    } else {
                        $error = "❌ Database error: " . $conn->error;
                    }
                    $stmt->close();
                } else {
                    $error = "❌ Upload failed. Check folder permissions.";
                }
            } else {
                $error = "❌ Only JPG, JPEG, PNG, GIF allowed.";
            }
        } else {
            $error = "❌ File too large (max 2MB).";
        }
    } else {
        $error = "❌ Invalid image file.";
    }
}

// ✅ Get selected doctor info for preview
if(isset($_POST['doctor_id'])) {
    $doctor_id = $_POST['doctor_id'];
    $sql = "SELECT name FROM doctors WHERE doctor_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if($result->num_rows > 0) {
        $selected_doctor = $result->fetch_assoc();
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload Doctor Photo</title>
    <link rel="stylesheet" href="style.css">
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
        <h1>📸 Upload Doctor Photo</h1>
        
        <?php if($success): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 20px 0; border: 1px solid #c3e6cb;">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 20px 0; border: 1px solid #f5c6cb;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="login-box" style="max-width: 600px; margin: 50px auto;">
            <form method="POST" action="" enctype="multipart/form-data">
                <!-- ✅ Doctor Dropdown -->
                <label><strong>Select Doctor:</strong></label>
                <select name="doctor_id" required>
                    <option value="">Choose Doctor...</option>
                    <?php
                    $sql = "SELECT doctor_id, name FROM doctors ORDER BY name";
                    $result = $conn->query($sql);
                    while($row = $result->fetch_assoc()) {
                        $selected = (isset($selected_doctor) && $selected_doctor['doctor_id'] == $row['doctor_id']) ? 'selected' : '';
                        echo "<option value='{$row['doctor_id']}' $selected>{$row['name']} (ID: {$row['doctor_id']})</option>";
                    }
                    ?>
                </select>

                <!-- ✅ File Upload -->
                <br><br>
                <label><strong>Select Photo:</strong></label>
                <input type="file" name="photo" accept="image/*" required>
                <small style="color: #666;">Max 2MB. JPG, PNG, GIF only.</small>

                <!-- ✅ Preview Selected Doctor -->
                <?php if($selected_doctor): ?>
                    <div style="background: #e8f4f8; padding: 15px; border-radius: 8px; margin: 15px 0;">
                        <strong>Selected:</strong> <?php echo htmlspecialchars($selected_doctor['name']); ?>
                    </div>
                <?php endif; ?>

                <!-- ✅ Upload Button -->
                <button type="submit" name="upload">🚀 Upload Photo</button>
            </form>
        </div>

        <!-- ✅ Instructions -->
        <div style="max-width: 600px; margin: 30px auto; padding: 20px; background: rgba(255,255,255,0.9); border-radius: 10px;">
            <h3>📋 Instructions:</h3>
            <ul style="color: #555;">
                <li>1. Select doctor from dropdown</li>
                <li>2. Choose photo file (JPG/PNG/GIF, max 2MB)</li>
                <li>3. Click "Upload Photo"</li>
                <li>4. Go to Dashboard to see photo</li>
            </ul>
        </div>
    </div>

    <script>
        // ✅ Preview selected image
        document.querySelector('input[type="file"]').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if(file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Add preview image
                    let preview = document.querySelector('.image-preview');
                    if(!preview) {
                        preview = document.createElement('div');
                        preview.className = 'image-preview';
                        preview.innerHTML = '<label><strong>Preview:</strong></label><br>';
                        e.target.parentNode.insertAdjacentElement('afterend', preview);
                    }
                    preview.innerHTML += '<img src="' + e.target.result + '" style="max-width:200px; max-height:200px; border-radius:10px; margin-top:10px;">';
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>