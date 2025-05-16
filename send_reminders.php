<?php
session_start();
include 'includes/room.php';

if (!isset($_SESSION['username']) || !isset($_POST['room_id'])) {
    die(json_encode(['success' => false, 'error' => 'Unauthorized access']));
}

$room_id = intval($_POST['room_id']);

// WhatsApp API Configuration
$whatsapp_token = 'EAAPBupHs5p0BO6mWfgCSf6kgr89UOBTHe8XMJyQxdK6AUl6p3S5TF2WDwUZCoQrasNsxZByDhc00VK9x8infgK6xZAIYO9hd1V50wzLBo2qSf1GcnpNhOVdEyB8qpZAS2Ewsp1fMqaWAVzoiQZAAp9MmmHcIr9N06NJTqlmNWM8e1mTqhEWWgmZBIqBzEnwHr03AZDZD';
$whatsapp_phone_number_id = '649447978245580';

// Get all unattended voters' phone numbers
$query = "SELECT nama_pengundi, no_telefon FROM voters WHERE room_id = ? AND attended = 0";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $room_id);
$stmt->execute();
$result = $stmt->get_result();

$voters = [];
while ($row = $result->fetch_assoc()) {
    $voters[] = $row;
}

if (empty($voters)) {
    die(json_encode(['success' => false, 'error' => 'No unattended voters found']));
}

$success_count = 0;

foreach ($voters as $voter) {
    $formatted_phone = preg_replace('/[^0-9]/', '', $voter['no_telefon']);
    
    // Improved phone number formatting for Malaysian numbers
    if (substr($formatted_phone, 0, 2) === '01') {
        $formatted_phone = '60' . substr($formatted_phone, 1);
    } else if (substr($formatted_phone, 0, 1) === '0') {
        $formatted_phone = '60' . substr($formatted_phone, 1);
    } else if (substr($formatted_phone, 0, 2) !== '60') {
        $formatted_phone = '60' . $formatted_phone;
    }
    
    error_log("Sending to: " . $formatted_phone); // Debug log
    
    $url = "https://graph.facebook.com/v22.0/{$whatsapp_phone_number_id}/messages"; // Updated API version
    
    $data = [
        "messaging_product" => "whatsapp",
        "to" => $formatted_phone,
        "type" => "template",
        "template" => [
            "name" => "mesej_reminder",  // Changed to mesej_reminder template
            "language" => [
                "code" => "ms"           // Changed to Malay language code
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $whatsapp_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Enhanced error logging
    if ($response === false) {
        error_log("cURL Error: " . curl_error($ch));
    } else {
        $response_data = json_decode($response, true);
        error_log("API Response: " . $response); // Debug log
        
        if ($http_code === 200 && isset($response_data['messages'][0]['id'])) {
            $success_count++;
            error_log("Success: Template sent to {$voter['nama_pengundi']} ({$formatted_phone})");
        } else {
            error_log("Error: Failed to send to {$voter['nama_pengundi']} - Status: {$http_code}, Response: " . $response);
        }
    }
    
    curl_close($ch);
    usleep(200000);
}

// Log the reminder if at least one reminder was sent successfully
if ($success_count > 0) {
    $stmt = $conn->prepare("INSERT INTO reminder_logs (room_id) VALUES (?)");
    $stmt->bind_param("i", $_POST['room_id']);
    $stmt->execute();
}

echo json_encode([
    'success' => true,
    'count' => $success_count
]);