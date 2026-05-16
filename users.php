<?php
require_once 'connect.php';
requireAdmin();

$action  = $_GET['action'] ?? 'list';
$msg     = '';
$msgType = 'success';

// --- DELETE ---
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($id == $_SESSION['user_id']) {
        $msg = 'You cannot delete your own account.'; $msgType = 'danger';
    } else {
        $connection->query("DELETE FROM tbluser WHERE UserID = $id");
        $msg = 'User deleted successfully.';
    }
    $action = 'list';
}

// --- SAVE (Add / Edit) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = intval($_POST['hdnID'] ?? 0);
    $fname   = trim($_POST['txtfirstname'] ?? '');
    $mname   = trim($_POST['txtmiddlename'] ?? '');
    $lname   = trim($_POST['txtlastname'] ?? '');
    $email   = trim($_POST['txtemail'] ?? '');
    $uname   = trim($_POST['txtusername'] ?? '');
    $role    = $_POST['txtrole'] ?? 'student';
    $isActive= intval($_POST['isActive'] ?? 1);
    $pword   = $_POST['txtpassword'] ?? '';

    if ($id === 0) {
        // INSERT
        $hashed = password_hash($pword, PASSWORD_DEFAULT);
        $isS = ($role === 'student') ? 1 : 0;
        $isO = ($role === 'organization') ? 1 : 0;
        $stmt = $connection->prepare(
            "INSERT INTO tbluser (Institutional_Email,FirstName,MiddleName,LastName,isActive,isOrganization,isStudent,Username,Password,Role)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->bind_param('ssssiiiiss', $email,$fname,$mname,$lname,$isActive,$isO,$isS,$uname,$hashed,$role);
        if ($stmt->execute()) $msg = 'User added successfully.';
        else { $msg = 'Error: ' . $stmt->error; $msgType = 'danger'; }
        $stmt->close();
    } else {
        // UPDATE
        if (!empty($pword)) {
            $hashed = password_hash($pword, PASSWORD_DEFAULT);
            $stmt = $connection->prepare(
                "UPDATE tbluser SET Institutional_Email=?,FirstName=?,MiddleName=?,LastName=?,isActive=?,Role=?,Username=?,Password=? WHERE UserID=?"
            );
            $stmt->bind_param('ssssisssi', $email,$fname,$mname,$lname,$isActive,$role,$uname,$hashed,$id);
        } else {
            $stmt = $connection->prepare(
                "UPDATE tbluser SET Institutional_Email=?,FirstName=?,MiddleName=?,LastName=?,isActive=?,Role=?,Username=? WHERE UserID=?"
            );
            $stmt->bind_param('ssssissi', $email,$fname,$mname,$lname,$isActive,$role,$uname,$id);
        }
        if ($stmt->execute()) $msg = 'User updated successfully.';
        else { $msg = 'Error: ' . $stmt->error; $msgType = 'danger'; }
        $stmt->close();
    }
    $action = 'list';
}

// Load edit record
$editRow = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $r  = $connection->query("SELECT * FROM tbluser WHERE UserID = $id");
    $editRow = $r->fetch_assoc();
    if (!$editRow) { $action = 'list'; }
}

// List
$search = trim($_GET['q'] ?? '');
$where  = '';
if ($search) {
    $s = $connection->real_escape_string($search);
    $where = "WHERE Username LIKE '%$s%' OR FirstName LIKE '%$s%' OR LastName LIKE '%$s%' OR Institutional_Email LIKE '%$s%'";
}
$users = $connection->query("SELECT * FROM tbluser $where ORDER BY CreatedAt DESC");

$pageTitle = 'Users Management';
require_once 'includes/header.php';
?>

<?php require_once "includes/navbar.php"; ?>
<div class="layout-top">
<div class="page-wrapper">

<div class="page-header">
    <div class="page-title"><h1>Users Management</h1><p>Add, edit, and manage all system users</p></div>
    <div style="display:flex;gap:10px;"><a href="?action=add" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add User</a></div>
</div>
    <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msgType; ?> auto-dismiss"><i class="fas fa-<?php echo $msgType==='success'?'circle-check':'circle-xmark'; ?>"></i> <?php echo $msg; ?></div>
    <?php endif; ?>

    <?php if ($action === 'add' || $action === 'edit'): ?>
    <!-- Form -->
    <div class="card" style="max-width:700px;margin-bottom:24px;">
        <div class="card-header">
            <div>
                <h3><?php echo $action === 'edit' ? 'Edit User' : 'Add New User'; ?></h3>
                <p><?php echo $action === 'edit' ? 'Modify user account details' : 'Create a new system user account'; ?></p>
            </div>
            <a href="users.php" class="btn btn-outline btn-sm"><i class="fas fa-xmark"></i> Cancel</a>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="hdnID" value="<?php echo $editRow['UserID'] ?? 0; ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="txtfirstname" value="<?php echo htmlspecialchars($editRow['FirstName'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="txtmiddlename" value="<?php echo htmlspecialchars($editRow['MiddleName'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="txtlastname" value="<?php echo htmlspecialchars($editRow['LastName'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Institutional Email <span class="required">*</span></label>
                        <input type="email" name="txtemail" value="<?php echo htmlspecialchars($editRow['Institutional_Email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Username <span class="required">*</span></label>
                        <input type="text" name="txtusername" value="<?php echo htmlspecialchars($editRow['Username'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Role <span class="required">*</span></label>
                        <select name="txtrole">
                            <option value="admin" <?php if(($editRow['Role']??'')=='admin') echo 'selected'; ?>>Admin</option>
                            <option value="student" <?php if(($editRow['Role']??'student')=='student') echo 'selected'; ?>>Student</option>
                            <option value="organization" <?php if(($editRow['Role']??'')=='organization') echo 'selected'; ?>>Organization</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="isActive">
                            <option value="1" <?php if(($editRow['isActive']??1)==1) echo 'selected'; ?>>Active</option>
                            <option value="0" <?php if(($editRow['isActive']??1)==0) echo 'selected'; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Password <?php echo $action==='add' ? '<span class="required">*</span>' : '<small style="color:var(--gray-400);font-weight:400;">(leave blank to keep)</small>'; ?></label>
                        <input type="password" name="txtpassword" <?php echo $action==='add'?'required':''; ?>>
                    </div>
                </div>
                <div style="margin-top:20px;display:flex;gap:10px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $action==='edit'?'Update User':'Add User'; ?></button>
                    <a href="users.php" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Table -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3>All Users</h3>
                <p><?php echo $users ? $users->num_rows : 0; ?> user(s) found</p>
            </div>
            <form method="get" style="display:flex;gap:8px;align-items:center;">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <button type="submit" class="btn btn-outline btn-sm"><i class="fas fa-filter"></i> Filter</button>
                <?php if($search): ?><a href="users.php" class="btn btn-outline btn-sm"><i class="fas fa-xmark"></i></a><?php endif; ?>
            </form>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users && $users->num_rows > 0):
                        while ($row = $users->fetch_assoc()): ?>
                    <tr>
                        <td class="mono"><?php echo $row['UserID']; ?></td>
                        <td>
                            <div style="font-weight:600;"><?php echo htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']); ?></div>
                            <?php if($row['MiddleName']): ?><div style="font-size:11px;color:var(--gray-400);"><?php echo htmlspecialchars($row['MiddleName']); ?></div><?php endif; ?>
                        </td>
                        <td style="font-size:12px;"><?php echo htmlspecialchars($row['Institutional_Email']); ?></td>
                        <td style="font-size:12px;"><?php echo htmlspecialchars($row['Username']); ?></td>
                        <td>
                            <?php $rcls = $row['Role']==='admin'?'badge-danger':($row['Role']==='student'?'badge-info':'badge-warning'); ?>
                            <span class="badge <?php echo $rcls; ?>"><?php echo ucfirst($row['Role']); ?></span>
                        </td>
                        <td>
                            <?php if($row['isActive']): ?>
                            <span class="badge badge-success"><i class="fas fa-circle" style="font-size:7px;"></i> Active</span>
                            <?php else: ?>
                            <span class="badge badge-gray">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px;color:var(--gray-500);"><?php echo date('M j, Y', strtotime($row['CreatedAt'])); ?></td>
                        <td>
                            <a href="?action=edit&id=<?php echo $row['UserID']; ?>" class="btn btn-outline btn-sm"><i class="fas fa-pen"></i></a>
                            <a href="?action=delete&id=<?php echo $row['UserID']; ?>" class="btn btn-danger btn-sm confirm-delete" style="margin-left:4px;"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="8">
                        <div class="empty-state">
                            <i class="fas fa-users-slash"></i>
                            <h3>No users found</h3>
                            <p><?php echo $search ? 'Try a different search term.' : 'Add your first user to get started.'; ?></p>
                        </div>
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<?php require_once 'includes/footer.php'; ?>
