<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b1020">

    <title>Gosyen Stock Opname Pal</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen antialiased">
    <div id="stock-app" class="min-h-screen">
        <div id="alertRegion" class="alert-region" aria-live="polite" aria-atomic="true"></div>

        <header class="sticky top-0 z-30 border-b bg-[var(--header)]/95 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-2 px-3 py-3 sm:gap-3 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <img src="{{ asset('image/GosyenLogo.png') }}" alt="Gosyen" class="h-10 w-10 shrink-0 rounded-md border border-[var(--line)] object-contain p-1" />
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase text-[var(--brand)]">Gosyen</p>
                        <h1 class="truncate text-base font-bold text-[var(--text)] sm:text-xl">Stock Opname Pal</h1>
                    </div>
                </div>

                <div class="relative flex shrink-0 items-center gap-2">
                    <button id="navMenuToggle" data-nav-menu-toggle="#navActions" class="grid h-10 w-10 place-items-center rounded-md border border-[var(--line)] bg-[var(--panel)] text-[var(--text)] transition hover:bg-[var(--panel-soft)] sm:hidden" type="button" aria-label="Open menu" aria-expanded="false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                    <div id="navActions" class="nav-actions">
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.users') }}" class="nav-action">
                            Akun
                        </a>
                        <a href="{{ route('admin.companies') }}" class="nav-action">
                            Clients
                        </a>
                    @endif
                    <button id="themeToggle" class="nav-icon-action" type="button" aria-label="Toggle dark mode">
                        <svg class="theme-icon theme-icon-moon h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M21 14.4A8.2 8.2 0 0 1 9.6 3a8.8 8.8 0 1 0 11.4 11.4Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <svg class="theme-icon theme-icon-sun hidden h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 4V2M12 22v-2M4.93 4.93 3.52 3.52M20.48 20.48l-1.41-1.41M4 12H2M22 12h-2M4.93 19.07l-1.41 1.41M20.48 3.52l-1.41 1.41" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </button>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="nav-action w-full" type="submit">
                            Logout
                        </button>
                    </form>
                    </div>
                </div>
            </div>
        </header>

        <div class="border-b border-[var(--line)] bg-[var(--subheader)]">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-3 py-4 sm:px-6 lg:px-8">
                <h2 class="text-lg font-bold text-[var(--text)]">Stock Opname</h2>
                <button id="exportCsv" class="rounded-md bg-[#0f6b4b] px-3 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-[#0b5139] sm:text-sm" type="button">
                    Export
                </button>
            </div>
        </div>

        <main class="mx-auto grid max-w-7xl items-start gap-3 px-3 py-3 sm:gap-4 sm:px-6 lg:grid-cols-[360px_1fr] lg:px-8">
            <section class="grid auto-rows-max gap-4 lg:sticky lg:top-24 lg:self-start">
                <div class="panel form-panel form-panel-company p-4">
                    <p class="text-sm font-semibold text-[var(--muted)]">Client company</p>
                    <div class="mt-3 grid gap-3">
                        <label class="block">
                            <span class="label">Database aktif</span>
                            <select id="companySelect" class="field mt-1"></select>
                        </label>
                        @if (auth()->user()->isAdmin())
                            <form id="companyForm" class="grid gap-2">
                                <input name="name" required class="field" placeholder="Company baru" />
                                <input name="location" class="field" placeholder="Lokasi utama" />
                                <select name="pic_user_id" class="field">
                                    <option value="">Pilih PIC</option>
                                    @foreach ($picUsers as $picUser)
                                        <option value="{{ $picUser->id }}">{{ $picUser->name }} · {{ $picUser->email }}</option>
                                    @endforeach
                                </select>
                                <button class="rounded-md bg-[var(--brand)] px-3 py-2 text-sm font-bold text-white">Tambah</button>
                            </form>
                        @else
                            <form id="companyForm" class="grid gap-2">
                                <input name="name" required class="field" placeholder="Request company baru" />
                                <input name="location" class="field" placeholder="Lokasi utama" />
                                <select name="pic_user_id" class="field">
                                    <option value="">Pilih PIC</option>
                                    @foreach ($picUsers as $picUser)
                                        <option value="{{ $picUser->id }}">{{ $picUser->name }} · {{ $picUser->email }}</option>
                                    @endforeach
                                </select>
                                <button class="rounded-md bg-[var(--brand)] px-3 py-2 text-sm font-bold text-white">Kirim Request</button>
                            </form>
                        @endif
                    </div>
                </div>

                <form id="productForm" class="panel form-panel form-panel-product p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-[var(--muted)]">Tambah barang</p>
                            <h2 class="text-xl font-bold text-[var(--text)]">Produk baru</h2>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3">
                        <label class="block">
                            <span class="label">Nama stok</span>
                            <input name="name" required class="field mt-1" placeholder="Contoh: Plastik vacuum 1 kg" />
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block">
                                <span class="label">Tipe</span>
                                <input name="type" required class="field mt-1" placeholder="Kemasan" />
                            </label>
                            <label class="block">
                                <span class="label">Satuan</span>
                                <input name="unit" required class="field mt-1" placeholder="pcs / kg" />
                            </label>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block">
                                <span class="label">Stok sistem</span>
                                <input name="systemStock" type="number" min="0" class="field mt-1" value="0" />
                            </label>
                            <label class="block">
                                <span class="label">Stok opname awal</span>
                                <input name="actualStock" type="number" min="0" class="field mt-1" value="0" />
                            </label>
                        </div>
                        <button class="rounded-md bg-[var(--brand)] px-4 py-3 text-sm font-bold text-white transition hover:bg-[var(--brand-strong)]">
                            Simpan Barang
                        </button>
                    </div>
                </form>

                <form id="movementForm" class="panel form-panel form-panel-movement p-4">
                    <p class="text-sm font-semibold text-[var(--muted)]">Input manual</p>
                    <h2 class="text-xl font-bold text-[var(--text)]">Opname / Mutasi</h2>
                    <div class="mt-4 grid gap-3">
                        <select name="productId" id="movementProduct" class="field" required></select>
                        <div class="grid grid-cols-[1fr_110px] gap-3">
                            <select name="kind" class="field">
                                <option value="count">Input opname</option>
                                <option value="in">Tambah stok</option>
                                <option value="out">Minus stok</option>
                            </select>
                            <input name="qty" type="number" min="0" required class="field" placeholder="Qty" />
                        </div>
                        <input name="note" class="field" placeholder="Catatan singkat" />
                        <button class="rounded-md bg-[var(--brand)] px-4 py-3 text-sm font-bold text-white transition hover:bg-[var(--brand-strong)]">
                            Catat Gerak
                        </button>
                    </div>
                </form>
            </section>

            <section class="grid auto-rows-max gap-4">
                <div class="panel grid gap-3 p-4 md:grid-cols-[1fr_170px_150px]">
                    <label class="block">
                        <span class="label">Cari nama, tipe, kode</span>
                        <input id="searchInput" class="field mt-1" placeholder="Ketik untuk filter stok..." />
                    </label>
                    <label class="block">
                        <span class="label">Filter tipe</span>
                        <select id="typeFilter" class="field mt-1"></select>
                    </label>
                    <label class="block">
                        <span class="label">Status</span>
                        <select id="statusFilter" class="field mt-1">
                            <option value="all">Semua</option>
                            <option value="match">Sesuai</option>
                            <option value="plus">Lebih</option>
                            <option value="minus">Kurang</option>
                        </select>
                    </label>
                </div>

                <div id="loadingState" class="panel p-4 text-sm font-semibold text-[var(--muted)]">Mengambil data stok...</div>

                <div class="grid auto-rows-max grid-cols-2 gap-3 md:grid-cols-4">
                    <div class="metric"><span>Total SKU</span><strong id="totalSku">0</strong></div>
                    <div class="metric"><span>Sesuai</span><strong id="matchCount">0</strong></div>
                    <div class="metric"><span>Selisih +</span><strong id="plusCount">0</strong></div>
                    <div class="metric"><span>Selisih -</span><strong id="minusCount">0</strong></div>
                </div>

                <div id="productList" class="grid gap-3"></div>

                <div class="panel">
                    <div class="flex items-center justify-between gap-3 border-b border-[var(--line)] p-4">
                        <div>
                            <p class="text-sm font-semibold text-[var(--muted)]">Riwayat</p>
                            <h2 class="text-xl font-bold text-[var(--text)]">Aktivitas terakhir</h2>
                        </div>
                        <a id="historyLink" href="{{ route('stock-opname.history') }}" class="rounded-md bg-[var(--panel-soft)] px-3 py-2 text-xs font-bold text-[var(--brand)] transition hover:bg-[var(--field)]">Semua Riwayat</a>
                    </div>
                    <div id="activityLog" class="divide-y divide-[var(--line)]"></div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
