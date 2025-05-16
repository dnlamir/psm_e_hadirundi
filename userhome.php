<?php
session_start();
include 'includes/room.php'; // Updated path to room.php

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Fetch rooms from the database
$query = "SELECT * FROM room";
$result = $conn->query($query);

// Check for database query errors
if (!$result) {
    die("Database query failed: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/userhome.css">
</head>
<body>

<div class="content-wrapper">
    <!-- User Home Content -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1>Laman Utama Pengguna</h1>
            <p>Selamat Kembali, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
        </div>
        <div class="date-display">
            <i class="far fa-calendar-alt"></i> <?php 
                // Set locale to Malay
                setlocale(LC_TIME, 'ms_MY.utf8', 'ms_MY', 'ms');
                
                // Define Malay day and month names
                $hari = array('Ahad', 'Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat', 'Sabtu');
                $bulan = array('', 'Januari', 'Februari', 'Mac', 'April', 'Mei', 'Jun', 'Julai', 'Ogos', 'September', 'Oktober', 'November', 'Disember');
                
                // Get current date components
                $hari_semasa = $hari[date('w')];
                $tarikh = date('j');
                $bulan_semasa = $bulan[date('n')];
                $tahun = date('Y');
                $masa = date('h:i A');
                
                // Output formatted date and time in Malay
                echo "$hari_semasa, $tarikh $bulan_semasa $tahun, $masa"; 
            ?>
        </div>
    </div>
    
    <!-- Display Rooms -->
    <h2>Lokasi Buang Undi</h2>
    <div class="room-container">
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="room">
                <h2><?php echo htmlspecialchars($row['name']); ?></h2>
                <img src="<?php echo htmlspecialchars($row['image']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
                <a href="manage_voters.php?room_id=<?php echo $row['id']; ?>">
                    <button><i class="fas fa-users"></i> Lihat Pengundi & Tandakan Kehadiran</button>
                </a>
            </div>
        <?php endwhile; ?>
    </div>

    <!-- Include Sidebar -->
    <?php include 'usersidebar.php'; ?>
</div>
</body>
</html>
