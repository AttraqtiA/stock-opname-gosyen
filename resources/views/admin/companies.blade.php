<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Client Companies · Gosyen</title>
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
                    <p class="text-xs font-bold uppercase text-[var(--brand)]">Admin</p>
                    <h1 class="truncate text-xl font-bold text-[var(--text)]">Client Companies</h1>
                </div>
            </div>
            <div class="relative">
                <button data-nav-menu-toggle="#adminCompaniesNav" class="grid h-10 w-10 place-items-center rounded-md border border-[var(--line)] bg-[var(--panel)] text-[var(--text)] transition hover:bg-[var(--panel-soft)] sm:hidden" type="button" aria-label="Open menu" aria-expanded="false">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
                <div id="adminCompaniesNav" class="nav-actions">
                    <a href="{{ route('admin.users') }}" class="nav-action">Akun</a>
                    <a href="{{ route('home') }}" class="nav-action">Stock Opname</a>
                </div>
            </div>
        </div>
    </header>

    <main class="mx-auto grid max-w-6xl gap-4 px-4 py-5">
        @if (session('status'))
            <div class="panel p-3 text-sm font-semibold text-[var(--brand)]">{{ session('status') }}</div>
        @endif

        <section class="panel">
            <div class="border-b border-[var(--line)] p-4">
                <h2 class="text-lg font-bold text-[var(--text)]">Request Menunggu Approval</h2>
            </div>
            <div class="divide-y divide-[var(--line)]">
                @forelse ($pendingCompanies as $company)
                    <div class="grid gap-3 p-4 lg:grid-cols-[1fr_auto] lg:items-start">
                        <form method="POST" action="{{ route('admin.companies.update', $company) }}" class="grid gap-3 md:grid-cols-3">
                            @csrf
                            @method('PUT')
                            <input name="name" value="{{ $company->name }}" class="field" />
                            <input name="location" value="{{ $company->location }}" class="field" placeholder="Lokasi" />
                            <select name="pic_user_id" class="field">
                                <option value="">Pilih PIC</option>
                                @foreach ($picUsers as $picUser)
                                    <option value="{{ $picUser->id }}" @selected($company->pic_user_id === $picUser->id)>{{ $picUser->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-sm font-semibold text-[var(--muted)] md:col-span-3">
                                Request oleh {{ $company->requester?->name ?? '-' }}
                            </p>
                            <button class="rounded-md border border-[var(--line)] px-4 py-3 text-sm font-bold text-[var(--text)] md:w-max">Simpan Draft</button>
                        </form>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-1">
                            <form method="POST" action="{{ route('admin.companies.approve', $company) }}">
                                @csrf
                                <button class="w-full rounded-md bg-[var(--brand)] px-4 py-3 text-sm font-bold text-white">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.companies.destroy', $company) }}">
                                @csrf
                                @method('DELETE')
                                <button class="w-full rounded-md bg-[#a12020] px-4 py-3 text-sm font-bold text-white">Hapus</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="p-4 text-sm font-semibold text-[var(--muted)]">Tidak ada request company pending.</div>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <div class="border-b border-[var(--line)] p-4">
                <h2 class="text-lg font-bold text-[var(--text)]">Client Aktif</h2>
            </div>
            <div class="divide-y divide-[var(--line)]">
                @foreach ($approvedCompanies as $company)
                    <div class="grid gap-3 p-4 lg:grid-cols-[1fr_auto] lg:items-start">
                        <form method="POST" action="{{ route('admin.companies.update', $company) }}" class="grid gap-3 md:grid-cols-3">
                            @csrf
                            @method('PUT')
                            <input name="name" value="{{ $company->name }}" class="field" />
                            <input name="location" value="{{ $company->location }}" class="field" placeholder="Lokasi" />
                            <select name="pic_user_id" class="field">
                                <option value="">Pilih PIC</option>
                                @foreach ($picUsers as $picUser)
                                    <option value="{{ $picUser->id }}" @selected($company->pic_user_id === $picUser->id)>{{ $picUser->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-sm font-semibold text-[var(--muted)] md:col-span-3">
                                {{ $company->code_prefix }} · PIC {{ $company->pic?->name ?? '-' }}
                            </p>
                            <button class="rounded-md border border-[var(--line)] px-4 py-3 text-sm font-bold text-[var(--text)] md:w-max">Simpan</button>
                        </form>
                        <form method="POST" action="{{ route('admin.companies.destroy', $company) }}">
                            @csrf
                            @method('DELETE')
                            <button class="w-full rounded-md bg-[#a12020] px-4 py-3 text-sm font-bold text-white">Hapus</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>
    </main>
</body>
</html>
