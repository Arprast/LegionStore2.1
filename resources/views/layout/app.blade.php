<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legion Store</title>

    <!-- 1. Deklarasi Variabel CSS Root Anda -->
    <style>
        :root {
            --bg-main: #0B1220;
            --bg-card: #111827;
            --bg-navbar: #0E1627;
            --border-soft: #1F2937;
            --gold: #D4AF37;
            --gold-hover: #FACC15;
            --cyan: #00E5FF;
            --text-main: #E5E7EB;
            --text-muted: #9CA3AF;
            --border-gold: rgba(212, 175, 55, 0.2);
            --bg-body: radial-gradient(circle at top, #101A2E, #070B14);
            --navbar-hover: rgba(212, 175, 55, 0.5);
            --custom-border: #3a79db;
        }

        /* Set background body bawaan menggunakan gradient Anda */
        body {
            background: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
        }
    </style>

    <!-- 2. Panggil Tailwind CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- 3. Hubungkan Variabel CSS ke Class Tailwind -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        main: 'var(--bg-main)',
                        card: 'var(--bg-card)',
                        navbar: 'var(--bg-navbar)',
                        'border-soft': 'var(--border-soft)',
                        gold: 'var(--gold)',
                        'gold-hover': 'var(--gold-hover)',
                        cyan: 'var(--cyan)',
                        'text-main': 'var(--text-main)',
                        muted: 'var(--text-muted)',
                        'border-gold': 'var(--border-gold)',
                        'nav-hover': 'var(--navbar-hover)',
                        'custom-border': 'var(--custom-border)'
                    }
                }
            }
        }
    </script>
</head>
<body class="antialiased">

    <!-- Area Navbar -->
    <header class="bg-navbar border-b border-border-gold">
        <!-- Nanti kodingan Navbar di paste di sini -->
    </header>

    <!-- Area Konten -->
    <main>
        @yield('content')
    </main>

</body>
</html>