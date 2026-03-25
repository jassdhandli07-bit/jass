<?php
session_start();
if(!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

include 'db_connect.php';

/* Total Doctors */
$doctor_count = $conn->query("SELECT COUNT(*) as total FROM doctors")->fetch_assoc()['total'];

/* Total Patients */
$patient_count = $conn->query("SELECT COUNT(*) as total FROM patients")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html>
<head>
<title>Hospital Dashboard</title>

<style>

body{
font-family: Arial;
margin:0;
background:#f4f6f9;
}

.navbar{
background:#2c3e50;
padding:15px;
}

.navbar a{
color:white;
margin-right:20px;
text-decoration:none;
font-weight:bold;
}

.container{
width:90%;
margin:auto;
margin-top:20px;
}

.stats{
display:flex;
gap:20px;
margin-bottom:30px;
}

.box{
flex:1;
background:white;
padding:20px;
border-radius:8px;
box-shadow:0 0 5px rgba(0,0,0,0.2);
text-align:center;
}

.grid-container{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
gap:20px;
}

.card{
background:white;
padding:15px;
border-radius:8px;
box-shadow:0 0 5px rgba(0,0,0,0.2);
}

.doctor-img{
width:100%;
height:200px;
object-fit:cover;
border-radius:5px;
}

.btn{
display:inline-block;
padding:6px 10px;
margin-top:8px;
font-size:14px;
text-decoration:none;
border-radius:4px;
}

.edit{
background:#3498db;
color:white;
}

.delete{
background:#e74c3c;
color:white;
}

.section-title{
margin-top:40px;
}

</style>
</head>

<body>

<div class="navbar">
<a href="dashboard.php">Dashboard</a>
<a href="add_doctor.php">Add Doctor</a>
<a href="add_patient.php">Add Patient</a>
<a href="logout.php">Logout</a>
</div>

<div class="container">

<h1>Hospital Management System</h1>

<!-- STATS -->
<div class="stats">

<div class="box">
<h2><?php echo $doctor_count; ?></h2>
<p>Total Doctors</p>
</div>

<div class="box">
<h2><?php echo $patient_count; ?></h2>
<p>Total Patients</p>
</div>

</div>


<!-- DOCTORS -->
<h2 class="section-title">Medical Staff</h2>

<div class="grid-container">

<?php

$result = $conn->query("SELECT * FROM doctors");

if($result->num_rows>0){

while($row=$result->fetch_assoc()){

$photo = !empty($row['photo_path']) ? $row['photo_path'] : "ram.jpg";

echo "<div class='card'>";

echo "<img src='$photo' class='doctor-img'>";

echo "<h3>".$row['name']."</h3>";
echo "<p><b>Specialization:</b> ".$row['specialization']."</p>";
echo "<p><b>License:</b> ".$row['license_no']."</p>";
echo "<p><b>Contact:</b> ".$row['contact']."</p>";

echo "<a class='btn edit' href='edit_doctor.php?id=".$row['doctor_id']."'>Edit</a>";
echo "<a class='btn delete' href='delete_doctor.php?id=".$row['doctor_id']."'>Delete</a>";

echo "</div>";

}

}else{

echo "No doctors found";

}

?>

</div>



<!-- PATIENTS -->
<h2 class="section-title">Admitted Patients</h2>

<div class="grid-container">

<?php

$sql="SELECT p.*, d.name as doctor_name
FROM patients p
LEFT JOIN doctors d
ON p.assigned_doctor_id=d.doctor_id";

$result=$conn->query($sql);

if($result->num_rows>0){

while($row=$result->fetch_assoc()){

$doctor = $row['doctor_name'] ? $row['doctor_name'] : "Unassigned";

echo "<div class='card'>";

echo "<h3>".$row['name']."</h3>";
echo "<p><b>Age/Gender:</b> ".$row['age']." / ".$row['gender']."</p>";
echo "<p><b>Disease:</b> ".$row['disease']."</p>";
echo "<p><b>Admission Date:</b> ".$row['admission_date']."</p>";
echo "<p><b>Doctor:</b> ".$doctor."</p>";
echo "<p><b>Treatment:</b> ".$row['treatment']."</p>";

echo "<a class='btn edit' href='edit_patient.php?id=".$row['patient_id']."'>Edit</a>";
echo "<a class='btn delete' href='delete_patient.php?id=".$row['patient_id']."'>Delete</a>";

echo "</div>";

}

}else{

echo "No patients found";

}

?>

</div>

</div>

</body>
</html>
