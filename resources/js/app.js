import './bootstrap';

const app = document.querySelector('#stock-app');

if (app) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const elements = {
        productForm: document.querySelector('#productForm'),
        companyForm: document.querySelector('#companyForm'),
        companySelect: document.querySelector('#companySelect'),
        movementForm: document.querySelector('#movementForm'),
        movementProduct: document.querySelector('#movementProduct'),
        searchInput: document.querySelector('#searchInput'),
        typeFilter: document.querySelector('#typeFilter'),
        statusFilter: document.querySelector('#statusFilter'),
        productList: document.querySelector('#productList'),
        activityLog: document.querySelector('#activityLog'),
        totalSku: document.querySelector('#totalSku'),
        matchCount: document.querySelector('#matchCount'),
        plusCount: document.querySelector('#plusCount'),
        minusCount: document.querySelector('#minusCount'),
        exportCsv: document.querySelector('#exportCsv'),
        loadingState: document.querySelector('#loadingState'),
        syncStatus: document.querySelector('#syncStatus'),
        historyLink: document.querySelector('#historyLink'),
        themeToggle: document.querySelector('#themeToggle'),
        navMenuToggle: document.querySelector('#navMenuToggle'),
        navActions: document.querySelector('#navActions'),
        alertRegion: document.querySelector('#alertRegion'),
    };
    let state = { companies: [], currentCompanyId: null, products: [], activities: [] };
    const pendingActions = new Set();
    let alertTimeout;

    function selectedCompanyId() {
        const fromSelect = Number(elements.companySelect.value || 0);
        const fromUrl = Number(new URLSearchParams(window.location.search).get('company_id') || 0);

        return fromSelect || fromUrl || state.currentCompanyId;
    }

    function syncCompanyUrl(companyId, replace = false) {
        if (!companyId) return;

        const url = new URL(window.location.href);
        url.searchParams.set('company_id', companyId);
        const method = replace ? 'replaceState' : 'pushState';
        window.history[method]({}, '', url);
    }

    function applyTheme(theme) {
        document.documentElement.classList.toggle('dark', theme === 'dark');
        localStorage.setItem('gosyen-stock-theme', theme);
        elements.themeToggle.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
        elements.themeToggle.querySelector('.theme-icon-moon')?.classList.toggle('hidden', theme === 'dark');
        elements.themeToggle.querySelector('.theme-icon-sun')?.classList.toggle('hidden', theme !== 'dark');
    }

    function setMenuOpen(isOpen) {
        elements.navActions?.classList.toggle('is-open', isOpen);
        elements.navMenuToggle?.setAttribute('aria-expanded', String(isOpen));
        elements.navMenuToggle?.setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
    }

    async function request(url, options = {}) {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                ...(options.headers || {}),
            },
            ...options,
        });

        const contentType = response.headers.get('content-type') || '';
        const payload = contentType.includes('application/json')
            ? await response.json()
            : await response.text();

        if (!response.ok) {
            const message = typeof payload === 'string'
                ? payload
                : payload.message || Object.values(payload.errors || {})?.flat()?.[0];
            throw new Error(message || 'Request gagal.');
        }

        return payload;
    }

    async function loadData(companyId = selectedCompanyId(), replaceUrl = true) {
        setBusy('Mengambil data stok...');
        const params = new URLSearchParams();
        if (companyId) {
            params.set('company_id', companyId);
        }
        state = await request(`/stock-opname?${params.toString()}`);
        syncCompanyUrl(state.currentCompanyId, replaceUrl);
        elements.loadingState.hidden = true;
        setSynced();
        render();
    }

    function setBusy(message) {
        if (!elements.syncStatus) return;

        elements.syncStatus.textContent = message;
        elements.syncStatus.className = 'rounded-md bg-[var(--panel-soft)] px-3 py-2 text-xs font-bold text-[var(--muted)]';
    }

    function setSynced() {
        if (!elements.syncStatus) return;

        elements.syncStatus.textContent = 'Tersimpan di database';
        elements.syncStatus.className = 'rounded-md bg-[var(--panel-soft)] px-3 py-2 text-xs font-bold text-[var(--brand)]';
    }

    function setNeedsAttention() {
        if (!elements.syncStatus) return;

        elements.syncStatus.textContent = 'Perlu dicek';
        elements.syncStatus.className = 'rounded-md bg-[var(--panel-soft)] px-3 py-2 text-xs font-bold text-[var(--muted)]';
    }

    function setError(error) {
        const message = error?.message || 'Gagal sinkronisasi. Cek koneksi database dan coba lagi.';
        showAlert(message, 'error');
        if (!state.companies.length && !state.products.length) {
            elements.loadingState.hidden = false;
            elements.loadingState.textContent = 'Data belum dapat ditampilkan.';
        }
        setNeedsAttention();
        console.error(error);
    }

    function showAlert(message, type = 'error') {
        window.clearTimeout(alertTimeout);

        elements.alertRegion.innerHTML = `
            <div class="alert-toast alert-toast-${type}">
                <span>${escapeHtml(message)}</span>
                <button type="button" aria-label="Tutup pesan" data-alert-close>&times;</button>
            </div>
        `;

        alertTimeout = window.setTimeout(clearAlert, 4500);
    }

    function clearAlert() {
        elements.alertRegion.innerHTML = '';
    }

    async function guardedRequest(key, callback) {
        if (pendingActions.has(key)) {
            return;
        }

        pendingActions.add(key);
        renderPendingControls();

        try {
            await callback();
        } finally {
            pendingActions.delete(key);
            renderPendingControls();
        }
    }

    function renderPendingControls() {
        document.querySelectorAll('[data-pending-key]').forEach((button) => {
            button.disabled = pendingActions.has(button.dataset.pendingKey);
        });

        document.querySelectorAll('form button').forEach((button) => {
            const belongsToEmptyMovementForm = button.closest('#movementForm') && state.products.length === 0;
            button.disabled = pendingActions.size > 0 || belongsToEmptyMovementForm;
        });
    }

    function getStatus(product) {
        const diff = product.actualStock - product.systemStock;

        if (diff > 0) return 'plus';
        if (diff < 0) return 'minus';
        return 'match';
    }

    function statusLabel(status) {
        return {
            match: 'Sesuai',
            plus: 'Lebih',
            minus: 'Kurang',
        }[status];
    }

    function statusClass(status) {
        return {
            match: 'bg-[#e8f6ec] text-[#0b6a3b] dark:bg-[#123628] dark:text-[#7bd8a4]',
            plus: 'bg-[#e8f2ff] text-[#24669d] dark:bg-[#17324d] dark:text-[#85c4ff]',
            minus: 'bg-[#fdecec] text-[#a12020] dark:bg-[#492125] dark:text-[#ff9ca0]',
        }[status];
    }

    function filteredProducts() {
        const query = elements.searchInput.value.trim().toLowerCase();
        const type = elements.typeFilter.value;
        const status = elements.statusFilter.value;

        return state.products.filter((product) => {
            const haystack = `${product.code} ${product.name} ${product.type}`.toLowerCase();
            return (!query || haystack.includes(query))
                && (type === 'all' || product.normalizedType === type)
                && (status === 'all' || getStatus(product) === status);
        });
    }

    function render() {
        renderCompanies();
        renderHistoryLink();
        renderFilters();
        renderSummary();
        renderMovementOptions();
        renderProducts();
        renderActivities();
    }

    function renderFilters() {
        const currentType = elements.typeFilter.value || 'all';
        const typeMap = new Map();
        state.products.forEach((product) => {
            if (!typeMap.has(product.normalizedType)) {
                typeMap.set(product.normalizedType, product.type);
            }
        });
        const types = [...typeMap.entries()].sort((a, b) => a[1].localeCompare(b[1]));
        elements.typeFilter.innerHTML = [
            '<option value="all">Semua tipe</option>',
            ...types.map(([normalizedType, type]) => `<option value="${escapeHtml(normalizedType)}">${escapeHtml(type)}</option>`),
        ].join('');
        elements.typeFilter.value = typeMap.has(currentType) ? currentType : 'all';
    }

    function renderCompanies() {
        elements.companySelect.innerHTML = state.companies
            .map((company) => `<option value="${company.id}">${escapeHtml(company.name)} (${escapeHtml(company.code_prefix)})</option>`)
            .join('');
        elements.companySelect.value = String(state.currentCompanyId || '');
    }

    function renderHistoryLink() {
        if (!elements.historyLink || !state.currentCompanyId) return;

        const url = new URL(elements.historyLink.href);
        url.searchParams.set('company_id', state.currentCompanyId);
        elements.historyLink.href = url.toString();
    }

    function renderSummary() {
        const counts = state.products.reduce((carry, product) => {
            carry[getStatus(product)] += 1;
            return carry;
        }, { match: 0, plus: 0, minus: 0 });

        elements.totalSku.textContent = state.products.length;
        elements.matchCount.textContent = counts.match;
        elements.plusCount.textContent = counts.plus;
        elements.minusCount.textContent = counts.minus;
    }

    function renderMovementOptions() {
        elements.movementForm.querySelectorAll('button, select, input').forEach((control) => {
            control.disabled = state.products.length === 0;
        });

        elements.movementProduct.innerHTML = state.products
            .map((product) => `<option value="${product.id}">${escapeHtml(product.name)} (${escapeHtml(product.unit)})</option>`)
            .join('');
    }

    function renderProducts() {
        const products = filteredProducts();

        if (!products.length) {
            const isFilteredEmpty = state.products.length > 0;
            elements.productList.innerHTML = `
                <div class="empty-state">
                    <p class="font-bold text-[var(--text)]">${isFilteredEmpty ? 'Tidak ada stok yang cocok.' : 'Belum ada stok untuk company ini.'}</p>
                    <p class="mt-1 text-sm font-semibold text-[var(--muted)]">${isFilteredEmpty ? 'Ubah pencarian atau filter untuk melihat stok lain.' : 'Tambahkan master barang dulu, lalu tim gudang bisa mulai input opname dari mobile.'}</p>
                </div>
            `;
            return;
        }

        elements.productList.innerHTML = products.map((product) => {
            const diff = product.actualStock - product.systemStock;
            const status = getStatus(product);

            return `
                <article class="stock-card">
                    <div class="grid gap-3 p-4 md:grid-cols-[1fr_auto] md:items-start">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-md bg-[var(--panel-soft)] px-2 py-1 text-xs font-bold text-[var(--muted)]">${escapeHtml(product.code)}</span>
                                <span class="rounded-md px-2 py-1 text-xs font-bold ${statusClass(status)}">${statusLabel(status)}</span>
                            </div>
                            <h3 class="mt-2 text-lg font-bold text-[var(--text)]">${escapeHtml(product.name)}</h3>
                            <p class="text-sm font-semibold text-[var(--muted)]">${escapeHtml(product.type)} · ${escapeHtml(product.unit)}</p>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-center md:min-w-[270px]">
                            <div class="rounded-md bg-[var(--panel-soft)] p-2"><span class="text-xs font-bold text-[var(--muted)]">Sistem</span><strong class="block text-lg text-[var(--text)]">${product.systemStock}</strong></div>
                            <div class="rounded-md bg-[var(--panel-soft)] p-2"><span class="text-xs font-bold text-[var(--muted)]">Fisik</span><strong class="block text-lg text-[var(--text)]">${product.actualStock}</strong></div>
                            <div class="rounded-md bg-[var(--panel-soft)] p-2"><span class="text-xs font-bold text-[var(--muted)]">Selisih</span><strong class="block text-lg text-[var(--text)]">${diff > 0 ? '+' : ''}${diff}</strong></div>
                        </div>
                    </div>
                    <div class="grid gap-2 border-t border-[var(--line)] p-3 sm:grid-cols-[1fr_auto_auto_auto]">
                        <div class="grid grid-cols-[1fr_auto] gap-2">
                            <input class="field min-h-10 py-2" inputmode="numeric" min="0" data-count-input="${product.id}" type="number" placeholder="Qty opname" />
                            <button class="stock-action bg-[var(--brand)] text-white disabled:cursor-not-allowed disabled:opacity-50" data-action="count" data-id="${product.id}" data-pending-key="count:${product.id}" type="button">Input</button>
                        </div>
                        <button class="stock-action disabled:cursor-not-allowed disabled:opacity-50" data-action="quick-in" data-id="${product.id}" data-pending-key="quick-in:${product.id}" type="button">+1</button>
                        <button class="stock-action disabled:cursor-not-allowed disabled:opacity-50" data-action="quick-out" data-id="${product.id}" data-pending-key="quick-out:${product.id}" type="button">-1</button>
                        <button class="stock-action disabled:cursor-not-allowed disabled:opacity-50" data-action="sync" data-id="${product.id}" data-pending-key="sync:${product.id}" type="button">Samakan</button>
                    </div>
                </article>
            `;
        }).join('');
    }

    function renderActivities() {
        const rows = state.activities.slice(0, 8).map((activity) => {
            const kindText = { in: 'Tambah', out: 'Minus', count: 'Input opname', sync: 'Samakan', create: 'Barang baru' }[activity.kind] || activity.kind;
            const time = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(activity.at));

            return `
                <div class="grid gap-1 p-4 sm:grid-cols-[1fr_auto] sm:items-center">
                    <div>
                        <p class="font-bold text-[var(--text)]">${escapeHtml(activity.productName || 'Produk dihapus')}</p>
                        <p class="text-sm text-[var(--muted)]">${kindText} · ${activity.qty} ${activity.unit || ''}${activity.note ? ` · ${escapeHtml(activity.note)}` : ''}</p>
                    </div>
                    <time class="text-xs font-bold text-[var(--brand)]">${time}</time>
                </div>
            `;
        });

        elements.activityLog.innerHTML = rows.length
            ? rows.join('')
            : '<div class="p-4 text-sm font-semibold text-[var(--muted)]">Belum ada aktivitas.</div>';
    }

    function sessionPayload() {
        return {
            company_id: Number(elements.companySelect.value || state.currentCompanyId),
        };
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[char]));
    }

    elements.productForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const data = new FormData(form);

        guardedRequest('product-form', async () => {
            try {
                setBusy('Menyimpan barang...');
                state = await request('/stock-opname/items', {
                    method: 'POST',
                    body: JSON.stringify({
                        name: data.get('name'),
                        type: data.get('type'),
                        unit: data.get('unit'),
                        system_stock: Number(data.get('systemStock') || 0),
                        actual_stock: Number(data.get('actualStock') || 0),
                        ...sessionPayload(),
                    }),
                });
                form.reset();
                setSynced();
                render();
            } catch (error) {
                setError(error);
            }
        });
    });

    elements.companyForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const data = new FormData(form);

        guardedRequest('company-form', async () => {
            try {
                setBusy('Membuat company...');
                state = await request('/stock-opname/companies', {
                    method: 'POST',
                    body: JSON.stringify({
                        name: data.get('name'),
                        location: data.get('location'),
                        pic_user_id: data.get('pic_user_id') || null,
                    }),
                });
                if (state.requestAccepted) {
                    form.reset();
                    state = state.payload;
                    showAlert('Request company dikirim. Admin perlu approve sebelum client aktif.', 'success');
                    setSynced();
                    render();
                    return;
                }

                form.reset();
                const companyId = state.currentCompanyId;
                syncCompanyUrl(companyId);
                setBusy('Memuat company baru...');
                await loadData(companyId, false);
            } catch (error) {
                setError(error);
            }
        });
    });

    elements.movementForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const data = new FormData(form);

        guardedRequest('movement-form', async () => {
            try {
                setBusy('Mencatat gerak...');
                state = await request('/stock-opname/movements', {
                    method: 'POST',
                    body: JSON.stringify({
                        stock_item_id: Number(data.get('productId')),
                        kind: data.get('kind'),
                        quantity: Number(data.get('qty') || 0),
                        note: data.get('note'),
                        ...sessionPayload(),
                    }),
                });
                form.reset();
                setSynced();
                render();
            } catch (error) {
                setError(error);
            }
        });
    });

    elements.productList.addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-action]');
        if (!button) return;

        const countInput = elements.productList.querySelector(`[data-count-input="${button.dataset.id}"]`);
        const kind = { 'quick-in': 'in', 'quick-out': 'out', count: 'count', sync: 'sync' }[button.dataset.action];
        const quantity = button.dataset.action === 'sync'
            ? 0
            : button.dataset.action === 'count'
                ? Number(countInput?.value || 0)
                : 1;

        if (button.dataset.action === 'count' && quantity < 1) {
            setError(new Error('Qty opname minimal 1.'));
            countInput?.focus();
            return;
        }

        guardedRequest(`${button.dataset.action}:${button.dataset.id}`, async () => {
            try {
                setBusy('Sinkronisasi...');
                state = await request('/stock-opname/movements', {
                    method: 'POST',
                    body: JSON.stringify({
                        stock_item_id: Number(button.dataset.id),
                        kind,
                        quantity,
                        note: button.dataset.action === 'sync' ? 'Disamakan dengan sistem' : 'Input dari kartu stok',
                        ...sessionPayload(),
                    }),
                });
                if (button.dataset.action === 'count') {
                    countInput.value = '';
                }
                setSynced();
                render();
            } catch (error) {
                setError(error);
            }
        });
    });

    elements.searchInput.addEventListener('input', renderProducts);
    elements.companySelect.addEventListener('change', () => {
        loadData(Number(elements.companySelect.value), false).catch(setError);
    });
    elements.typeFilter.addEventListener('change', renderProducts);
    elements.statusFilter.addEventListener('change', renderProducts);
    elements.exportCsv.addEventListener('click', () => {
        const params = new URLSearchParams(sessionPayload());
        window.location.href = `/stock-opname/export?${params.toString()}`;
    });
    elements.themeToggle.addEventListener('click', () => {
        applyTheme(document.documentElement.classList.contains('dark') ? 'light' : 'dark');
        setMenuOpen(false);
    });
    elements.navMenuToggle?.addEventListener('click', () => {
        setMenuOpen(!elements.navActions?.classList.contains('is-open'));
    });
    elements.navActions?.addEventListener('click', (event) => {
        if (event.target.closest('a, button')) {
            setMenuOpen(false);
        }
    });
    elements.alertRegion?.addEventListener('click', (event) => {
        if (event.target.closest('[data-alert-close]')) {
            window.clearTimeout(alertTimeout);
            clearAlert();
        }
    });
    document.addEventListener('click', (event) => {
        if (!event.target.closest('#navActions, #navMenuToggle')) {
            setMenuOpen(false);
        }
    });

    applyTheme(localStorage.getItem('gosyen-stock-theme') || 'light');
    loadData().catch(setError);
}
