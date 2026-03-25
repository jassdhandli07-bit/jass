<?php
session_start();
if(!isset($_SESSION['user'])) { header("Location: index.php"); exit(); }
include 'db_connect.php';

if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php?error=Invalid+ID"); exit();
}
$patient_id = (int)$_GET['id'];

// Patient + doctor + room
$stmt = $conn->prepare("
    SELECT p.*, d.name as doctor_name, d.specialization, d.license_no, d.contact as doctor_contact,
           r.room_number, r.room_type, r.price_per_day
    FROM patients p
    LEFT JOIN doctors d ON p.assigned_doctor_id = d.doctor_id
    LEFT JOIN rooms   r ON p.room_id = r.room_id
    WHERE p.patient_id = ?
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();
if(!$patient) { header("Location: dashboard.php?error=Patient+not+found"); exit(); }

$success = ""; $error = "";

// ── ADD MEDICATION ──────────────────────────────────────────────
if(isset($_POST['add_med'])) {
    $med_type   = trim($_POST['med_type']);
    $med_name   = trim($_POST['med_name']);
    $dosage     = trim($_POST['dosage']);
    $frequency  = trim($_POST['frequency']);
    $start_date = trim($_POST['start_date']);
    $end_date   = !empty($_POST['end_date']) ? trim($_POST['end_date']) : null;
    $charge     = (float)$_POST['charge'];
    $notes      = trim($_POST['notes']);

    if(empty($med_name)) { $error = "Medication name is required."; }
    else {
        $s = $conn->prepare("INSERT INTO patient_medications (patient_id,med_type,med_name,dosage,frequency,start_date,end_date,charge,notes) VALUES (?,?,?,?,?,?,?,?,?)");
        $s->bind_param("issssssds", $patient_id,$med_type,$med_name,$dosage,$frequency,$start_date,$end_date,$charge,$notes);
        if($s->execute()) $success = "Medication added!";
        else $error = "Error: ".$conn->error;
        $s->close();
    }
}

// ── DELETE MEDICATION ───────────────────────────────────────────
if(isset($_GET['del_med']) && is_numeric($_GET['del_med'])) {
    $mid = (int)$_GET['del_med'];
    $s = $conn->prepare("DELETE FROM patient_medications WHERE med_id=? AND patient_id=?");
    $s->bind_param("ii", $mid, $patient_id); $s->execute(); $s->close();
    $success = "Deleted.";
}

// ── DELETE REPORT ───────────────────────────────────────────────
if(isset($_GET['del_rep']) && is_numeric($_GET['del_rep'])) {
    $rid = (int)$_GET['del_rep'];
    $s = $conn->prepare("SELECT file_path FROM patient_reports WHERE report_id=? AND patient_id=?");
    $s->bind_param("ii",$rid,$patient_id); $s->execute();
    $rr = $s->get_result()->fetch_assoc(); $s->close();
    if($rr) {
        if($rr['file_path'] && file_exists($rr['file_path'])) unlink($rr['file_path']);
        $d = $conn->prepare("DELETE FROM patient_reports WHERE report_id=?");
        $d->bind_param("i",$rid); $d->execute(); $d->close();
        $success = "Report deleted.";
    }
}

// ── ADD REPORT ──────────────────────────────────────────────────
if(isset($_POST['add_report'])) {
    $rtype  = trim($_POST['report_type']);
    $rtitle = trim($_POST['report_title']);
    $rdesc  = trim($_POST['description']);
    $rdate  = trim($_POST['report_date']);
    $rchg   = (float)$_POST['report_charge'];
    $fpath  = null; $fname = null;

    if(empty($rtitle)||empty($rdate)) { $error = "Report title and date required."; }
    else {
        if(isset($_FILES['report_file']) && $_FILES['report_file']['error']==0) {
            $ext = strtolower(pathinfo($_FILES['report_file']['name'], PATHINFO_EXTENSION));
            if(in_array($ext,['jpg','jpeg','png','gif','pdf']) && $_FILES['report_file']['size']<=5000000) {
                $dir = "reports/"; if(!is_dir($dir)) mkdir($dir,0755,true);
                $fname = $_FILES['report_file']['name'];
                $fpath = $dir.uniqid()."_".time().".".$ext;
                if(!move_uploaded_file($_FILES['report_file']['tmp_name'],$fpath)) { $fpath=null; $error="Upload failed."; }
            } else { $error = "Invalid file. JPG/PNG/PDF under 5MB."; }
        }
        if(empty($error)) {
            $s = $conn->prepare("INSERT INTO patient_reports (patient_id,report_type,report_title,description,charge,file_path,file_name,report_date) VALUES (?,?,?,?,?,?,?,?)");
            $s->bind_param("isssdsss",$patient_id,$rtype,$rtitle,$rdesc,$rchg,$fpath,$fname,$rdate);
            if($s->execute()) $success = "Report added!";
            else $error = "Error: ".$conn->error;
            $s->close();
        }
    }
}

// ── FETCH DATA ──────────────────────────────────────────────────
$meds = $conn->prepare("SELECT * FROM patient_medications WHERE patient_id=? ORDER BY created_at DESC");
$meds->bind_param("i",$patient_id); $meds->execute();
$medications = $meds->get_result()->fetch_all(MYSQLI_ASSOC); $meds->close();

$reps = $conn->prepare("SELECT * FROM patient_reports WHERE patient_id=? ORDER BY report_date DESC");
$reps->bind_param("i",$patient_id); $reps->execute();
$reports = $reps->get_result()->fetch_all(MYSQLI_ASSOC); $reps->close();

// Totals
$total_med_charge    = array_sum(array_column($medications,'charge'));
$total_report_charge = array_sum(array_column($reports,'charge'));
$grand_total         = $total_med_charge + $total_report_charge;

// Days admitted
$adm  = new DateTime($patient['admission_date'] ?? date('Y-m-d'));
$dis  = $patient['discharge_date'] ? new DateTime($patient['discharge_date']) : new DateTime();
$days = max(1, $adm->diff($dis)->days);

$report_types   = ['X-Ray','Blood Test','MRI Scan','CT Scan','Urine Test','ECG','Ultrasound','Other'];
$default_charges= ['X-Ray'=>500,'Blood Test'=>300,'MRI Scan'=>3500,'CT Scan'=>2500,'Urine Test'=>150,'ECG'=>400,'Ultrasound'=>800,'Other'=>200];
$type_icons     = ['X-Ray'=>'fa-x-ray','Blood Test'=>'fa-tint','MRI Scan'=>'fa-brain','CT Scan'=>'fa-radiation','Urine Test'=>'fa-flask','ECG'=>'fa-heartbeat','Ultrasound'=>'fa-wave-square','Other'=>'fa-file-medical'];
$med_icons      = ['Medicine'=>'fa-pills','Injection'=>'fa-syringe','Glucose'=>'fa-tint','IV Fluid'=>'fa-procedures','Oxygen'=>'fa-wind','Vaccine'=>'fa-shield-virus','Other'=>'fa-capsules'];
$med_colors     = ['Medicine'=>'#4f46e5','Injection'=>'#ef4444','Glucose'=>'#f59e0b','IV Fluid'=>'#06b6d4','Oxygen'=>'#10b981','Vaccine'=>'#8b5cf6','Other'=>'#64748b'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Medical Record — <?php echo htmlspecialchars($patient['name']); ?></title>
<link rel="stylesheet" href="style.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
/* ── SCREEN STYLES ── */
.rec-header{background:linear-gradient(135deg,var(--primary-dark),var(--primary));color:#fff;padding:28px 32px;border-radius:var(--radius);margin-bottom:28px;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px;}
.rec-header h2{font-size:1.4rem;margin-bottom:4px;}
.rec-header p{opacity:.85;font-size:.88rem;margin:2px 0;}
.ph-badge{background:rgba(255,255,255,.2);padding:4px 12px;border-radius:20px;font-size:.8rem;font-weight:600;display:inline-flex;align-items:center;gap:5px;margin:3px 2px;}
.rec-actions{display:flex;gap:10px;flex-wrap:wrap;}
.rec-actions a,.rec-actions button{padding:9px 18px;border-radius:var(--radius-sm);font-size:.85rem;font-weight:600;display:inline-flex;align-items:center;gap:6px;text-decoration:none;cursor:pointer;border:1px solid rgba(255,255,255,.4);background:rgba(255,255,255,.15);color:#fff;font-family:inherit;transition:all .2s;}
.rec-actions a:hover,.rec-actions button:hover{background:rgba(255,255,255,.3);}

.summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:32px;}
.sum-box{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;text-align:center;box-shadow:var(--shadow-sm);position:relative;overflow:hidden;}
.sum-box::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;}
.sum-box.blue::before{background:var(--primary);}
.sum-box.red::before{background:#ef4444;}
.sum-box.green::before{background:#10b981;}
.sum-box.amber::before{background:#f59e0b;}
.sum-box i{font-size:1.8rem;margin-bottom:8px;display:block;}
.sum-box.blue i{color:var(--primary);}
.sum-box.red i{color:#ef4444;}
.sum-box.green i{color:#10b981;}
.sum-box.amber i{color:#f59e0b;}
.sum-box h3{font-size:1.5rem;font-weight:700;color:var(--text);margin-bottom:2px;}
.sum-box p{font-size:.8rem;color:var(--text-muted);font-weight:500;}

.panel{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:24px;margin-bottom:24px;box-shadow:var(--shadow-sm);}
.panel-title{font-size:1rem;font-weight:700;color:var(--text);margin-bottom:18px;display:flex;align-items:center;gap:8px;padding-bottom:12px;border-bottom:1px solid var(--border);}
.panel-title i{color:var(--primary);}

.med-row,.rep-row{display:flex;align-items:flex-start;gap:14px;padding:14px 0;border-bottom:1px solid var(--border);}
.med-row:last-child,.rep-row:last-child{border-bottom:none;padding-bottom:0;}
.med-icon,.rep-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#fff;flex-shrink:0;}
.med-body,.rep-body{flex:1;}
.med-body h4,.rep-body h4{font-size:.95rem;font-weight:700;color:var(--text);margin-bottom:3px;}
.med-body p,.rep-body p{font-size:.82rem;color:var(--text-muted);margin:2px 0;}
.charge-tag{display:inline-flex;align-items:center;gap:4px;background:#fef3c7;color:#92400e;border-radius:20px;padding:2px 10px;font-size:.78rem;font-weight:700;margin-top:5px;}
.del-btn{padding:5px 10px;background:#fee2e2;color:#ef4444;border-radius:6px;text-decoration:none;font-size:.78rem;font-weight:600;display:inline-flex;align-items:center;gap:4px;transition:all .2s;flex-shrink:0;}
.del-btn:hover{background:#ef4444;color:#fff;}
.view-btn{padding:5px 10px;background:var(--primary-light);color:var(--primary);border-radius:6px;text-decoration:none;font-size:.78rem;font-weight:600;display:inline-flex;align-items:center;gap:4px;transition:all .2s;flex-shrink:0;}
.view-btn:hover{background:var(--primary);color:#fff;}
.empty-state{text-align:center;padding:32px;color:var(--text-muted);}
.empty-state i{font-size:2rem;opacity:.25;display:block;margin-bottom:8px;}

/* ── PRINT STYLES ── */
@media print {
    *{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;}
    .no-print{display:none!important;}
    body{background:#fff!important;font-size:12px;}
    .navbar{display:none!important;}
    .container{padding:0!important;max-width:100%!important;}
    .print-header{display:block!important;}
    .rec-header{background:#1e3a5f!important;-webkit-print-color-adjust:exact;}
    .panel{box-shadow:none!important;border:1px solid #ddd!important;page-break-inside:avoid;}
    .sum-box{box-shadow:none!important;}
    .del-btn,.view-btn,.rec-actions{display:none!important;}
    .grand-total-bar{background:#1e3a5f!important;-webkit-print-color-adjust:exact;}
}
.print-header{display:none;}

.grand-total-bar{background:linear-gradient(135deg,var(--primary-dark),var(--primary));color:#fff;border-radius:var(--radius);padding:20px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
.grand-total-bar .label{font-size:.9rem;opacity:.85;}
.grand-total-bar .amount{font-size:2rem;font-weight:700;}
</style>
</head>
<body>
<div class="navbar no-print">
    <span class="navbar-brand"><i class="fas fa-hospital"></i> Hospital MS</span>
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="patient_bill.php?id=<?php echo $patient_id;?>"><i class="fas fa-file-invoice-dollar"></i> Bill</a>
    <a href="rooms.php"><i class="fas fa-door-open"></i> Rooms</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="container">
<?php if($success): ?><div class="alert alert-success no-print"><i class="fas fa-check-circle"></i> <?php echo $success;?></div><?php endif;?>
<?php if($error):   ?><div class="alert alert-error no-print"><i class="fas fa-exclamation-circle"></i> <?php echo $error;?></div><?php endif;?>

<!-- Patient Header -->
<div class="rec-header">
    <div>
        <h2><i class="fas fa-user-injured"></i> <?php echo htmlspecialchars($patient['name']);?></h2>
        <p>Patient ID: #<?php echo $patient_id;?> &nbsp;|&nbsp; <?php echo $patient['age'];?> yrs / <?php echo $patient['gender'];?></p>
        <div style="margin-top:8px;">
            <span class="ph-badge"><i class="fas fa-virus"></i> <?php echo htmlspecialchars($patient['disease']??'N/A');?></span>
            <span class="ph-badge"><i class="fas fa-user-md"></i> <?php echo htmlspecialchars($patient['doctor_name']??'Unassigned');?></span>
            <?php if($patient['room_number']): ?>
            <span class="ph-badge"><i class="fas fa-door-open"></i> Room <?php echo $patient['room_number'];?> (<?php echo $patient['room_type'];?>)</span>
            <?php endif;?>
            <span class="ph-badge"><i class="fas fa-calendar-check"></i> Admitted: <?php echo $patient['admission_date']??'N/A';?></span>
            <?php if($patient['discharge_date']): ?>
            <span class="ph-badge" style="background:rgba(16,185,129,.3);"><i class="fas fa-calendar-times"></i> Discharged: <?php echo $patient['discharge_date'];?></span>
            <?php endif;?>
        </div>
    </div>
    <div class="rec-actions no-print">
        <button onclick="window.print()"><i class="fas fa-print"></i> Print Full Record</button>
        <a href="patient_bill.php?id=<?php echo $patient_id;?>"><i class="fas fa-file-invoice-dollar"></i> Bill</a>
        <a href="edit_patient.php?id=<?php echo $patient_id;?>"><i class="fas fa-edit"></i> Edit</a>
    </div>
</div>

<!-- Summary Boxes -->
<div class="summary-grid">
    <div class="sum-box blue">
        <i class="fas fa-pills"></i>
        <h3><?php echo count($medications); ?></h3>
        <p>Medications</p>
    </div>
    <div class="sum-box red">
        <i class="fas fa-file-medical-alt"></i>
        <h3><?php echo count($reports); ?></h3>
        <p>Reports / Tests</p>
    </div>
    <div class="sum-box amber">
        <i class="fas fa-calendar-day"></i>
        <h3><?php echo $days; ?></h3>
        <p>Days Admitted</p>
    </div>
    <div class="sum-box green">
        <i class="fas fa-rupee-sign"></i>
        <h3>₹<?php echo number_format($grand_total, 0); ?></h3>
        <p>Med + Report Charges</p>
    </div>
</div>

<!-- Grand Total Bar -->
<div class="grand-total-bar no-print">
    <div>
        <div class="label"><i class="fas fa-calculator"></i> Grand Total (Medications + Reports)</div>
        <div class="amount">₹<?php echo number_format($grand_total, 2); ?></div>
    </div>
    <div>
        <a href="patient_bill.php?id=<?php echo $patient_id; ?>" style="padding:9px 18px;border-radius:var(--radius-sm);font-size:.85rem;font-weight:600;display:inline-flex;align-items:center;gap:6px;text-decoration:none;cursor:pointer;border:1px solid rgba(255,255,255,.4);background:rgba(255,255,255,.15);color:#fff;">
            <i class="fas fa-file-invoice-dollar"></i> View Full Bill
        </a>
    </div>
</div>

<!-- ── ADD MEDICATION FORM ── -->
<div class="panel no-print">
    <div class="panel-title"><i class="fas fa-plus-circle"></i> Add Medication / Treatment</div>
    <form method="POST">
        <div class="form-row">
            <div class="form-group">
                <label><i class="fas fa-tag"></i> Type</label>
                <select name="med_type" id="med_type_sel">
                    <?php foreach(array_keys($med_icons) as $mt): ?>
                        <option value="<?php echo $mt; ?>"><?php echo $mt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-capsules"></i> Name *</label>
                <input type="text" name="med_name" placeholder="e.g. Paracetamol 500mg" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label><i class="fas fa-weight"></i> Dosage</label>
                <input type="text" name="dosage" placeholder="e.g. 500mg, 1 unit">
            </div>
            <div class="form-group">
                <label><i class="fas fa-clock"></i> Frequency</label>
                <input type="text" name="frequency" placeholder="e.g. Twice daily, Every 8 hrs">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label><i class="fas fa-calendar-plus"></i> Start Date</label>
                <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label><i class="fas fa-calendar-minus"></i> End Date</label>
                <input type="date" name="end_date">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label><i class="fas fa-rupee-sign"></i> Charge (₹)</label>
                <input type="number" name="charge" step="0.01" min="0" value="0" placeholder="0.00">
            </div>
            <div class="form-group">
                <label><i class="fas fa-sticky-note"></i> Notes</label>
                <input type="text" name="notes" placeholder="Additional notes...">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" name="add_med" class="btn-primary"><i class="fas fa-plus"></i> Add Medication</button>
        </div>
    </form>
</div>

<!-- ── MEDICATIONS LIST ── -->
<div class="panel">
    <div class="panel-title"><i class="fas fa-pills"></i> Medications & Treatments
        <span style="margin-left:auto;font-size:.82rem;color:var(--text-muted);font-weight:500;">
            Total: <strong style="color:var(--primary);">₹<?php echo number_format($total_med_charge,2); ?></strong>
        </span>
    </div>
    <?php if(empty($medications)): ?>
        <div class="empty-state"><i class="fas fa-pills"></i><p>No medications added yet.</p></div>
    <?php else: ?>
        <?php foreach($medications as $m): ?>
        <div class="med-row">
            <div class="med-icon" style="background:<?php echo $med_colors[$m['med_type']] ?? '#4f46e5'; ?>">
                <i class="fas <?php echo $med_icons[$m['med_type']] ?? 'fa-pills'; ?>"></i>
            </div>
            <div class="med-body">
                <h4><?php echo htmlspecialchars($m['med_name']); ?>
                    <span style="font-size:.78rem;font-weight:500;color:var(--text-muted);margin-left:6px;"><?php echo $m['med_type']; ?></span>
                </h4>
                <?php if($m['dosage']): ?><p><i class="fas fa-weight" style="color:var(--primary);width:14px;"></i> <?php echo htmlspecialchars($m['dosage']); ?></p><?php endif; ?>
                <?php if($m['frequency']): ?><p><i class="fas fa-clock" style="color:var(--primary);width:14px;"></i> <?php echo htmlspecialchars($m['frequency']); ?></p><?php endif; ?>
                <?php if($m['start_date']): ?>
                <p><i class="fas fa-calendar" style="color:var(--primary);width:14px;"></i>
                    <?php echo $m['start_date']; ?><?php echo $m['end_date'] ? ' → '.$m['end_date'] : ''; ?>
                </p>
                <?php endif; ?>
                <?php if($m['notes']): ?><p><i class="fas fa-sticky-note" style="color:var(--primary);width:14px;"></i> <?php echo htmlspecialchars($m['notes']); ?></p><?php endif; ?>
                <span class="charge-tag"><i class="fas fa-rupee-sign"></i> ₹<?php echo number_format($m['charge'],2); ?></span>
            </div>
            <a href="?id=<?php echo $patient_id; ?>&del_med=<?php echo $m['med_id']; ?>"
               class="del-btn no-print" onclick="return confirm('Delete this medication?')">
                <i class="fas fa-trash"></i>
            </a>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ── ADD REPORT FORM ── -->
<div class="panel no-print">
    <div class="panel-title"><i class="fas fa-upload"></i> Add Report / Test</div>
    <form method="POST" enctype="multipart/form-data">
        <div class="form-row">
            <div class="form-group">
                <label><i class="fas fa-tag"></i> Report Type</label>
                <select name="report_type" id="rep_type_sel" onchange="setRepCharge(this.value)">
                    <?php foreach($report_types as $rt): ?>
                        <option value="<?php echo $rt; ?>"><?php echo $rt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-rupee-sign"></i> Charge (₹)</label>
                <input type="number" name="report_charge" id="rep_charge_inp" step="0.01" min="0"
                       value="<?php echo $default_charges['X-Ray']; ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label><i class="fas fa-heading"></i> Report Title *</label>
                <input type="text" name="report_title" placeholder="e.g. Chest X-Ray, CBC Blood Test" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-calendar"></i> Report Date *</label>
                <input type="date" name="report_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label><i class="fas fa-align-left"></i> Findings / Description</label>
                <textarea name="description" rows="2" placeholder="Enter findings..."></textarea>
            </div>
            <div class="form-group">
                <label><i class="fas fa-paperclip"></i> Attach File (JPG/PNG/PDF, max 5MB)</label>
                <input type="file" name="report_file" accept=".jpg,.jpeg,.png,.gif,.pdf">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" name="add_report" class="btn-primary"><i class="fas fa-plus"></i> Add Report</button>
        </div>
    </form>
</div>

<!-- ── REPORTS LIST ── -->
<div class="panel">
    <div class="panel-title"><i class="fas fa-file-medical-alt"></i> Reports & Tests
        <span style="margin-left:auto;font-size:.82rem;color:var(--text-muted);font-weight:500;">
            Total: <strong style="color:var(--primary);">₹<?php echo number_format($total_report_charge,2); ?></strong>
        </span>
    </div>
    <?php if(empty($reports)): ?>
        <div class="empty-state"><i class="fas fa-file-medical"></i><p>No reports added yet.</p></div>
    <?php else: ?>
        <?php foreach($reports as $r): ?>
        <div class="rep-row">
            <div class="rep-icon" style="background:<?php
                $rcolors=['X-Ray'=>'#4f46e5','Blood Test'=>'#ef4444','MRI Scan'=>'#8b5cf6','CT Scan'=>'#06b6d4','Urine Test'=>'#f59e0b','ECG'=>'#10b981','Ultrasound'=>'#3b82f6','Other'=>'#64748b'];
                echo $rcolors[$r['report_type']] ?? '#4f46e5';
            ?>">
                <i class="fas <?php echo $type_icons[$r['report_type']] ?? 'fa-file-medical'; ?>"></i>
            </div>
            <div class="rep-body">
                <h4><?php echo htmlspecialchars($r['report_title']); ?>
                    <span style="font-size:.78rem;font-weight:500;color:var(--text-muted);margin-left:6px;"><?php echo $r['report_type']; ?></span>
                </h4>
                <?php if($r['description']): ?><p><?php echo htmlspecialchars($r['description']); ?></p><?php endif; ?>
                <p><i class="fas fa-calendar" style="color:var(--primary);width:14px;"></i> <?php echo $r['report_date']; ?></p>
                <?php if($r['file_name']): ?><p><i class="fas fa-paperclip" style="color:var(--primary);width:14px;"></i> <?php echo htmlspecialchars($r['file_name']); ?></p><?php endif; ?>
                <span class="charge-tag"><i class="fas fa-rupee-sign"></i> ₹<?php echo number_format($r['charge'],2); ?></span>
            </div>
            <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0;">
                <?php if($r['file_path'] && file_exists($r['file_path'])): ?>
                    <a href="<?php echo $r['file_path']; ?>" target="_blank" class="view-btn"><i class="fas fa-eye"></i> View</a>
                    <a href="<?php echo $r['file_path']; ?>" download class="view-btn"><i class="fas fa-download"></i></a>
                <?php endif; ?>
                <a href="?id=<?php echo $patient_id; ?>&del_rep=<?php echo $r['report_id']; ?>"
                   class="del-btn no-print" onclick="return confirm('Delete this report?')">
                    <i class="fas fa-trash"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</div><!-- /container -->

<script>
const repDefaults = <?php echo json_encode($default_charges); ?>;
function setRepCharge(type) {
    document.getElementById('rep_charge_inp').value = repDefaults[type] || 0;
}
</script>
</body>
</html>
