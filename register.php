<?php
require_once 'connect.php';

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

    // Student fields
    $student_id  = trim($_POST['txtstudentid'] ?? '');
    $program     = trim($_POST['txtprogram'] ?? '');
    $yearlevel   = intval($_POST['numyearlevel'] ?? 1);
    $department  = trim($_POST['txtdepartment'] ?? '');

    // Org fields
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
        // Check unique username
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
                // Insert user
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
                $success = 'Account registered successfully! You may now <a href="login.php">log in</a>.';
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

<div class="auth-page" style="align-items:flex-start;">
    <div class="auth-left" style="position:sticky;top:0;min-height:100vh;">
        <div class="auth-bg-overlay"></div>
        <div class="auth-left-inner">
            <div class="auth-brand-title">HUWAM</div>
            <p class="auth-brand-sub">Join the CIT-U borrowing community. Share items with fellow Wildcats.</p>
        </div>
    </div>
    <div class="auth-right" style="align-items:flex-start;padding:48px 60px;overflow-y:auto;">
    <div style="width:100%;max-width:560px;">
        <div style="font-family:var(--font-auth);font-size:40px;font-weight:600;color:var(--text-dark);letter-spacing:-2px;margin-bottom:24px;">Create Account</div>

        <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-circle-xmark"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-circle-check"></i> <?php echo $success; ?></div>
        <?php endif; ?>

        <form method="post" id="regForm">
            <div class="form-grid" style="margin-bottom:14px;">
                <div class="form-group">
                    <label>First Name <span class="required">*</span></label>
                    <input type="text" name="txtfirstname" value="<?php echo htmlspecialchars($_POST['txtfirstname'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Middle Name</label>
                    <input type="text" name="txtmiddlename" value="<?php echo htmlspecialchars($_POST['txtmiddlename'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Last Name <span class="required">*</span></label>
                    <input type="text" name="txtlastname" value="<?php echo htmlspecialchars($_POST['txtlastname'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Institutional Email <span class="required">*</span></label>
                    <input type="email" name="txtemail" placeholder="you@cit.edu" value="<?php echo htmlspecialchars($_POST['txtemail'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Username <span class="required">*</span></label>
                    <input type="text" name="txtusername" value="<?php echo htmlspecialchars($_POST['txtusername'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Role <span class="required">*</span></label>
                    <select name="txtrole" id="roleSelect" onchange="toggleFields()">
                        <option value="student" <?php if(($_POST['txtrole']??'student')==='student') echo 'selected'; ?>>Student</option>
                        <option value="organization" <?php if(($_POST['txtrole']??'')==='organization') echo 'selected'; ?>>Organization</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Password <span class="required">*</span></label>
                    <input type="password" name="txtpassword" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password <span class="required">*</span></label>
                    <input type="password" name="txtconfirm" required>
                </div>
            </div>

            <!-- Student fields -->
            <div id="studentFields">
                <hr class="divider">
                <p style="font-size:13px;font-weight:700;color:var(--gray-700);margin-bottom:14px;"><i class="fas fa-user-graduate" style="color:var(--primary);"></i> Student Information</p>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Student ID <span class="required">*</span></label>
                        <input type="text" name="txtstudentid" placeholder="e.g. 22-1234-567" value="<?php echo htmlspecialchars($_POST['txtstudentid'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Year Level <span class="required">*</span></label>
                        <select name="numyearlevel">
                            <?php for($i=1;$i<=5;$i++): ?>
                            <option value="<?php echo $i; ?>" <?php if(($_POST['numyearlevel']??1)==$i) echo 'selected'; ?>>Year <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Program <span class="required">*</span></label>
                        <input type="text" name="txtprogram" placeholder="e.g. BSCS" value="<?php echo htmlspecialchars($_POST['txtprogram'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Department <span class="required">*</span></label>
                        <select name="txtdepartment">
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
                <hr class="divider">
                <p style="font-size:13px;font-weight:700;color:var(--gray-700);margin-bottom:14px;"><i class="fas fa-building" style="color:var(--primary);"></i> Organization Information</p>
                <div class="form-grid">
                    <div class="form-group span-2">
                        <label>Organization Name</label>
                        <input type="text" name="txtorgname" value="<?php echo htmlspecialchars($_POST['txtorgname'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="txtorgtype">
                            <option value="">-- Select Type --</option>
                            <option value="Academic">Academic</option>
                            <option value="Cultural">Cultural</option>
                            <option value="Sports">Sports</option>
                            <option value="Civic">Civic</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Contact Email</label>
                        <input type="email" name="txtorgemail" value="<?php echo htmlspecialchars($_POST['txtorgemail'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div style="margin-top:24px;display:flex;gap:10px;align-items:center;">
                <button type="submit" name="btnRegister" class="btn btn-primary btn-lg">
                    <i class="fas fa-user-plus"></i> Register Account
                </button>
                <a href="login.php" class="btn btn-outline">Back to Login</a>
            </div>
        </form>
    </div>
</div>

<script>
function toggleFields() {
    const role = document.getElementById('roleSelect').value;
    document.getElementById('studentFields').style.display = role === 'student' ? 'block' : 'none';
    document.getElementById('orgFields').style.display = role === 'organization' ? 'block' : 'none';
}
toggleFields();
</script>

<?php require_once 'includes/footer.php'; ?>
