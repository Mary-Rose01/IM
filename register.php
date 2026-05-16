<?php
require_once 'connect.php';

$error = '';
$success = '';

// Initialize variables
$fname = $mname = $lname = $email = $uname = $student_id = $program = $department = $orgname = $orgtype = $orgemail = '';
$role = 'student';
$yearlevel = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnRegister'])) {
    // 1. Capture and Trim
    $fname      = trim($_POST['txtfirstname'] ?? '');
    $mname      = trim($_POST['txtmiddlename'] ?? '');
    $lname      = trim($_POST['txtlastname'] ?? '');
    $email      = trim($_POST['txtemail'] ?? '');
    $uname      = trim($_POST['txtusername'] ?? ''); 
    $pword      = $_POST['txtpassword'] ?? '';
    $confirm    = $_POST['txtconfirm'] ?? '';
    $role       = $_POST['txtrole'] ?? 'student';

    $student_id  = trim($_POST['txtstudentid'] ?? '');
    $program     = trim($_POST['txtprogram'] ?? '');
    $yearlevel   = intval($_POST['numyearlevel'] ?? 1);
    $department  = trim($_POST['txtdepartment'] ?? '');

    $orgname   = trim($_POST['txtorgname'] ?? '');
    $orgtype   = trim($_POST['txtorgtype'] ?? '');
    $orgemail  = trim($_POST['txtorgemail'] ?? '');

    // 2. Comprehensive Logic Validation
    if (empty($fname) || empty($lname) || empty($email) || empty($uname) || empty($pword)) {
        $error = 'All required basic fields must be filled.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with(strtolower($email), '@cit.edu')) {
        $error = 'A valid @cit.edu institutional email is required.';
    } elseif ($pword !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($pword) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($role === 'student' && (empty($student_id) || empty($department))) {
        $error = 'Student ID and Department are required for students.';
    } elseif ($role === 'organization' && (empty($orgname) || empty($orgemail))) {
        $error = 'Organization Name and Contact Email are required.';
    } else {
        
        // 3. Duplicate Prevention (Username/Email)
        $stmtCheck = $connection->prepare("SELECT UserID FROM tbluser WHERE Username = ? OR Institutional_Email = ?");
        $stmtCheck->bind_param("ss", $uname, $email);
        $stmtCheck->execute();
        $stmtCheck->store_result();

        if ($stmtCheck->num_rows > 0) {
            $error = 'Username or Institutional Email is already registered.';
            $stmtCheck->close();
        } else {
            $stmtCheck->close();
            
            // 4. Duplicate Prevention (Student ID)
            $idExists = false;
            if ($role === 'student') {
                $stmtIdCheck = $connection->prepare("SELECT StudentID FROM tblstudent WHERE StudentID = ?");
                $stmtIdCheck->bind_param("s", $student_id);
                $stmtIdCheck->execute();
                $stmtIdCheck->store_result();
                if ($stmtIdCheck->num_rows > 0) {
                    $idExists = true;
                    $error = 'This Student ID is already registered to an account.';
                }
                $stmtIdCheck->close();
            }

            if (!$idExists) {
                // 5. Database Insertion using Transactions
                $hashedPwd = password_hash($pword, PASSWORD_DEFAULT);
                $isStudent = ($role === 'student') ? 1 : 0;
                $isOrg     = ($role === 'organization') ? 1 : 0;

                $connection->begin_transaction();
                try {
                    // Parent Table: tbluser
                    // FIXED bind_param: Changed "ssssiiiss" to "ssssiisss" 
                    // This ensures Username ($uname) is treated as a string, not an integer.
                    $stmtUser = $connection->prepare("INSERT INTO tbluser (Institutional_Email, FirstName, MiddleName, LastName, isActive, isOrganization, isStudent, Username, Password, Role) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?)");
                    $stmtUser->bind_param("ssssiisss", $email, $fname, $mname, $lname, $isOrg, $isStudent, $uname, $hashedPwd, $role);
                    $stmtUser->execute();
                    $newUserID = $connection->insert_id;

                    // Child Table: Student or Org
                    if ($role === 'student') {
                        $stmtSub = $connection->prepare("INSERT INTO tblstudent (StudentID, Program, YearLevel, Department, UserID) VALUES (?, ?, ?, ?, ?)");
                        $stmtSub->bind_param("ssisi", $student_id, $program, $yearlevel, $department, $newUserID);
                    } else {
                        $stmtSub = $connection->prepare("INSERT INTO tblorganization (OrgName, Type, Accreditation_Status, Contact_Email, UserID) VALUES (?, ?, 1, ?, ?)");
                        $stmtSub->bind_param("sssi", $orgname, $orgtype, $orgemail, $newUserID);
                    }
                    $stmtSub->execute();

                    $connection->commit();
                    $success = 'Registration successful! <a href="login.php" style="color: #800000; font-weight: bold;">Click here to Login</a>';
                    
                    // Reset fields on success
                    $fname = $mname = $lname = $email = $uname = $student_id = $program = $department = $orgname = $orgemail = '';
                } catch (Exception $e) {
                    $connection->rollback();
                    $error = 'Registration Failed: ' . $e->getMessage();
                }
            }
        }
    }
}

$pageTitle = 'Register';
require_once 'includes/header.php';
?>

<div class="auth-wrap">
    <div class="auth-card auth-card--register">
        <div class="auth-form-panel">
            <div class="auth-form-inner" style="max-width:550px; width: 100%;">
                <h1 class="form-title">Create Account</h1>
                <p class="form-sub">Join the CIT-U borrowing community</p>

                <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div><?php endif; ?>

                <form method="post" id="regForm" class="auth-form">
                    <div class="form-row" style="display: flex; gap: 14px; margin-bottom: 14px;">
                        <div class="input-group" style="flex: 1;"><i class="fas fa-user input-icon"></i><input type="text" name="txtfirstname" placeholder="First Name" value="<?php echo htmlspecialchars($fname); ?>" required></div>
                        <div class="input-group" style="flex: 1;"><i class="fas fa-user input-icon"></i><input type="text" name="txtmiddlename" placeholder="Middle Name" value="<?php echo htmlspecialchars($mname); ?>"></div>
                    </div>

                    <div class="form-row" style="display: flex; gap: 14px; margin-bottom: 14px;">
                        <div class="input-group" style="flex: 1;"><i class="fas fa-user input-icon"></i><input type="text" name="txtlastname" placeholder="Last Name" value="<?php echo htmlspecialchars($lname); ?>" required></div>
                        <div class="input-group" style="flex: 1;"><i class="fas fa-at input-icon"></i><input type="text" name="txtusername" placeholder="Username" value="<?php echo htmlspecialchars($uname); ?>" required></div>
                    </div>

                    <div class="form-row" style="display: flex; gap: 14px; margin-bottom: 14px;">
                        <div class="input-group" style="flex: 1.5;"><i class="fas fa-envelope input-icon"></i><input type="email" name="txtemail" placeholder="Institutional Email" pattern=".+@cit\.edu" value="<?php echo htmlspecialchars($email); ?>" required></div>
                        <div class="input-group" style="flex: 1;">
                            <i class="fas fa-users-cog input-icon"></i>
                            <select name="txtrole" id="roleSelect" onchange="toggleFields()" style="padding-left: 40px;">
                                <option value="student" <?php echo ($role === 'student') ? 'selected' : ''; ?>>Student</option>
                                <option value="organization" <?php echo ($role === 'organization') ? 'selected' : ''; ?>>Organization</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row" style="display: flex; gap: 14px; margin-bottom: 14px;">
                        <div class="input-group" style="flex: 1;">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" id="regPwd" name="txtpassword" placeholder="Password" required>
                            <button type="button" class="eye-btn" onclick="togglePwd('regPwd', this)"><i class="fas fa-eye"></i></button>
                        </div>
                        <div class="input-group" style="flex: 1;">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" id="regConfirm" name="txtconfirm" placeholder="Confirm Password" required>
                            <button type="button" class="eye-btn" onclick="togglePwd('regConfirm', this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>

                    <div id="studentFields">
                        <div class="section-label" style="font-weight: 700; color: #800000; margin: 20px 0 10px; font-size: 0.8rem;"><i class="fas fa-graduation-cap"></i> STUDENT INFORMATION</div>
                        <div class="form-row" style="display: flex; gap: 14px; margin-bottom: 14px;">
                            <div class="input-group" style="flex: 1.5;"><i class="fas fa-id-card input-icon"></i><input type="text" name="txtstudentid" id="student_id_field" placeholder="Student ID (e.g. 22-1234-567)" value="<?php echo htmlspecialchars($student_id); ?>"></div>
                            <div class="input-group" style="flex: 1;">
                                <i class="fas fa-layer-group input-icon"></i>
                                <select name="numyearlevel" style="padding-left: 40px;">
                                    <?php for($i=1;$i<=5;$i++): ?><option value="<?php echo $i; ?>" <?php echo ($yearlevel == $i) ? 'selected' : ''; ?>>Year <?php echo $i; ?></option><?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-row" style="display: flex; gap: 14px; margin-bottom: 14px;">
                            <div class="input-group" style="flex: 1;"><i class="fas fa-book input-icon"></i><input type="text" name="txtprogram" placeholder="Program (e.g. BSCS)" value="<?php echo htmlspecialchars($program); ?>"></div>
                            <div class="input-group" style="flex: 1;">
                                <i class="fas fa-university input-icon"></i>
                                <select name="txtdepartment" style="padding-left:40px;">
                                    <option value="">-- Select Department --</option>
                                    <option value="CCS" <?php echo ($department == 'CCS') ? 'selected' : ''; ?>>College of Computer Studies</option>
                                    <option value="CEA" <?php echo ($department == 'CEA') ? 'selected' : ''; ?>>College of Engineering and Architecture</option>
                                    <option value="CASE" <?php echo ($department == 'CASE') ? 'selected' : ''; ?>>College of Arts, Sciences and Education</option>
                                    <option value="CNAHS" <?php echo ($department == 'CNAHS') ? 'selected' : ''; ?>>College of Nursing and Allied Health Sciences</option>
                                    <option value="CMBA" <?php echo ($department == 'CMBA') ? 'selected' : ''; ?>>College of Management, Business and Accountancy</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="orgFields" style="display:none;">
                        <div class="section-label" style="font-weight: 700; color: #800000; margin: 20px 0 10px; font-size: 0.8rem;"><i class="fas fa-building"></i> ORGANIZATION INFORMATION</div>
                        <div class="input-group"><i class="fas fa-users input-icon"></i><input type="text" name="txtorgname" placeholder="Official Organization Name" value="<?php echo htmlspecialchars($orgname); ?>"></div>
                        <div class="form-row" style="display: flex; gap: 14px;">
                            <div class="input-group" style="flex: 1;"><i class="fas fa-tag input-icon"></i><select name="txtorgtype" style="padding-left: 40px;"><option value="Academic">Academic</option><option value="Cultural">Cultural</option><option value="Sports">Sports</option></select></div>
                            <div class="input-group" style="flex: 1;"><i class="fas fa-envelope-open-text input-icon"></i><input type="email" name="txtorgemail" placeholder="Org Contact Email" value="<?php echo htmlspecialchars($orgemail); ?>"></div>
                        </div>
                    </div>

                    <button type="submit" name="btnRegister" class="btn-submit" style="width: 100%; margin-top: 10px; height: 50px; font-weight: bold; font-size: 1rem;">
                        <i class="fas fa-user-plus"></i> REGISTER ACCOUNT
                    </button>
                </form>
                <p style="text-align: center; margin-top: 15px;">Already have an account? <a href="login.php" style="color: #800000; font-weight: bold;">Log In</a></p>
            </div>
        </div>

        <div class="auth-panel auth-panel--right">
            <div class="auth-panel-inner">
                <div class="auth-logo-placeholder" id="logoPlaceholder" style="display:none; margin-bottom:20px;">
                    <i class="fas fa-university"></i>
                </div>
                <img src="img/citlogo.png" 
                     alt="" class="panel-logo"
                     onerror="this.style.display='none'; document.getElementById('logoPlaceholder').style.display='flex';">
                <div class="panel-brand" style="font-size: 2.5rem; letter-spacing: 5px;">HUWAM</div>
                <p class="panel-sub">Access your institutional borrowing account.</p>
                <a href="login.php" class="panel-btn">LOG IN</a>
            </div>
        </div>
    </div>
</div>

<script>
function toggleFields() {
    const role = document.getElementById('roleSelect').value;
    const studentFields = document.getElementById('studentFields');
    const orgFields = document.getElementById('orgFields');
    const studentIdInput = document.getElementById('student_id_field');

    if (role === 'student') {
        studentFields.style.display = 'block';
        orgFields.style.display = 'none';
        studentIdInput.required = true;
    } else {
        studentFields.style.display = 'none';
        orgFields.style.display = 'block';
        studentIdInput.required = false;
    }
}

function togglePwd(id, btn) {
    const input = document.getElementById(id);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

window.onload = toggleFields;
</script>

<?php require_once 'includes/footer.php'; ?>