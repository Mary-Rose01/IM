<?php
$currentPage = basename($_SERVER['PHP_SELF']);
function navActive($page, $current) { return $page === $current ? 'active' : ''; }
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
?>
<nav class="navbar">
    <a href="<?php echo isLoggedIn() ? 'dashboard.php' : 'index.php'; ?>" class="navbar-brand">
        <img src="https://images.bukas.ph/images/school-logos/CIT.original.png" alt="" class="navbar-logo">
        <span class="brand-huwam">HUWAM</span>
    </a>

    <?php if (isLoggedIn()): ?>
    <div class="navbar-center">
        <div class="nav-pill">
            <a href="dashboard.php" class="<?php echo navActive('dashboard.php', $currentPage); ?>">DASHBOARD</a>
            <a href="items.php" class="<?php echo navActive('items.php', $currentPage); ?>">BROWSE ITEMS</a>
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
        <a href="logout.php" class="navbar-logout"><i class="fas fa-right-from-bracket"></i> Logout</a>
    </div>
    <?php endif; ?>
</nav>
