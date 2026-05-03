<?php
require_once 'connect.php';
requireAdmin();

$action = $_GET['action'] ?? 'list';
$msg = ''; $msgType = 'success';

if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $connection->query("DELETE FROM tblorganization WHERE OrgID = $id");
    $msg = 'Organization deleted.';
    $action = 'list';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = intval($_POST['hdnID'] ?? 0);
    $name     = trim($_POST['txtname'] ?? '');
    $type     = trim($_POST['txttype'] ?? '');
    $accred   = intval($_POST['accred'] ?? 1);
    $cemail   = trim($_POST['txtemail'] ?? '');
    $userID   = intval($_POST['hdnUserID'] ?? 0);

    if ($id === 0) {
        $stmt = $connection->prepare("INSERT INTO tblorganization (OrgName,Type,Accreditation_Status,Contact_Email,UserID) VALUES (?,?,?,?,?)");
        $stmt->bind_param('ssisi', $name,$type,$accred,$cemail,$userID);
        if ($stmt->execute()) $msg = 'Organization added.';
        else { $msg = $stmt->error; $msgType = 'danger'; }
        $stmt->close();
    } else {
        $stmt = $connection->prepare("UPDATE tblorganization SET OrgName=?,Type=?,Accreditation_Status=?,Contact_Email=? WHERE OrgID=?");
        $stmt->bind_param('ssisi', $name,$type,$accred,$cemail,$id);
        if ($stmt->execute()) $msg = 'Organization updated.';
        else { $msg = $stmt->error; $msgType = 'danger'; }
        $stmt->close();
    }
    $action = 'list';
}

$editRow = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $editRow = $connection->query("SELECT o.*, CONCAT(u.FirstName,' ',u.LastName) as FullName FROM tblorganization o JOIN tbluser u ON o.UserID=u.UserID WHERE o.OrgID=$id")->fetch_assoc();
}

$search = trim($_GET['q'] ?? '');
$where = $search ? "WHERE o.OrgName LIKE '%".($connection->real_escape_string($search))."%'" : '';
$orgs = $connection->query("SELECT o.*, CONCAT(u.FirstName,' ',u.LastName) as AdminName, u.Institutional_Email FROM tblorganization o JOIN tbluser u ON o.UserID=u.UserID $where ORDER BY o.OrgName");
$orgUsers = $connection->query("SELECT u.UserID, CONCAT(u.FirstName,' ',u.LastName) as Name FROM tbluser u WHERE u.isOrganization=1 AND u.UserID NOT IN (SELECT UserID FROM tblorganization) ORDER BY u.FirstName");

$pageTitle = 'Organizations';
require_once 'includes/header.php';
?>

<?php require_once "includes/navbar.php"; ?>
<div class="layout-top">
<div class="page-wrapper">

<div class="page-header">
    <div class="page-title"><h1>Organizations</h1><p>Manage registered university organizations</p></div>
    <div style="display:flex;gap:10px;"><a href="?action=add" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Organization</a></div>
</div>
    <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msgType; ?> auto-dismiss"><i class="fas fa-circle-check"></i> <?php echo $msg; ?></div>
    <?php endif; ?>

    <?php if ($action === 'add' || $action === 'edit'): ?>
    <div class="card" style="max-width:660px;margin-bottom:24px;">
        <div class="card-header">
            <div><h3><?php echo $action==='edit'?'Edit Organization':'Add Organization'; ?></h3></div>
            <a href="organizations.php" class="btn btn-outline btn-sm"><i class="fas fa-xmark"></i> Cancel</a>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="hdnID" value="<?php echo $editRow['OrgID'] ?? 0; ?>">
                <div class="form-grid">
                    <div class="form-group span-2">
                        <label>Organization Name <span class="required">*</span></label>
                        <input type="text" name="txtname" value="<?php echo htmlspecialchars($editRow['OrgName'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="txttype">
                            <option value="">-- Select Type --</option>
                            <?php foreach(['Academic','Cultural','Sports','Civic','Others'] as $t): ?>
                            <option value="<?php echo $t; ?>" <?php if(($editRow['Type']??'')===$t) echo 'selected'; ?>><?php echo $t; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Accreditation Status</label>
                        <select name="accred">
                            <option value="1" <?php if(($editRow['Accreditation_Status']??1)==1) echo 'selected'; ?>>Accredited (Active)</option>
                            <option value="0" <?php if(($editRow['Accreditation_Status']??1)==0) echo 'selected'; ?>>Not Accredited (Inactive)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Contact Email</label>
                        <input type="email" name="txtemail" value="<?php echo htmlspecialchars($editRow['Contact_Email'] ?? ''); ?>">
                    </div>
                    <?php if ($action === 'add'): ?>
                    <div class="form-group">
                        <label>Linked User Account</label>
                        <select name="hdnUserID">
                            <option value="0">-- Select Admin User --</option>
                            <?php while($ou=$orgUsers->fetch_assoc()): ?>
                            <option value="<?php echo $ou['UserID']; ?>"><?php echo htmlspecialchars($ou['Name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                        <label>Linked User</label>
                        <input type="text" value="<?php echo htmlspecialchars($editRow['FullName'] ?? ''); ?>" disabled style="background:var(--gray-50);color:var(--gray-500);">
                    </div>
                    <?php endif; ?>
                </div>
                <div style="margin-top:20px;display:flex;gap:10px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                    <a href="organizations.php" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div><h3>All Organizations</h3><p><?php echo $orgs->num_rows; ?> organization(s)</p></div>
            <form method="get" style="display:flex;gap:8px;">
                <div class="search-bar"><i class="fas fa-search"></i><input type="text" name="q" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>"></div>
                <button class="btn btn-outline btn-sm">Filter</button>
                <?php if($search): ?><a href="organizations.php" class="btn btn-outline btn-sm"><i class="fas fa-xmark"></i></a><?php endif; ?>
            </form>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr><th>ID</th><th>Organization Name</th><th>Type</th><th>Accreditation</th><th>Contact</th><th>Admin User</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if ($orgs->num_rows > 0): while($row=$orgs->fetch_assoc()): ?>
                    <tr>
                        <td class="mono"><?php echo $row['OrgID']; ?></td>
                        <td style="font-weight:600;"><?php echo htmlspecialchars($row['OrgName']); ?></td>
                        <td><?php if($row['Type']): ?><span class="badge badge-info"><?php echo $row['Type']; ?></span><?php else: ?>—<?php endif; ?></td>
                        <td><?php if($row['Accreditation_Status']): ?><span class="badge badge-success">Accredited</span><?php else: ?><span class="badge badge-danger">Not Accredited</span><?php endif; ?></td>
                        <td style="font-size:12px;"><?php echo htmlspecialchars($row['Contact_Email'] ?? '—'); ?></td>
                        <td style="font-size:12px;"><?php echo htmlspecialchars($row['AdminName']); ?></td>
                        <td>
                            <a href="?action=edit&id=<?php echo $row['OrgID']; ?>" class="btn btn-outline btn-sm"><i class="fas fa-pen"></i></a>
                            <a href="?action=delete&id=<?php echo $row['OrgID']; ?>" class="btn btn-danger btn-sm confirm-delete" style="margin-left:4px;"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="7"><div class="empty-state"><i class="fas fa-building"></i><h3>No organizations yet</h3></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<?php require_once 'includes/footer.php'; ?>
