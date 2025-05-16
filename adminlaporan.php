<?php
session_start();
include 'includes/room.php';

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Add delete report functionality
if (isset($_GET['delete_report']) && !empty($_GET['delete_report'])) {
    $reportToDelete = basename($_GET['delete_report']); // Get filename and sanitize it
    $reportPath = __DIR__ . '/reports/' . $reportToDelete;
    
    // Check if file exists and is within the reports directory
    if (file_exists($reportPath) && is_file($reportPath) && strpos(realpath($reportPath), realpath(__DIR__ . '/reports/')) === 0) {
        if (unlink($reportPath)) {
            header("Location: adminlaporan.php?delete_success=1");
            exit();
        } else {
            header("Location: adminlaporan.php?delete_error=1");
            exit();
        }
    } else {
        header("Location: adminlaporan.php?delete_error=2");
        exit();
    }
}

// Generate PDF report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    require('fpdf/fpdf.php');
    
    // Get room name for the report title
    $roomName = "Semua Bilik";
    if (!empty($_POST['room_id'])) {
        $roomStmt = $conn->prepare("SELECT name FROM room WHERE id = ?");
        $roomStmt->bind_param('i', $_POST['room_id']);
        $roomStmt->execute();
        $roomResult = $roomStmt->get_result();
        if ($roomRow = $roomResult->fetch_assoc()) {
            $roomName = $roomRow['name'];
        }
    }
    
    // Get status text
    $statusText = "Semua Status";
    if ($_POST['filter'] === 'hadir') {
        $statusText = "Hadir";
    } elseif ($_POST['filter'] === 'tidak_hadir') {
        $statusText = "Tidak Hadir";
    }
    
    // Build SQL query for report
    $sql = "SELECT id_pengundi, nama_pengundi, no_ic, attended, updated_at FROM voters WHERE 1";
    $params = [];
    $types = '';

    if (!empty($_POST['room_id'])) {
        $sql .= " AND room_id = ?";
        $params[] = $_POST['room_id'];
        $types .= 'i';
    }
    if ($_POST['filter'] !== 'semua') {
        $sql .= " AND attended = ?";
        $params[] = ($_POST['filter'] === 'hadir') ? 1 : 0;
        $types .= 'i';
    }
    $sql .= " ORDER BY attended DESC, updated_at DESC";

    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $reportData = [];
    while ($row = $result->fetch_assoc()) {
        $reportData[] = $row;
    }
    
    // Create PDF
    class PDF extends FPDF {
        function Header() {
        
            // Logo - positioned better
            $this->Image('assets/img/sprlogo.png', 15, 8, 25);
            
            // Arial bold 15
            $this->SetFont('Arial', 'B', 15);
            // Title - centered properly
            $this->SetTextColor(50, 50, 50);
            $this->SetY(15);
            $this->Cell(0, 10, 'Laporan Kehadiran Pengundi', 0, 0, 'C');
            
            // Line break
            $this->Ln(25);
        }

        function Footer() {
            // Footer bar
            $this->SetFillColor(220, 220, 220);
            $this->Rect(0, $this->GetPageHeight()-20, $this->GetPageWidth(), 20, 'F');
            
            // Position at 1.5 cm from bottom
            $this->SetY(-15);
            // Arial italic 8
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(50, 50, 50);
            // Page number
            $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }

    // Initialize PDF
    $pdf = new PDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 12);
    
    // Report title and info - improved styling
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(0, 10, 'Laporan Kehadiran: ' . $roomName, 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(70, 70, 70);
    $pdf->Cell(0, 10, 'Status: ' . $statusText, 0, 1);
    $pdf->Cell(0, 10, 'Tarikh: ' . date('d/m/Y H:i'), 0, 1);
    $pdf->Ln(5);
    $pdf->Ln(10);
    
    // Table header
    $pdf->SetFillColor(44, 62, 80);
    $pdf->SetTextColor(255);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(20, 10, 'ID', 1, 0, 'C', true);
    $pdf->Cell(70, 10, 'Nama Pengundi', 1, 0, 'C', true);
    $pdf->Cell(40, 10, 'No. IC', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'Status', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'Masa Kehadiran', 1, 1, 'C', true);
    
    // Table data
    $pdf->SetFillColor(245, 247, 250);
    $pdf->SetTextColor(0);
    $pdf->SetFont('Arial', '', 10);
    
    $fill = false;
    foreach ($reportData as $row) {
        $pdf->Cell(20, 10, $row['id_pengundi'], 1, 0, 'C', $fill);
        $pdf->Cell(70, 10, $row['nama_pengundi'], 1, 0, 'L', $fill);
        $pdf->Cell(40, 10, $row['no_ic'], 1, 0, 'C', $fill);
        $status = $row['attended'] ? 'Hadir' : 'Tidak Hadir';
        $pdf->Cell(30, 10, $status, 1, 0, 'C', $fill);
        $time = $row['attended'] ? date('d/m/Y H:i', strtotime($row['updated_at'])) : '-';
        $pdf->Cell(30, 10, $time, 1, 1, 'C', $fill);
        $fill = !$fill;
    }
    
    // Summary
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 10, 'Ringkasan:', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    
    $totalCount = count($reportData);
    $attendedCount = 0;
    $notAttendedCount = 0;
    
    foreach ($reportData as $row) {
        if ($row['attended']) {
            $attendedCount++;
        } else {
            $notAttendedCount++;
        }
    }
    
    $pdf->Cell(0, 10, 'Jumlah Pengundi: ' . $totalCount, 0, 1);
    $pdf->Cell(0, 10, 'Pengundi Hadir: ' . $attendedCount, 0, 1);
    $pdf->Cell(0, 10, 'Pengundi Tidak Hadir: ' . $notAttendedCount, 0, 1);
    
    // Generate filename for temporary storage
    $timestamp = date('YmdHis');
    $filename = "temp_laporan_kehadiran_{$roomName}_{$statusText}_{$timestamp}.pdf";
    $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $filename); // Sanitize filename
    
    // Create temp directory if it doesn't exist
    $tempDir = __DIR__ . '/temp';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    
    $tempFilepath = $tempDir . '/' . $filename;
    
    // Save PDF to temporary file
    $pdf->Output('F', $tempFilepath);
    
    // Store report data in session for later saving if needed
    $_SESSION['temp_report'] = [
        'filename' => $filename,
        'room_name' => $roomName,
        'status_text' => $statusText,
        'timestamp' => $timestamp,
        'temp_path' => $tempFilepath
    ];
    
    // Redirect to preview page
    header("Location: adminlaporan.php?preview_temp_report=" . urlencode($filename));
    exit();
}

// Save report to permanent storage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_report']) && isset($_SESSION['temp_report'])) {
    $tempReport = $_SESSION['temp_report'];
    
    // Create reports directory if it doesn't exist
    $reportsDir = __DIR__ . '/reports';
    if (!is_dir($reportsDir)) {
        mkdir($reportsDir, 0777, true);
    }
    
    // Generate permanent filename
    $permanentFilename = "laporan_kehadiran_{$tempReport['room_name']}_{$tempReport['status_text']}_{$tempReport['timestamp']}.pdf";
    $permanentFilename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $permanentFilename); // Sanitize filename
    $permanentFilepath = $reportsDir . '/' . $permanentFilename;
    
    // Copy from temp to permanent location
    if (file_exists($tempReport['temp_path']) && copy($tempReport['temp_path'], $permanentFilepath)) {
        // Delete temp file
        unlink($tempReport['temp_path']);
        
        // Clear session data
        unset($_SESSION['temp_report']);
        
        // Redirect to success page
        header("Location: adminlaporan.php?save_success=1&preview_report=" . urlencode($permanentFilename));
        exit();
    } else {
        // Handle error
        header("Location: adminlaporan.php?save_error=1");
        exit();
    }
}

$rooms = $conn->query("SELECT * FROM room ORDER BY name");

$filter = $_POST['filter'] ?? 'semua';
$room_id = $_POST['room_id'] ?? '';
$filteredResults = [];
$showResults = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filter_submit'])) {
    // Build SQL query
    $sql = "SELECT id_pengundi, nama_pengundi, no_ic, attended, updated_at FROM voters WHERE 1";
    $params = [];
    $types = '';

    if (!empty($room_id)) {
        $sql .= " AND room_id = ?";
        $params[] = $room_id;
        $types .= 'i';
    }
    if ($filter !== 'semua') {
        $sql .= " AND attended = ?";
        $params[] = ($filter === 'hadir') ? 1 : 0;
        $types .= 'i';
    }
    $sql .= " ORDER BY attended DESC, updated_at DESC";

    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $filteredResults[] = $row;
    }
    $showResults = true;
}

// Get preview temp report if requested
$previewTempReport = null;
if (isset($_GET['preview_temp_report']) && !empty($_GET['preview_temp_report']) && isset($_SESSION['temp_report'])) {
    $reportToPreview = basename($_GET['preview_temp_report']);
    $tempPath = __DIR__ . '/temp/' . $reportToPreview;
    
    if (file_exists($tempPath) && is_file($tempPath) && $reportToPreview === $_SESSION['temp_report']['filename']) {
        $previewTempReport = [
            'name' => $reportToPreview,
            'url' => 'temp/' . urlencode($reportToPreview)
        ];
    }
}

// Get preview report if requested
$previewReport = null;
if (isset($_GET['preview_report']) && !empty($_GET['preview_report'])) {
    $reportToPreview = basename($_GET['preview_report']);
    $reportPath = __DIR__ . '/reports/' . $reportToPreview;
    
    if (file_exists($reportPath) && is_file($reportPath) && strpos(realpath($reportPath), realpath(__DIR__ . '/reports/')) === 0) {
        $previewReport = [
            'name' => $reportToPreview,
            'url' => 'reports/' . urlencode($reportToPreview)
        ];
    }
}

$reportsDir = __DIR__ . '/reports';
$reportFiles = [];
if (is_dir($reportsDir)) {
    $files = array_diff(scandir($reportsDir), ['.', '..']);
    foreach ($files as $file) {
        $filePath = $reportsDir . '/' . $file;
        if (is_file($filePath)) {
            $reportFiles[] = [
                'name' => $file,
                'date' => date("Y-m-d H:i", filemtime($filePath)),
                'url' => 'reports/' . urlencode($file)
            ];
        }
    }
    // Sort by date descending
    usort($reportFiles, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}

// Count statistics
$totalVoters = 0;
$attendedVoters = 0;
$notAttendedVoters = 0;

if (!empty($room_id)) {
    $statsQuery = "SELECT COUNT(*) as total, 
                  SUM(CASE WHEN attended = 1 THEN 1 ELSE 0 END) as attended,
                  SUM(CASE WHEN attended = 0 THEN 1 ELSE 0 END) as not_attended
                  FROM voters WHERE room_id = ?";
    $statsStmt = $conn->prepare($statsQuery);
    $statsStmt->bind_param('i', $room_id);
    $statsStmt->execute();
    $statsResult = $statsStmt->get_result()->fetch_assoc();
    
    $totalVoters = $statsResult['total'];
    $attendedVoters = $statsResult['attended'];
    $notAttendedVoters = $statsResult['not_attended'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Laporan Kehadiran</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Link to external CSS file -->
    <link rel="stylesheet" href="assets/css/adminlaporan.css">
    <style>
        /* PDF Preview Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.7);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 900px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close-modal:hover {
            color: #333;
        }
        
        .pdf-container {
            width: 100%;
            height: 600px;
            margin: 20px 0;
            border: 1px solid #ddd;
        }
        
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-download {
            background-color: #27ae60;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-download:hover {
            background-color: #219653;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <?php include 'adminsidebar.php'; ?>
    </div>
    <div class="main-content">
        <div class="container">
            <div class="page-header">
                <h1><i class="fas fa-chart-bar"></i> Admin Laporan Kehadiran Pengundi</h1>
                <p>Pantau dan analisis kehadiran pengundi secara masa nyata</p>
            </div>
            
            <?php if (isset($_GET['save_success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Laporan telah berjaya disimpan.
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['save_error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> Ralat semasa menyimpan laporan. Sila cuba lagi.
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['delete_success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Laporan telah berjaya dipadam.
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['delete_error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> 
                <?php if ($_GET['delete_error'] == 2): ?>
                    Fail tidak dijumpai atau tidak sah.
                <?php else: ?>
                    Ralat semasa memadam fail. Sila cuba lagi.
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($room_id)): ?>
            <div class="stats-container">
                <div class="stat-card primary">
                    <i class="fas fa-users"></i>
                    <div class="stat-value"><?= $totalVoters ?></div>
                    <div class="stat-label">Jumlah Pengundi</div>
                </div>
                <div class="stat-card success">
                    <i class="fas fa-user-check"></i>
                    <div class="stat-value"><?= $attendedVoters ?></div>
                    <div class="stat-label">Pengundi Hadir</div>
                </div>
                <div class="stat-card danger">
                    <i class="fas fa-user-times"></i>
                    <div class="stat-value"><?= $notAttendedVoters ?></div>
                    <div class="stat-label">Pengundi Tidak Hadir</div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <h2><i class="fas fa-filter"></i> Filter Kehadiran</h2>
                <form method="POST" class="filter-form">
                    <div class="form-group">
                        <label for="room_id"><i class="fas fa-door-open"></i> Bilik:</label>
                        <select name="room_id" id="room_id" required>
                            <option value="">Pilih Bilik</option>
                            <?php foreach ($rooms as $room): ?>
                                <option value="<?= $room['id'] ?>" <?= ($room_id == $room['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($room['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="filter"><i class="fas fa-clipboard-check"></i> Status:</label>
                        <select name="filter" id="filter" required>
                            <option value="hadir" <?= ($filter == 'hadir') ? 'selected' : '' ?>>Hadir</option>
                            <option value="tidak_hadir" <?= ($filter == 'tidak_hadir') ? 'selected' : '' ?>>Tidak Hadir</option>
                            <option value="semua" <?= ($filter == 'semua') ? 'selected' : '' ?>>Semua</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 0 0 auto; min-width: 150px;">
                        <button type="submit" name="filter_submit"><i class="fas fa-search"></i> Tapis</button>
                    </div>
                </form>
            </div>

            <?php if ($showResults): ?>
            <div class="card">
                <h2><i class="fas fa-list"></i> Senarai Kehadiran</h2>
                
                <form method="POST" style="margin-bottom: 20px;">
                    <input type="hidden" name="room_id" value="<?= htmlspecialchars($room_id) ?>">
                    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                    <button type="submit" name="generate_report" class="btn-download">
                        <i class="fas fa-file-pdf"></i> Jana Laporan
                    </button>
                </form>
                
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-id-card"></i> ID Pengundi</th>
                            <th><i class="fas fa-user"></i> Nama Pengundi</th>
                            <th><i class="fas fa-fingerprint"></i> No. IC</th>
                            <th><i class="fas fa-check-circle"></i> Status</th>
                            <th><i class="fas fa-clock"></i> Masa Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($filteredResults) > 0): ?>
                            <?php foreach ($filteredResults as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['id_pengundi']) ?></td>
                                    <td><?= htmlspecialchars($row['nama_pengundi']) ?></td>
                                    <td><?= htmlspecialchars($row['no_ic']) ?></td>
                                    <td>
                                        <span class="<?= $row['attended'] ? 'status-hadir' : 'status-tidak' ?>">
                                            <?php if ($row['attended']): ?>
                                                <i class="fas fa-check-circle"></i> Hadir
                                            <?php else: ?>
                                                <i class="fas fa-times-circle"></i> Tidak Hadir
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td><?= $row['attended'] ? date('d/m/Y H:i', strtotime($row['updated_at'])) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="empty-state"><i class="fas fa-info-circle"></i> Tiada data untuk tapisan ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div class="card">
                <h2><i class="fas fa-file-alt"></i> Laporan Tersedia</h2>
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-file-pdf"></i> Nama Fail</th>
                            <th><i class="fas fa-calendar-alt"></i> Tarikh Kemaskini</th>
                            <th><i class="fas fa-eye"></i> Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($reportFiles) > 0): ?>
                            <?php foreach ($reportFiles as $f): ?>
                                <tr>
                                    <td><?= htmlspecialchars($f['name']) ?></td>
                                    <td><?= htmlspecialchars($f['date']) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a class="preview-link" href="adminlaporan.php?preview_report=<?= urlencode($f['name']) ?>">
                                                <i class="fas fa-eye"></i> Pratonton
                                            </a>
                                            <a class="preview-link" href="<?= $f['url'] ?>" target="_blank">
                                                <i class="fas fa-external-link-alt"></i> Lihat
                                            </a>
                                            <a class="delete-link" href="adminlaporan.php?delete_report=<?= urlencode($f['name']) ?>" onclick="return confirm('Adakah anda pasti mahu memadam laporan ini?');">
                                                <i class="fas fa-trash-alt"></i> Padam
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="empty-state"><i class="fas fa-info-circle"></i> Tiada laporan dijumpai.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- PDF Preview Modal -->
    <div id="pdfPreviewModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <h2><i class="fas fa-file-pdf"></i> Pratonton Laporan</h2>
            
            <?php if ($previewTempReport): ?>
                <div class="pdf-container">
                    <iframe src="<?= $previewTempReport['url'] ?>" width="100%" height="100%" frameborder="0"></iframe>
                </div>
                <div class="modal-actions">
                    <a href="<?= $previewTempReport['url'] ?>" download class="btn-download">
                        <i class="fas fa-download"></i> Muat Turun PDF
                    </a>
                </div>
            <?php elseif ($previewReport): ?>
                <div class="pdf-container">
                    <iframe src="<?= $previewReport['url'] ?>" width="100%" height="100%" frameborder="0"></iframe>
                </div>
                <div class="modal-actions">
                    <a href="<?= $previewReport['url'] ?>" download class="btn-download">
                        <i class="fas fa-download"></i> Muat Turun PDF
                    </a>
                </div>
            <?php else: ?>
                <p>Tiada laporan untuk dipaparkan.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
        
        // Show modal if preview report is set
        <?php if ($previewReport || $previewTempReport): ?>
            document.getElementById('pdfPreviewModal').style.display = 'block';
        <?php endif; ?>
    });
    
    // Close modal function
    function closeModal() {
        document.getElementById('pdfPreviewModal').style.display = 'none';
        
        // Remove preview parameter from URL to avoid showing modal again on refresh
        const url = new URL(window.location.href);
        url.searchParams.delete('preview_report');
        url.searchParams.delete('preview_temp_report');
        window.history.replaceState({}, document.title, url);
        
        <?php if ($previewTempReport): ?>
        // Redirect to main page to avoid keeping temp file reference
        window.location.href = 'adminlaporan.php';
        <?php endif; ?>
    }
    
    // Close modal when clicking outside of it
    window.onclick = function(event) {
        const modal = document.getElementById('pdfPreviewModal');
        if (event.target == modal) {
            closeModal();
        }
    }
    </script>
</body>
</html>