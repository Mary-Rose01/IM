<?php
require_once 'connect.php';
requireLogin();

// --- AUTO OVERDUE DETECTION ---
$connection->query("UPDATE tblborrowtransaction SET Status = 'Overdue' WHERE Status = 'Active' AND ExpectedReturn_DateTime < NOW()");

$stats = [];
$isAdmin = ($_SESSION['role'] === 'admin');
$uid = $_SESSION['user_id'];

$tab_reqs = $_GET['tab_reqs'] ?? 'incoming';

$condIncoming = $isAdmin ? "1=1" : "r.RequestID IN (SELECT req.RequestID FROM tblborrowrequest req JOIN tblbooking b ON req.BookingID = b.BookingID JOIN tblavailabilityslot s ON b.SlotID = s.SlotID JOIN tblitem i ON s.ItemID = i.ItemID WHERE i.OwnerUserID = $uid)";
$condMy = "r.UserID = $uid";

$whereItems = $isAdmin ? "1=1" : "Availability_Status != 'Archived'"; 
$whereReqs  = ($tab_reqs === 'my') ? $condMy : $condIncoming;
$whereTx    = $isAdmin ? "1=1" : "r.UserID = $uid";   // For transactions related to the user's requests

$stats['items']     = $connection->query("SELECT COUNT(*) c FROM tblitem WHERE Availability_Status != 'Archived' AND $whereItems")->fetch_assoc()['c'];
$stats['pending']   = $connection->query("SELECT COUNT(*) c FROM tblborrowrequest r WHERE Status = 'Pending' AND $condIncoming")->fetch_assoc()['c'];
$stats['borrowed']  = $connection->query("SELECT COUNT(*) c FROM tblitem WHERE Availability_Status = 'Borrowed' AND $whereItems")->fetch_assoc()['c'];
$stats['completed'] = $connection->query("SELECT COUNT(*) c FROM tblborrowtransaction t JOIN tblborrowrequest r ON t.RequestID = r.RequestID WHERE t.Status = 'Returned' AND $whereTx")->fetch_assoc()['c'];

// Pagination Logic
$limit = 10;
$p_items = max(1, intval($_GET['p_items'] ?? 1));
$p_reqs  = max(1, intval($_GET['p_reqs'] ?? 1));
$p_tx    = max(1, intval($_GET['p_tx'] ?? 1));

$off_items = ($p_items - 1) * $limit;
$off_reqs  = ($p_reqs - 1) * $limit;
$off_tx    = ($p_tx - 1) * $limit;

$totalItemsCount = $connection->query("SELECT COUNT(*) c FROM tblitem WHERE $whereItems")->fetch_assoc()['c'];
$totalReqsCount  = $connection->query("SELECT COUNT(*) c FROM tblborrowrequest r WHERE $whereReqs")->fetch_assoc()['c'];
$totalTxCount    = $connection->query("SELECT COUNT(*) c FROM tblborrowtransaction t JOIN tblborrowrequest r ON t.RequestID = r.RequestID WHERE $whereTx")->fetch_assoc()['c'];

$pagesItems = max(1, ceil($totalItemsCount / $limit));
$pagesReqs  = max(1, ceil($totalReqsCount / $limit));
$pagesTx    = max(1, ceil($totalTxCount / $limit));

$recentItems = $connection->query(
    "SELECT i.ItemID, i.Item_Name, i.Category, i.Availability_Status,
            CONCAT(u.FirstName,' ',u.LastName) as OwnerName
     FROM tblitem i JOIN tbluser u ON i.OwnerUserID = u.UserID
     WHERE $whereItems ORDER BY i.CreatedAt DESC LIMIT $limit OFFSET $off_items"
);

$recentReqs = $connection->query(
    "SELECT r.RequestID, r.Status, r.CreatedAt, r.Requested_Start, r.Requested_End,
            CONCAT(u.FirstName,' ',u.LastName) as RequesterName
     FROM tblborrowrequest r JOIN tbluser u ON r.UserID = u.UserID
     WHERE $whereReqs ORDER BY r.CreatedAt DESC LIMIT $limit OFFSET $off_reqs"
);

$recentTx = $connection->query(
    "SELECT t.TransactionID, t.Status, t.Release_DateTime, t.ExpectedReturn_DateTime,
            t.ActualReturn_DateTime, t.Condition_On_Return,
            CONCAT(u.FirstName,' ',u.LastName) as BorrowerName
     FROM tblborrowtransaction t
     JOIN tblborrowrequest r ON t.RequestID = r.RequestID
     JOIN tbluser u ON r.UserID = u.UserID
     WHERE $whereTx ORDER BY t.CreatedAt DESC LIMIT $limit OFFSET $off_tx"
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
            <div class="stat-icon-wrap"><i class="fas fa-hand-holding"></i></div>
            <div class="stat-info"><h3><?php echo $stats['borrowed']; ?></h3><p>Active Borrows</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-wrap"><i class="fas fa-circle-check"></i></div>
            <div class="stat-info"><h3><?php echo $stats['completed']; ?></h3><p>Completed</p></div>
        </div>
    </div>

    <?php 
    // Helper to generate pagination URLs preserving other section pages
    function pagelink($pi, $pr, $pt, $tr) {
        return "?p_items=$pi&p_reqs=$pr&p_tx=$pt&tab_reqs=$tr";
    } ?>

    <!-- My Listed Items -->
    <div style="margin-bottom:32px;">
        <div class="section-heading">
            <h2><i class="fas fa-box"></i> <?php echo $_SESSION['role']==='admin'?'All Items':'Item Catalog'; ?></h2>
            <a href="items.php?action=add" class="section-link">+ Add Item</a>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;">
        <?php if ($recentItems && $recentItems->num_rows > 0): while ($row = $recentItems->fetch_assoc()):
            $ac = $row['Availability_Status']==='Available'?'badge-success':($row['Availability_Status']==='Borrowed'?'badge-warning':'badge-gray'); ?>
            <div style="background:var(--cream-dark);border:1px solid var(--cream-border);border-radius:var(--radius-lg);padding:20px;cursor:pointer;transition:box-shadow .18s ease,transform .18s ease;" onmouseover="this.style.boxShadow='var(--shadow)';this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='';this.style.transform=''" onclick="location.href='items.php?action=view&id=<?php echo $row['ItemID']; ?>'">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                    <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--amber);"><?php echo htmlspecialchars($row['Category']??'Item'); ?></span>
                    <span class="badge <?php echo $ac; ?>"><?php echo $row['Availability_Status']; ?></span>
                </div>
                <div style="font-family:var(--font-display);font-size:16px;font-weight:600;color:var(--text-dark);line-height:1.3;margin-bottom:6px;"><?php echo htmlspecialchars($row['Item_Name']); ?></div>
                <div style="font-size:12px;color:var(--text-muted);">by <?php echo htmlspecialchars($row['OwnerName']); ?></div>
                <a href="items.php?action=view&id=<?php echo $row['ItemID']; ?>" style="display:inline-block;margin-top:12px;font-size:12px;font-weight:500;color:var(--maroon);">View Details →</a>
            </div>
        <?php endwhile; else: ?>
            <div style="grid-column:1/-1;"><div class="empty-state"><i class="fas fa-box-open"></i><h3>No items yet</h3><p>Add your first item.</p></div></div>
        <?php endif; ?>
        </div>

        <!-- Items Pagination -->
        <?php if ($pagesItems >= 1): ?>
        <div class="pagination" style="margin-top:24px;">
            <?php if ($p_items > 1): ?>
                <a href="<?php echo pagelink($p_items-1, $p_reqs, $p_tx, $tab_reqs); ?>" class="page-link" style="width:auto; padding:0 15px; margin-right:5px;"><i class="fas fa-chevron-left"></i> Back</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $pagesItems; $i++): ?>
                <a href="<?php echo pagelink($i, $p_reqs, $p_tx, $tab_reqs); ?>" class="page-link <?php echo $i == $p_items ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($p_items < $pagesItems): ?>
                <a href="<?php echo pagelink($p_items+1, $p_reqs, $p_tx, $tab_reqs); ?>" class="page-link" style="width:auto; padding:0 15px; margin-left:5px;">Next <i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
            <div class="page-info">Showing page <?php echo $p_items; ?> of <?php echo $pagesItems; ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Borrow Requests -->
    <div style="margin-bottom:32px;">
        <div class="section-heading">
            <h2><i class="fas fa-hand-holding-box"></i> Borrow Requests</h2>
            <a href="borrow_requests.php" class="section-link">View All →</a>
        </div>
        <div class="tablist">
            <a href="<?php echo pagelink($p_items, 1, $p_tx, 'incoming'); ?>" class="tab-item <?php echo $tab_reqs === 'incoming' ? 'active' : ''; ?>"><i class="fas fa-inbox"></i> Incoming (<?php echo $stats['pending']; ?>)</a>
            <a href="<?php echo pagelink($p_items, 1, $p_tx, 'my'); ?>" class="tab-item <?php echo $tab_reqs === 'my' ? 'active' : ''; ?>"><i class="fas fa-paper-plane"></i> My Requests</a>
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
                <div class="request-title"><strong><?php echo htmlspecialchars($row['RequesterName']); ?></strong> <span style="color:var(--text-muted);font-weight:400;"><?php echo $tab_reqs === 'my' ? 'request for item' : 'wants to borrow an item'; ?></span></div>
                <div class="request-time">
                    <?php echo date('Y-m-d', strtotime($row['Requested_Start'])); ?> ·
                    <?php echo date('g:i A', strtotime($row['Requested_Start'])); ?>–<?php echo date('g:i A', strtotime($row['Requested_End'])); ?>
                </div>
            </div>
            <?php if ($_SESSION['role']==='admin' && $s==='Pending'): ?>
            <div class="request-actions">
                <button type="button" onclick="showConfirmModal('borrow_requests.php?action=status&id=<?php echo $row['RequestID']; ?>&s=Approved', 'Approve Request', 'Do you want to approve this borrow request?', 'btn-primary')" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> Approve</button>
                <button type="button" onclick="showConfirmModal('borrow_requests.php?action=status&id=<?php echo $row['RequestID']; ?>&s=Rejected', 'Decline Request', 'Are you sure you want to decline this request?', 'btn-outline')" class="btn btn-outline btn-sm"><i class="fas fa-xmark"></i> Decline</button>
            </div>
            <?php endif; ?>
        </div>
        <?php endwhile; else: ?>
        <div class="empty-state" style="padding:40px 20px;"><i class="fas fa-inbox"></i><h3>No borrow requests yet</h3></div>
        <?php endif; ?>

        <!-- Requests Pagination -->
        <?php if ($pagesReqs >= 1): ?>
        <div class="pagination" style="margin-top:24px;">
            <?php if ($p_reqs > 1): ?>
                <a href="<?php echo pagelink($p_items, $p_reqs-1, $p_tx, $tab_reqs); ?>" class="page-link" style="width:auto; padding:0 15px; margin-right:5px;"><i class="fas fa-chevron-left"></i> Back</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $pagesReqs; $i++): ?>
                <a href="<?php echo pagelink($p_items, $i, $p_tx, $tab_reqs); ?>" class="page-link <?php echo $i == $p_reqs ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($p_reqs < $pagesReqs): ?>
                <a href="<?php echo pagelink($p_items, $p_reqs+1, $p_tx, $tab_reqs); ?>" class="page-link" style="width:auto; padding:0 15px; margin-left:5px;">Next <i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
            <div class="page-info">Showing page <?php echo $p_reqs; ?> of <?php echo $pagesReqs; ?></div>
        </div>
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
                <div style="margin-bottom:4px;">
                    <span class="badge <?php echo $tsc; ?>">
                        <?php if($ts === 'Active'): ?><i class="fas fa-clock-rotate-left" style="font-size:8px;margin-right:3px;"></i><?php endif; ?>
                        <?php echo $ts; ?>
                    </span>
                </div>
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
            <button type="button" onclick="showConfirmModal('transactions.php?action=edit&id=<?php echo $tx['TransactionID']; ?>', 'Confirm Return', 'Confirm that the item has been returned?', 'btn-outline')" class="btn btn-outline btn-sm"><i class="fas fa-rotate-left"></i> Confirm Return</button>
            <?php endif; ?>
        </div>
        <?php endwhile; else: ?>
        <div class="empty-state" style="padding:40px 20px;"><i class="fas fa-arrow-right-arrow-left"></i><h3>No transactions yet</h3></div>
        <?php endif; ?>

        <!-- Transactions Pagination -->
        <?php if ($pagesTx >= 1): ?>
        <div class="pagination" style="margin-top:24px;">
            <?php if ($p_tx > 1): ?>
                <a href="<?php echo pagelink($p_items, $p_reqs, $p_tx-1, $tab_reqs); ?>" class="page-link" style="width:auto; padding:0 15px; margin-right:5px;"><i class="fas fa-chevron-left"></i> Back</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $pagesTx; $i++): ?>
                <a href="<?php echo pagelink($p_items, $p_reqs, $i, $tab_reqs); ?>" class="page-link <?php echo $i == $p_tx ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($p_tx < $pagesTx): ?>
                <a href="<?php echo pagelink($p_items, $p_reqs, $p_tx+1, $tab_reqs); ?>" class="page-link" style="width:auto; padding:0 15px; margin-left:5px;">Next <i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
            <div class="page-info">Showing page <?php echo $p_tx; ?> of <?php echo $pagesTx; ?></div>
        </div>
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
function showConfirmModal(url, title, message, btnClass = 'btn-danger') {
    document.getElementById('modalTitle').innerText = title;
    document.getElementById('modalMessage').innerText = message;
    const confirmBtn = document.getElementById('modalConfirmBtn');
    confirmBtn.href = url;
    confirmBtn.className = 'btn ' + btnClass;
    document.getElementById('confirmModalOverlay').classList.add('open');
}
function closeConfirmModal() {
    document.getElementById('confirmModalOverlay').classList.remove('open');
}
</script>

<?php require_once 'includes/footer.php'; ?>
