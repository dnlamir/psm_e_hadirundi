<?php
session_start();
include 'includes/room.php';

// Check if admin is logged in
if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Handle filter input
$filter_room = isset($_GET['room']) ? $_GET['room'] : '';
// $filter_date = isset($_GET['date']) ? $_GET['date'] : ''; // DELETE THIS LINE

// --- ADD THIS BLOCK: Get reminder count ---
$reminder_count = 0;
if ($filter_room !== '') {
    // Filter by selected room
    $reminder_sql = "SELECT COUNT(*) as cnt FROM reminder_logs WHERE room_id = '".intval($filter_room)."'";
} else {
    // All rooms
    $reminder_sql = "SELECT COUNT(*) as cnt FROM reminder_logs";
}
$reminder_result = $conn->query($reminder_sql);
if ($reminder_result && $row = $reminder_result->fetch_assoc()) {
    $reminder_count = (int)$row['cnt'];
}
// --- END ADD ---

// Build filter conditions for SQL
$where = [];
if ($filter_room !== '') {
    $where[] = "v.room_id = '".intval($filter_room)."'";
}
// if ($filter_date !== '') {
//     $where[] = "DATE(v.updated_at) = '".date('Y-m-d', strtotime($filter_date))."'";
// } // DELETE THESE LINES
$where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get list of rooms for dropdown
$rooms_result = $conn->query("SELECT id, name FROM room ORDER BY name");

// Now, all queries that use $where_sql are below this point
// Get overall statistics with more details
$overall_query = "SELECT 
    COUNT(DISTINCT v.id) as total_voters,
    SUM(CASE WHEN v.attended = 1 THEN 1 ELSE 0 END) as total_attended,
    COUNT(DISTINCT r.id) as total_rooms,
    SUM(CASE WHEN v.attended = 0 THEN 1 ELSE 0 END) as total_absent,
    COUNT(DISTINCT CASE WHEN v.attended = 1 THEN DATE(v.updated_at) END) as total_active_days
    FROM voters v
    LEFT JOIN room r ON v.room_id = r.id
    $where_sql";

// Get hourly attendance statistics
$hourly_stats_query = "SELECT 
    HOUR(updated_at) as hour,
    COUNT(*) as count
    FROM voters 
    WHERE attended = 1 
    AND updated_at >= CURDATE()
    GROUP BY HOUR(updated_at)
    ORDER BY hour";

// Add this new query for hourly attendance by room
$hourly_line_query = "SELECT 
    r.id as room_id,
    r.name as room_name,
    HOUR(v.updated_at) as hour,
    COUNT(*) as attendance_count
    FROM room r
    LEFT JOIN voters v ON r.id = v.room_id
    WHERE v.attended = 1 
        AND v.updated_at IS NOT NULL";

// Add filter condition if room is selected
if ($filter_room !== '') {
    $hourly_line_query .= " AND r.id = '".intval($filter_room)."'";
}

$hourly_line_query .= " GROUP BY r.id, r.name, HOUR(v.updated_at)
    ORDER BY r.id, HOUR(v.updated_at)";

// Get attendance by date
$daily_stats_query = "SELECT 
    DATE(updated_at) as date,
    COUNT(*) as count
    FROM voters 
    WHERE attended = 1
    GROUP BY DATE(updated_at)
    ORDER BY date DESC
    LIMIT 7";

// Get user activity log
$user_activity_query = "SELECT 
    v.nama_pengundi,
    v.id_pengundi,
    v.no_ic,
    v.no_telefon,
    r.name as room_name,
    v.updated_at,
    v.alamat
    FROM voters v
    JOIN room r ON v.room_id = r.id
    WHERE v.attended = 1
    ORDER BY v.updated_at DESC
    LIMIT 15";

$overall_result = $conn->query($overall_query);
$hourly_result = $conn->query($hourly_stats_query);
$daily_result = $conn->query($daily_stats_query);
$user_activity_result = $conn->query($user_activity_query);
$hourly_line_result = $conn->query($hourly_line_query); // Execute the new query

// Get room statistics with more details
$room_stats_query = "SELECT 
    r.id,
    r.name,
    COUNT(v.id) as total_voters,
    SUM(CASE WHEN v.attended = 1 THEN 1 ELSE 0 END) as attended_voters,
    MAX(v.updated_at) as last_attendance,
    MIN(CASE WHEN v.attended = 1 THEN v.updated_at END) as first_attendance,
    COUNT(DISTINCT DATE(v.updated_at)) as active_days
    FROM room r
    LEFT JOIN voters v ON r.id = v.room_id
    GROUP BY r.id, r.name";

$room_stats_result = $conn->query($room_stats_query);
$overall_stats = $overall_result->fetch_assoc();

// Prepare hourly data for chart
$hourly_data = array_fill(0, 24, 0);
while ($row = $hourly_result->fetch_assoc()) {
    $hourly_data[$row['hour']] = (int)$row['count'];
}

// Add this block to prepare hourly line data by room
$hourly_line_data = [];
while ($row = $hourly_line_result->fetch_assoc()) {
    $room_id = $row['room_id'];
    if (!isset($hourly_line_data[$room_id])) {
        $hourly_line_data[$room_id] = [
            'name' => $row['room_name'],
            'hours' => array_fill(0, 24, 0) // Initialize all hours with 0
        ];
    }
    $hourly_line_data[$room_id]['hours'][$row['hour']] = (int)$row['attendance_count'];
}

// Prepare daily data for chart
$daily_data = [];
$daily_labels = [];
while ($row = $daily_result->fetch_assoc()) {
    array_unshift($daily_data, (int)$row['count']);
    array_unshift($daily_labels, date('d/m', strtotime($row['date'])));
}

// --- ADD THIS BLOCK TO DEFINE THE VARIABLES USED IN THE DASHBOARD CARDS ---

// Summary variables
$total_voters = isset($overall_stats['total_voters']) ? (int)$overall_stats['total_voters'] : 0;
$attended = isset($overall_stats['total_attended']) ? (int)$overall_stats['total_attended'] : 0;
$absent = isset($overall_stats['total_absent']) ? (int)$overall_stats['total_absent'] : 0;

// Pie chart percentages
$attended_percent = $total_voters > 0 ? round(($attended / $total_voters) * 100, 1) : 0;
$absent_percent = 100 - $attended_percent;

// Hadir vs Tak Hadir
$hadir_vs_tak_hadir = [
    'Hadir' => $attended,
    'Belum Hadir' => $absent
];

// Room/PDM bar chart and highest attended room
$room_names = [];
$room_attended = [];
$room_highest = '';
$room_highest_count = 0;

// Update this block to respect filter
if ($filter_room !== '') {
    // Only the selected room
    $room_stats_query_chart = "SELECT 
        r.name,
        SUM(CASE WHEN v.attended = 1 THEN 1 ELSE 0 END) as attended_voters
        FROM room r
        LEFT JOIN voters v ON r.id = v.room_id
        WHERE r.id = '".intval($filter_room)."'
        GROUP BY r.id, r.name";
} else {
    // All rooms
    $room_stats_query_chart = "SELECT 
        r.name,
        SUM(CASE WHEN v.attended = 1 THEN 1 ELSE 0 END) as attended_voters
        FROM room r
        LEFT JOIN voters v ON r.id = v.room_id
        GROUP BY r.id, r.name";
}
$room_stats_result2 = $conn->query($room_stats_query_chart);
while ($room = $room_stats_result2->fetch_assoc()) {
    $room_names[] = $room['name'];
    $room_attended[] = (int)$room['attended_voters'];
    if ($room['attended_voters'] > $room_highest_count) {
        $room_highest_count = $room['attended_voters'];
        $room_highest = $room['name'];
    }
}

// Peak hour
$peak_hour = '-';
$peak_count = 0;
foreach ($hourly_data as $h => $count) {
    if ($count > $peak_count) {
        $peak_count = $count;
        $peak_hour = sprintf('%02d:00', $h);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Add Google Fonts for Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Link to external CSS file -->
    <link rel="stylesheet" href="assets/css/admindashboard.css">
</head>
<body>
    <?php include 'adminsidebar.php'; ?>

    <div class="dashboard-container">
        <h1>Admin Dashboard</h1>

        <!-- FILTER FORM -->
        <form method="get" style="margin-bottom:10px;display:flex;gap:10px;align-items:center;">
            <label for="room">Saluran:</label>
            <select name="room" id="room" onchange="this.form.submit()" style="padding:2px 6px;">
                <option value="">Semua Saluran</option>
                <?php while($r = $rooms_result->fetch_assoc()): ?>
                    <option value="<?php echo $r['id']; ?>" <?php if($filter_room == $r['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($r['name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <!--
            <label for="date">Tarikh:</label>
            <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($filter_date); ?>" onchange="this.form.submit()" style="padding:2px 6px;">
            -->
            <a href="admindashboard.php" style="margin-left:10px;color:#007bff;text-decoration:none;font-size:0.95em;">Reset</a>
        </form>

        <!-- Power BI Style Summary Cards -->
        <div class="powerbi-cards">
            <div class="powerbi-card">
                <span class="icon"><i class="fa fa-users"></i></span>
                <div class="label">Jumlah Pengundi Berdaftar</div>
                <div class="big"><?php echo $total_voters; ?></div>
            </div>
            <div class="powerbi-card">
                <span class="icon"><i class="fa fa-user-check"></i></span>
                <div class="label">Jumlah Pengundi Hadir</div>
                <div class="big" style="color:#28a745"><?php echo $attended; ?></div>
            </div>
            <div class="powerbi-card">
                <span class="icon"><i class="fa fa-user-times"></i></span>
                <div class="label">Jumlah Belum Hadir</div>
                <div class="big" style="color:#e74c3c"><?php echo $absent; ?></div>
            </div>
            <div class="powerbi-card">
                <span class="icon"><i class="fa fa-door-open"></i></span>
                <div class="label">Saluran Paling Ramai Hadir</div>
                <div class="big" style="color:#f39c12"><?php echo htmlspecialchars($room_highest); ?></div>
                <div style="font-size:0.95em;color:#666;">(<?php echo $room_highest_count; ?> hadir)</div>
            </div>
            <div class="powerbi-card">
                <span class="icon"><i class="fa fa-clock"></i></span>
                <div class="label">Waktu Paling Ramai Hadir</div>
                <div class="big" style="color:#007bff"><?php echo $peak_hour; ?></div>
                <div style="font-size:0.95em;color:#666;">(<?php echo $peak_count; ?> hadir)</div>
            </div>
            <!-- ADD THIS CARD -->
            <div class="powerbi-card">
                <span class="icon"><i class="fa fa-bell"></i></span>
                <div class="label">Peringatan Kehadiran Ditekan</div>
                <div class="big" style="color:#ff9800"><?php echo $reminder_count; ?></div>
                <div style="font-size:0.95em;color:#666;">
                    <?php
                        if ($filter_room !== '') {
                            echo "Untuk Saluran Ini";
                        } else {
                            echo "Jumlah Semua Saluran";
                        }
                    ?>
                </div>
            </div>
            <!-- END CARD -->
        </div>

        <!-- Charts Row -->
        <div class="charts-row">
            <div class="chart-container">
                <div class="chart-title">Pengundi Hadir vs Belum Hadir</div>
                <canvas id="hadirBarChart" height="90"></canvas>
            </div>
            <div class="chart-container pie-chart-container">
                <div class="chart-title">Peratusan Kehadiran Pengundi</div>
                <canvas id="pieChart" height="90"></canvas>
            </div>
            <div class="chart-container">
                <div class="chart-title">Kehadiran Mengikut Saluran</div>
                <canvas id="roomBarChart" height="90"></canvas>
            </div>
        </div>

        <!-- Add new chart row for hourly attendance trends -->
        <div class="charts-row" style="grid-template-columns: 1fr;">
        <div class="chart-container line-chart-container">
                <div class="chart-title">Trend Kehadiran</div>
                <canvas id="hourlyAttendanceChart"></canvas>
            </div>
        </div>
                
        <!-- Enhanced Room Statistics -->
        <div class="room-stats">
            <h2>Statistik Terperinci</h2>
            <table>
                <thead>
                    <tr>
                        <th>Saluran</th>
                        <th>Jumlah Pengundi</th>
                        <th>Kehadiran</th>
                        <th>Progres</th>
                        <th>Aktiviti Pertama</th>
                        <th>Aktiviti Terakhir</th>
                        <!-- Removed Active Days column -->
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Adjust query for filter
                    if ($filter_room !== '') {
                        $filtered_room_stats_query = "SELECT 
                            r.id,
                            r.name,
                            COUNT(v.id) as total_voters,
                            SUM(CASE WHEN v.attended = 1 THEN 1 ELSE 0 END) as attended_voters,
                            MAX(v.updated_at) as last_attendance,
                            MIN(CASE WHEN v.attended = 1 THEN v.updated_at END) as first_attendance
                            FROM room r
                            LEFT JOIN voters v ON r.id = v.room_id
                            WHERE r.id = '".intval($filter_room)."'
                            GROUP BY r.id, r.name";
                        $room_stats_result_display = $conn->query($filtered_room_stats_query);
                    } else {
                        $room_stats_result_display = $conn->query($room_stats_query);
                    }
                    while ($room = $room_stats_result_display->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($room['name']); ?></strong>
                            </td>
                            <td><?php echo $room['total_voters']; ?></td>
                            <td><?php echo $room['attended_voters']; ?></td>
                            <td>
                                <?php 
                                $attendance_rate = $room['total_voters'] > 0 
                                    ? round(($room['attended_voters'] / $room['total_voters']) * 100, 1) 
                                    : 0;
                                ?>
                                <div><?php echo $attendance_rate; ?>%</div>
                                <div class="progress-bar">
                                    <div class="progress" style="width: <?php echo $attendance_rate; ?>%"></div>
                                </div>
                            </td>
                            <td><?php echo $room['first_attendance'] ? date('d/m/Y H:i', strtotime($room['first_attendance'])) : '-'; ?></td>
                            <td><?php echo $room['last_attendance'] ? date('d/m/Y H:i', strtotime($room['last_attendance'])) : '-'; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    <script>
        // Hadir vs Tak Hadir Bar Chart
        new Chart(document.getElementById('hadirBarChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($hadir_vs_tak_hadir)); ?>,
                datasets: [{
                    label: 'Bilangan Pengundi',
                    data: <?php echo json_encode(array_values($hadir_vs_tak_hadir)); ?>,
                    backgroundColor: ['#28a745', '#ffc107']
                }]
            },
            options: {
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, stepSize: 1 } }
            }
        });

        // Pie Chart Kehadiran
        new Chart(document.getElementById('pieChart'), {
            type: 'pie',
            data: {
                labels: ['Hadir', 'Belum Hadir'],
                datasets: [{
                    data: [<?php echo $attended_percent; ?>, <?php echo $absent_percent; ?>],
                    backgroundColor: ['#007bff', '#e74c3c']
                }]
            },
            options: {
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed + '%';
                            }
                        }
                    }
                }
            }
        });

        // Bar Chart by Room/PDM
        new Chart(document.getElementById('roomBarChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($room_names); ?>,
                datasets: [{
                    label: 'Pengundi Hadir',
                    data: <?php echo json_encode($room_attended); ?>,
                    backgroundColor: '#17a2b8'
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, stepSize: 1 } }
            }
        });

        // Hourly Chart
        new Chart(document.getElementById('hourlyChart'), {
            type: 'bar',
            data: {
                labels: Array.from({length: 24}, (_, i) => i + ':00'),
                datasets: [{
                    label: 'Attendance Count',
                    data: <?php echo json_encode(array_values($hourly_data)); ?>,
                    backgroundColor: '#007bff'
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Daily Chart
        new Chart(document.getElementById('dailyChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($daily_labels); ?>,
                datasets: [{
                    label: 'Daily Attendance',
                    data: <?php echo json_encode($daily_data); ?>,
                    borderColor: '#28a745',
                    tension: 0.1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>

<script>
    // Initialize hourly attendance chart (from userdashboard.php)
    const hourlyLineData = <?php echo json_encode($hourly_line_data); ?>;
    
    // Modified to only show 8am to 6pm (8:00 to 18:00)
    const lineHours = Array.from({length: 11}, (_, i) => (i + 8).toString().padStart(2, '0') + ':00');
    
    const lineDatasets = Object.values(hourlyLineData).map((room, index) => ({
        label: room.name,
        // Only use data from hours 8-18 (8am to 6pm)
        data: room.hours.slice(8, 19),
        borderColor: getLineColor(index),
        tension: 0.4,
        fill: false
    }));

    function getLineColor(index) {
        const colors = [
            '#007bff', '#28a745', '#dc3545', '#ffc107', 
            '#17a2b8', '#6610f2', '#fd7e14', '#20c997'
        ];
        return colors[index % colors.length];
    }

    new Chart(document.getElementById('hourlyAttendanceChart'), {
        type: 'line',
        data: {
            labels: lineHours,
            datasets: lineDatasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        font: {
                            size: 10
                        }
                    }
                },
                title: {
                    display: false
                }
            },
            layout: {
                padding: 5 // Reduce from 10
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Bilangan Pengundi',
                        font: {
                            size: 10
                        }
                    },
                    ticks: {
                        font: {
                            size: 9
                        }
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Masa (8pg-6ptg)',
                        font: {
                            size: 10
                        }
                    },
                    ticks: {
                        font: {
                            size: 9
                        }
                    }
                }
            }
        }
    });
</script>
</body>
</html>