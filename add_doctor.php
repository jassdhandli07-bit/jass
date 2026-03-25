<?php
session_start();
if(!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';

$success = "";
$error = "";

if(isset($_POST['add_doctor'])) {
    $name = trim($_POST['name']);
    $specialization = trim($_POST['specialization']);
    $license_no = trim($_POST['license_no']);
    $contact = trim($_POST['contact']);
    $password = trim($_POST['password']);
    
    // ✅ Handle photo upload
    $photo_path = 'images/ram.jpg'; // Default
    if(isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $target_dir = "images/";
        $file_extension = pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION);
        $target_file = $target_dir . uniqid() . "_" . time() . "." . $file_extension;
        
        // ✅ Validate image
        $check = getimagesize($_FILES["photo"]["tmp_name"]);
        if($check !== false && $_FILES["photo"]["size"] <= 2000000) {
            if(move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
                $photo_path = $target_file;
            }
        }
    } else {
        $photo_path = $_POST['photo_path'] ?: 'images/ram.jpg';
    }
    
    // ✅ Validate input
    if(empty($name) || empty($specialization) || empty($license_no) || empty($contact) || empty($password)) {
        $error = "All fields are required!";
    } else {
        $sql = "INSERT INTO doctors (name, specialization, license_no, contact, password, photo_path) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $name, $specialization, $license_no, $contact, $password, $photo_path);
        
        if($stmt->execute()) {
            $success = "✅ Doctor added successfully!<br>ID: " . $conn->insert_id . "<br>Photo: " . basename($photo_path);
        } else {
            $error = "❌ Error: " . $conn->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Doctor</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        <h1>👨‍⚕️ Add New Doctor</h1>
        
        <?php if($success): ?>
            <div style="background: #d4edda; color: #155724; padding: 20px; border-radius: 10px; margin: 20px 0; border: 1px solid #c3e6cb;">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 20px; border-radius: 10px; margin: 20px 0; border: 1px solid #f5c6cb;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="login-box" style="max-width: 700px; margin: 50px auto;">
            <form method="POST" action="" enctype="multipart/form-data">
                <!-- Basic Info -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <input type="text" name="name" placeholder="Doctor Name *" required>
                    </div>
                    <div>
                        <input type="text" name="specialization" placeholder="Specialization *" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
                    <div>
                        <input type="text" name="license_no" placeholder="License Number *" required>
                    </div>
                    <div>
                        <input type="text" name="contact" placeholder="Contact Number *" required>
                    </div>
                </div>

                <input type="password" name="password" placeholder="Password *" required>

                <!-- ✅ Photo Upload Section -->
                <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0;">
                    <label><strong>📸 Doctor Photo</strong></label>
                    <input type="file" name="photo" accept="image/*">
                    <small style="color: #666;">JPG/PNG/GIF (max 2MB) or use default</small>
                    <br>
                    <input type="text" name="photo_path" placeholder="Or enter path (e.g., images/ram.jpg)" value="images/ram.jpg">
                </div>

                <div style="display: flex; gap: 15px;">
                    <button type="submit" name="add_doctor" style="flex: 1;">🚀 Add Doctor</button>
                    <a href="dashboard.php" style="flex: 1; padding: 15px; background: #95a5a6; color: white; text-align: center; text-decoration: none; border-radius: 8px;">Cancel</a>
                </div>
            </form>
        </div>

        <!-- ✅ Instructions -->
        <div style="max-width: 700px; margin: 30px auto; padding: 25px; background: rgba(255,255,255,0.9); border-radius: 15px; text-align: center;">
            <h3>📋 Quick Guide:</h3>
            <ul style="text-align: left; max-width: 500px; margin: 0 auto; color: #555;">
                <li>✅ Fill all required fields (*)</li>
                <li>✅ Upload photo OR use default path</li>
                <li>✅ Click "Add Doctor"</li>
                <li>✅ Check Dashboard to see new doctor</li>
            </ul>
        </div>
    </div>

    <script>
        // ✅ Photo preview
        document.querySelector('input[name="photo"]').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if(file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    let preview = document.querySelector('.photo-preview');
                    if(!preview) {
                        preview = document.createElement('div');
                        preview.className = 'photo-preview';
                        preview.style.cssText = 'margin: 10px 0; padding: 15px; background: #e8f4f8; border-radius: 10px;';
                        preview.innerHTML = '<strong>📷 Photo Preview:</strong><br>';
                        e.target.parentNode.parentNode.appendChild(preview);
                    }
                    preview.innerHTML = '<strong>📷 Photo Preview:</strong><br><img src="' + e.target.result + '" style="max-width:150px; max-height:150px; border-radius:10px; border: 3px solid #3498db;">';
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>