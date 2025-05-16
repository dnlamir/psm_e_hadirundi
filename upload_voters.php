<?php
session_start();
include 'includes/room.php'; // Updated path to room.php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'admin') {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['room_id'])) {
    echo "Invalid request.";
    exit();
}

$room_id = $_GET['room_id'];
$uploadSuccess = false;
$fileName = '';
$fileSize = '';
$errorMessage = '';

// Fetch room name - Fix table name from 'rooms' to 'room'
$roomQuery = $conn->query("SELECT name FROM room WHERE id = $room_id");
$roomData = $roomQuery->fetch_assoc();
$roomName = $roomData ? $roomData['name'] : 'Unknown Room';

// Handle file deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    // Remove the server-side confirmation check since we're handling it with JavaScript
    $conn->query("DELETE FROM voters WHERE room_id = $room_id");
    echo "<script>alert('Senarai pengundi berjaya dipadam.'); window.location.href='upload_voters.php?room_id=$room_id';</script>";
    exit();
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['voter_file'])) {
    if ($_FILES['voter_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['voter_file']['tmp_name'];
        $fileName = $_FILES['voter_file']['name'];
        $fileSize = round($_FILES['voter_file']['size'] / (1024 * 1024), 1) . ' MB';
        
        // Validate file type
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($fileExtension !== 'xlsx' && $fileExtension !== 'xls') {
            $errorMessage = "Jenis fail tidak sah. Sila muat naik fail Excel (.xlsx atau .xls).";
        } else {
            try {
                $spreadsheet = IOFactory::load($file);
                $worksheet = $spreadsheet->getActiveSheet();
                $data = $worksheet->toArray();
                
                if (count($data) <= 1) {
                    $errorMessage = "Fail Excel tidak mengandungi data yang mencukupi.";
                } else {
                    array_shift($data); // Remove headers
                    
                    $conn->query("DELETE FROM voters WHERE room_id = $room_id");
                    $insertCount = 0;
                    
                    foreach ($data as $row) {
                        if (count($row) < 5 || empty(array_filter($row))) continue;
                        
                        $id_pengundi = trim($row[0]);
                        $nama_pengundi = trim($row[1]);
                        $no_ic = trim($row[2]);
                        $alamat = trim($row[3]);
                        $no_telefon = trim($row[4]);
                        
                        $stmt = $conn->prepare("INSERT INTO voters (room_id, id_pengundi, nama_pengundi, no_ic, alamat, no_telefon, attended) VALUES (?, ?, ?, ?, ?, ?, 0)");
                        $stmt->bind_param("isssss", $room_id, $id_pengundi, $nama_pengundi, $no_ic, $alamat, $no_telefon);
                        $stmt->execute();
                        $insertCount++;
                    }
                    
                    echo "<script>
                        alert('Senarai pengundi berjaya dimuat naik. $insertCount rekod telah diimport.');
                        window.location.href='upload_voters.php?room_id=$room_id';
                    </script>";
                    exit();
                }
            } catch (Exception $e) {
                $errorMessage = "Ralat memproses fail: " . $e->getMessage();
            }
        }
    } else {
        switch ($_FILES['voter_file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMessage = "Saiz fail terlalu besar. Had maksimum adalah 10MB.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMessage = "Fail hanya dimuat naik sebahagian sahaja.";
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMessage = "Tiada fail dipilih.";
                break;
            default:
                $errorMessage = "Ralat memuat naik fail. Kod ralat: " . $_FILES['voter_file']['error'];
        }
    }
    
    if (!empty($errorMessage)) {
        echo "<script>alert('$errorMessage');</script>";
    }
}

// Fetch the uploaded data
$voters = $conn->query("SELECT * FROM voters WHERE room_id = $room_id");
$voterCount = $voters->num_rows;
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kehadiran - Muat Naik Pengundi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/upload_voters.css">
</head>
<body>
<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<div class="page-container">
    <div class="page-header">
        <h1 class="page-title">Sistem Pengurusan Kehadiran Pengundi</h1>
        <h2 style="color: var(--primary-color); font-size: 1.4rem; margin-top: 0.5rem;"><?php echo htmlspecialchars($roomName); ?></h2>
        <p class="page-subtitle">Muat naik dan urus senarai pengundi dengan mudah</p>
    </div>

    <div class="card">
        <div class="card-header">
            <i class="fas fa-file-upload card-header-icon"></i>
            <h2 class="card-header-title">Muat Naik Senarai Pengundi</h2>
        </div>
        <div class="card-body">
            <!-- File Upload Form -->
            <form action="upload_voters.php?room_id=<?php echo $room_id; ?>" method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="upload-area" id="dropArea">
                    <div class="upload-icon">
                        <i class="fas fa-file-excel"></i>
                    </div>
                    <div class="upload-text">
                        <strong>Seret & Lepaskan Fail Excel Anda di Sini</strong><br>
                        atau
                    </div>
                    <input type="file" id="fileInput" class="file-input" name="voter_file" accept=".xls,.xlsx" required>
                    <label for="fileInput" class="btn btn-primary"><i class="fas fa-search"></i> Pilih Fail</label>
                    <div class="upload-text" style="margin-top: 0.75rem; font-size: 0.9rem;">
                        <i class="fas fa-info-circle"></i> Format yang diterima: .xlsx, .xls | Saiz maksimum: 10MB
                    </div>
                </div>

                <!-- Preview filename and size -->
                <div id="fileInfo" class="file-info">
                    <div class="file-info-icon">
                        <i class="fas fa-file-excel"></i>
                    </div>
                    <div class="file-info-content">
                        <div class="file-info-name" id="fileName"></div>
                        <div class="file-info-size" id="fileSize"></div>
                    </div>
                </div>

                <div class="buttons-container">
                    <button type="submit" class="btn btn-success" id="importBtn"><i class="fas fa-file-import"></i> Import Data</button>
                    <a href="adminhome.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                </div>
            </form>
            
            <!-- Separate Delete Form -->
            <form action="upload_voters.php?room_id=<?php echo $room_id; ?>" method="POST" style="margin-top: 1.25rem; text-align: center;" id="confirmDeleteForm">
                <button type="button" onclick="confirmDelete()" class="btn btn-danger"><i class="fas fa-trash-alt"></i> Padam Semua Data</button>
                <input type="hidden" name="delete_file" value="1">
            </form>
        </div>
    </div>

    <?php if ($voterCount > 0): ?>
    <div class="card">
        <div class="card-header">
            <i class="fas fa-users card-header-icon"></i>
            <h2 class="card-header-title">Senarai Pengundi</h2>
        </div>
        <div class="card-body">
            <div class="data-summary">
                <div class="data-count">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Jumlah Pengundi: <strong><?php echo $voterCount; ?></strong></span>
                </div>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID Pengundi</th>
                            <th>Nama Pengundi</th>
                            <th>No IC</th>
                            <th>Alamat</th>
                            <th>No Telefon</th>
                            <th>Status Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $voters->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['id_pengundi']); ?></td>
                                <td><?php echo htmlspecialchars($row['nama_pengundi']); ?></td>
                                <td><?php echo htmlspecialchars($row['no_ic']); ?></td>
                                <td><?php echo htmlspecialchars($row['alamat']); ?></td>
                                <td><?php echo htmlspecialchars($row['no_telefon']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $row['attended'] ? 'status-yes' : 'status-no'; ?>">
                                        <?php echo $row['attended'] ? 'Hadir' : 'Belum Hadir'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Include Footer -->
<?php include 'adminsidebar.php'; ?>

<script>
    const fileInput = document.getElementById("fileInput");
    const fileInfo = document.getElementById("fileInfo");
    const fileName = document.getElementById("fileName");
    const fileSize = document.getElementById("fileSize");
    const dropArea = document.getElementById("dropArea");
    const uploadForm = document.getElementById("uploadForm");
    const loadingOverlay = document.getElementById("loadingOverlay");
    const importBtn = document.getElementById("importBtn");

    // Handle drag and drop events
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, unhighlight, false);
    });

    function highlight() {
        dropArea.classList.add('dragover');
    }

    function unhighlight() {
        dropArea.classList.remove('dragover');
    }

    dropArea.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        fileInput.files = files;
        updateFileInfo(files[0]);
    }

    fileInput.addEventListener("change", function() {
        if (this.files.length > 0) {
            updateFileInfo(this.files[0]);
        }
    });

    function updateFileInfo(file) {
        if (file) {
            // Validate file type
            const fileExtension = file.name.split('.').pop().toLowerCase();
            if (fileExtension !== 'xlsx' && fileExtension !== 'xls') {
                alert('Jenis fail tidak sah. Sila muat naik fail Excel (.xlsx atau .xls).');
                fileInput.value = '';
                fileInfo.classList.remove("show");
                return;
            }
            
            // Validate file size (max 10MB)
            const maxSize = 10 * 1024 * 1024; // 10MB in bytes
            if (file.size > maxSize) {
                alert('Saiz fail terlalu besar. Had maksimum adalah 10MB.');
                fileInput.value = '';
                fileInfo.classList.remove("show");
                return;
            }
            
            const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
            fileInfo.classList.add("show");
            fileName.textContent = file.name;
            fileSize.textContent = `Saiz: ${sizeInMB} MB`;
            importBtn.focus();
        } else {
            fileInfo.classList.remove("show");
            fileName.textContent = '';
            fileSize.textContent = '';
        }
    }
    
    function confirmDelete() {
        if(confirm('Adakah anda pasti ingin memadam semua rekod pengundi? Tindakan ini tidak boleh dibatalkan.')) {
            loadingOverlay.style.display = 'flex';
            document.getElementById('confirmDeleteForm').submit();
        }
    }
    
    // Show loading overlay when form is submitted
    uploadForm.addEventListener('submit', function() {
        if (fileInput.files.length > 0) {
            loadingOverlay.style.display = 'flex';
        }
    });
</script>

</body>
</html>