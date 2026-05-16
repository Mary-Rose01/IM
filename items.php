<?php
require_once 'connect.php';
requireLogin();

$action  = $_GET['action'] ?? 'list';
$msg     = $_GET['msg'] ?? '';
$msgType = 'success';
$isAdmin = ($_SESSION['role'] === 'admin');

// --- DELETE ---
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    // Only admin or owner can delete
    $check = $connection->query("SELECT OwnerUserID FROM tblitem WHERE ItemID = $id")->fetch_assoc();
    if ($isAdmin || ($check && $check['OwnerUserID'] == $_SESSION['user_id'])) {
        $connection->query("DELETE FROM tblitem WHERE ItemID = $id");
        $msg = 'Item deleted.';
    } else {
        $msg = 'Permission denied.'; $msgType = 'danger';
    }
    $action = 'list';
}

// --- SAVE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = intval($_POST['hdnID'] ?? 0);
    $name   = trim($_POST['txtname'] ?? '');
    $desc   = trim($_POST['txtdesc'] ?? '');
    $cat    = trim($_POST['txtcategory'] ?? '');
    $status = trim($_POST['txtstatus'] ?? 'Good');
    $avail  = trim($_POST['txtavail'] ?? 'Available');
    $owner  = $isAdmin ? intval($_POST['hdnOwner'] ?? $_SESSION['user_id']) : $_SESSION['user_id'];

    if ($id === 0) {
        $stmt = $connection->prepare(
            "INSERT INTO tblitem (Item_Name,Description,Category,Status,Availability_Status,OwnerUserID) VALUES (?,?,?,?,?,?)"
        );
        $stmt->bind_param('sssssi', $name,$desc,$cat,$status,$avail,$owner);
        if ($stmt->execute()) $msg = 'Item added successfully.';
        else { $msg = 'Error: '.$stmt->error; $msgType = 'danger'; }
        $stmt->close();
    } else {
        // Security Check: Only Admin or Owner can Update
        $check = $connection->query("SELECT OwnerUserID FROM tblitem WHERE ItemID = $id")->fetch_assoc();
        if ($isAdmin || ($check && $check['OwnerUserID'] == $_SESSION['user_id'])) {
            $stmt = $connection->prepare(
                "UPDATE tblitem SET Item_Name=?,Description=?,Category=?,Status=?,Availability_Status=? WHERE ItemID=?"
            );
            $stmt->bind_param('sssssi', $name,$desc,$cat,$status,$avail,$id);
            if ($stmt->execute()) $msg = 'Item updated.';
            else { $msg = 'Error: '.$stmt->error; $msgType = 'danger'; }
            $stmt->close();
        } else {
            $msg = 'Permission denied.'; 
            $msgType = 'danger';
        }
    }
    $action = 'list';
}

$editRow = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $r = $connection->query("SELECT * FROM tblitem WHERE ItemID = $id");
    $editRow = $r->fetch_assoc();
    // Security Check: Only Admin or Owner can see Edit Form
    if (!$isAdmin && (!$editRow || $editRow['OwnerUserID'] != $_SESSION['user_id'])) {
        header("Location: items.php?msg=Permission+denied&msgType=danger");
        exit;
    }
}

$viewRow = null;
if ($action === 'view' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $r = $connection->query(
        "SELECT i.*, CONCAT(u.FirstName,' ',u.LastName) as OwnerName
         FROM tblitem i JOIN tbluser u ON i.OwnerUserID = u.UserID
         WHERE i.ItemID = $id"
    );
    $viewRow = $r->fetch_assoc();
    if (!$viewRow) {
        header("Location: items.php?msg=Item+not+found&msgType=danger");
        exit;
    }
}

// List
$search = trim($_GET['q'] ?? '');
$filterAvail = $_GET['avail'] ?? '';
$where = $isAdmin ? '1=1' : "i.Availability_Status != 'Archived'";
if ($search) { $s = $connection->real_escape_string($search); $where .= " AND (i.Item_Name LIKE '%$s%' OR i.Category LIKE '%$s%')"; }
if ($filterAvail) { $fa = $connection->real_escape_string($filterAvail); $where .= " AND i.Availability_Status = '$fa'"; }

// Pagination Logic
$limit = 10;
$p = max(1, intval($_GET['p'] ?? 1));
$off = ($p - 1) * $limit;

$totalCountResult = $connection->query("SELECT COUNT(*) c FROM tblitem i WHERE $where");
$totalCount = $totalCountResult->fetch_assoc()['c'];
$totalPages = max(1, ceil($totalCount / $limit));

$items = $connection->query(
    "SELECT i.*, CONCAT(u.FirstName,' ',u.LastName) as OwnerName
     FROM tblitem i JOIN tbluser u ON i.OwnerUserID = u.UserID
     WHERE $where ORDER BY i.CreatedAt DESC LIMIT $limit OFFSET $off"
);

$owners = $isAdmin ? $connection->query("SELECT UserID, CONCAT(FirstName,' ',LastName) as Name FROM tbluser WHERE isActive=1 ORDER BY FirstName") : null;

$pageTitle = 'Items';
require_once 'includes/header.php';
?>

<?php require_once "includes/navbar.php"; ?>
<div class="layout-top">
<div class="page-wrapper">

<div class="page-header">
    <div class="page-title"><h1>Item Catalog</h1><p>Browse and manage borrowable items</p></div>
    <?php if ($action !== 'add' && $action !== 'edit'): ?>
    <div style="display:flex;gap:10px;"><a href="?action=add" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Item</a></div>
    <?php endif; ?>
</div>
    <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msgType; ?> auto-dismiss"><i class="fas fa-circle-check"></i> <?php echo $msg; ?></div>
    <?php endif; ?>

    <?php if ($action === 'add' || $action === 'edit'): ?>
    <div class="card" style="max-width:680px;margin-bottom:24px;">
        <div class="card-header">
            <div><h3><?php echo $action==='edit'?'Edit Item':'Add New Item'; ?></h3></div>
        </div>
        <div class="card-body">
            <form method="post" id="itemForm">
                <input type="hidden" name="hdnID" value="<?php echo $editRow['ItemID'] ?? 0; ?>">
                <div class="form-grid">
                    <div class="form-group span-2">
                        <label>Item Name <span class="required">*</span></label>
                        <input type="text" name="txtname" value="<?php echo htmlspecialchars($editRow['Item_Name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="txtcategory">
                            <option value="">-- Select --</option>
                            <?php foreach(['Equipment','Apparel','Tool','Electronics','Furniture','Others'] as $c): ?>
                            <option value="<?php echo $c; ?>" <?php if(($editRow['Category']??'')===$c) echo 'selected'; ?>><?php echo $c; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Physical Status</label>
                        <select name="txtstatus">
                            <?php foreach(['Good','Damaged','Under Repair','Lost'] as $s): ?>
                            <option value="<?php echo $s; ?>" <?php if(($editRow['Status']??'Good')===$s) echo 'selected'; ?>><?php echo $s; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Availability Status</label>
                        <select name="txtavail">
                            <?php foreach(['Available','Borrowed','Reserved','Archived'] as $a): ?>
                            <option value="<?php echo $a; ?>" <?php if(($editRow['Availability_Status']??'Available')===$a) echo 'selected'; ?>><?php echo $a; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($isAdmin): ?>
                    <div class="form-group">
                        <label>Owner</label>
                        <select name="hdnOwner">
                            <?php while($o=$owners->fetch_assoc()): ?>
                            <option value="<?php echo $o['UserID']; ?>" <?php if(($editRow['OwnerUserID']??$_SESSION['user_id'])==$o['UserID']) echo 'selected'; ?>><?php echo htmlspecialchars($o['Name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="form-group span-2">
                        <label>Description</label>
                        <textarea name="txtdesc"><?php echo htmlspecialchars($editRow['Description'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div style="margin-top:20px;display:flex;gap:10px;">
                    <button type="button" onclick="validateAndShowModal()" class="btn btn-primary"><i class="fas fa-save"></i> Save Item</button>
                    <button type="button" onclick="showConfirmModal('items.php', 'Discard Changes', 'Are you sure you want to leave? Any unsaved changes will be lost.')" class="btn btn-outline">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($action === 'view' && $viewRow): ?>
    <div class="card" style="max-width:850px;margin-bottom:24px;">
        <div class="card-header">
            <div>
                <span class="badge badge-gray" style="margin-bottom:4px;"><?php echo htmlspecialchars($viewRow['Category'] ?? 'Item'); ?></span>
                <h3><?php echo htmlspecialchars($viewRow['Item_Name']); ?></h3>
            </div>
            <a href="items.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back to Catalog</a>
        </div>
        <div class="card-body">
            <div style="max-width: 650px;">
                    <div style="margin-bottom:20px;">
                        <label style="color:var(--text-muted); font-size:11px; text-transform:uppercase; letter-spacing:0.5px;">Item Status</label>
                        <div style="margin-top:6px; display:flex; gap:8px;">
                            <?php $ac=$viewRow['Availability_Status']==='Available'?'badge-success':($viewRow['Availability_Status']==='Borrowed'?'badge-warning':($viewRow['Availability_Status']==='Archived'?'badge-gray':'badge-info')); ?>
                            <span class="badge <?php echo $ac; ?>" style="font-size:12px; padding:6px 14px;"><?php echo $viewRow['Availability_Status']; ?></span>
                            <?php $sc=$viewRow['Status']==='Good'?'badge-success':($viewRow['Status']==='Lost'?'badge-danger':'badge-warning'); ?>
                            <span class="badge <?php echo $sc; ?>" style="font-size:12px; padding:6px 14px;">Condition: <?php echo $viewRow['Status']; ?></span>
                        </div>
                    </div>
                    <div style="margin-bottom:20px;">
                        <label style="color:var(--text-muted); font-size:11px; text-transform:uppercase; letter-spacing:0.5px;">Description</label>
                        <p style="margin-top:6px; color:var(--text-dark); line-height:1.6; font-size:15px;"><?php echo nl2br(htmlspecialchars($viewRow['Description'] ?: 'No description provided for this item.')); ?></p>
                    </div>
                    <div style="margin-bottom:24px; padding:16px; background:var(--cream); border-radius:var(--radius-sm); border-left:4px solid var(--maroon);">
                        <div style="font-size:12px; color:var(--text-muted);">Owned by</div>
                        <div style="font-weight:700; color:var(--text-dark); font-size:16px;"><?php echo htmlspecialchars($viewRow['OwnerName']); ?></div>
                        <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">Listed on <?php echo date('M j, Y', strtotime($viewRow['CreatedAt'])); ?></div>
                    </div>
                    <div style="display:flex; gap:12px;">
                        <?php if ($viewRow['Availability_Status'] === 'Available' && $viewRow['OwnerUserID'] != $_SESSION['user_id']): ?>
                        <a href="borrow_requests.php?action=add&item_id=<?php echo $viewRow['ItemID']; ?>" class="btn btn-primary btn-lg"><i class="fas fa-hand-holding"></i> Request to Borrow</a>
                        <?php endif; ?>
                        <?php if ($isAdmin || $viewRow['OwnerUserID'] == $_SESSION['user_id']): ?>
                        <a href="?action=edit&id=<?php echo $viewRow['ItemID']; ?>" class="btn btn-outline"><i class="fas fa-pen"></i> Edit Item</a>
                        <?php endif; ?>
                    </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <?php if ($action === 'list'): ?>
    <div class="card">
        <div class="card-header">
            <div><h3>Item Catalog</h3><p><?php echo $totalCount; ?> item(s)</p></div>
            <form method="get" style="display:flex;gap:8px;align-items:center;">
                <div class="search-bar"><i class="fas fa-search"></i>
                    <input type="text" name="q" placeholder="Search items..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="avail" style="padding:7px 10px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-size:13px;">
                    <option value="">All Status</option>
                    <?php foreach(['Available','Borrowed','Reserved','Archived'] as $a): ?>
                    <option value="<?php echo $a; ?>" <?php if($filterAvail===$a) echo 'selected'; ?>><?php echo $a; ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline btn-sm"><i class="fas fa-filter"></i> Filter</button>
                <a href="items.php" class="btn btn-outline btn-sm"><i class="fas fa-rotate-left"></i></a>
            </form>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th><th>Item Name</th><th>Category</th><th>Physical Status</th><th>Availability</th><th>Owner</th><th>Added</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($items && $items->num_rows > 0):
                        while ($row = $items->fetch_assoc()): ?>
                    <tr>
                        <td class="mono"><?php echo $row['ItemID']; ?></td>
                        <td>
                            <div style="font-weight:600;"><?php echo htmlspecialchars($row['Item_Name']); ?></div>
                            <?php if($row['Description']): ?><div style="font-size:11px;color:var(--gray-400);"><?php echo htmlspecialchars(substr($row['Description'],0,50)); ?>...</div><?php endif; ?>
                        </td>
                        <td><?php if($row['Category']): ?><span class="badge badge-gray"><?php echo $row['Category']; ?></span><?php else: ?>—<?php endif; ?></td>
                        <td>
                            <?php $sc=$row['Status']==='Good'?'badge-success':($row['Status']==='Lost'?'badge-danger':'badge-warning'); ?>
                            <span class="badge <?php echo $sc; ?>"><?php echo $row['Status']; ?></span>
                        </td>
                        <td>
                            <?php $ac=$row['Availability_Status']==='Available'?'badge-success':($row['Availability_Status']==='Borrowed'?'badge-warning':($row['Availability_Status']==='Archived'?'badge-gray':'badge-info')); ?>
                            <span class="badge <?php echo $ac; ?>"><?php echo $row['Availability_Status']; ?></span>
                        </td>
                        <td style="font-size:12px;"><?php echo htmlspecialchars($row['OwnerName']); ?></td>
                        <td style="font-size:12px;color:var(--gray-500);"><?php echo date('M j, Y', strtotime($row['CreatedAt'])); ?></td>
                        <td>
                            <?php if ($isAdmin || $row['OwnerUserID'] == $_SESSION['user_id']): ?>
                            <a href="?action=edit&id=<?php echo $row['ItemID']; ?>" class="btn btn-outline btn-sm"><i class="fas fa-pen"></i></a>
                            <button type="button" onclick="showConfirmModal('?action=delete&id=<?php echo $row['ItemID']; ?>', 'Delete Item', 'Are you sure you want to delete this item? This action cannot be undone.')" class="btn btn-danger btn-sm" style="margin-left:4px;"><i class="fas fa-trash"></i></button>
                            <?php elseif ($row['Availability_Status'] === 'Available'): ?>
                            <a href="borrow_requests.php?action=add" class="btn btn-success btn-sm"><i class="fas fa-hand-holding"></i> Borrow</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="8"><div class="empty-state"><i class="fas fa-box-open"></i><h3>No items found</h3><p>Add your first item to get started.</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination UI -->
        <?php if ($totalPages >= 1): ?>
        <div class="pagination" style="margin-top:0; border-top:1px solid var(--cream-border);">
            <?php if ($p > 1): ?>
                <a href="?p=<?php echo $p-1; ?>&q=<?php echo urlencode($search); ?>&avail=<?php echo urlencode($filterAvail); ?>" class="page-link" style="width:auto; padding:0 15px; margin-right:5px;"><i class="fas fa-chevron-left"></i> Back</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?p=<?php echo $i; ?>&q=<?php echo urlencode($search); ?>&avail=<?php echo urlencode($filterAvail); ?>" class="page-link <?php echo $i == $p ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($p < $totalPages): ?>
                <a href="?p=<?php echo $p+1; ?>&q=<?php echo urlencode($search); ?>&avail=<?php echo urlencode($filterAvail); ?>" class="page-link" style="width:auto; padding:0 15px; margin-left:5px;">Next <i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
            <div class="page-info">Showing page <?php echo $p; ?> of <?php echo $totalPages; ?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
</div>

<div class="modal-overlay" id="confirmModalOverlay">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Confirm Action</h3>
            <button type="button" class="btn-ghost" onclick="closeConfirmModal()"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <p id="modalMessage">Are you sure you want to proceed?</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeConfirmModal()">Cancel</button>
            <a href="#" id="modalConfirmBtn" class="btn btn-danger">Confirm</a>
        </div>
    </div>
</div>

<script>
function validateAndShowModal() {
    const form = document.getElementById('itemForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    showConfirmModal('SUBMIT_FORM', 'Save Item', 'Are you sure you want to save this item?', 'btn-primary');
}

function showConfirmModal(url, title, message, btnClass = 'btn-danger') {
    document.getElementById('modalTitle').innerText = title;
    document.getElementById('modalMessage').innerText = message;
    const confirmBtn = document.getElementById('modalConfirmBtn');
    
    if (url === 'SUBMIT_FORM') {
        confirmBtn.href = "javascript:void(0)";
        confirmBtn.onclick = () => document.getElementById('itemForm').submit();
    } else {
        confirmBtn.href = url;
        confirmBtn.onclick = null;
    }
    
    confirmBtn.className = 'btn ' + btnClass;
    document.getElementById('confirmModalOverlay').classList.add('open');
}
function closeConfirmModal() {
    document.getElementById('confirmModalOverlay').classList.remove('open');
}
</script>

<?php require_once 'includes/footer.php'; ?>
