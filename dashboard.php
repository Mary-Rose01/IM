<?php
require_once 'connect.php';
requireLogin();

$stats = [];
$stats['items']     = $connection->query("SELECT COUNT(*) c FROM tblitem WHERE Availability_Status != 'Archived'")->fetch_assoc()['c'];
$stats['pending']   = $connection->query("SELECT COUNT(*) c FROM tblborrowrequest WHERE Status = 'Pending'")->fetch_assoc()['c'];
$stats['borrowed']  = $connection->query("SELECT COUNT(*) c FROM tblitem WHERE Availability_Status = 'Borrowed'")->fetch_assoc()['c'];
$stats['completed'] = $connection->query("SELECT COUNT(*) c FROM tblborrowtransaction WHERE Status = 'Returned'")->fetch_assoc()['c'];

$recentItems = $connection->query(
    "SELECT i.ItemID, i.Item_Name, i.Category, i.Availability_Status,
            CONCAT(u.FirstName,' ',u.LastName) as OwnerName
     FROM tblitem i JOIN tbluser u ON i.OwnerUserID = u.UserID
     ORDER BY i.CreatedAt DESC LIMIT 4"
);

$recentReqs = $connection->query(
    "SELECT r.RequestID, r.Status, r.CreatedAt, r.Requested_Start, r.Requested_End,
            CONCAT(u.FirstName,' ',u.LastName) as RequesterName
     FROM tblborrowrequest r JOIN tbluser u ON r.UserID = u.UserID
     ORDER BY r.CreatedAt DESC LIMIT 5"
);

$recentTx = $connection->query(
    "SELECT t.TransactionID, t.Status, t.Release_DateTime, t.ExpectedReturn_DateTime,
            t.ActualReturn_DateTime, t.Condition_On_Return,
            CONCAT(u.FirstName,' ',u.LastName) as BorrowerName
     FROM tblborrowtransaction t
     JOIN tblborrowrequest r ON t.RequestID = r.RequestID
     JOIN tbluser u ON r.UserID = u.UserID
     ORDER BY t.CreatedAt DESC LIMIT 4"
);

$pageTitle = 'Dashboard';
require_once 'includes/header.php';
?>

<?php require_once 'includes/navbar.php'; ?>
<div class="layout-top">
<div class="page-wrapper">

    <div class="page-header">
        <div class="page-title">
            <h1>Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? '')); ?></p>
        </div>
        <a href="items.php?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> List New Item</a>
    </div>

    <!-- 4 Stat Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon-wrap"><i class="fas fa-box"></i></div>
            <div class="stat-info"><h3><?php echo $stats['items']; ?></h3><p>Items Listed</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-wrap"><i class="fas fa-clock"></i></div>
            <div class="stat-info"><h3><?php echo $stats['pending']; ?></h3><p>Pending Requests</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-wrap"><i class="fas fa-hand-holding-box"></i></div>
            <div class="stat-info"><h3><?php echo $stats['borrowed']; ?></h3><p>Active Borrows</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-wrap"><i class="fas fa-circle-check"></i></div>
            <div class="stat-info"><h3><?php echo $stats['completed']; ?></h3><p>Completed</p></div>
        </div>
    </div>

    <!-- My Listed Items -->
    <div style="margin-bottom:32px;">
        <div class="section-heading">
            <h2><i class="fas fa-box"></i> <?php echo $_SESSION['role']==='admin'?'All Items':'My Listed Items'; ?></h2>
            <a href="items.php?action=add" class="section-link">+ Add Item</a>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;">
        <?php if ($recentItems && $recentItems->num_rows > 0): while ($row = $recentItems->fetch_assoc()):
            $ac = $row['Availability_Status']==='Available'?'badge-success':($row['Availability_Status']==='Borrowed'?'badge-warning':'badge-gray'); ?>
            <div style="background:var(--cream-dark);border:1px solid var(--cream-border);border-radius:var(--radius-lg);padding:20px;cursor:pointer;transition:box-shadow .18s ease,transform .18s ease;" onmouseover="this.style.boxShadow='var(--shadow)';this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='';this.style.transform=''">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                    <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--amber);"><?php echo htmlspecialchars($row['Category']??'Item'); ?></span>
                    <span class="badge <?php echo $ac; ?>"><?php echo $row['Availability_Status']; ?></span>
                </div>
                <div style="font-family:var(--font-display);font-size:16px;font-weight:600;color:var(--text-dark);line-height:1.3;margin-bottom:6px;"><?php echo htmlspecialchars($row['Item_Name']); ?></div>
                <div style="font-size:12px;color:var(--text-muted);">by <?php echo htmlspecialchars($row['OwnerName']); ?></div>
                <a href="items.php?action=edit&id=<?php echo $row['ItemID']; ?>" style="display:inline-block;margin-top:12px;font-size:12px;font-weight:500;color:var(--maroon);">View Details →</a>
            </div>
        <?php endwhile; else: ?>
            <div style="grid-column:1/-1;"><div class="empty-state"><i class="fas fa-box-open"></i><h3>No items yet</h3><p>Add your first item.</p></div></div>
        <?php endif; ?>
        </div>
    </div>

    <!-- Borrow Requests -->
    <div style="margin-bottom:32px;">
        <div class="section-heading">
            <h2><i class="fas fa-hand-holding-box"></i> Borrow Requests</h2>
            <a href="borrow_requests.php" class="section-link">View All →</a>
        </div>
        <div class="tablist">
            <a href="borrow_requests.php" class="tab-item active"><i class="fas fa-inbox"></i> Incoming (<?php echo $stats['pending']; ?>)</a>
            <a href="borrow_requests.php" class="tab-item"><i class="fas fa-paper-plane"></i> My Requests</a>
        </div>

        <?php if ($recentReqs && $recentReqs->num_rows > 0): while ($row = $recentReqs->fetch_assoc()):
            $s = $row['Status'];
            $sc = $s==='Approved'?'badge-success':($s==='Pending'?'badge-warning':($s==='Rejected'||$s==='Cancelled'?'badge-danger':'badge-info')); ?>
        <div class="request-card">
            <div class="request-info">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                    <span class="badge <?php echo $sc; ?>"><?php echo $s; ?></span>
                    <span style="font-size:10px;color:var(--text-muted);"><?php echo date('Y-m-d', strtotime($row['CreatedAt'])); ?></span>
                </div>
                <div class="request-title"><strong><?php echo htmlspecialchars($row['RequesterName']); ?></strong> <span style="color:var(--text-muted);font-weight:400;">wants to borrow an item</span></div>
                <div class="request-time">
                    <?php echo date('Y-m-d', strtotime($row['Requested_Start'])); ?> ·
                    <?php echo date('g:i A', strtotime($row['Requested_Start'])); ?>–<?php echo date('g:i A', strtotime($row['Requested_End'])); ?>
                </div>
            </div>
            <?php if ($_SESSION['role']==='admin' && $s==='Pending'): ?>
            <div class="request-actions">
                <a href="borrow_requests.php?action=status&id=<?php echo $row['RequestID']; ?>&s=Approved" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> Approve</a>
                <a href="borrow_requests.php?action=status&id=<?php echo $row['RequestID']; ?>&s=Rejected" class="btn btn-outline btn-sm"><i class="fas fa-xmark"></i> Decline</a>
            </div>
            <?php endif; ?>
        </div>
        <?php endwhile; else: ?>
        <div class="empty-state" style="padding:40px 20px;"><i class="fas fa-inbox"></i><h3>No borrow requests yet</h3></div>
        <?php endif; ?>
    </div>

    <!-- Transactions -->
    <div style="margin-bottom:32px;">
        <div class="section-heading">
            <h2><i class="fas fa-arrow-right-arrow-left"></i> Transactions</h2>
            <a href="transactions.php" class="section-link">View All →</a>
        </div>
        <?php if ($recentTx && $recentTx->num_rows > 0): while ($tx = $recentTx->fetch_assoc()):
            $ts = $tx['Status'];
            $tsc = $ts==='Returned'?'badge-success':($ts==='Active'?'badge-warning':($ts==='Overdue'?'badge-danger':'badge-gray')); ?>
        <div class="request-card">
            <div class="request-info">
                <div style="margin-bottom:4px;"><span class="badge <?php echo $tsc; ?>"><?php echo $ts; ?></span></div>
                <div style="font-family:var(--font-display);font-size:14px;font-weight:600;color:var(--text-dark);margin:4px 0;">Transaction #<?php echo $tx['TransactionID']; ?></div>
                <div style="font-size:12px;color:var(--text-muted);">Lent to <?php echo htmlspecialchars($tx['BorrowerName']); ?> · <?php echo date('Y-m-d', strtotime($tx['Release_DateTime'])); ?></div>
                <div style="font-size:12px;color:var(--amber);margin-top:2px;">
                    <?php if($ts==='Returned'&&$tx['ActualReturn_DateTime']): ?>
                    Returned: <?php echo date('Y-m-d', strtotime($tx['ActualReturn_DateTime'])); ?> · Condition: <?php echo $tx['Condition_On_Return']??'Good'; ?>
                    <?php else: ?>
                    Expected return: <?php echo date('Y-m-d', strtotime($tx['ExpectedReturn_DateTime'])); ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php if($ts==='Active'): ?>
            <a href="transactions.php?action=edit&id=<?php echo $tx['TransactionID']; ?>" class="btn btn-outline btn-sm"><i class="fas fa-rotate-left"></i> Confirm Return</a>
            <?php endif; ?>
        </div>
        <?php endwhile; else: ?>
        <div class="empty-state" style="padding:40px 20px;"><i class="fas fa-arrow-right-arrow-left"></i><h3>No transactions yet</h3></div>
        <?php endif; ?>
    </div>

    <!-- Admin Quick Actions -->
    <?php if($_SESSION['role']==='admin'): ?>
    <div style="padding:20px;background:var(--cream-dark);border:1px solid var(--cream-border);border-radius:var(--radius-lg);">
        <div style="font-family:var(--font-display);font-size:15px;font-weight:600;color:var(--text-dark);margin-bottom:14px;">
            <i class="fas fa-bolt" style="color:var(--amber);margin-right:6px;"></i>Quick Actions
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="users.php?action=add" class="btn btn-primary btn-sm"><i class="fas fa-user-plus"></i> Add User</a>
            <a href="students.php?action=add" class="btn btn-outline btn-sm"><i class="fas fa-user-graduate"></i> Add Student</a>
            <a href="organizations.php?action=add" class="btn btn-outline btn-sm"><i class="fas fa-building"></i> Add Organization</a>
            <a href="items.php?action=add" class="btn btn-outline btn-sm"><i class="fas fa-box"></i> Add Item</a>
            <a href="borrow_requests.php" class="btn btn-outline btn-sm"><i class="fas fa-list-check"></i> Review Requests</a>
        </div>
    </div>
    <?php endif; ?>

</div>
</div>

<?php require_once 'includes/footer.php'; ?>
