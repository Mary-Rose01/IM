<?php
/**
 * get_slots.php
 * AJAX endpoint: returns all open (not reserved, future) availability slots
 * for a given ItemID as a JSON array.
 * Called by borrow_requests.php JS via fetch().
 */
require_once 'connect.php';
requireLogin();

header('Content-Type: application/json');

$itemID = intval($_GET['item_id'] ?? 0);
if ($itemID <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $connection->prepare(
    "SELECT SlotID, Start_DateTime, End_DateTime
     FROM tblavailabilityslot
     WHERE ItemID = ? AND isReserved = 0 AND End_DateTime > NOW()
     ORDER BY Start_DateTime ASC"
);
$stmt->bind_param('i', $itemID);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$slots = [];
while ($row = $result->fetch_assoc()) {
    $start = new DateTime($row['Start_DateTime']);
    $end   = new DateTime($row['End_DateTime']);
    $slots[] = [
        'SlotID'         => $row['SlotID'],
        'Start_DateTime' => $row['Start_DateTime'],
        'End_DateTime'   => $row['End_DateTime'],
        'label'          => $start->format('M j, Y g:i A') . ' → ' . $end->format('M j, Y g:i A'),
    ];
}

echo json_encode($slots);
