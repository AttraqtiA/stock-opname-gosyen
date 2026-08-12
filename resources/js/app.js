import './bootstrap';

document.querySelectorAll('[data-nav-menu-toggle]').forEach((toggle) => {
    const target = document.querySelector(toggle.dataset.navMenuToggle);
    if (!target) return;

    function setOpen(isOpen) {
        target.classList.toggle('is-open', isOpen);
        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
    }

    toggle.addEventListener('click', (event) => {
        event.stopPropagation();
        setOpen(!target.classList.contains('is-open'));
    });

    target.addEventListener('click', (event) => {
        if (event.target.closest('a, button')) {
            setOpen(false);
        }
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest(`${toggle.dataset.navMenuToggle}, [data-nav-menu-toggle]`)) {
            setOpen(false);
        }
    });
});

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
        warehouseForm: document.querySelector('#warehouseForm'),
        warehouseList: document.querySelector('#warehouseList'),
        productWarehouseSelect: document.querySelector('#productWarehouseSelect'),
        
        barcodeInput: document.querySelector('#barcodeInput'),
        scanCameraBtn: document.querySelector('#scanCameraBtn'),
        scannerModal: document.querySelector('#scannerModal'),
        closeScannerBtn: document.querySelector('#closeScannerBtn'),
        stopScannerBtn: document.querySelector('#stopScannerBtn'),
        sessionStatusArea: document.querySelector('#sessionStatusArea'),
        sessionApprovalPanel: document.querySelector('#sessionApprovalPanel'),
        pendingSessionsList: document.querySelector('#pendingSessionsList'),
        offlineBanner: document.querySelector('#offlineBanner'),
        offlinePendingCount: document.querySelector('#offlinePendingCount'),
    };
    let state = { 
        companies: [], 
        currentCompanyId: null, 
        products: [], 
        warehouses: [], 
        activities: [],
        activeSession: null,
        pendingSession: null,
        pastSessions: []
    };
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

    let isOffline = !navigator.onLine;

    async function request(url, options = {}) {
        const isWrite = ['POST', 'PATCH', 'PUT', 'DELETE'].includes(options.method || 'GET');
        
        if (isOffline) {
            if (isWrite) {
                queueOfflineAction(url, options);
                return state;
            }
            const cached = localStorage.getItem('gosyen_opname_state');
            if (cached) {
                const parsed = JSON.parse(cached);
                applyPendingQueueToState(parsed);
                return parsed;
            }
            throw new Error('Koneksi terputus dan tidak ada cache lokal.');
        }

        try {
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

            if (!isWrite && url.startsWith('/stock-opname')) {
                localStorage.setItem('gosyen_opname_state', JSON.stringify(payload));
            }

            return payload;
        } catch (error) {
            if (error instanceof TypeError) {
                isOffline = true;
                updateOfflineBanner();
                if (isWrite) {
                    queueOfflineAction(url, options);
                    return state;
                }
                const cached = localStorage.getItem('gosyen_opname_state');
                if (cached) {
                    const parsed = JSON.parse(cached);
                    applyPendingQueueToState(parsed);
                    return parsed;
                }
            }
            throw error;
        }
    }

    function queueOfflineAction(url, options) {
        const queue = JSON.parse(localStorage.getItem('gosyen_pending_actions') || '[]');
        queue.push({
            id: Date.now() + Math.random().toString(36).substr(2, 5),
            url,
            method: options.method || 'POST',
            body: options.body,
        });
        localStorage.setItem('gosyen_pending_actions', JSON.stringify(queue));
        
        const body = JSON.parse(options.body || '{}');
        applySingleActionToState(state, url, options.method, body);
        
        updateOfflineBanner();
        showAlert('Data disimpan secara lokal (Offline)', 'success');
    }

    function updateOfflineBanner() {
        const queue = JSON.parse(localStorage.getItem('gosyen_pending_actions') || '[]');
        const count = queue.length;
        if (isOffline || count > 0) {
            elements.offlineBanner?.classList.remove('hidden');
            if (elements.offlinePendingCount) {
                elements.offlinePendingCount.textContent = `${count} data belum disinkronkan`;
            }
        } else {
            elements.offlineBanner?.classList.add('hidden');
        }
    }

    function applyPendingQueueToState(stateObj) {
        const queue = JSON.parse(localStorage.getItem('gosyen_pending_actions') || '[]');
        queue.forEach(action => {
            const body = JSON.parse(action.body || '{}');
            applySingleActionToState(stateObj, action.url, action.method, body);
        });
    }

    function applySingleActionToState(stateObj, url, method, body) {
        if (url.includes('/stock-opname/movements') && method === 'POST') {
            const itemId = body.stock_item_id;
            const product = stateObj.products.find(p => p.id === itemId);
            if (product) {
                if (body.kind === 'count') {
                    product.actualStock = body.quantity;
                } else if (body.kind === 'in') {
                    product.actualStock += body.quantity;
                } else if (body.kind === 'out') {
                    product.actualStock = Math.max(0, product.actualStock - body.quantity);
                } else if (body.kind === 'sync') {
                    product.actualStock = product.systemStock;
                }
            }
        }
        if (url.includes('/stock-opname/sessions') && method === 'POST' && !url.includes('/finalize') && !url.includes('/approve') && !url.includes('/reject')) {
            stateObj.activeSession = {
                id: Date.now(),
                name: body.name,
                status: 'active',
                creatorName: 'Anda (Offline)',
                createdAt: new Date().toISOString(),
            };
            stateObj.products.forEach(p => {
                p.actualStock = 0;
            });
        }
        if (url.includes('/sessions/') && url.includes('/finalize') && method === 'POST') {
            let hasLargeDiscrepancy = false;
            stateObj.products.forEach(p => {
                if (Math.abs(p.actualStock - p.systemStock) >= 10) {
                    hasLargeDiscrepancy = true;
                }
            });
            
            if (hasLargeDiscrepancy && !window.isAdmin) {
                stateObj.pendingSession = {
                    ...stateObj.activeSession,
                    status: 'pending_approval',
                };
                stateObj.activeSession = null;
            } else {
                stateObj.products.forEach(p => {
                    p.systemStock = p.actualStock;
                });
                if (stateObj.activeSession) {
                    stateObj.pastSessions.unshift({
                        id: stateObj.activeSession.id,
                        name: stateObj.activeSession.name,
                        completedAt: new Date().toISOString(),
                        completerName: 'Anda (Offline)',
                    });
                }
                stateObj.activeSession = null;
            }
        }
    }

    async function flushOfflineQueue() {
        let queue = JSON.parse(localStorage.getItem('gosyen_pending_actions') || '[]');
        if (queue.length === 0) return;

        setBusy(`Sinkronisasi ${queue.length} data offline...`);
        
        const failed = [];
        for (const action of queue) {
            try {
                await fetch(action.url, {
                    method: action.method,
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: action.body,
                });
            } catch (err) {
                console.error('Failed to sync offline action:', err);
                failed.push(action);
            }
        }

        localStorage.setItem('gosyen_pending_actions', JSON.stringify(failed));
        updateOfflineBanner();

        if (failed.length === 0) {
            showAlert('Semua data offline berhasil disinkronkan!', 'success');
            await loadData(selectedCompanyId(), false);
        } else {
            setError(new Error(`Gagal mensinkronkan ${failed.length} data offline.`));
        }
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
        renderWarehouses();
        renderProducts();
        renderActivities();
        renderSession();
        renderSessionApprovals();
    }

    function renderWarehouses() {
        if (!elements.warehouseList || !elements.productWarehouseSelect) return;

        elements.productWarehouseSelect.innerHTML = state.warehouses
            .map((w) => `<option value="${w.id}">${escapeHtml(w.name)} ${w.location ? `(${escapeHtml(w.location)})` : ''}</option>`)
            .join('');

        elements.warehouseList.innerHTML = state.warehouses.length
            ? state.warehouses.map((w) => `
                <div class="flex items-center justify-between gap-2 py-2 text-sm text-[var(--text)]">
                    <div class="min-w-0">
                        <strong class="block truncate">${escapeHtml(w.name)}</strong>
                        ${w.location ? `<span class="text-xs text-[var(--muted)] block truncate">${escapeHtml(w.location)}</span>` : ''}
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button type="button" class="p-1 text-[var(--brand)] hover:text-[var(--brand-strong)] transition" data-action="edit-warehouse" data-id="${w.id}" data-name="${escapeHtml(w.name)}" data-location="${escapeHtml(w.location)}" aria-label="Edit ${escapeHtml(w.name)}">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M12 20h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="m16.5 3.5 4 4L8 20H4v-4L16.5 3.5Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                            </svg>
                        </button>
                        ${state.warehouses.length > 1 ? `
                            <button type="button" class="p-1 text-[#a12020] hover:text-red-700 transition" data-action="delete-warehouse" data-id="${w.id}" aria-label="Hapus ${escapeHtml(w.name)}">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M4 7h16M10 11v6M14 11v6M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                        ` : ''}
                    </div>
                </div>
            `).join('')
            : '<div class="py-2 text-xs text-[var(--muted)]">Belum ada gudang.</div>';
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
                                <span class="rounded-md bg-[var(--panel-soft)] px-2 py-1 text-xs font-semibold text-[var(--brand)]">📍 ${escapeHtml(state.warehouses.find(w => w.id === product.warehouseId)?.name || 'Utama')}</span>
                                <span class="rounded-md px-2 py-1 text-xs font-bold ${statusClass(status)}">${statusLabel(status)}</span>
                            </div>
                            <div class="product-title-row mt-2">
                                <h3 class="min-w-0 text-lg font-bold text-[var(--text)]">${escapeHtml(product.name)}</h3>
                                <button class="product-info-button disabled:cursor-not-allowed disabled:opacity-50" data-action="edit-info" data-id="${product.id}" data-type="${escapeHtml(product.type)}" data-unit="${escapeHtml(product.unit)}" data-pending-key="edit-info:${product.id}" type="button" aria-label="Edit tipe dan satuan ${escapeHtml(product.name)}">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M12 20h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        <path d="m16.5 3.5 4 4L8 20H4v-4L16.5 3.5Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </div>
                            <p class="text-sm font-semibold text-[var(--muted)]">${escapeHtml(product.type)} · ${escapeHtml(product.unit)}</p>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-center md:min-w-[270px]">
                            <div class="rounded-md bg-[var(--panel-soft)] p-2"><span class="text-xs font-bold text-[var(--muted)]">Sistem</span><strong class="block text-lg text-[var(--text)]">${product.systemStock}</strong></div>
                            <div class="rounded-md bg-[var(--panel-soft)] p-2"><span class="text-xs font-bold text-[var(--muted)]">Fisik</span><strong class="block text-lg text-[var(--text)]">${product.actualStock}</strong></div>
                            <div class="rounded-md bg-[var(--panel-soft)] p-2"><span class="text-xs font-bold text-[var(--muted)]">Selisih</span><strong class="block text-lg text-[var(--text)]">${diff > 0 ? '+' : ''}${diff}</strong></div>
                        </div>
                    </div>
                    <div class="stock-workflow">
                        <div class="stock-workflow-block">
                            <p class="stock-workflow-title">Input hasil hitung fisik</p>
                            <div class="stock-count-row">
                                <input class="field min-h-10 py-2" inputmode="numeric" min="0" data-count-input="${product.id}" type="number" placeholder="Masukkan qty fisik baru" aria-label="Qty fisik ${escapeHtml(product.name)}" />
                                <button class="stock-action stock-action-primary disabled:cursor-not-allowed disabled:opacity-50" data-action="count" data-id="${product.id}" data-pending-key="count:${product.id}" type="button">Simpan fisik</button>
                            </div>
                        </div>
                        <div class="stock-workflow-block">
                            <p class="stock-workflow-title">Koreksi stok fisik cepat</p>
                            <div class="stock-button-grid">
                                <button class="stock-action disabled:cursor-not-allowed disabled:opacity-50" data-action="quick-in" data-id="${product.id}" data-pending-key="quick-in:${product.id}" type="button">Tambah fisik +1</button>
                                <button class="stock-action disabled:cursor-not-allowed disabled:opacity-50" data-action="quick-out" data-id="${product.id}" data-pending-key="quick-out:${product.id}" type="button">Kurangi fisik -1</button>
                                <button class="stock-action disabled:cursor-not-allowed disabled:opacity-50" data-action="sync" data-id="${product.id}" data-pending-key="sync:${product.id}" type="button">Fisik = Sistem</button>
                            </div>
                        </div>
                        ${window.isAdmin ? `
                        <div class="stock-workflow-block">
                            <p class="stock-workflow-title">Kelola master stok</p>
                            <div class="stock-admin-actions">
                                <button class="stock-action disabled:cursor-not-allowed disabled:opacity-50" data-action="edit-system" data-id="${product.id}" data-system-stock="${product.systemStock}" data-pending-key="edit-system:${product.id}" type="button">Edit stok sistem</button>
                                <button class="stock-action stock-action-danger disabled:cursor-not-allowed disabled:opacity-50" data-action="delete-item" data-id="${product.id}" data-pending-key="delete-item:${product.id}" type="button">Hapus produk</button>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </article>
            `;
        }).join('');
    }

    function renderActivities() {
        const rows = state.activities.slice(0, 10).map((activity) => {
            const kindText = { in: 'Tambah', out: 'Minus', count: 'Input opname', sync: 'Samakan', create: 'Barang baru', update: 'Edit stok', delete: 'Hapus produk' }[activity.kind] || activity.kind;
            const time = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(activity.at));

            return `
                <div class="grid gap-1 p-4 sm:grid-cols-[1fr_auto] sm:items-center">
                    <div>
                        <p class="font-bold text-[var(--text)]">${escapeHtml(activity.productName || 'Produk dihapus')}</p>
                        <p class="text-sm text-[var(--muted)]">${kindText} · ${activity.qty} ${activity.unit || ''}</p>
                        <p class="text-xs font-bold text-[var(--brand)]">Oleh ${escapeHtml(activity.actorName || activity.accountName || activity.officer || '-')}</p>
                        ${activity.note ? `<p class="text-xs font-semibold text-[var(--muted)]">${escapeHtml(activity.note)}</p>` : ''}
                    </div>
                    <time class="text-xs font-bold text-[var(--brand)]">${time}</time>
                </div>
            `;
        });

        elements.activityLog.innerHTML = rows.length
            ? rows.join('')
            : '<div class="p-4 text-sm font-semibold text-[var(--muted)]">Belum ada aktivitas.</div>';
    }

    function renderSession() {
        if (!elements.sessionStatusArea) return;

        const session = state.activeSession;
        const pending = state.pendingSession;

        if (session) {
            elements.sessionStatusArea.innerHTML = `
                <div class="rounded-md bg-[#e8f2ff] p-3 text-sm text-[var(--brand-strong)] dark:bg-[#17324d] dark:text-[#85c4ff]">
                    <span class="block font-bold">🟢 Sesi Aktif: ${escapeHtml(session.name)}</span>
                    <span class="text-xs block mt-1">Dibuat oleh: <strong>${escapeHtml(session.creatorName)}</strong></span>
                    <span class="text-xs block">Tanggal: ${new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(session.createdAt))}</span>
                </div>
                <button type="button" id="finalizeSessionBtn" data-action="finalize-session" class="w-full rounded-md bg-[var(--brand)] text-white py-2 px-3 text-xs font-bold transition hover:bg-[var(--brand-strong)] mt-2">
                    Selesaikan & Sinkronisasi
                </button>
            `;
        } else if (pending) {
            elements.sessionStatusArea.innerHTML = `
                <div class="rounded-md bg-[#fdecec] p-3 text-sm text-[#a12020] dark:bg-[#492125] dark:text-[#ff9ca0]">
                    <span class="block font-bold">⏳ Menunggu Persetujuan</span>
                    <span class="text-xs block mt-1 font-semibold">${escapeHtml(pending.name)}</span>
                    <span class="text-xs block mt-1">Diajukan oleh: <strong>${escapeHtml(pending.creatorName)}</strong></span>
                </div>
                <div class="text-xs font-semibold text-[var(--muted)] text-center mt-1">
                    Hubungi supervisor/admin untuk menyetujui selisih penyesuaian stok.
                </div>
            `;
        } else {
            elements.sessionStatusArea.innerHTML = `
                <form id="startSessionForm" class="grid gap-2">
                    <input name="sessionName" required class="field text-xs min-h-8 py-1.5" placeholder="Contoh: Opname Semester 1" />
                    <button class="w-full rounded-md bg-[var(--brand)] text-white py-2 px-3 text-xs font-bold transition hover:bg-[var(--brand-strong)]">
                        Buka Sesi Opname Baru
                    </button>
                </form>
            `;
        }

        renderSessionControls();
    }

    function renderSessionControls() {
        const hasActiveSession = !!state.activeSession;
        
        elements.movementForm?.querySelectorAll('button, select, input').forEach(control => {
            control.disabled = !hasActiveSession || state.products.length === 0;
        });

        elements.productForm?.querySelectorAll('button, select, input').forEach(control => {
            control.disabled = !hasActiveSession;
        });

        document.querySelectorAll('[data-action="count"], [data-action="quick-in"], [data-action="quick-out"], [data-action="sync"], [data-count-input]').forEach(el => {
            el.disabled = !hasActiveSession;
            if (el.tagName === 'INPUT') {
                el.readOnly = !hasActiveSession;
                if (!hasActiveSession) el.placeholder = 'Sesi tidak aktif';
            }
        });
    }

    function renderSessionApprovals() {
        if (!elements.sessionApprovalPanel || !elements.pendingSessionsList) return;

        const pending = state.pendingSession;

        if (pending && window.isAdmin) {
            elements.sessionApprovalPanel.classList.remove('hidden');
            elements.pendingSessionsList.innerHTML = `
                <div class="py-3 flex flex-col gap-2">
                    <div class="min-w-0">
                        <strong class="text-sm block text-[var(--text)]">${escapeHtml(pending.name)}</strong>
                        <span class="text-xs text-[var(--muted)] block">Diajukan oleh: ${escapeHtml(pending.creatorName)}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 mt-1">
                        <button type="button" data-action="approve-session" data-id="${pending.id}" class="rounded bg-[#0f6b4b] hover:bg-[#0b5139] text-white font-bold py-1.5 px-2 text-xs transition text-center">
                            Approve
                        </button>
                        <button type="button" data-action="reject-session" data-id="${pending.id}" class="rounded bg-[#a12020] hover:bg-red-700 text-white font-bold py-1.5 px-2 text-xs transition text-center">
                            Reject
                        </button>
                    </div>
                </div>
            `;
        } else {
            elements.sessionApprovalPanel.classList.add('hidden');
            elements.pendingSessionsList.innerHTML = '';
        }
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

    function openDialog({
        title,
        message,
        confirmText = 'Lanjutkan',
        cancelText = 'Batal',
        danger = false,
        input = null,
        fields = null,
    }) {
        return new Promise((resolve) => {
            const backdrop = document.createElement('div');
            backdrop.className = 'confirm-backdrop';
            const fieldMarkup = fields?.length
                ? `<div class="confirm-field-grid">
                    ${fields.map((field) => `
                        <label class="block">
                            <span class="label">${escapeHtml(field.label)}</span>
                            <input class="field mt-1" data-confirm-field="${escapeHtml(field.name)}" type="${field.type || 'text'}" inputmode="${field.inputmode || 'text'}" min="${field.min ?? ''}" value="${escapeHtml(field.value ?? '')}" />
                        </label>
                    `).join('')}
                </div>`
                : '';

            backdrop.innerHTML = `
                <div class="confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
                    <div class="confirm-dialog-body">
                        <h2 id="confirmTitle" class="confirm-dialog-title">${escapeHtml(title)}</h2>
                        <p class="confirm-dialog-message">${escapeHtml(message)}</p>
                        ${input ? `<input class="field" data-confirm-input type="${input.type || 'text'}" inputmode="${input.inputmode || 'text'}" min="${input.min ?? ''}" value="${escapeHtml(input.value ?? '')}" aria-label="${escapeHtml(input.label || title)}" />` : ''}
                        ${fieldMarkup}
                    </div>
                    <div class="confirm-dialog-actions">
                        <button class="confirm-button" data-confirm-cancel type="button">${escapeHtml(cancelText)}</button>
                        <button class="confirm-button ${danger ? 'confirm-button-danger' : 'confirm-button-primary'}" data-confirm-ok type="button">${escapeHtml(confirmText)}</button>
                    </div>
                </div>
            `;

            const cleanup = (value) => {
                document.removeEventListener('keydown', onKeydown);
                backdrop.remove();
                resolve(value);
            };
            const onKeydown = (event) => {
                if (event.key === 'Escape') cleanup(null);
            };

            backdrop.addEventListener('click', (event) => {
                if (event.target === backdrop || event.target.closest('[data-confirm-cancel]')) cleanup(null);
                if (event.target.closest('[data-confirm-ok]')) {
                    const field = backdrop.querySelector('[data-confirm-input]');
                    if (fields?.length) {
                        cleanup(Object.fromEntries([...backdrop.querySelectorAll('[data-confirm-field]')].map((input) => [input.dataset.confirmField, input.value])));
                        return;
                    }
                    cleanup(field ? field.value : true);
                }
            });
            document.addEventListener('keydown', onKeydown);
            document.body.append(backdrop);
            (backdrop.querySelector('[data-confirm-input]') || backdrop.querySelector('[data-confirm-field]') || backdrop.querySelector('[data-confirm-ok]'))?.focus();
        });
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
                        warehouse_id: Number(data.get('warehouse_id')),
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

        if (button.dataset.action === 'edit-system') {
            const productName = button.closest('.stock-card')?.querySelector('h3')?.textContent || 'stok ini';
            const value = await openDialog({
                title: 'Edit stok sistem',
                message: `Ubah stok sistem untuk ${productName}. Ini memengaruhi angka Sistem dan Selisih.`,
                confirmText: 'Simpan stok sistem',
                input: {
                    type: 'number',
                    inputmode: 'numeric',
                    min: 0,
                    value: button.dataset.systemStock || '0',
                    label: `Stok sistem baru untuk ${productName}`,
                },
            });
            if (value === null) return;

            const systemStock = Number(value);
            if (!Number.isInteger(systemStock) || systemStock < 0) {
                setError(new Error('Stok sistem harus angka bulat minimal 0.'));
                return;
            }

            guardedRequest(`edit-system:${button.dataset.id}`, async () => {
                try {
                    setBusy('Mengedit stok sistem...');
                    state = await request(`/stock-opname/items/${button.dataset.id}`, {
                        method: 'PATCH',
                        body: JSON.stringify({
                            system_stock: systemStock,
                            ...sessionPayload(),
                        }),
                    });
                    setSynced();
                    render();
                } catch (error) {
                    setError(error);
                }
            });
            return;
        }

        if (button.dataset.action === 'edit-info') {
            const productName = button.closest('.stock-card')?.querySelector('h3')?.textContent || 'stok ini';
            const value = await openDialog({
                title: 'Edit info produk',
                message: `Ubah tipe dan satuan untuk ${productName}. Ini tidak mengubah angka Sistem atau Fisik.`,
                confirmText: 'Simpan info produk',
                fields: [
                    {
                        name: 'type',
                        label: 'Tipe produk',
                        value: button.dataset.type || '',
                    },
                    {
                        name: 'unit',
                        label: 'Satuan',
                        value: button.dataset.unit || '',
                    },
                ],
            });
            if (value === null) return;

            const type = String(value.type || '').trim();
            const unit = String(value.unit || '').trim();
            if (!type || !unit) {
                setError(new Error('Tipe dan satuan wajib diisi.'));
                return;
            }

            guardedRequest(`edit-info:${button.dataset.id}`, async () => {
                try {
                    setBusy('Mengedit info produk...');
                    state = await request(`/stock-opname/items/${button.dataset.id}`, {
                        method: 'PATCH',
                        body: JSON.stringify({
                            type,
                            unit,
                            ...sessionPayload(),
                        }),
                    });
                    setSynced();
                    render();
                } catch (error) {
                    setError(error);
                }
            });
            return;
        }

        if (button.dataset.action === 'delete-item') {
            const productName = button.closest('.stock-card')?.querySelector('h3')?.textContent || 'stok ini';
            const confirmed = await openDialog({
                title: 'Hapus produk',
                message: `Hapus ${productName} dari stok aktif? Riwayat mutasi tetap tersimpan.`,
                confirmText: 'Hapus produk',
                danger: true,
            });
            if (!confirmed) return;

            guardedRequest(`delete-item:${button.dataset.id}`, async () => {
                try {
                    setBusy('Menghapus produk...');
                    state = await request(`/stock-opname/items/${button.dataset.id}`, {
                        method: 'DELETE',
                        body: JSON.stringify(sessionPayload()),
                    });
                    setSynced();
                    render();
                } catch (error) {
                    setError(error);
                }
            });
            return;
        }

        if (['quick-in', 'quick-out', 'sync'].includes(button.dataset.action)) {
            const actionLabel = {
                'quick-in': 'tambah stok fisik +1',
                'quick-out': 'kurangi stok fisik -1',
                sync: 'samakan stok fisik dengan stok sistem',
            }[button.dataset.action];
            const productName = button.closest('.stock-card')?.querySelector('h3')?.textContent || 'stok ini';
            const confirmed = await openDialog({
                title: 'Konfirmasi koreksi fisik',
                message: `Aksi ini akan ${actionLabel} untuk ${productName}.`,
                confirmText: 'Ya, lanjutkan',
            });

            if (!confirmed) {
                return;
            }
        }

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

    elements.warehouseForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const data = new FormData(form);

        guardedRequest('warehouse-form', async () => {
            try {
                setBusy('Menyimpan gudang...');
                state = await request('/stock-opname/warehouses', {
                    method: 'POST',
                    body: JSON.stringify({
                        name: data.get('name'),
                        location: data.get('location'),
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

    elements.warehouseList?.addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-action]');
        if (!button) return;

        if (button.dataset.action === 'edit-warehouse') {
            const currentName = button.dataset.name;
            const currentLocation = button.dataset.location;

            const value = await openDialog({
                title: 'Edit gudang',
                message: `Ubah informasi untuk gudang ${currentName}.`,
                confirmText: 'Simpan',
                fields: [
                    {
                        name: 'name',
                        label: 'Nama Gudang',
                        value: currentName,
                    },
                    {
                        name: 'location',
                        label: 'Lokasi Gudang (Opsional)',
                        value: currentLocation,
                    },
                ],
            });
            if (value === null) return;

            const name = String(value.name || '').trim();
            const location = String(value.location || '').trim();
            if (!name) {
                setError(new Error('Nama gudang wajib diisi.'));
                return;
            }

            guardedRequest(`edit-warehouse:${button.dataset.id}`, async () => {
                try {
                    setBusy('Mengedit gudang...');
                    state = await request(`/stock-opname/warehouses/${button.dataset.id}`, {
                        method: 'PATCH',
                        body: JSON.stringify({
                            name,
                            location,
                            ...sessionPayload(),
                        }),
                    });
                    setSynced();
                    render();
                } catch (error) {
                    setError(error);
                }
            });
            return;
        }

        if (button.dataset.action === 'delete-warehouse') {
            const confirmed = await openDialog({
                title: 'Hapus gudang',
                message: 'Hapus gudang ini? Gudang hanya bisa dihapus jika tidak berisi barang dan bukan satu-satunya gudang.',
                confirmText: 'Hapus',
                danger: true,
            });
            if (!confirmed) return;

            guardedRequest(`delete-warehouse:${button.dataset.id}`, async () => {
                try {
                    setBusy('Menghapus gudang...');
                    state = await request(`/stock-opname/warehouses/${button.dataset.id}`, {
                        method: 'DELETE',
                        body: JSON.stringify(sessionPayload()),
                    });
                    setSynced();
                    render();
                } catch (error) {
                    setError(error);
                }
            });
            return;
        }
    });

    // -------------------------------------------------------------
    // AUDIO & SPEECH FEEDBACK
    // -------------------------------------------------------------
    function playSuccessBeep() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            gain.gain.setValueAtTime(0.08, ctx.currentTime);
            osc.start();
            osc.stop(ctx.currentTime + 0.1);
        } catch (e) {
            console.log('Audio feedback not available');
        }
    }

    function playErrorBeep() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.setValueAtTime(220, ctx.currentTime);
            gain.gain.setValueAtTime(0.12, ctx.currentTime);
            osc.start();
            osc.stop(ctx.currentTime + 0.25);
        } catch (e) {
            console.log('Audio feedback not available');
        }
    }

    function speakText(text) {
        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'id-ID';
            utterance.rate = 1.1;
            window.speechSynthesis.speak(utterance);
        }
    }

    // -------------------------------------------------------------
    // BARCODE INPUT & CAMERA SCANNER
    // -------------------------------------------------------------
    function handleScannedBarcode(code) {
        const barcode = code.trim().toLowerCase();
        if (!barcode) return;

        const product = state.products.find(p => p.code.toLowerCase() === barcode);
        if (product) {
            playSuccessBeep();
            speakText(`Ditemukan: ${product.name}`);
            
            elements.searchInput.value = '';
            elements.typeFilter.value = 'all';
            elements.statusFilter.value = 'all';
            renderProducts();

            setTimeout(() => {
                const inputEl = document.querySelector(`[data-count-input="${product.id}"]`);
                if (inputEl) {
                    const card = inputEl.closest('.stock-card');
                    if (card) {
                        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        card.classList.add('border-[var(--brand)]', 'ring-2', 'ring-[var(--brand)]');
                        setTimeout(() => card.classList.remove('border-[var(--brand)]', 'ring-2', 'ring-[var(--brand)]'), 3000);
                    }
                    inputEl.focus();
                    inputEl.select();
                }
            }, 100);
        } else {
            playErrorBeep();
            speakText('Barang tidak ditemukan');
            showAlert(`Produk dengan kode "${code}" tidak ditemukan.`, 'error');
        }
    }

    elements.barcodeInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            const code = elements.barcodeInput.value;
            elements.barcodeInput.value = '';
            handleScannedBarcode(code);
        }
    });

    let html5QrcodeScanner = null;

    elements.scanCameraBtn?.addEventListener('click', async () => {
        if (!elements.scannerModal) return;
        elements.scannerModal.classList.remove('hidden');

        try {
            if (typeof Html5Qrcode === 'undefined') {
                throw new Error('Library kamera scanner gagal dimuat (cek internet).');
            }

            html5QrcodeScanner = new Html5Qrcode("scannerReader");
            await html5QrcodeScanner.start(
                { facingMode: "environment" },
                {
                    fps: 10,
                    qrbox: { width: 250, height: 250 },
                },
                (decodedText) => {
                    stopCameraScanner();
                    handleScannedBarcode(decodedText);
                },
                (errorMessage) => {}
            );
        } catch (err) {
            console.error(err);
            showAlert('Gagal menyalakan kamera: ' + err.message, 'error');
            elements.scannerModal.classList.add('hidden');
        }
    });

    function stopCameraScanner() {
        if (html5QrcodeScanner) {
            html5QrcodeScanner.stop().then(() => {
                html5QrcodeScanner = null;
            }).catch(err => console.error('Failed to stop camera:', err));
        }
        elements.scannerModal?.classList.add('hidden');
    }

    elements.closeScannerBtn?.addEventListener('click', stopCameraScanner);
    elements.stopScannerBtn?.addEventListener('click', stopCameraScanner);

    // -------------------------------------------------------------
    // SESSION EVENT LISTENERS
    // -------------------------------------------------------------
    elements.sessionStatusArea?.addEventListener('submit', async (event) => {
        const form = event.target.closest('#startSessionForm');
        if (!form) return;
        event.preventDefault();
        const data = new FormData(form);
        const name = String(data.get('sessionName') || '').trim();

        if (!name) return;

        guardedRequest('start-session', async () => {
            try {
                setBusy('Membuka sesi...');
                state = await request('/stock-opname/sessions', {
                    method: 'POST',
                    body: JSON.stringify({
                        name,
                        ...sessionPayload(),
                    }),
                });
                setSynced();
                render();
            } catch (error) {
                setError(error);
            }
        });
    });

    elements.sessionStatusArea?.addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-action="finalize-session"]');
        if (!button) return;

        const confirmed = await openDialog({
            title: 'Selesaikan Sesi Opname?',
            message: 'Aksi ini akan menyamakan stok sistem dengan hasil pencatatan fisik dan menutup sesi ini.',
            confirmText: 'Ya, Selesaikan',
        });

        if (!confirmed) return;

        guardedRequest('finalize-session', async () => {
            try {
                setBusy('Finalisasi sesi...');
                const response = await request(`/stock-opname/sessions/${state.activeSession.id}/finalize`, {
                    method: 'POST',
                    body: JSON.stringify(sessionPayload()),
                });

                if (response.pending) {
                    showAlert(response.message, 'success');
                    state = response.payload;
                } else {
                    state = response;
                    showAlert('Sesi opname berhasil diselesaikan!', 'success');
                }
                setSynced();
                render();
            } catch (error) {
                setError(error);
            }
        });
    });

    elements.pendingSessionsList?.addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-action]');
        if (!button) return;

        const action = button.dataset.action;
        const sessionId = button.dataset.id;
        const actionLabel = action === 'approve-session' ? 'menyetujui' : 'menolak';

        const confirmed = await openDialog({
            title: `Konfirmasi ${actionLabel} sesi`,
            message: `Apakah Anda yakin ingin ${actionLabel} sesi opname ini?`,
            confirmText: 'Lanjutkan',
            danger: action === 'reject-session',
        });

        if (!confirmed) return;

        const endpoint = action === 'approve-session' ? 'approve' : 'reject';

        guardedRequest(`${action}:${sessionId}`, async () => {
            try {
                setBusy(`${action === 'approve-session' ? 'Menyetujui' : 'Menolak'} sesi...`);
                state = await request(`/stock-opname/sessions/${sessionId}/${endpoint}`, {
                    method: 'POST',
                    body: JSON.stringify(sessionPayload()),
                });
                showAlert(`Sesi opname berhasil ${action === 'approve-session' ? 'disetujui' : 'ditolak'}.`, 'success');
                setSynced();
                render();
            } catch (error) {
                setError(error);
            }
        });
    });

    // -------------------------------------------------------------
    // ONLINE/OFFLINE EVENT DETECTORS
    // -------------------------------------------------------------
    window.addEventListener('online', () => {
        isOffline = false;
        elements.offlineBanner?.classList.add('hidden');
        flushOfflineQueue().catch(setError);
    });

    window.addEventListener('offline', () => {
        isOffline = true;
        updateOfflineBanner();
    });

    updateOfflineBanner();

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
        elements.navActions?.classList.remove('is-open');
    });
    elements.alertRegion?.addEventListener('click', (event) => {
        if (event.target.closest('[data-alert-close]')) {
            window.clearTimeout(alertTimeout);
            clearAlert();
        }
    });
    applyTheme(localStorage.getItem('gosyen-stock-theme') || 'light');
    loadData().catch(setError);
}
