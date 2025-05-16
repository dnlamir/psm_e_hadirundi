<?php
// Start the session
session_start();

// Database connection
$host = "localhost"; 
$username = "root"; 
$password = ""; 
$database = "user";

$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$response = array();

// Check if form data is set
if (isset($_POST['username']) && isset($_POST['password'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $conn->real_escape_string($_POST['password']);

    // Use prepared statement to prevent SQL injection
    $sql = "SELECT * FROM login WHERE username = ? AND password = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Fetch user data
        $row = $result->fetch_assoc();
        $_SESSION['username'] = $row['username'];

        // Redirect based on user role
        if ($row['username'] === 'admin') {
            $response['redirect'] = 'adminhome.php';
        } elseif ($row['username'] === 'user') {
            $response['redirect'] = 'userhome.php';
        } else {
            $response['error'] = "Unauthorized access.";
        }
    } else {
        $response['error'] = "Invalid username or password.";
    }

    // Close statement
    $stmt->close();
}

$conn->close();

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit();
?>
