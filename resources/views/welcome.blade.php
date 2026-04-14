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
        <header class="sticky top-0 z-30 border-b bg-[var(--header)]/95 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-[var(--line)] bg-[var(--panel-soft)] text-[var(--brand)]">
                        <span class="text-lg font-black">G</span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase text-[var(--brand)]">Gosyen</p>
                        <h1 class="truncate text-base font-bold text-[var(--text)] sm:text-xl">Stock Opname Pal</h1>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button id="themeToggle" class="rounded-md border border-[var(--line)] bg-[var(--panel)] px-3 py-2 text-sm font-bold text-[var(--text)] transition hover:bg-[var(--panel-soft)]" type="button">
                        Dark
                    </button>
                    <button id="exportCsv" class="rounded-md bg-[#0f6b4b] px-3 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-[#0b5139]" type="button">
                        Export Excel
                    </button>
                </div>
            </div>
        </header>

        <div class="border-b border-[var(--line)] bg-[var(--subheader)]">
            <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                <h2 class="text-lg font-bold text-[var(--text)]">Stock Opname</h2>
            </div>
        </div>

        <main class="mx-auto grid max-w-7xl gap-4 px-4 py-4 sm:px-6 lg:grid-cols-[360px_1fr] lg:px-8">
            <section class="grid gap-4 lg:sticky lg:top-24 lg:self-start">
                <div class="panel p-4">
                    <p class="text-sm font-semibold text-[var(--muted)]">Sesi opname</p>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <label class="block">
                            <span class="label">Lokasi</span>
                            <input id="sessionLocation" class="field mt-1" value="Gudang Utama" />
                        </label>
                        <label class="block">
                            <span class="label">Petugas</span>
                            <input id="sessionOfficer" class="field mt-1" value="Tim Gosyen" />
                        </label>
                    </div>
                </div>

                <form id="productForm" class="panel p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-[var(--muted)]">Tambah barang</p>
                            <h2 class="text-xl font-bold text-[var(--text)]">Produk baru</h2>
                        </div>
                        <span class="rounded-md bg-[var(--panel-soft)] px-2 py-1 text-xs font-bold text-[var(--brand)]">Master</span>
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
                                <span class="label">Stok fisik</span>
                                <input name="actualStock" type="number" min="0" class="field mt-1" value="0" />
                            </label>
                        </div>
                        <button class="rounded-md bg-[var(--brand)] px-4 py-3 text-sm font-bold text-white transition hover:bg-[var(--brand-strong)]">
                            Simpan Barang
                        </button>
                    </div>
                </form>

                <form id="movementForm" class="panel p-4">
                    <p class="text-sm font-semibold text-[var(--muted)]">Gerak stok cepat</p>
                    <h2 class="text-xl font-bold text-[var(--text)]">Masuk / Keluar</h2>
                    <div class="mt-4 grid gap-3">
                        <select name="productId" id="movementProduct" class="field" required></select>
                        <div class="grid grid-cols-[1fr_110px] gap-3">
                            <select name="kind" class="field">
                                <option value="in">Tambah stok</option>
                                <option value="out">Minus stok</option>
                                <option value="count">Set stok fisik</option>
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

            <section class="grid gap-4">
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

                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
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
                        <span id="syncStatus" class="rounded-md bg-[var(--panel-soft)] px-3 py-2 text-xs font-bold text-[var(--muted)]">Database</span>
                    </div>
                    <div id="activityLog" class="divide-y divide-[var(--line)]"></div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
