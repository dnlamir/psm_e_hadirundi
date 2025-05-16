<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-HadirUndi - Login</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .error-message {
            color: red;
            margin-top: 10px;
            text-align: center;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-box">
            <h1 class="logo">E-HadirUndi</h1>
            <form id="loginForm" action="index_login.php" method="post">
                <div class="input-group">
                    <label for="username">
                        <i class="fas fa-user"></i> Nama Pengguna atau ID
                    </label>
                    <input type="text" id="username" name="username" required 
                           placeholder="Masukkan nama pengguna anda atau ID">
                </div>

                <div class="input-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Kata Laluan
                    </label>
                    <input type="password" id="password" name="password" required
                           placeholder="Masukkan kata laluan anda">
                </div>

                <div class="error-message" id="errorMessage"></div>

                <button type="submit">
                    <i class="fas fa-sign-in-alt"></i> Log Masuk
                </button>

                <a href="#" class="forgot-password">
                    <i class="fas fa-key"></i> Lupa Kata Laluan?
                </a>
            </form>
        </div>
    </div>

    <footer>
        <p>© 2024 E-HadirUndi. Semua hak terpelihara.</p>
        <div class="footer-links">
            <a href="privacy-policy.php"><i class="fas fa-shield-alt"></i> Dasar Privasi</a>
            <a href="terms-of-service.php"><i class="fas fa-file-contract"></i> Syarat Perkhidmatan</a>
            <a href="contact-support.php"><i class="fas fa-headset"></i> Hubungi Sokongan</a>
        </div>
    </footer>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('index_login.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    const errorMessage = document.getElementById('errorMessage');
                    errorMessage.textContent = data.error;
                    errorMessage.style.display = 'block';
                } else if (data.redirect) {
                    window.location.href = data.redirect;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const errorMessage = document.getElementById('errorMessage');
                errorMessage.textContent = 'An error occurred during login.';
                errorMessage.style.display = 'block';
            });
        });
    </script>
</body>
</html>
