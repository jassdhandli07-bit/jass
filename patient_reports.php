<?php
session_start();
if(!isset($_SESSION['user'])) { header("Location: index.php"); exit(); }
include 'db_connect.php';

if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php?error=Invalid+patient+ID"); exit();
}
$patient_id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT p.*, d.name as doctor_name FROM patients p LEFT JOIN doctors d ON p.assigned_doctor_id = d.doctor_id WHERE p.patient_id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();
if(!$patient) { header("Location: dashboard.php?error=Patient+not+found"); exit(); }

$success = ""; $error = "";

// Default charges per report type
$default_charges = [
    'X-Ray'      => 500,
    'Blood Test' => 300,
    'MRI Scan'   => 3500,
    'CT Scan'    => 2500,
    'Urine Test' => 150,
    'ECG'        => 400,
    'Ultrasound' => 800,
    'Other'      => 200,
];

$type_icons = [
    'X-Ray'      => 'fa-x-ray',
    'Blood Test' => 'fa-tint',
    'MRI Scan'   => 'fa-brain',
    'CT Scan'    => 'fa-radiation',
    'Urine Test' => 'fa-flask',
    'ECG'        => 'fa-heartbeat',
    'Ultrasound' => 'fa-wave-square',
    'Other'      => 'fa-file-medical',
];
$type_colors = [
    'X-Ray'      => '#4f46e5',
    'Blood Test' => '#ef4444',
    'MRI Scan'   => '#8b5cf6',
    'CT Scan'    => '#06b6d4',
    'Urine Test' => '#f59e0b',
    'ECG'        => '#10b981',
    'Ultrasound' => '#3b82f6',
    'Other'      => '#64748b',
];

// Add report
if(isset($_POST['add_report'])) {
    $report_type  = trim($_POST['report_type']);
    $report_title = trim($_POST['report_title']);
    $description  = trim($_POST['description']);
    $report_date  = trim($_POST['report_date']);
    $charge       = (float)($_POST['charge'] ?? 0);
    $file_path = null; $file_name = null;

    if(empty($report_title) || empty($report_date)) {
        $error = "Title and date are required.";
    } else {
        if(isset($_FILES['report_file']) && $_FILES['report_file']['error'] == 0) {
            $allowed = ['jpg','jpeg','png','gif','pdf','doc','docx'];
            $ext = strtolower(pathinfo($_FILES['report_file']['name'], PATHINFO_EXTENSION));
            if(!in_array($ext, $allowed)) {
                $error = "File type not allowed.";
            } elseif($_FILES['report_file']['size'] > 5000000) {
                $error = "File too large. Max 5MB.";
            } else {
                $upload_dir = "reports/";
                if(!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $file_name = $_FILES['report_file']['name'];
                $file_path = $upload_dir . uniqid() . "_" . time() . "." . $ext;
                if(!move_uploaded_file($_FILES['report_file']['tmp_name'], $file_path)) {
                    $error = "File upload failed."; $file_path = null;
                }
            }
        }
        if(empty($error)) {
            $stmt = $conn->prepare("INSERT INTO patient_reports (patient_id, report_type, report_title, description, charge, file_path, file_name, report_date) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param("isssdsss", $patient_id, $report_type, $report_title, $description, $charge, $file_path, $file_name, $report_date);
            if($stmt->execute()) {
                $success = "Report added successfully!";
            } else {
                $error = "Failed: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Delete report
if(isset($_GET['delete_report']) && is_numeric($_GET['delete_report'])) {
    $rid = (int)$_GET['delete_report'];
    $s = $conn->prepare("SELECT file_path FROM patient_reports WHERE report_id=? AND patient_id=?");
    $s->bind_param("ii", $rid, $patient_id);
    $s->execute();
    $rrow = $s->get_result()->fetch_assoc();
    $s->close();
    if($rrow) {
        if($rrow['file_path'] && file_exists($rrow['file_path'])) unlink($rrow['file_path']);
        $d = $conn->prepare("DELETE FROM patient_reports WHERE report_id=?");
        $d->bind_param("i", $rid); $d->execute(); $d->close();
        $success = "Report deleted.";
    }
}

// Fetch all reports
$rs = $conn->prepare("SELECT * FROM patient_reports WHERE patient_id=? ORDER BY report_date DESC");
$rs->bind_param("i", $patient_id);
$rs->execute();
$all_reports = $rs->get_result()->fetch_all(MYSQLI_ASSOC);
$rs->close();

// Total charges
$total_report_charges = array_sum(array_column($all_reports, 'charge'));

// Counts per type
$counts = [];
foreach($all_reports as $r) $counts[$r['report_type']] = ($counts[$r['report_type']] ?? 0) + 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo htmlspecialchars($patient['name']); ?></title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .patient-header { background: linear-gradient(135deg, var(--primary-dark), var(--primary)); color:#fff; padding:24px 32px; border-radius:var(--radius); margin-bottom:28px; display:flex; align-items:center; gap:20px; flex-wrap:wrap; }
        .patient-header h2 { font-size:1.4rem; margin-bottom:4px; }
        .patient-header p  { opacity:0.85; font-size:0.88rem; }
        .ph-badges { display:flex; gap:8px; flex-wrap:wrap; margin-top:8px; }
        .ph-badge  { background:rgba(255,255,255,0.2); padding:4px 12px; border-radius:20px; font-size:0.8rem; font-weight:600; }

        .charge-summary { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:14px; margin-bottom:28px; }
        .charge-box { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:16px; text-align:center; box-shadow:var(--shadow-sm); }
        .charge-box .cb-icon { font-size:1.6rem; margin-bottom:8px; display:block; }
        .charge-box .cb-type { font-size:0.78rem; color:var(--text-muted); font-weight:600; margin-bottom:4px; }
        .charge-box .cb-amount { font-size:1.1rem; font-weight:700; color:var(--primary); }
        .charge-box .cb-count  { font-size:0.75rem; color:var(--text-muted); }
        .total-charge-box { background:linear-gradient(135deg,var(--primary-dark),var(--primary)); color:#fff; border-radius:var(--radius); padding:20px 28px; margin-bottom:28px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; }
        .total-charge-box h3 { font-size:1rem; opacity:0.9; margin-bottom:4px; }
        .total-charge-box .big-amount { font-size:2rem; font-weight:700; }

        .report-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:24px; }
        .tab-btn { padding:8px 18px; border-radius:20px; border:1.5px solid var(--border); background:var(--surface); color:var(--text-muted); font-size:0.85rem; font-weight:600; cursor:pointer; transition:all 0.2s; font-family:inherit; display:inline-flex; align-items:center; gap:6px; }
        .tab-btn.active,.tab-btn:hover { background:var(--primary); color:#fff; border-color:var(--primary); }
        .tab-btn .cnt { background:rgba(255,255,255,0.25); border-radius:10px; padding:1px 7px; font-size:0.75rem; }

        .report-section { display:none; }
        .report-section.active { display:block; }

        .report-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:18px 22px; margin-bottom:12px; display:flex; align-items:flex-start; gap:16px; box-shadow:var(--shadow-sm); transition:box-shadow 0.2s; }
        .report-card:hover { box-shadow:var(--shadow-md); }
        .report-icon { width:50px; height:50px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; flex-shrink:0; color:#fff; }
        .report-info { flex:1; }
        .report-info h4 { font-size:0.98rem; font-weight:700; color:var(--text); margin-bottom:4px; }
        .report-info p  { font-size:0.84rem; color:var(--text-muted); margin:3px 0; }
        .report-charge-badge { display:inline-flex; align-items:center; gap:4px; background:#fef3c7; color:#92400e; border-radius:20px; padding:3px 12px; font-size:0.82rem; font-weight:700; margin-top:6px; }
        .report-meta { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-top:6px; }
        .report-date { font-size:0.8rem; color:var(--text-muted); display:flex; align-items:center; gap:5px; }
        .report-actions { display:flex; gap:8px; flex-shrink:0; align-items:flex-start; }
        .btn-view   { padding:7px 14px; background:var(--primary-light); color:var(--primary); border-radius:var(--radius-sm); text-decoration:none; font-size:0.82rem; font-weight:600; display:inline-flex; align-items:center; gap:5px; transition:all 0.2s; }
        .btn-view:hover { background:var(--primary); color:#fff; }
        .btn-del-sm { padding:7px 14px; background:#fee2e2; color:var(--danger); border-radius:var(--radius-sm); text-decoration:none; font-size:0.82rem; font-weight:600; display:inline-flex; align-items:center; gap:5px; transition:all 0.2s; }
        .btn-del-sm:hover { background:var(--danger); color:#fff; }
        .no-reports { text-align:center; padding:40px; color:var(--text-muted); background:var(--surface); border-radius:var(--radius); border:1px dashed var(--border); }
        .no-reports i { font-size:2.5rem; margin-bottom:12px; display:block; opacity:0.3; }
        .upload-panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:28px; margin-bottom:28px; box-shadow:var(--shadow-sm); }
        .upload-panel h3 { font-size:1.05rem; font-weight:700; margin-bottom:20px; display:flex; align-items:center; gap:8px; color:var(--text); }
        .upload-panel h3 i { color:var(--primary); }
        @media print { .navbar,.upload-panel,.report-tabs,.report-actions,.no-print { display:none!important; } body { background:white!important; } }
    </style>
</head>
<body>
<div class="navbar no-print">
    <span class="navbar-brand"><i class="fas fa-hospital"></i> Hospital MS</span>
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="add_patient.php"><i class="fas fa-user-injured"></i> Add Patient</a>
    <a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
    <a href="rooms.php"><i class="fas fa-door-open"></i> Rooms</a>
    <a href="search_patient.php"><i class="fas fa-search"></i> Search</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="container">
    <?php if($success): ?>
        <div class="alert alert-success no-print"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-error no-print"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Patient Header -->
    <div class="patient-header">
        <div style="width:60px;height:60px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.8rem;">
            <i class="fas fa-user"></i>
        </div>
        <div style="flex:1;">
            <h2><?php echo htmlspecialchars($patient['name']); ?></h2>
            <p>ID: #<?php echo $patient_id; ?> &nbsp;|&nbsp; <?php echo $patient['age']; ?> yrs / <?php echo $patient['gender']; ?></p>
            <div class="ph-badges">
                <span class="ph-badge"><i class="fas fa-virus"></i> <?php echo htmlspecialchars($patient['disease'] ?? 'N/A'); ?></span>
                <span class="ph-badge"><i class="fas fa-user-md"></i> <?php echo htmlspecialchars($patient['doctor_name'] ?? 'Unassigned'); ?></span>
                <span class="ph-badge"><i class="fas fa-calendar-check"></i> <?php echo $patient['admission_date'] ?? 'N/A'; ?></span>
            </div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;" class="no-print">
            <a href="patient_bill.php?id=<?php echo $patient_id; ?>" class="btn-primary" style="background:rgba(255,255,255,0.2);box-shadow:none;border:1px solid rgba(255,255,255,0.4);">
                <i class="fas fa-file-invoice-dollar"></i> Bill
            </a>
            <button onclick="window.print()" class="btn-primary" style="background:rgba(255,255,255,0.2);box-shadow:none;border:1px solid rgba(255,255,255,0.4);">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Total Charges Summary -->
    <?php if(!empty($all_reports)): ?>
    <div class="total-charge-box no-print">
        <div>
            <h3><i class="fas fa-file-medical-alt"></i> Total Report Charges</h3>
            <div class="big-amount">₹<?php echo number_format($total_report_charges, 2); ?></div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:0.9rem;opacity:0.85;"><?php echo count($all_reports); ?> report(s) on file</div>
            <a href="patient_bill.php?id=<?php echo $patient_id; ?>&report_charges=<?php echo $total_report_charges; ?>"
               class="btn-primary" style="margin-top:10px;background:rgba(255,255,255,0.2);box-shadow:none;border:1px solid rgba(255,255,255,0.4);">
                <i class="fas fa-plus"></i> Add to Bill
            </a>
        </div>
    </div>

    <!-- Per-type charge summary -->
    <div class="charge-summary no-print">
        <?php
        $type_totals = [];
        foreach($all_reports as $r) {
            $type_totals[$r['report_type']]['amount'] = ($type_totals[$r['report_type']]['amount'] ?? 0) + $r['charge'];
            $type_totals[$r['report_type']]['count']  = ($type_totals[$r['report_type']]['count']  ?? 0) + 1;
        }
        foreach($type_totals as $type => $data):
        ?>
        <div class="charge-box">
            <span class="cb-icon" style="color:<?php echo $type_colors[$type]; ?>">
                <i class="fas <?php echo $type_icons[$type]; ?>"></i>
            </span>
            <div class="cb-type"><?php echo $type; ?></div>
            <div class="cb-amount">₹<?php echo number_format($data['amount'], 2); ?></div>
            <div class="cb-count"><?php echo $data['count']; ?> test(s)</div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Upload Form -->
    <div class="upload-panel no-print">
        <h3><i class="fas fa-upload"></i> Add New Report</h3>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Report Type *</label>
                    <select name="report_type" id="report_type_sel" required onchange="setDefaultCharge(this.value)">
                        <?php foreach($default_charges as $rt => $dc): ?>
                            <option value="<?php echo $rt; ?>"><?php echo $rt; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-rupee-sign"></i> Charge Amount (₹) *</label>
                    <input type="number" name="charge" id="charge_input" step="0.01" min="0"
                           value="<?php echo $default_charges['X-Ray']; ?>" required>
                    <small style="color:var(--text-muted);font-size:0.78rem;">Default charges auto-filled. You can edit.</small>
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
                    <label><i class="fas fa-align-left"></i> Description / Findings</label>
                    <textarea name="description" rows="2" placeholder="Enter findings or observations..."></textarea>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-paperclip"></i> Attach File (JPG/PNG/PDF, max 5MB)</label>
                    <input type="file" name="report_file" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" name="add_report" class="btn-primary"><i class="fas fa-plus"></i> Add Report</button>
            </div>
        </form>
    </div>

    <!-- Tabs -->
    <div class="report-tabs no-print">
        <button class="tab-btn active" onclick="showTab('all',this)">
            <i class="fas fa-list"></i> All <span class="cnt"><?php echo count($all_reports); ?></span>
        </button>
        <?php foreach(array_keys($default_charges) as $rt):
            $c = $counts[$rt] ?? 0; if(!$c) continue; ?>
        <button class="tab-btn" onclick="showTab('<?php echo str_replace(' ','-',$rt); ?>',this)">
            <i class="fas <?php echo $type_icons[$rt]; ?>"></i>
            <?php echo $rt; ?> <span class="cnt"><?php echo $c; ?></span>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- All tab -->
    <div id="tab-all" class="report-section active">
        <?php if(empty($all_reports)): ?>
            <div class="no-reports"><i class="fas fa-file-medical"></i><p>No reports yet. Add one above.</p></div>
        <?php else: ?>
            <?php foreach($all_reports as $r): ?>
            <div class="report-card">
                <div class="report-icon" style="background:<?php echo $type_colors[$r['report_type']] ?? '#4f46e5'; ?>">
                    <i class="fas <?php echo $type_icons[$r['report_type']] ?? 'fa-file-medical'; ?>"></i>
                </div>
                <div class="report-info">
                    <h4><?php echo htmlspecialchars($r['report_title']); ?></h4>
                    <p><strong><?php echo $r['report_type']; ?></strong></p>
                    <?php if($r['description']): ?>
                        <p><?php echo htmlspecialchars($r['description']); ?></p>
                    <?php endif; ?>
                    <span class="report-charge-badge"><i class="fas fa-rupee-sign"></i> ₹<?php echo number_format($r['charge'],2); ?></span>
                    <div class="report-meta">
                        <span class="report-date"><i class="fas fa-calendar"></i> <?php echo $r['report_date']; ?></span>
                        <?php if($r['file_name']): ?>
                            <span class="report-date"><i class="fas fa-paperclip"></i> <?php echo htmlspecialchars($r['file_name']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="report-actions no-print">
                    <?php if($r['file_path'] && file_exists($r['file_path'])): ?>
                        <a href="<?php echo $r['file_path']; ?>" target="_blank" class="btn-view"><i class="fas fa-eye"></i> View</a>
                        <a href="<?php echo $r['file_path']; ?>" download class="btn-view"><i class="fas fa-download"></i></a>
                    <?php endif; ?>
                    <a href="?id=<?php echo $patient_id; ?>&delete_report=<?php echo $r['report_id']; ?>"
                       class="btn-del-sm" onclick="return confirm('Delete this report?')">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Per-type tabs -->
    <?php foreach(array_keys($default_charges) as $rt):
        $tab_id  = 'tab-' . str_replace(' ','-',$rt);
        $filtered = array_filter($all_reports, fn($r) => $r['report_type'] == $rt);
        $type_total = array_sum(array_column(array_values($filtered), 'charge'));
    ?>
    <div id="<?php echo $tab_id; ?>" class="report-section">
        <?php if(empty($filtered)): ?>
            <div class="no-reports"><i class="fas <?php echo $type_icons[$rt]; ?>"></i><p>No <?php echo $rt; ?> reports.</p></div>
        <?php else: ?>
            <div style="background:var(--primary-light);color:var(--primary);padding:12px 18px;border-radius:var(--radius-sm);margin-bottom:16px;font-weight:700;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-rupee-sign"></i>
                Total <?php echo $rt; ?> Charges: ₹<?php echo number_format($type_total,2); ?>
                (<?php echo count($filtered); ?> test<?php echo count($filtered)>1?'s':''; ?>)
            </div>
            <?php foreach($filtered as $r): ?>
            <div class="report-card">
                <div class="report-icon" style="background:<?php echo $type_colors[$rt]; ?>">
                    <i class="fas <?php echo $type_icons[$rt]; ?>"></i>
                </div>
                <div class="report-info">
                    <h4><?php echo htmlspecialchars($r['report_title']); ?></h4>
                    <?php if($r['description']): ?><p><?php echo htmlspecialchars($r['description']); ?></p><?php endif; ?>
                    <span class="report-charge-badge"><i class="fas fa-rupee-sign"></i> ₹<?php echo number_format($r['charge'],2); ?></span>
                    <div class="report-meta">
                        <span class="report-date"><i class="fas fa-calendar"></i> <?php echo $r['report_date']; ?></span>
                        <?php if($r['file_name']): ?><span class="report-date"><i class="fas fa-paperclip"></i> <?php echo htmlspecialchars($r['file_name']); ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="report-actions no-print">
                    <?php if($r['file_path'] && file_exists($r['file_path'])): ?>
                        <a href="<?php echo $r['file_path']; ?>" target="_blank" class="btn-view"><i class="fas fa-eye"></i> View</a>
                        <a href="<?php echo $r['file_path']; ?>" download class="btn-view"><i class="fas fa-download"></i></a>
                    <?php endif; ?>
                    <a href="?id=<?php echo $patient_id; ?>&delete_report=<?php echo $r['report_id']; ?>"
                       class="btn-del-sm" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<script>
const defaultCharges = <?php echo json_encode($default_charges); ?>;
function setDefaultCharge(type) {
    document.getElementById('charge_input').value = defaultCharges[type] || 0;
}
function showTab(name, btn) {
    document.querySelectorAll('.report-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}
</script>
</body>
</html>
