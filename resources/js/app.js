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
        sessionLocation: document.querySelector('#sessionLocation'),
        sessionOfficer: document.querySelector('#sessionOfficer'),
        loadingState: document.querySelector('#loadingState'),
        syncStatus: document.querySelector('#syncStatus'),
        themeToggle: document.querySelector('#themeToggle'),
    };
    let state = { companies: [], currentCompanyId: null, products: [], activities: [] };

    function applyTheme(theme) {
        document.documentElement.classList.toggle('dark', theme === 'dark');
        localStorage.setItem('gosyen-stock-theme', theme);
        elements.themeToggle.textContent = theme === 'dark' ? 'Light' : 'Dark';
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

        if (!response.ok) {
            const message = await response.text();
            throw new Error(message || 'Request gagal.');
        }

        return response.json();
    }

    async function loadData() {
        setBusy('Mengambil data stok...');
        const params = new URLSearchParams();
        if (elements.companySelect.value) {
            params.set('company_id', elements.companySelect.value);
        }
        state = await request(`/stock-opname?${params.toString()}`);
        elements.loadingState.hidden = true;
        setSynced();
        render();
    }

    function setBusy(message) {
        elements.syncStatus.textContent = message;
        elements.syncStatus.className = 'rounded-md bg-[var(--panel-soft)] px-3 py-2 text-xs font-bold text-[var(--muted)]';
    }

    function setSynced() {
        elements.syncStatus.textContent = 'Tersimpan di database';
        elements.syncStatus.className = 'rounded-md bg-[var(--panel-soft)] px-3 py-2 text-xs font-bold text-[var(--brand)]';
    }

    function setError(error) {
        elements.loadingState.hidden = false;
        elements.loadingState.textContent = 'Gagal memuat data. Cek koneksi database dan coba refresh.';
        elements.syncStatus.textContent = 'Database error';
        elements.syncStatus.className = 'rounded-md bg-[#fdecec] px-3 py-2 text-xs font-bold text-[#a12020]';
        console.error(error);
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
        elements.movementProduct.innerHTML = state.products
            .map((product) => `<option value="${product.id}">${escapeHtml(product.name)} (${escapeHtml(product.unit)})</option>`)
            .join('');
    }

    function renderProducts() {
        const products = filteredProducts();

        if (!products.length) {
            elements.productList.innerHTML = '<div class="panel border-dashed p-6 text-center text-sm font-semibold text-[var(--muted)]">Tidak ada stok yang cocok dengan filter.</div>';
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
                    <div class="grid grid-cols-3 gap-2 border-t border-[var(--line)] p-3">
                        <button class="stock-action" data-action="quick-in" data-id="${product.id}" type="button">+1</button>
                        <button class="stock-action" data-action="quick-out" data-id="${product.id}" type="button">-1</button>
                        <button class="stock-action" data-action="sync" data-id="${product.id}" type="button">Samakan</button>
                    </div>
                </article>
            `;
        }).join('');
    }

    function renderActivities() {
        const rows = state.activities.slice(0, 8).map((activity) => {
            const kindText = { in: 'Tambah', out: 'Minus', count: 'Hitung fisik', sync: 'Samakan', create: 'Barang baru' }[activity.kind] || activity.kind;
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
            location: elements.sessionLocation.value,
            officer: elements.sessionOfficer.value,
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

    elements.productForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = new FormData(event.currentTarget);

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
            event.currentTarget.reset();
            setSynced();
            render();
        } catch (error) {
            setError(error);
        }
    });

    elements.companyForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = new FormData(event.currentTarget);

        try {
            setBusy('Membuat company...');
            state = await request('/stock-opname/companies', {
                method: 'POST',
                body: JSON.stringify({ name: data.get('name') }),
            });
            event.currentTarget.reset();
            setSynced();
            render();
        } catch (error) {
            setError(error);
        }
    });

    elements.movementForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = new FormData(event.currentTarget);

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
            event.currentTarget.reset();
            setSynced();
            render();
        } catch (error) {
            setError(error);
        }
    });

    elements.productList.addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-action]');
        if (!button) return;

        const kind = { 'quick-in': 'in', 'quick-out': 'out', sync: 'sync' }[button.dataset.action];
        const quantity = button.dataset.action === 'sync' ? 0 : 1;

        try {
            setBusy('Sinkronisasi...');
            state = await request('/stock-opname/movements', {
                method: 'POST',
                body: JSON.stringify({
                    stock_item_id: Number(button.dataset.id),
                    kind,
                    quantity,
                    note: button.dataset.action === 'sync' ? 'Disamakan dengan sistem' : 'Quick action',
                    ...sessionPayload(),
                }),
            });
            setSynced();
            render();
        } catch (error) {
            setError(error);
        }
    });

    elements.searchInput.addEventListener('input', renderProducts);
    elements.companySelect.addEventListener('change', () => {
        loadData().catch(setError);
    });
    elements.typeFilter.addEventListener('change', renderProducts);
    elements.statusFilter.addEventListener('change', renderProducts);
    elements.exportCsv.addEventListener('click', () => {
        const params = new URLSearchParams(sessionPayload());
        window.location.href = `/stock-opname/export?${params.toString()}`;
    });
    elements.themeToggle.addEventListener('click', () => {
        applyTheme(document.documentElement.classList.contains('dark') ? 'light' : 'dark');
    });

    applyTheme(localStorage.getItem('gosyen-stock-theme') || 'light');
    loadData().catch(setError);
}
