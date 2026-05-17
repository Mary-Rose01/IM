<?php
$currentPage = basename($_SERVER['PHP_SELF']);
function navActive($page, $current) { return $page === $current ? 'active' : ''; }
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Unread notification count for bell badge
$_unreadCount = 0;
if (isLoggedIn() && isset($connection)) {
    $uid = $_SESSION['user_id'];
    $res = $connection->query("SELECT COUNT(*) c FROM tblnotification WHERE UserID=$uid AND is_read=0");
    if ($res) $_unreadCount = intval($res->fetch_assoc()['c']);
}
?>
<nav class="navbar">
    <a href="<?php echo isLoggedIn() ? 'dashboard.php' : 'index.php'; ?>" class="navbar-brand">
        <span class="brand-huwam">HUWAM</span>
    </a>

    <?php if (isLoggedIn()): ?>
    <div class="navbar-center">
        <div class="nav-pill">
            <a href="dashboard.php" class="<?php echo navActive('dashboard.php', $currentPage); ?>">DASHBOARD</a>
            <a href="items.php" class="<?php echo navActive('items.php', $currentPage); ?>">BROWSE ITEMS</a>
            <a href="slots.php" class="<?php echo navActive('slots.php', $currentPage); ?>">SLOTS</a>
            <?php if ($isAdmin): ?>
            <a href="users.php" class="<?php echo navActive('users.php', $currentPage); ?>">USERS</a>
            <a href="students.php" class="<?php echo in_array($currentPage, ['students.php','organizations.php']) ? 'active' : ''; ?>">STUDENTS</a>
            <a href="borrow_requests.php" class="<?php echo in_array($currentPage, ['borrow_requests.php','transactions.php','bookings.php']) ? 'active' : ''; ?>">REQUESTS</a>
            <?php else: ?>
            <a href="borrow_requests.php" class="<?php echo navActive('borrow_requests.php', $currentPage); ?>">MY BORROWS</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="navbar-end">
        <div class="navbar-user-info">
            <div class="name"><?php echo htmlspecialchars(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? '')); ?></div>
            <div class="role"><?php echo ucfirst($_SESSION['role'] ?? 'user'); ?></div>
        </div>
        <div class="navbar-avatar"><?php echo strtoupper(substr($_SESSION['firstname'] ?? 'U', 0, 1)); ?></div>
        <!-- Notification Bell -->
        <a href="notifications.php" class="navbar-logout" style="position:relative;margin-right:4px;" title="Notifications">
            <i class="fas fa-bell"></i>
            <?php if ($_unreadCount > 0): ?>
            <span style="position:absolute;top:-6px;right:-6px;background:var(--maroon);color:#fff;font-size:10px;font-weight:700;border-radius:999px;min-width:17px;height:17px;display:flex;align-items:center;justify-content:center;padding:0 3px;line-height:1;"><?php echo $_unreadCount > 99 ? '99+' : $_unreadCount; ?></span>
            <?php endif; ?>
        </a>
        <a href="logout.php" class="navbar-logout"><i class="fas fa-right-from-bracket"></i> Logout</a>
    </div>
    <?php endif; ?>
</nav>
