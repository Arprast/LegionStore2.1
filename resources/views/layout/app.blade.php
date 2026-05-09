<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legion Storeid</title>

    <!-- Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Konfigurasi tema jika diperlukan
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        legion: '#1a202c', // Contoh warna kustom
                    }
                }
            }
        }
    </script>

    <!-- Swiper JS (CSS) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
</head>
<body class="bg-gray-900 text-white">

    <!-- Navbar & Menu (Paste kode lama Anda yang sudah berfungsi di sini) -->
    <nav>benar</nav>

    <!-- Konten Dinamis -->
    <main>
        @yield('content')
    </main>

    <!-- Swiper JS (Script) -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <!-- Inisialisasi Carousel/Slider (Misal: untuk Rating/Review slider) -->
    <script>
        const swiper = new Swiper('.mySwiper', {
            loop: true,
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
        });
    </script>
</body>
</html>