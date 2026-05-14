<?php
require_once 'connect.php';

// fix the bug in Login
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnLogin'])) {
    $uname = trim($_POST['txtusername'] ?? '');
    $pwd   = $_POST['txtpassword'] ?? '';

    if (empty($uname) || empty($pwd)) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $connection->prepare(
            "SELECT * FROM tbluser WHERE (Username = ? OR Institutional_Email = ?) AND isActive = 1"
        );
        $stmt->bind_param('ss', $uname, $uname);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $error = 'Username or email not found, or account is inactive.';
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

<div class="auth-wrap">
    <div class="auth-card">

        <!-- Colored panel — left on login -->
        <div class="auth-panel auth-panel--left">
            <div class="auth-panel-inner">
                <img src="https://www.figma.com/api/mcp/asset/d0a1aca0-7d6e-412b-ae4d-37b9720ca456"
                     alt="CIT-U Logo" class="panel-logo"
                     onerror="this.style.display='none'">
                <div class="panel-brand">HUWAM</div>
                <p class="panel-sub">A peer-to-peer item borrowing platform built exclusively for CIT-U students.</p>
                <a href="register.php" class="panel-btn">Sign Up</a>
            </div>
        </div>

        <!-- Form panel — right on login -->
        <div class="auth-form-panel">
            <div class="auth-form-inner">
                <h1 class="form-title">Welcome Back!</h1>
                <p class="form-sub">Sign in to your account</p>

                <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-circle-xmark"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="post" class="auth-form">
                    <div class="input-group">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="txtusername" placeholder="Username or Email"
                               value="<?php echo htmlspecialchars($_POST['txtusername'] ?? ''); ?>" required>
                    </div>
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="loginPwd" name="txtpassword" placeholder="Password" required>
                        <button type="button" class="eye-btn" onclick="togglePwd('loginPwd', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>

                    <button type="submit" name="btnLogin" class="btn-submit">Log In</button>
                </form>

                <div class="alert alert-info" style="margin-top:20px;font-size:12px;">
                    <i class="fas fa-circle-info"></i>
                    <div>Default admin — <strong>username:</strong> <code>admin</code> / <strong>password:</strong> <code>password</code></div>
                </div>

                <!-- Mobile only link -->
                <p class="mobile-switch">Don't have an account? <a href="register.php">Sign Up</a></p>
            </div>
        </div>

    </div>
</div>

<script>
function togglePwd(id, btn) {
    const inp = document.getElementById(id);
    const ico = btn.querySelector('i');
    if (inp.type === 'password') {
        inp.type = 'text';
        ico.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        inp.type = 'password';
        ico.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>