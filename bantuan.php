
<?php
session_start();
include 'includes/room.php'; // Updated path to room.php

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Fetch rooms from the database
$query = "SELECT * FROM room";
$result = $conn->query($query);

// Check for database query errors
if (!$result) {
    die("Database query failed: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bantuan - E-HadirUndi</title>
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/bantuan.css">
</head>
<body>
    <div class="content-wrapper">
        <div class="container">
            <div class="page-header">
                <h1 class="mb-4">Pusat Bantuan</h1>
                <p class="lead text-muted">Selamat datang ke pusat bantuan E-HadirUndi. Kami sedia membantu anda menggunakan sistem dengan lebih efektif.</p>
                
                <div class="search-container">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" id="helpSearch" class="form-control search-input border-start-0" placeholder="Cari bantuan di sini...">
                    </div>
                </div>
                
                <div class="quick-links">
                    <a href="#panduan" class="quick-link">
                        <i class="fas fa-book"></i>
                        <div>Panduan Pengguna</div>
                    </a>
                    <a href="#faq" class="quick-link">
                        <i class="fas fa-question-circle"></i>
                        <div>Soalan Lazim</div>
                    </a>
                    <a href="#hubungi" class="quick-link">
                        <i class="fas fa-headset"></i>
                        <div>Hubungi Kami</div>
                    </a>
                    <a href="#video" class="quick-link">
                        <i class="fas fa-video"></i>
                        <div>Video Panduan</div>
                    </a>
                </div>
            </div>
            
            <div class="help-section" id="panduan">
                <h2><i class="fas fa-book me-2"></i>Panduan Pengguna</h2>
                
                <div class="help-item">
                    <h3><i class="fas fa-home me-2"></i> Dashboard</h3>
                    <p>Dashboard menunjukkan ringkasan maklumat sistem E-HadirUndi. Di sini anda boleh melihat statistik kehadiran pengundi, jumlah bilik mengundi, dan maklumat penting lain. Paparan ini memberikan gambaran keseluruhan tentang status operasi semasa.</p>
                </div>
                
                <div class="help-item">
                    <h3><i class="fas fa-user-check me-2"></i> Kehadiran</h3>
                    <p>Menu Kehadiran membolehkan anda menguruskan kehadiran pengundi mengikut saluran. Pilih saluran dari submenu untuk melihat dan mengemaskini status kehadiran pengundi.</p>
                    <div class="card bg-light p-3 mt-3">
                        <ul class="mb-0">
                            <li>Klik pada nama saluran untuk melihat senarai pengundi</li>
                            <li>Tandakan kehadiran dengan mengklik butang "Hadir"</li>
                            <li>Anda boleh mencari pengundi menggunakan kotak carian</li>
                            <li>Lihat statistik kehadiran secara masa nyata</li>
                        </ul>
                    </div>
                </div>
                
                <div class="help-item">
                    <h3><i class="fas fa-file-alt me-2"></i> Laporan</h3>
                    <p>Menu Laporan membolehkan anda menjana dan melihat laporan kehadiran pengundi. Anda boleh melihat statistik kehadiran mengikut saluran. Laporan boleh dimuat turun dalam format PDF untuk analisis lanjut.</p>
                </div>
                
                <div class="help-item">
                    <h3><i class="fas fa-users me-2"></i> Pengundi</h3>
                    <p>Menu Pengundi membolehkan anda menguruskan senarai pengundi. Anda boleh mencari maklumat tentang pengundi contohnya pengundi didaftar di saluran berapa. Bahagian ini digunakan untuk petugas di luar pagar untuk memberi info lanjut kepada pengundi yang ingin membuang undi.</p>
                </div>
            </div>
            
            <div class="help-section" id="faq">
                <h2><i class="fas fa-question-circle me-2"></i>Soalan Lazim (FAQ)</h2>
                
                <div class="faq-item">
                    <div class="faq-question"><i class="fas fa-question-circle me-2 text-primary"></i>Bagaimana cara menandakan kehadiran pengundi?</div>
                    <div class="faq-answer">Pergi ke menu Kehadiran, pilih saluran yang berkaitan, cari nama pengundi dan klik butang "Hadir" di sebelah nama pengundi tersebut. Status kehadiran akan dikemaskini secara automatik dalam sistem.</div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question"><i class="fas fa-question-circle me-2 text-primary"></i>Bagaimana cara menjana laporan kehadiran?</div>
                    <div class="faq-answer">Pergi ke menu Laporan, pilih jenis laporan yang dikehendaki, tetapkan kehadiran saluran berapa dan klik butang "Jana Laporan". Anda boleh memilih untuk melihat laporan secara dalam talian atau memuat turun dalam format PDF.</div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question"><i class="fas fa-question-circle me-2 text-primary"></i>Bagaimana cara menambah pengundi baharu?</div>
                    <div class="faq-answer">Fungsi ini terdapat pada admin sahaja. Petugas tidak mempunyai akses untuk menambah dan membuang senarai pengundi.</div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question"><i class="fas fa-question-circle me-2 text-primary"></i>Apa yang perlu dilakukan jika terdapat ralat sistem?</div>
                    <div class="faq-answer">Sila hubungi pentadbir sistem dengan segera menggunakan maklumat hubungan di bawah. Pastikan anda menyediakan maklumat terperinci tentang ralat yang dihadapi, termasuk mesej ralat dan langkah-langkah yang membawa kepada ralat tersebut.</div>
                </div>
            </div>
            
            <div class="help-section" id="video">
                <h2><i class="fas fa-video me-2"></i>Video Panduan</h2>
                <p class="mb-4">Tonton video panduan berikut untuk memahami cara menggunakan sistem E-HadirUndi dengan lebih baik:</p>
                
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-play-circle me-2 text-primary"></i>Pengenalan Sistem</h5>
                                <p class="card-text">Video pengenalan kepada sistem E-HadirUndi dan fungsi-fungsi utamanya.</p>
                                <a href="videos/pengenalan.mp4" class="btn btn-outline-primary">Tonton Video</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-play-circle me-2 text-primary"></i>Menguruskan Kehadiran</h5>
                                <p class="card-text">Panduan langkah demi langkah untuk menguruskan kehadiran pengundi.</p>
                                <a href="videos/kehadiran.mp4" class="btn btn-outline-primary">Tonton Video</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-play-circle me-2 text-primary"></i>Menjana Laporan</h5>
                                <p class="card-text">Cara menjana dan menganalisis laporan kehadiran pengundi.</p>
                                <a href="videos/laporan.mp4" class="btn btn-outline-primary">Tonton Video</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-play-circle me-2 text-primary"></i>Penggunaan Dashboard</h5>
                                <p class="card-text">Panduan melihat statistik kehadiran mengikut saluran</p>
                                <a href="videos/dashboard.mp4" class="btn btn-outline-primary">Tonton Video</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="help-section" id="hubungi">
                <h2><i class="fas fa-headset me-2"></i>Hubungi Kami</h2>
                <p class="mb-4">Jika anda memerlukan bantuan tambahan, sila hubungi kami melalui saluran berikut:</p>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="contact-info">
                            <div class="contact-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <div class="text-muted">Email:</div>
                                <div><strong>bantuan@ehadirundi.com</strong></div>
                                <small class="text-muted">Waktu respons: 24 jam</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="contact-info">
                            <div class="contact-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div>
                                <div class="text-muted">Telefon:</div>
                                <div><strong>+60 3-8765 4321</strong></div>
                                <small class="text-muted">Isnin - Jumaat: 8:00 pagi - 5:00 petang</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Search functionality -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('helpSearch');
        const helpItems = document.querySelectorAll('.help-item, .faq-item');
        
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            
            helpItems.forEach(item => {
                const text = item.textContent.toLowerCase();
                if(text.includes(searchTerm)) {
                    item.style.display = 'block';
                    // Highlight the matching item
                    if(searchTerm.length > 2) {
                        item.style.borderLeftColor = '#3498db';
                        item.style.backgroundColor = '#f8f9fa';
                    } else {
                        item.style.borderLeftColor = '';
                        item.style.backgroundColor = '';
                    }
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
    </script>
    
    <?php include 'usersidebar.php'; ?>
</body>
</html>