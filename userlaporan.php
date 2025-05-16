<?php
session_start();
include 'includes/room.php';
require 'vendor/autoload.php'; // For TCPDF

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Handle PDF generation
if (isset($_POST['generate_pdf']) && isset($_POST['room_id'])) {
    $room_id = $_POST['room_id'];
    
    // Get room details
    $room_query = "SELECT name FROM room WHERE id = ?";
    $stmt = $conn->prepare($room_query);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $room_result = $stmt->get_result()->fetch_assoc();
    
    // Get attendance statistics
    $stats_query = "SELECT 
        COUNT(*) as total_voters,
        SUM(CASE WHEN attended = 1 THEN 1 ELSE 0 END) as attended_count,
        SUM(CASE WHEN attended = 0 THEN 1 ELSE 0 END) as not_attended_count
        FROM voters WHERE room_id = ?";
    $stmt = $conn->prepare($stats_query);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    // Get voters details
    $voters_query = "SELECT id_pengundi, nama_pengundi, no_ic, attended, updated_at 
                    FROM voters WHERE room_id = ? ORDER BY updated_at DESC";
    $stmt = $conn->prepare($voters_query);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $voters = $stmt->get_result();
    
     // Generate PDF with improved formatting
     $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
     $pdf->SetCreator('E-HadirUndi');
     $pdf->SetAuthor('Suruhanjaya Pilihan Raya Malaysia');
     $pdf->SetTitle('Laporan Kehadiran - ' . $room_result['name']);
     $pdf->SetSubject('Laporan Kehadiran Pengundi');
     $pdf->SetKeywords('SPR, Kehadiran, Pengundi, Laporan');
     
     // Remove default header/footer
     $pdf->setPrintHeader(false);
     $pdf->setPrintFooter(false);
     
     // Add a page
     $pdf->AddPage();
     
     // Set page margins for better layout
     $pdf->SetMargins(15, 15, 15);
     
     // Get page dimensions
     $pageWidth = $pdf->getPageWidth();
     $pageHeight = $pdf->getPageHeight();
     $margins = $pdf->getMargins();
     $contentWidth = $pageWidth - $margins['left'] - $margins['right'];
     
     // Create professional header with background
     $headerHeight = 90;
     $pdf->SetFillColor(245, 245, 245);
     $pdf->Rect($margins['left'] - 5, 15, $contentWidth + 10, $headerHeight, 'F');
     
     // Add logo with professional positioning (left side)
     $logoWidth = 60;
     $logoHeight = 60;
     $logoX = $margins['left'] + 5;
     $logoY = 30;
     $pdf->Image('assets/img/sprlogo.png', $logoX, $logoY, $logoWidth, $logoHeight, '', '', '', false, 300, '', false, false, 0);
     
     // Add header text next to logo
     $headerTextX = $logoX + $logoWidth + 15;
     $headerTextWidth = $contentWidth - $logoWidth - 15;
     
     // Header title with blue color
     $pdf->SetFont('helvetica', 'B', 16);
     $pdf->SetTextColor(0, 74, 143); // SPR blue color
     $pdf->SetXY($headerTextX, $logoY + 5);
     $pdf->Cell($headerTextWidth, 12, 'SURUHANJAYA PILIHAN RAYA MALAYSIA', 0, 1, 'L');
     
     $pdf->SetFont('helvetica', 'B', 14);
     $pdf->SetXY($headerTextX, $logoY + 20);
     $pdf->Cell($headerTextWidth, 10, 'LAPORAN KEHADIRAN PENGUNDI', 0, 1, 'L');
     
     $pdf->SetFont('helvetica', 'B', 12);
     $pdf->SetXY($headerTextX, $logoY + 35);
     $pdf->Cell($headerTextWidth, 10, $room_result['name'], 0, 1, 'L');
     
     $pdf->SetFont('helvetica', '', 10);
     $pdf->SetXY($headerTextX, $logoY + 48);
     $pdf->Cell($headerTextWidth, 10, 'Tarikh: ' . date('d/m/Y'), 0, 1, 'L');
     
     // Add decorative line below header
     $pdf->SetDrawColor(0, 74, 143);
     $pdf->SetLineWidth(0.5);
     $pdf->Line($margins['left'], $logoY + $headerHeight - 10, $pageWidth - $margins['right'], $logoY + $headerHeight - 10);
     
     // Reset position for content and text color
     $pdf->SetY($logoY + $headerHeight);
     $pdf->SetTextColor(0, 0, 0);
     
     // Statistics section with improved layout
     $pdf->SetFont('helvetica', 'B', 14);
     $pdf->Cell($contentWidth, 12, 'STATISTIK KEHADIRAN:', 0, 1, 'L');
     
     // Add underline for section title
     $pdf->SetLineWidth(0.2);
     $pdf->Line($margins['left'], $pdf->GetY() - 2, $margins['left'] + 70, $pdf->GetY() - 2);
     $pdf->Ln(10);
     
     // Calculate box dimensions for 2x2 grid
     $stats_width = ($contentWidth - 20) / 2; // 20px spacing between boxes
     $stats_height = 50; // Taller boxes for better appearance
     $x_start = $margins['left'];
     $y_start = $pdf->GetY();
     
     // Enhanced function to draw stat box with consistent styling
     function drawStatBox($pdf, $x, $y, $width, $height, $label, $value, $bgColor, $icon = '') {
         // Draw background with rounded corners
         $pdf->SetFillColor($bgColor[0], $bgColor[1], $bgColor[2]);
         $radius = 4;
         $pdf->RoundedRect($x, $y, $width, $height, $radius, '1111', 'F');
         
         // Add subtle border
         $pdf->SetDrawColor($bgColor[0] - 20, $bgColor[1] - 20, $bgColor[2] - 20);
         $pdf->SetLineWidth(0.2);
         $pdf->RoundedRect($x, $y, $width, $height, $radius, '1111', 'D');
         
         // Add icon
         $pdf->SetXY($x + 10, $y + 8);
         $pdf->SetTextColor(255, 255, 255);
         $pdf->SetFont('helvetica', '', 14);
         $pdf->Write(0, $icon);
         
         // Label
         $pdf->SetXY($x + 10, $y + 8);
         $pdf->SetFont('helvetica', 'B', 11);
         $pdf->Cell($width - 20, 10, $label, 0, 1, 'C');
         
         // Value with larger font
         $pdf->SetXY($x, $y + $height - 30);
         $pdf->SetFont('helvetica', 'B', 22);
         $pdf->Cell($width, 20, $value, 0, 1, 'C');
     }
     
     // Total Voters Box (Blue)
     drawStatBox($pdf, $x_start, $y_start, $stats_width, $stats_height, 
         'Jumlah Pengundi', 
         $stats['total_voters'],
         [41, 128, 185],
         '👥'); // Dark Blue with people icon
     
     // Present Box (Green)
     drawStatBox($pdf, $x_start + $stats_width + 20, $y_start, $stats_width, $stats_height,
         'Hadir',
         $stats['attended_count'],
         [39, 174, 96],
         '✓'); // Green with check icon
     
     // Adjust Y position for next row
     $y_start += $stats_height + 20;
     
     // Absent Box (Red)
     drawStatBox($pdf, $x_start, $y_start, $stats_width, $stats_height,
         'Tidak Hadir',
         $stats['not_attended_count'],
         [192, 57, 43],
         '✗'); // Red with X icon
     
     // Percentage Box (Purple)
     $attendance_percentage = number_format(($stats['attended_count'] / $stats['total_voters']) * 100, 2) . '%';
     drawStatBox($pdf, $x_start + $stats_width + 20, $y_start, $stats_width, $stats_height,
         'Peratus Kehadiran',
         $attendance_percentage,
         [142, 68, 173],
         '%'); // Purple with percentage icon
     
     // Reset colors
     $pdf->SetTextColor(0, 0, 0);
     
     // Add more spacing before table
     $pdf->Ln($stats_height + 30);
    
    // Add more spacing before table
    $pdf->Ln($stats_height + 30);
    
    // Table header with improved styling
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell($contentWidth, 12, 'SENARAI KEHADIRAN PENGUNDI:', 0, 1, 'L');
    $pdf->Ln(5);
    
    // Improved table styling
    $colWidth1 = $contentWidth * 0.08;  // ID (smaller)
    $colWidth2 = $contentWidth * 0.37; // Name (larger)
    $colWidth3 = $contentWidth * 0.2;  // IC
    $colWidth4 = $contentWidth * 0.15; // Status
    $colWidth5 = $contentWidth * 0.2;  // Time
    
    // Table header with better colors
    $pdf->SetFillColor(41, 128, 185); // Matching blue
    $pdf->SetTextColor(255);
    $pdf->SetFont('helvetica', 'B', 11);
    
    $pdf->Cell($colWidth1, 10, 'ID', 1, 0, 'C', true);
    $pdf->Cell($colWidth2, 10, 'Nama Pengundi', 1, 0, 'C', true);
    $pdf->Cell($colWidth3, 10, 'No. IC', 1, 0, 'C', true);
    $pdf->Cell($colWidth4, 10, 'Status', 1, 0, 'C', true);
    $pdf->Cell($colWidth5, 10, 'Masa Hadir', 1, 1, 'C', true);
    
    // Table content with responsive widths
    $pdf->SetTextColor(0);
    $pdf->SetFont('helvetica', '', 10);
    $fill = false;
    
    // In the table content section
    while ($voter = $voters->fetch_assoc()) {
        $fill = !$fill;
        $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);
        
        $pdf->Cell($colWidth1, 8, $voter['id_pengundi'], 1, 0, 'C', $fill);
        $pdf->Cell($colWidth2, 8, $voter['nama_pengundi'], 1, 0, 'L', $fill);
        $pdf->Cell($colWidth3, 8, $voter['no_ic'], 1, 0, 'C', $fill);
        
        $status_text = $voter['attended'] ? 'Hadir' : 'Tidak Hadir';
        $pdf->SetTextColor($voter['attended'] ? 0 : 187, $voter['attended'] ? 150 : 0, $voter['attended'] ? 0 : 0);
        $pdf->Cell($colWidth4, 8, $status_text, 1, 0, 'C', $fill);
        $pdf->SetTextColor(0);
        
        $pdf->Cell($colWidth5, 8, $voter['attended'] ? date('d/m/Y H:i', strtotime($voter['updated_at'])) : '-', 1, 1, 'C', $fill);
    }

    // Add footer with generation information
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell($contentWidth, 10, 'Laporan ini dijana secara automatik oleh sistem E-HadirUndi pada ' . date('d/m/Y H:i:s'), 0, 1, 'C');
    $pdf->Cell($contentWidth, 10, 'Dokumen ini adalah sah tanpa tandatangan.', 0, 1, 'C');

    // Create reports directory if it doesn't exist
    if (!file_exists(__DIR__ . '/reports')) {
        mkdir(__DIR__ . '/reports', 0777, true);
    }

     // Output PDF
    $filename = 'laporan_kehadiran_' . str_replace(' ', '_', strtolower($room_result['name'])) . '.pdf';
    $pdf->Output(__DIR__ . '/reports/' . $filename, 'F');
    
    // Update database (change user_id to id_pengundi)
    $stmt = $conn->prepare("INSERT INTO report_requests (id_pengundi, room_id, request_date, status, report_file) 
                           VALUES (?, ?, NOW(), 'approved', ?)");
    $stmt->bind_param("iis", $_SESSION['id_pengundi'], $room_id, $filename);
    $stmt->execute();
    
    header("Location: userlaporan.php?success=1");
    exit();
}

// Modify the preview generation section to include voter list
if (isset($_POST['preview_report']) && isset($_POST['room_id'])) {
    $room_id = $_POST['room_id'];
    
    // Get room details
    $room_query = "SELECT name FROM room WHERE id = ?";
    $stmt = $conn->prepare($room_query);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $room_result = $stmt->get_result()->fetch_assoc();
    
    // Get attendance statistics
    $stats_query = "SELECT 
        COUNT(*) as total_voters,
        SUM(CASE WHEN attended = 1 THEN 1 ELSE 0 END) as attended_count,
        SUM(CASE WHEN attended = 0 THEN 1 ELSE 0 END) as not_attended_count
        FROM voters WHERE room_id = ?";
    $stmt = $conn->prepare($stats_query);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    // Get voters details and store in session
    $voters_query = "SELECT id_pengundi, nama_pengundi, no_ic, attended, updated_at 
                    FROM voters WHERE room_id = ? ORDER BY attended DESC, updated_at DESC";
    $stmt = $conn->prepare($voters_query);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $voters_result = $stmt->get_result();
    $voters_list = [];
    while ($voter = $voters_result->fetch_assoc()) {
        $voters_list[] = $voter;
    }
    
    $_SESSION['preview_data'] = [
        'room_name' => $room_result['name'],
        'stats' => $stats,
        'room_id' => $room_id,
        'voters' => $voters_list
    ];
    
    header("Location: userlaporan.php?preview=1");
    exit();
}

// Fetch rooms
$rooms = $conn->query("SELECT * FROM room ORDER BY name");

// Fetch user's report requests
// Replace user_id with id_pengundi
$id_pengundi = isset($_SESSION['id_pengundi']) ? $_SESSION['id_pengundi'] : 0;

// Update the SQL query
$stmt = $conn->prepare("SELECT r.*, rm.name as room_name 
                       FROM report_requests r 
                       LEFT JOIN room rm ON r.room_id = rm.id 
                       WHERE r.id_pengundi = ? 
                       ORDER BY r.request_date DESC");
$stmt->bind_param("i", $id_pengundi);
$stmt->execute();
$requests = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Laporan Kehadiran | E-HadirUndi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/userlaporan.css">
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-file-alt"></i> Laporan Kehadiran Pengundi</h1>
        </div>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> Laporan anda telah berjaya dijana! 
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-plus-circle"></i> Jana Laporan Baru
            </div>
            <div class="card-body">
                <form method="POST" class="report-form">
                    <div class="form-group">
                        <label for="room_id"><i class="fas fa-door-open"></i> Pilih Saluran/Lokasi:</label>
                        <select name="room_id" id="room_id" required>
                            <option value="">-- Pilih Saluran --</option>
                            <?php while ($room = $rooms->fetch_assoc()): ?>
                                <option value="<?= $room['id'] ?>"><?= htmlspecialchars($room['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" name="preview_report" class="btn btn-primary">
                        <i class="fas fa-eye"></i> Pratonton Laporan
                    </button>
                </form>
            </div>
        </div>
        
        <?php if (isset($_GET['preview']) && isset($_SESSION['preview_data'])): ?>
            <div class="preview-section">
                <div class="preview-content">
                    <div class="report-header">
                        <img src="assets/img/sprlogo.png" alt="Logo SPR" class="report-logo">
                        <div class="header-text">
                            <h2>SURUHANJAYA PILIHAN RAYA MALAYSIA</h2>
                            <h3>LAPORAN KEHADIRAN PENGUNDI</h3>
                            <h4><?= htmlspecialchars($_SESSION['preview_data']['room_name']) ?></h4>
                            <p class="report-date">Tarikh: <?= date('d/m/Y') ?></p>
                        </div>
                    </div>
                    
                    <div class="stats-container">
                        <div class="stats-grid">
                            <div class="stat-card total">
                                <i class="fas fa-users stat-icon"></i>
                                <div class="stat-info">
                                    <span class="stat-label">Jumlah Pengundi</span>
                                    <span class="stat-value"><?= $_SESSION['preview_data']['stats']['total_voters'] ?></span>
                                </div>
                            </div>
                            <div class="stat-card present">
                                <i class="fas fa-user-check stat-icon"></i>
                                <div class="stat-info">
                                    <span class="stat-label">Hadir</span>
                                    <span class="stat-value"><?= $_SESSION['preview_data']['stats']['attended_count'] ?></span>
                                </div>
                            </div>
                            <div class="stat-card absent">
                                <i class="fas fa-user-times stat-icon"></i>
                                <div class="stat-info">
                                    <span class="stat-label">Tidak Hadir</span>
                                    <span class="stat-value"><?= $_SESSION['preview_data']['stats']['not_attended_count'] ?></span>
                                </div>
                            </div>
                            <div class="stat-card percentage">
                                <i class="fas fa-chart-pie stat-icon"></i>
                                <div class="stat-info">
                                    <span class="stat-label">Peratus Kehadiran</span>
                                    <span class="stat-value"><?= number_format(($_SESSION['preview_data']['stats']['attended_count'] / $_SESSION['preview_data']['stats']['total_voters']) * 100, 2) ?>%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="voters-section">
                        <h4>SENARAI KEHADIRAN PENGUNDI</h4>
                        <table class="voters-table">
                            <thead>
                                <tr>
                                    <th>ID Pengundi</th>
                                    <th>Nama Pengundi</th>
                                    <th>No. Kad Pengenalan</th>
                                    <th>Status</th>
                                    <th>Masa Kehadiran</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_SESSION['preview_data']['voters'] as $voter): ?>
                                    <tr class="<?= $voter['attended'] ? 'attended-row' : 'not-attended-row' ?>">
                                        <td><?= htmlspecialchars($voter['id_pengundi']) ?></td>
                                        <td><?= htmlspecialchars($voter['nama_pengundi']) ?></td>
                                        <td><?= htmlspecialchars($voter['no_ic']) ?></td>
                                        <td>
                                            <span class="status-badge <?= $voter['attended'] ? 'status-hadir' : 'status-belum' ?>">
                                                <?= $voter['attended'] ? 'Hadir' : 'Tidak Hadir' ?>
                                            </span>
                                        </td>
                                        <td><?= $voter['attended'] ? date('d/m/Y H:i', strtotime($voter['updated_at'])) : '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="action-buttons">
                        <form method="POST" class="generate-form">
                            <input type="hidden" name="room_id" value="<?= $_SESSION['preview_data']['room_id'] ?>">
                            <button type="submit" name="generate_pdf" class="generate-btn">
                                <i class="fas fa-file-pdf"></i> Jana Laporan Rasmi PDF
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Remove this entire style block as these styles are already in userlaporan.css -->
        <?php endif; ?>
        
    
        <?php include 'usersidebar.php'; ?>
    </body>
    </html>
    