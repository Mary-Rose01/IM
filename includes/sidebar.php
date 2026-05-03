<?php
$currentPage = basename($_SERVER['PHP_SELF']);
function navActive($page, $current) {
    return $page === $current ? 'active' : '';
}
?>
<nav class="sidebar">
    <div class="sidebar-brand">
        <a href="dashboard.php" class="brand-logo">
            <div class="brand-icon"><i class="fas fa-boxes-stacked"></i></div>
            <div class="brand-text">
                <h3>BorrowEase</h3>
                <span>CSIT226 — IM1</span>
            </div>
        </a>
    </div>

    <?php if (isLoggedIn()): $u = currentUser(); ?>
    <div class="sidebar-user">
        <div class="user-avatar"><?php echo strtoupper(substr($u['firstname'] ?? 'U', 0, 1)); ?></div>
        <div class="user-info">
            <h4><?php echo htmlspecialchars(($u['firstname'] ?? '') . ' ' . ($u['lastname'] ?? '')); ?></h4>
            <span><?php echo ucfirst($u['role'] ?? 'user'); ?></span>
        </div>
    </div>
    <?php endif; ?>

    <div class="sidebar-nav">
        <div class="nav-section-label">Overview</div>
        <a href="dashboard.php" class="nav-item <?php echo navActive('dashboard.php', $currentPage); ?>">
            <i class="fas fa-grid-2"></i> Dashboard
        </a>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <div class="nav-section-label">Admin — CRUD</div>
        <a href="users.php" class="nav-item <?php echo navActive('users.php', $currentPage); ?>">
            <i class="fas fa-users"></i> Users
        </a>
        <a href="students.php" class="nav-item <?php echo navActive('students.php', $currentPage); ?>">
            <i class="fas fa-user-graduate"></i> Students
        </a>
        <a href="organizations.php" class="nav-item <?php echo navActive('organizations.php', $currentPage); ?>">
            <i class="fas fa-building"></i> Organizations
        </a>
        <a href="items.php" class="nav-item <?php echo navActive('items.php', $currentPage); ?>">
            <i class="fas fa-box"></i> Items
        </a>
        <a href="bookings.php" class="nav-item <?php echo navActive('bookings.php', $currentPage); ?>">
            <i class="fas fa-calendar-check"></i> Bookings
        </a>
        <a href="borrow_requests.php" class="nav-item <?php echo navActive('borrow_requests.php', $currentPage); ?>">
            <i class="fas fa-hand-holding-box"></i> Borrow Requests
        </a>
        <a href="transactions.php" class="nav-item <?php echo navActive('transactions.php', $currentPage); ?>">
            <i class="fas fa-arrow-right-arrow-left"></i> Transactions
        </a>
        <?php else: ?>
        <div class="nav-section-label">My Borrowing</div>
        <a href="items.php" class="nav-item <?php echo navActive('items.php', $currentPage); ?>">
            <i class="fas fa-box"></i> Browse Items
        </a>
        <a href="borrow_requests.php" class="nav-item <?php echo navActive('borrow_requests.php', $currentPage); ?>">
            <i class="fas fa-hand-holding-box"></i> My Requests
        </a>
        <?php endif; ?>
    </div>

    <div class="sidebar-footer">
        <a href="logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a>
    </div>
</nav>
