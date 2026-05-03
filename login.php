<?php
require_once 'connect.php';

if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnLogin'])) {
    $uname = trim($_POST['txtusername'] ?? '');
    $pwd   = $_POST['txtpassword'] ?? '';

    if (empty($uname) || empty($pwd)) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $connection->prepare("SELECT * FROM tbluser WHERE Username = ? AND isActive = 1");
        $stmt->bind_param('s', $uname);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $error = 'Username not found or account is inactive.';
        } else {
            $user = $result->fetch_assoc();
            if (!password_verify($pwd, $user['Password'])) {
                $error = 'Incorrect password. Please try again.';
            } else {
                $_SESSION['user_id']   = $user['UserID'];
                $_SESSION['username']  = $user['Username'];
                $_SESSION['firstname'] = $user['FirstName'];
                $_SESSION['lastname']  = $user['LastName'];
                $_SESSION['role']      = $user['Role'];
                header('Location: dashboard.php');
                exit;
            }
        }
        $stmt->close();
    }
}

$pageTitle = 'Login';
require_once 'includes/header.php';
?>

<div class="auth-page">
    <!-- Left panel — CIT-U Maroon -->
    <div class="auth-left">
        <div class="auth-bg-overlay"></div>
        <div class="auth-left-inner">
            <img src="https://www.figma.com/api/mcp/asset/d0a1aca0-7d6e-412b-ae4d-37b9720ca456"
                 alt="CIT-U Logo" class="auth-cit-logo"
                 onerror="this.style.display='none'">
            <div class="auth-brand-title">HUWAM</div>
            <p class="auth-brand-sub">A peer-to-peer item borrowing platform built exclusively for CIT-U students.</p>
        </div>
    </div>

    <!-- Right panel — Form -->
    <div class="auth-right">
        <div class="auth-form-box">
            <div class="auth-form-title" style="color:var(--text-dark);font-size:42px;margin-bottom:24px;">Welcome Back</div>

            <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-circle-xmark"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="auth-card">
                <form method="post">
                    <div class="form-group" style="margin-bottom:14px;">
                        <label for="txtusername">Email / Username</label>
                        <input type="text" id="txtusername" name="txtusername"
                            placeholder="you@cit.edu"
                            value="<?php echo htmlspecialchars($_POST['txtusername'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group" style="margin-bottom:22px;">
                        <label for="txtpassword">Password</label>
                        <input type="password" id="txtpassword" name="txtpassword"
                            placeholder="••••••••" required>
                    </div>
                    <button type="submit" name="btnLogin" class="btn btn-primary btn-block btn-lg">LOG IN</button>
                </form>
            </div>

            <p class="auth-footer-text">
                Don't have an account? <a href="register.php">Sign Up</a>
            </p>

            <div class="alert alert-info" style="margin-top:16px;font-size:12px;">
                <i class="fas fa-circle-info"></i>
                <div>Default admin — <strong>username:</strong> <code>admin</code> / <strong>password:</strong> <code>password</code></div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
