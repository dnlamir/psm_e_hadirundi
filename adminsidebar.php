<footer>
<?php
include 'includes/room.php'; // Add this at the top of the file
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-HadirUndi</title>
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Link to the external CSS file -->
    <link rel="stylesheet" href="assets/css/adminsidebar.css">
</head>
<body>    
<aside class="sidebar">
<!-- Removed inline styles, now in external CSS file -->

<div class="sidebar-header">
    <div class="logo">
        <div class="logo-text">
            <a href="adminhome.php" style="text-decoration: none; color: inherit; display: flex; align-items: center;">
                <i class="fas fa-vote-yea" style="margin-right: 10px;"></i>
                E-HadirUndi
            </a>
        </div>
    </div>
</div>

<!-- Rest of the sidebar content remains unchanged -->
<div class="nav-section">
    <div class="section-title">Main Menu</div>
    <div class="nav-menu">
        <a href="admindashboard.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-home"></i></div>
            <div>Dashboard</div>
        </a>
        <div class="nav-item has-submenu">
            <div class="nav-icon"><i class="fas fa-user-check"></i></div>
            <div>Kehadiran</div>
            <div class="submenu">
                <?php
                // Fetch rooms from database
                $query = "SELECT * FROM room";
                $result = $conn->query($query);
                
                // Display each room as a submenu item
                while ($row = $result->fetch_assoc()): ?>
                    <a href="upload_voters.php?room_id=<?php echo $row['id']; ?>" class="submenu-item">
                        <?php echo htmlspecialchars($row['name']); ?>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>
        <a href="adminlaporan.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-file-alt"></i></div>
            <div>Laporan</div>
        </a>
    </div>
</div>

<div class="nav-section">
    <div class="section-title">Akaun</div>
    <div class="nav-menu">
        <a href="logout.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-sign-out-alt"></i></div>
            <div>Logout</div>
        </a>
    </div>
</div>

</aside>
</body>

</html>
</footer>
