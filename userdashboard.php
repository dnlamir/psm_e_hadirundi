<?php
session_start();
include 'includes/room.php'; // Updated path to room.php

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Get statistics for all rooms
$query = "SELECT 
            r.id as room_id,
            r.name as room_name,
            COUNT(v.id) as total_voters,
            SUM(CASE WHEN v.attended = 1 THEN 1 ELSE 0 END) as attended_voters
          FROM room r
          LEFT JOIN voters v ON r.id = v.room_id
          GROUP BY r.id, r.name";

// Get hourly attendance data
$hourly_query = "SELECT 
                    r.id as room_id,
                    r.name as room_name,
                    HOUR(v.updated_at) as hour,
                    COUNT(*) as attendance_count
                FROM room r
                LEFT JOIN voters v ON r.id = v.room_id
                WHERE v.attended = 1 
                    AND v.updated_at IS NOT NULL
                GROUP BY r.id, r.name, HOUR(v.updated_at)
                ORDER BY r.id, HOUR(v.updated_at)";

$result = $conn->query($query);
$hourly_result = $conn->query($hourly_query);

// Prepare hourly data for charts
$hourly_data = [];
while ($row = $hourly_result->fetch_assoc()) {
    $room_id = $row['room_id'];
    if (!isset($hourly_data[$room_id])) {
        $hourly_data[$room_id] = [
            'name' => $row['room_name'],
            'hours' => array_fill(0, 24, 0) // Initialize all hours with 0
        ];
    }
    $hourly_data[$room_id]['hours'][$row['hour']] = (int)$row['attendance_count'];
}

// Calculate total attendance stats
$total_voters = 0;
$total_attended = 0;
$room_with_highest_attendance = '';
$highest_attendance = 0;

if ($result->num_rows > 0) {
    // Store the result for later use
    $rooms_data = [];
    $result->data_seek(0);
    while ($row = $result->fetch_assoc()) {
        $rooms_data[] = $row;
        $total_voters += $row['total_voters'];
        $total_attended += $row['attended_voters'];
        
        if ($row['attended_voters'] > $highest_attendance) {
            $highest_attendance = $row['attended_voters'];
            $room_with_highest_attendance = $row['room_name'];
        }
    }
}

// Calculate attendance percentage
$attendance_percentage = $total_voters > 0 ? round(($total_attended / $total_voters) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Papan Pemuka Kehadiran</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/userdashboard.css">
</head>
<body>
    <?php include 'usersidebar.php'; ?>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Papan Pemuka Kehadiran</h1>
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
        
        <!-- Power BI Style Summary Cards -->
        <div class="powerbi-cards">
            <div class="powerbi-card">
                <span class="icon"><i class="fas fa-users"></i></span>
                <div class="label">Jumlah Pengundi</div>
                <div class="big"><?php echo number_format($total_voters); ?></div>
            </div>
            <div class="powerbi-card">
                <span class="icon"><i class="fas fa-user-check"></i></span>
                <div class="label">Hadir</div>
                <div class="big"><?php echo number_format($total_attended); ?></div>
            </div>
            <div class="powerbi-card">
                <span class="icon"><i class="fas fa-user-times"></i></span>
                <div class="label">Tidak Hadir</div>
                <div class="big"><?php echo number_format($total_voters - $total_attended); ?></div>
            </div>
            <div class="powerbi-card">
                <span class="icon"><i class="fas fa-percentage"></i></span>
                <div class="label">Kadar Kehadiran</div>
                <div class="big"><?php echo $attendance_percentage; ?>%</div>
            </div>
            <div class="powerbi-card">
                <span class="icon"><i class="fas fa-award"></i></span>
                <div class="label">Kehadiran Tertinggi</div>
                <div class="big"><?php echo htmlspecialchars($room_with_highest_attendance); ?></div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="section-title">
            <i class="fas fa-chart-line"></i> Analisis Kehadiran
        </div>
        
        <div class="charts-row">
            <div class="chart-container">
                <div class="chart-title">Trend Kehadiran Mengikut Jam</div>
                <canvas id="attendanceChart"></canvas>
            </div>
            <div class="chart-container">
                <div class="chart-title">Kehadiran Mengikut Saluran</div>
                <canvas id="roomBarChart"></canvas>
            </div>
        </div>
        
        <!-- Room Stats Section -->
        <div class="section-title">
            <i class="fas fa-door-open"></i> Statistik Saluran
        </div>
        
        <div class="room-stats-grid">
            <?php if (isset($rooms_data) && count($rooms_data) > 0): ?>
                <?php foreach ($rooms_data as $row): 
                    $total = $row['total_voters'];
                    $attended = $row['attended_voters'];
                    $percentage = $total > 0 ? round(($attended / $total) * 100, 1) : 0;
                ?>
                    <div class="room-card">
                        <div class="room-name"><?php echo htmlspecialchars($row['room_name']); ?></div>
                        <div class="stats-info">
                            <div class="stat-item">
                                <div class="stat-label">Jumlah</div>
                                <div class="stat-value"><?php echo number_format($total); ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Hadir</div>
                                <div class="stat-value"><?php echo number_format($attended); ?></div>
                            </div>
                        </div>
                        <div class="progress-container">
                            <div class="progress-bar">
                                <div class="progress" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                            <div class="percentage"><?php echo $percentage; ?>% Kehadiran</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="room-card" style="grid-column: 1 / -1; text-align: center; padding: 30px;">
                    <i class="fas fa-info-circle" style="font-size: 2em; color: var(--info-color); margin-bottom: 15px;"></i>
                    <p>Tiada saluran dijumpai dalam sistem.</p>
                </div>
            <?php endif; ?>
        </div>

        <a href="userhome.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Kembali ke Laman Utama
        </a>
    </div>
    
    <!-- Chart initialization scripts -->
    <script>
        // Chart.js global defaults
        Chart.defaults.font.family = "'Segoe UI', 'Helvetica Neue', 'Helvetica', 'Arial', sans-serif";
        Chart.defaults.font.size = 12;
        Chart.defaults.color = '#64748b';
        
        // Hourly attendance chart
        const hourlyData = <?php echo json_encode($hourly_data); ?>;
        
        // Modified to only show 8am to 6pm (8:00 to 18:00)
        const hours = Array.from({length: 11}, (_, i) => (i + 8).toString().padStart(2, '0') + ':00');
        
        const datasets = Object.values(hourlyData).map((room, index) => ({
            label: room.name,
            // Only use data from hours 8-18 (8am to 6pm)
            data: room.hours.slice(8, 19),
            borderColor: getColor(index),
            backgroundColor: getColorWithOpacity(index, 0.1),
            borderWidth: 3,
            tension: 0.4,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: 'white',
            pointHoverBackgroundColor: getColor(index),
            pointBorderWidth: 2,
            pointBorderColor: getColor(index),
            fill: true
        }));

        function getColor(index) {
            const colors = [
                '#2563eb', '#10b981', '#ef4444', '#f59e0b', 
                '#0ea5e9', '#8b5cf6', '#ec4899', '#14b8a6'
            ];
            return colors[index % colors.length];
        }
        
        function getColorWithOpacity(index, opacity) {
            const color = getColor(index);
            // Convert hex to rgba
            const r = parseInt(color.slice(1, 3), 16);
            const g = parseInt(color.slice(3, 5), 16);
            const b = parseInt(color.slice(5, 7), 16);
            return `rgba(${r}, ${g}, ${b}, ${opacity})`;
        }

        new Chart(document.getElementById('attendanceChart'), {
            type: 'line',
            data: {
                labels: hours,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(255, 255, 255, 0.9)',
                        titleColor: '#1e293b',
                        bodyColor: '#334155',
                        borderColor: '#e2e8f0',
                        borderWidth: 1,
                        padding: 12,
                        boxPadding: 6,
                        usePointStyle: true,
                        callbacks: {
                            label: function(context) {
                                return `${context.dataset.label}: ${context.raw} pengundi`;
                            }
                        }
                    }
                },
                layout: {
                    padding: 10
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#e2e8f0',
                            drawBorder: false
                        },
                        ticks: {
                            padding: 10
                        },
                        title: {
                            display: true,
                            text: 'Bilangan Pengundi',
                            font: {
                                size: 13,
                                weight: 'bold'
                            },
                            padding: {top: 10, bottom: 10}
                        }
                    },
                    x: {
                        grid: {
                            color: '#e2e8f0',
                            drawBorder: false
                        },
                        ticks: {
                            padding: 10
                        },
                        title: {
                            display: true,
                            text: 'Masa (8pg-6ptg)',
                            font: {
                                size: 13,
                                weight: 'bold'
                            },
                            padding: {top: 10, bottom: 0}
                        }
                    }
                }
            }
        });

        // Room attendance bar chart
        const roomData = <?php echo json_encode(isset($rooms_data) ? $rooms_data : []); ?>;
        const roomNames = roomData.map(room => room.room_name);
        const roomAttendance = roomData.map(room => room.attended_voters);
        const roomTotals = roomData.map(room => room.total_voters);
        
        new Chart(document.getElementById('roomBarChart'), {
            type: 'bar',
            data: {
                labels: roomNames,
                datasets: [
                    {
                        label: 'Hadir',
                        data: roomAttendance,
                        backgroundColor: '#0ea5e9',
                        borderRadius: 6,
                        borderWidth: 0
                    },
                    {
                        label: 'Jumlah Berdaftar',
                        data: roomTotals,
                        backgroundColor: 'rgba(14, 165, 233, 0.2)',
                        borderRadius: 6,
                        borderWidth: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(255, 255, 255, 0.9)',
                        titleColor: '#1e293b',
                        bodyColor: '#334155',
                        borderColor: '#e2e8f0',
                        borderWidth: 1,
                        padding: 12,
                        boxPadding: 6,
                        usePointStyle: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#e2e8f0',
                            drawBorder: false
                        },
                        ticks: {
                            padding: 10
                        },
                        title: {
                            display: true,
                            text: 'Bilangan Pengundi',
                            font: {
                                size: 13,
                                weight: 'bold'
                            },
                            padding: {top: 10, bottom: 10}
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            padding: 10
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>