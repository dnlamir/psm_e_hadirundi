<?php
// Connect to database
include 'includes/room.php'; // Adjust path if needed

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination settings
$records_per_page = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Count total voters for stats
$count_sql = "SELECT COUNT(*) as total FROM voters";
$total_voters = $conn->query($count_sql)->fetch_assoc()['total'];

// Get room count
$room_count_sql = "SELECT COUNT(*) as total FROM room";
$room_count = $conn->query($room_count_sql)->fetch_assoc()['total'];

// Count total records for pagination
$total_records_sql = "SELECT COUNT(*) as total FROM voters";
if ($search !== '') {
    $total_records_sql .= " WHERE nama_pengundi LIKE '%$search%' OR no_ic LIKE '%$search%'";
}
$total_records = $conn->query($total_records_sql)->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// SQL query to get all voters with their room information
$sql = "SELECT v.id, v.nama_pengundi, v.no_ic, r.name AS room_name 
        FROM voters v
        LEFT JOIN room r ON v.room_id = r.id";

if ($search !== '') {
    $sql .= " WHERE v.nama_pengundi LIKE '%$search%' OR v.no_ic LIKE '%$search%'";
}

$sql .= " ORDER BY v.nama_pengundi ASC LIMIT $offset, $records_per_page";

// Use direct query
$result = $conn->query($sql);

// Check for query errors
if (!$result) {
    echo "Error: " . $conn->error;
    echo "<pre>SQL Query: $sql</pre>";
    exit;
}

// Debug: Check if we have results
$debug_count = $result ? $result->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Senarai Pengundi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/voters.css">
</head>
<body>
    <?php include 'usersidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-users"></i> Senarai Pengundi</h1>
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
        
        <!-- Add purpose note -->
        <div class="alert-info">
            <i class="fas fa-info-circle"></i> Senarai ini untuk kegunaan petugas di pintu pagar bagi mengenal pasti saluran pengundi.
        </div>
        
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-title">Jumlah Pengundi</div>
                <div class="stat-value"><?php echo number_format($total_voters); ?></div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">Bilangan Saluran</div>
                <div class="stat-value"><?php echo $room_count; ?></div>
                <div class="stat-icon"><i class="fas fa-door-open"></i></div>
            </div>
            
            <?php 
            // Get attendance stats
            $attendance_sql = "SELECT 
                                SUM(CASE WHEN attended = 1 THEN 1 ELSE 0 END) as attended,
                                COUNT(*) as total
                              FROM voters";
            $attendance = $conn->query($attendance_sql)->fetch_assoc();
            $attendance_percent = $attendance['total'] > 0 ? round(($attendance['attended'] / $attendance['total']) * 100) : 0;
            ?>
            
            <div class="stat-card">
                <div class="stat-title">Kehadiran</div>
                <div class="stat-value"><?php echo $attendance_percent; ?>%</div>
                <div class="stat-icon"><i class="fas fa-chart-pie"></i></div>
            </div>
        </div>
        
        <div class="search-container">
            <form class="search-box" method="get">
                <input type="text" name="search" placeholder="Cari nama atau IC pengundi..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit"><i class="fas fa-search"></i> Cari</button>
            </form>
        </div>
        
        <div class="data-container">
            <?php 
            // Use the alternative SQL query that works correctly
            $alt_sql = "SELECT v.id, v.nama_pengundi, v.no_ic, 
                        (SELECT name FROM room WHERE id = v.room_id) AS room_name 
                        FROM voters v";
            
            if ($search !== '') {
                $alt_sql .= " WHERE v.nama_pengundi LIKE '%$search%' OR v.no_ic LIKE '%$search%'";
            }
            
            $alt_sql .= " ORDER BY v.nama_pengundi ASC LIMIT $offset, $records_per_page";
            
            // Execute the query
            $result = $conn->query($alt_sql);
            
            // Check for query errors
            if (!$result) {
                echo "Error with query: " . $conn->error;
                exit;
            }
            
            // Update debug count
            $debug_count = $result ? $result->num_rows : 0;
            ?>
            
            <?php if ($result && $result->num_rows > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nama Pengundi</th>
                            <th>No. IC</th>
                            <th>Saluran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['nama_pengundi']); ?></td>
                                <td><?php echo htmlspecialchars($row['no_ic']); ?></td>
                                <td>
                                    <?php if (isset($row['room_name']) && $row['room_name']): ?>
                                        <span class="room-badge"><?php echo htmlspecialchars($row['room_name']); ?></span>
                                    <?php else: ?>
                                        <span style="color:#e17055;">Tiada saluran</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1); ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>">&laquo;</a>
                    <?php endif; ?>
                    
                    <?php
                    // Show limited page numbers with current page in the middle
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
                           <?php echo ($i == $page) ? 'class="active"' : ''; ?>><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo ($page + 1); ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>">&raquo;</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <p>Tiada pengundi dijumpai<?php echo !empty($search) ? ' untuk carian "'.htmlspecialchars($search).'"' : ''; ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>