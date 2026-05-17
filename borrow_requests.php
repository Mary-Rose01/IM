<?php
require_once 'connect.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$msg = ''; 
$msgType = 'success';
$isAdmin = ($_SESSION['role'] === 'admin');

// --- DELETE ---
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    // Check permission: Admin or Requester
    $check = $connection->query("SELECT UserID FROM tblborrowrequest WHERE RequestID = $id")->fetch_assoc();
    if ($isAdmin || ($check && $check['UserID'] == $_SESSION['user_id'])) {
        $connection->query("DELETE FROM tblborrowrequest WHERE RequestID = $id");
        $msg = 'Request deleted.';
    } else {
        $msg = 'Permission denied.'; 
        $msgType = 'danger';
    }
    $action = 'list';
}

// --- UPDATE STATUS (admin) ---
if ($action === 'status' && isset($_GET['id']) && isset($_GET['s'])) {
    $id = intval($_GET['id']);
    $s  = $connection->real_escape_string($_GET['s']);
    $now = date('Y-m-d H:i:s');
    $connection->query("UPDATE tblborrowrequest SET Status='$s', ReviewedAt='$now' WHERE RequestID=$id");

    // Notify the requester
    $reqRow = $connection->query("SELECT UserID FROM tblborrowrequest WHERE RequestID=$id")->fetch_assoc();
    if ($reqRow) {
        $notifMsg = $connection->real_escape_string("Your borrow request #$id has been $s.");
        $notifLink = $connection->real_escape_string("borrow_requests.php?q=&status=$s");
        $connection->query("INSERT INTO tblnotification (UserID, Message, Link) VALUES ({$reqRow['UserID']}, '$notifMsg', '$notifLink')");
    }

    $msg = "Request marked as $s.";
    $action = 'list';
}

// --- SAVE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = intval($_POST['hdnID'] ?? 0);
    $userID  = $isAdmin ? intval($_POST['hdnUser'] ?? $_SESSION['user_id']) : $_SESSION['user_id'];
    $bookID  = intval($_POST['hdnBooking'] ?? 1);
    $start   = trim($_POST['txtstart'] ?? '');
    $end     = trim($_POST['txtend'] ?? '');
    $status  = trim($_POST['txtstatus'] ?? 'Pending');

    // Validation: Ensure requested time is not in the past and end is after start
    $now = time();
    if (strtotime($start) < ($now - 60)) { // 60s grace period for form submission
        $msg = 'Requested start time cannot be in the past.';
        $msgType = 'danger';
        $action = ($id === 0) ? 'add' : 'edit';
    } elseif (strtotime($end) <= strtotime($start)) {
        $msg = 'Requested end time must be after the start time.';
        $msgType = 'danger';
        $action = ($id === 0) ? 'add' : 'edit';
    } elseif ($id === 0) {
        $stmt = $connection->prepare("INSERT INTO tblborrowrequest (UserID,BookingID,Requested_Start,Requested_End,Status) VALUES (?,?,?,?,?)");
        $stmt->bind_param('iisss', $userID,$bookID,$start,$end,$status);
        if ($stmt->execute()) $msg = 'Borrow request created.';
        else { $msg = $stmt->error; $msgType='danger'; }
        $stmt->close();
        $action = 'list';
    } else {
        // Security Check: Admin or Requester
        $check = $connection->query("SELECT UserID FROM tblborrowrequest WHERE RequestID = $id")->fetch_assoc();
        if ($isAdmin || ($check && $check['UserID'] == $_SESSION['user_id'])) {
            $stmt = $connection->prepare("UPDATE tblborrowrequest SET Requested_Start=?,Requested_End=?,Status=? WHERE RequestID=?");
            $stmt->bind_param('sssi', $start,$end,$status,$id);
            if ($stmt->execute()) $msg = 'Request updated.';
            else { $msg = $stmt->error; $msgType='danger'; }
            $stmt->close();
        } else {
            $msg = 'Permission denied.';
            $msgType = 'danger';
        }
        $action = 'list';
    }
}

$editRow = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $editRow = $connection->query("SELECT * FROM tblborrowrequest WHERE RequestID=$id")->fetch_assoc();
    // Security Check
    if (!$isAdmin && (!$editRow || $editRow['UserID'] != $_SESSION['user_id'])) {
        header("Location: borrow_requests.php?msg=Permission+denied&msgType=danger");
        exit;
    }
}

// For Add form: load available items
$availableItems = $connection->query(
    "SELECT i.ItemID, i.Item_Name, i.Category
     FROM tblitem i
     WHERE i.Availability_Status = 'Available'
     ORDER BY i.Item_Name"
);

// Pre-selected item (from items.php "Request to Borrow" button)
$preItemID = intval($_GET['item_id'] ?? 0);

// List
$search = trim($_GET['q'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$where = $isAdmin ? '1=1' : "r.UserID = {$_SESSION['user_id']}";
if ($filterStatus) { $fs=$connection->real_escape_string($filterStatus); $where.=" AND r.Status='$fs'"; }
if ($search) { $s=$connection->real_escape_string($search); $where.=" AND (u.FirstName LIKE '%$s%' OR u.LastName LIKE '%$s%')"; }

// Pagination Logic
$limit = 10;
$p = max(1, intval($_GET['p'] ?? 1));
$off = ($p - 1) * $limit;

$totalCountResult = $connection->query("SELECT COUNT(*) c FROM tblborrowrequest r JOIN tbluser u ON r.UserID=u.UserID WHERE $where");
$totalCount = $totalCountResult->fetch_assoc()['c'];
$totalPages = max(1, ceil($totalCount / $limit));

$requests = $connection->query(
    "SELECT r.*, CONCAT(u.FirstName,' ',u.LastName) as RequesterName
     FROM tblborrowrequest r JOIN tbluser u ON r.UserID=u.UserID
     WHERE $where ORDER BY r.CreatedAt DESC LIMIT $limit OFFSET $off"
);

$users = $isAdmin ? $connection->query("SELECT UserID,CONCAT(FirstName,' ',LastName) as Name FROM tbluser WHERE isActive=1 ORDER BY FirstName") : null;
$bookings = $connection->query("SELECT BookingID FROM tblbooking ORDER BY BookingID DESC LIMIT 20");

$pageTitle = 'Borrow Requests';
require_once 'includes/header.php';
?>

<?php require_once "includes/navbar.php"; ?>
<div class="layout-top">
<div class="page-wrapper">

<div class="page-header">
    <div class="page-title"><h1>Borrow Requests</h1><p>View and manage all borrow requests</p></div>
    <div style="display:flex;gap:10px;"><a href="?action=add" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Request</a></div>
</div>
    <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msgType; ?> auto-dismiss"><i class="fas fa-circle-check"></i> <?php echo $msg; ?></div>
    <?php endif; ?>

    <?php if ($action === 'add' || $action === 'edit'): ?>
    <div class="card" style="max-width:640px;margin-bottom:24px;">
        <div class="card-header">
            <div><h3><?php echo $action==='edit'?'Edit Request':'New Borrow Request'; ?></h3>
            <?php if ($action === 'add'): ?><p style="margin-top:2px;font-size:12px;color:var(--text-muted);">Select an item, then pick an available time slot.</p><?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <form method="post" id="requestForm">
                <input type="hidden" name="hdnID" value="<?php echo $editRow['RequestID'] ?? 0; ?>">
                <input type="hidden" name="hdnBooking" id="hdnBooking" value="<?php echo $editRow['BookingID'] ?? ''; ?>">

                <?php if ($action === 'add'): ?>
                <div class="form-group" style="margin-bottom:16px;">
                    <label>1. Select Item <span class="required">*</span></label>
                    <select id="itemSelect" style="width:100%;padding:9px 12px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-size:13px;">
                        <option value="">— Choose an available item —</option>
                        <?php if ($availableItems && $availableItems->num_rows > 0):
                            while ($itm = $availableItems->fetch_assoc()): ?>
                        <option value="<?php echo $itm['ItemID']; ?>" <?php echo $preItemID === intval($itm['ItemID']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($itm['Item_Name']); ?> <?php echo $itm['Category'] ? '('.$itm['Category'].')' : ''; ?>
                        </option>
                        <?php endwhile; else: ?>
                        <option disabled>No items with open slots available</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group" id="slotGroup" style="margin-bottom:16px;display:<?php echo $preItemID ? 'block' : 'none'; ?>;">
                    <label>2. Select Availability Slot <span class="required">*</span></label>
                    <select id="slotSelect" style="width:100%;padding:9px 12px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-size:13px;">
                        <option value="">— Choose a slot —</option>
                    </select>
                </div>

                <div id="bookingChip" style="display:none;margin-bottom:16px;padding:10px 14px;background:var(--cream);border:1px solid var(--cream-border);border-radius:var(--radius-sm);font-size:13px;">
                    <i class="fas fa-calendar-check" style="color:var(--maroon);margin-right:6px;"></i>
                    <strong>Booking slot:</strong> <span id="bookingChipText"></span>
                </div>

                <?php if ($isAdmin): ?>
                <div class="form-group" style="margin-bottom:16px;">
                    <label>Requester</label>
                    <select name="hdnUser">
                        <?php if ($users) { $users->data_seek(0); while($u=$users->fetch_assoc()): ?>
                        <option value="<?php echo $u['UserID']; ?>"><?php echo htmlspecialchars($u['Name']); ?></option>
                        <?php endwhile; } ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:16px;">
                    <label>Status</label>
                    <select name="txtstatus">
                        <?php foreach(['Pending','Approved','Rejected','Cancelled','Completed'] as $st): ?>
                        <option value="<?php echo $st; ?>"><?php echo $st; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <input type="hidden" name="txtstart" id="txtstart">
                <input type="hidden" name="txtend" id="txtend">

                <?php else: ?>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Requested Start <span class="required">*</span></label>
                        <input type="datetime-local" name="txtstart" value="<?php echo str_replace(' ','T',$editRow['Requested_Start']??''); ?>" min="<?php echo date('Y-m-d\TH:i'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Requested End <span class="required">*</span></label>
                        <input type="datetime-local" name="txtend" value="<?php echo str_replace(' ','T',$editRow['Requested_End']??''); ?>" min="<?php echo date('Y-m-d\TH:i'); ?>" required>
                    </div>
                    <?php if ($isAdmin): ?>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="txtstatus">
                            <?php foreach(['Pending','Approved','Rejected','Cancelled','Completed'] as $st): ?>
                            <option value="<?php echo $st; ?>" <?php if(($editRow['Status']??'Pending')===$st) echo 'selected'; ?>><?php echo $st; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div style="margin-top:20px;display:flex;gap:10px;">
                    <button type="button" onclick="validateRequestAndShowModal()" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                    <button type="button" onclick="showConfirmModal('borrow_requests.php', 'Discard Changes', 'Are you sure you want to leave? Any unsaved changes will be lost.')" class="btn btn-outline">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div><h3>All Requests</h3><p><?php echo $totalCount; ?> request(s)</p></div>
            <form method="get" style="display:flex;gap:8px;align-items:center;">
                <div class="search-bar"><i class="fas fa-search"></i><input type="text" name="q" placeholder="Search requester..." value="<?php echo htmlspecialchars($search); ?>"></div>
                <select name="status" style="padding:7px 10px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-size:13px;">
                    <option value="">All Status</option>
                    <?php foreach(['Pending','Approved','Rejected','Cancelled','Completed'] as $st): ?>
                    <option value="<?php echo $st; ?>" <?php if($filterStatus===$st) echo 'selected'; ?>><?php echo $st; ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline btn-sm">Filter</button>
                <a href="borrow_requests.php" class="btn btn-outline btn-sm"><i class="fas fa-rotate-left"></i></a>
            </form>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr><th>#</th><th>Requester</th><th>Requested Period</th><th>Status</th><th>Reviewed At</th><th>Created</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if ($requests->num_rows > 0): while($row=$requests->fetch_assoc()): ?>
                    <tr>
                        <td class="mono">#<?php echo $row['RequestID']; ?></td>
                        <td style="font-weight:600;"><?php echo htmlspecialchars($row['RequesterName']); ?></td>
                        <td style="font-size:12px;">
                            <div><?php echo date('M j, Y g:i A', strtotime($row['Requested_Start'])); ?></div>
                            <div style="color:var(--gray-400);">→ <?php echo date('M j, Y g:i A', strtotime($row['Requested_End'])); ?></div>
                        </td>
                        <td>
                            <?php $sc=$row['Status']==='Approved'?'badge-success':($row['Status']==='Pending'?'badge-warning':($row['Status']==='Rejected'||$row['Status']==='Cancelled'?'badge-danger':'badge-info')); ?>
                            <span class="badge <?php echo $sc; ?>"><?php echo $row['Status']; ?></span>
                        </td>
                        <td style="font-size:12px;color:var(--gray-500);"><?php echo $row['ReviewedAt'] ? date('M j, Y', strtotime($row['ReviewedAt'])) : '—'; ?></td>
                        <td style="font-size:12px;color:var(--gray-500);"><?php echo date('M j, Y', strtotime($row['CreatedAt'])); ?></td>
                        <td style="display:flex;gap:4px;flex-wrap:wrap;">
                            <?php if ($isAdmin && $row['Status'] === 'Pending'): ?>
                            <button type="button" onclick="showConfirmModal('?action=status&id=<?php echo $row['RequestID']; ?>&s=Approved', 'Approve Request', 'Do you want to approve this borrow request?', 'btn-primary')" class="btn btn-success btn-sm"><i class="fas fa-check"></i></button>
                            <button type="button" onclick="showConfirmModal('?action=status&id=<?php echo $row['RequestID']; ?>&s=Rejected', 'Decline Request', 'Are you sure you want to decline this request?', 'btn-outline')" class="btn btn-danger btn-sm"><i class="fas fa-xmark"></i></button>
                            <?php endif; ?>
                            <a href="?action=edit&id=<?php echo $row['RequestID']; ?>" class="btn btn-outline btn-sm"><i class="fas fa-pen"></i></a>
                            <?php if ($isAdmin): ?>
                            <button type="button" onclick="showConfirmModal('?action=delete&id=<?php echo $row['RequestID']; ?>', 'Delete Request', 'Are you sure you want to delete this borrow request? This action cannot be undone.')" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="7"><div class="empty-state"><i class="fas fa-hand-holding-box"></i><h3>No borrow requests yet</h3></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination UI -->
        <?php if ($totalPages >= 1): ?>
        <div class="pagination" style="margin-top:0; border-top:1px solid var(--cream-border);">
            <?php if ($p > 1): ?>
                <a href="?p=<?php echo $p-1; ?>&q=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filterStatus); ?>" class="page-link" style="width:auto; padding:0 15px; margin-right:5px;"><i class="fas fa-chevron-left"></i> Back</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?p=<?php echo $i; ?>&q=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filterStatus); ?>" class="page-link <?php echo $i == $p ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($p < $totalPages): ?>
                <a href="?p=<?php echo $p+1; ?>&q=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filterStatus); ?>" class="page-link" style="width:auto; padding:0 15px; margin-left:5px;">Next <i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
            <div class="page-info">Showing page <?php echo $p; ?> of <?php echo $totalPages; ?></div>
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
// ---- Toast notification (replaces browser alert/confirm) ----
function showToast(message, type = 'danger') {
    let toast = document.getElementById('toastNotif');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toastNotif';
        toast.style.cssText = 'position:fixed;bottom:28px;right:28px;z-index:9999;min-width:280px;max-width:380px;padding:14px 18px;border-radius:10px;font-size:14px;font-weight:500;box-shadow:0 4px 20px rgba(0,0,0,0.15);display:flex;align-items:center;gap:10px;transition:opacity 0.3s;';
        document.body.appendChild(toast);
    }
    const colors = {
        danger:  { bg: '#fef2f2', border: '#fecaca', text: '#991b1b', icon: 'fa-circle-xmark' },
        warning: { bg: '#fffbeb', border: '#fde68a', text: '#92400e', icon: 'fa-triangle-exclamation' },
        success: { bg: '#f0fdf4', border: '#bbf7d0', text: '#166534', icon: 'fa-circle-check' },
        info:    { bg: '#eff6ff', border: '#bfdbfe', text: '#1e40af', icon: 'fa-circle-info' },
    };
    const c = colors[type] || colors.info;
    toast.style.background = c.bg;
    toast.style.border = '1.5px solid ' + c.border;
    toast.style.color = c.text;
    toast.style.opacity = '1';
    toast.innerHTML = `<i class="fas ${c.icon}" style="font-size:16px;flex-shrink:0;"></i><span>${message}</span>`;
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => { toast.style.opacity = '0'; }, 3500);
}

// ---- Item → Slot → Booking chain (Add form only) ----
const itemSelect    = document.getElementById('itemSelect');
const slotSelect    = document.getElementById('slotSelect');
const slotGroup     = document.getElementById('slotGroup');
const bookingChip   = document.getElementById('bookingChip');
const bookingChipText = document.getElementById('bookingChipText');
const hdnBooking    = document.getElementById('hdnBooking');
const txtstart      = document.getElementById('txtstart');
const txtend        = document.getElementById('txtend');

if (itemSelect) {
    itemSelect.addEventListener('change', function() {
        const itemID = this.value;
        slotGroup.style.display = 'none';
        bookingChip.style.display = 'none';
        if (hdnBooking) hdnBooking.value = '';
        if (txtstart) txtstart.value = '';
        if (txtend)   txtend.value = '';
        slotSelect.innerHTML = '<option value="">— Loading slots… —</option>';
        if (!itemID) return;

        fetch('get_slots.php?item_id=' + itemID)
            .then(r => r.json())
            .then(slots => {
                slotSelect.innerHTML = '<option value="">— Choose a time slot —</option>';
                if (slots.length === 0) {
                    slotSelect.innerHTML += '<option value="" disabled>No open slots for this item yet</option>';
                    slotGroup.style.display = 'block';
                    showToast('This item has no available time slots yet. Contact the admin to add slots.', 'warning');
                } else {
                    slots.forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s.SlotID;
                        opt.textContent = s.label;
                        opt.dataset.start = s.Start_DateTime;
                        opt.dataset.end   = s.End_DateTime;
                        slotSelect.appendChild(opt);
                    });
                    slotGroup.style.display = 'block';
                }
            })
            .catch(() => {
                slotSelect.innerHTML = '<option disabled>Error loading slots</option>';
                slotGroup.style.display = 'block';
                showToast('Could not load slots. Please refresh the page.', 'danger');
            });
    });

    slotSelect.addEventListener('change', function() {
        const slotID = this.value;
        bookingChip.style.display = 'none';
        if (hdnBooking) hdnBooking.value = '';
        if (txtstart) txtstart.value = '';
        if (txtend)   txtend.value = '';
        if (!slotID) return;

        const opt = this.options[this.selectedIndex];
        if (txtstart) txtstart.value = opt.dataset.start;
        if (txtend)   txtend.value   = opt.dataset.end;

        fetch('get_booking.php?slot_id=' + slotID)
            .then(r => r.json())
            .then(data => {
                if (data.error) { showToast('Booking error: ' + data.error, 'danger'); return; }
                if (hdnBooking) hdnBooking.value = data.booking_id;
                bookingChipText.textContent = opt.textContent + ' (Booking #' + data.booking_id + ')';
                bookingChip.style.display = 'block';
            })
            .catch(() => showToast('Could not create booking. Please try again.', 'danger'));
    });

    // Auto-trigger if item was pre-selected (from "Request to Borrow" button)
    if (itemSelect.value) {
        itemSelect.dispatchEvent(new Event('change'));
    }
}

// ---- Validation & Modal ----
function validateRequestAndShowModal() {
    const form = document.getElementById('requestForm');
    const isAddForm = !!document.getElementById('itemSelect');

    if (isAddForm) {
        if (!itemSelect || !itemSelect.value) {
            showToast('Please select an item.', 'warning'); return;
        }
        if (!slotSelect || !slotSelect.value) {
            showToast('Please select a time slot.', 'warning'); return;
        }
        if (!hdnBooking || !hdnBooking.value) {
            showToast('Booking is still being set up. Please wait a moment and try again.', 'warning'); return;
        }
    }

    if (!form.checkValidity()) { form.reportValidity(); return; }
    showConfirmModal('SUBMIT_FORM', 'Submit Request', 'Are you sure you want to submit this borrow request?', 'btn-primary');
}

function showConfirmModal(url, title, message, btnClass = 'btn-danger') {
    document.getElementById('modalTitle').innerText = title;
    document.getElementById('modalMessage').innerText = message;
    const confirmBtn = document.getElementById('modalConfirmBtn');
    if (url === 'SUBMIT_FORM') {
        confirmBtn.href = "javascript:void(0)";
        confirmBtn.onclick = () => document.getElementById('requestForm').submit();
    } else {
        confirmBtn.href = url;
        confirmBtn.onclick = null;
    }
    confirmBtn.className = 'btn ' + btnClass;
    document.getElementById('confirmModalOverlay').classList.add('open');
}

function closeConfirmModal() {
    document.getElementById('confirmModalOverlay').classList.remove('open');
}
</script>

<?php require_once 'includes/footer.php'; ?>
