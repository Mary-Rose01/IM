<?php
require_once 'connect.php';
requireAdmin();

$action  = $_GET['action'] ?? 'list';
$msg     = '';
$msgType = 'success';

// --- DELETE ---
if ($action === 'delete' && isset($_GET['id'])) {
    $id = $connection->real_escape_string($_GET['id']);
    $connection->query("DELETE FROM tblstudent WHERE StudentID = '$id'");
    $msg = 'Student record deleted.';
    $action = 'list';
}

// --- SAVE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldID    = trim($_POST['hdnID'] ?? '');
    $sid      = trim($_POST['txtstudentid'] ?? '');
    $program  = trim($_POST['txtprogram'] ?? '');
    $year     = intval($_POST['numyearlevel'] ?? 1);
    $dept     = trim($_POST['txtdepartment'] ?? '');
    $userID   = intval($_POST['hdnUserID'] ?? 0);

    if (empty($oldID)) {
        // INSERT
        $stmt = $connection->prepare("INSERT INTO tblstudent (StudentID,Program,YearLevel,Department,UserID) VALUES (?,?,?,?,?)");
        $stmt->bind_param('ssisi', $sid,$program,$year,$dept,$userID);
        if ($stmt->execute()) $msg = 'Student added successfully.';
        else { $msg = 'Error: ' . $stmt->error; $msgType = 'danger'; }
        $stmt->close();
    } else {
        // UPDATE
        $stmt = $connection->prepare("UPDATE tblstudent SET StudentID=?,Program=?,YearLevel=?,Department=? WHERE StudentID=?");
        $stmt->bind_param('ssiss', $sid,$program,$year,$dept,$oldID);
        if ($stmt->execute()) $msg = 'Student updated successfully.';
        else { $msg = 'Error: ' . $stmt->error; $msgType = 'danger'; }
        $stmt->close();
    }
    $action = 'list';
}

$editRow = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = $connection->real_escape_string($_GET['id']);
    $r = $connection->query(
        "SELECT s.*, CONCAT(u.FirstName,' ',u.LastName) as FullName, u.Institutional_Email
         FROM tblstudent s JOIN tbluser u ON s.UserID = u.UserID
         WHERE s.StudentID = '$id'"
    );
    $editRow = $r->fetch_assoc();
}

// List
$search = trim($_GET['q'] ?? '');
$where  = '';
if ($search) {
    $s = $connection->real_escape_string($search);
    $where = "WHERE s.StudentID LIKE '%$s%' OR u.FirstName LIKE '%$s%' OR u.LastName LIKE '%$s%' OR s.Program LIKE '%$s%'";
}
$students = $connection->query(
    "SELECT s.*, CONCAT(u.FirstName,' ',u.MiddleName,' ',u.LastName) as FullName, u.Institutional_Email
     FROM tblstudent s JOIN tbluser u ON s.UserID = u.UserID $where ORDER BY s.StudentID"
);

// Available users (for add)
$availUsers = $connection->query(
    "SELECT u.UserID, CONCAT(u.FirstName,' ',u.LastName) as Name, u.Institutional_Email
     FROM tbluser u WHERE u.isStudent = 1
     AND u.UserID NOT IN (SELECT UserID FROM tblstudent)
     ORDER BY u.FirstName"
);

$pageTitle = 'Students Management';
require_once 'includes/header.php';
?>

<?php require_once "includes/navbar.php"; ?>
<div class="layout-top">
<div class="page-wrapper">

<div class="page-header">
    <div class="page-title"><h1>Students Management</h1><p>Manage student profiles and academic information</p></div>
    <div style="display:flex;gap:10px;"><a href="?action=add" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Student</a></div>
</div>
    <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msgType; ?> auto-dismiss"><i class="fas fa-circle-check"></i> <?php echo $msg; ?></div>
    <?php endif; ?>

    <?php if ($action === 'add' || $action === 'edit'): ?>
    <div class="card" style="max-width:660px;margin-bottom:24px;">
        <div class="card-header">
            <div><h3><?php echo $action==='edit'?'Edit Student':'Add Student'; ?></h3></div>
            <a href="students.php" class="btn btn-outline btn-sm"><i class="fas fa-xmark"></i> Cancel</a>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="hdnID" value="<?php echo htmlspecialchars($editRow['StudentID'] ?? ''); ?>">
                <input type="hidden" name="hdnUserID" value="<?php echo $editRow['UserID'] ?? ''; ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Student ID <span class="required">*</span></label>
                        <input type="text" name="txtstudentid" value="<?php echo htmlspecialchars($editRow['StudentID'] ?? ''); ?>" placeholder="22-1234-567" required>
                    </div>
                    <?php if ($action === 'add'): ?>
                    <div class="form-group">
                        <label>Link to User Account</label>
                        <select name="hdnUserID">
                            <option value="0">-- Select User --</option>
                            <?php while($u = $availUsers->fetch_assoc()): ?>
                            <option value="<?php echo $u['UserID']; ?>"><?php echo htmlspecialchars($u['Name']); ?> (<?php echo $u['Institutional_Email']; ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                        <label>User Account</label>
                        <input type="text" value="<?php echo htmlspecialchars($editRow['FullName'] ?? ''); ?>" disabled style="background:var(--gray-50);color:var(--gray-500);">
                    </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label>Program <span class="required">*</span></label>
                        <input type="text" name="txtprogram" value="<?php echo htmlspecialchars($editRow['Program'] ?? ''); ?>" placeholder="e.g. BSCS" required>
                    </div>
                    <div class="form-group">
                        <label>Year Level <span class="required">*</span></label>
                        <select name="numyearlevel">
                            <?php for($i=1;$i<=5;$i++): ?>
                            <option value="<?php echo $i; ?>" <?php if(($editRow['YearLevel']??1)==$i) echo 'selected'; ?>>Year <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group span-2">
                        <label>Department <span class="required">*</span></label>
                        <select name="txtdepartment">
                            <option value="">-- Select Department --</option>
                            <?php
                            $depts = [
                                'College of Computer Studies',
                                'College of Architecture and Engineering',
                                'College of Nursing and Allied Health Sciences',
                                'College of Arts, Sciences and Education',
                                'College of Management, Business and Accountancy',
                                'College of Criminal Justice',
                            ];
                            foreach ($depts as $d): ?>
                            <option value="<?php echo $d; ?>" <?php if(($editRow['Department']??'')===$d) echo 'selected'; ?>><?php echo $d; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="margin-top:20px;display:flex;gap:10px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $action==='edit'?'Update':'Add'; ?> Student</button>
                    <a href="students.php" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div><h3>All Students</h3><p><?php echo $students ? $students->num_rows : 0; ?> student(s)</p></div>
            <form method="get" style="display:flex;gap:8px;">
                <div class="search-bar"><i class="fas fa-search"></i>
                    <input type="text" name="q" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <button class="btn btn-outline btn-sm">Filter</button>
                <?php if($search): ?><a href="students.php" class="btn btn-outline btn-sm"><i class="fas fa-xmark"></i></a><?php endif; ?>
            </form>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Program</th>
                        <th>Year</th>
                        <th>Department</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($students && $students->num_rows > 0):
                        while ($row = $students->fetch_assoc()): ?>
                    <tr>
                        <td class="mono"><?php echo htmlspecialchars($row['StudentID']); ?></td>
                        <td style="font-weight:600;"><?php echo htmlspecialchars($row['FullName']); ?></td>
                        <td style="font-size:12px;"><?php echo htmlspecialchars($row['Institutional_Email']); ?></td>
                        <td><span class="badge badge-info"><?php echo htmlspecialchars($row['Program']); ?></span></td>
                        <td style="text-align:center;">Year <?php echo $row['YearLevel']; ?></td>
                        <td style="font-size:12px;color:var(--gray-500);"><?php echo htmlspecialchars($row['Department']); ?></td>
                        <td>
                            <a href="?action=edit&id=<?php echo urlencode($row['StudentID']); ?>" class="btn btn-outline btn-sm"><i class="fas fa-pen"></i></a>
                            <a href="?action=delete&id=<?php echo urlencode($row['StudentID']); ?>" class="btn btn-danger btn-sm confirm-delete" style="margin-left:4px;"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="7"><div class="empty-state"><i class="fas fa-user-graduate"></i><h3>No students yet</h3></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<?php require_once 'includes/footer.php'; ?>
