<?php
session_start();
if(!isset($_SESSION['user'])) { header("Location: index.php"); exit(); }
include 'db_connect.php';

$total_rooms     = $conn->query("SELECT COUNT(*) as c FROM rooms")->fetch_assoc()['c'];
$occupied_rooms  = $conn->query("SELECT COUNT(*) as c FROM rooms WHERE status='Occupied'")->fetch_assoc()['c'];
$available_rooms = $total_rooms - $occupied_rooms;

$rooms = $conn->query("
    SELECT r.*, p.name as patient_name, p.patient_id
    FROM rooms r
    LEFT JOIN patients p ON p.room_id = r.room_id
    ORDER BY r.room_type, r.room_number
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Management</title>
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
    <h1><i class="fas fa-door-open"></i> Room Management</h1>

    <!-- Room Stats -->
    <div class="stats">
        <div class="stat-box">
            <i class="fas fa-building stat-icon"></i>
            <h2><?php echo $total_rooms; ?></h2>
            <p>Total Rooms</p>
        </div>
        <div class="stat-box" style="--accent-color:#10b981;">
            <i class="fas fa-door-open stat-icon" style="background:linear-gradient(135deg,#10b981,#06b6d4);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"></i>
            <h2 style="color:#10b981;"><?php echo $available_rooms; ?></h2>
            <p>Available</p>
        </div>
        <div class="stat-box">
            <i class="fas fa-bed stat-icon" style="background:linear-gradient(135deg,#ef4444,#f59e0b);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"></i>
            <h2 style="color:#ef4444;"><?php echo $occupied_rooms; ?></h2>
            <p>Occupied</p>
        </div>
        <div class="stat-box">
            <i class="fas fa-chart-pie stat-icon"></i>
            <h2><?php echo $total_rooms > 0 ? round(($occupied_rooms/$total_rooms)*100) : 0; ?>%</h2>
            <p>Occupancy Rate</p>
        </div>
    </div>

    <!-- Room Grid -->
    <?php
    $types = ['General','Private','ICU','Emergency'];
    foreach($types as $type):
        $conn->data_seek ?? null;
        $rooms->data_seek(0);
        $has = false;
        while($r = $rooms->fetch_assoc()) { if($r['room_type'] == $type) { $has = true; break; } }
        if(!$has) continue;
        $rooms->data_seek(0);
    ?>
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-door-closed"></i> <?php echo $type; ?> Rooms</h2>
    </div>
    <div class="grid-container">
        <?php while($room = $rooms->fetch_assoc()):
            if($room['room_type'] != $type) continue;
            $occupied = $room['status'] == 'Occupied';
        ?>
        <div class="room-card <?php echo $occupied ? 'room-occupied' : 'room-available'; ?>">
            <div class="room-number">
                <i class="fas fa-door-<?php echo $occupied ? 'closed' : 'open'; ?>"></i>
                Room <?php echo htmlspecialchars($room['room_number']); ?>
            </div>
            <span class="room-badge <?php echo $occupied ? 'badge-occupied' : 'badge-available'; ?>">
                <?php echo $occupied ? 'Occupied' : 'Available'; ?>
            </span>
            <p class="room-type-label"><?php echo $room['room_type']; ?></p>
            <p class="room-price"><i class="fas fa-rupee-sign"></i> <?php echo number_format($room['price_per_day'],0); ?> / day</p>
            <?php if($occupied && $room['patient_name']): ?>
                <p class="room-patient"><i class="fas fa-user"></i> <?php echo htmlspecialchars($room['patient_name']); ?></p>
                <a href="patient_bill.php?id=<?php echo $room['patient_id']; ?>" class="btn-bill">
                    <i class="fas fa-file-invoice-dollar"></i> View Bill
                </a>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endforeach; ?>
</div>
</body>
</html>
