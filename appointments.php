<?php
session_start();
if(!isset($_SESSION['user'])) { header("Location: index.php"); exit(); }
include 'db_connect.php';

$success = ""; $error = "";

// ── DELETE ──────────────────────────────────────────────────────
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $s = $conn->prepare("DELETE FROM appointments WHERE appointment_id=? LIMIT 1");
    $s->bind_param("i", $did); $s->execute(); $s->close();
    header("Location: appointments.php?success=Appointment+deleted"); exit();
}

// ── STATUS CHANGE ────────────────────────────────────────────────
if(isset($_GET['status']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $sid    = (int)$_GET['id'];
    $status = in_array($_GET['status'], ['Scheduled','Completed','Cancelled']) ? $_GET['status'] : 'Scheduled';
    $s = $conn->prepare("UPDATE appointments SET status=? WHERE appointment_id=? LIMIT 1");
    $s->bind_param("si", $status, $sid); $s->execute(); $s->close();
    header("Location: appointments.php?success=Status+updated"); exit();
}

// ── ADD APPOINTMENT (PRG) ────────────────────────────────────────
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_appointment'])) {
    $pname  = trim($_POST['patient_name']);
    $pphone = trim($_POST['patient_phone']);
    $page   = (int)$_POST['patient_age'];
    $pgender= $_POST['patient_gender'];
    $doc_id = (int)$_POST['doctor_id'];
    $adate  = trim($_POST['appointment_date']);
    $atime  = trim($_POST['appointment_time']);
    $reason = trim($_POST['reason']);
    $notes  = trim($_POST['notes']);

    if(empty($pname) || empty($pphone) || empty($adate) || empty($atime) || $doc_id < 1) {
        $error = "Patient name, phone, doctor, date and time are required.";
    } else {
        $s = $conn->prepare("INSERT INTO appointments (patient_name,patient_phone,patient_age,patient_gender,doctor_id,appointment_date,appointment_time,reason,notes) VALUES (?,?,?,?,?,?,?,?,?)");
        $s->bind_param("ssisissss", $pname,$pphone,$page,$pgender,$doc_id,$adate,$atime,$reason,$notes);
        if($s->execute()) {
            $s->close();
            header("Location: appointments.php?success=Appointment+booked+successfully"); exit();
        } else {
            $error = "Error: ".$conn->error;
        }
        $s->close();
    }
}

// ── FETCH DOCTORS ────────────────────────────────────────────────
$doctors = $conn->query("SELECT doctor_id, name, specialization FROM doctors ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// ── FILTERS ──────────────────────────────────────────────────────
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$filter_doctor = isset($_GET['filter_doctor']) && is_numeric($_GET['filter_doctor']) ? (int)$_GET['filter_doctor'] : 0;
$filter_date   = isset($_GET['filter_date']) ? trim($_GET['filter_date']) : '';

$where = ["1=1"];
$params = []; $types = "";
if($filter_status) { $where[] = "a.status=?"; $params[] = $filter_status; $types .= "s"; }
if($filter_doctor) { $where[] = "a.doctor_id=?"; $params[] = $filter_doctor; $types .= "i"; }
if($filter_date)   { $where[] = "a.appointment_date=?"; $params[] = $filter_date; $types .= "s"; }

$sql = "SELECT a.*, d.name as doctor_name, d.specialization FROM appointments a
        LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
        WHERE ".implode(" AND ",$where)."
        ORDER BY a.appointment_date ASC, a.appointment_time ASC";

$stmt = $conn->prepare($sql);
if($params) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Stats
$total       = $conn->query("SELECT COUNT(*) as c FROM appointments")->fetch_assoc()['c'];
$scheduled   = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE status='Scheduled'")->fetch_assoc()['c'];
$completed   = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE status='Completed'")->fetch_assoc()['c'];
$cancelled   = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE status='Cancelled'")->fetch_assoc()['c'];
$today_count = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE appointment_date=CURDATE() AND status='Scheduled'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Appointments — Hospital MS</title>
<link rel="stylesheet" href="style.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
.appt-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:32px;}
.appt-stat{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;text-align:center;box-shadow:var(--shadow-sm);position:relative;overflow:hidden;}
.appt-stat::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;}
.appt-stat.blue::before{background:var(--primary);}
.appt-stat.green::before{background:#10b981;}
.appt-stat.red::before{background:#ef4444;}
.appt-stat.amber::before{background:#f59e0b;}
.appt-stat.cyan::before{background:#06b6d4;}
.appt-stat i{font-size:1.6rem;margin-bottom:8px;display:block;}
.appt-stat.blue i{color:var(--primary);}
.appt-stat.green i{color:#10b981;}
.appt-stat.red i{color:#ef4444;}
.appt-stat.amber i{color:#f59e0b;}
.appt-stat.cyan i{color:#06b6d4;}
.appt-stat h3{font-size:1.8rem;font-weight:700;color:var(--text);margin-bottom:2px;}
.appt-stat p{font-size:.8rem;color:var(--text-muted);font-weight:500;}

.filter-bar{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:18px 22px;margin-bottom:24px;display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end;box-shadow:var(--shadow-sm);}
.filter-bar .form-group{margin-bottom:0;min-width:160px;}
.filter-bar label{font-size:.8rem;font-weight:600;color:var(--text-muted);margin-bottom:5px;display:block;}
.filter-bar select,.filter-bar input{padding:8px 12px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:.85rem;font-family:inherit;color:var(--text);background:#f8fafc;width:100%;}
.filter-bar select:focus,.filter-bar input:focus{outline:none;border-color:var(--primary);}

.appt-table{width:100%;border-collapse:collapse;background:var(--surface);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow-sm);border:1px solid var(--border);}
.appt-table th{background:var(--primary-light);color:var(--primary);padding:13px 16px;text-align:left;font-size:.82rem;font-weight:700;white-space:nowrap;}
.appt-table td{padding:13px 16px;border-bottom:1px solid var(--border);font-size:.87rem;color:var(--text);vertical-align:middle;}
.appt-table tr:last-child td{border-bottom:none;}
.appt-table tr:hover td{background:#f8fafc;}

.status-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:.76rem;font-weight:700;}
.status-scheduled{background:#dbeafe;color:#1d4ed8;}
.status-completed{background:#d1fae5;color:#065f46;}
.status-cancelled{background:#fee2e2;color:#991b1b;}

.action-btns{display:flex;gap:6px;flex-wrap:wrap;}
.btn-sm{padding:5px 10px;border-radius:6px;font-size:.76rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:4px;transition:all .2s;white-space:nowrap;border:none;cursor:pointer;font-family:inherit;}
.btn-complete{background:#d1fae5;color:#065f46;}
.btn-complete:hover{background:#10b981;color:#fff;}
.btn-cancel{background:#fef3c7;color:#92400e;}
.btn-cancel:hover{background:#f59e0b;color:#fff;}
.btn-delete-sm{background:#fee2e2;color:#ef4444;}
.btn-delete-sm:hover{background:#ef4444;color:#fff;}

.book-panel{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:28px;margin-bottom:28px;box-shadow:var(--shadow-sm);}
.book-panel h3{font-size:1.05rem;font-weight:700;color:var(--text);margin-bottom:20px;display:flex;align-items:center;gap:8px;padding-bottom:14px;border-bottom:1px solid var(--border);}
.book-panel h3 i{color:var(--primary);}

.today-badge{background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:700;margin-left:8px;}
.empty-table{text-align:center;padding:40px;color:var(--text-muted);}
.empty-table i{font-size:2.5rem;opacity:.2;display:block;margin-bottom:10px;}

@media(max-width:768px){
    .appt-table{display:block;overflow-x:auto;}
    .filter-bar{flex-direction:column;}
}
</style>
</head>
<body>
<div class="navbar">
    <span class="navbar-brand"><i class="fas fa-hospital"></i> Hospital MS</span>
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="add_doctor.php"><i class="fas fa-user-md"></i> Add Doctor</a>
    <a href="add_patient.php"><i class="fas fa-user-injured"></i> Add Patient</a>
    <a href="appointments.php" style="color:#fff;background:rgba(255,255,255,.15);"><i class="fas fa-calendar-check"></i> Appointments</a>
    <a href="rooms.php"><i class="fas fa-door-open"></i> Rooms</a>
    <a href="search_patient.php"><i class="fas fa-search"></i> Search</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="container">
<?php if(isset($_GET['success'])): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?></div>
<?php endif; ?>
<?php if($error): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
<?php endif; ?>

<h1><i class="fas fa-calendar-check"></i> Appointment Booking</h1>

<!-- Stats -->
<div class="appt-stats">
    <div class="appt-stat blue">
        <i class="fas fa-calendar-alt"></i>
        <h3><?php echo $total; ?></h3>
        <p>Total Appointments</p>
    </div>
    <div class="appt-stat cyan">
        <i class="fas fa-calendar-day"></i>
        <h3><?php echo $today_count; ?></h3>
        <p>Today's Scheduled</p>
    </div>
    <div class="appt-stat amber">
        <i class="fas fa-clock"></i>
        <h3><?php echo $scheduled; ?></h3>
        <p>Scheduled</p>
    </div>
    <div class="appt-stat green">
        <i class="fas fa-check-circle"></i>
        <h3><?php echo $completed; ?></h3>
        <p>Completed</p>
    </div>
    <div class="appt-stat red">
        <i class="fas fa-times-circle"></i>
        <h3><?php echo $cancelled; ?></h3>
        <p>Cancelled</p>
    </div>
</div>

<!-- Book Appointment Form -->
<div class="book-panel">
    <h3><i class="fas fa-plus-circle"></i> Book New Appointment</h3>
    <form method="POST">
        <div class="form-row">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Patient Name *</label>
                <input type="text" name="patient_name" placeholder="Full name" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-phone"></i> Phone Number *</label>
                <input type="text" name="patient_phone" placeholder="e.g. 9876543210" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label><i class="fas fa-birthday-cake"></i> Age</label>
                <input type="number" name="patient_age" min="0" max="120" placeholder="Age">
            </div>
            <div class="form-group">
                <label><i class="fas fa-venus-mars"></i> Gender</label>
                <select name="patient_gender">
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label><i class="fas fa-user-md"></i> Doctor *</label>
                <select name="doctor_id" required>
                    <option value="">— Select Doctor —</option>
                    <?php foreach($doctors as $d): ?>
                        <option value="<?php echo $d['doctor_id']; ?>">
                            <?php echo htmlspecialchars($d['name']); ?> — <?php echo htmlspecialchars($d['specialization']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-stethoscope"></i> Reason for Visit</label>
                <input type="text" name="reason" placeholder="e.g. Routine checkup, Fever, Follow-up">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label><i class="fas fa-calendar"></i> Appointment Date *</label>
                <input type="date" name="appointment_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-clock"></i> Appointment Time *</label>
                <input type="time" name="appointment_time" value="09:00" required>
            </div>
        </div>
        <div class="form-group">
            <label><i class="fas fa-sticky-note"></i> Notes</label>
            <textarea name="notes" rows="2" placeholder="Any additional notes..."></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" name="add_appointment" class="btn-primary">
                <i class="fas fa-calendar-plus"></i> Book Appointment
            </button>
        </div>
    </form>
</div>

<!-- Filter Bar -->
<form method="GET">
<div class="filter-bar">
    <div class="form-group">
        <label>Filter by Status</label>
        <select name="filter_status">
            <option value="">All Statuses</option>
            <option value="Scheduled"  <?php echo $filter_status=='Scheduled'  ? 'selected':''; ?>>Scheduled</option>
            <option value="Completed"  <?php echo $filter_status=='Completed'  ? 'selected':''; ?>>Completed</option>
            <option value="Cancelled"  <?php echo $filter_status=='Cancelled'  ? 'selected':''; ?>>Cancelled</option>
        </select>
    </div>
    <div class="form-group">
        <label>Filter by Doctor</label>
        <select name="filter_doctor">
            <option value="">All Doctors</option>
            <?php foreach($doctors as $d): ?>
                <option value="<?php echo $d['doctor_id']; ?>" <?php echo $filter_doctor==$d['doctor_id'] ? 'selected':''; ?>>
                    <?php echo htmlspecialchars($d['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>Filter by Date</label>
        <input type="date" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>">
    </div>
    <div style="display:flex;gap:8px;align-items:flex-end;">
        <button type="submit" class="btn-primary" style="padding:8px 18px;">
            <i class="fas fa-filter"></i> Filter
        </button>
        <a href="appointments.php" class="btn-secondary" style="padding:8px 18px;">
            <i class="fas fa-times"></i> Clear
        </a>
    </div>
</div>
</form>

<!-- Appointments Table -->
<div style="overflow-x:auto;">
<table class="appt-table">
    <thead>
        <tr>
            <th>#</th>
            <th>Patient</th>
            <th>Phone</th>
            <th>Doctor</th>
            <th>Date & Time</th>
            <th>Reason</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php if(empty($appointments)): ?>
        <tr><td colspan="8" class="empty-table">
            <i class="fas fa-calendar-times"></i>
            <p>No appointments found.</p>
        </td></tr>
    <?php else: ?>
        <?php foreach($appointments as $a):
            $is_today = ($a['appointment_date'] == date('Y-m-d'));
        ?>
        <tr>
            <td style="color:var(--text-muted);font-size:.8rem;">#<?php echo $a['appointment_id']; ?></td>
            <td>
                <strong><?php echo htmlspecialchars($a['patient_name']); ?></strong>
                <?php if($is_today && $a['status']=='Scheduled'): ?>
                    <span class="today-badge">Today</span>
                <?php endif; ?>
                <br><small style="color:var(--text-muted);"><?php echo $a['patient_age'] ? $a['patient_age'].' yrs / ' : ''; ?><?php echo $a['patient_gender']; ?></small>
            </td>
            <td><?php echo htmlspecialchars($a['patient_phone']); ?></td>
            <td>
                <strong><?php echo htmlspecialchars($a['doctor_name']); ?></strong><br>
                <small style="color:var(--text-muted);"><?php echo htmlspecialchars($a['specialization']); ?></small>
            </td>
            <td>
                <strong><?php echo date('d M Y', strtotime($a['appointment_date'])); ?></strong><br>
                <small style="color:var(--primary);font-weight:600;"><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($a['appointment_time'])); ?></small>
            </td>
            <td><?php echo $a['reason'] ? htmlspecialchars($a['reason']) : '<span style="color:var(--text-muted);">—</span>'; ?></td>
            <td>
                <span class="status-badge status-<?php echo strtolower($a['status']); ?>">
                    <i class="fas fa-<?php echo $a['status']=='Scheduled' ? 'clock' : ($a['status']=='Completed' ? 'check-circle' : 'times-circle'); ?>"></i>
                    <?php echo $a['status']; ?>
                </span>
            </td>
            <td>
                <div class="action-btns">
                    <?php if($a['status'] == 'Scheduled'): ?>
                        <a href="appointments.php?status=Completed&id=<?php echo $a['appointment_id']; ?>"
                           class="btn-sm btn-complete" onclick="return confirm('Mark as Completed?')">
                            <i class="fas fa-check"></i> Done
                        </a>
                        <a href="appointments.php?status=Cancelled&id=<?php echo $a['appointment_id']; ?>"
                           class="btn-sm btn-cancel" onclick="return confirm('Cancel this appointment?')">
                            <i class="fas fa-ban"></i> Cancel
                        </a>
                    <?php elseif($a['status'] == 'Cancelled'): ?>
                        <a href="appointments.php?status=Scheduled&id=<?php echo $a['appointment_id']; ?>"
                           class="btn-sm btn-complete" onclick="return confirm('Reschedule this appointment?')">
                            <i class="fas fa-redo"></i> Reschedule
                        </a>
                    <?php endif; ?>
                    <a href="appointments.php?delete=<?php echo $a['appointment_id']; ?>"
                       class="btn-sm btn-delete-sm" onclick="return confirm('Delete this appointment?')">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>
</div>

</div>
</body>
</html>
