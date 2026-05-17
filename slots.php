<?php
require_once 'connect.php';
requireLogin();

$action  = $_GET['action'] ?? 'list';
$msg     = $_GET['msg'] ?? '';
$msgType = $_GET['msgType'] ?? 'success';
$isAdmin = ($_SESSION['role'] === 'admin');
$uid     = $_SESSION['user_id'];

// --- DELETE ---
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    // Only admin or the item owner can delete a slot
    $check = $connection->query(
        "SELECT i.OwnerUserID FROM tblavailabilityslot s
         JOIN tblitem i ON s.ItemID = i.ItemID
         WHERE s.SlotID = $id"
    )->fetch_assoc();
    if ($isAdmin || ($check && $check['OwnerUserID'] == $uid)) {
        $connection->query("DELETE FROM tblavailabilityslot WHERE SlotID = $id");
        $msg = 'Slot deleted.';
    } else {
        $msg = 'Permission denied.'; $msgType = 'danger';
    }
    $action = 'list';
}

// --- SAVE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = intval($_POST['hdnID'] ?? 0);
    $itemID  = intval($_POST['hdnItem'] ?? 0);
    $start   = trim($_POST['txtstart'] ?? '');
    $end     = trim($_POST['txtend'] ?? '');

    // Validation
    if ($itemID <= 0) {
        $msg = 'Please select an item.'; $msgType = 'danger';
        $action = $id === 0 ? 'add' : 'edit';
    } elseif (empty($start) || empty($end)) {
        $msg = 'Start and end date/time are required.'; $msgType = 'danger';
        $action = $id === 0 ? 'add' : 'edit';
    } elseif (strtotime($end) <= strtotime($start)) {
        $msg = 'End time must be after start time.'; $msgType = 'danger';
        $action = $id === 0 ? 'add' : 'edit';
    } elseif (strtotime($start) < (time() - 60)) {
        $msg = 'Start time cannot be in the past.'; $msgType = 'danger';
        $action = $id === 0 ? 'add' : 'edit';
    } else {
        // Permission check: only admin or item owner
        $ownerCheck = $connection->query(
            "SELECT OwnerUserID FROM tblitem WHERE ItemID = $itemID"
        )->fetch_assoc();
        if (!$isAdmin && (!$ownerCheck || $ownerCheck['OwnerUserID'] != $uid)) {
            $msg = 'You can only add slots to items you own.'; $msgType = 'danger';
            $action = $id === 0 ? 'add' : 'edit';
        } else {
            if ($id === 0) {
                $stmt = $connection->prepare(
                    "INSERT INTO tblavailabilityslot (ItemID, Start_DateTime, End_DateTime, isReserved) VALUES (?, ?, ?, 0)"
                );
                $stmt->bind_param('iss', $itemID, $start, $end);
                if ($stmt->execute()) {
                    $msg = 'Availability slot added successfully.';
                } else {
                    $msg = 'Error: ' . $stmt->error; $msgType = 'danger';
                }
                $stmt->close();
            } else {
                // Only admin or item owner can edit
                $slotOwner = $connection->query(
                    "SELECT i.OwnerUserID FROM tblavailabilityslot s
                     JOIN tblitem i ON s.ItemID = i.ItemID WHERE s.SlotID = $id"
                )->fetch_assoc();
                if ($isAdmin || ($slotOwner && $slotOwner['OwnerUserID'] == $uid)) {
                    $stmt = $connection->prepare(
                        "UPDATE tblavailabilityslot SET ItemID=?, Start_DateTime=?, End_DateTime=? WHERE SlotID=?"
                    );
                    $stmt->bind_param('issi', $itemID, $start, $end, $id);
                    if ($stmt->execute()) {
                        $msg = 'Slot updated successfully.';
                    } else {
                        $msg = 'Error: ' . $stmt->error; $msgType = 'danger';
                    }
                    $stmt->close();
                } else {
                    $msg = 'Permission denied.'; $msgType = 'danger';
                }
            }
            $action = 'list';
        }
    }
}

// --- EDIT load ---
$editRow = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $editRow = $connection->query(
        "SELECT s.*, i.Item_Name, i.OwnerUserID FROM tblavailabilityslot s
         JOIN tblitem i ON s.ItemID = i.ItemID
         WHERE s.SlotID = $id"
    )->fetch_assoc();
    if (!$editRow || (!$isAdmin && $editRow['OwnerUserID'] != $uid)) {
        header('Location: slots.php?msg=Permission+denied&msgType=danger');
        exit;
    }
}

// --- Pre-selected item from items.php "Manage Slots" button ---
$preItemID = intval($_GET['item_id'] ?? 0);

// Items the current user can manage slots for
$myItems = $isAdmin
    ? $connection->query("SELECT ItemID, Item_Name, Category FROM tblitem WHERE Availability_Status != 'Archived' ORDER BY Item_Name")
    : $connection->query("SELECT ItemID, Item_Name, Category FROM tblitem WHERE OwnerUserID = $uid AND Availability_Status != 'Archived' ORDER BY Item_Name");

// --- LIST query ---
$filterItem = intval($_GET['item_id_filter'] ?? 0);
$search     = trim($_GET['q'] ?? '');

$where = $isAdmin ? '1=1' : "i.OwnerUserID = $uid";
if ($filterItem > 0) $where .= " AND s.ItemID = $filterItem";
if ($search) {
    $sq = $connection->real_escape_string($search);
    $where .= " AND i.Item_Name LIKE '%$sq%'";
}

$limit = 15;
$p   = max(1, intval($_GET['p'] ?? 1));
$off = ($p - 1) * $limit;

$totalRes = $connection->query(
    "SELECT COUNT(*) c FROM tblavailabilityslot s
     JOIN tblitem i ON s.ItemID = i.ItemID
     WHERE $where"
);
$totalCount = $totalRes->fetch_assoc()['c'];
$totalPages = max(1, ceil($totalCount / $limit));

$slots = $connection->query(
    "SELECT s.SlotID, s.Start_DateTime, s.End_DateTime, s.isReserved, s.CreatedAt,
            i.ItemID, i.Item_Name, i.Category, i.Image,
            CONCAT(u.FirstName,' ',u.LastName) as OwnerName
     FROM tblavailabilityslot s
     JOIN tblitem i ON s.ItemID = i.ItemID
     JOIN tbluser u ON i.OwnerUserID = u.UserID
     WHERE $where
     ORDER BY s.Start_DateTime ASC
     LIMIT $limit OFFSET $off"
);

// For filter dropdown: items that have slots
$filterItems = $isAdmin
    ? $connection->query("SELECT DISTINCT i.ItemID, i.Item_Name FROM tblavailabilityslot s JOIN tblitem i ON s.ItemID = i.ItemID ORDER BY i.Item_Name")
    : $connection->query("SELECT DISTINCT i.ItemID, i.Item_Name FROM tblavailabilityslot s JOIN tblitem i ON s.ItemID = i.ItemID WHERE i.OwnerUserID = $uid ORDER BY i.Item_Name");

$pageTitle = 'Availability Slots';
require_once 'includes/header.php';
?>

<?php require_once "includes/navbar.php"; ?>
<div class="layout-top">
<div class="page-wrapper">

<div class="page-header">
    <div class="page-title">
        <h1>Availability Slots</h1>
        <p>Set when your items are available to be borrowed</p>
    </div>
    <div style="display:flex;gap:10px;">
        <a href="?action=add<?php echo $preItemID ? '&item_id='.$preItemID : ''; ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Slot
        </a>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?php echo $msgType; ?> auto-dismiss">
    <i class="fas fa-<?php echo $msgType==='success'?'circle-check':'circle-xmark'; ?>"></i> <?php echo htmlspecialchars($msg); ?>
</div>
<?php endif; ?>

<!-- ADD / EDIT FORM -->
<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card" style="max-width:640px;margin-bottom:24px;">
    <div class="card-header">
        <div>
            <h3><?php echo $action === 'edit' ? 'Edit Slot' : 'Add Availability Slot'; ?></h3>
            <p style="margin-top:2px;font-size:12px;color:var(--text-muted);">
                Define a time window when this item can be borrowed.
            </p>
        </div>
        <a href="slots.php" class="btn btn-outline btn-sm"><i class="fas fa-xmark"></i></a>
    </div>
    <div class="card-body">
        <form method="post" id="slotForm">
            <input type="hidden" name="hdnID" value="<?php echo $editRow['SlotID'] ?? 0; ?>">
            <div class="form-grid">
                <!-- Item selector -->
                <div class="form-group span-2">
                    <label>Item <span class="required">*</span></label>
                    <?php
                    $selectedItem = $editRow['ItemID'] ?? $preItemID;
                    if ($myItems && $myItems->num_rows > 0): ?>
                    <select name="hdnItem" id="hdnItem" required>
                        <option value="">— Select your item —</option>
                        <?php $myItems->data_seek(0); while ($itm = $myItems->fetch_assoc()): ?>
                        <option value="<?php echo $itm['ItemID']; ?>" <?php echo intval($selectedItem) === intval($itm['ItemID']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($itm['Item_Name']); ?>
                            <?php echo $itm['Category'] ? ' (' . $itm['Category'] . ')' : ''; ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                    <?php else: ?>
                    <div style="padding:12px;background:var(--cream);border:1px solid var(--cream-border);border-radius:var(--radius-sm);font-size:13px;color:var(--text-muted);">
                        <i class="fas fa-triangle-exclamation" style="color:#d97706;margin-right:6px;"></i>
                        You have no items yet. <a href="items.php?action=add" style="color:var(--maroon);font-weight:600;">Add an item first →</a>
                    </div>
                    <input type="hidden" name="hdnItem" value="0">
                    <?php endif; ?>
                </div>

                <!-- Start datetime -->
                <div class="form-group">
                    <label>Available From <span class="required">*</span></label>
                    <input type="datetime-local" name="txtstart" id="txtstart"
                           value="<?php echo $editRow ? str_replace(' ', 'T', $editRow['Start_DateTime']) : ''; ?>"
                           min="<?php echo date('Y-m-d\TH:i'); ?>" required>
                </div>

                <!-- End datetime -->
                <div class="form-group">
                    <label>Available Until <span class="required">*</span></label>
                    <input type="datetime-local" name="txtend" id="txtend"
                           value="<?php echo $editRow ? str_replace(' ', 'T', $editRow['End_DateTime']) : ''; ?>"
                           min="<?php echo date('Y-m-d\TH:i'); ?>" required>
                </div>
            </div>

            <!-- Duration preview -->
            <div id="durationPreview" style="display:none;margin-top:4px;margin-bottom:8px;padding:10px 14px;background:var(--cream);border:1px solid var(--cream-border);border-radius:var(--radius-sm);font-size:13px;">
                <i class="fas fa-clock" style="color:var(--maroon);margin-right:6px;"></i>
                <span id="durationText"></span>
            </div>

            <div style="margin-top:20px;display:flex;gap:10px;">
                <button type="button" onclick="validateSlotAndShowModal()" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo $action === 'edit' ? 'Update Slot' : 'Add Slot'; ?>
                </button>
                <button type="button" onclick="showConfirmModal('slots.php', 'Discard Changes', 'Are you sure you want to leave? Any unsaved changes will be lost.')" class="btn btn-outline">Cancel</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- LIST -->
<?php if ($action === 'list'): ?>
<div class="card">
    <div class="card-header">
        <div>
            <h3>All Slots</h3>
            <p><?php echo $totalCount; ?> slot(s)</p>
        </div>
        <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" name="q" placeholder="Search item name…" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <?php if ($filterItems && $filterItems->num_rows > 0): ?>
            <select name="item_id_filter" style="padding:7px 10px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-size:13px;">
                <option value="0">All Items</option>
                <?php $filterItems->data_seek(0); while ($fi = $filterItems->fetch_assoc()): ?>
                <option value="<?php echo $fi['ItemID']; ?>" <?php echo $filterItem === intval($fi['ItemID']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($fi['Item_Name']); ?>
                </option>
                <?php endwhile; ?>
            </select>
            <?php endif; ?>
            <button class="btn btn-outline btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <a href="slots.php" class="btn btn-outline btn-sm"><i class="fas fa-rotate-left"></i></a>
        </form>
    </div>

    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item</th>
                    <th>Available From</th>
                    <th>Available Until</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <?php if ($isAdmin): ?><th>Owner</th><?php endif; ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($slots && $slots->num_rows > 0):
                while ($row = $slots->fetch_assoc()):
                    $startTs  = strtotime($row['Start_DateTime']);
                    $endTs    = strtotime($row['End_DateTime']);
                    $diffSecs = $endTs - $startTs;
                    $diffH    = floor($diffSecs / 3600);
                    $diffM    = floor(($diffSecs % 3600) / 60);
                    $duration = $diffH > 0 ? "{$diffH}h " : '';
                    $duration .= $diffM > 0 ? "{$diffM}m" : '';
                    $duration = trim($duration) ?: '< 1m';

                    $isPast     = $endTs < time();
                    $isReserved = $row['isReserved'];
                    if ($isPast)         { $badgeClass = 'badge-danger';  $badgeLabel = 'Expired'; }
                    elseif ($isReserved) { $badgeClass = 'badge-warning'; $badgeLabel = 'Reserved'; }
                    else                 { $badgeClass = 'badge-success'; $badgeLabel = 'Open'; }
            ?>
            <tr style="<?php echo $isPast ? 'opacity:0.55;' : ''; ?>">
                <td class="mono">#<?php echo $row['SlotID']; ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <?php if (!empty($row['Image'])): ?>
                        <img src="uploads/items/<?php echo htmlspecialchars($row['Image']); ?>" alt="" style="width:34px;height:34px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--cream-border);flex-shrink:0;">
                        <?php else: ?>
                        <div style="width:34px;height:34px;background:var(--cream-dark);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas fa-box" style="color:var(--gray-400);font-size:12px;"></i>
                        </div>
                        <?php endif; ?>
                        <div>
                            <div style="font-weight:600;font-size:13px;"><?php echo htmlspecialchars($row['Item_Name']); ?></div>
                            <?php if ($row['Category']): ?>
                            <div style="font-size:11px;color:var(--gray-400);"><?php echo htmlspecialchars($row['Category']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td style="font-size:12px;">
                    <div><?php echo date('M j, Y', $startTs); ?></div>
                    <div style="color:var(--gray-400);"><?php echo date('g:i A', $startTs); ?></div>
                </td>
                <td style="font-size:12px;">
                    <div><?php echo date('M j, Y', $endTs); ?></div>
                    <div style="color:var(--gray-400);"><?php echo date('g:i A', $endTs); ?></div>
                </td>
                <td style="font-size:12px;color:var(--text-muted);"><?php echo $duration; ?></td>
                <td><span class="badge <?php echo $badgeClass; ?>"><?php echo $badgeLabel; ?></span></td>
                <?php if ($isAdmin): ?>
                <td style="font-size:12px;"><?php echo htmlspecialchars($row['OwnerName']); ?></td>
                <?php endif; ?>
                <td>
                    <?php if (!$isPast && !$isReserved): ?>
                    <a href="?action=edit&id=<?php echo $row['SlotID']; ?>" class="btn btn-outline btn-sm"><i class="fas fa-pen"></i></a>
                    <?php endif; ?>
                    <button type="button"
                        onclick="showConfirmModal(
                            '?action=delete&id=<?php echo $row['SlotID']; ?>',
                            'Delete Slot',
                            'Are you sure you want to delete this slot for <strong><?php echo htmlspecialchars(addslashes($row['Item_Name'])); ?></strong>?<?php echo $isReserved ? ' Warning: this slot has an active booking.' : ''; ?>'
                        )"
                        class="btn btn-danger btn-sm" style="margin-left:4px;">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
            <?php endwhile; else: ?>
            <tr>
                <td colspan="<?php echo $isAdmin ? 8 : 7; ?>">
                    <div class="empty-state">
                        <i class="fas fa-calendar-plus"></i>
                        <h3>No slots yet</h3>
                        <p>Click <strong>Add Slot</strong> to define when your items are available.</p>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination" style="margin-top:0;border-top:1px solid var(--cream-border);">
        <?php if ($p > 1): ?>
            <a href="?p=<?php echo $p-1; ?>&q=<?php echo urlencode($search); ?>&item_id_filter=<?php echo $filterItem; ?>" class="page-link" style="width:auto;padding:0 15px;"><i class="fas fa-chevron-left"></i> Back</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?p=<?php echo $i; ?>&q=<?php echo urlencode($search); ?>&item_id_filter=<?php echo $filterItem; ?>" class="page-link <?php echo $i == $p ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <?php if ($p < $totalPages): ?>
            <a href="?p=<?php echo $p+1; ?>&q=<?php echo urlencode($search); ?>&item_id_filter=<?php echo $filterItem; ?>" class="page-link" style="width:auto;padding:0 15px;">Next <i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
        <div class="page-info">Page <?php echo $p; ?> of <?php echo $totalPages; ?></div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

</div>
</div>

<!-- Confirm Modal -->
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
// ---- Duration preview ----
const startInput = document.getElementById('txtstart');
const endInput   = document.getElementById('txtend');
const preview    = document.getElementById('durationPreview');
const previewTxt = document.getElementById('durationText');

function updateDuration() {
    if (!startInput || !endInput) return;
    const s = new Date(startInput.value);
    const e = new Date(endInput.value);
    if (isNaN(s) || isNaN(e) || e <= s) { preview.style.display = 'none'; return; }
    const diffMs   = e - s;
    const diffH    = Math.floor(diffMs / 3600000);
    const diffM    = Math.floor((diffMs % 3600000) / 60000);
    const diffD    = Math.floor(diffH / 24);
    const remH     = diffH % 24;
    let label = '';
    if (diffD > 0)  label += diffD + ' day' + (diffD > 1 ? 's' : '') + ' ';
    if (remH > 0)   label += remH + ' hr' + (remH > 1 ? 's' : '') + ' ';
    if (diffM > 0)  label += diffM + ' min';
    previewTxt.textContent = 'Duration: ' + label.trim();
    preview.style.display = 'block';
}

if (startInput) startInput.addEventListener('change', updateDuration);
if (endInput)   endInput.addEventListener('change', updateDuration);
updateDuration(); // run on load for edit form

// ---- Validation & modal ----
function validateSlotAndShowModal() {
    const form    = document.getElementById('slotForm');
    const itemSel = document.getElementById('hdnItem');
    const start   = document.getElementById('txtstart');
    const end     = document.getElementById('txtend');

    if (itemSel && !itemSel.value) {
        showToast('Please select an item.', 'warning'); return;
    }
    if (!start.value || !end.value) {
        showToast('Please fill in both start and end date/time.', 'warning'); return;
    }
    if (new Date(end.value) <= new Date(start.value)) {
        showToast('End time must be after start time.', 'warning'); return;
    }
    if (new Date(start.value) < new Date(Date.now() - 60000)) {
        showToast('Start time cannot be in the past.', 'warning'); return;
    }
    if (!form.checkValidity()) { form.reportValidity(); return; }

    const isEdit = (document.querySelector('input[name="hdnID"]').value !== '0');
    showConfirmModal('SUBMIT_FORM',
        isEdit ? 'Update Slot' : 'Add Slot',
        isEdit ? 'Are you sure you want to update this availability slot?' : 'Are you sure you want to add this availability slot?',
        'btn-primary');
}

function showToast(message, type = 'danger') {
    let toast = document.getElementById('toastNotif');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toastNotif';
        toast.style.cssText = 'position:fixed;bottom:28px;right:28px;z-index:9999;min-width:280px;max-width:380px;padding:14px 18px;border-radius:10px;font-size:14px;font-weight:500;box-shadow:0 4px 20px rgba(0,0,0,0.15);display:flex;align-items:center;gap:10px;transition:opacity 0.3s;';
        document.body.appendChild(toast);
    }
    const colors = {
        danger:  { bg:'#fef2f2', border:'#fecaca', text:'#991b1b', icon:'fa-circle-xmark' },
        warning: { bg:'#fffbeb', border:'#fde68a', text:'#92400e', icon:'fa-triangle-exclamation' },
        success: { bg:'#f0fdf4', border:'#bbf7d0', text:'#166534', icon:'fa-circle-check' },
        info:    { bg:'#eff6ff', border:'#bfdbfe', text:'#1e40af', icon:'fa-circle-info' },
    };
    const c = colors[type] || colors.info;
    toast.style.cssText += `background:${c.bg};border:1.5px solid ${c.border};color:${c.text};opacity:1;`;
    toast.innerHTML = `<i class="fas ${c.icon}" style="font-size:16px;flex-shrink:0;"></i><span>${message}</span>`;
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => { toast.style.opacity = '0'; }, 3500);
}

function showConfirmModal(url, title, message, btnClass = 'btn-danger') {
    document.getElementById('modalTitle').innerText = title;
    document.getElementById('modalMessage').innerHTML = message;
    const confirmBtn = document.getElementById('modalConfirmBtn');
    if (url === 'SUBMIT_FORM') {
        confirmBtn.href = 'javascript:void(0)';
        confirmBtn.onclick = () => document.getElementById('slotForm').submit();
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
