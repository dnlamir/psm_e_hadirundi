<?php
session_start();
include 'includes/room.php'; // Updated path to room.php

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['room_id'])) {
    die("Room ID not provided.");
}

$room_id = intval($_GET['room_id']);
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$voters_per_page = 5;
$offset = ($page - 1) * $voters_per_page;

// First, get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM voters WHERE room_id = ?";
$count_params = [$room_id];

if (!empty($search)) {
    $count_query .= " AND (nama_pengundi LIKE ? OR no_ic LIKE ? OR id_pengundi LIKE ?)";
    $searchTerm = "%$search%";
    $count_params = array_merge([$room_id], array_fill(0, 3, $searchTerm));
}

$count_stmt = $conn->prepare($count_query);
$count_types = str_repeat('s', count($count_params));
$count_stmt->bind_param($count_types, ...$count_params);
$count_stmt->execute();
$total_result = $count_stmt->get_result()->fetch_assoc();
$total_voters = $total_result['total'];
$total_pages = ceil($total_voters / $voters_per_page);

// Modify the main query to include LIMIT and OFFSET
$query = "SELECT id, id_pengundi, nama_pengundi, no_ic, alamat, no_telefon, attended 
          FROM voters 
          WHERE room_id = ?";
$params = [$room_id];

if (!empty($search)) {
    $query .= " AND (nama_pengundi LIKE ? OR no_ic LIKE ? OR id_pengundi LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge([$room_id], array_fill(0, 3, $searchTerm));
}

$query .= " LIMIT ? OFFSET ?";
$params = array_merge($params, [$voters_per_page, $offset]);

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Database error: " . $conn->error);
}

$types = str_repeat('s', count($params));
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Kehadiran Pengundi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Add Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Add Toastify CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/manage_voters.css">
</head>
<body>
<div class="container">
    <?php
    // Get room name
    $room_query = "SELECT name as nama_room FROM room WHERE id = ?";  // Changed 'rooms' to 'room' and adjusted column name
    $room_stmt = $conn->prepare($room_query);
    $room_stmt->bind_param('i', $room_id);
    $room_stmt->execute();
    $room_result = $room_stmt->get_result();
    $room_name = $room_result->fetch_assoc()['nama_room'];
    ?>
    <h1>Kehadiran Pengundi - <?php echo htmlspecialchars($room_name); ?></h1>

    <!-- Add date display in Malay format -->
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

    <!-- Add stats card here -->
    <?php
    // Get attendance statistics for current room
    $stats_query = "SELECT 
        COUNT(*) as total_voters,
        SUM(CASE WHEN attended = 1 THEN 1 ELSE 0 END) as attended_voters
        FROM voters 
        WHERE room_id = ?";
    
    $stats_stmt = $conn->prepare($stats_query);
    $stats_stmt->bind_param('i', $room_id);
    $stats_stmt->execute();
    $stats = $stats_stmt->get_result()->fetch_assoc();
    
    $total = $stats['total_voters'];
    $attended = $stats['attended_voters'];
    $percentage = $total > 0 ? round(($attended / $total) * 100, 1) : 0;
    ?>

    <div class="search-container">
        <form method="GET" action="" style="display: flex; gap: 10px; width: 100%; justify-content: center;">
            <input type="hidden" name="room_id" value="<?php echo htmlspecialchars($room_id); ?>">
            <input type="text" class="search-box" name="search" 
                   placeholder="Cari mengikut Nama, ID atau Nombor IC..." 
                   value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="search-btn">
                <i class="fas fa-search"></i> Cari
            </button>
            <button type="button" class="reminder-btn" onclick="sendReminders()">
                <i class="fas fa-bell"></i> Peringatan Kehadiran
            </button>
        </form>
    </div>

    <div class="stats-card">
        <div class="stats-info">
            <div class="stat-item">
                <div class="stat-label">Jumlah Pengundi</div>
                <div class="stat-value"><?php echo $total; ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Telah Hadir</div>
                <div class="stat-value"><?php echo $attended; ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Belum Hadir</div>
                <div class="stat-value"><?php echo $total - $attended; ?></div>
            </div>
        </div>
        <div class="progress-bar">
            <div class="progress" style="width: <?php echo $percentage; ?>%"></div>
        </div>
        <div class="percentage"><?php echo $percentage; ?>% Kehadiran</div>
    </div>

    <table class="voters-table">
        <thead>
        <tr>
            <th>ID Pengundi</th>
            <th>Nama</th>
            <th>Nombor IC</th>
            <th>Alamat</th>
            <th>Nombor Telefon</th>
            <th>Status</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <!-- Modify the table rows to include data-label attributes -->
        <tbody>
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr data-voter-id="<?php echo $row['id']; ?>">
                    <td data-label="ID Pengundi"><?php echo htmlspecialchars($row['id_pengundi']); ?></td>
                    <td data-label="Nama"><?php echo htmlspecialchars($row['nama_pengundi']); ?></td>
                    <td data-label="Nombor IC"><?php echo htmlspecialchars($row['no_ic']); ?></td>
                    <td data-label="Alamat"><?php echo htmlspecialchars($row['alamat']); ?></td>
                    <td data-label="Nombor Telefon"><?php echo htmlspecialchars($row['no_telefon']); ?></td>
                    <td data-label="Status">
                        <span class="status-badge <?php echo $row['attended'] ? 'status-attended' : 'status-not-attended'; ?>">
                            <?php echo $row['attended'] ? 'Hadir' : 'Belum Hadir'; ?>
                        </span>
                    </td>
                    <td data-label="Aksi">
                        <?php if (!$row['attended']): ?>
                            <button class="action-btn" onclick='showConfirmation(<?php echo json_encode($row); ?>)'>Tandakan Kehadiran</button>
                        <?php else: ?>
                            <button class="action-btn disabled-btn" disabled>Sudah Hadir</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="7" style="text-align: center;">Tiada Pengundi yang dijumpai<?php echo !empty($search) ? ' matching your search' : ''; ?>.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Add pagination controls -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?room_id=<?php echo $room_id; ?>&page=<?php echo ($page-1); ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="page-btn">&laquo; Sebelum</a>
            <?php endif; ?>
            
            <span class="page-info">Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?></span>
            
            <?php if ($page < $total_pages): ?>
                <a href="?room_id=<?php echo $room_id; ?>&page=<?php echo ($page+1); ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="page-btn">Seterusnya &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <a href="userhome.php" class="back-btn">Kembali ke Halaman Utama</a>
</div>

<!-- Modal -->
<div id="confirmationModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Sahkan Kehadiran</div>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        <div class="voter-details" id="voterDetails"></div>
        <div class="modal-footer">
            <button class="cancel-btn" onclick="closeModal()">Batal</button>
            <button class="confirm-btn" onclick="confirmAttendance()">Sahkan</button>
        </div>
    </div>
</div>

<!-- Add a new modal for reminders confirmation -->
<div id="reminderModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Peringatan Kehadiran</div>
            <button class="close-btn" onclick="closeReminderModal()">&times;</button>
        </div>
        <div class="reminder-details">
            <p>Adakah anda pasti untuk menghantar peringatan kepada semua pengundi yang belum hadir?</p>
        </div>
        <div class="modal-footer">
            <button class="cancel-btn" onclick="closeReminderModal()">Batal</button>
            <button class="confirm-btn" onclick="confirmSendReminders()">Hantar</button>
        </div>
    </div>
</div>

<!-- Add Toastify JS -->
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

<script>
let currentVoter = null;

function showConfirmation(voter) {
    currentVoter = voter;
    const details = `
        <div class="detail-row"><span class="detail-label">ID Pengundi:</span> ${voter.id_pengundi}</div>
        <div class="detail-row"><span class="detail-label">Nama:</span> ${voter.nama_pengundi}</div>
        <div class="detail-row"><span class="detail-label">Nombor IC:</span> ${voter.no_ic}</div>
        <div class="detail-row"><span class="detail-label">Alamat:</span> ${voter.alamat}</div>
        <div class="detail-row"><span class="detail-label">Nombor Telefon:</span> ${voter.no_telefon}</div>
        <p style="margin-top: 20px;">Adakah anda pasti mahu menandakan pengundi ini sebagai hadir?</p>
    `;
    document.getElementById('voterDetails').innerHTML = details;
    document.getElementById('confirmationModal').style.display = 'flex';
    speakVoterDetails(voter);
}

function speakVoterDetails(voter) {
    // Convert numbers to Malay words
    function convertNumbersToMalay(text) {
        // Replace digits with Malay words
        return text.replace(/\d+/g, function(match) {
            // Convert each digit to its Malay equivalent
            const digits = match.split('');
            let malayDigits = [];
            
            for (let i = 0; i < digits.length; i++) {
                switch(digits[i]) {
                    case '0': malayDigits.push('kosong'); break;
                    case '1': malayDigits.push('satu'); break;
                    case '2': malayDigits.push('dua'); break;
                    case '3': malayDigits.push('tiga'); break;
                    case '4': malayDigits.push('empat'); break;
                    case '5': malayDigits.push('lima'); break;
                    case '6': malayDigits.push('enam'); break;
                    case '7': malayDigits.push('tujuh'); break;
                    case '8': malayDigits.push('lapan'); break;
                    case '9': malayDigits.push('sembilan'); break;
                }
            }
            
            return malayDigits.join(' ');
        });
    }
    
    // Convert the voter details to Malay pronunciation
    const idPengundi = convertNumbersToMalay(voter.id_pengundi);
    const noIC = convertNumbersToMalay(voter.no_ic);
    const noTelefon = convertNumbersToMalay(voter.no_telefon);
    
    const text = `Maklumat pengundi. 
        ID pengundi: ${idPengundi}. 
        Nama: ${voter.nama_pengundi}. 
        Nombor IC: ${noIC}. 
        Alamat: ${voter.alamat}. 
        Nombor telefon: ${noTelefon}. 
        Sila sahkan kehadiran.`;
        
    const utterance = new SpeechSynthesisUtterance(text);
    
    // Set the language to Malay (Malaysia)
    utterance.lang = 'ms-MY';

    // Attempt to find a Malay voice and set it
    const voices = speechSynthesis.getVoices();
    const malayVoice = voices.find(voice => voice.lang === 'ms-MY');

    if (malayVoice) {
        utterance.voice = malayVoice;
    }

    // Speak the text
    speechSynthesis.speak(utterance);
}


function closeModal() {
    speechSynthesis.cancel();
    document.getElementById('confirmationModal').style.display = 'none';
}

function showToast(message, isSuccess = true) {
    Toastify({
        text: message,
        duration: 3000,
        close: true,
        gravity: "top",
        position: "center",
        className: isSuccess ? "toast-success" : "toast-error",
        stopOnFocus: true,
    }).showToast();
}

function confirmAttendance() {
    if (!currentVoter) return;

    fetch('attendance.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `voter_id=${currentVoter.id}&room_id=<?php echo $room_id; ?>`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const row = document.querySelector(`tr[data-voter-id="${currentVoter.id}"]`);
            if (row) {
                // Update the status cell
                const statusCell = row.querySelector('td:nth-child(6)');
                statusCell.innerHTML = `
                    <span class="status-badge status-attended">Hadir</span>
                `;
                
                // Update the action cell
                const actionCell = row.querySelector('td:last-child');
                actionCell.innerHTML = '<button class="action-btn disabled-btn" disabled>Sudah Hadir</button>';

                // Update statistics
                const totalValue = parseInt(document.querySelector('.stat-value').textContent);
                const attendedElement = document.querySelectorAll('.stat-value')[1];
                const notAttendedElement = document.querySelectorAll('.stat-value')[2];
                const attended = parseInt(attendedElement.textContent) + 1;
                const notAttended = totalValue - attended;
                const percentage = (attended / totalValue) * 100;

                attendedElement.textContent = attended;
                notAttendedElement.textContent = notAttended;
                document.querySelector('.progress').style.width = percentage + '%';
                document.querySelector('.percentage').textContent = percentage.toFixed(1) + '% Kehadiran';
            }
            // Show success message with toast instead of alert
            showToast('Kehadiran telah dikemaskini!', true);
        } else {
            // Show error message with toast instead of alert
            showToast('Error: ' + (data.error || 'Tidak dapat mengemaskini status'), false);
        }
    })
    .catch(error => {
        // Show error message with toast instead of alert
        showToast('Error: ' + error.message, false);
    })
    .finally(() => {
        closeModal();
    });
}

window.onclick = function(event) {
    if (event.target == document.getElementById('confirmationModal')) {
        closeModal();
    }
    if (event.target == document.getElementById('reminderModal')) {
        closeReminderModal();
    }
};

document.querySelector('.search-box').addEventListener('input', function () {
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(() => this.form.submit(), 1500); 
});

// Functions for reminder modal
function sendReminders() {
    // Show the reminder modal instead of using confirm()
    document.getElementById('reminderModal').style.display = 'flex';
}

function closeReminderModal() {
    document.getElementById('reminderModal').style.display = 'none';
}

function confirmSendReminders() {
    // Close the modal
    closeReminderModal();
    
    // Show a loading toast
    showToast('Menghantar peringatan...', true);
    
    fetch('send_reminders.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `room_id=<?php echo $room_id; ?>`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`Peringatan telah dihantar kepada ${data.count} pengundi yang belum hadir.`, true);
        } else {
            showToast('Error: ' + (data.error || 'Tidak dapat menghantar peringatan'), false);
        }
    })
    .catch(error => {
        showToast('Error: ' + error.message, false);
    });
}
</script>

<!-- Include Footer -->
<?php include 'usersidebar.php'; ?>
