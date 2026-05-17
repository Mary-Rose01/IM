<?php
require_once 'connect.php';
requireLogin();

$uid = $_SESSION['user_id'];

// Mark all as read when this page is visited
$connection->query("UPDATE tblnotification SET is_read=1 WHERE UserID=$uid AND is_read=0");

// Delete a single notification
if (isset($_GET['delete']) && intval($_GET['delete']) > 0) {
    $nid = intval($_GET['delete']);
    $connection->query("DELETE FROM tblnotification WHERE NotifID=$nid AND UserID=$uid");
    header('Location: notifications.php');
    exit;
}

// Clear all notifications
if (isset($_GET['clearall'])) {
    $connection->query("DELETE FROM tblnotification WHERE UserID=$uid");
    header('Location: notifications.php');
    exit;
}

// Pagination
$limit = 15;
$p = max(1, intval($_GET['p'] ?? 1));
$off = ($p - 1) * $limit;

$totalRes = $connection->query("SELECT COUNT(*) c FROM tblnotification WHERE UserID=$uid");
$total = $totalRes->fetch_assoc()['c'];
$totalPages = max(1, ceil($total / $limit));

$notifs = $connection->query(
    "SELECT * FROM tblnotification WHERE UserID=$uid ORDER BY CreatedAt DESC LIMIT $limit OFFSET $off"
);

$pageTitle = 'Notifications';
require_once 'includes/header.php';
?>

<?php require_once "includes/navbar.php"; ?>
<div class="layout-top">
<div class="page-wrapper">

<div class="page-header">
    <div class="page-title">
        <h1><i class="fas fa-bell" style="color:var(--maroon);margin-right:8px;"></i>Notifications</h1>
        <p><?php echo $total; ?> notification(s) total</p>
    </div>
    <?php if ($total > 0): ?>
    <button type="button" onclick="showConfirmModal('?clearall=1','Clear All Notifications','Are you sure you want to clear all notifications? This cannot be undone.')" class="btn btn-outline btn-sm">
        <i class="fas fa-trash"></i> Clear All
    </button>
    <?php endif; ?>
</div>

<div class="card">
    <?php if ($notifs && $notifs->num_rows > 0): ?>
    <ul style="list-style:none;margin:0;padding:0;">
        <?php while ($n = $notifs->fetch_assoc()): ?>
        <li style="display:flex;align-items:flex-start;gap:14px;padding:16px 20px;border-bottom:1px solid var(--cream-border);<?php echo $n['is_read'] ? '' : 'background:var(--cream);'; ?>">
            <div style="flex-shrink:0;width:36px;height:36px;background:<?php echo $n['is_read'] ? 'var(--cream-dark)' : 'var(--maroon)'; ?>;border-radius:999px;display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-bell" style="color:<?php echo $n['is_read'] ? 'var(--gray-400)' : '#fff'; ?>;font-size:14px;"></i>
            </div>
            <div style="flex:1;">
                <div style="font-size:14px;color:var(--text-dark);font-weight:<?php echo $n['is_read'] ? '400' : '600'; ?>;">
                    <?php echo htmlspecialchars($n['Message']); ?>
                </div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:3px;">
                    <?php echo date('M j, Y g:i A', strtotime($n['CreatedAt'])); ?>
                </div>
                <?php if ($n['Link']): ?>
                <a href="<?php echo htmlspecialchars($n['Link']); ?>" style="font-size:12px;color:var(--maroon);font-weight:500;margin-top:4px;display:inline-block;">View →</a>
                <?php endif; ?>
            </div>
            <button type="button" onclick="showConfirmModal('?delete=<?php echo $n['NotifID']; ?>','Dismiss Notification','Are you sure you want to dismiss this notification?')" title="Dismiss" style="background:none;border:none;cursor:pointer;color:var(--gray-400);font-size:14px;padding:4px;flex-shrink:0;">
                <i class="fas fa-xmark"></i>
            </button>
        </li>
        <?php endwhile; ?>
    </ul>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination" style="margin-top:0;border-top:1px solid var(--cream-border);">
        <?php if ($p > 1): ?>
            <a href="?p=<?php echo $p-1; ?>" class="page-link" style="width:auto;padding:0 15px;"><i class="fas fa-chevron-left"></i> Back</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?p=<?php echo $i; ?>" class="page-link <?php echo $i==$p?'active':''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <?php if ($p < $totalPages): ?>
            <a href="?p=<?php echo $p+1; ?>" class="page-link" style="width:auto;padding:0 15px;">Next <i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
        <div class="page-info">Page <?php echo $p; ?> of <?php echo $totalPages; ?></div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="empty-state" style="padding:60px 20px;">
        <i class="fas fa-bell-slash"></i>
        <h3>No notifications</h3>
        <p>You're all caught up!</p>
    </div>
    <?php endif; ?>
</div>

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
