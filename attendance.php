<?php
session_start();
include 'includes/room.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Get POST data
$voter_id = isset($_POST['voter_id']) ? intval($_POST['voter_id']) : 0;
$room_id = isset($_POST['room_id']) ? intval($_POST['room_id']) : 0;

if (!$voter_id || !$room_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit();
}

// Update attendance status and timestamp in database
$current_timestamp = date('Y-m-d H:i:s');
$query = "UPDATE voters SET attended = 1, updated_at = ? WHERE id = ? AND room_id = ?";
$stmt = $conn->prepare($query);

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit();
}

// Fix: Correct parameter types (s for string, i for integer) and correct order
$stmt->bind_param('sii', $current_timestamp, $voter_id, $room_id);
$result = $stmt->execute();

if ($result) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update attendance']);
}

$stmt->close();
$conn->close();
