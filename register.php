<?php
require_once 'connect.php';

// fixed the error 

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnRegister'])) {
    $fname     = trim($_POST['txtfirstname'] ?? '');
    $mname     = trim($_POST['txtmiddlename'] ?? '');
    $lname     = trim($_POST['txtlastname'] ?? '');
    $email     = trim($_POST['txtemail'] ?? '');
    $uname     = trim($_POST['txtusername'] ?? '');
    $pword     = $_POST['txtpassword'] ?? '';
    $confirm   = $_POST['txtconfirm'] ?? '';
    $role      = $_POST['txtrole'] ?? 'student';

    $student_id  = trim($_POST['txtstudentid'] ?? '');
    $program     = trim($_POST['txtprogram'] ?? '');
    $yearlevel   = intval($_POST['numyearlevel'] ?? 1);
    $department  = trim($_POST['txtdepartment'] ?? '');

    $orgname   = trim($_POST['txtorgname'] ?? '');
    $orgtype   = trim($_POST['txtorgtype'] ?? '');
    $orgemail  = trim($_POST['txtorgemail'] ?? '');

    if (empty($fname) || empty($lname) || empty($email) || empty($uname) || empty($pword)) {
        $error = 'Please fill in all required fields.';
    } elseif ($pword !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($pword) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $chk = $connection->prepare("SELECT UserID FROM tbluser WHERE Username = ? OR Institutional_Email = ?");
        $chk->bind_param('ss', $uname, $email);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            $error = 'Username or email already exists.';
        } else {
            $hashed = password_hash($pword, PASSWORD_DEFAULT);
            $isStudent = ($role === 'student') ? 1 : 0;
            $isOrg     = ($role === 'organization') ? 1 : 0;

            $connection->begin_transaction();
            try {
                $ins = $connection->prepare(
                    "INSERT INTO tbluser (Institutional_Email, FirstName, MiddleName, LastName, isActive, isOrganization, isStudent, Username, Password, Role)
                     VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?)"
                );
                $ins->bind_param('ssssiiiss', $email, $fname, $mname, $lname, $isOrg, $isStudent, $uname, $hashed, $role);
                $ins->execute();
                $newUserID = $connection->insert_id;
                $ins->close();

                if ($role === 'student') {
                    $ins2 = $connection->prepare(
                        "INSERT INTO tblstudent (StudentID, Program, YearLevel, Department, UserID) VALUES (?, ?, ?, ?, ?)"
                    );
                    $ins2->bind_param('ssisi', $student_id, $program, $yearlevel, $department, $newUserID);
                    $ins2->execute();
                    $ins2->close();
                } elseif ($role === 'organization') {
                    $ins3 = $connection->prepare(
                        "INSERT INTO tblorganization (OrgName, Type, Accreditation_Status, Contact_Email, UserID) VALUES (?, ?, 1, ?, ?)"
                    );
                    $ins3->bind_param('sssi', $orgname, $orgtype, $orgemail, $newUserID);
                    $ins3->execute();
                    $ins3->close();
                }

                $connection->commit();
                $success = 'Account created! You may now <a href="login.php">log in</a>.';
            } catch (Exception $e) {
                $connection->rollback();
                $error = 'Registration failed: ' . $e->getMessage();
            }
        }
        $chk->close();
    }
}

$pageTitle = 'Register';
require_once 'includes/header.php';
?>

<div class="auth-wrap">
    <div class="auth-card auth-card--register">

        <!-- Form panel — left on register -->
        <div class="auth-form-panel">
            <div class="auth-form-inner" style="max-width:520px;">
                <h1 class="form-title">Create Account</h1>
                <p class="form-sub">Join the CIT-U borrowing community</p>

                <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-circle-xmark"></i> <?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-circle-check"></i> <?php echo $success; ?></div>
                <?php endif; ?>

                <form method="post" id="regForm" class="auth-form">
                    <div class="reg-grid">
                        <div class="input-group">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" name="txtfirstname" placeholder="First Name"
                                   value="<?php echo htmlspecialchars($_POST['txtfirstname'] ?? ''); ?>" required>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" name="txtmiddlename" placeholder="Middle Name"
                                   value="<?php echo htmlspecialchars($_POST['txtmiddlename'] ?? ''); ?>">
                        </div>
                        <div class="input-group">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" name="txtlastname" placeholder="Last Name"
                                   value="<?php echo htmlspecialchars($_POST['txtlastname'] ?? ''); ?>" required>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" name="txtemail" placeholder="Institutional Email (you@cit.edu)"
                                   value="<?php echo htmlspecialchars($_POST['txtemail'] ?? ''); ?>" required>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-at input-icon"></i>
                            <input type="text" name="txtusername" placeholder="Username"
                                   value="<?php echo htmlspecialchars($_POST['txtusername'] ?? ''); ?>" required>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-id-badge input-icon"></i>
                            <select name="txtrole" id="roleSelect" onchange="toggleFields()" style="padding-left:40px;">
                                <option value="student" <?php if(($_POST['txtrole']??'student')==='student') echo 'selected'; ?>>Student</option>
                                <option value="organization" <?php if(($_POST['txtrole']??'')==='organization') echo 'selected'; ?>>Organization</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" id="regPwd" name="txtpassword" placeholder="Password" required>
                            <button type="button" class="eye-btn" onclick="togglePwd('regPwd', this)"><i class="fas fa-eye"></i></button>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" id="regConfirm" name="txtconfirm" placeholder="Confirm Password" required>
                            <button type="button" class="eye-btn" onclick="togglePwd('regConfirm', this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>

                    <!-- Student fields -->
                    <div id="studentFields">
                        <div class="section-label"><i class="fas fa-user-graduate"></i> Student Information</div>
                        <div class="reg-grid">
                            <div class="input-group">
                                <i class="fas fa-id-card input-icon"></i>
                                <input type="text" name="txtstudentid" placeholder="Student ID (e.g. 22-1234-567)"
                                       value="<?php echo htmlspecialchars($_POST['txtstudentid'] ?? ''); ?>">
                            </div>
                            <div class="input-group">
                                <i class="fas fa-layer-group input-icon"></i>
                                <select name="numyearlevel" style="padding-left:40px;">
                                    <?php for($i=1;$i<=5;$i++): ?>
                                    <option value="<?php echo $i; ?>" <?php if(($_POST['numyearlevel']??1)==$i) echo 'selected'; ?>>Year <?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="input-group">
                                <i class="fas fa-book input-icon"></i>
                                <input type="text" name="txtprogram" placeholder="Program (e.g. BSCS)"
                                       value="<?php echo htmlspecialchars($_POST['txtprogram'] ?? ''); ?>">
                            </div>
                            <div class="input-group">
                                <i class="fas fa-building-columns input-icon"></i>
                                <select name="txtdepartment" style="padding-left:40px;">
                                    <option value="">-- Select Department --</option>
                                    <option value="College of Computer Studies">College of Computer Studies</option>
                                    <option value="College of Architecture and Engineering">College of Architecture and Engineering</option>
                                    <option value="College of Nursing and Allied Health Sciences">College of Nursing and Allied Health Sciences</option>
                                    <option value="College of Arts, Sciences and Education">College of Arts, Sciences and Education</option>
                                    <option value="College of Management, Business and Accountancy">College of Management, Business and Accountancy</option>
                                    <option value="College of Criminal Justice">College of Criminal Justice</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Organization fields -->
                    <div id="orgFields" style="display:none;">
                        <div class="section-label"><i class="fas fa-building"></i> Organization Information</div>
                        <div class="reg-grid">
                            <div class="input-group reg-span2">
                                <i class="fas fa-building input-icon"></i>
                                <input type="text" name="txtorgname" placeholder="Organization Name"
                                       value="<?php echo htmlspecialchars($_POST['txtorgname'] ?? ''); ?>">
                            </div>
                            <div class="input-group">
                                <i class="fas fa-tag input-icon"></i>
                                <select name="txtorgtype" style="padding-left:40px;">
                                    <option value="">-- Type --</option>
                                    <option value="Academic">Academic</option>
                                    <option value="Cultural">Cultural</option>
                                    <option value="Sports">Sports</option>
                                    <option value="Civic">Civic</option>
                                    <option value="Others">Others</option>
                                </select>
                            </div>
                            <div class="input-group">
                                <i class="fas fa-envelope input-icon"></i>
                                <input type="email" name="txtorgemail" placeholder="Contact Email"
                                       value="<?php echo htmlspecialchars($_POST['txtorgemail'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="btnRegister" class="btn-submit" style="margin-top:8px;">
                        <i class="fas fa-user-plus"></i> Register Account
                    </button>
                </form>

                <p class="mobile-switch">Already have an account? <a href="login.php">Log In</a></p>
            </div>
        </div>

        <!-- Colored panel — right on register -->
        <div class="auth-panel auth-panel--right">
            <div class="auth-panel-inner">
                <img src="https://www.figma.com/api/mcp/asset/d0a1aca0-7d6e-412b-ae4d-37b9720ca456"
                     alt="CIT-U Logo" class="panel-logo"
                     onerror="this.style.display='none'">
                <div class="panel-brand">HUWAM</div>
                <p class="panel-sub">Already part of the community? Sign in to your account.</p>
                <a href="login.php" class="panel-btn">Log In</a>
            </div>
        </div>

    </div>
</div>

<script>
function toggleFields() {
    const role = document.getElementById('roleSelect').value;
    document.getElementById('studentFields').style.display = role === 'student' ? 'block' : 'none';
    document.getElementById('orgFields').style.display = role === 'organization' ? 'block' : 'none';
}

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

toggleFields();
</script>

<?php require_once 'includes/footer.php'; ?>
