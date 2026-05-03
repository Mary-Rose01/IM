<?php
require_once 'connect.php';
requireAdmin();

$action = $_GET['action'] ?? 'list';
$msg = ''; $msgType = 'success';

if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $connection->query("DELETE FROM tblbooking WHERE BookingID = $id");
    $msg = 'Booking deleted.'; $action = 'list';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = intval($_POST['hdnID'] ?? 0);
    $userID = intval($_POST['hdnUser'] ?? 0);
    $slotID = intval($_POST['hdnSlot'] ?? 0);
    $status = trim($_POST['txtstatus'] ?? 'Pending');
    $desc   = trim($_POST['txtdesc'] ?? '');

    if ($id === 0) {
        $stmt = $connection->prepare("INSERT INTO tblbooking (UserID,SlotID,Status,Description) VALUES (?,?,?,?)");
        $stmt->bind_param('iiss', $userID,$slotID,$status,$desc);
        if ($stmt->execute()) $msg = 'Booking created.';
        else { $msg = $stmt->error; $msgType='danger'; }
        $stmt->close();
    } else {
        $stmt = $connection->prepare("UPDATE tblbooking SET Status=?,Description=? WHERE BookingID=?");
        $stmt->bind_param('ssi', $status,$desc,$id);
        if ($stmt->execute()) $msg = 'Booking updated.';
        else { $msg = $stmt->error; $msgType='danger'; }
        $stmt->close();
    }
    $action = 'list';
}

$editRow = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $editRow = $connection->query("SELECT * FROM tblbooking WHERE BookingID=$id")->fetch_assoc();
}

$bookings = $connection->query(
    "SELECT b.*, CONCAT(u.FirstName,' ',u.LastName) as UserName, s.Start_DateTime, s.End_DateTime
     FROM tblbooking b
     JOIN tbluser u ON b.UserID = u.UserID
     JOIN tblavailabilityslot s ON b.SlotID = s.SlotID
     ORDER BY b.CreatedAt DESC"
);

$users = $connection->query("SELECT UserID,CONCAT(FirstName,' ',LastName) as Name FROM tbluser ORDER BY FirstName");
$slots = $connection->query("SELECT SlotID,Start_DateTime,End_DateTime FROM tblavailabilityslot WHERE isReserved=0 ORDER BY Start_DateTime");

$pageTitle = 'Bookings';
require_once 'includes/header.php';
?>

<?php require_once "includes/navbar.php"; ?>
<div class="layout-top">
<div class="page-wrapper">

<div class="page-header">
    <div class="page-title"><h1>Bookings</h1><p>Manage availability slot bookings</p></div>
    <div style="display:flex;gap:10px;"><a href="?action=add" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Booking</a></div>
</div>
    <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msgType; ?> auto-dismiss"><i class="fas fa-circle-check"></i> <?php echo $msg; ?></div>
    <?php endif; ?>

    <?php if ($action === 'add' || $action === 'edit'): ?>
    <div class="card" style="max-width:620px;margin-bottom:24px;">
        <div class="card-header"><div><h3><?php echo $action==='edit'?'Edit Booking':'New Booking'; ?></h3></div>
        <a href="bookings.php" class="btn btn-outline btn-sm"><i class="fas fa-xmark"></i></a></div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="hdnID" value="<?php echo $editRow['BookingID'] ?? 0; ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>User</label>
                        <select name="hdnUser">
                            <?php while($u=$users->fetch_assoc()): ?>
                            <option value="<?php echo $u['UserID']; ?>" <?php if(($editRow['UserID']??'_')==$u['UserID']) echo 'selected'; ?>><?php echo htmlspecialchars($u['Name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Availability Slot</label>
                        <select name="hdnSlot">
                            <?php while($s=$slots->fetch_assoc()): ?>
                            <option value="<?php echo $s['SlotID']; ?>">#<?php echo $s['SlotID']; ?> — <?php echo date('M j g:i A', strtotime($s['Start_DateTime'])); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="txtstatus">
                            <?php foreach(['Pending','Confirmed','Cancelled','Completed'] as $s): ?>
                            <option value="<?php echo $s; ?>" <?php if(($editRow['Status']??'Pending')===$s) echo 'selected'; ?>><?php echo $s; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group span-2">
                        <label>Description / Notes</label>
                        <textarea name="txtdesc"><?php echo htmlspecialchars($editRow['Description'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div style="margin-top:20px;display:flex;gap:10px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                    <a href="bookings.php" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><div><h3>All Bookings</h3><p><?php echo $bookings->num_rows; ?> booking(s)</p></div></div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr><th>#</th><th>User</th><th>Slot Period</th><th>Booking Date</th><th>Status</th><th>Notes</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if ($bookings->num_rows > 0): while($row=$bookings->fetch_assoc()): ?>
                    <tr>
                        <td class="mono">#<?php echo $row['BookingID']; ?></td>
                        <td style="font-weight:600;"><?php echo htmlspecialchars($row['UserName']); ?></td>
                        <td style="font-size:12px;">
                            <div><?php echo date('M j, Y g:i A', strtotime($row['Start_DateTime'])); ?></div>
                            <div style="color:var(--gray-400);">→ <?php echo date('M j, Y g:i A', strtotime($row['End_DateTime'])); ?></div>
                        </td>
                        <td style="font-size:12px;"><?php echo date('M j, Y', strtotime($row['Booking_DateTime'])); ?></td>
                        <td>
                            <?php $sc=$row['Status']==='Confirmed'?'badge-success':($row['Status']==='Pending'?'badge-warning':($row['Status']==='Cancelled'?'badge-danger':'badge-info')); ?>
                            <span class="badge <?php echo $sc; ?>"><?php echo $row['Status']; ?></span>
                        </td>
                        <td style="font-size:12px;color:var(--gray-500);"><?php echo $row['Description'] ? htmlspecialchars(substr($row['Description'],0,40)).'...' : '—'; ?></td>
                        <td>
                            <a href="?action=edit&id=<?php echo $row['BookingID']; ?>" class="btn btn-outline btn-sm"><i class="fas fa-pen"></i></a>
                            <a href="?action=delete&id=<?php echo $row['BookingID']; ?>" class="btn btn-danger btn-sm confirm-delete" style="margin-left:4px;"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="7"><div class="empty-state"><i class="fas fa-calendar-xmark"></i><h3>No bookings yet</h3></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<?php require_once 'includes/footer.php'; ?>
