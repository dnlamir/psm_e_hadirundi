
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-HadirUndi</title>
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- External CSS -->
    <link rel="stylesheet" href="assets/css/usersidebar.css">
</head>
<body>    
<aside class="sidebar">

<div class="sidebar-header">
    <div class="logo">
        <div class="logo-text">
            <a href="userhome.php" style="text-decoration: none; color: inherit; display: flex; align-items: center;">
                <i class="fas fa-vote-yea" style="margin-right: 10px;"></i>
                E-HadirUndi
            </a>
        </div>
    </div>
</div>


<div class="nav-section">
    <div class="section-title"> Menu Utama</div>
    <div class="nav-menu">
        <a href="userdashboard.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-home"></i></div>
            <div>Dashboard</div>
        </a>
        <div class="nav-item has-submenu">
            <div class="nav-icon"><i class="fas fa-user-check"></i></div>
            <div>Kehadiran</div>
            <div class="submenu">
                <?php
                $query = "SELECT * FROM room";
                $result = $conn->query($query);
                ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                <a href="manage_voters.php?room_id=<?php echo $row['id']; ?>" class="submenu-item"><?php echo htmlspecialchars($row['name']); ?></a>
                <?php endwhile; ?>
            </div>
        </div>
        <a href="userlaporan.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-file-alt"></i></div>
            <div>Laporan</div>
        </a>
        <!-- Add Pengundi menu here -->
        <a href="voters.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-users"></i></div>
            <div>Pengundi</div>
        </a>
    </div>
</div>

<div class="nav-section">
    <div class="section-title">Akaun</div>
    <div class="nav-menu">
        <a href="bantuan.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-question-circle"></i></div>
            <div>Bantuan</div>
        </a>
        <a href="logout.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-sign-out-alt"></i></div>
            <div>Log Keluar</div>
        </a>
    </div>
</div>

</aside>
</body>
</html>

