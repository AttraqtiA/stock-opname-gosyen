<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Approval Akun · Gosyen</title>
    <link rel="icon" type="image/png" href="{{ asset('image/GosyenLogo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('image/GosyenLogo.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen antialiased">
    <div id="stock-app" class="min-h-screen">
        <header class="sticky top-0 z-30 border-b bg-[var(--header)]/95 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-2 px-3 py-3 sm:gap-3 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <img src="{{ asset('image/GosyenLogo.png') }}" alt="Gosyen" class="h-10 w-10 shrink-0 rounded-md border border-[var(--line)] object-contain p-1" />
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase text-[var(--brand)]">Admin</p>
                        <h1 class="truncate text-base font-bold text-[var(--text)] sm:text-xl">Approval Akun</h1>
                    </div>
                </div>

                <div class="relative flex shrink-0 items-center gap-2">
                    <button id="navMenuToggle" data-nav-menu-toggle="#navActions" class="grid h-10 w-10 place-items-center rounded-md border border-[var(--line)] bg-[var(--panel)] text-[var(--text)] transition hover:bg-[var(--panel-soft)] sm:hidden" type="button" aria-label="Open menu" aria-expanded="false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                    <div id="navActions" class="nav-actions">
                        <a href="{{ route('home') }}" class="nav-action">
                            Stock Opname
                        </a>
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

    <main class="mx-auto grid max-w-5xl gap-4 px-4 py-5">
        @if (session('status'))
            <div class="panel p-3 text-sm font-semibold text-[var(--brand)]">{{ session('status') }}</div>
        @endif

        <section class="panel">
            <div class="border-b border-[var(--line)] p-4">
                <h2 class="text-lg font-bold text-[var(--text)]">Menunggu Approval</h2>
            </div>
            <div class="divide-y divide-[var(--line)]">
                @forelse ($pendingUsers as $user)
                    <form method="POST" action="{{ route('admin.users.approve', $user) }}" class="grid gap-3 p-4 sm:grid-cols-[1fr_150px_auto] sm:items-center">
                        @csrf
                        <div>
                            <p class="font-bold text-[var(--text)]">{{ $user->name }}</p>
                            <p class="text-sm text-[var(--muted)]">{{ $user->email }}</p>
                        </div>
                        <select name="role" class="field">
                            <option value="employee">Employee</option>
                            <option value="admin">Admin</option>
                        </select>
                        <button class="rounded-md bg-[var(--brand)] px-4 py-3 text-sm font-bold text-white">Approve</button>
                    </form>
                @empty
                    <div class="p-4 text-sm font-semibold text-[var(--muted)]">Tidak ada akun pending.</div>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <div class="border-b border-[var(--line)] p-4">
                <h2 class="text-lg font-bold text-[var(--text)]">Akun Aktif</h2>
            </div>
            <div class="divide-y divide-[var(--line)]">
                @foreach ($approvedUsers as $user)
                    <div class="grid gap-1 p-4 sm:grid-cols-[1fr_auto] sm:items-center">
                        <div>
                            <p class="font-bold text-[var(--text)]">{{ $user->name }}</p>
                            <p class="text-sm text-[var(--muted)]">{{ $user->email }}</p>
                        </div>
                        <span class="rounded-md bg-[var(--panel-soft)] px-3 py-2 text-xs font-bold text-[var(--brand)]">{{ $user->role }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    </main>
    </div>
</body>
</html>
