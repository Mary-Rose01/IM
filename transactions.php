<?php
require_once 'connect.php';
requireAdmin();

$action = $_GET['action'] ?? 'list';
$msg = ''; $msgType = 'success';

if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $connection->query("DELETE FROM tblborrowtransaction WHERE TransactionID = $id");
    $msg = 'Transaction deleted.'; $action = 'list';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id         = intval($_POST['hdnID'] ?? 0);
    $reqID      = intval($_POST['hdnRequest'] ?? 0);
    $release    = trim($_POST['txtrelease'] ?? '');
    $expected   = trim($_POST['txtexpected'] ?? '');
    $actual     = trim($_POST['txtactual'] ?? '') ?: null;
    $condR      = trim($_POST['txtcondRelease'] ?? 'Good');
    $condRet    = trim($_POST['txtcondReturn'] ?? '') ?: null;
    $status     = trim($_POST['txtstatus'] ?? 'Active');

    if ($id === 0) {
        $stmt = $connection->prepare("INSERT INTO tblborrowtransaction (RequestID,Release_DateTime,ExpectedReturn_DateTime,ActualReturn_DateTime,Condition_On_Release,Condition_On_Return,Status) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param('issssss', $reqID,$release,$expected,$actual,$condR,$condRet,$status);
        if ($stmt->execute()) $msg = 'Transaction created.';
        else { $msg = $stmt->error; $msgType='danger'; }
        $stmt->close();
    } else {
        $stmt = $connection->prepare("UPDATE tblborrowtransaction SET ActualReturn_DateTime=?,Condition_On_Return=?,Status=? WHERE TransactionID=?");
        $stmt->bind_param('sssi', $actual,$condRet,$status,$id);
        if ($stmt->execute()) $msg = 'Transaction updated.';
        else { $msg = $stmt->error; $msgType='danger'; }
        $stmt->close();
    }
    $action = 'list';
}

$editRow = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $editRow = $connection->query("SELECT * FROM tblborrowtransaction WHERE TransactionID=$id")->fetch_assoc();
}

$transactions = $connection->query(
    "SELECT t.*, CONCAT(u.FirstName,' ',u.LastName) as RequesterName, r.Requested_Start, r.Requested_End
     FROM tblborrowtransaction t
     JOIN tblborrowrequest r ON t.RequestID = r.RequestID
     JOIN tbluser u ON r.UserID = u.UserID
     ORDER BY t.CreatedAt DESC"
);

$approvedReqs = $connection->query(
    "SELECT r.RequestID, CONCAT(u.FirstName,' ',u.LastName) as Name
     FROM tblborrowrequest r JOIN tbluser u ON r.UserID=u.UserID
     WHERE r.Status='Approved' AND r.RequestID NOT IN (SELECT RequestID FROM tblborrowtransaction)
     ORDER BY r.RequestID DESC"
);

$pageTitle = 'Borrow Transactions';
require_once 'includes/header.php';
?>

<?php require_once "includes/navbar.php"; ?>
<div class="layout-top">
<div class="page-wrapper">

<div class="page-header">
    <div class="page-title"><h1>Borrow Transactions</h1><p>Track item releases and returns</p></div>
    <div style="display:flex;gap:10px;"><a href="?action=add" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Record Transaction</a></div>
</div>
    <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msgType; ?> auto-dismiss"><i class="fas fa-circle-check"></i> <?php echo $msg; ?></div>
    <?php endif; ?>

    <?php if ($action === 'add' || $action === 'edit'): ?>
    <div class="card" style="max-width:700px;margin-bottom:24px;">
        <div class="card-header"><div><h3><?php echo $action==='edit'?'Update Transaction':'New Transaction'; ?></h3></div>
        <a href="transactions.php" class="btn btn-outline btn-sm"><i class="fas fa-xmark"></i></a></div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="hdnID" value="<?php echo $editRow['TransactionID'] ?? 0; ?>">
                <div class="form-grid cols-3">
                    <?php if ($action === 'add'): ?>
                    <div class="form-group span-3">
                        <label>Approved Borrow Request</label>
                        <select name="hdnRequest">
                            <?php while($r=$approvedReqs->fetch_assoc()): ?>
                            <option value="<?php echo $r['RequestID']; ?>">#<?php echo $r['RequestID']; ?> — <?php echo htmlspecialchars($r['Name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Release Date/Time</label>
                        <input type="datetime-local" name="txtrelease" value="<?php echo str_replace(' ','T',$editRow['Release_DateTime']??date('Y-m-d\TH:i')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Expected Return</label>
                        <input type="datetime-local" name="txtexpected" value="<?php echo str_replace(' ','T',$editRow['ExpectedReturn_DateTime']??''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Condition on Release</label>
                        <select name="txtcondRelease">
                            <?php foreach(['Good','Fair','Damaged'] as $c): ?>
                            <option value="<?php echo $c; ?>" <?php if(($editRow['Condition_On_Release']??'Good')===$c) echo 'selected'; ?>><?php echo $c; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label>Actual Return Date/Time</label>
                        <input type="datetime-local" name="txtactual" value="<?php echo $editRow['ActualReturn_DateTime'] ? str_replace(' ','T',$editRow['ActualReturn_DateTime']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Condition on Return</label>
                        <select name="txtcondReturn">
                            <option value="">— Not Yet Returned —</option>
                            <?php foreach(['Good','Fair','Damaged','Lost'] as $c): ?>
                            <option value="<?php echo $c; ?>" <?php if(($editRow['Condition_On_Return']??'')===$c) echo 'selected'; ?>><?php echo $c; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="txtstatus">
                            <?php foreach(['Active','Returned','Overdue','Lost'] as $s): ?>
                            <option value="<?php echo $s; ?>" <?php if(($editRow['Status']??'Active')===$s) echo 'selected'; ?>><?php echo $s; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="margin-top:20px;display:flex;gap:10px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Transaction</button>
                    <a href="transactions.php" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><div><h3>Transaction History</h3><p><?php echo $transactions->num_rows; ?> transaction(s)</p></div></div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr><th>#</th><th>Borrower</th><th>Released</th><th>Expected Return</th><th>Actual Return</th><th>Cond. Release</th><th>Cond. Return</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if ($transactions->num_rows > 0): while($row=$transactions->fetch_assoc()): ?>
                    <tr>
                        <td class="mono">#<?php echo $row['TransactionID']; ?></td>
                        <td style="font-weight:600;"><?php echo htmlspecialchars($row['RequesterName']); ?></td>
                        <td style="font-size:12px;"><?php echo date('M j, Y g:i A', strtotime($row['Release_DateTime'])); ?></td>
                        <td style="font-size:12px;"><?php echo date('M j, Y g:i A', strtotime($row['ExpectedReturn_DateTime'])); ?></td>
                        <td style="font-size:12px;"><?php echo $row['ActualReturn_DateTime'] ? date('M j, Y g:i A', strtotime($row['ActualReturn_DateTime'])) : '<span style="color:var(--gray-400);">Not yet</span>'; ?></td>
                        <td><span class="badge <?php echo $row['Condition_On_Release']==='Good'?'badge-success':($row['Condition_On_Release']==='Damaged'?'badge-danger':'badge-warning'); ?>"><?php echo $row['Condition_On_Release']; ?></span></td>
                        <td><?php if($row['Condition_On_Return']): ?><span class="badge <?php echo $row['Condition_On_Return']==='Good'?'badge-success':($row['Condition_On_Return']==='Lost'||$row['Condition_On_Return']==='Damaged'?'badge-danger':'badge-warning'); ?>"><?php echo $row['Condition_On_Return']; ?></span><?php else: ?>—<?php endif; ?></td>
                        <td>
                            <?php $sc=$row['Status']==='Returned'?'badge-success':($row['Status']==='Active'?'badge-info':($row['Status']==='Overdue'?'badge-warning':'badge-danger')); ?>
                            <span class="badge <?php echo $sc; ?>"><?php echo $row['Status']; ?></span>
                        </td>
                        <td>
                            <a href="?action=edit&id=<?php echo $row['TransactionID']; ?>" class="btn btn-outline btn-sm"><i class="fas fa-pen"></i></a>
                            <a href="?action=delete&id=<?php echo $row['TransactionID']; ?>" class="btn btn-danger btn-sm confirm-delete" style="margin-left:4px;"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="9"><div class="empty-state"><i class="fas fa-arrow-right-arrow-left"></i><h3>No transactions yet</h3></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<?php require_once 'includes/footer.php'; ?>
