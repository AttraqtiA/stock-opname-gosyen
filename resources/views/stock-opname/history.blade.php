<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Riwayat Stock Opname · Gosyen</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <header class="border-b border-[var(--line)] bg-[var(--header)]">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-4">
            <div class="flex min-w-0 items-center gap-3">
                <img src="{{ asset('image/GosyenLogo.png') }}" alt="Gosyen" class="h-10 w-10 shrink-0 rounded-md border border-[var(--line)] object-contain p-1" />
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase text-[var(--brand)]">Stock Opname</p>
                    <h1 class="truncate text-xl font-bold text-[var(--text)]">Riwayat Mutasi</h1>
                </div>
            </div>
            <div class="relative">
                <button data-nav-menu-toggle="#historyNav" class="grid h-10 w-10 place-items-center rounded-md border border-[var(--line)] bg-[var(--panel)] text-[var(--text)] transition hover:bg-[var(--panel-soft)] sm:hidden" type="button" aria-label="Open menu" aria-expanded="false">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
                <div id="historyNav" class="nav-actions">
                    <a href="{{ route('home', ['company_id' => $currentCompanyId]) }}" class="nav-action">Kembali</a>
                </div>
            </div>
        </div>
    </header>

    <main class="mx-auto grid max-w-6xl gap-4 px-4 py-5">
        <form class="panel grid gap-3 p-4 md:grid-cols-[1fr_160px_160px_auto]" method="GET" action="{{ route('stock-opname.history') }}">
            <label class="block">
                <span class="label">Company</span>
                <select name="company_id" class="field mt-1">
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}" @selected($company->id === $currentCompanyId)>
                            {{ $company->name }} ({{ $company->code_prefix }})
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="label">Dari tanggal</span>
                <input name="from" value="{{ $from }}" type="date" class="field mt-1" />
            </label>
            <label class="block">
                <span class="label">Sampai tanggal</span>
                <input name="to" value="{{ $to }}" type="date" class="field mt-1" />
            </label>
            <button class="self-end rounded-md bg-[var(--brand)] px-4 py-3 text-sm font-bold text-white">Filter</button>
        </form>

        <section class="panel overflow-hidden">
            <div class="border-b border-[var(--line)] p-4">
                <h2 class="text-lg font-bold text-[var(--text)]">Semua perubahan stok</h2>
            </div>
            <div class="divide-y divide-[var(--line)]">
                @forelse ($movements as $movement)
                    <div class="grid gap-2 p-4 lg:grid-cols-[1fr_150px_140px_140px_160px] lg:items-center">
                        <div>
                            <p class="font-bold text-[var(--text)]">{{ $movement->stockItem?->name ?? 'Produk dihapus' }}</p>
                            <p class="text-sm text-[var(--muted)]">
                                {{ $movement->stockItem?->code }} · {{ ucfirst($movement->kind) }} · {{ $movement->quantity }} {{ $movement->stockItem?->unit }}
                                @if ($movement->note)
                                    · {{ $movement->note }}
                                @endif
                            </p>
                        </div>
                        <div class="text-sm font-semibold text-[var(--muted)]">
                            Akun: {{ $movement->user?->name ?? $movement->officer ?? '-' }}
                        </div>
                        <div class="text-sm font-semibold text-[var(--muted)]">
                            Sebelum: {{ $movement->actual_stock_before }}
                        </div>
                        <div class="text-sm font-semibold text-[var(--muted)]">
                            Sesudah: {{ $movement->actual_stock_after }}
                        </div>
                        <time class="text-sm font-bold text-[var(--brand)]">
                            {{ $movement->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}
                        </time>
                    </div>
                @empty
                    <div class="p-4 text-sm font-semibold text-[var(--muted)]">Belum ada riwayat pada filter ini.</div>
                @endforelse
            </div>
        </section>

        {{ $movements->links() }}
    </main>
</body>
</html>
