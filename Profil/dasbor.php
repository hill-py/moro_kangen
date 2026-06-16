<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moro Kangen - Selamat Datang</title>
    <style>
        /* Reset CSS Dasar */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f3f4f6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }

        /* HEADER: Tombol Login Admin di Kanan Atas */
        header {
            width: 100%;
            padding: 20px 40px;
            display: flex;
            justify-content: flex-end;
            position: absolute;
            top: 0;
            left: 0;
        }

        .btn-admin {
            background-color: #ffffff;
            color: #333333;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .btn-admin:hover {
            color: #e1931a;
            border-color: #e1931a;
        }

        /* UTAMA: Konten di Tengah Halaman */
        main {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            text-align: center;
            margin-top: 60px; /* Jarak agar tidak tertabrak header */
        }

        /* Branding Judul */
        .branding {
            margin-bottom: 30px;
        }

        .branding h1 {
            font-size: 42px;
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .branding p {
            font-size: 16px;
            color: #6b7280;
            letter-spacing: 0.5px;
        }

        /* Kotak Card Utama */
        .card-pesanan {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            border: 1px solid #f1f5f9;
            max-width: 400px;
            width: 100%;
            transition: transform 0.3s ease;
        }

        .card-pesanan:hover {
            transform: translateY(-5px);
        }

        .card-pesanan p {
            color: #4b5563;
            font-size: 15px;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        /* Tombol Pesanan Utama (Warna Oranye menyesuaikan gambar kamu) */
        .btn-pesanan {
            display: block;
            background-color: #e1931a;
            color: #ffffff;
            text-decoration: none;
            padding: 15px 25px;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            box-shadow: 0 4px 6px rgba(225, 147, 26, 0.2);
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
        }

        .btn-pesanan:hover {
            background-color: #c77f13;
            box-shadow: 0 6px 12px rgba(225, 147, 26, 0.3);
        }

        /* FOOTER */
        footer {
            width: 100%;
            text-align: center;
            padding: 15px;
            font-size: 12px;
            color: #9ca3af;
        }
    </style>
</head>
<body>

    <header>
        <a href="auth/login.php" class="btn-admin">Login Admin</a>
    </header>

    <main>
        <div class="branding">
            <h1>Moro Kangen</h1>
            <p>Mie Ayam Bakso - Karanggede, Boyolali</p>
        </div>

        <div class="card-pesanan">
            <p>Selamat Datang! Silakan klik tombol di bawah ini untuk memulai pesanan makanan Anda.</p>
            <a href="pelanggan/index.php" class="btn-pesanan">Buat Pesanan</a>
        </div>
    </main>

    <footer>
        &copy; <?php echo date('Y'); ?> Moro Kangen. All rights reserved.
    </footer>

</body>
</html>
