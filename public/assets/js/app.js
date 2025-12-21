(() => {
    if (!window.APP_BASE) {
        window.APP_BASE = '.';
    }

    const internalLoginForm = document.querySelector('[data-login-form]');
    const portalLoginForm = document.querySelector('[data-portal-login-form]');
    const portalShell = document.querySelector('[data-portal-shell]');
    const portalLogoutButtons = document.querySelectorAll('[data-portal-logout]');

    if (portalLoginForm) {
        initPortalLogin();
        return;
    }

    if (portalShell) {
        portalLogoutButtons.forEach((button) => {
            button.addEventListener('click', async () => {
                try {
                    await fetch(`${window.APP_BASE}/api/customer_auth/logout.php`, { method: 'POST' });
                } catch (error) {
                    // Ignore logout errors and continue to redirect.
                }
                window.location.href = `${window.APP_BASE}/views/portal/home`;
            });
        });
        initPortalDashboard();
        return;
    }

    if (!internalLoginForm) {
        const logoutButtons = document.querySelectorAll('[data-logout]');
        if (logoutButtons.length === 0) {
            initSidebarToggle();
            initShipmentsPage();
            initShipmentCreate();
            initShipmentView();
            initShipmentCustomerOrders();
            initOrdersPage();
            initOrderCreate();
            initCustomersPage();
            initBranchesPage();
            initUsersPage();
            initCustomerCreate();
            initCustomerEdit();
            initCustomerView();
            initAuditPage();
            initReceivingPage();
            return;
        }

        logoutButtons.forEach((button) => {
            button.addEventListener('click', async () => {
                try {
                    await fetch(`${window.APP_BASE}/api/auth/logout.php`, { method: 'POST' });
                } catch (error) {
                    // Ignore logout errors and continue to redirect.
                }
                window.location.href = `${window.APP_BASE}/`;
            });
        });

        initSidebarToggle();
        initShipmentsPage();
        initShipmentCreate();
        initShipmentView();
        initShipmentCustomerOrders();
        initOrdersPage();
        initOrderCreate();
        initCustomersPage();
        initBranchesPage();
        initUsersPage();
        initCustomerCreate();
        initCustomerEdit();
        initCustomerView();
        initAuditPage();
        initReceivingPage();
        return;
    }

    const statusEl = document.querySelector('[data-login-status]');

    internalLoginForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (statusEl) {
            statusEl.textContent = '';
        }

        const formData = new FormData(internalLoginForm);
        const payload = {
            username: (formData.get('username') || '').toString().trim(),
            password: (formData.get('password') || '').toString(),
        };

        if (!payload.username || !payload.password) {
            if (statusEl) {
                statusEl.textContent = 'Please enter your username and password.';
            }
            return;
        }

        try {
            const response = await fetch(`${window.APP_BASE}/api/auth/login.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();

            if (!response.ok || !data.ok) {
                const message = data.error || 'Login failed. Please try again.';
                if (statusEl) {
                    statusEl.textContent = message;
                }
                return;
            }

            const redirectTo = window.INTERNAL_HOME || `${window.APP_BASE}/views/internal/dashboard`;
            window.location.href = redirectTo;
        } catch (error) {
            if (statusEl) {
                statusEl.textContent = 'Network error. Please try again.';
            }
        }
    });

    initShipmentsPage();
    initShipmentCreate();
    initShipmentView();
    initShipmentCustomerOrders();
    initOrdersPage();
    initOrderCreate();
    initCustomersPage();
    initCustomerCreate();
    initCustomerEdit();
    initCustomerView();
    initAuditPage();
    initReceivingPage();
})();

async function fetchJson(url, options = {}) {
    const response = await fetch(url, { credentials: 'same-origin', ...options });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.ok === false) {
        const message = data.error || 'Request failed.';
        throw new Error(message);
    }
    return data;
}

function getUserContext() {
    const shell = document.querySelector('.app-shell');
    if (!shell) {
        return { role: null, branchId: null, branchCountryId: null };
    }
    return {
        role: shell.dataset.userRole || null,
        branchId: shell.dataset.branchId || null,
        branchCountryId: shell.dataset.branchCountryId || null,
    };
}

function hasFullCustomerAccess(role) {
    return ['Admin', 'Owner', 'Main Branch'].includes(role || '');
}

function hasAuditMetaAccess(role) {
    return ['Admin', 'Owner', 'Main Branch'].includes(role || '');
}

function initSidebarToggle() {
    const shell = document.querySelector('.app-shell.internal-shell');
    if (!shell) {
        return;
    }
    const toggles = shell.querySelectorAll('[data-sidebar-toggle]');
    const scrim = shell.querySelector('[data-sidebar-scrim]');

    const setOpen = (open) => {
        if (open) {
            shell.classList.add('sidebar-open');
        } else {
            shell.classList.remove('sidebar-open');
        }
    };

    toggles.forEach((button) => {
        button.addEventListener('click', () => {
            setOpen(!shell.classList.contains('sidebar-open'));
        });
    });

    if (scrim) {
        scrim.addEventListener('click', () => setOpen(false));
    }

    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });
}

function initPortalLogin() {
    const form = document.querySelector('[data-portal-login-form]');
    if (!form) {
        return;
    }

    const statusStack = document.querySelector('[data-portal-login-status]');

    const showNotice = (message, type = 'error') => {
        if (!statusStack) {
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        statusStack.appendChild(notice);
        setTimeout(() => notice.remove(), 6000);
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (statusStack) {
            statusStack.innerHTML = '';
        }

        const formData = new FormData(form);
        const payload = {
            username: (formData.get('username') || '').toString().trim(),
            password: (formData.get('password') || '').toString(),
        };

        if (!payload.username || !payload.password) {
            showNotice('Please enter your username and password.', 'error');
            return;
        }

        try {
            const response = await fetch(`${window.APP_BASE}/api/customer_auth/login.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.ok === false) {
                showNotice(data.error || 'Login failed. Please try again.', 'error');
                return;
            }
            const redirectTo = window.PORTAL_HOME || `${window.APP_BASE}/views/portal/home`;
            window.location.href = redirectTo;
        } catch (error) {
            showNotice('Network error. Please try again.', 'error');
        }
    });
}

function initPortalDashboard() {
    const shell = document.querySelector('[data-portal-shell]');
    if (!shell) {
        return;
    }

    const statusStack = shell.querySelector('[data-portal-status]');
    const nameEl = shell.querySelector('[data-portal-name]');
    const codeEl = shell.querySelector('[data-portal-code]');
    const branchEl = shell.querySelector('[data-portal-branch]');
    const balanceEl = shell.querySelector('[data-portal-balance]');
    const phoneEl = shell.querySelector('[data-portal-phone]');
    const addressEl = shell.querySelector('[data-portal-address]');
    const ordersTable = shell.querySelector('[data-portal-orders]');
    const invoicesTable = shell.querySelector('[data-portal-invoices]');
    const greetingEl = shell.querySelector('[data-portal-greeting]');
    const userName = shell.querySelector('[data-portal-user-name]');
    const userCode = shell.querySelector('[data-portal-user-code]');
    const ordersPrev = shell.querySelector('[data-portal-orders-prev]');
    const ordersNext = shell.querySelector('[data-portal-orders-next]');
    const ordersPageLabel = shell.querySelector('[data-portal-orders-page]');
    const invoicesPrev = shell.querySelector('[data-portal-invoices-prev]');
    const invoicesNext = shell.querySelector('[data-portal-invoices-next]');
    const invoicesPageLabel = shell.querySelector('[data-portal-invoices-page]');
    const pageSize = 5;
    let ordersPage = 0;
    let invoicesPage = 0;
    let ordersData = [];
    let invoicesData = [];

    const showNotice = (message, type = 'error') => {
        if (!statusStack) {
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        statusStack.appendChild(notice);
        setTimeout(() => notice.remove(), 7000);
    };

    const escapeHtml = (value) =>
        String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#39;');

    const paginateRows = (rows, pageIndex) => rows.slice(pageIndex * pageSize, pageIndex * pageSize + pageSize);

    const updatePager = (prevButton, nextButton, label, pageIndex, rows) => {
        if (prevButton) {
            prevButton.disabled = pageIndex === 0;
        }
        if (nextButton) {
            nextButton.disabled = rows.length <= (pageIndex + 1) * pageSize;
        }
        if (label) {
            label.textContent = `Page ${pageIndex + 1}`;
        }
    };

    const renderOrders = (rows) => {
        if (!ordersTable) {
            return;
        }
        if (!rows || rows.length === 0) {
            ordersTable.innerHTML = '<tr><td colspan="5" class="muted">No orders yet.</td></tr>';
            updatePager(ordersPrev, ordersNext, ordersPageLabel, ordersPage, rows || []);
            return;
        }
        const pageRows = paginateRows(rows, ordersPage);
        ordersTable.innerHTML = pageRows
            .map(
                (order) => `<tr>
                    <td>${order.tracking_number || '-'}</td>
                    <td>${order.shipment_number || '-'}</td>
                    <td>${order.fulfillment_status || '-'}</td>
                    <td>${order.total_price || '0.00'}</td>
                    <td>${order.created_at || '-'}</td>
                </tr>`
            )
            .join('');
        updatePager(ordersPrev, ordersNext, ordersPageLabel, ordersPage, rows);
    };

    const renderInvoices = (rows) => {
        if (!invoicesTable) {
            return;
        }
        if (!rows || rows.length === 0) {
            invoicesTable.innerHTML = '<tr><td colspan="5" class="muted">No invoices found.</td></tr>';
            updatePager(invoicesPrev, invoicesNext, invoicesPageLabel, invoicesPage, rows || []);
            return;
        }
        const pageRows = paginateRows(rows, invoicesPage);
        invoicesTable.innerHTML = pageRows
            .map(
                (inv) => `<tr>
                    <td>${inv.invoice_no || '-'}</td>
                    <td>${inv.status || '-'}</td>
                    <td>${inv.total || '0.00'}</td>
                    <td>${inv.due_total || '0.00'}</td>
                    <td>${inv.issued_at || '-'}</td>
                </tr>`
            )
            .join('');
        updatePager(invoicesPrev, invoicesNext, invoicesPageLabel, invoicesPage, rows);
    };

    const loadOverview = async () => {
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/customer_auth/overview.php`);
            const customer = data.customer || {};
            if (nameEl) {
                nameEl.textContent = customer.name || '--';
            }
            if (codeEl) {
                codeEl.textContent = customer.code || '--';
            }
            if (branchEl) {
                branchEl.textContent = customer.sub_branch_name || '--';
            }
            if (balanceEl) {
                balanceEl.textContent = customer.balance ?? '--';
            }
            if (phoneEl) {
                phoneEl.textContent = customer.phone || '--';
            }
            if (addressEl) {
                addressEl.textContent = customer.address || '--';
            }
            if (greetingEl) {
                greetingEl.textContent = `Welcome back, ${customer.name || 'Customer'}`;
            }
            if (userName) {
                userName.textContent = customer.name || 'Customer';
            }
            if (userCode) {
                userCode.textContent = customer.code || '';
            }
            ordersData = data.orders || [];
            invoicesData = data.invoices || [];
            ordersPage = 0;
            invoicesPage = 0;
            renderOrders(ordersData);
            renderInvoices(invoicesData);
        } catch (error) {
            showNotice(`Portal load failed: ${error.message}`, 'error');
            if (ordersTable) {
                ordersTable.innerHTML = '<tr><td colspan="5" class="muted">Unable to load orders.</td></tr>';
            }
            if (invoicesTable) {
                invoicesTable.innerHTML = '<tr><td colspan="5" class="muted">Unable to load invoices.</td></tr>';
            }
            if (String(error.message || '').toLowerCase().includes('unauthorized')) {
                setTimeout(() => {
                    window.location.href = `${window.APP_BASE}/views/portal/home`;
                }, 1200);
            }
        }
    };

    if (ordersPrev) {
        ordersPrev.addEventListener('click', () => {
            if (ordersPage === 0) {
                return;
            }
            ordersPage -= 1;
            renderOrders(ordersData);
        });
    }
    if (ordersNext) {
        ordersNext.addEventListener('click', () => {
            if (ordersData.length <= (ordersPage + 1) * pageSize) {
                return;
            }
            ordersPage += 1;
            renderOrders(ordersData);
        });
    }
    if (invoicesPrev) {
        invoicesPrev.addEventListener('click', () => {
            if (invoicesPage === 0) {
                return;
            }
            invoicesPage -= 1;
            renderInvoices(invoicesData);
        });
    }
    if (invoicesNext) {
        invoicesNext.addEventListener('click', () => {
            if (invoicesData.length <= (invoicesPage + 1) * pageSize) {
                return;
            }
            invoicesPage += 1;
            renderInvoices(invoicesData);
        });
    }

    loadOverview();
}

function initShipmentsPage() {
    const page = document.querySelector('[data-shipments-page]');
    if (!page) {
        return;
    }

    const filterForm = page.querySelector('[data-shipments-filter]');
    const createForm = page.querySelector('[data-shipments-create]');
    const tableBody = page.querySelector('[data-shipments-table]');
    const statusStack = page.querySelector('[data-shipments-status]');
    const refreshButton = page.querySelector('[data-shipments-refresh]');
    const originSelects = page.querySelectorAll('[data-origin-select]');
    const prevButton = page.querySelector('[data-shipments-prev]');
    const nextButton = page.querySelector('[data-shipments-next]');
    const pageLabel = page.querySelector('[data-shipments-page]');
    const { role } = getUserContext();
    const showMeta = hasAuditMetaAccess(role);
    const columnCount = 9;
    const metaClass = 'meta-col';
    const limit = 5;
    let offset = 0;
    let lastFilters = {};

    const showNotice = (message, type = 'error') => {
        if (!statusStack) {
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        statusStack.appendChild(notice);
        setTimeout(() => notice.remove(), 6000);
    };

    const loadCountries = async () => {
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/shipments/origins.php`);
            originSelects.forEach((select) => {
                const current = select.value;
                select.querySelectorAll('option[value]').forEach((option, index) => {
                    if (index === 0) {
                        return;
                    }
                    option.remove();
                });
                data.data.forEach((country) => {
                    const option = document.createElement('option');
                    option.value = country.id;
                    option.textContent = country.name;
                    select.appendChild(option);
                });
                if (current) {
                    select.value = current;
                }
            });
        } catch (error) {
            showNotice(`Origins load failed: ${error.message}`, 'error');
        }
    };

    const renderRows = (rows) => {
        if (!tableBody) {
            return;
        }
        if (!rows.length) {
            tableBody.innerHTML = `<tr><td colspan="${columnCount}" class="muted">No shipments found.</td></tr>`;
            return;
        }
        tableBody.innerHTML = rows
            .map(
                (row) =>
                    `<tr>
                        <td>${row.shipment_number || '-'}</td>
                        <td>${row.origin_country || '-'}</td>
                        <td>${row.status || '-'}</td>
                        <td>${row.shipping_type || '-'}</td>
                        <td>${row.departure_date || '-'}</td>
                        <td>${row.arrival_date || '-'}</td>
                        <td class="${metaClass}">${
                            showMeta && row.created_by_name
                                ? `${row.created_by_name} - ${row.created_at || '-'}`
                                : row.created_at || '-'
                        }</td>
                        <td class="${metaClass}">${
                            showMeta && row.updated_by_name
                                ? `${row.updated_by_name} - ${row.updated_at || '-'}`
                                : row.updated_at || '-'
                        }</td>
                        <td><a class="text-link" href="${window.APP_BASE}/views/internal/shipment_view?id=${row.id}">Open</a></td>
                    </tr>`
            )
            .join('');
    };

    const loadShipments = async (filters = {}) => {
        if (tableBody) {
            tableBody.innerHTML = `<tr><td colspan="${columnCount}" class="muted">Loading shipments...</td></tr>`;
        }
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== null && value !== undefined && String(value).trim() !== '') {
                params.append(key, String(value));
            }
        });
        params.append('limit', String(limit));
        params.append('offset', String(offset));
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/shipments/list.php?${params.toString()}`);
            renderRows(data.data || []);
            if (prevButton) {
                prevButton.disabled = offset === 0;
            }
            if (nextButton) {
                nextButton.disabled = (data.data || []).length < limit;
            }
            if (pageLabel) {
                pageLabel.textContent = `Page ${Math.floor(offset / limit) + 1}`;
            }
        } catch (error) {
            renderRows([]);
            showNotice(`Shipments load failed: ${error.message}`, 'error');
        }
    };

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(filterForm);
            offset = 0;
            lastFilters = Object.fromEntries(formData.entries());
            loadShipments(lastFilters);
        });
    }

    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            if (filterForm) {
                const formData = new FormData(filterForm);
                offset = 0;
                lastFilters = Object.fromEntries(formData.entries());
                loadShipments(lastFilters);
            } else {
                offset = 0;
                lastFilters = {};
                loadShipments();
            }
        });
    }

    if (prevButton) {
        prevButton.addEventListener('click', () => {
            if (offset === 0) {
                return;
            }
            offset = Math.max(0, offset - limit);
            loadShipments(lastFilters);
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => {
            offset += limit;
            loadShipments(lastFilters);
        });
    }

    if (createForm) {
        createForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(createForm);
            const payload = Object.fromEntries(formData.entries());
            try {
                const data = await fetchJson(`${window.APP_BASE}/api/shipments/create.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showNotice(`Shipment created (#${data.id}).`, 'success');
                createForm.reset();
                loadShipments();
            } catch (error) {
                showNotice(`Create failed: ${error.message}`, 'error');
            }
        });
    }

    loadCountries();
    loadShipments();
}

function initShipmentView() {
    const page = document.querySelector('[data-shipment-view]');
    if (!page) {
        return;
    }

    const statusStack = page.querySelector('[data-shipment-status]');
    const editForm = page.querySelector('[data-shipment-edit-form]');
    const editStatus = page.querySelector('[data-shipment-edit-status]');
    const editShipmentIdField = page.querySelector('[data-shipment-id-field]');
    const editPanel = page.querySelector('[data-shipment-edit-panel]');
    const editDrawer = page.querySelector('[data-shipment-edit-drawer]');
    const editDrawerOpen = page.querySelector('[data-shipment-edit-open]');
    const editDrawerTrigger = page.querySelector('[data-shipment-edit-trigger]');
    const editDrawerCloseButtons = page.querySelectorAll('[data-shipment-edit-close]');
    const distributeButton = page.querySelector('[data-shipment-distribute]');
    const collectionForm = page.querySelector('[data-collection-create-form]');
    const addOrderLink = page.querySelector('[data-add-order-link]');
    const originSelects = page.querySelectorAll('[data-origin-select]');
    const details = page.querySelectorAll('[data-detail]');
    const collectionsTable = page.querySelector('[data-collections-table]');
    const ordersTable = page.querySelector('[data-orders-table]');
    const attachmentsTable = page.querySelector('[data-attachments-table]');
    const collectionsPrev = page.querySelector('[data-collections-prev]');
    const collectionsNext = page.querySelector('[data-collections-next]');
    const collectionsPageLabel = page.querySelector('[data-collections-page]');
    const ordersPrev = page.querySelector('[data-orders-prev]');
    const ordersNext = page.querySelector('[data-orders-next]');
    const ordersPageLabel = page.querySelector('[data-orders-page]');
    const attachmentsPrev = page.querySelector('[data-attachments-prev]');
    const attachmentsNext = page.querySelector('[data-attachments-next]');
    const attachmentsPageLabel = page.querySelector('[data-attachments-page]');
    const customerOrdersUrl = page.getAttribute('data-customer-orders-url') || `${window.APP_BASE}/views/internal/shipment_customer_orders`;
    const orderCreateUrl = page.getAttribute('data-order-create-url') || `${window.APP_BASE}/views/internal/order_create`;
    const shipmentPackingUrl = page.getAttribute('data-shipment-packing-url') || `${window.APP_BASE}/api/shipments/packing_list.php`;
    const collectionPackingUrl = page.getAttribute('data-collection-packing-url') || `${window.APP_BASE}/api/collections/packing_list.php`;
    const shipmentMediaForm = page.querySelector('[data-shipment-media-form]');
    const shipmentMediaTable = page.querySelector('[data-shipment-media-table]');
    const shipmentMediaStatus = page.querySelector('[data-shipment-media-status]');
    const shipmentMediaIdField = page.querySelector('[data-shipment-media-id]');
    const shipmentMediaPrev = page.querySelector('[data-shipment-media-prev]');
    const shipmentMediaNext = page.querySelector('[data-shipment-media-next]');
    const shipmentMediaPageLabel = page.querySelector('[data-shipment-media-page]');
    const shipmentPackingViewLink = page.querySelector('[data-shipment-packing-view]');
    const shipmentPackingDownloadLink = page.querySelector('[data-shipment-packing-download]');
    const { role } = getUserContext();
    const canEditRole = ['Admin', 'Owner', 'Main Branch', 'Warehouse'].includes(role || '');
    const canDistributeRole = ['Admin', 'Owner', 'Main Branch'].includes(role || '');
    let canEdit = canEditRole;
    const pageSize = 5;
    let collectionsPage = 0;
    let ordersPage = 0;
    let attachmentsPage = 0;
    let shipmentMediaPage = 0;
    let collectionsData = [];
    let ordersData = [];
    let attachmentsData = [];
    let shipmentMediaData = [];
    let warehouseLockNotified = false;

    const openEditDrawer = () => {
        if (!editDrawer) {
            return;
        }
        editDrawer.classList.add('is-open');
        document.body.classList.add('drawer-open');
    };

    const closeEditDrawer = () => {
        if (!editDrawer) {
            return;
        }
        editDrawer.classList.remove('is-open');
        document.body.classList.remove('drawer-open');
    };

    const showNotice = (message, type = 'error') => {
        if (!statusStack) {
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        statusStack.appendChild(notice);
        setTimeout(() => notice.remove(), 7000);
    };

    const showEditNotice = (message, type = 'error') => {
        if (!editStatus) {
            showNotice(message, type);
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        editStatus.appendChild(notice);
        setTimeout(() => notice.remove(), 7000);
    };

    const showMediaNotice = (message, type = 'error') => {
        if (!shipmentMediaStatus) {
            showNotice(message, type);
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        shipmentMediaStatus.appendChild(notice);
        setTimeout(() => notice.remove(), 7000);
    };

    const params = new URLSearchParams(window.location.search);
    const shipmentId = params.get('id');
    const shipmentNumber = params.get('shipment_number');
    if (!shipmentId && !shipmentNumber) {
        showNotice('Missing shipment id.', 'error');
        return;
    }

    const urlParams = shipmentId ? `shipment_id=${encodeURIComponent(shipmentId)}` : `shipment_number=${encodeURIComponent(shipmentNumber)}`;
    let currentShipmentId = shipmentId ? parseInt(shipmentId, 10) : null;
    let currentShipment = null;
    let pendingOriginId = null;

    const paginateRows = (rows, pageIndex) => rows.slice(pageIndex * pageSize, pageIndex * pageSize + pageSize);

    const updatePager = (prevButton, nextButton, label, pageIndex, rows) => {
        if (prevButton) {
            prevButton.disabled = pageIndex === 0;
        }
        if (nextButton) {
            nextButton.disabled = rows.length <= (pageIndex + 1) * pageSize;
        }
        if (label) {
            label.textContent = `Page ${pageIndex + 1}`;
        }
    };

    const renderCollections = () => {
        if (!collectionsTable) {
            return;
        }
        if (!collectionsData.length) {
            collectionsTable.innerHTML = '<tr><td colspan="2" class="muted">No collections found.</td></tr>';
            updatePager(collectionsPrev, collectionsNext, collectionsPageLabel, collectionsPage, collectionsData);
            return;
        }
        const rows = paginateRows(collectionsData, collectionsPage);
        collectionsTable.innerHTML = rows
            .map((item) => {
                const viewUrl = buildCollectionPackingUrl(item.id, false);
                const downloadUrl = buildCollectionPackingUrl(item.id, true);
                const links = [];
                if (canEdit) {
                    const orderUrl = buildOrderUrl(item.id);
                    links.push(`<a class="text-link" href="${orderUrl}">Add order</a>`);
                }
                links.push(`<a class="text-link" target="_blank" href="${viewUrl}">Packing list</a>`);
                links.push(`<a class="text-link" target="_blank" href="${downloadUrl}">Download</a>`);
                return `<tr>
                    <td>${item.name}</td>
                    <td>${links.join(' | ')}</td>
                </tr>`;
            })
            .join('');
        updatePager(collectionsPrev, collectionsNext, collectionsPageLabel, collectionsPage, collectionsData);
    };

    const renderOrders = (shipment) => {
        if (!ordersTable) {
            return;
        }
        if (!ordersData.length) {
            ordersTable.innerHTML = '<tr><td colspan="5" class="muted">No orders found.</td></tr>';
            updatePager(ordersPrev, ordersNext, ordersPageLabel, ordersPage, ordersData);
            return;
        }
        const rows = paginateRows(ordersData, ordersPage);
        ordersTable.innerHTML = rows
            .map((row) => {
                const qtyValue = row.total_qty !== null && row.total_qty !== undefined ? row.total_qty : null;
                const unitLabel = row.unit_types ? ` (${row.unit_types})` : '';
                const qtyLabel = qtyValue !== null && qtyValue !== '' ? `${qtyValue}${unitLabel}` : '-';
                const link =
                    row.customer_id
                        ? `${customerOrdersUrl}?shipment_id=${encodeURIComponent(shipment.id)}&customer_id=${encodeURIComponent(
                              row.customer_id
                          )}`
                        : '#';
                return `<tr>
                    <td>${row.customer_name || '-'}</td>
                    <td>${row.order_count || 0}</td>
                    <td>${qtyLabel}</td>
                    <td>${row.total_price || '0.00'}</td>
                    <td><a class="text-link" href="${link}">View orders</a></td>
                </tr>`;
            })
            .join('');
        updatePager(ordersPrev, ordersNext, ordersPageLabel, ordersPage, ordersData);
    };

    const renderAttachments = () => {
        if (!attachmentsTable) {
            return;
        }
        if (!attachmentsData.length) {
            attachmentsTable.innerHTML = '<tr><td colspan="5" class="muted">No attachments found.</td></tr>';
            updatePager(attachmentsPrev, attachmentsNext, attachmentsPageLabel, attachmentsPage, attachmentsData);
            return;
        }
        const rows = paginateRows(attachmentsData, attachmentsPage);
        attachmentsTable.innerHTML = rows
            .map((attachment) => {
                const entityLabel = attachment.entity_type === 'order' ? `Order #${attachment.entity_id}` : 'Shipment';
                return `<tr>
                    <td>${attachment.title || attachment.original_name || '-'}</td>
                    <td>${entityLabel}</td>
                    <td>${attachment.mime_type || '-'}</td>
                    <td>${attachment.created_at || '-'}</td>
                    <td><a class="text-link" href="${attachment.download_url}">Download</a></td>
                </tr>`;
            })
            .join('');
        updatePager(attachmentsPrev, attachmentsNext, attachmentsPageLabel, attachmentsPage, attachmentsData);
    };

    const renderShipmentMedia = () => {
        if (!shipmentMediaTable) {
            return;
        }
        if (!shipmentMediaData.length) {
            shipmentMediaTable.innerHTML = '<tr><td colspan="5" class="muted">No shipment media yet.</td></tr>';
            updatePager(shipmentMediaPrev, shipmentMediaNext, shipmentMediaPageLabel, shipmentMediaPage, shipmentMediaData);
            return;
        }
        const rows = paginateRows(shipmentMediaData, shipmentMediaPage);
        shipmentMediaTable.innerHTML = rows
            .map(
                (att) => `<tr>
                    <td>${att.title || att.original_name || '-'}</td>
                    <td>${att.mime_type || '-'}</td>
                    <td>${att.created_at || '-'}</td>
                    <td><a class="text-link" href="${att.download_url}">Download</a></td>
                    <td><button class="button ghost small" type="button" data-shipment-attachment-delete data-attachment-id="${att.id}">Delete</button></td>
                </tr>`
            )
            .join('');

        shipmentMediaTable.querySelectorAll('[data-shipment-attachment-delete]').forEach((button) => {
            button.addEventListener('click', async () => {
                const attachmentId = button.getAttribute('data-attachment-id');
                if (!attachmentId) {
                    return;
                }
                if (!currentShipmentId) {
                    return;
                }
                try {
                    await fetchJson(`${window.APP_BASE}/api/attachments/delete.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: attachmentId }),
                    });
                    showMediaNotice('Attachment removed.', 'success');
                    await loadShipmentMedia(currentShipmentId);
                } catch (error) {
                    showMediaNotice(`Delete failed: ${error.message}`, 'error');
                }
            });
        });
        updatePager(shipmentMediaPrev, shipmentMediaNext, shipmentMediaPageLabel, shipmentMediaPage, shipmentMediaData);
    };
    const loadCountries = async () => {
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/countries/list.php?limit=300`);
            originSelects.forEach((select) => {
                const current = select.value;
                select.querySelectorAll('option[value]').forEach((option, index) => {
                    if (index === 0) {
                        return;
                    }
                    option.remove();
                });
                data.data.forEach((country) => {
                    const option = document.createElement('option');
                    option.value = country.id;
                    option.textContent = country.name;
                    select.appendChild(option);
                });
                if (pendingOriginId) {
                    select.value = String(pendingOriginId);
                } else if (current) {
                    select.value = current;
                }
            });
        } catch (error) {
            showNotice(`Countries load failed: ${error.message}`, 'error');
        }
    };

    const populateEditForm = (shipment) => {
        if (!editForm || !shipment) {
            return;
        }
        if (editShipmentIdField) {
            editShipmentIdField.value = shipment.id || '';
        }
        editForm.querySelector('[name="shipment_number"]').value = shipment.shipment_number || '';
        editForm.querySelector('[name="shipping_type"]').value = shipment.shipping_type || '';
        editForm.querySelector('[name="status"]').value = shipment.status || 'active';
        editForm.querySelector('[name="departure_date"]').value = shipment.departure_date || '';
        editForm.querySelector('[name="arrival_date"]').value = shipment.arrival_date || '';
        editForm.querySelector('[name="default_rate"]').value = shipment.default_rate ?? '';
        editForm.querySelector('[name="default_rate_unit"]').value = shipment.default_rate_unit || '';
        editForm.querySelector('[name="note"]').value = shipment.note || '';
        pendingOriginId = shipment.origin_country_id || '';
        originSelects.forEach((select) => {
            if (pendingOriginId) {
                select.value = String(pendingOriginId);
            }
        });
    };

    const buildOrderUrl = (collectionId) => {
        const joinChar = orderCreateUrl.includes('?') ? '&' : '?';
        return `${orderCreateUrl}${joinChar}collection_id=${encodeURIComponent(collectionId)}`;
    };

    const buildCollectionPackingUrl = (collectionId, download = false) => {
        const params = new URLSearchParams({ collection_id: String(collectionId) });
        if (download) {
            params.append('download', '1');
        }
        return `${collectionPackingUrl}?${params.toString()}`;
    };

    const buildShipmentPackingUrl = (download = false) => {
        const params = new URLSearchParams({ shipment_id: String(currentShipmentId || '') });
        if (download) {
            params.append('download', '1');
        }
        return `${shipmentPackingUrl}?${params.toString()}`;
    };

    const validateDates = () => {
        if (!editForm) {
            return true;
        }
        const departureValue = editForm.querySelector('[name="departure_date"]')?.value || '';
        const arrivalValue = editForm.querySelector('[name="arrival_date"]')?.value || '';
        if (!arrivalValue) {
            return true;
        }
        const arrivalDate = new Date(`${arrivalValue}T00:00:00`);
        if (Number.isNaN(arrivalDate.getTime())) {
            showEditNotice('Arrival date is invalid.', 'error');
            return false;
        }
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (arrivalDate < today) {
            showEditNotice('Arrival date must be today or later.', 'error');
            return false;
        }
        if (departureValue) {
            const departureDate = new Date(`${departureValue}T00:00:00`);
            if (Number.isNaN(departureDate.getTime())) {
                showEditNotice('Departure date is invalid.', 'error');
                return false;
            }
            if (arrivalDate <= departureDate) {
                showEditNotice('Arrival date must be greater than departure date.', 'error');
                return false;
            }
        }
        return true;
    };

    const loadShipment = async () => {
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/shipments/view.php?${urlParams}`);
            const shipment = data.shipment || {};
            const warehouseLocked = role === 'Warehouse' && shipment.status && shipment.status !== 'active';
            canEdit = canEditRole && !warehouseLocked;
            if (editPanel) {
                editPanel.classList.toggle('is-hidden', !canEdit);
            }
            if (editDrawerOpen) {
                editDrawerOpen.classList.toggle('is-hidden', !canEdit);
            }
            if (editDrawerTrigger) {
                editDrawerTrigger.classList.toggle('is-hidden', !canEdit);
            }
            if (!canEdit) {
                closeEditDrawer();
            }
            if (collectionForm) {
                collectionForm.classList.toggle('is-hidden', !canEdit);
            }
            if (addOrderLink) {
                addOrderLink.classList.toggle('is-hidden', !canEdit);
            }
            if (distributeButton) {
                const canDistribute = canDistributeRole && (shipment.status || '') === 'arrived';
                distributeButton.classList.toggle('is-hidden', !canDistribute);
            }
            if (warehouseLocked && !warehouseLockNotified) {
                warehouseLockNotified = true;
                showNotice('Shipment is not active. Warehouse edits are disabled.', 'error');
            }
            currentShipment = shipment;
            currentShipmentId = shipment.id ? parseInt(shipment.id, 10) : currentShipmentId;
            details.forEach((el) => {
                const key = el.getAttribute('data-detail');
                const value = shipment[key];
                el.textContent = value !== null && value !== undefined && value !== '' ? value : '--';
            });
            populateEditForm(shipment);

            collectionsData = data.collections || [];
            ordersData = data.customer_orders || [];
            attachmentsData = data.attachments || [];
            collectionsPage = 0;
            ordersPage = 0;
            attachmentsPage = 0;
            renderCollections();
            renderOrders(shipment);
            renderAttachments();

            if (shipmentMediaIdField && currentShipmentId) {
                shipmentMediaIdField.value = String(currentShipmentId);
            }
            if (shipmentMediaTable && currentShipmentId) {
                loadShipmentMedia(currentShipmentId);
            }
            if (currentShipmentId) {
                const viewUrl = buildShipmentPackingUrl(false);
                const downloadUrl = buildShipmentPackingUrl(true);
                if (shipmentPackingViewLink) {
                    shipmentPackingViewLink.href = viewUrl;
                }
                if (shipmentPackingDownloadLink) {
                    shipmentPackingDownloadLink.href = downloadUrl;
                }
            }
        } catch (error) {
            showNotice(`Failed to load shipment: ${error.message}`, 'error');
        }
    };

    const loadShipmentMedia = async (shipmentIdValue) => {
        if (!shipmentMediaTable) {
            return;
        }
        shipmentMediaTable.innerHTML = '<tr><td colspan="5" class="muted">Loading shipment media...</td></tr>';
        try {
            const data = await fetchJson(
                `${window.APP_BASE}/api/attachments/list.php?entity_type=shipment&entity_id=${encodeURIComponent(
                    shipmentIdValue
                )}`
            );
            shipmentMediaData = data.data || [];
            shipmentMediaPage = 0;
            renderShipmentMedia();
        } catch (error) {
            showMediaNotice(`Media load failed: ${error.message}`, 'error');
        }
    };

    if (editForm) {
        editForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!validateDates()) {
                return;
            }
            if (!currentShipmentId) {
                showEditNotice('Shipment id is missing.', 'error');
                return;
            }
            const formData = new FormData(editForm);
            const payload = Object.fromEntries(formData.entries());
            payload.shipment_id = currentShipmentId;
            try {
                await fetchJson(`${window.APP_BASE}/api/shipments/update.php`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showEditNotice('Shipment updated.', 'success');
                loadShipment();
            } catch (error) {
                showEditNotice(`Update failed: ${error.message}`, 'error');
            }
        });
    }

    if (collectionForm) {
        collectionForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!currentShipmentId) {
                showNotice('Shipment id is missing.', 'error');
                return;
            }
            const formData = new FormData(collectionForm);
            const payload = Object.fromEntries(formData.entries());
            payload.shipment_id = currentShipmentId;
            try {
                await fetchJson(`${window.APP_BASE}/api/collections/create.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showNotice('Collection created.', 'success');
                collectionForm.reset();
                loadShipment();
            } catch (error) {
                showNotice(`Collection create failed: ${error.message}`, 'error');
            }
        });
    }

    if (collectionsPrev) {
        collectionsPrev.addEventListener('click', () => {
            if (collectionsPage === 0) {
                return;
            }
            collectionsPage -= 1;
            renderCollections();
        });
    }
    if (collectionsNext) {
        collectionsNext.addEventListener('click', () => {
            if (collectionsData.length <= (collectionsPage + 1) * pageSize) {
                return;
            }
            collectionsPage += 1;
            renderCollections();
        });
    }
    if (ordersPrev) {
        ordersPrev.addEventListener('click', () => {
            if (ordersPage === 0) {
                return;
            }
            ordersPage -= 1;
            if (currentShipment) {
                renderOrders(currentShipment);
            }
        });
    }
    if (ordersNext) {
        ordersNext.addEventListener('click', () => {
            if (ordersData.length <= (ordersPage + 1) * pageSize) {
                return;
            }
            ordersPage += 1;
            if (currentShipment) {
                renderOrders(currentShipment);
            }
        });
    }
    if (attachmentsPrev) {
        attachmentsPrev.addEventListener('click', () => {
            if (attachmentsPage === 0) {
                return;
            }
            attachmentsPage -= 1;
            renderAttachments();
        });
    }
    if (attachmentsNext) {
        attachmentsNext.addEventListener('click', () => {
            if (attachmentsData.length <= (attachmentsPage + 1) * pageSize) {
                return;
            }
            attachmentsPage += 1;
            renderAttachments();
        });
    }
    if (shipmentMediaPrev) {
        shipmentMediaPrev.addEventListener('click', () => {
            if (shipmentMediaPage === 0) {
                return;
            }
            shipmentMediaPage -= 1;
            renderShipmentMedia();
        });
    }
    if (shipmentMediaNext) {
        shipmentMediaNext.addEventListener('click', () => {
            if (shipmentMediaData.length <= (shipmentMediaPage + 1) * pageSize) {
                return;
            }
            shipmentMediaPage += 1;
            renderShipmentMedia();
        });
    }

    if (shipmentMediaForm) {
        shipmentMediaForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!currentShipmentId) {
                showMediaNotice('Shipment id is missing.', 'error');
                return;
            }
            const fileInput = shipmentMediaForm.querySelector('[name="file"]');
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                showMediaNotice('Choose a file to upload.', 'error');
                return;
            }
            const formData = new FormData(shipmentMediaForm);
            formData.set('entity_type', 'shipment');
            formData.set('entity_id', String(currentShipmentId));
            try {
                const response = await fetch(`${window.APP_BASE}/api/attachments/upload.php`, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || data.ok === false) {
                    throw new Error(data.error || 'Upload failed.');
                }
                showMediaNotice('Shipment media uploaded.', 'success');
                shipmentMediaForm.querySelectorAll('input[type="text"]').forEach((input) => {
                    input.value = '';
                });
                fileInput.value = '';
                loadShipmentMedia(currentShipmentId);
            } catch (error) {
                showMediaNotice(`Upload failed: ${error.message}`, 'error');
            }
        });
    }

    if (editDrawerOpen) {
        editDrawerOpen.addEventListener('click', () => {
            if (!canEdit) {
                showEditNotice('You do not have permission to edit this shipment.', 'error');
                return;
            }
            openEditDrawer();
        });
    }

    if (distributeButton) {
        distributeButton.addEventListener('click', async () => {
            if (!currentShipmentId) {
                showNotice('Shipment id is missing.', 'error');
                return;
            }
            if (!canDistributeRole) {
                showNotice('You do not have permission to distribute shipments.', 'error');
                return;
            }
            distributeButton.disabled = true;
            try {
                const data = await fetchJson(`${window.APP_BASE}/api/shipments/distribute.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ shipment_id: currentShipmentId }),
                });
                const updatedCount = data.updated_orders ?? 0;
                const remainingWithoutBranch = data.remaining_without_branch ?? 0;
                const shipmentDistributed = Boolean(data.shipment_distributed);
                const queuedLabel = updatedCount === 1 ? '1 order' : `${updatedCount} order(s)`;
                const remainingLabel = remainingWithoutBranch === 1
                    ? '1 order'
                    : `${remainingWithoutBranch} order(s)`;
                if (shipmentDistributed) {
                    showNotice(`Shipment distributed. ${queuedLabel} queued for sub-branches.`, 'success');
                } else {
                    showNotice(
                        `${queuedLabel} queued for sub-branches. ${remainingLabel} still missing a branch, so the shipment stays in main branch.`,
                        'success'
                    );
                }
                loadShipment();
            } catch (error) {
                showNotice(`Distribution failed: ${error.message}`, 'error');
            } finally {
                distributeButton.disabled = false;
            }
        });
    }

    if (editDrawerCloseButtons.length) {
        editDrawerCloseButtons.forEach((button) => {
            button.addEventListener('click', closeEditDrawer);
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && editDrawer?.classList.contains('is-open')) {
            closeEditDrawer();
        }
    });

    loadCountries();
    loadShipment();
}

function initReceivingPage() {
    const page = document.querySelector('[data-receiving-page]');
    if (!page) {
        return;
    }

    const statusStack = page.querySelector('[data-receiving-status]');
    const filterForm = page.querySelector('[data-receiving-filter]');
    const refreshButton = page.querySelector('[data-receiving-refresh]');
    const shipmentsTable = page.querySelector('[data-receiving-shipments-table]');
    const shipmentsPrev = page.querySelector('[data-receiving-shipments-prev]');
    const shipmentsNext = page.querySelector('[data-receiving-shipments-next]');
    const shipmentsPageLabel = page.querySelector('[data-receiving-shipments-page]');
    const searchInput = page.querySelector('[data-receiving-search]');
    const branchSelect = page.querySelector('[data-receiving-branch]');

    const unmatchedTable = page.querySelector('[data-receiving-unmatched-table]');
    const unmatchedPrev = page.querySelector('[data-receiving-unmatched-prev]');
    const unmatchedNext = page.querySelector('[data-receiving-unmatched-next]');
    const unmatchedPageLabel = page.querySelector('[data-receiving-unmatched-page]');
    const unmatchedRefresh = page.querySelector('[data-receiving-unmatched-refresh]');

    const { role, branchId } = getUserContext();
    const canManage = ['Admin', 'Owner', 'Main Branch'].includes(role || '');
    const branchIdValue = branchId ? parseInt(branchId, 10) : null;
    const limit = 6;
    const ordersLimit = 200;
    let shipmentsPage = 0;
    let unmatchedPage = 0;
    let shipmentsData = [];
    let unmatchedData = [];
    const openShipments = new Set();

    const showNotice = (message, type = 'error') => {
        if (!statusStack) {
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        statusStack.appendChild(notice);
        setTimeout(() => notice.remove(), 6000);
    };

    const showScanNotice = (container, message, type = 'error') => {
        if (!container) {
            showNotice(message, type);
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        container.appendChild(notice);
        setTimeout(() => notice.remove(), 6000);
    };

    const updatePager = (prevButton, nextButton, label, pageIndex, rows) => {
        if (prevButton) {
            prevButton.disabled = pageIndex === 0;
        }
        if (nextButton) {
            nextButton.disabled = rows.length < limit;
        }
        if (label) {
            label.textContent = `Page ${pageIndex + 1}`;
        }
    };

    const getFilters = () => {
        const searchValue = searchInput ? searchInput.value.trim() : '';
        const branchValue = canManage && branchSelect ? branchSelect.value : '';
        return { searchValue, branchValue };
    };

    const renderShipmentOrders = (shipmentId, rows) => {
        if (!shipmentsTable) {
            return;
        }
        const tbody = shipmentsTable.querySelector(`[data-receiving-orders="${shipmentId}"]`);
        if (!tbody) {
            return;
        }
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="muted">No pending orders for this shipment.</td></tr>';
            return;
        }
        tbody.innerHTML = rows
            .map((row) => {
                const disabled = !row.sub_branch_id || !row.shipment_id || !row.tracking_number;
                return `<tr>
                    <td>${row.tracking_number || '-'}</td>
                    <td>${row.customer_name || row.customer_code || '-'}</td>
                    <td>${row.sub_branch_name || '-'}</td>
                    <td>${row.fulfillment_status || '-'}</td>
                    <td>
                        <button class="button ghost small" type="button" data-receive-order
                            data-tracking="${row.tracking_number || ''}"
                            data-shipment-id="${row.shipment_id || ''}"
                            data-branch-id="${row.sub_branch_id || ''}" ${disabled ? 'disabled' : ''}>
                            Receive
                        </button>
                    </td>
                </tr>`;
            })
            .join('');
    };

    const renderUnmatched = () => {
        if (!unmatchedTable) {
            return;
        }
        if (!unmatchedData.length) {
            unmatchedTable.innerHTML = '<tr><td colspan="5" class="muted">No unmatched scans.</td></tr>';
            updatePager(unmatchedPrev, unmatchedNext, unmatchedPageLabel, unmatchedPage, unmatchedData);
            return;
        }
        unmatchedTable.innerHTML = unmatchedData
            .map(
                (row) => `<tr>
                    <td>${row.tracking_number || '-'}</td>
                    <td>${row.shipment_id || '-'}</td>
                    <td>${row.branch_id || '-'}</td>
                    <td>${row.scanned_at || '-'}</td>
                    <td>${row.note || '-'}</td>
                </tr>`
            )
            .join('');
        updatePager(unmatchedPrev, unmatchedNext, unmatchedPageLabel, unmatchedPage, unmatchedData);
    };

    const loadShipmentOrders = async (shipmentId) => {
        if (!shipmentsTable) {
            return;
        }
        const tbody = shipmentsTable.querySelector(`[data-receiving-orders="${shipmentId}"]`);
        if (!tbody) {
            return;
        }
        tbody.innerHTML = '<tr><td colspan="5" class="muted">Loading pending orders...</td></tr>';
        const params = new URLSearchParams();
        params.append('fulfillment_status', 'pending_receipt');
        params.append('shipment_id', String(shipmentId));
        params.append('limit', String(ordersLimit));
        params.append('offset', '0');
        const { searchValue, branchValue } = getFilters();
        if (searchValue) {
            params.append('q', searchValue);
        }
        if (branchValue) {
            params.append('sub_branch_id', branchValue);
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/orders/list.php?${params.toString()}`);
            renderShipmentOrders(shipmentId, data.data || []);
        } catch (error) {
            renderShipmentOrders(shipmentId, []);
            showNotice(`Pending orders load failed: ${error.message}`, 'error');
        }
    };

    const setShipmentOpen = (shipmentId, open) => {
        if (!shipmentsTable) {
            return;
        }
        const expandRow = shipmentsTable.querySelector(`[data-expand-row="${shipmentId}"]`);
        const toggleButton = shipmentsTable.querySelector(`[data-shipment-toggle="${shipmentId}"]`);
        if (!expandRow) {
            openShipments.delete(shipmentId);
            return;
        }
        if (open) {
            expandRow.classList.add('is-open');
            if (toggleButton) {
                toggleButton.setAttribute('aria-expanded', 'true');
                toggleButton.textContent = 'Hide';
            }
            loadShipmentOrders(shipmentId);
        } else {
            expandRow.classList.remove('is-open');
            if (toggleButton) {
                toggleButton.setAttribute('aria-expanded', 'false');
                toggleButton.textContent = 'View';
            }
        }
    };

    const renderShipments = () => {
        if (!shipmentsTable) {
            return;
        }
        if (!shipmentsData.length) {
            shipmentsTable.innerHTML = '<tr><td colspan="5" class="muted">No pending receipts found.</td></tr>';
            updatePager(shipmentsPrev, shipmentsNext, shipmentsPageLabel, shipmentsPage, shipmentsData);
            return;
        }
        shipmentsTable.innerHTML = shipmentsData
            .map((row) => {
                const shipmentId = row.id || '';
                const isOpen = openShipments.has(String(shipmentId));
                return `<tr>
                    <td>${row.shipment_number || '-'}</td>
                    <td>${row.origin_country || '-'}</td>
                    <td>${row.status || '-'}</td>
                    <td>${row.pending_count || 0}</td>
                    <td>
                        <button class="button ghost small" type="button"
                            data-shipment-toggle="${shipmentId}" aria-expanded="${isOpen ? 'true' : 'false'}">
                            ${isOpen ? 'Hide' : 'View'}
                        </button>
                    </td>
                </tr>
                <tr class="expand-row ${isOpen ? 'is-open' : ''}" data-expand-row="${shipmentId}" data-receiving-expand>
                    <td colspan="5" class="expand-cell">
                        <div class="expand-panel">
                            <div class="expand-header">
                                <div>
                                    <h4>Pending orders</h4>
                                    <p>Scan or enter a tracking number to receive for this shipment.</p>
                                </div>
                            </div>
                            <form class="grid-form" data-receiving-scan-form data-shipment-id="${shipmentId}">
                                <label>
                                    <span>Tracking number</span>
                                    <input type="text" name="tracking_number" required>
                                </label>
                                <label class="full">
                                    <span>Note</span>
                                    <input type="text" name="note" placeholder="Optional note">
                                </label>
                                <button class="button primary small" type="submit">Receive</button>
                            </form>
                            <div class="notice-stack" data-receiving-scan-status></div>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Tracking</th>
                                            <th>Customer</th>
                                            <th>Sub branch</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody data-receiving-orders="${shipmentId}">
                                        <tr><td colspan="5" class="muted">Expand to view pending orders.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </td>
                </tr>`;
            })
            .join('');
        updatePager(shipmentsPrev, shipmentsNext, shipmentsPageLabel, shipmentsPage, shipmentsData);
        openShipments.forEach((shipmentId) => setShipmentOpen(shipmentId, true));
    };

    const loadShipments = async () => {
        if (shipmentsTable) {
            shipmentsTable.innerHTML = '<tr><td colspan="5" class="muted">Loading pending receipts...</td></tr>';
        }
        const params = new URLSearchParams();
        params.append('limit', String(limit));
        params.append('offset', String(shipmentsPage * limit));
        const { searchValue, branchValue } = getFilters();
        if (searchValue) {
            params.append('q', searchValue);
        }
        if (branchValue) {
            params.append('sub_branch_id', branchValue);
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/receiving/shipments.php?${params.toString()}`);
            shipmentsData = data.data || [];
            renderShipments();
        } catch (error) {
            shipmentsData = [];
            renderShipments();
            showNotice(`Pending receipts load failed: ${error.message}`, 'error');
        }
    };

    const loadUnmatched = async () => {
        if (unmatchedTable) {
            unmatchedTable.innerHTML = '<tr><td colspan="5" class="muted">Loading unmatched scans...</td></tr>';
        }
        const params = new URLSearchParams();
        params.append('limit', String(limit));
        params.append('offset', String(unmatchedPage * limit));
        if (canManage && branchSelect && branchSelect.value) {
            params.append('branch_id', branchSelect.value);
        } else if (branchIdValue) {
            params.append('branch_id', String(branchIdValue));
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/receiving/unmatched.php?${params.toString()}`);
            unmatchedData = data.data || [];
            renderUnmatched();
        } catch (error) {
            unmatchedData = [];
            renderUnmatched();
            showNotice(`Unmatched scans load failed: ${error.message}`, 'error');
        }
    };

    const loadBranches = async () => {
        if (!branchSelect || !canManage) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/branches/list.php?type=sub&limit=200`);
            branchSelect.innerHTML = '<option value="">All sub branches</option>';
            (data.data || []).forEach((branch) => {
                const option = document.createElement('option');
                option.value = branch.id;
                option.textContent = branch.name;
                branchSelect.appendChild(option);
            });
        } catch (error) {
            showNotice(`Branches load failed: ${error.message}`, 'error');
        }
    };

    const submitScan = async (payload, statusEl, shipmentId) => {
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/receiving/scan.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            if (data.matched) {
                showScanNotice(statusEl, 'Order marked as received.', 'success');
            } else {
                showScanNotice(statusEl, 'Scan recorded but no order matched.', 'error');
            }
            loadShipments();
            if (shipmentId) {
                loadShipmentOrders(shipmentId);
            }
            loadUnmatched();
        } catch (error) {
            showScanNotice(statusEl, `Scan failed: ${error.message}`, 'error');
        }
    }

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            shipmentsPage = 0;
            unmatchedPage = 0;
            openShipments.clear();
            loadShipments();
            loadUnmatched();
        });
    }

    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            shipmentsPage = 0;
            loadShipments();
        });
    }

    if (shipmentsPrev) {
        shipmentsPrev.addEventListener('click', () => {
            if (shipmentsPage === 0) {
                return;
            }
            shipmentsPage -= 1;
            loadShipments();
        });
    }
    if (shipmentsNext) {
        shipmentsNext.addEventListener('click', () => {
            if (shipmentsData.length < limit) {
                return;
            }
            shipmentsPage += 1;
            loadShipments();
        });
    }

    if (shipmentsTable) {
        shipmentsTable.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            const toggleButton = target.closest('[data-shipment-toggle]');
            if (toggleButton) {
                const shipmentId = toggleButton.getAttribute('data-shipment-toggle');
                if (!shipmentId) {
                    return;
                }
                const isOpen = openShipments.has(String(shipmentId));
                if (isOpen) {
                    openShipments.delete(String(shipmentId));
                    setShipmentOpen(String(shipmentId), false);
                } else {
                    openShipments.add(String(shipmentId));
                    setShipmentOpen(String(shipmentId), true);
                }
                return;
            }
            const receiveButton = target.closest('[data-receive-order]');
            if (receiveButton) {
                const trackingNumber = receiveButton.getAttribute('data-tracking') || '';
                const shipmentIdValue = receiveButton.getAttribute('data-shipment-id') || '';
                const branchIdValueRaw = receiveButton.getAttribute('data-branch-id') || '';
                if (!trackingNumber || !shipmentIdValue) {
                    showNotice('Missing order data for receipt.', 'error');
                    return;
                }
                const statusEl = receiveButton
                    .closest('[data-receiving-expand]')
                    ?.querySelector('[data-receiving-scan-status]');
                const payload = {
                    tracking_number: trackingNumber,
                    shipment_id: parseInt(shipmentIdValue, 10),
                };
                if (branchIdValueRaw) {
                    payload.branch_id = parseInt(branchIdValueRaw, 10);
                }
                submitScan(payload, statusEl, shipmentIdValue);
            }
        });
    }

    page.addEventListener('submit', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLFormElement)) {
            return;
        }
        if (!target.matches('[data-receiving-scan-form]')) {
            return;
        }
        event.preventDefault();
        const trackingInput = target.querySelector('[name="tracking_number"]');
        const noteInput = target.querySelector('[name="note"]');
        const trackingNumber = trackingInput ? trackingInput.value.trim() : '';
        const noteValue = noteInput ? noteInput.value.trim() : '';
        const shipmentIdValue = target.getAttribute('data-shipment-id') || '';
        if (!trackingNumber || !shipmentIdValue) {
            const statusEl = target
                .closest('[data-receiving-expand]')
                ?.querySelector('[data-receiving-scan-status]');
            showScanNotice(statusEl, 'Tracking number is required.', 'error');
            return;
        }
        const { branchValue } = getFilters();
        const payload = {
            tracking_number: trackingNumber,
            shipment_id: parseInt(shipmentIdValue, 10),
            note: noteValue || null,
        };
        if (branchValue) {
            payload.branch_id = parseInt(branchValue, 10);
        }
        const statusEl = target
            .closest('[data-receiving-expand]')
            ?.querySelector('[data-receiving-scan-status]');
        submitScan(payload, statusEl, shipmentIdValue);
        if (trackingInput) {
            trackingInput.value = '';
        }
        if (noteInput) {
            noteInput.value = '';
        }
    });

    if (unmatchedRefresh) {
        unmatchedRefresh.addEventListener('click', () => {
            unmatchedPage = 0;
            loadUnmatched();
        });
    }
    if (unmatchedPrev) {
        unmatchedPrev.addEventListener('click', () => {
            if (unmatchedPage === 0) {
                return;
            }
            unmatchedPage -= 1;
            loadUnmatched();
        });
    }
    if (unmatchedNext) {
        unmatchedNext.addEventListener('click', () => {
            if (unmatchedData.length <= (unmatchedPage + 1) * limit) {
                return;
            }
            unmatchedPage += 1;
            loadUnmatched();
        });
    }

    loadBranches();
    loadShipments();
    loadUnmatched();
}

function initShipmentCustomerOrders() {
    const page = document.querySelector('[data-shipment-customer-orders]');
    if (!page) {
        return;
    }

    const statusStack = page.querySelector('[data-customer-orders-status]');
    const details = page.querySelectorAll('[data-detail]');
    const tableBody = page.querySelector('[data-customer-orders-table]');
    const ordersPrev = page.querySelector('[data-customer-orders-prev]');
    const ordersNext = page.querySelector('[data-customer-orders-next]');
    const ordersPageLabel = page.querySelector('[data-customer-orders-page]');
    const editDrawer = page.querySelector('[data-order-edit-drawer]');
    const editForm = page.querySelector('[data-order-edit-form]');
    const editStatus = page.querySelector('[data-order-edit-status]');
    const editOrderIdField = page.querySelector('[data-order-id-field]');
    const editDrawerCloseButtons = page.querySelectorAll('[data-order-edit-close]');
    const weightTypeSelect = page.querySelector('[data-order-weight-type]');
    const weightActualField = page.querySelector('[data-order-weight-actual]');
    const weightDimensionFields = page.querySelectorAll('[data-order-weight-dimension]');
    const adjustmentsList = page.querySelector('[data-adjustments-list]');
    const adjustmentAddButton = page.querySelector('[data-adjustment-add]');
    const shipmentId = page.getAttribute('data-shipment-id');
    const customerId = page.getAttribute('data-customer-id');
    const canEditAttr = page.getAttribute('data-can-edit') === '1';
    const pageSize = 5;
    let ordersPage = 0;
    let ordersData = [];
    let currentOrder = null;
    const { role } = getUserContext();
    const canEditRole = ['Admin', 'Owner', 'Main Branch', 'Warehouse'].includes(role || '');
    const canEdit = canEditAttr && canEditRole;

    const detailMap = {};
    details.forEach((el) => {
        detailMap[el.getAttribute('data-detail')] = el;
    });

    const showNotice = (message, type = 'error') => {
        if (!statusStack) {
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        statusStack.appendChild(notice);
        setTimeout(() => notice.remove(), 7000);
    };

    const setDetail = (key, value) => {
        const target = detailMap[key];
        if (target) {
            target.textContent = value !== null && value !== undefined && value !== '' ? value : '--';
        }
    };

    const openEditDrawer = () => {
        if (!editDrawer) {
            return;
        }
        editDrawer.classList.add('is-open');
        document.body.classList.add('drawer-open');
    };

    const closeEditDrawer = () => {
        if (!editDrawer) {
            return;
        }
        editDrawer.classList.remove('is-open');
        document.body.classList.remove('drawer-open');
    };

    const showEditNotice = (message, type = 'error') => {
        if (!editStatus) {
            showNotice(message, type);
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        editStatus.appendChild(notice);
        setTimeout(() => notice.remove(), 7000);
    };

    const setWeightFields = (weightType) => {
        const showActual = weightType === 'actual';
        if (weightActualField) {
            weightActualField.classList.toggle('is-hidden', !showActual);
        }
        weightDimensionFields.forEach((field) => {
            field.classList.toggle('is-hidden', showActual);
        });
    };

    const addAdjustmentRow = (adjustment = {}) => {
        if (!adjustmentsList) {
            return;
        }
        const row = document.createElement('div');
        row.className = 'adjustment-row';
        row.setAttribute('data-adjustment-row', '');
        row.innerHTML = `
            <input name="title" type="text" placeholder="Title" value="${adjustment.title || ''}">
            <select name="kind">
                <option value="cost"${adjustment.kind === 'cost' ? ' selected' : ''}>Cost</option>
                <option value="discount"${adjustment.kind === 'discount' ? ' selected' : ''}>Discount</option>
            </select>
            <select name="calc_type">
                <option value="amount"${adjustment.calc_type === 'amount' ? ' selected' : ''}>Amount</option>
                <option value="percentage"${adjustment.calc_type === 'percentage' ? ' selected' : ''}>Percent</option>
            </select>
            <input name="value" type="number" step="0.01" placeholder="0" value="${adjustment.value ?? ''}">
            <input name="description" type="text" placeholder="Notes" value="${adjustment.description || ''}">
            <button class="button ghost small" type="button" data-adjustment-remove>Remove</button>
        `;
        const removeButton = row.querySelector('[data-adjustment-remove]');
        if (removeButton) {
            removeButton.addEventListener('click', () => row.remove());
        }
        adjustmentsList.appendChild(row);
    };

    const loadOrderDetails = async (orderId) => {
        if (!editForm || !editStatus || !editOrderIdField) {
            return;
        }
        editStatus.innerHTML = '';
        try {
            const data = await fetchJson(
                `${window.APP_BASE}/api/orders/view.php?order_id=${encodeURIComponent(orderId)}`
            );
            currentOrder = data.order || null;
            if (!currentOrder) {
                showEditNotice('Order not found.', 'error');
                return;
            }
            editOrderIdField.value = currentOrder.id || '';
            if (weightTypeSelect) {
                weightTypeSelect.value = currentOrder.weight_type || 'actual';
                setWeightFields(weightTypeSelect.value);
            }
            const actualInput = editForm.querySelector('[name="actual_weight"]');
            const wInput = editForm.querySelector('[name="w"]');
            const dInput = editForm.querySelector('[name="d"]');
            const hInput = editForm.querySelector('[name="h"]');
            const rateInput = editForm.querySelector('[name="rate"]');
            if (actualInput) {
                actualInput.value = currentOrder.actual_weight ?? '';
            }
            if (wInput) {
                wInput.value = currentOrder.w ?? '';
            }
            if (dInput) {
                dInput.value = currentOrder.d ?? '';
            }
            if (hInput) {
                hInput.value = currentOrder.h ?? '';
            }
            if (rateInput) {
                rateInput.value = currentOrder.rate ?? '';
            }
            if (adjustmentsList) {
                adjustmentsList.innerHTML = '';
                const adjustments = data.adjustments || [];
                adjustments.forEach((adj) => addAdjustmentRow(adj));
            }
            openEditDrawer();
        } catch (error) {
            showEditNotice(`Order load failed: ${error.message}`, 'error');
        }
    };

    if (!shipmentId || !customerId) {
        showNotice('Missing shipment or customer id.', 'error');
        return;
    }

    const formatNumber = (value, digits) => {
        if (!Number.isFinite(value)) {
            return '0';
        }
        return value.toFixed(digits).replace(/\.?0+$/, '');
    };

    const paginateRows = (rows, pageIndex) => rows.slice(pageIndex * pageSize, pageIndex * pageSize + pageSize);

    const updatePager = (rows) => {
        if (ordersPrev) {
            ordersPrev.disabled = ordersPage === 0;
        }
        if (ordersNext) {
            ordersNext.disabled = rows.length <= (ordersPage + 1) * pageSize;
        }
        if (ordersPageLabel) {
            ordersPageLabel.textContent = `Page ${ordersPage + 1}`;
        }
    };

    const renderOrders = (rows) => {
        if (!tableBody) {
            return;
        }
        if (!rows.length) {
            const colspan = canEdit ? 6 : 5;
            tableBody.innerHTML = `<tr><td colspan="${colspan}" class="muted">No orders found.</td></tr>`;
            updatePager(rows);
            return;
        }
        const pageRows = paginateRows(rows, ordersPage);
        tableBody.innerHTML = pageRows
            .map((row) => {
                const qtyValue = row.qty !== null && row.qty !== undefined ? row.qty : null;
                const unit = row.unit_type ? ` ${row.unit_type}` : '';
                const qtyLabel = qtyValue !== null && qtyValue !== '' ? `${qtyValue}${unit}` : '-';
                const actionCell = canEdit
                    ? `<td><button class="button ghost small" type="button" data-order-edit-open data-order-id="${row.id}">Edit</button></td>`
                    : '';
                return `<tr>
                    <td>${row.tracking_number || '-'}</td>
                    <td>${row.delivery_type || '-'}</td>
                    <td>${qtyLabel}</td>
                    <td>${row.total_price || '0.00'}</td>
                    <td>${row.fulfillment_status || '-'}</td>
                    ${actionCell}
                </tr>`;
            })
            .join('');
        updatePager(rows);
    };

    fetchJson(`${window.APP_BASE}/api/shipments/view.php?shipment_id=${encodeURIComponent(shipmentId)}`)
        .then((data) => {
            const shipment = data.shipment || {};
            setDetail('shipment_number', shipment.shipment_number || '--');
            setDetail('status', shipment.status || '--');
            setDetail('origin_country', shipment.origin_country || '--');
        })
        .catch((error) => {
            showNotice(`Shipment load failed: ${error.message}`, 'error');
        });

    const loadOrders = () =>
        fetchJson(
            `${window.APP_BASE}/api/orders/list.php?shipment_id=${encodeURIComponent(shipmentId)}&customer_id=${encodeURIComponent(
                customerId
            )}&limit=200`
        )
            .then((data) => {
                const rows = data.data || [];
                ordersData = rows;
                ordersPage = 0;
                renderOrders(ordersData);

                const unitTypes = new Set();
                let totalQty = 0;
                let totalPrice = 0;
                rows.forEach((row) => {
                    const qty = parseFloat(row.qty);
                    const price = parseFloat(row.total_price);
                    if (Number.isFinite(qty)) {
                        totalQty += qty;
                    }
                    if (Number.isFinite(price)) {
                        totalPrice += price;
                    }
                    if (row.unit_type) {
                        unitTypes.add(row.unit_type);
                    }
                });

                const unitLabel = unitTypes.size ? ` (${Array.from(unitTypes).join(', ')})` : '';
                const customerName = rows[0]?.customer_name ? rows[0].customer_name : `Customer #${customerId}`;
                setDetail('customer_name', customerName);
                setDetail('order_count', rows.length);
                setDetail('total_qty', `${formatNumber(totalQty, 3)}${unitLabel}`);
                setDetail('total_price', formatNumber(totalPrice, 2));
            })
            .catch((error) => {
                showNotice(`Orders load failed: ${error.message}`, 'error');
            });

    if (ordersPrev) {
        ordersPrev.addEventListener('click', () => {
            if (ordersPage === 0) {
                return;
            }
            ordersPage -= 1;
            renderOrders(ordersData);
        });
    }

    if (ordersNext) {
        ordersNext.addEventListener('click', () => {
            if (ordersData.length <= (ordersPage + 1) * pageSize) {
                return;
            }
            ordersPage += 1;
            renderOrders(ordersData);
        });
    }

    if (tableBody && canEdit) {
        tableBody.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            const button = target.closest('[data-order-edit-open]');
            if (!button) {
                return;
            }
            const orderIdValue = button.getAttribute('data-order-id');
            if (!orderIdValue) {
                return;
            }
            loadOrderDetails(orderIdValue);
        });
    }

    if (weightTypeSelect) {
        weightTypeSelect.addEventListener('change', (event) => {
            setWeightFields(event.target.value);
        });
    }

    if (adjustmentAddButton) {
        adjustmentAddButton.addEventListener('click', () => addAdjustmentRow());
    }

    if (editForm) {
        editForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!currentOrder) {
                showEditNotice('Order is not loaded.', 'error');
                return;
            }
            const weightType = editForm.querySelector('[name="weight_type"]')?.value || 'actual';
            const rateValue = editForm.querySelector('[name="rate"]')?.value || '';
            const payload = {
                order_id: currentOrder.id,
                weight_type: weightType,
                rate: rateValue !== '' ? parseFloat(rateValue) : null,
                adjustments: [],
            };
            if (weightType === 'actual') {
                const actualWeightValue = editForm.querySelector('[name="actual_weight"]')?.value || '';
                payload.actual_weight = actualWeightValue !== '' ? parseFloat(actualWeightValue) : null;
            } else {
                const wValue = editForm.querySelector('[name="w"]')?.value || '';
                const dValue = editForm.querySelector('[name="d"]')?.value || '';
                const hValue = editForm.querySelector('[name="h"]')?.value || '';
                payload.w = wValue !== '' ? parseFloat(wValue) : null;
                payload.d = dValue !== '' ? parseFloat(dValue) : null;
                payload.h = hValue !== '' ? parseFloat(hValue) : null;
            }

            if (adjustmentsList) {
                const rows = adjustmentsList.querySelectorAll('[data-adjustment-row]');
                for (const row of rows) {
                    const title = row.querySelector('[name="title"]')?.value?.trim() || '';
                    const description = row.querySelector('[name="description"]')?.value?.trim() || '';
                    const kind = row.querySelector('[name="kind"]')?.value || 'cost';
                    const calcType = row.querySelector('[name="calc_type"]')?.value || 'amount';
                    const valueRaw = row.querySelector('[name="value"]')?.value || '';
                    if (!title && !description && valueRaw === '') {
                        continue;
                    }
                    if (!title) {
                        showEditNotice('Adjustment title is required.', 'error');
                        return;
                    }
                    payload.adjustments.push({
                        title,
                        description,
                        kind,
                        calc_type: calcType,
                        value: valueRaw !== '' ? parseFloat(valueRaw) : 0,
                    });
                }
            }

            try {
                await fetchJson(`${window.APP_BASE}/api/orders/update.php`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showEditNotice('Order updated.', 'success');
                loadOrders();
                closeEditDrawer();
            } catch (error) {
                showEditNotice(`Update failed: ${error.message}`, 'error');
            }
        });
    }

    if (editDrawerCloseButtons.length) {
        editDrawerCloseButtons.forEach((button) => {
            button.addEventListener('click', closeEditDrawer);
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && editDrawer?.classList.contains('is-open')) {
            closeEditDrawer();
        }
    });

    loadOrders();
}

function initShipmentCreate() {
    const page = document.querySelector('[data-shipment-create]');
    if (!page) {
        return;
    }

    const createForm = page.querySelector('[data-shipments-create]');
    const statusStack = page.querySelector('[data-shipments-status]');
    const originSelects = page.querySelectorAll('[data-origin-select]');
    const { role, branchCountryId } = getUserContext();

    const showNotice = (message, type = 'error') => {
        if (!statusStack) {
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        statusStack.appendChild(notice);
        setTimeout(() => notice.remove(), 6000);
    };

    const loadCountries = async () => {
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/countries/list.php?limit=300`);
            originSelects.forEach((select) => {
                const current = select.value;
                select.querySelectorAll('option[value]').forEach((option, index) => {
                    if (index === 0) {
                        return;
                    }
                    option.remove();
                });
                data.data.forEach((country) => {
                    const option = document.createElement('option');
                    option.value = country.id;
                    option.textContent = country.name;
                    select.appendChild(option);
                });
                if (current) {
                    select.value = current;
                }
            });
            lockWarehouseOrigin();
        } catch (error) {
            showNotice(`Countries load failed: ${error.message}`, 'error');
        }
    };

    const lockWarehouseOrigin = () => {
        if (role !== 'Warehouse' || !branchCountryId) {
            return;
        }
        originSelects.forEach((select) => {
            const value = String(branchCountryId);
            select.value = value;
            select.disabled = true;
            let hidden = select.parentElement?.querySelector('input[data-locked-origin]');
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = select.name || 'origin_country_id';
                hidden.setAttribute('data-locked-origin', 'true');
                select.insertAdjacentElement('afterend', hidden);
            }
            hidden.value = value;
        });
    };

    const validateDates = () => {
        if (!createForm) {
            return true;
        }
        const departureValue = createForm.querySelector('[name="departure_date"]')?.value || '';
        const arrivalValue = createForm.querySelector('[name="arrival_date"]')?.value || '';
        if (!arrivalValue) {
            return true;
        }
        const arrivalDate = new Date(`${arrivalValue}T00:00:00`);
        if (Number.isNaN(arrivalDate.getTime())) {
            showNotice('Arrival date is invalid.', 'error');
            return false;
        }
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (arrivalDate < today) {
            showNotice('Arrival date must be today or later.', 'error');
            return false;
        }
        if (departureValue) {
            const departureDate = new Date(`${departureValue}T00:00:00`);
            if (Number.isNaN(departureDate.getTime())) {
                showNotice('Departure date is invalid.', 'error');
                return false;
            }
            if (arrivalDate <= departureDate) {
                showNotice('Arrival date must be greater than departure date.', 'error');
                return false;
            }
        }
        return true;
    };

    if (createForm) {
        createForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!validateDates()) {
                return;
            }
            const formData = new FormData(createForm);
            const payload = Object.fromEntries(formData.entries());
            try {
                const data = await fetchJson(`${window.APP_BASE}/api/shipments/create.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showNotice(`Shipment created (#${data.id}).`, 'success');
                createForm.reset();
                lockWarehouseOrigin();
            } catch (error) {
                showNotice(`Create failed: ${error.message}`, 'error');
            }
        });
    }

    loadCountries();
}

function initOrdersPage() {
    const page = document.querySelector('[data-orders-page-root]');
    if (!page) {
        return;
    }

    const filterForm = page.querySelector('[data-orders-filter]');
    const statusStack = page.querySelector('[data-orders-status]');
    const refreshButton = page.querySelector('[data-orders-refresh]');
    const { role } = getUserContext();
    const showMeta = hasAuditMetaAccess(role);
    const columnCount = 10;
    const limit = 10;
    let lastFilters = {};
    const statusGroups = [
        { key: 'in_shipment', label: 'In shipment' },
        { key: 'main_branch', label: 'Main branch' },
        { key: 'pending_receipt', label: 'Pending receipt' },
        { key: 'received_subbranch', label: 'Received sub-branch' },
    ];
    const stateByStatus = {};
    statusGroups.forEach((group) => {
        stateByStatus[group.key] = { offset: 0, lastCount: 0 };
    });

    const showNotice = (message, type = 'error') => {
        if (!statusStack) {
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        statusStack.appendChild(notice);
        setTimeout(() => notice.remove(), 6000);
    };

    const renderRows = (tableBody, rows) => {
        if (!tableBody) {
            return;
        }
        if (!rows.length) {
            tableBody.innerHTML = `<tr><td colspan="${columnCount}" class="muted">No orders found.</td></tr>`;
            return;
        }
        tableBody.innerHTML = rows
            .map((order) => {
                const customerLabel = order.customer_name
                    ? order.customer_code
                        ? `${order.customer_name} (${order.customer_code})`
                        : order.customer_name
                    : '-';
                const qtyValue = order.qty !== null && order.qty !== undefined ? order.qty : null;
                const qtyLabel = qtyValue !== null && qtyValue !== '' ? `${qtyValue} ${order.unit_type || ''}`.trim() : '-';
                const createdLabel =
                    showMeta && order.created_by_name
                        ? `${order.created_by_name} - ${order.created_at || '-'}`
                        : order.created_at || '-';
                const updatedLabel =
                    showMeta && order.updated_by_name
                        ? `${order.updated_by_name} - ${order.updated_at || '-'}`
                        : order.updated_at || '-';
                const shipmentLink = order.shipment_id
                    ? `${window.APP_BASE}/views/internal/shipment_view?id=${encodeURIComponent(order.shipment_id)}`
                    : '#';
                return `<tr>
                    <td>${order.tracking_number || '-'}</td>
                    <td>${customerLabel}</td>
                    <td>${order.shipment_number || '-'}</td>
                    <td>${order.sub_branch_name || '-'}</td>
                    <td>${qtyLabel}</td>
                    <td>${order.total_price || '0.00'}</td>
                    <td>${order.fulfillment_status || '-'}</td>
                    <td class="meta-col">${createdLabel}</td>
                    <td class="meta-col">${updatedLabel}</td>
                    <td><a class="text-link" href="${shipmentLink}">Open shipment</a></td>
                </tr>`;
            })
            .join('');
    };

    const loadOrders = async (statusKey, filters = {}) => {
        const tableBody = page.querySelector(`[data-orders-table="${statusKey}"]`);
        const prevButton = page.querySelector(`[data-orders-prev="${statusKey}"]`);
        const nextButton = page.querySelector(`[data-orders-next="${statusKey}"]`);
        const pageLabel = page.querySelector(`[data-orders-page="${statusKey}"]`);
        const state = stateByStatus[statusKey];
        if (!tableBody || !state) {
            return;
        }
        tableBody.innerHTML = `<tr><td colspan="${columnCount}" class="muted">Loading orders...</td></tr>`;
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== null && value !== undefined && String(value).trim() !== '') {
                params.append(key, String(value));
            }
        });
        params.append('fulfillment_status', statusKey);
        params.append('limit', String(limit));
        params.append('offset', String(state.offset));
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/orders/list.php?${params.toString()}`);
            const rows = data.data || [];
            state.lastCount = rows.length;
            renderRows(tableBody, rows);
            if (prevButton) {
                prevButton.disabled = state.offset === 0;
            }
            if (nextButton) {
                nextButton.disabled = rows.length < limit;
            }
            if (pageLabel) {
                pageLabel.textContent = `Page ${Math.floor(state.offset / limit) + 1}`;
            }
        } catch (error) {
            renderRows(tableBody, []);
            showNotice(`Orders load failed: ${error.message}`, 'error');
        }
    };

    const resetOffsets = () => {
        statusGroups.forEach((group) => {
            stateByStatus[group.key].offset = 0;
            stateByStatus[group.key].lastCount = 0;
        });
    };

    const loadAllOrders = () => {
        statusGroups.forEach((group) => {
            loadOrders(group.key, lastFilters);
        });
    };

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(filterForm);
            lastFilters = Object.fromEntries(formData.entries());
            resetOffsets();
            loadAllOrders();
        });
    }

    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            resetOffsets();
            loadAllOrders();
        });
    }

    statusGroups.forEach((group) => {
        const prevButton = page.querySelector(`[data-orders-prev="${group.key}"]`);
        const nextButton = page.querySelector(`[data-orders-next="${group.key}"]`);
        if (prevButton) {
            prevButton.addEventListener('click', () => {
                const state = stateByStatus[group.key];
                if (state.offset === 0) {
                    return;
                }
                state.offset = Math.max(0, state.offset - limit);
                loadOrders(group.key, lastFilters);
            });
        }
        if (nextButton) {
            nextButton.addEventListener('click', () => {
                const state = stateByStatus[group.key];
                if (state.lastCount < limit) {
                    return;
                }
                state.offset += limit;
                loadOrders(group.key, lastFilters);
            });
        }
    });

    loadAllOrders();
}

function initOrderCreate() {
    const page = document.querySelector('[data-order-create]');
    if (!page) {
        return;
    }

    const createForm = page.querySelector('[data-orders-create]');
    const statusStack = page.querySelector('[data-orders-status]');
    const collectionSelect = page.querySelector('[data-collection-select]');
    const customerInput = page.querySelector('[data-customer-input]');
    const customerIdInput = page.querySelector('[data-customer-id]');
    const customerList = page.querySelector('#customer-options');
    const subBranchDisplay = page.querySelector('[data-sub-branch-display]');
    const weightTypeSelect = page.querySelector('[data-weight-type]');
    const unitTypeInput = page.querySelector('[data-unit-type]');
    const unitDisplay = page.querySelector('[data-unit-display]');
    const actualGroups = page.querySelectorAll('[data-weight-actual]');
    const volumeGroups = page.querySelectorAll('[data-weight-volume]');
    const rateInput = createForm?.querySelector('[name="rate"]');
    const trackingInput = createForm?.querySelector('[name="tracking_number"]');
    const submitButton = createForm?.querySelector('button[type="submit"]');
    const { role } = getUserContext();

    const shipmentId = page.getAttribute('data-shipment-id');
    const shipmentNumber = page.getAttribute('data-shipment-number');
    const presetCollectionId = page.getAttribute('data-collection-id');
    const customerMap = new Map();

    const showNotice = (message, type = 'error') => {
        if (!statusStack) {
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        statusStack.appendChild(notice);
        setTimeout(() => notice.remove(), 7000);
    };

    if (!shipmentId && !shipmentNumber) {
        showNotice('Shipment is required to create an order.', 'error');
        return;
    }

    const resolveShipmentId = async () => {
        if (shipmentId || !shipmentNumber) {
            return shipmentId;
        }
        try {
            const data = await fetchJson(
                `${window.APP_BASE}/api/shipments/view.php?shipment_number=${encodeURIComponent(shipmentNumber)}`
            );
            const resolvedId = data.shipment?.id;
            if (resolvedId && createForm) {
                const field = createForm.querySelector('[name=\"shipment_id\"]');
                if (field) {
                    field.value = resolvedId;
                }
            }
            return resolvedId;
        } catch (error) {
            showNotice(`Shipment lookup failed: ${error.message}`, 'error');
            return null;
        }
    };

    const loadCollections = async (resolvedId) => {
        if (!collectionSelect || !resolvedId) {
            return;
        }
        try {
            const data = await fetchJson(
                `${window.APP_BASE}/api/collections/list.php?shipment_id=${encodeURIComponent(resolvedId)}`
            );
            data.data.forEach((collection) => {
                const option = document.createElement('option');
                option.value = collection.id;
                option.textContent = collection.name;
                collectionSelect.appendChild(option);
            });
            if (presetCollectionId) {
                collectionSelect.value = presetCollectionId;
            }
        } catch (error) {
            showNotice(`Collections load failed: ${error.message}`, 'error');
        }
    };

    const loadCustomers = async (query = '') => {
        if (!customerList || !customerInput) {
            return;
        }
        customerList.innerHTML = '';
        customerMap.clear();
        try {
            const params = new URLSearchParams({ limit: '200' });
            if (query) {
                params.append('q', query);
            }
            const data = await fetchJson(`${window.APP_BASE}/api/customers/list.php?${params.toString()}`);
            data.data.forEach((customer) => {
                const phone = customer.phone ? ` - ${customer.phone}` : '';
                const label = `${customer.name} (${customer.code})${phone}`;
                customerMap.set(label, {
                    id: customer.id,
                    name: customer.name || '',
                    code: customer.code || '',
                    subBranchId: customer.sub_branch_id ?? '',
                    subBranchName: customer.sub_branch_name ?? '',
                });
                const option = document.createElement('option');
                option.value = label;
                customerList.appendChild(option);
            });
        } catch (error) {
            showNotice(`Customers load failed: ${error.message}`, 'error');
        }
    };

    const findCustomerMatch = (value) => {
        const normalized = value.toLowerCase();
        if (!normalized) {
            return null;
        }
        for (const [label, data] of customerMap.entries()) {
            if (label.toLowerCase() === normalized) {
                return { label, data };
            }
            if (data.code && data.code.toLowerCase() === normalized) {
                return { label, data };
            }
            if (data.name && data.name.toLowerCase() === normalized) {
                return { label, data };
            }
        }
        return null;
    };

    const syncCustomerBranch = (value = '') => {
        if (!customerIdInput || !subBranchDisplay) {
            return;
        }
        if (!value) {
            customerIdInput.value = '';
            subBranchDisplay.value = 'Select customer first';
            return;
        }
        const match = findCustomerMatch(value);
        const selected = match ? match.data : null;
        if (!selected) {
            customerIdInput.value = '';
            subBranchDisplay.value = '';
            return;
        }
        if (match && customerInput && match.label !== value) {
            customerInput.value = match.label;
        }
        const branchName = selected.subBranchName || '';
        const branchId = selected.subBranchId || '';
        if (!branchId) {
            customerIdInput.value = '';
            subBranchDisplay.value = 'No sub branch assigned';
            showNotice('Selected customer has no sub branch assigned.', 'error');
            return;
        }
        customerIdInput.value = String(selected.id);
        subBranchDisplay.value = branchName || `Branch #${branchId}`;
    };

    const applyWeightMode = () => {
        const mode = weightTypeSelect?.value || 'actual';
        if (mode === 'actual') {
            actualGroups.forEach((el) => el.classList.remove('is-hidden'));
            volumeGroups.forEach((el) => el.classList.add('is-hidden'));
            const actualInput = createForm?.querySelector('[name="actual_weight"]');
            const w = createForm?.querySelector('[name="w"]');
            const d = createForm?.querySelector('[name="d"]');
            const h = createForm?.querySelector('[name="h"]');
            if (actualInput) {
                actualInput.required = true;
            }
            [w, d, h].forEach((input) => {
                if (input) {
                    input.required = false;
                }
            });
            if (unitTypeInput) {
                unitTypeInput.value = 'kg';
            }
            if (unitDisplay) {
                unitDisplay.value = 'KG';
            }
        } else {
            actualGroups.forEach((el) => el.classList.add('is-hidden'));
            volumeGroups.forEach((el) => el.classList.remove('is-hidden'));
            const actualInput = createForm?.querySelector('[name="actual_weight"]');
            const w = createForm?.querySelector('[name="w"]');
            const d = createForm?.querySelector('[name="d"]');
            const h = createForm?.querySelector('[name="h"]');
            if (actualInput) {
                actualInput.required = false;
            }
            [w, d, h].forEach((input) => {
                if (input) {
                    input.required = true;
                }
            });
            if (unitTypeInput) {
                unitTypeInput.value = 'cbm';
            }
            if (unitDisplay) {
                unitDisplay.value = 'CBM';
            }
        }
    };

    const focusWeightField = () => {
        if (!createForm) {
            return;
        }
        const target =
            weightTypeSelect?.value === 'volumetric'
                ? createForm.querySelector('[name="w"]')
                : createForm.querySelector('[name="actual_weight"]');
        if (target) {
            target.focus();
        }
    };

    if (createForm) {
        createForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (customerInput) {
                syncCustomerBranch(customerInput.value.trim());
            }
            if (customerIdInput && !customerIdInput.value) {
                showNotice('Select a valid customer.', 'error');
                return;
            }
            const lastCollection = collectionSelect ? collectionSelect.value : '';
            const formData = new FormData(createForm);
            const payload = Object.fromEntries(formData.entries());
            const resolvedId = await resolveShipmentId();
            if (resolvedId) {
                payload.shipment_id = resolvedId;
            }
            try {
                const data = await fetchJson(`${window.APP_BASE}/api/orders/create.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showNotice(`Order created (#${data.id}).`, 'success');
                createForm.reset();
                if (collectionSelect) {
                    collectionSelect.value = lastCollection;
                }
                if (customerInput) {
                    customerInput.value = '';
                }
                if (customerIdInput) {
                    customerIdInput.value = '';
                }
                syncCustomerBranch('');
                applyWeightMode();
                if (trackingInput) {
                    trackingInput.focus();
                }
            } catch (error) {
                showNotice(`Create failed: ${error.message}`, 'error');
            }
        });
    }

    resolveShipmentId().then((resolvedId) => {
        if (resolvedId) {
            loadCollections(resolvedId);
            fetchJson(`${window.APP_BASE}/api/shipments/view.php?shipment_id=${encodeURIComponent(resolvedId)}`)
                .then((data) => {
                    if (rateInput && data.shipment) {
                        const rateValue = data.shipment.default_rate;
                        if (rateValue !== null && rateValue !== undefined && String(rateValue) !== '') {
                            rateInput.value = rateValue;
                        }
                    }
                    if (data.shipment && role === 'Warehouse' && data.shipment.status !== 'active') {
                        if (createForm) {
                            createForm.classList.add('is-hidden');
                        }
                        showNotice('Shipment is not active. Warehouse orders can only be created while status is active.', 'error');
                    }
                })
                .catch((error) => {
                    showNotice(`Shipment data load failed: ${error.message}`, 'error');
                });
        }
    });
    const refreshCustomers = async (query = '') => {
        await loadCustomers(query);
        if (customerInput) {
            const value = customerInput.value.trim();
            if (value) {
                syncCustomerBranch(value);
            }
        }
    };

    loadCustomers();
    if (customerInput) {
        let searchTimer = null;
        customerInput.addEventListener('input', () => {
            const value = customerInput.value.trim();
            if (searchTimer) {
                clearTimeout(searchTimer);
            }
            if (!value) {
                syncCustomerBranch('');
                searchTimer = setTimeout(() => {
                    loadCustomers();
                }, 200);
                return;
            }
            searchTimer = setTimeout(() => {
                refreshCustomers(value);
            }, 300);
        });
        customerInput.addEventListener('change', () => {
            const value = customerInput.value.trim();
            syncCustomerBranch(value);
            focusWeightField();
        });
        customerInput.addEventListener('blur', () => {
            const value = customerInput.value.trim();
            syncCustomerBranch(value);
        });
    }
    if (weightTypeSelect) {
        weightTypeSelect.addEventListener('change', applyWeightMode);
    }
    if (subBranchDisplay) {
        subBranchDisplay.value = 'Select customer first';
    }
    applyWeightMode();

    if (trackingInput) {
        trackingInput.focus();
        trackingInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                if (customerInput) {
                    customerInput.focus();
                    customerInput.select();
                }
            }
        });
    }

    const weightInputs = createForm
        ? Array.from(createForm.querySelectorAll('[name="actual_weight"], [name="w"], [name="d"], [name="h"]'))
        : [];
    weightInputs.forEach((input) => {
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                if (submitButton) {
                    submitButton.focus();
                }
            }
        });
    });
}

function initCustomersPage() {
    const page = document.querySelector('[data-customers-page]');
    if (!page) {
        return;
    }

    const filterForm = page.querySelector('[data-customers-filter]');
    const tableBody = page.querySelector('[data-customers-table]');
    const statusStack = page.querySelector('[data-customers-status]');
    const refreshButton = page.querySelector('[data-customers-refresh]');
    const branchFilter = page.querySelector('[data-branch-filter]');
    const prevButton = page.querySelector('[data-customers-prev]');
    const nextButton = page.querySelector('[data-customers-next]');
    const pageLabel = page.querySelector('[data-customers-page]');

    const { role, branchId } = getUserContext();
    const fullAccess = hasFullCustomerAccess(role);
    const limit = 5;
    let offset = 0;
    let lastFilters = {};

    const showNotice = (message, type = 'error') => {
        if (!statusStack) {
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        statusStack.appendChild(notice);
        setTimeout(() => notice.remove(), 6000);
    };

    const loadBranches = async () => {
        if (!branchFilter) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/branches/list.php?type=sub&limit=200`);
            data.data.forEach((branch) => {
                const option = document.createElement('option');
                option.value = branch.id;
                option.textContent = branch.name;
                branchFilter.appendChild(option);
            });
        } catch (error) {
            showNotice(`Branches load failed: ${error.message}`, 'error');
        }
    };

    const renderRows = (rows) => {
        if (!tableBody) {
            return;
        }
        if (!rows.length) {
            tableBody.innerHTML = '<tr><td colspan="5" class="muted">No customers found.</td></tr>';
            return;
        }
        tableBody.innerHTML = rows
            .map(
                (row) =>
                    `<tr>
                        <td>${row.name || '-'}</td>
                        <td>${row.code || '-'}</td>
                        <td>${row.sub_branch_name || '-'}</td>
                        <td>${row.balance || '0.00'}</td>
                        <td>
                            <a class="text-link" href="${window.APP_BASE}/views/internal/customer_view?id=${row.id}">Open</a>
                            |
                            <a class="text-link" href="${window.APP_BASE}/views/internal/customer_edit?id=${row.id}">Edit</a>
                        </td>
                    </tr>`
            )
            .join('');
    };

    const loadCustomers = async (filters = {}) => {
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="5" class="muted">Loading customers...</td></tr>';
        }
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== null && value !== undefined && String(value).trim() !== '') {
                params.append(key, String(value));
            }
        });
        params.append('limit', String(limit));
        params.append('offset', String(offset));
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/customers/list.php?${params.toString()}`);
            renderRows(data.data || []);
            if (prevButton) {
                prevButton.disabled = offset === 0;
            }
            if (nextButton) {
                nextButton.disabled = (data.data || []).length < limit;
            }
            if (pageLabel) {
                pageLabel.textContent = `Page ${Math.floor(offset / limit) + 1}`;
            }
        } catch (error) {
            renderRows([]);
            showNotice(`Customers load failed: ${error.message}`, 'error');
        }
    };

    if (!fullAccess && branchFilter) {
        branchFilter.classList.add('is-hidden');
    }

    if (fullAccess) {
        loadBranches();
    }

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(filterForm);
            const filters = Object.fromEntries(formData.entries());
            if (!fullAccess && branchId) {
                filters.sub_branch_id = branchId;
            }
            offset = 0;
            lastFilters = filters;
            loadCustomers(filters);
        });
    }

    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            if (filterForm) {
                const formData = new FormData(filterForm);
                const filters = Object.fromEntries(formData.entries());
                if (!fullAccess && branchId) {
                    filters.sub_branch_id = branchId;
                }
                offset = 0;
                lastFilters = filters;
                loadCustomers(filters);
            } else {
                offset = 0;
                lastFilters = {};
                loadCustomers();
            }
        });
    }

    if (prevButton) {
        prevButton.addEventListener('click', () => {
            if (offset === 0) {
                return;
            }
            offset = Math.max(0, offset - limit);
            loadCustomers(lastFilters);
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => {
            offset += limit;
            loadCustomers(lastFilters);
        });
    }

    loadCustomers();
}

function initBranchesPage() {
    const page = document.querySelector('[data-branches-page]');
    if (!page) {
        return;
    }

    const filterForm = page.querySelector('[data-branches-filter]');
    const tableBody = page.querySelector('[data-branches-table]');
    const statusStack = page.querySelector('[data-branches-status]');
    const refreshButton = page.querySelector('[data-branches-refresh]');
    const prevButton = page.querySelector('[data-branches-prev]');
    const nextButton = page.querySelector('[data-branches-next]');
    const pageLabel = page.querySelector('[data-branches-page-label]');
    const addButton = page.querySelector('[data-branch-add]');
    const drawer = page.querySelector('[data-branch-drawer]');
    const form = page.querySelector('[data-branch-form]');
    const formTitle = page.querySelector('[data-branch-form-title]');
    const submitLabel = page.querySelector('[data-branch-submit-label]');
    const drawerStatus = page.querySelector('[data-branch-form-status]');
    const drawerCloseButtons = page.querySelectorAll('[data-branch-drawer-close]');
    const typeSelect = page.querySelector('[data-branch-type]');
    const countrySelect = page.querySelector('[data-branch-country]');
    const parentSelect = page.querySelector('[data-branch-parent]');
    const branchIdField = page.querySelector('[data-branch-id]');
    const countryNote = page.querySelector('[data-branch-country-note]');
    const typeFilter = page.querySelector('[data-branch-type-filter]');
    const countryFilter = page.querySelector('[data-branch-country-filter]');
    const canEdit = page.getAttribute('data-can-edit') === '1';

    const limit = 5;
    let offset = 0;
    let lastFilters = {};
    const branchMap = new Map();

    const showNotice = (message, type = 'error') => {
        if (!statusStack) {
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        statusStack.appendChild(notice);
        setTimeout(() => notice.remove(), 6000);
    };

    const showFormNotice = (message, type = 'error') => {
        if (!drawerStatus) {
            showNotice(message, type);
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        drawerStatus.appendChild(notice);
        setTimeout(() => notice.remove(), 7000);
    };

    const openDrawer = () => {
        if (!drawer) {
            return;
        }
        drawer.classList.add('is-open');
        document.body.classList.add('drawer-open');
    };

    const closeDrawer = () => {
        if (!drawer) {
            return;
        }
        drawer.classList.remove('is-open');
        document.body.classList.remove('drawer-open');
    };

    const updateCountryNote = () => {
        if (!countryNote || !typeSelect) {
            return;
        }
        const isWarehouse = typeSelect.value === 'warehouse';
        countryNote.classList.toggle('is-hidden', !isWarehouse);
    };

    const clearDynamicOptions = (select) => {
        if (!select) {
            return;
        }
        select.querySelectorAll('option[data-dynamic]').forEach((option) => option.remove());
    };

    const loadCountries = async () => {
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/countries/list.php?limit=300`);
            const countries = data.data || [];
            if (countrySelect) {
                clearDynamicOptions(countrySelect);
                countries.forEach((country) => {
                    const option = document.createElement('option');
                    option.value = country.id;
                    option.textContent = country.name;
                    option.setAttribute('data-dynamic', 'true');
                    countrySelect.appendChild(option);
                });
            }
            if (countryFilter) {
                clearDynamicOptions(countryFilter);
                countries.forEach((country) => {
                    const option = document.createElement('option');
                    option.value = country.id;
                    option.textContent = country.name;
                    option.setAttribute('data-dynamic', 'true');
                    countryFilter.appendChild(option);
                });
            }
        } catch (error) {
            showNotice(`Countries load failed: ${error.message}`, 'error');
        }
    };

    const loadParentBranches = async () => {
        if (!parentSelect) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/branches/list.php?limit=200`);
            clearDynamicOptions(parentSelect);
            (data.data || []).forEach((branch) => {
                const option = document.createElement('option');
                option.value = branch.id;
                option.textContent = branch.name;
                option.setAttribute('data-dynamic', 'true');
                parentSelect.appendChild(option);
            });
        } catch (error) {
            showNotice(`Parent branches load failed: ${error.message}`, 'error');
        }
    };

    const renderRows = (rows) => {
        if (!tableBody) {
            return;
        }
        branchMap.clear();
        if (!rows.length) {
            const colspan = canEdit ? 6 : 5;
            tableBody.innerHTML = `<tr><td colspan="${colspan}" class="muted">No branches found.</td></tr>`;
            return;
        }
        tableBody.innerHTML = rows
            .map((branch) => {
                branchMap.set(String(branch.id), branch);
                const typeLabel = branch.type ? branch.type.charAt(0).toUpperCase() + branch.type.slice(1) : '-';
                const contact = branch.phone || branch.address || '-';
                const actions = canEdit
                    ? `<td>
                            <button class="button ghost small" type="button" data-branch-edit data-branch-id="${branch.id}">Edit</button>
                            <button class="button ghost small" type="button" data-branch-delete data-branch-id="${branch.id}">Delete</button>
                        </td>`
                    : '';
                return `<tr>
                        <td>${branch.name || '-'}</td>
                        <td>${typeLabel}</td>
                        <td>${branch.country_name || '-'}</td>
                        <td>${branch.parent_branch_name || '-'}</td>
                        <td>${contact}</td>
                        ${actions}
                    </tr>`;
            })
            .join('');
    };

    const loadBranches = async (filters = {}) => {
        if (tableBody) {
            const colspan = canEdit ? 6 : 5;
            tableBody.innerHTML = `<tr><td colspan="${colspan}" class="muted">Loading branches...</td></tr>`;
        }
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== null && value !== undefined && String(value).trim() !== '') {
                params.append(key, String(value));
            }
        });
        params.append('limit', String(limit));
        params.append('offset', String(offset));
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/branches/list.php?${params.toString()}`);
            renderRows(data.data || []);
            if (prevButton) {
                prevButton.disabled = offset === 0;
            }
            if (nextButton) {
                nextButton.disabled = (data.data || []).length < limit;
            }
            if (pageLabel) {
                pageLabel.textContent = `Page ${Math.floor(offset / limit) + 1}`;
            }
        } catch (error) {
            renderRows([]);
            showNotice(`Branches load failed: ${error.message}`, 'error');
        }
    };

    const resetForm = () => {
        if (!form) {
            return;
        }
        form.reset();
        if (branchIdField) {
            branchIdField.value = '';
        }
        if (typeSelect) {
            typeSelect.value = 'sub';
        }
        updateCountryNote();
        if (drawerStatus) {
            drawerStatus.innerHTML = '';
        }
    };

    const openForm = (branch) => {
        if (!form) {
            return;
        }
        resetForm();
        if (branch) {
            if (branchIdField) {
                branchIdField.value = branch.id;
            }
            if (formTitle) {
                formTitle.textContent = 'Edit branch';
            }
            if (submitLabel) {
                submitLabel.textContent = 'Save changes';
            }
            form.querySelector('[name="name"]').value = branch.name || '';
            if (typeSelect) {
                typeSelect.value = branch.type || 'sub';
            }
            if (countrySelect) {
                countrySelect.value = branch.country_id || '';
            }
            if (parentSelect) {
                parentSelect.value = branch.parent_branch_id || '';
            }
            form.querySelector('[name="phone"]').value = branch.phone || '';
            form.querySelector('[name="address"]').value = branch.address || '';
        } else {
            if (formTitle) {
                formTitle.textContent = 'Add branch';
            }
            if (submitLabel) {
                submitLabel.textContent = 'Add branch';
            }
        }
        updateCountryNote();
        openDrawer();
    };

    if (typeSelect) {
        typeSelect.addEventListener('change', updateCountryNote);
    }

    if (addButton) {
        addButton.addEventListener('click', () => openForm(null));
    }

    if (drawerCloseButtons.length) {
        drawerCloseButtons.forEach((button) => button.addEventListener('click', closeDrawer));
    }

    if (form) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const name = form.querySelector('[name="name"]').value.trim();
            const type = form.querySelector('[name="type"]').value;
            const countryIdValue = form.querySelector('[name="country_id"]').value;
            const parentValue = form.querySelector('[name="parent_branch_id"]').value;
            const phone = form.querySelector('[name="phone"]').value.trim();
            const address = form.querySelector('[name="address"]').value.trim();
            if (!name || !type || !countryIdValue) {
                showFormNotice('Name, type, and country are required.', 'error');
                return;
            }
            const payload = {
                name,
                type,
                country_id: parseInt(countryIdValue, 10),
                parent_branch_id: parentValue ? parseInt(parentValue, 10) : null,
                phone: phone || null,
                address: address || null,
            };
            const branchIdValue = branchIdField ? branchIdField.value : '';
            try {
                if (branchIdValue) {
                    payload.branch_id = parseInt(branchIdValue, 10);
                    await fetchJson(`${window.APP_BASE}/api/branches/update.php`, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    showFormNotice('Branch updated.', 'success');
                } else {
                    await fetchJson(`${window.APP_BASE}/api/branches/create.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    showFormNotice('Branch created.', 'success');
                }
                closeDrawer();
                loadParentBranches();
                offset = 0;
                loadBranches(lastFilters);
            } catch (error) {
                showFormNotice(`Save failed: ${error.message}`, 'error');
            }
        });
    }

    if (tableBody && canEdit) {
        tableBody.addEventListener('click', async (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            const editButton = target.closest('[data-branch-edit]');
            if (editButton) {
                const branchId = editButton.getAttribute('data-branch-id');
                if (branchId && branchMap.has(branchId)) {
                    openForm(branchMap.get(branchId));
                }
                return;
            }
            const deleteButton = target.closest('[data-branch-delete]');
            if (deleteButton) {
                const branchId = deleteButton.getAttribute('data-branch-id');
                const branch = branchId ? branchMap.get(branchId) : null;
                if (!branchId || !branch) {
                    return;
                }
                const confirmed = window.confirm(`Delete branch "${branch.name}"?`);
                if (!confirmed) {
                    return;
                }
                try {
                    await fetchJson(`${window.APP_BASE}/api/branches/delete.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ branch_id: parseInt(branchId, 10) }),
                    });
                    showNotice('Branch removed.', 'success');
                    offset = 0;
                    loadBranches(lastFilters);
                    loadParentBranches();
                } catch (error) {
                    showNotice(`Delete failed: ${error.message}`, 'error');
                }
            }
        });
    }

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(filterForm);
            lastFilters = Object.fromEntries(formData.entries());
            offset = 0;
            loadBranches(lastFilters);
        });
    }

    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            if (filterForm) {
                const formData = new FormData(filterForm);
                lastFilters = Object.fromEntries(formData.entries());
            } else {
                lastFilters = {};
            }
            offset = 0;
            loadBranches(lastFilters);
        });
    }

    if (prevButton) {
        prevButton.addEventListener('click', () => {
            if (offset === 0) {
                return;
            }
            offset = Math.max(0, offset - limit);
            loadBranches(lastFilters);
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => {
            offset += limit;
            loadBranches(lastFilters);
        });
    }

    loadCountries();
    loadParentBranches();
    loadBranches();
}

function initUsersPage() {
    const page = document.querySelector('[data-users-page]');
    if (!page) {
        return;
    }

    const filterForm = page.querySelector('[data-users-filter]');
    const tableBody = page.querySelector('[data-users-table]');
    const statusStack = page.querySelector('[data-users-status]');
    const refreshButton = page.querySelector('[data-users-refresh]');
    const prevButton = page.querySelector('[data-users-prev]');
    const nextButton = page.querySelector('[data-users-next]');
    const pageLabel = page.querySelector('[data-users-page-label]');
    const addButton = page.querySelector('[data-user-add]');
    const drawer = page.querySelector('[data-user-drawer]');
    const form = page.querySelector('[data-user-form]');
    const formTitle = page.querySelector('[data-user-form-title]');
    const submitLabel = page.querySelector('[data-user-submit-label]');
    const drawerStatus = page.querySelector('[data-user-form-status]');
    const drawerCloseButtons = page.querySelectorAll('[data-user-drawer-close]');
    const roleFilter = page.querySelector('[data-user-role-filter]');
    const branchFilter = page.querySelector('[data-user-branch-filter]');
    const roleSelect = page.querySelector('[data-user-role]');
    const branchSelect = page.querySelector('[data-user-branch]');
    const userIdField = page.querySelector('[data-user-id]');
    const passwordInput = form ? form.querySelector('[name="password"]') : null;
    const canEdit = page.getAttribute('data-can-edit') === '1';

    const limit = 5;
    let offset = 0;
    let lastFilters = {};
    const userMap = new Map();

    const showNotice = (message, type = 'error') => {
        if (!statusStack) {
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        statusStack.appendChild(notice);
        setTimeout(() => notice.remove(), 6000);
    };

    const showFormNotice = (message, type = 'error') => {
        if (!drawerStatus) {
            showNotice(message, type);
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        drawerStatus.appendChild(notice);
        setTimeout(() => notice.remove(), 7000);
    };

    const openDrawer = () => {
        if (!drawer) {
            return;
        }
        drawer.classList.add('is-open');
        document.body.classList.add('drawer-open');
    };

    const closeDrawer = () => {
        if (!drawer) {
            return;
        }
        drawer.classList.remove('is-open');
        document.body.classList.remove('drawer-open');
    };

    const clearDynamicOptions = (select) => {
        if (!select) {
            return;
        }
        select.querySelectorAll('option[data-dynamic]').forEach((option) => option.remove());
    };

    const loadRoles = async () => {
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/roles/list.php`);
            const roles = data.data || [];
            if (roleSelect) {
                clearDynamicOptions(roleSelect);
                roles.forEach((role) => {
                    const option = document.createElement('option');
                    option.value = role.id;
                    option.textContent = role.name;
                    option.setAttribute('data-dynamic', 'true');
                    roleSelect.appendChild(option);
                });
            }
            if (roleFilter) {
                clearDynamicOptions(roleFilter);
                roles.forEach((role) => {
                    const option = document.createElement('option');
                    option.value = role.id;
                    option.textContent = role.name;
                    option.setAttribute('data-dynamic', 'true');
                    roleFilter.appendChild(option);
                });
            }
        } catch (error) {
            showNotice(`Roles load failed: ${error.message}`, 'error');
        }
    };

    const loadBranches = async () => {
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/branches/list.php?limit=200`);
            const branches = data.data || [];
            if (branchSelect) {
                clearDynamicOptions(branchSelect);
                branches.forEach((branch) => {
                    const option = document.createElement('option');
                    option.value = branch.id;
                    option.textContent = branch.name;
                    option.setAttribute('data-dynamic', 'true');
                    branchSelect.appendChild(option);
                });
            }
            if (branchFilter) {
                clearDynamicOptions(branchFilter);
                branches.forEach((branch) => {
                    const option = document.createElement('option');
                    option.value = branch.id;
                    option.textContent = branch.name;
                    option.setAttribute('data-dynamic', 'true');
                    branchFilter.appendChild(option);
                });
            }
        } catch (error) {
            showNotice(`Branches load failed: ${error.message}`, 'error');
        }
    };

    const renderRows = (rows) => {
        if (!tableBody) {
            return;
        }
        userMap.clear();
        if (!rows.length) {
            const colspan = canEdit ? 6 : 5;
            tableBody.innerHTML = `<tr><td colspan="${colspan}" class="muted">No users found.</td></tr>`;
            return;
        }
        tableBody.innerHTML = rows
            .map((row) => {
                userMap.set(String(row.id), row);
                const contact = row.phone || row.address || '-';
                const actions = canEdit
                    ? `<td>
                            <button class="button ghost small" type="button" data-user-edit data-user-id="${row.id}">Edit</button>
                            <button class="button ghost small" type="button" data-user-delete data-user-id="${row.id}">Delete</button>
                        </td>`
                    : '';
                return `<tr>
                        <td>${row.name || '-'}</td>
                        <td>${row.username || '-'}</td>
                        <td>${row.role_name || '-'}</td>
                        <td>${row.branch_name || '-'}</td>
                        <td>${contact}</td>
                        ${actions}
                    </tr>`;
            })
            .join('');
    };

    const loadUsers = async (filters = {}) => {
        if (tableBody) {
            const colspan = canEdit ? 6 : 5;
            tableBody.innerHTML = `<tr><td colspan="${colspan}" class="muted">Loading users...</td></tr>`;
        }
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== null && value !== undefined && String(value).trim() !== '') {
                params.append(key, String(value));
            }
        });
        params.append('limit', String(limit));
        params.append('offset', String(offset));
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/users/list.php?${params.toString()}`);
            renderRows(data.data || []);
            if (prevButton) {
                prevButton.disabled = offset === 0;
            }
            if (nextButton) {
                nextButton.disabled = (data.data || []).length < limit;
            }
            if (pageLabel) {
                pageLabel.textContent = `Page ${Math.floor(offset / limit) + 1}`;
            }
        } catch (error) {
            renderRows([]);
            showNotice(`Users load failed: ${error.message}`, 'error');
        }
    };

    const resetForm = () => {
        if (!form) {
            return;
        }
        form.reset();
        if (userIdField) {
            userIdField.value = '';
        }
        if (passwordInput) {
            passwordInput.required = true;
        }
        if (drawerStatus) {
            drawerStatus.innerHTML = '';
        }
    };

    const openForm = (user) => {
        if (!form) {
            return;
        }
        resetForm();
        if (user) {
            if (userIdField) {
                userIdField.value = user.id;
            }
            if (formTitle) {
                formTitle.textContent = 'Edit user';
            }
            if (submitLabel) {
                submitLabel.textContent = 'Save changes';
            }
            form.querySelector('[name="name"]').value = user.name || '';
            form.querySelector('[name="username"]').value = user.username || '';
            if (roleSelect) {
                roleSelect.value = user.role_id || '';
            }
            if (branchSelect) {
                branchSelect.value = user.branch_id || '';
            }
            form.querySelector('[name="phone"]').value = user.phone || '';
            form.querySelector('[name="address"]').value = user.address || '';
            if (passwordInput) {
                passwordInput.required = false;
                passwordInput.value = '';
            }
        } else {
            if (formTitle) {
                formTitle.textContent = 'Add user';
            }
            if (submitLabel) {
                submitLabel.textContent = 'Add user';
            }
            if (passwordInput) {
                passwordInput.required = true;
            }
        }
        openDrawer();
    };

    if (addButton) {
        addButton.addEventListener('click', () => openForm(null));
    }

    if (drawerCloseButtons.length) {
        drawerCloseButtons.forEach((button) => button.addEventListener('click', closeDrawer));
    }

    if (form) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const name = form.querySelector('[name="name"]').value.trim();
            const username = form.querySelector('[name="username"]').value.trim();
            const password = form.querySelector('[name="password"]').value;
            const roleIdValue = form.querySelector('[name="role_id"]').value;
            const branchValue = form.querySelector('[name="branch_id"]').value;
            const phone = form.querySelector('[name="phone"]').value.trim();
            const address = form.querySelector('[name="address"]').value.trim();

            if (!name || !username || !roleIdValue) {
                showFormNotice('Name, username, and role are required.', 'error');
                return;
            }
            const payload = {
                name,
                username,
                role_id: parseInt(roleIdValue, 10),
                branch_id: branchValue ? parseInt(branchValue, 10) : null,
                phone: phone || null,
                address: address || null,
            };
            const userIdValue = userIdField ? userIdField.value : '';
            if (userIdValue) {
                payload.user_id = parseInt(userIdValue, 10);
                if (password) {
                    payload.password = password;
                }
                try {
                    await fetchJson(`${window.APP_BASE}/api/users/update.php`, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    showFormNotice('User updated.', 'success');
                } catch (error) {
                    showFormNotice(`Save failed: ${error.message}`, 'error');
                    return;
                }
            } else {
                if (!password) {
                    showFormNotice('Password is required for new users.', 'error');
                    return;
                }
                payload.password = password;
                try {
                    await fetchJson(`${window.APP_BASE}/api/users/create.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    showFormNotice('User created.', 'success');
                } catch (error) {
                    showFormNotice(`Save failed: ${error.message}`, 'error');
                    return;
                }
            }
            closeDrawer();
            offset = 0;
            loadUsers(lastFilters);
        });
    }

    if (tableBody && canEdit) {
        tableBody.addEventListener('click', async (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            const editButton = target.closest('[data-user-edit]');
            if (editButton) {
                const userId = editButton.getAttribute('data-user-id');
                if (userId && userMap.has(userId)) {
                    openForm(userMap.get(userId));
                }
                return;
            }
            const deleteButton = target.closest('[data-user-delete]');
            if (deleteButton) {
                const userId = deleteButton.getAttribute('data-user-id');
                const user = userId ? userMap.get(userId) : null;
                if (!userId || !user) {
                    return;
                }
                const confirmed = window.confirm(`Delete user "${user.name}"?`);
                if (!confirmed) {
                    return;
                }
                try {
                    await fetchJson(`${window.APP_BASE}/api/users/delete.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ user_id: parseInt(userId, 10) }),
                    });
                    showNotice('User removed.', 'success');
                    offset = 0;
                    loadUsers(lastFilters);
                } catch (error) {
                    showNotice(`Delete failed: ${error.message}`, 'error');
                }
            }
        });
    }

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(filterForm);
            lastFilters = Object.fromEntries(formData.entries());
            offset = 0;
            loadUsers(lastFilters);
        });
    }

    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            if (filterForm) {
                const formData = new FormData(filterForm);
                lastFilters = Object.fromEntries(formData.entries());
            } else {
                lastFilters = {};
            }
            offset = 0;
            loadUsers(lastFilters);
        });
    }

    if (prevButton) {
        prevButton.addEventListener('click', () => {
            if (offset === 0) {
                return;
            }
            offset = Math.max(0, offset - limit);
            loadUsers(lastFilters);
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => {
            offset += limit;
            loadUsers(lastFilters);
        });
    }

    loadRoles();
    loadBranches();
    loadUsers();
}

function initCustomerCreate() {
    const page = document.querySelector('[data-customer-create]');
    if (!page) {
        return;
    }

    const form = page.querySelector('[data-customer-create-form]');
    const statusStack = page.querySelector('[data-customer-create-status]');
    const branchSelect = page.querySelector('[data-branch-select]');
    const branchField = page.querySelector('[data-branch-field]');
    const { role, branchId } = getUserContext();
    const fullAccess = hasFullCustomerAccess(role);
    const codeInput = form ? form.querySelector('[name="code"]') : null;
    const portalUsernameInput = form ? form.querySelector('[name="portal_username"]') : null;
    const portalPasswordInput = form ? form.querySelector('[name="portal_password"]') : null;
    const phoneInput = form ? form.querySelector('[name="phone"]') : null;

    const showNotice = (message, type = 'error') => {
        if (!statusStack) {
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        statusStack.appendChild(notice);
        setTimeout(() => notice.remove(), 6000);
    };

    const loadBranches = async () => {
        if (!branchSelect) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/branches/list.php?type=sub&limit=200`);
            data.data.forEach((branch) => {
                const option = document.createElement('option');
                option.value = branch.id;
                option.textContent = branch.name;
                branchSelect.appendChild(option);
            });
        } catch (error) {
            showNotice(`Branches load failed: ${error.message}`, 'error');
        }
    };

    if (!fullAccess && branchField) {
        branchField.classList.add('is-hidden');
    }

    if (fullAccess) {
        loadBranches();
    }

    const syncPortalUsername = () => {
        if (!codeInput || !portalUsernameInput) {
            return;
        }
        const codeValue = codeInput.value.trim();
        const manual = portalUsernameInput.dataset.manual === 'true';
        if (!manual || portalUsernameInput.value.trim() === '' || portalUsernameInput.value.trim() === codeValue) {
            portalUsernameInput.value = codeValue;
        }
    };

    if (portalUsernameInput) {
        portalUsernameInput.addEventListener('input', () => {
            portalUsernameInput.dataset.manual = portalUsernameInput.value.trim() === '' ? 'false' : 'true';
        });
    }

    if (codeInput) {
        codeInput.addEventListener('input', syncPortalUsername);
        syncPortalUsername();
    }

    if (form) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(form);
            const payload = Object.fromEntries(formData.entries());
            if (portalUsernameInput && !payload.portal_username) {
                showNotice('Portal username is required.', 'error');
                return;
            }
            if (portalPasswordInput && !payload.portal_password) {
                showNotice('Portal password is required.', 'error');
                return;
            }
            if (phoneInput && payload.phone && String(payload.phone).trim().length < 8) {
                showNotice('Phone number must be at least 8 characters.', 'error');
                return;
            }
            if (!fullAccess && branchId) {
                payload.sub_branch_id = branchId;
            }
            try {
                const data = await fetchJson(`${window.APP_BASE}/api/customers/create.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showNotice(`Customer created (#${data.id}).`, 'success');
                form.reset();
            } catch (error) {
                showNotice(`Create failed: ${error.message}`, 'error');
            }
        });
    }
}

function initCustomerEdit() {
    const page = document.querySelector('[data-customer-edit]');
    if (!page) {
        return;
    }

    const customerId = page.getAttribute('data-customer-id');
    const form = page.querySelector('[data-customer-edit-form]');
    const statusStack = page.querySelector('[data-customer-edit-status]');
    const branchSelect = page.querySelector('[data-branch-select]');
    const branchField = page.querySelector('[data-branch-field]');
    const { role, branchId } = getUserContext();
    const fullAccess = hasFullCustomerAccess(role);
    const portalUsernameInput = form ? form.querySelector('[name="portal_username"]') : null;
    const portalPasswordInput = form ? form.querySelector('[name="portal_password"]') : null;
    const phoneInput = form ? form.querySelector('[name="phone"]') : null;

    const showNotice = (message, type = 'error') => {
        if (!statusStack) {
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        statusStack.appendChild(notice);
        setTimeout(() => notice.remove(), 6000);
    };

    if (!customerId) {
        showNotice('Missing customer id.', 'error');
        return;
    }

    const loadBranches = async () => {
        if (!branchSelect) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/branches/list.php?type=sub&limit=200`);
            data.data.forEach((branch) => {
                const option = document.createElement('option');
                option.value = branch.id;
                option.textContent = branch.name;
                branchSelect.appendChild(option);
            });
        } catch (error) {
            showNotice(`Branches load failed: ${error.message}`, 'error');
        }
    };

    const populateForm = (customer) => {
        if (!form) {
            return;
        }
        form.querySelector('[name="name"]').value = customer.name || '';
        form.querySelector('[name="code"]').value = customer.code || '';
        form.querySelector('[name="phone"]').value = customer.phone || '';
        form.querySelector('[name="address"]').value = customer.address || '';
        if (portalUsernameInput) {
            portalUsernameInput.value = customer.portal_username || '';
        }
        if (branchSelect && customer.sub_branch_id) {
            branchSelect.value = customer.sub_branch_id;
        }
    };

    if (!fullAccess && branchField) {
        branchField.classList.add('is-hidden');
    }

    if (fullAccess) {
        loadBranches();
    }

    fetchJson(`${window.APP_BASE}/api/customers/view.php?customer_id=${encodeURIComponent(customerId)}`)
        .then((data) => {
            populateForm(data.customer);
        })
        .catch((error) => {
            showNotice(`Load failed: ${error.message}`, 'error');
        });

    if (form) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(form);
            const payload = Object.fromEntries(formData.entries());
            if (portalUsernameInput && !payload.portal_username) {
                showNotice('Portal username is required.', 'error');
                return;
            }
            if (payload.portal_password !== undefined && String(payload.portal_password).trim() === '') {
                delete payload.portal_password;
            }
            if (phoneInput && payload.phone && String(payload.phone).trim().length < 8) {
                showNotice('Phone number must be at least 8 characters.', 'error');
                return;
            }
            payload.customer_id = customerId;
            if (!fullAccess) {
                delete payload.sub_branch_id;
            }
            try {
                await fetchJson(`${window.APP_BASE}/api/customers/update.php`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showNotice('Customer updated.', 'success');
            } catch (error) {
                showNotice(`Update failed: ${error.message}`, 'error');
            }
        });
    }
}

function initCustomerView() {
    const page = document.querySelector('[data-customer-view]');
    if (!page) {
        return;
    }

    const customerId = page.getAttribute('data-customer-id');
    const statusStack = page.querySelector('[data-customer-view-status]');
    const details = page.querySelectorAll('[data-detail]');
    const invoicesTable = page.querySelector('[data-customer-invoices]');
    const transactionsTable = page.querySelector('[data-customer-transactions]');
    const ordersTable = page.querySelector('[data-customer-orders]');
    const reassignSelect = page.querySelector('[data-reassign-customer]');
    const reassignButton = page.querySelector('[data-reassign-submit]');
    const selectAllOrders = page.querySelector('[data-orders-select-all]');
    const mediaPanel = page.querySelector('[data-order-media-panel]');
    const mediaTitle = page.querySelector('[data-order-media-title]');
    const mediaForm = page.querySelector('[data-order-media-form]');
    const mediaIdField = page.querySelector('[data-order-media-id]');
    const mediaTable = page.querySelector('[data-order-media-table]');
    const mediaStatus = page.querySelector('[data-order-media-status]');
    const invoicesPrev = page.querySelector('[data-customer-invoices-prev]');
    const invoicesNext = page.querySelector('[data-customer-invoices-next]');
    const invoicesPageLabel = page.querySelector('[data-customer-invoices-page]');
    const transactionsPrev = page.querySelector('[data-customer-transactions-prev]');
    const transactionsNext = page.querySelector('[data-customer-transactions-next]');
    const transactionsPageLabel = page.querySelector('[data-customer-transactions-page]');
    const ordersPrev = page.querySelector('[data-customer-orders-prev]');
    const ordersNext = page.querySelector('[data-customer-orders-next]');
    const ordersPageLabel = page.querySelector('[data-customer-orders-page]');
    const mediaPrev = page.querySelector('[data-order-media-prev]');
    const mediaNext = page.querySelector('[data-order-media-next]');
    const mediaPageLabel = page.querySelector('[data-order-media-page]');
    const noteSearchInput = page.querySelector('[data-order-note-search]');
    const noteSearchButton = page.querySelector('[data-order-note-submit]');
    const { role } = getUserContext();
    const showMeta = hasAuditMetaAccess(role);
    const ordersColumnCount = 9;
    const metaClass = 'meta-col';
    const pageSize = 5;
    let invoicesPage = 0;
    let transactionsPage = 0;
    let ordersPage = 0;
    let mediaPage = 0;
    let invoicesData = [];
    let transactionsData = [];
    let ordersData = [];
    let mediaData = [];
    let currentMediaOrderId = null;

    const showNotice = (message, type = 'error') => {
        if (!statusStack) {
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        statusStack.appendChild(notice);
        setTimeout(() => notice.remove(), 6000);
    };

    if (!customerId) {
        showNotice('Missing customer id.', 'error');
        return;
    }

    const showMediaNotice = (message, type = 'error') => {
        if (!mediaStatus) {
            showNotice(message, type);
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        mediaStatus.appendChild(notice);
        setTimeout(() => notice.remove(), 6000);
    };

    const paginateRows = (rows, pageIndex) => rows.slice(pageIndex * pageSize, pageIndex * pageSize + pageSize);

    const updatePager = (prevButton, nextButton, label, pageIndex, rows) => {
        if (prevButton) {
            prevButton.disabled = pageIndex === 0;
        }
        if (nextButton) {
            nextButton.disabled = rows.length <= (pageIndex + 1) * pageSize;
        }
        if (label) {
            label.textContent = `Page ${pageIndex + 1}`;
        }
    };

    const renderInvoices = (rows) => {
        if (!invoicesTable) {
            return;
        }
        if (!rows || rows.length === 0) {
            invoicesTable.innerHTML = '<tr><td colspan="5" class="muted">No invoices found.</td></tr>';
            updatePager(invoicesPrev, invoicesNext, invoicesPageLabel, invoicesPage, rows || []);
            return;
        }
        const pageRows = paginateRows(rows, invoicesPage);
        invoicesTable.innerHTML = pageRows
            .map(
                (inv) => `<tr>
                    <td>${inv.invoice_no}</td>
                    <td>${inv.status}</td>
                    <td>${inv.total}</td>
                    <td>${inv.due_total}</td>
                    <td>${inv.issued_at}</td>
                </tr>`
            )
            .join('');
        updatePager(invoicesPrev, invoicesNext, invoicesPageLabel, invoicesPage, rows);
    };

    const renderTransactions = (rows) => {
        if (!transactionsTable) {
            return;
        }
        if (!rows || rows.length === 0) {
            transactionsTable.innerHTML = '<tr><td colspan="4" class="muted">No transactions found.</td></tr>';
            updatePager(transactionsPrev, transactionsNext, transactionsPageLabel, transactionsPage, rows || []);
            return;
        }
        const pageRows = paginateRows(rows, transactionsPage);
        transactionsTable.innerHTML = pageRows
            .map(
                (tx) => `<tr>
                    <td>${tx.type}</td>
                    <td>${tx.amount}</td>
                    <td>${tx.payment_method || '-'}</td>
                    <td>${tx.payment_date || tx.created_at || '-'}</td>
                </tr>`
            )
            .join('');
        updatePager(transactionsPrev, transactionsNext, transactionsPageLabel, transactionsPage, rows);
    };

    const renderOrders = (rows) => {
        if (!ordersTable) {
            return;
        }
        if (!rows || rows.length === 0) {
            ordersTable.innerHTML = `<tr><td colspan="${ordersColumnCount}" class="muted">No orders found.</td></tr>`;
            updatePager(ordersPrev, ordersNext, ordersPageLabel, ordersPage, rows || []);
            return;
        }
        const pageRows = paginateRows(rows, ordersPage);
        ordersTable.innerHTML = pageRows
            .map(
                (order) => `<tr>
                    <td><input type="checkbox" data-order-select value="${order.id}"></td>
                    <td>${order.tracking_number}</td>
                    <td>${order.shipment_number || order.shipment_id || '-'}</td>
                    <td>${order.fulfillment_status}</td>
                    <td>${order.note || '-'}</td>
                    <td>${order.total_price}</td>
                    <td class="${metaClass}">${
                        showMeta && order.created_by_name
                            ? `${order.created_by_name} - ${order.created_at || '-'}`
                            : order.created_at || '-'
                    }</td>
                    <td class="${metaClass}">${
                        showMeta && order.updated_by_name
                            ? `${order.updated_by_name} - ${order.updated_at || '-'}`
                            : order.updated_at || '-'
                    }</td>
                    <td>
                        <button class="button ghost small" type="button" data-order-media data-order-id="${order.id}"
                            data-order-tracking="${order.tracking_number || ''}">
                            Manage
                        </button>
                    </td>
                </tr>`
            )
            .join('');
        updatePager(ordersPrev, ordersNext, ordersPageLabel, ordersPage, rows);
    };

    const getSelectedOrderIds = () =>
        Array.from(page.querySelectorAll('[data-order-select]:checked'))
            .map((input) => parseInt(input.value, 10))
            .filter((value) => Number.isFinite(value));

    const bindMediaDeletes = (orderId) => {
        if (!mediaTable) {
            return;
        }
        mediaTable.querySelectorAll('[data-attachment-delete]').forEach((button) => {
            button.addEventListener('click', async () => {
                const attachmentId = button.getAttribute('data-attachment-id');
                if (!attachmentId) {
                    return;
                }
                try {
                    await fetchJson(`${window.APP_BASE}/api/attachments/delete.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: attachmentId }),
                    });
                    showMediaNotice('Attachment removed.', 'success');
                    await loadAttachments(orderId);
                } catch (error) {
                    showMediaNotice(`Delete failed: ${error.message}`, 'error');
                }
            });
        });
    };

    const loadAttachments = async (orderId) => {
        if (!mediaTable) {
            return;
        }
        mediaTable.innerHTML = '<tr><td colspan="5" class="muted">Loading attachments...</td></tr>';
        try {
            const data = await fetchJson(
                `${window.APP_BASE}/api/attachments/list.php?entity_type=order&entity_id=${encodeURIComponent(orderId)}`
            );
            mediaData = data.data || [];
            mediaPage = 0;
            if (mediaData.length === 0) {
                mediaTable.innerHTML = '<tr><td colspan="5" class="muted">No attachments yet.</td></tr>';
                updatePager(mediaPrev, mediaNext, mediaPageLabel, mediaPage, mediaData);
                return;
            }
            const pageRows = paginateRows(mediaData, mediaPage);
            mediaTable.innerHTML = pageRows
                .map(
                    (att) => `<tr>
                        <td>${att.title || att.original_name || '-'}</td>
                        <td>${att.mime_type || '-'}</td>
                        <td>${att.created_at || '-'}</td>
                        <td><a class="text-link" href="${att.download_url}">Download</a></td>
                        <td><button class="button ghost small" type="button" data-attachment-delete data-attachment-id="${att.id}">Delete</button></td>
                    </tr>`
                )
                .join('');
            updatePager(mediaPrev, mediaNext, mediaPageLabel, mediaPage, mediaData);

            bindMediaDeletes(orderId);
        } catch (error) {
            showMediaNotice(`Attachments load failed: ${error.message}`, 'error');
        }
    };

    const openMediaPanel = (orderId, tracking) => {
        currentMediaOrderId = orderId;
        if (mediaPanel) {
            mediaPanel.classList.remove('is-hidden');
        }
        if (mediaIdField) {
            mediaIdField.value = String(orderId);
        }
        if (mediaTitle) {
            const label = tracking ? `Order ${tracking}` : `Order #${orderId}`;
            mediaTitle.textContent = `Attachments for ${label}.`;
        }
        loadAttachments(orderId);
    };

    const bindOrderActions = () => {
        const mediaButtons = page.querySelectorAll('[data-order-media]');
        mediaButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const orderId = parseInt(button.getAttribute('data-order-id') || '', 10);
                if (!Number.isFinite(orderId)) {
                    return;
                }
                openMediaPanel(orderId, button.getAttribute('data-order-tracking') || '');
            });
        });

        if (selectAllOrders) {
            selectAllOrders.checked = false;
        }
    };

    const loadCustomerView = async () => {
        try {
            const params = new URLSearchParams({ customer_id: customerId });
            const noteQuery = noteSearchInput ? noteSearchInput.value.trim() : '';
            if (noteQuery) {
                params.append('order_note', noteQuery);
            }
            const data = await fetchJson(`${window.APP_BASE}/api/customers/view.php?${params.toString()}`);
            const customer = data.customer || {};
            details.forEach((el) => {
                const key = el.getAttribute('data-detail');
                let value = customer[key];
                if (key === 'is_system') {
                    value = customer.is_system ? 'Yes' : 'No';
                }
                el.textContent = value !== null && value !== undefined && value !== '' ? value : '--';
            });
            invoicesData = data.invoices || [];
            transactionsData = data.transactions || [];
            ordersData = data.orders || [];
            invoicesPage = 0;
            transactionsPage = 0;
            ordersPage = 0;
            renderInvoices(invoicesData);
            renderTransactions(transactionsData);
            renderOrders(ordersData);
            bindOrderActions();
        } catch (error) {
            showNotice(`Load failed: ${error.message}`, 'error');
        }
    };

    const loadReassignCustomers = async () => {
        if (!reassignSelect) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/customers/list.php?is_system=0&limit=200`);
            reassignSelect.innerHTML = '<option value="">Select customer</option>';
            data.data.forEach((cust) => {
                if (String(cust.id) === String(customerId)) {
                    return;
                }
                const option = document.createElement('option');
                option.value = cust.id;
                option.textContent = `${cust.name} (${cust.code})`;
                reassignSelect.appendChild(option);
            });
        } catch (error) {
            showNotice(`Customer list load failed: ${error.message}`, 'error');
        }
    };

    if (reassignButton) {
        reassignButton.addEventListener('click', async () => {
            const selectedOrders = getSelectedOrderIds();
            const newCustomerId = reassignSelect ? reassignSelect.value : '';
            if (!newCustomerId) {
                showNotice('Select a new customer to reassign orders.', 'error');
                return;
            }
            if (selectedOrders.length === 0) {
                showNotice('Select one or more orders to reassign.', 'error');
                return;
            }
            if (String(newCustomerId) === String(customerId)) {
                showNotice('Select a different customer to reassign.', 'error');
                return;
            }
            let updated = 0;
            try {
                for (const orderId of selectedOrders) {
                    await fetchJson(`${window.APP_BASE}/api/orders/reassign_customer.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ order_id: orderId, customer_id: newCustomerId }),
                    });
                    updated += 1;
                }
                showNotice(`Reassigned ${updated} order(s).`, 'success');
                await loadCustomerView();
            } catch (error) {
                showNotice(`Reassign failed: ${error.message}`, 'error');
            }
        });
    }

    if (invoicesPrev) {
        invoicesPrev.addEventListener('click', () => {
            if (invoicesPage === 0) {
                return;
            }
            invoicesPage -= 1;
            renderInvoices(invoicesData);
        });
    }
    if (invoicesNext) {
        invoicesNext.addEventListener('click', () => {
            if (invoicesData.length <= (invoicesPage + 1) * pageSize) {
                return;
            }
            invoicesPage += 1;
            renderInvoices(invoicesData);
        });
    }
    if (transactionsPrev) {
        transactionsPrev.addEventListener('click', () => {
            if (transactionsPage === 0) {
                return;
            }
            transactionsPage -= 1;
            renderTransactions(transactionsData);
        });
    }
    if (transactionsNext) {
        transactionsNext.addEventListener('click', () => {
            if (transactionsData.length <= (transactionsPage + 1) * pageSize) {
                return;
            }
            transactionsPage += 1;
            renderTransactions(transactionsData);
        });
    }
    if (ordersPrev) {
        ordersPrev.addEventListener('click', () => {
            if (ordersPage === 0) {
                return;
            }
            ordersPage -= 1;
            renderOrders(ordersData);
            bindOrderActions();
        });
    }
    if (ordersNext) {
        ordersNext.addEventListener('click', () => {
            if (ordersData.length <= (ordersPage + 1) * pageSize) {
                return;
            }
            ordersPage += 1;
            renderOrders(ordersData);
            bindOrderActions();
        });
    }
    if (mediaPrev) {
        mediaPrev.addEventListener('click', () => {
            if (mediaPage === 0) {
                return;
            }
            mediaPage -= 1;
            const pageRows = paginateRows(mediaData, mediaPage);
            mediaTable.innerHTML = pageRows
                .map(
                    (att) => `<tr>
                        <td>${att.title || att.original_name || '-'}</td>
                        <td>${att.mime_type || '-'}</td>
                        <td>${att.created_at || '-'}</td>
                        <td><a class="text-link" href="${att.download_url}">Download</a></td>
                        <td><button class="button ghost small" type="button" data-attachment-delete data-attachment-id="${att.id}">Delete</button></td>
                    </tr>`
                )
                .join('');
            updatePager(mediaPrev, mediaNext, mediaPageLabel, mediaPage, mediaData);
            if (currentMediaOrderId) {
                bindMediaDeletes(currentMediaOrderId);
            }
        });
    }
    if (mediaNext) {
        mediaNext.addEventListener('click', () => {
            if (mediaData.length <= (mediaPage + 1) * pageSize) {
                return;
            }
            mediaPage += 1;
            const pageRows = paginateRows(mediaData, mediaPage);
            mediaTable.innerHTML = pageRows
                .map(
                    (att) => `<tr>
                        <td>${att.title || att.original_name || '-'}</td>
                        <td>${att.mime_type || '-'}</td>
                        <td>${att.created_at || '-'}</td>
                        <td><a class="text-link" href="${att.download_url}">Download</a></td>
                        <td><button class="button ghost small" type="button" data-attachment-delete data-attachment-id="${att.id}">Delete</button></td>
                    </tr>`
                )
                .join('');
            updatePager(mediaPrev, mediaNext, mediaPageLabel, mediaPage, mediaData);
            if (currentMediaOrderId) {
                bindMediaDeletes(currentMediaOrderId);
            }
        });
    }

    if (noteSearchButton) {
        noteSearchButton.addEventListener('click', () => {
            loadCustomerView();
        });
    }
    if (noteSearchInput) {
        noteSearchInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                loadCustomerView();
            }
        });
    }

    if (selectAllOrders && !selectAllOrders.dataset.bound) {
        selectAllOrders.dataset.bound = 'true';
        selectAllOrders.addEventListener('change', () => {
            const checked = selectAllOrders.checked;
            page.querySelectorAll('[data-order-select]').forEach((input) => {
                input.checked = checked;
            });
        });
    }

    if (mediaForm) {
        mediaForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const orderId = mediaIdField ? mediaIdField.value : '';
            if (!orderId) {
                showMediaNotice('Select an order to upload media.', 'error');
                return;
            }
            const fileInput = mediaForm.querySelector('[name="file"]');
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                showMediaNotice('Choose a file to upload.', 'error');
                return;
            }
            const formData = new FormData(mediaForm);
            formData.set('entity_type', 'order');
            formData.set('entity_id', orderId);
            try {
                const response = await fetch(`${window.APP_BASE}/api/attachments/upload.php`, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || data.ok === false) {
                    throw new Error(data.error || 'Upload failed.');
                }
                showMediaNotice('Attachment uploaded.', 'success');
                mediaForm.querySelectorAll('input[type="text"]').forEach((input) => {
                    input.value = '';
                });
                fileInput.value = '';
                await loadAttachments(orderId);
            } catch (error) {
                showMediaNotice(`Upload failed: ${error.message}`, 'error');
            }
        });
    }

    loadReassignCustomers();
    loadCustomerView();
}

function initAuditPage() {
    const page = document.querySelector('[data-audit-page]');
    if (!page) {
        return;
    }

    const filterForm = page.querySelector('[data-audit-filter]');
    const tableBody = page.querySelector('[data-audit-table]');
    const statusStack = page.querySelector('[data-audit-status]');
    const prevButton = page.querySelector('[data-audit-prev]');
    const nextButton = page.querySelector('[data-audit-next]');
    const pageLabel = page.querySelector('[data-audit-page]');
    const limit = 5;
    let offset = 0;
    let lastFilters = {};

    const showNotice = (message, type = 'error') => {
        if (!statusStack) {
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        statusStack.appendChild(notice);
        setTimeout(() => notice.remove(), 6000);
    };

    const escapeHtml = (value) =>
        String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#39;');

    const prettyJson = (value) => {
        if (value === null || value === undefined || value === '') {
            return 'null';
        }
        try {
            return JSON.stringify(JSON.parse(value), null, 2);
        } catch (error) {
            return String(value);
        }
    };

    const renderRows = (rows) => {
        if (!tableBody) {
            return;
        }
        if (!rows.length) {
            tableBody.innerHTML = '<tr><td colspan="5" class="muted">No audit logs found.</td></tr>';
            return;
        }
        tableBody.innerHTML = rows
            .map((row) => {
                const userLabel = row.user_name ? `${row.user_name} (${row.username || 'user'})` : 'System';
                const entityLabel = row.entity_type
                    ? `${row.entity_type}${row.entity_id ? ` #${row.entity_id}` : ''}`
                    : '-';
                const details = `Before:\\n${prettyJson(row.before_json)}\\n\\nAfter:\\n${prettyJson(
                    row.after_json
                )}\\n\\nMeta:\\n${prettyJson(row.meta_json)}\\n\\nIP: ${row.ip_address || '-'}\\nAgent: ${
                    row.user_agent || '-'
                }`;
                return `<tr>
                    <td>${row.created_at || '-'}</td>
                    <td>${escapeHtml(userLabel)}</td>
                    <td>${escapeHtml(row.action || '-')}</td>
                    <td>${escapeHtml(entityLabel)}</td>
                    <td>
                        <details>
                            <summary>View</summary>
                            <pre>${escapeHtml(details)}</pre>
                        </details>
                    </td>
                </tr>`;
            })
            .join('');
    };

    const loadLogs = async (filters = {}) => {
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="5" class="muted">Loading audit logs...</td></tr>';
        }
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== null && value !== undefined && String(value).trim() !== '') {
                params.append(key, String(value));
            }
        });
        params.append('limit', String(limit));
        params.append('offset', String(offset));
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/audit/list.php?${params.toString()}`);
            renderRows(data.data || []);
            if (prevButton) {
                prevButton.disabled = offset === 0;
            }
            if (nextButton) {
                nextButton.disabled = (data.data || []).length < limit;
            }
            if (pageLabel) {
                pageLabel.textContent = `Page ${Math.floor(offset / limit) + 1}`;
            }
        } catch (error) {
            renderRows([]);
            showNotice(`Audit log load failed: ${error.message}`, 'error');
        }
    };

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(filterForm);
            offset = 0;
            lastFilters = Object.fromEntries(formData.entries());
            loadLogs(lastFilters);
        });
    }

    if (prevButton) {
        prevButton.addEventListener('click', () => {
            if (offset === 0) {
                return;
            }
            offset = Math.max(0, offset - limit);
            loadLogs(lastFilters);
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => {
            offset += limit;
            loadLogs(lastFilters);
        });
    }

    loadLogs();
}
