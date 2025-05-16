<?php
session_start();
include 'includes/room.php'; // Updated path to room.php

// Check if admin is logged in
if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Handle adding a new room
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_room'])) {
    $roomName = $_POST['room_name'];
    $image = $_FILES['room_image'];
    $uploadSuccess = false;
    $uploadError = '';

    // Image upload handling
    $targetDir = "uploads/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $targetFile = $targetDir . basename($image["name"]);
    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($imageFileType, $allowedTypes)) {
        if (move_uploaded_file($image["tmp_name"], $targetFile)) {
            $stmt = $conn->prepare("INSERT INTO room (name, image) VALUES (?, ?)");
            $stmt->bind_param("ss", $roomName, $targetFile);
            $stmt->execute();
            $stmt->close();
            $uploadSuccess = true;
        } else {
            $uploadError = "Ralat memuat naik fail.";
        }
    } else {
        $uploadError = "Jenis fail tidak sah.";
    }
}

// Handle deleting a room
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_room'])) {
    $roomId = $_POST['room_id'];

    // First, delete dependent rows in report_requests
    $stmt = $conn->prepare("DELETE FROM report_requests WHERE room_id = ?");
    $stmt->bind_param("i", $roomId);
    $stmt->execute();
    $stmt->close();

    // Now, delete the room
    $stmt = $conn->prepare("DELETE FROM room WHERE id = ?");
    $stmt->bind_param("i", $roomId);
    $stmt->execute();
    $stmt->close();
    $deleteSuccess = true;
}

// Get room count for dashboard stats
$roomCount = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM room");
if ($result && $row = $result->fetch_assoc()) {
    $roomCount = $row['count'];
}

// Get voter count for dashboard stats
$voterCount = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM voters");
if ($result && $row = $result->fetch_assoc()) {
    $voterCount = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laman Utama Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Link to external CSS file -->
    <link rel="stylesheet" href="assets/css/adminhome.css">
</head>
<body>
    <?php include 'adminsidebar.php'; ?>

    <div class="dashboard-header">
        <div class="welcome-section">
            <h1>Laman Utama</h1>
            <p>Selamat Kembali, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
        </div>
        <div class="date-time">
            <p id="currentDateTime"></p>
        </div>
    </div>

    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon rooms-icon">
                <i class="fas fa-door-open"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $roomCount; ?></h3>
                <p>Jumlah Saluran</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon voters-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $voterCount; ?></h3>
                <p>Jumlah Pengundi</p>
            </div>
        </div>
    </div>

    <div class="content-section">
        <div class="add-room-form">
            <div class="section-title">
                <i class="fas fa-plus-circle"></i>
                <h2>Tambah Saluran Baharu</h2>
            </div>
            <form method="POST" enctype="multipart/form-data" id="addRoomForm">
                <div class="form-group">
                    <label for="room_name">Nama Saluran</label>
                    <input type="text" id="room_name" name="room_name" placeholder="Isi Nama Saluran" required>
                </div>
                <div class="form-group">
                    <label for="room_image">Gambar Saluran</label>
                    <input type="file" id="room_image" name="room_image" accept="image/*" required>
                    <img id="imagePreview" src="#" alt="Image Preview" />
                </div>
                <button type="submit" name="add_room" class="add-room-btn" id="addRoomBtn" disabled>
                    <i class="fas fa-plus"></i> Tambah Saluran
                </button>
            </form>
        </div>

        <div class="rooms-container">
            <div class="rooms-header">
                <h2><i class="fas fa-door-open"></i> Saluran Tersedia</h2>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="roomSearch" placeholder="Cari Saluran...">
                </div>
            </div>

            <?php
            $result = $conn->query("SELECT * FROM room");
            if ($result->num_rows > 0): ?>
                <div class="rooms-grid">
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <div class="room-card" tabindex="0" data-room-name="<?php echo htmlspecialchars(strtolower($row['name'])); ?>">
                            <div class="room-image">
                                <img src="<?php echo htmlspecialchars($row['image']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
                            </div>
                            <div class="room-details">
                                <h3 class="room-name"><?php echo htmlspecialchars($row['name']); ?></h3>
                                <div class="room-actions">
                                    <button class="btn btn-danger" onclick="openDeleteModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['name'])); ?>')" title="Delete this room">
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                    <a href="upload_voters.php?room_id=<?php echo $row['id']; ?>" style="flex: 1;" title="Manage voters for this room">
                                        <button class="btn btn-primary">
                                            <i class="fas fa-users"></i> Urus
                                        </button>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-door-open"></i>
                    <h3>Tiada Saluran Ditemui</h3>
                    <p>Mulakan dengan menambah saluran baharu menggunakan borang di sebelah kiri.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3>Pengesahan </h3>
            <p id="deleteModalText"></p>
            <form method="POST" id="deleteRoomForm">
                <input type="hidden" name="room_id" id="deleteRoomId">
                <div class="modal-actions">
                    <button type="button" onclick="closeDeleteModal()" class="btn btn-cancel">Batal</button>
                    <button type="submit" name="delete_room" class="btn btn-danger">Hapus</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="toast" class="toast"></div>

    <script>
        // Update current date and time
        function updateDateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            document.getElementById('currentDateTime').textContent = now.toLocaleDateString('ms-MY', options);
        }
        updateDateTime();
        setInterval(updateDateTime, 60000); // Update every minute
        
        // Image preview for room upload
        document.getElementById('room_image').addEventListener('change', function(event) {
            const [file] = event.target.files;
            const preview = document.getElementById('imagePreview');
            if (file) {
                preview.src = URL.createObjectURL(file);
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        });
    
        // Enable Add Room button only if both fields are filled
        function checkForm() {
            const name = document.getElementById('room_name').value.trim();
            const image = document.getElementById('room_image').files.length > 0;
            document.getElementById('addRoomBtn').disabled = !(name && image);
        }
        document.getElementById('room_name').addEventListener('input', checkForm);
        document.getElementById('room_image').addEventListener('change', checkForm);
    
        // Modal for delete confirmation
        function openDeleteModal(roomId, roomName) {
            document.getElementById('deleteRoomId').value = roomId;
            document.getElementById('deleteModalText').textContent = `Adakah anda pasti mahu memadam "${roomName}"? Tindakan ini tidak boleh diulang.`;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeDeleteModal();
            }
        };
    
        function showToast(message, type) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = `toast ${type}`;
            toast.style.display = 'block';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }
        
        // Room search functionality
        document.getElementById('roomSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase().trim();
            const roomCards = document.querySelectorAll('.room-card');
            
            roomCards.forEach(card => {
                const roomName = card.getAttribute('data-room-name');
                if (roomName.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    
        // Show toast for PHP messages
        <?php
        if (isset($uploadSuccess) && $uploadSuccess) {
            echo "showToast('Saluran berjaya ditambahkan!', 'success');";
        }
        if (!empty($uploadError)) {
            echo "showToast('Error: " . addslashes($uploadError) . "', 'error');";
        }
        if (isset($deleteSuccess) && $deleteSuccess) {
            echo "showToast('Saluran berjaya dipadamkan!', 'success');";
        }
        ?>
    </script>
</body>
</html>
