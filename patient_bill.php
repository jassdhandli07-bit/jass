<?php
session_start();
if(!isset($_SESSION['user'])) { header("Location: index.php"); exit(); }
include 'db_connect.php';

if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php?error=Invalid+patient+ID");
    exit();
}
$patient_id = (int)$_GET['id'];

// Fetch patient + room + doctor
$stmt = $conn->prepare("
    SELECT p.*, d.name as doctor_name, d.specialization,
           r.room_number, r.room_type, r.price_per_day
    FROM patients p
    LEFT JOIN doctors d ON p.assigned_doctor_id = d.doctor_id
    LEFT JOIN rooms r ON p.room_id = r.room_id
    WHERE p.patient_id = ?
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$patient) {
    header("Location: dashboard.php?error=Patient+not+found");
    exit();
}

// Calculate room charges based on days
$admission = new DateTime($patient['admission_date'] ?? date('Y-m-d'));
$discharge  = $patient['discharge_date'] ? new DateTime($patient['discharge_date']) : new DateTime();
$days       = max(1, $admission->diff($discharge)->days);
$room_charge = $days * ($patient['price_per_day'] ?? 0);

// Total report charges for this patient
$rep = $conn->prepare("SELECT COALESCE(SUM(charge),0) as total FROM patient_reports WHERE patient_id=?");
$rep->bind_param("i", $patient_id);
$rep->execute();
$report_charges_total = (float)$rep->get_result()->fetch_assoc()['total'];
$rep->close();

// Total medication charges for this patient
$med = $conn->prepare("SELECT COALESCE(SUM(charge),0) as total FROM patient_medications WHERE patient_id=?");
$med->bind_param("i", $patient_id);
$med->execute();
$med_charges_total = (float)$med->get_result()->fetch_assoc()['total'];
$med->close();

// Fetch existing bill or create default values
$bill_stmt = $conn->prepare("SELECT * FROM bills WHERE patient_id = ? ORDER BY bill_id DESC LIMIT 1");
$bill_stmt->bind_param("i", $patient_id);
$bill_stmt->execute();
$bill = $bill_stmt->get_result()->fetch_assoc();
$bill_stmt->close();

$success = "";
$error   = "";

if(isset($_POST['save_bill'])) {
    $medicine_charges = (float)$_POST['medicine_charges'];
    $doctor_charges   = (float)$_POST['doctor_charges'];
    $other_charges    = (float)$_POST['other_charges'];
    $room_ch          = (float)$_POST['room_charges'];
    $total            = $room_ch + $medicine_charges + $doctor_charges + $other_charges;
    $status           = $_POST['payment_status'];

    if($bill) {
        $s = $conn->prepare("UPDATE bills SET room_charges=?, medicine_charges=?, doctor_charges=?, other_charges=?, total_amount=?, payment_status=? WHERE bill_id=?");
        $s->bind_param("dddddsi", $room_ch, $medicine_charges, $doctor_charges, $other_charges, $total, $status, $bill['bill_id']);
    } else {
        $s = $conn->prepare("INSERT INTO bills (patient_id, room_charges, medicine_charges, doctor_charges, other_charges, total_amount, payment_status) VALUES (?,?,?,?,?,?,?)");
        $s->bind_param("iddddss", $patient_id, $room_ch, $medicine_charges, $doctor_charges, $other_charges, $total, $status);
    }
    if($s->execute()) {
        $success = "Bill saved successfully!";
        // Refresh bill
        $bill_stmt = $conn->prepare("SELECT * FROM bills WHERE patient_id = ? ORDER BY bill_id DESC LIMIT 1");
        $bill_stmt->bind_param("i", $patient_id);
        $bill_stmt->execute();
        $bill = $bill_stmt->get_result()->fetch_assoc();
        $bill_stmt->close();
    } else {
        $error = "Failed to save bill: " . $conn->error;
    }
    $s->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Bill - <?php echo htmlspecialchars($patient['name']); ?></title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .bill-wrapper { max-width: 820px; margin: 0 auto; }
        .bill-header { background: linear-gradient(135deg, var(--primary-dark), var(--primary)); color: #fff; padding: 32px; border-radius: var(--radius) var(--radius) 0 0; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; }
        .bill-header h2 { font-size: 1.5rem; margin-bottom: 4px; }
        .bill-header p { opacity: 0.85; font-size: 0.88rem; }
        .bill-status { padding: 6px 18px; border-radius: 20px; font-weight: 700; font-size: 0.85rem; }
        .status-paid    { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .bill-body { background: var(--surface); border: 1px solid var(--border); border-top: none; border-radius: 0 0 var(--radius) var(--radius); padding: 32px; }
        .bill-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; padding-bottom: 24px; border-bottom: 1px solid var(--border); }
        .bill-info-item label { font-size: 0.78rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 4px; }
        .bill-info-item span { font-size: 0.95rem; font-weight: 600; color: var(--text); }
        .charges-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .charges-table th { background: var(--primary-light); color: var(--primary); padding: 12px 16px; text-align: left; font-size: 0.85rem; font-weight: 700; }
        .charges-table td { padding: 12px 16px; border-bottom: 1px solid var(--border); font-size: 0.9rem; }
        .charges-table tr:last-child td { border-bottom: none; }
        .charges-table input[type="number"] { width: 140px; padding: 8px 12px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); font-size: 0.9rem; font-family: inherit; }
        .charges-table input:focus { outline: none; border-color: var(--primary); }
        .total-row td { font-weight: 700; font-size: 1.05rem; color: var(--primary); background: var(--primary-light); }
        .print-btn { background: #1e293b; color: #fff; border: none; padding: 10px 22px; border-radius: var(--radius-sm); cursor: pointer; font-size: 0.9rem; font-weight: 600; display: inline-flex; align-items: center; gap: 7px; font-family: inherit; }
        @media print {
            .navbar, .form-actions, .no-print { display: none !important; }
            body { background: white !important; }
            .bill-wrapper { max-width: 100%; }
        }
    </style>
</head>
<body>
<div class="navbar no-print">
    <span class="navbar-brand"><i class="fas fa-hospital"></i> Hospital MS</span>
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
    <a href="rooms.php"><i class="fas fa-door-open"></i> Rooms</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="container">
    <?php if($success): ?>
        <div class="alert alert-success no-print"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-error no-print"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <div class="bill-wrapper">
        <!-- Bill Header -->
        <div class="bill-header">
            <div>
                <h2><i class="fas fa-hospital-alt"></i> Hospital Management System</h2>
                <p>Patient Invoice / Bill</p>
                <p>Bill Date: <?php echo $bill ? date('d M Y', strtotime($bill['bill_date'])) : date('d M Y'); ?></p>
            </div>
            <div style="text-align:right;">
                <span class="bill-status <?php echo ($bill && $bill['payment_status']=='Paid') ? 'status-paid' : 'status-pending'; ?>">
                    <i class="fas fa-<?php echo ($bill && $bill['payment_status']=='Paid') ? 'check-circle' : 'clock'; ?>"></i>
                    <?php echo $bill ? $bill['payment_status'] : 'Pending'; ?>
                </span>
                <p style="margin-top:10px; opacity:0.85;">Patient ID: #<?php echo $patient_id; ?></p>
            </div>
        </div>

        <!-- Bill Body -->
        <div class="bill-body">
            <!-- Patient Info -->
            <div class="bill-info-grid">
                <div class="bill-info-item">
                    <label>Patient Name</label>
                    <span><?php echo htmlspecialchars($patient['name']); ?></span>
                </div>
                <div class="bill-info-item">
                    <label>Age / Gender</label>
                    <span><?php echo $patient['age']; ?> yrs / <?php echo $patient['gender']; ?></span>
                </div>
                <div class="bill-info-item">
                    <label>Disease</label>
                    <span><?php echo htmlspecialchars($patient['disease'] ?? 'N/A'); ?></span>
                </div>
                <div class="bill-info-item">
                    <label>Doctor</label>
                    <span><?php echo $patient['doctor_name'] ? htmlspecialchars($patient['doctor_name']) : 'Unassigned'; ?></span>
                </div>
                <div class="bill-info-item">
                    <label>Room</label>
                    <span><?php echo $patient['room_number'] ? 'Room '.$patient['room_number'].' ('.$patient['room_type'].')' : 'No Room'; ?></span>
                </div>
                <div class="bill-info-item">
                    <label>Admission Date</label>
                    <span><?php echo $patient['admission_date'] ?? 'N/A'; ?></span>
                </div>
                <div class="bill-info-item">
                    <label>Discharge Date</label>
                    <span>
                        <?php if($patient['discharge_date']): ?>
                            <span style="color:#10b981;font-weight:700;"><?php echo $patient['discharge_date']; ?></span>
                        <?php else: ?>
                            <span style="color:#ef4444;font-weight:700;">Not Discharged</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="bill-info-item">
                    <label>Days Admitted</label>
                    <span><?php echo $days; ?> day(s)</span>
                </div>
                <div class="bill-info-item">
                    <label>Room Rate</label>
                    <span>₹<?php echo number_format($patient['price_per_day'] ?? 0, 2); ?>/day</span>
                </div>
            </div>

            <!-- Charges Form -->
            <form method="POST">
                <table class="charges-table">
                    <thead>
                        <tr>
                            <th>Charge Type</th>
                            <th>Amount (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><i class="fas fa-bed" style="color:var(--primary);margin-right:8px;"></i> Room Charges (<?php echo $days; ?> days × ₹<?php echo number_format($patient['price_per_day']??0,0); ?>)</td>
                            <td><input type="number" name="room_charges" id="rc" step="0.01" min="0" value="<?php echo $bill ? $bill['room_charges'] : $room_charge; ?>" oninput="calcTotal()"></td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-pills" style="color:var(--primary);margin-right:8px;"></i> Medicine / Treatment Charges
                                <?php if($med_charges_total > 0): ?>
                                <small style="color:var(--text-muted);font-size:0.78rem;"> (auto-filled from Medical Record)</small>
                                <?php endif; ?>
                            </td>
                            <td><input type="number" name="medicine_charges" id="mc" step="0.01" min="0" value="<?php echo $bill ? $bill['medicine_charges'] : $med_charges_total; ?>" oninput="calcTotal()"></td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-user-md" style="color:var(--primary);margin-right:8px;"></i> Doctor / Consultation Charges</td>
                            <td><input type="number" name="doctor_charges" id="dc" step="0.01" min="0" value="<?php echo $bill ? $bill['doctor_charges'] : 500; ?>" oninput="calcTotal()"></td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-file-medical" style="color:var(--primary);margin-right:8px;"></i> Report / Lab Charges</td>
                            <td><input type="number" name="report_charges" id="rpc" step="0.01" min="0" value="<?php echo $bill ? $bill['other_charges'] : $report_charges_total; ?>" oninput="calcTotal()"></td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-plus-circle" style="color:var(--primary);margin-right:8px;"></i> Other Charges</td>
                            <td><input type="number" name="other_charges" id="oc" step="0.01" min="0" value="<?php echo $bill ? $bill['other_charges'] : 0; ?>" oninput="calcTotal()"></td>
                        </tr>
                        <tr class="total-row">
                            <td><i class="fas fa-rupee-sign" style="margin-right:8px;"></i> Total Amount</td>
                            <td><strong id="total_display">₹<?php
                                $t = $bill ? $bill['total_amount'] : ($room_charge + 500);
                                echo number_format($t, 2);
                            ?></strong></td>
                        </tr>
                    </tbody>
                </table>

                <div class="form-row no-print" style="max-width:400px;">
                    <div class="form-group">
                        <label><i class="fas fa-credit-card"></i> Payment Status</label>
                        <select name="payment_status">
                            <option value="Pending" <?php echo (!$bill || $bill['payment_status']=='Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Paid"    <?php echo ($bill && $bill['payment_status']=='Paid') ? 'selected' : ''; ?>>Paid</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions no-print">
                    <button type="submit" name="save_bill" class="btn-primary">
                        <i class="fas fa-save"></i> Save Bill
                    </button>
                    <button type="button" class="print-btn" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Bill
                    </button>
                    <a href="dashboard.php" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function calcTotal() {
    const rc  = parseFloat(document.getElementById('rc').value)  || 0;
    const mc  = parseFloat(document.getElementById('mc').value)  || 0;
    const dc  = parseFloat(document.getElementById('dc').value)  || 0;
    const rpc = parseFloat(document.getElementById('rpc').value) || 0;
    const oc  = parseFloat(document.getElementById('oc').value)  || 0;
    const total = rc + mc + dc + rpc + oc;
    document.getElementById('total_display').textContent = '₹' + total.toLocaleString('en-IN', {minimumFractionDigits:2});
}
</script>
</body>
</html>
