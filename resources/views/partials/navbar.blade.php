<!-- ===============================
   NAVBAR MAIN
=============================== -->
<nav class="fixed top-0 left-0 w-full z-[9999] bg-gradient-to-b from-[#05080f]/85 via-[#0b1220]/75 to-[#0b1220]/65 backdrop-blur-md border-b border-white/10 shadow-[0_6px_20px_rgba(0,0,0,0.45)] transition-all duration-300 py-2.5">
    <div class="container mx-auto px-4 lg:px-8">
        <div class="flex items-center justify-between gap-3 bg-transparent pb-3 lg:pb-0">
            
            <!-- LOGO -->
            <a href="/" class="flex-shrink-0 flex items-center lg:mr-3">
                <img src="/img/logo2.png" alt="Logo" class="h-[28px] lg:h-[42px] w-auto object-contain transition-all duration-300 hover:scale-110 hover:drop-shadow-[0_0_10px_var(--gold)]">
            </a>

            <!-- SEARCH (DESKTOP) -->
            <div class="relative flex-1 max-w-[780px] mx-auto hidden lg:flex group">
                <input type="text" id="searchInputDesktop" placeholder="Cari game atau produk..." 
                       class="w-full py-[9px] pr-[18px] pl-[40px] rounded-[30px] border border-border-soft outline-none bg-gradient-to-br from-[#0F172A] to-main text-text-main text-sm transition-all duration-300 focus:border-gold focus:ring-2 focus:ring-gold/15 placeholder-muted">
                <i class="bi bi-search absolute left-[16px] top-1/2 -translate-y-1/2 text-[17px] text-cyan pointer-events-none"></i>
                
                <!-- Dropdown Search Desktop -->
                <div id="searchDropdownDesktop" class="hidden absolute top-[110%] left-0 w-full bg-white rounded-[10px] shadow-[0_6px_25px_rgba(0,0,0,0.15)] z-[9999] overflow-y-auto max-h-[320px] py-2"></div>
            </div>

            <!-- MENU / DROPDOWN (DESKTOP) -->
            <div class="hidden lg:flex items-center gap-8 flex-shrink-0">
                <a href="{{ url('/') }}" class="flex items-center gap-1 text-white hover:text-gold-hover transition-colors {{ request()->is('/') ? 'border-b-2 border-gold text-gold font-semibold drop-shadow-[0_0_10px_rgba(212,175,55,0.6)]' : '' }}">
                    <i class="bi bi-controller"></i> Beranda
                </a>

                <a href="{{ url('track-progress') }}" class="flex items-center gap-1 text-white hover:text-gold-hover transition-colors {{ request()->is('track-progress') ? 'border-b-2 border-gold text-gold font-semibold drop-shadow-[0_0_10px_rgba(212,175,55,0.6)]' : '' }}">
                    <i class="bi bi-receipt"></i> Cek Transaksi
                </a>

                @auth
                <!-- Profile Dropdown (Desktop) -->
                <div class="relative profile-menu">
                    <button type="button" class="flex items-center gap-2 text-white hover:text-gold-hover transition-colors focus:outline-none" id="btnProfileDesktop">
                        <i class="bi bi-person-circle text-xl"></i>
                        <span>{{ Auth::user()->name }}</span>
                    </button>
                    <!-- Dropdown Content -->
                    <div id="dropdownProfileDesktop" class="hidden absolute right-0 mt-2 min-w-[170px] bg-gradient-to-b from-[#0F172A] to-main border border-border-gold shadow-[0_14px_32px_rgba(0,0,0,0.65)] rounded-xl py-1.5 z-[100]">
                        <div class="px-4 pt-2 text-[13px] text-text-main">Telah masuk sebagai</div>
                        <div class="px-4 pb-2 text-[13px] font-semibold text-gold">{{ Auth::user()->name }}</div>
                        <div class="border-t border-border-gold/50 my-1"></div>
                        <a href="{{ url('profile-dashboard') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm text-text-main hover:bg-gold/15 hover:text-[#FFD86B] transition-colors"><i class="bi bi-grid text-[15px] text-gold"></i> Dashboard</a>
                        <a href="{{ url('transaction-history') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm text-text-main hover:bg-gold/15 hover:text-[#FFD86B] transition-colors"><i class="bi bi-receipt text-[15px] text-gold"></i> Riwayat Transaksi</a>
                        <a href="{{ url('profile') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm text-text-main hover:bg-gold/15 hover:text-[#FFD86B] transition-colors"><i class="bi bi-person text-[15px] text-gold"></i> Pengaturan Profil</a>
                        <div class="border-t border-border-gold/50 my-1"></div>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full text-left flex items-center gap-2 px-4 py-2.5 text-sm text-text-main hover:bg-gold/15 hover:text-[#FFD86B] transition-colors"><i class="bi bi-box-arrow-right text-[15px] text-gold"></i> Keluar</button>
                        </form>
                    </div>
                </div>
                @endauth

                @guest
                    <a href="{{ route('login') }}" class="flex items-center gap-1 text-white hover:text-gold-hover transition-colors">
                        <i class="bi bi-box-arrow-in-right"></i> Masuk
                    </a>
                @endguest
            </div>

            <!-- MOBILE ICONS & HAMBURGER -->
            <div class="flex lg:hidden justify-end items-center gap-4">
                <button type="button" id="btnToggleMobileSearch" class="text-[18px] text-gold border-none bg-transparent p-0 focus:outline-none">
                    <i class="bi bi-search"></i>
                </button>

                @auth
                <!-- Profile Dropdown (Mobile) -->
                <div class="relative profile-menu">
                    <button type="button" class="flex items-center gap-2 text-white focus:outline-none" id="btnProfileMobile">
                        <i class="bi bi-person-circle text-xl"></i>
                    </button>
                    <!-- Dropdown Content Mobile -->
                    <div id="dropdownProfileMobile" class="hidden absolute right-0 mt-2 min-w-[170px] bg-gradient-to-b from-[#0F172A] to-main border border-border-gold shadow-lg rounded-xl py-1.5 z-[100]">
                         <div class="px-4 pt-2 text-xs text-muted">Masuk sebagai</div>
                         <div class="px-4 pb-2 text-[13px] font-semibold text-gold">{{ Auth::user()->name }}</div>
                         <div class="border-t border-border-gold/50 my-1"></div>
                         <a href="{{ url('profile-dashboard') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-text-main hover:bg-gold/15"><i class="bi bi-grid text-gold"></i> Dashboard</a>
                         <div class="border-t border-border-gold/50 my-1"></div>
                         <form action="{{ route('logout') }}" method="POST">
                             @csrf
                             <button type="submit" class="w-full text-left flex items-center gap-2 px-4 py-2 text-sm text-red-500 hover:bg-red-500/10"><i class="bi bi-box-arrow-right"></i> Keluar</button>
                         </form>
                    </div>
                </div>
                @endauth

                <!-- Hamburger Button -->
                <button type="button" id="btnOpenDrawer" class="text-[26px] text-white border-none bg-transparent p-0 focus:outline-none">
                    <i class="bi bi-list"></i>
                </button>
            </div>
        </div>

        <!-- SEARCH (MOBILE COLLAPSE) -->
        <div id="mobileSearchContainer" class="hidden lg:hidden mt-3 pb-2 transition-all duration-300">
            <div class="relative w-full mx-auto">
                <input type="text" id="searchInputMobile" placeholder="Cari game atau produk..." 
                       class="w-full h-[40px] py-2 pr-[18px] pl-[40px] rounded-[30px] border border-border-soft outline-none bg-gradient-to-br from-[#0F172A] to-main text-text-main text-[13px] focus:border-gold">
                <i class="bi bi-search absolute left-[16px] top-1/2 -translate-y-1/2 text-[15px] text-cyan pointer-events-none"></i>
                <div id="searchDropdownMobile" class="hidden absolute top-[110%] left-0 w-full bg-white rounded-[10px] shadow-lg z-[9999] overflow-y-auto max-h-[320px] py-2"></div>
            </div>
        </div>
    </div>
</nav>

<!-- ===============================
   SIDE DRAWER MENU (MOBILE)
=============================== -->
<!-- Overlay -->
<div id="drawerOverlay" class="fixed inset-0 bg-black/80 backdrop-blur-[4px] z-[10000] opacity-0 invisible transition-all duration-300 lg:hidden"></div>

<!-- Drawer -->
<div id="sideDrawerMenu" class="fixed top-0 right-[-320px] w-[300px] h-[100dvh] bg-main border-l border-border-gold/20 z-[10001] flex flex-col transition-all duration-300 cubic-bezier(0.4,0,0.2,1) lg:hidden">
    
    <div class="p-[25px] flex justify-between items-center border-b border-white/5">
        <img src="/img/logo2.png" alt="Legion Store" class="h-[28px]">
        <button id="btnCloseDrawer" class="bg-transparent border-none text-white text-[24px] cursor-pointer">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div class="p-[20px] flex flex-col gap-[10px] flex-grow overflow-y-auto">
        <a href="{{ url('/') }}" class="flex items-center gap-3 p-[15px] rounded-lg transition-colors {{ request()->is('/') ? 'bg-gold/10 text-gold' : 'text-white hover:bg-gold/10 hover:text-gold' }}">
            <i class="bi bi-controller text-xl"></i> 
            <span>Beranda</span>
        </a>
        
        <a href="{{ url('track-progress') }}" class="flex items-center gap-3 p-[15px] rounded-lg transition-colors {{ request()->is('track-progress') ? 'bg-gold/10 text-gold' : 'text-white hover:bg-gold/10 hover:text-gold' }}">
            <i class="bi bi-receipt text-xl"></i> 
            <span>Cek Transaksi</span>
        </a>
    </div>

    <div class="mt-auto p-[20px] bg-black/20 flex flex-col items-center">
        @guest
        <div class="flex justify-center gap-3 mb-[15px] w-full max-w-[260px]">
            <a href="/register" class="flex-1 bg-gold hover:bg-gold-hover text-black text-center py-3 rounded-[10px] font-bold text-sm transition-colors">Daftar</a>
            <a href="{{ route('login') }}" class="flex-1 bg-transparent hover:bg-gold/10 text-gold border-[1.5px] border-gold text-center py-3 rounded-[10px] font-bold text-sm transition-colors">Masuk</a>
        </div>
        @endguest

        @auth
        <div class="w-full flex justify-center mb-2">
            <form action="{{ route('logout') }}" method="POST" class="w-full text-center">
                @csrf
                <button type="submit" class="w-full max-w-[260px] bg-red-500/10 hover:bg-red-500/20 text-red-500 border border-red-500 py-3 rounded-[10px] font-semibold text-center transition-colors cursor-pointer">
                    <i class="bi bi-box-arrow-right"></i> Keluar
                </button>
            </form>
        </div>
        @endauth
        
        <div class="text-center text-[11px] text-slate-500 mt-1 tracking-wide">
            &copy; Legion Store (2025)
        </div>
    </div>
</div>

<!-- ==============================================
   JAVASCRIPT: DRAWER, DROPDOWN & AJAX SEARCH
=============================================== -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // 1. Logika Toggle Vanilla JS (Pengganti Bootstrap)
    document.addEventListener('DOMContentLoaded', function () {
        // Toggle Mobile Search Bar
        const btnToggleSearch = document.getElementById('btnToggleMobileSearch');
        const mobileSearchContainer = document.getElementById('mobileSearchContainer');
        if(btnToggleSearch) {
            btnToggleSearch.addEventListener('click', () => {
                mobileSearchContainer.classList.toggle('hidden');
            });
        }

        // Toggle Profile Dropdown (Desktop)
        const btnProfileDesktop = document.getElementById('btnProfileDesktop');
        const dropdownProfileDesktop = document.getElementById('dropdownProfileDesktop');
        if(btnProfileDesktop) {
            btnProfileDesktop.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdownProfileDesktop.classList.toggle('hidden');
            });
        }

        // Toggle Profile Dropdown (Mobile)
        const btnProfileMobile = document.getElementById('btnProfileMobile');
        const dropdownProfileMobile = document.getElementById('dropdownProfileMobile');
        if(btnProfileMobile) {
            btnProfileMobile.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdownProfileMobile.classList.toggle('hidden');
            });
        }

        // Klik di luar dropdown untuk menutupnya
        document.addEventListener('click', () => {
            if(dropdownProfileDesktop) dropdownProfileDesktop.classList.add('hidden');
            if(dropdownProfileMobile) dropdownProfileMobile.classList.add('hidden');
        });

        // Toggle Side Drawer (Hamburger Menu)
        const btnOpenDrawer = document.getElementById('btnOpenDrawer');
        const btnCloseDrawer = document.getElementById('btnCloseDrawer');
        const sideDrawerMenu = document.getElementById('sideDrawerMenu');
        const drawerOverlay = document.getElementById('drawerOverlay');

        function openDrawer() {
            sideDrawerMenu.style.right = '0';
            drawerOverlay.classList.remove('opacity-0', 'invisible');
            drawerOverlay.classList.add('opacity-100', 'visible');
        }

        function closeDrawer() {
            sideDrawerMenu.style.right = '-320px';
            drawerOverlay.classList.remove('opacity-100', 'visible');
            drawerOverlay.classList.add('opacity-0', 'invisible');
        }

        if(btnOpenDrawer) btnOpenDrawer.addEventListener('click', openDrawer);
        if(btnCloseDrawer) btnCloseDrawer.addEventListener('click', closeDrawer);
        if(drawerOverlay) drawerOverlay.addEventListener('click', closeDrawer);
    });

    // 2. Logika AJAX Search (Tetap menggunakan jQuery bawaan Anda)
    $(document).ready(function() {
        function triggerAjaxSearch(inputId, dropdownId) {
            let keyword = $(inputId).val();
            let dropdown = $(dropdownId);

            if (keyword.trim().length > 0) {
                $.ajax({
                    url: '/search-products-games', 
                    type: 'GET',
                    data: { q: keyword },
                    success: function(response) {
                        dropdown.empty();
                        dropdown.removeClass('hidden'); // Tailwind hidden class

                        if (response.data.length === 0) {
                            dropdown.append(`
                                <div class="flex flex-col items-center justify-center py-10 px-5 text-center">
                                    <img src="/img/image_1daa5c.png" alt="Tidak Ditemukan" class="w-[180px] h-auto mb-4 object-contain">
                                    <p class="text-gray-500 text-sm font-medium m-0">Produk yang dicari tidak ditemukan</p>
                                </div>
                            `);
                        } else {
                            response.data.forEach(function(game) {
                                dropdown.append(`
                                    <a href="/id/${game.slug}" class="flex items-center gap-3 px-4 py-2.5 cursor-pointer transition-colors hover:bg-gray-100 no-underline">
                                        <img src="${game.imagePath}" class="w-[40px] h-[40px] rounded-lg object-cover" alt="${game.name}">
                                        <div>
                                            <div class="text-black font-semibold text-sm">${game.name}</div>
                                            <div class="text-xs text-gray-500">${game.publisher ? game.publisher : 'Game Product'}</div>
                                        </div>
                                    </a>
                                `);
                            });
                        }
                    },
                    error: function(xhr) {
                        console.error("Gagal mengambil data pencarian.");
                    }
                });
            } else {
                dropdown.addClass('hidden');
                dropdown.empty();
            }
        }

        $('#searchInputDesktop').on('keyup', function() { triggerAjaxSearch('#searchInputDesktop', '#searchDropdownDesktop'); });
        $('#searchInputMobile').on('keyup', function() { triggerAjaxSearch('#searchInputMobile', '#searchDropdownMobile'); });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.group, #mobileSearchContainer').length) {
                $('#searchDropdownDesktop, #searchDropdownMobile').addClass('hidden');
            }
        });
    });
</script>