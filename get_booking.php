<?php
/**
 * get_booking.php
 * AJAX endpoint: given a SlotID, find or create a Pending booking for
 * the logged-in student, then return the BookingID as JSON.
 *
 * Also returns the slot's start/end datetimes so the form can pre-fill them.
 *
 * Called by borrow_requests.php JS via fetch().
 */
require_once 'connect.php';
requireLogin();

header('Content-Type: application/json');

$slotID = intval($_GET['slot_id'] ?? 0);
$uid    = $_SESSION['user_id'];

if ($slotID <= 0) {
    echo json_encode(['error' => 'Invalid slot.']);
    exit;
}

// Load slot details
$slotStmt = $connection->prepare(
    "SELECT SlotID, Start_DateTime, End_DateTime, isReserved FROM tblavailabilityslot WHERE SlotID = ?"
);
$slotStmt->bind_param('i', $slotID);
$slotStmt->execute();
$slot = $slotStmt->get_result()->fetch_assoc();
$slotStmt->close();

if (!$slot) {
    echo json_encode(['error' => 'Slot not found.']);
    exit;
}

// Check if student already has a Pending booking for this slot
$checkStmt = $connection->prepare(
    "SELECT BookingID FROM tblbooking WHERE UserID = ? AND SlotID = ? AND Status = 'Pending'"
);
$checkStmt->bind_param('ii', $uid, $slotID);
$checkStmt->execute();
$existing = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if ($existing) {
    $bookingID = $existing['BookingID'];
} else {
    // Create a new Pending booking
    $insStmt = $connection->prepare(
        "INSERT INTO tblbooking (UserID, SlotID, Status) VALUES (?, ?, 'Pending')"
    );
    $insStmt->bind_param('ii', $uid, $slotID);
    $insStmt->execute();
    $bookingID = $connection->insert_id;
    $insStmt->close();
}

echo json_encode([
    'booking_id' => $bookingID,
    'start'      => $slot['Start_DateTime'],
    'end'        => $slot['End_DateTime'],
]);
