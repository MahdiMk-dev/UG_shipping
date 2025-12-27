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
            initStaffPage();
            initStaffView();
            initCustomerCreate();
            initCustomerEdit();
            initCustomerView();
            initAuditPage();
            initReceivingPage();
            initAttachmentsPage();
            initInvoicesPage();
            initTransactionsPage();
            initExpensesPage();
            initReportsPage();
            initPartnersPage();
            initPartnerView();
            initCompanyPage();
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
        initStaffPage();
        initStaffView();
        initCustomerCreate();
        initCustomerEdit();
        initCustomerView();
        initAuditPage();
        initReceivingPage();
        initAttachmentsPage();
        initInvoicesPage();
        initTransactionsPage();
        initExpensesPage();
        initReportsPage();
        initPartnersPage();
        initPartnerView();
        initCompanyPage();
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
    initStaffPage();
    initStaffView();
    initAuditPage();
    initReceivingPage();
    initReportsPage();
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
    const accountUsernameEl = shell.querySelector('[data-portal-account-username]');
    const accountPhoneEl = shell.querySelector('[data-portal-account-phone]');
    const accountProfilesEl = shell.querySelector('[data-portal-account-profiles]');
    const primaryNameEl = shell.querySelector('[data-portal-primary-name]');
    const primaryCodeEl = shell.querySelector('[data-portal-primary-code]');
    const primaryCountryEl = shell.querySelector('[data-portal-primary-country]');
    const primaryBranchEl = shell.querySelector('[data-portal-primary-branch]');
    const primaryBalanceEl = shell.querySelector('[data-portal-primary-balance]');
    const profilesTable = shell.querySelector('[data-portal-profiles]');
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
            ordersTable.innerHTML = '<tr><td colspan="6" class="muted">No orders yet.</td></tr>';
            updatePager(ordersPrev, ordersNext, ordersPageLabel, ordersPage, rows || []);
            return;
        }
        const pageRows = paginateRows(rows, ordersPage);
        ordersTable.innerHTML = pageRows
            .map(
                (order) => {
                    const profileLabel = order.customer_name
                        ? order.customer_code
                            ? `${order.customer_name} (${order.customer_code})`
                            : order.customer_name
                        : '-';
                    return `<tr>
                    <td>${escapeHtml(profileLabel)}</td>
                    <td>${order.tracking_number || '-'}</td>
                    <td>${order.shipment_number || '-'}</td>
                    <td>${order.fulfillment_status || '-'}</td>
                    <td>${order.total_price || '0.00'}</td>
                    <td>${order.created_at || '-'}</td>
                </tr>`;
                }
            )
            .join('');
        updatePager(ordersPrev, ordersNext, ordersPageLabel, ordersPage, rows);
    };

    const renderInvoices = (rows) => {
        if (!invoicesTable) {
            return;
        }
        if (!rows || rows.length === 0) {
            invoicesTable.innerHTML = '<tr><td colspan="6" class="muted">No invoices found.</td></tr>';
            updatePager(invoicesPrev, invoicesNext, invoicesPageLabel, invoicesPage, rows || []);
            return;
        }
        const pageRows = paginateRows(rows, invoicesPage);
        invoicesTable.innerHTML = pageRows
            .map(
                (inv) => {
                    const profileLabel = inv.customer_name
                        ? inv.customer_code
                            ? `${inv.customer_name} (${inv.customer_code})`
                            : inv.customer_name
                        : '-';
                    return `<tr>
                    <td>${escapeHtml(profileLabel)}</td>
                    <td>${inv.invoice_no || '-'}</td>
                    <td>${inv.status || '-'}</td>
                    <td>${inv.total || '0.00'}</td>
                    <td>${inv.due_total || '0.00'}</td>
                    <td>${inv.issued_at || '-'}</td>
                </tr>`;
                }
            )
            .join('');
        updatePager(invoicesPrev, invoicesNext, invoicesPageLabel, invoicesPage, rows);
    };

    const renderProfiles = (rows) => {
        if (!profilesTable) {
            return;
        }
        if (!rows || rows.length === 0) {
            profilesTable.innerHTML = '<tr><td colspan="5" class="muted">No profiles found.</td></tr>';
            return;
        }
        profilesTable.innerHTML = rows
            .map(
                (profile) => `<tr>
                    <td>${escapeHtml(profile.name || '-')}</td>
                    <td>${escapeHtml(profile.code || '-')}</td>
                    <td>${escapeHtml(profile.profile_country_name || '-')}</td>
                    <td>${escapeHtml(profile.sub_branch_name || '-')}</td>
                    <td>${profile.balance ?? '0.00'}</td>
                </tr>`
            )
            .join('');
    };

    const loadOverview = async () => {
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/customer_auth/overview.php`);
            const account = data.account || {};
            const profiles = data.profiles || [];
            const primaryProfile = profiles[0] || {};
            if (accountUsernameEl) {
                accountUsernameEl.textContent = account.username || '--';
            }
            if (accountPhoneEl) {
                accountPhoneEl.textContent = account.phone || '--';
            }
            if (accountProfilesEl) {
                accountProfilesEl.textContent = profiles.length ? String(profiles.length) : '0';
            }
            if (primaryNameEl) {
                primaryNameEl.textContent = primaryProfile.name || '--';
            }
            if (primaryCodeEl) {
                primaryCodeEl.textContent = primaryProfile.code || '--';
            }
            if (primaryCountryEl) {
                primaryCountryEl.textContent = primaryProfile.profile_country_name || '--';
            }
            if (primaryBranchEl) {
                primaryBranchEl.textContent = primaryProfile.sub_branch_name || '--';
            }
            if (primaryBalanceEl) {
                primaryBalanceEl.textContent = primaryProfile.balance ?? '--';
            }
            if (greetingEl) {
                greetingEl.textContent = `Welcome back, ${account.username || primaryProfile.name || 'Customer'}`;
            }
            if (userName) {
                userName.textContent = account.username || primaryProfile.name || 'Account';
            }
            if (userCode) {
                userCode.textContent = account.phone || '';
            }
            renderProfiles(profiles);
            ordersData = data.orders || [];
            invoicesData = data.invoices || [];
            ordersPage = 0;
            invoicesPage = 0;
            renderOrders(ordersData);
            renderInvoices(invoicesData);
        } catch (error) {
            showNotice(`Portal load failed: ${error.message}`, 'error');
            if (ordersTable) {
                ordersTable.innerHTML = '<tr><td colspan="6" class="muted">Unable to load orders.</td></tr>';
            }
            if (invoicesTable) {
                invoicesTable.innerHTML = '<tr><td colspan="6" class="muted">Unable to load invoices.</td></tr>';
            }
            if (profilesTable) {
                profilesTable.innerHTML = '<tr><td colspan="5" class="muted">Unable to load profiles.</td></tr>';
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
    const partnerSelects = page.querySelectorAll('[data-partner-select]');
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
    const shipmentExpensesSection = page.querySelector('[data-shipment-expenses]');
    const shipmentExpensesTable = page.querySelector('[data-shipment-expenses-table]');
    const shipmentExpensesStatus = page.querySelector('[data-shipment-expenses-status]');
    const shipmentExpensesAddButton = page.querySelector('[data-shipment-expenses-add]');
    const shipmentExpensesPrev = page.querySelector('[data-shipment-expenses-prev]');
    const shipmentExpensesNext = page.querySelector('[data-shipment-expenses-next]');
    const shipmentExpensesPageLabel = page.querySelector('[data-shipment-expenses-page]');
    const shipmentExpensesDrawer = page.querySelector('[data-shipment-expenses-drawer]');
    const shipmentExpensesForm = page.querySelector('[data-shipment-expenses-form]');
    const shipmentExpensesTitle = page.querySelector('[data-shipment-expenses-title]');
    const shipmentExpensesSubmitLabel = page.querySelector('[data-shipment-expenses-submit-label]');
    const shipmentExpenseIdField = page.querySelector('[data-shipment-expense-id]');
    const shipmentExpensesFormStatus = page.querySelector('[data-shipment-expenses-form-status]');
    const shipmentExpensesCloseButtons = page.querySelectorAll('[data-shipment-expenses-close]');
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
    const { role, branchId } = getUserContext();
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
    const shipmentExpensesLimit = 5;
    let shipmentExpensesOffset = 0;
    let shipmentExpensesData = [];
    const shipmentExpenseMap = new Map();
    let warehouseLockNotified = false;
    const escapeHtml = (value) =>
        String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#39;');
    const formatAmount = (value) => {
        const num = Number(value ?? 0);
        return Number.isFinite(num) ? num.toFixed(2) : '0.00';
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

    const showShipmentExpensesNotice = (message, type = 'error') => {
        if (!shipmentExpensesStatus) {
            showNotice(message, type);
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        shipmentExpensesStatus.appendChild(notice);
        setTimeout(() => notice.remove(), 7000);
    };

    const showShipmentExpenseFormNotice = (message, type = 'error') => {
        if (!shipmentExpensesFormStatus) {
            showShipmentExpensesNotice(message, type);
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        shipmentExpensesFormStatus.appendChild(notice);
        setTimeout(() => notice.remove(), 7000);
    };

    const openShipmentExpensesDrawer = () => {
        if (!shipmentExpensesDrawer) {
            return;
        }
        shipmentExpensesDrawer.classList.add('is-open');
        document.body.classList.add('drawer-open');
    };

    const closeShipmentExpensesDrawer = () => {
        if (!shipmentExpensesDrawer) {
            return;
        }
        shipmentExpensesDrawer.classList.remove('is-open');
        document.body.classList.remove('drawer-open');
        if (shipmentExpensesFormStatus) {
            shipmentExpensesFormStatus.innerHTML = '';
        }
    };

    const setShipmentExpenseFormValues = (expense) => {
        if (!shipmentExpensesForm) {
            return;
        }
        if (shipmentExpenseIdField) {
            shipmentExpenseIdField.value = expense?.id ? String(expense.id) : '';
        }
        if (shipmentExpensesTitle) {
            shipmentExpensesTitle.textContent = expense ? 'Edit expense' : 'Add expense';
        }
        if (shipmentExpensesSubmitLabel) {
            shipmentExpensesSubmitLabel.textContent = expense ? 'Save changes' : 'Add expense';
        }
        shipmentExpensesForm.querySelector('[name="title"]').value = expense?.title || '';
        shipmentExpensesForm.querySelector('[name="amount"]').value = expense?.amount ?? '';
        shipmentExpensesForm.querySelector('[name="expense_date"]').value = expense?.expense_date || '';
        shipmentExpensesForm.querySelector('[name="note"]').value = expense?.note || '';
    };

    const renderShipmentExpenses = () => {
        if (!shipmentExpensesTable) {
            return;
        }
        shipmentExpenseMap.clear();
        if (!shipmentExpensesData.length) {
            shipmentExpensesTable.innerHTML = '<tr><td colspan="5" class="muted">No expenses found.</td></tr>';
            return;
        }
        shipmentExpensesTable.innerHTML = shipmentExpensesData
            .map((row) => {
                shipmentExpenseMap.set(String(row.id), row);
                const dateLabel = row.expense_date || row.created_at || '-';
                return `<tr>
                    <td>${escapeHtml(dateLabel)}</td>
                    <td>${escapeHtml(row.title || '-')}</td>
                    <td>${formatAmount(row.amount)}</td>
                    <td>${escapeHtml(row.note || '-')}</td>
                    <td>
                        <button class="text-link" type="button" data-shipment-expense-edit data-expense-id="${row.id}">
                            Edit
                        </button>
                        <button class="text-link" type="button" data-shipment-expense-delete data-expense-id="${row.id}">
                            Delete
                        </button>
                    </td>
                </tr>`;
            })
            .join('');

        shipmentExpensesTable.querySelectorAll('[data-shipment-expense-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                const id = button.getAttribute('data-expense-id');
                if (!id || !shipmentExpenseMap.has(id)) {
                    return;
                }
                setShipmentExpenseFormValues(shipmentExpenseMap.get(id));
                openShipmentExpensesDrawer();
            });
        });

        shipmentExpensesTable.querySelectorAll('[data-shipment-expense-delete]').forEach((button) => {
            button.addEventListener('click', async () => {
                const id = button.getAttribute('data-expense-id');
                if (!id) {
                    return;
                }
                try {
                    await fetchJson(`${window.APP_BASE}/api/expenses/delete.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id }),
                    });
                    showShipmentExpensesNotice('Expense removed.', 'success');
                    if (shipmentExpensesData.length === 1 && shipmentExpensesOffset > 0) {
                        shipmentExpensesOffset = Math.max(0, shipmentExpensesOffset - shipmentExpensesLimit);
                    }
                    if (currentShipmentId) {
                        loadShipmentExpenses(currentShipmentId);
                    }
                } catch (error) {
                    showShipmentExpensesNotice(`Delete failed: ${error.message}`, 'error');
                }
            });
        });
    };

    const loadShipmentExpenses = async (shipmentIdValue) => {
        if (!shipmentExpensesTable) {
            return;
        }
        shipmentExpensesTable.innerHTML = '<tr><td colspan="5" class="muted">Loading expenses...</td></tr>';
        const params = new URLSearchParams();
        params.append('shipment_id', String(shipmentIdValue));
        params.append('limit', String(shipmentExpensesLimit));
        params.append('offset', String(shipmentExpensesOffset));
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/expenses/list.php?${params.toString()}`);
            shipmentExpensesData = data.data || [];
            renderShipmentExpenses();
            if (shipmentExpensesPrev) {
                shipmentExpensesPrev.disabled = shipmentExpensesOffset === 0;
            }
            if (shipmentExpensesNext) {
                shipmentExpensesNext.disabled = shipmentExpensesData.length < shipmentExpensesLimit;
            }
            if (shipmentExpensesPageLabel) {
                shipmentExpensesPageLabel.textContent = `Page ${Math.floor(shipmentExpensesOffset / shipmentExpensesLimit) + 1}`;
            }
        } catch (error) {
            shipmentExpensesData = [];
            renderShipmentExpenses();
            showShipmentExpensesNotice(`Expenses load failed: ${error.message}`, 'error');
        }
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
    const pendingPartnerIds = { shipper: null, consignee: null };

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

    const applyPartnerSelection = () => {
        if (!partnerSelects.length) {
            return;
        }
        partnerSelects.forEach((select) => {
            const type = select.getAttribute('data-partner-type');
            const pendingValue = type ? pendingPartnerIds[type] : null;
            if (pendingValue !== null && pendingValue !== undefined && String(pendingValue) !== '') {
                select.value = String(pendingValue);
            } else {
                select.value = '';
            }
        });
    };

    const loadPartners = async () => {
        if (!partnerSelects.length) {
            return;
        }
        const cache = new Map();
        const types = new Set();
        partnerSelects.forEach((select) => {
            const type = select.getAttribute('data-partner-type');
            if (type) {
                types.add(type);
            }
        });
        for (const type of types) {
            try {
                const params = new URLSearchParams({ type, limit: '200' });
                const data = await fetchJson(`${window.APP_BASE}/api/partners/list.php?${params.toString()}`);
                cache.set(type, data.data || []);
            } catch (error) {
                showNotice(`Partners load failed: ${error.message}`, 'error');
                cache.set(type, []);
            }
        }
        partnerSelects.forEach((select) => {
            const type = select.getAttribute('data-partner-type');
            select.querySelectorAll('option[data-dynamic]').forEach((option) => option.remove());
            const rows = cache.get(type) || [];
            rows.forEach((partner) => {
                const option = document.createElement('option');
                option.value = partner.id;
                option.textContent = partner.name;
                option.setAttribute('data-dynamic', 'true');
                select.appendChild(option);
            });
        });
        applyPartnerSelection();
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
        pendingPartnerIds.shipper = shipment.shipper_profile_id || '';
        pendingPartnerIds.consignee = shipment.consignee_profile_id || '';
        originSelects.forEach((select) => {
            if (pendingOriginId) {
                select.value = String(pendingOriginId);
            }
        });
        applyPartnerSelection();
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
                const canDistribute = canDistributeRole
                    && ['arrived', 'partially_distributed'].includes(shipment.status || '');
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
            if (shipmentExpensesTable && currentShipmentId) {
                loadShipmentExpenses(currentShipmentId);
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
    if (shipmentExpensesPrev) {
        shipmentExpensesPrev.addEventListener('click', () => {
            if (shipmentExpensesOffset === 0) {
                return;
            }
            shipmentExpensesOffset = Math.max(0, shipmentExpensesOffset - shipmentExpensesLimit);
            if (currentShipmentId) {
                loadShipmentExpenses(currentShipmentId);
            }
        });
    }
    if (shipmentExpensesNext) {
        shipmentExpensesNext.addEventListener('click', () => {
            shipmentExpensesOffset += shipmentExpensesLimit;
            if (currentShipmentId) {
                loadShipmentExpenses(currentShipmentId);
            }
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
    if (shipmentExpensesAddButton) {
        shipmentExpensesAddButton.addEventListener('click', () => {
            setShipmentExpenseFormValues(null);
            openShipmentExpensesDrawer();
        });
    }
    if (shipmentExpensesCloseButtons.length) {
        shipmentExpensesCloseButtons.forEach((button) => {
            button.addEventListener('click', closeShipmentExpensesDrawer);
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

    if (shipmentExpensesForm) {
        shipmentExpensesForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!currentShipmentId) {
                showShipmentExpenseFormNotice('Shipment id is missing.', 'error');
                return;
            }
            const formData = new FormData(shipmentExpensesForm);
            const payload = Object.fromEntries(formData.entries());
            const expenseId = payload.expense_id || '';
            delete payload.expense_id;
            if (!payload.title || !payload.amount) {
                showShipmentExpenseFormNotice('Title and amount are required.', 'error');
                return;
            }
            try {
                if (expenseId) {
                    payload.id = expenseId;
                    await fetchJson(`${window.APP_BASE}/api/expenses/update.php`, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    showShipmentExpensesNotice('Expense updated.', 'success');
                } else {
                    payload.shipment_id = currentShipmentId;
                    await fetchJson(`${window.APP_BASE}/api/expenses/create.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    showShipmentExpensesNotice('Expense added.', 'success');
                }
                closeShipmentExpensesDrawer();
                loadShipmentExpenses(currentShipmentId);
            } catch (error) {
                showShipmentExpenseFormNotice(`Save failed: ${error.message}`, 'error');
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
                const remainingMain = data.remaining_main_branch ?? 0;
                const remainingInShipment = data.remaining_in_shipment ?? 0;
                const remainingTotal = remainingMain + remainingInShipment;
                const shipmentDistributed = Boolean(data.shipment_distributed);
                const queuedLabel = updatedCount === 1 ? '1 order' : `${updatedCount} order(s)`;
                const remainingLabel = remainingTotal === 1 ? '1 order' : `${remainingTotal} order(s)`;
                if (shipmentDistributed) {
                    showNotice(`Shipment distributed. ${queuedLabel} queued for sub-branches.`, 'success');
                } else {
                    showNotice(
                        `Shipment partially distributed. ${queuedLabel} queued for sub-branches. ${remainingLabel} still pending at main branch.`,
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
    loadPartners();
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
    const isMainBranch = role === 'Main Branch';
    const canManage = ['Admin', 'Owner', 'Main Branch'].includes(role || '');
    const branchIdValue = branchId ? parseInt(branchId, 10) : null;
    const limit = 6;
    const ordersLimit = 200;
    const receivingStatus = isMainBranch ? 'in_shipment' : 'pending_receipt';
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
            tbody.innerHTML = '<tr><td colspan="5" class="muted">No orders awaiting receipt for this shipment.</td></tr>';
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
        tbody.innerHTML = '<tr><td colspan="5" class="muted">Loading orders...</td></tr>';
        const params = new URLSearchParams();
        params.append('fulfillment_status', receivingStatus);
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
            shipmentsTable.innerHTML = '<tr><td colspan="5" class="muted">No shipments awaiting receipt.</td></tr>';
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
                                    <h4>Orders awaiting receipt</h4>
                                    <p>Scan or enter a tracking number to confirm receipt for this shipment.</p>
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
            shipmentsTable.innerHTML = '<tr><td colspan="5" class="muted">Loading shipments awaiting receipt...</td></tr>';
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
            showNotice(`Receiving queue load failed: ${error.message}`, 'error');
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

    const submitScan = async (payload, statusEl, shipmentId, focusInput) => {
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
        } finally {
            if (focusInput && typeof focusInput.focus === 'function') {
                focusInput.focus();
            }
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
                const focusInput = receiveButton
                    .closest('[data-receiving-expand]')
                    ?.querySelector('input[name="tracking_number"]');
                const payload = {
                    tracking_number: trackingNumber,
                    shipment_id: parseInt(shipmentIdValue, 10),
                };
                if (branchIdValueRaw) {
                    payload.branch_id = parseInt(branchIdValueRaw, 10);
                }
                submitScan(payload, statusEl, shipmentIdValue, focusInput);
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
        submitScan(payload, statusEl, shipmentIdValue, trackingInput);
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
    const partnerSelects = page.querySelectorAll('[data-partner-select]');
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

    const loadPartners = async () => {
        if (!partnerSelects.length) {
            return;
        }
        const cache = new Map();
        const types = new Set();
        partnerSelects.forEach((select) => {
            const type = select.getAttribute('data-partner-type');
            if (type) {
                types.add(type);
            }
        });
        for (const type of types) {
            try {
                const params = new URLSearchParams({ type, limit: '200' });
                const data = await fetchJson(`${window.APP_BASE}/api/partners/list.php?${params.toString()}`);
                cache.set(type, data.data || []);
            } catch (error) {
                showNotice(`Partners load failed: ${error.message}`, 'error');
                cache.set(type, []);
            }
        }
        partnerSelects.forEach((select) => {
            const type = select.getAttribute('data-partner-type');
            const current = select.value;
            select.querySelectorAll('option[data-dynamic]').forEach((option) => option.remove());
            const rows = cache.get(type) || [];
            rows.forEach((partner) => {
                const option = document.createElement('option');
                option.value = partner.id;
                option.textContent = partner.name;
                option.setAttribute('data-dynamic', 'true');
                select.appendChild(option);
            });
            if (current) {
                select.value = current;
            }
        });
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
    loadPartners();
}

function initPartnerView() {
    const page = document.querySelector('[data-partner-view]');
    if (!page) {
        return;
    }

    const partnerId = page.getAttribute('data-partner-id');
    if (!partnerId) {
        return;
    }

    const statusStack = page.querySelector('[data-partner-status]');
    const details = page.querySelectorAll('[data-partner-detail]');
    const shipmentsTable = page.querySelector('[data-partner-shipments]');
    const invoiceForm = page.querySelector('[data-partner-invoice-form]');
    const invoiceStatus = page.querySelector('[data-partner-invoice-status]');
    const invoicesTable = page.querySelector('[data-partner-invoices]');
    const invoicesPrev = page.querySelector('[data-partner-invoices-prev]');
    const invoicesNext = page.querySelector('[data-partner-invoices-next]');
    const invoicesPageLabel = page.querySelector('[data-partner-invoices-page]');
    const transactionForm = page.querySelector('[data-partner-transaction-form]');
    const transactionStatus = page.querySelector('[data-partner-transaction-status]');
    const transactionsTable = page.querySelector('[data-partner-transactions]');
    const transactionsPrev = page.querySelector('[data-partner-transactions-prev]');
    const transactionsNext = page.querySelector('[data-partner-transactions-next]');
    const transactionsPageLabel = page.querySelector('[data-partner-transactions-page]');
    const invoiceSelect = page.querySelector('[data-partner-invoice-select]');
    const shipmentSearchInput = page.querySelector('[data-shipment-search]');
    const shipmentSelect = page.querySelector('[data-shipment-select]');
    const branchSelect = page.querySelector('[data-branch-select]');
    const paymentMethodSelect = page.querySelector('[data-payment-method-select]');
    const canEdit = page.getAttribute('data-can-edit') === '1';
    const { branchId } = getUserContext();

    const limit = 5;
    let invoicesPage = 0;
    let transactionsPage = 0;
    let shipmentSearchTimer = null;

    const escapeHtml = (value) =>
        String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#39;');

    const formatAmount = (value) => {
        const num = Number(value ?? 0);
        return Number.isFinite(num) ? num.toFixed(2) : '0.00';
    };

    const showNotice = (stack, message, type = 'error') => {
        if (!stack) {
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        stack.appendChild(notice);
        setTimeout(() => notice.remove(), 7000);
    };

    const normalizeDateTime = (value) => {
        if (!value) {
            return null;
        }
        const normalized = value.replace('T', ' ');
        return normalized.length === 16 ? `${normalized}:00` : normalized;
    };

    const renderPartner = (partner) => {
        details.forEach((el) => {
            const key = el.getAttribute('data-partner-detail');
            const value = partner[key];
            el.textContent = value !== null && value !== undefined && value !== '' ? value : '--';
        });
    };

    const renderShipments = (rows) => {
        if (!shipmentsTable) {
            return;
        }
        if (!rows.length) {
            shipmentsTable.innerHTML = '<tr><td colspan="5" class="muted">No shipments linked.</td></tr>';
            return;
        }
        shipmentsTable.innerHTML = rows
            .map((row) => {
                const roleLabel =
                    row.partner_role === 'shipper' ? 'Shipper' : row.partner_role === 'consignee' ? 'Consignee' : '-';
                return `<tr>
                    <td>${escapeHtml(row.shipment_number || '-')}</td>
                    <td>${escapeHtml(roleLabel)}</td>
                    <td>${escapeHtml(row.status || '-')}</td>
                    <td>${escapeHtml(row.origin_country || '-')}</td>
                    <td><a class="text-link" href="${window.APP_BASE}/views/internal/shipment_view?id=${row.id}">Open</a></td>
                </tr>`;
            })
            .join('');
    };

    const renderInvoices = (rows) => {
        if (!invoicesTable) {
            return;
        }
        if (!rows.length) {
            invoicesTable.innerHTML = '<tr><td colspan="7" class="muted">No invoices found.</td></tr>';
            return;
        }
        invoicesTable.innerHTML = rows
            .map(
                (row) => `<tr>
                    <td>${escapeHtml(row.invoice_no || '-')}</td>
                    <td>${escapeHtml(row.shipment_number || '-')}</td>
                    <td>${escapeHtml(row.status || '-')}</td>
                    <td>${formatAmount(row.total)}</td>
                    <td>${formatAmount(row.due_total)}</td>
                    <td>${escapeHtml(row.issued_at || '-')}</td>
                    <td><a class="text-link" href="${window.APP_BASE}/views/internal/partner_invoice_print?id=${row.id}" target="_blank" rel="noreferrer">Print</a></td>
                </tr>`
            )
            .join('');
    };

    const renderTransactions = (rows) => {
        if (!transactionsTable) {
            return;
        }
        if (!rows.length) {
            transactionsTable.innerHTML = '<tr><td colspan="7" class="muted">No transactions found.</td></tr>';
            return;
        }
        transactionsTable.innerHTML = rows
            .map(
                (row) => `<tr>
                    <td>${escapeHtml(row.payment_date || row.created_at || '-')}</td>
                    <td>${escapeHtml(row.type || '-')}</td>
                    <td>${escapeHtml(row.payment_method_name || '-')}</td>
                    <td>${formatAmount(row.amount)}</td>
                    <td>${escapeHtml(row.invoice_no || '-')}</td>
                    <td>${escapeHtml(row.note || '-')}</td>
                    <td><a class="text-link" href="${window.APP_BASE}/views/internal/partner_receipt_print?id=${row.id}" target="_blank" rel="noreferrer">Print</a></td>
                </tr>`
            )
            .join('');
    };

    const loadPartner = async () => {
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/partners/view.php?id=${encodeURIComponent(partnerId)}`);
            renderPartner(data.partner || {});
            renderShipments(data.shipments || []);
        } catch (error) {
            showNotice(statusStack, `Partner load failed: ${error.message}`, 'error');
        }
    };

    const loadInvoices = async () => {
        try {
            const params = new URLSearchParams({
                partner_id: partnerId,
                limit: String(limit),
                offset: String(invoicesPage * limit),
            });
            const data = await fetchJson(`${window.APP_BASE}/api/partner_invoices/list.php?${params.toString()}`);
            renderInvoices(data.data || []);
            if (invoicesPrev) {
                invoicesPrev.disabled = invoicesPage === 0;
            }
            if (invoicesNext) {
                invoicesNext.disabled = (data.data || []).length < limit;
            }
            if (invoicesPageLabel) {
                invoicesPageLabel.textContent = `Page ${invoicesPage + 1}`;
            }
        } catch (error) {
            renderInvoices([]);
            showNotice(statusStack, `Invoices load failed: ${error.message}`, 'error');
        }
    };

    const loadInvoiceOptions = async () => {
        if (!invoiceSelect) {
            return;
        }
        try {
            const params = new URLSearchParams({ partner_id: partnerId, limit: '200' });
            const data = await fetchJson(`${window.APP_BASE}/api/partner_invoices/list.php?${params.toString()}`);
            const rows = (data.data || []).filter((row) => !['paid', 'void'].includes(row.status));
            invoiceSelect.querySelectorAll('option[data-dynamic]').forEach((option) => option.remove());
            rows.forEach((row) => {
                const option = document.createElement('option');
                option.value = row.id;
                option.textContent = `${row.invoice_no} - Due ${formatAmount(row.due_total)}`;
                option.setAttribute('data-dynamic', 'true');
                invoiceSelect.appendChild(option);
            });
        } catch (error) {
            showNotice(statusStack, `Invoice list load failed: ${error.message}`, 'error');
        }
    };

    const loadTransactions = async () => {
        try {
            const params = new URLSearchParams({
                partner_id: partnerId,
                limit: String(limit),
                offset: String(transactionsPage * limit),
            });
            const data = await fetchJson(`${window.APP_BASE}/api/partner_transactions/list.php?${params.toString()}`);
            renderTransactions(data.data || []);
            if (transactionsPrev) {
                transactionsPrev.disabled = transactionsPage === 0;
            }
            if (transactionsNext) {
                transactionsNext.disabled = (data.data || []).length < limit;
            }
            if (transactionsPageLabel) {
                transactionsPageLabel.textContent = `Page ${transactionsPage + 1}`;
            }
        } catch (error) {
            renderTransactions([]);
            showNotice(statusStack, `Transactions load failed: ${error.message}`, 'error');
        }
    };

    const loadShipmentOptions = async (query = '') => {
        if (!shipmentSelect) {
            return;
        }
        const currentValue = shipmentSelect.value;
        try {
            const params = new URLSearchParams({ limit: '50', partner_id: partnerId });
            if (query) {
                params.set('q', query);
            }
            const data = await fetchJson(`${window.APP_BASE}/api/shipments/list.php?${params.toString()}`);
            shipmentSelect.querySelectorAll('option[data-dynamic]').forEach((option) => option.remove());
            const rows = data.data || [];
            if (!rows.length && query) {
                const option = document.createElement('option');
                option.textContent = 'No shipments found';
                option.disabled = true;
                option.setAttribute('data-dynamic', 'true');
                shipmentSelect.appendChild(option);
                return;
            }
            rows.forEach((shipment) => {
                const option = document.createElement('option');
                option.value = shipment.id;
                option.textContent = `${shipment.shipment_number || 'Shipment'} (${shipment.status || 'status'})`;
                option.setAttribute('data-dynamic', 'true');
                shipmentSelect.appendChild(option);
            });
            if (currentValue) {
                shipmentSelect.value = currentValue;
            }
        } catch (error) {
            showNotice(statusStack, `Shipments load failed: ${error.message}`, 'error');
        }
    };

    const loadBranches = async () => {
        if (!branchSelect) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/branches/list.php?limit=200`);
            branchSelect.querySelectorAll('option[data-dynamic]').forEach((option) => option.remove());
            (data.data || []).forEach((branch) => {
                const option = document.createElement('option');
                option.value = branch.id;
                option.textContent = branch.name;
                option.setAttribute('data-dynamic', 'true');
                branchSelect.appendChild(option);
            });
            if (branchId) {
                branchSelect.value = String(branchId);
            }
        } catch (error) {
            showNotice(statusStack, `Branches load failed: ${error.message}`, 'error');
        }
    };

    const loadPaymentMethods = async () => {
        if (!paymentMethodSelect) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/payment_methods/list.php?limit=200`);
            paymentMethodSelect.querySelectorAll('option[data-dynamic]').forEach((option) => option.remove());
            (data.data || []).forEach((method) => {
                const option = document.createElement('option');
                option.value = method.id;
                option.textContent = method.name;
                option.setAttribute('data-dynamic', 'true');
                paymentMethodSelect.appendChild(option);
            });
        } catch (error) {
            showNotice(statusStack, `Payment methods load failed: ${error.message}`, 'error');
        }
    };

    if (invoiceForm && canEdit) {
        invoiceForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(invoiceForm);
            const payload = Object.fromEntries(formData.entries());
            if (!payload.total) {
                showNotice(invoiceStatus, 'Total is required.', 'error');
                return;
            }
            if (!payload.shipment_id) {
                delete payload.shipment_id;
            }
            payload.partner_id = partnerId;
            payload.issued_at = normalizeDateTime(payload.issued_at);
            if (!payload.issued_at) {
                delete payload.issued_at;
            }
            try {
                await fetchJson(`${window.APP_BASE}/api/partner_invoices/create.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showNotice(invoiceStatus, 'Invoice created.', 'success');
                invoiceForm.reset();
                invoicesPage = 0;
                loadPartner();
                loadInvoices();
                loadInvoiceOptions();
            } catch (error) {
                showNotice(invoiceStatus, `Create failed: ${error.message}`, 'error');
            }
        });
    }

    if (shipmentSearchInput && shipmentSelect) {
        shipmentSearchInput.addEventListener('input', () => {
            if (shipmentSearchTimer) {
                window.clearTimeout(shipmentSearchTimer);
            }
            shipmentSearchTimer = window.setTimeout(() => {
                loadShipmentOptions(shipmentSearchInput.value.trim());
            }, 300);
        });
    }

    if (transactionForm && canEdit) {
        transactionForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(transactionForm);
            const payload = Object.fromEntries(formData.entries());
            payload.partner_id = partnerId;
            if (!payload.invoice_id) {
                delete payload.invoice_id;
            }
            if (!payload.branch_id || !payload.payment_method_id || !payload.amount) {
                showNotice(transactionStatus, 'Branch, method, and amount are required.', 'error');
                return;
            }
            try {
                await fetchJson(`${window.APP_BASE}/api/partner_transactions/create.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showNotice(transactionStatus, 'Receipt recorded.', 'success');
                transactionForm.querySelector('[name="amount"]').value = '';
                transactionForm.querySelector('[name="note"]').value = '';
                transactionsPage = 0;
                loadPartner();
                loadTransactions();
                loadInvoiceOptions();
            } catch (error) {
                showNotice(transactionStatus, `Create failed: ${error.message}`, 'error');
            }
        });
    }

    if (invoicesPrev) {
        invoicesPrev.addEventListener('click', () => {
            if (invoicesPage === 0) {
                return;
            }
            invoicesPage -= 1;
            loadInvoices();
        });
    }
    if (invoicesNext) {
        invoicesNext.addEventListener('click', () => {
            invoicesPage += 1;
            loadInvoices();
        });
    }
    if (transactionsPrev) {
        transactionsPrev.addEventListener('click', () => {
            if (transactionsPage === 0) {
                return;
            }
            transactionsPage -= 1;
            loadTransactions();
        });
    }
    if (transactionsNext) {
        transactionsNext.addEventListener('click', () => {
            transactionsPage += 1;
            loadTransactions();
        });
    }

    loadPartner();
    loadInvoices();
    loadInvoiceOptions();
    loadTransactions();
    loadShipmentOptions();
    loadBranches();
    loadPaymentMethods();
}

function initAttachmentsPage() {
    const page = document.querySelector('[data-attachments-page]');
    if (!page) {
        return;
    }

    const uploadForm = page.querySelector('[data-attachments-upload-form]');
    const uploadStatus = page.querySelector('[data-attachments-upload-status]');
    const filterForm = page.querySelector('[data-attachments-filter]');
    const refreshButton = page.querySelector('[data-attachments-refresh]');
    const tableBody = page.querySelector('[data-attachments-table]');
    const statusStack = page.querySelector('[data-attachments-status]');
    const prevButton = page.querySelector('[data-attachments-prev]');
    const nextButton = page.querySelector('[data-attachments-next]');
    const pageLabel = page.querySelector('[data-attachments-page-label]');

    const limit = 10;
    let offset = 0;
    let lastFilters = {};

    const showNotice = (stack, message, type = 'error') => {
        if (!stack) {
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        stack.appendChild(notice);
        setTimeout(() => notice.remove(), 6000);
    };

    const renderRows = (rows) => {
        if (!tableBody) {
            return;
        }
        if (!rows.length) {
            tableBody.innerHTML = '<tr><td colspan="5" class="muted">No attachments found.</td></tr>';
        } else {
            tableBody.innerHTML = rows
                .map((attachment) => {
                    const typeLabel = {
                        shipment: 'Shipment',
                        order: 'Order',
                        shopping_order: 'Shopping order',
                        invoice: 'Invoice',
                    }[attachment.entity_type] || (attachment.entity_type || 'Attachment');
                    const entityLabel = attachment.entity_id ? `${typeLabel} #${attachment.entity_id}` : typeLabel;
                    const downloadUrl =
                        attachment.download_url ||
                        `${window.APP_BASE}/api/attachments/download.php?id=${attachment.id}`;
                    return `<tr>
                        <td>${attachment.title || attachment.original_name || '-'}</td>
                        <td>${entityLabel}</td>
                        <td>${attachment.mime_type || '-'}</td>
                        <td>${attachment.created_at || '-'}</td>
                        <td>
                            <a class="text-link" href="${downloadUrl}">Download</a>
                            <button class="button ghost small" type="button" data-attachment-delete data-attachment-id="${attachment.id}">
                                Delete
                            </button>
                        </td>
                    </tr>`;
                })
                .join('');
        }
        if (prevButton) {
            prevButton.disabled = offset === 0;
        }
        if (nextButton) {
            nextButton.disabled = rows.length < limit;
        }
        if (pageLabel) {
            pageLabel.textContent = `Page ${Math.floor(offset / limit) + 1}`;
        }
    };

    const bindDeletes = (rows) => {
        if (!tableBody) {
            return;
        }
        tableBody.querySelectorAll('[data-attachment-delete]').forEach((button) => {
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
                    showNotice(statusStack, 'Attachment removed.', 'success');
                    if (rows.length === 1 && offset > 0) {
                        offset = Math.max(0, offset - limit);
                    }
                    await loadAttachments(lastFilters);
                } catch (error) {
                    showNotice(statusStack, `Delete failed: ${error.message}`, 'error');
                }
            });
        });
    };

    const loadAttachments = async (filters = lastFilters) => {
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="5" class="muted">Loading attachments...</td></tr>';
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
            const data = await fetchJson(`${window.APP_BASE}/api/attachments/list.php?${params.toString()}`);
            const rows = data.data || [];
            renderRows(rows);
            bindDeletes(rows);
        } catch (error) {
            renderRows([]);
            showNotice(statusStack, `Attachments load failed: ${error.message}`, 'error');
        }
    };

    if (prevButton) {
        prevButton.addEventListener('click', () => {
            if (offset === 0) {
                return;
            }
            offset = Math.max(0, offset - limit);
            loadAttachments(lastFilters);
        });
    }
    if (nextButton) {
        nextButton.addEventListener('click', () => {
            offset += limit;
            loadAttachments(lastFilters);
        });
    }
    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(filterForm);
            lastFilters = Object.fromEntries(formData.entries());
            offset = 0;
            loadAttachments(lastFilters);
        });
    }
    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            offset = 0;
            loadAttachments(lastFilters);
        });
    }

    if (uploadForm) {
        uploadForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const fileInput = uploadForm.querySelector('[name="file"]');
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                showNotice(uploadStatus, 'Choose a file to upload.', 'error');
                return;
            }
            const formData = new FormData(uploadForm);
            const entityId = (formData.get('entity_id') || '').toString().trim();
            if (!entityId) {
                showNotice(uploadStatus, 'Entity ID is required.', 'error');
                return;
            }
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
                showNotice(uploadStatus, 'Attachment uploaded.', 'success');
                uploadForm.reset();
                offset = 0;
                loadAttachments(lastFilters);
            } catch (error) {
                showNotice(uploadStatus, `Upload failed: ${error.message}`, 'error');
            }
        });
    }

    loadAttachments();
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
    const rateField = rateInput ? rateInput.closest('label') : null;
    const trackingInput = createForm?.querySelector('[name="tracking_number"]');
    const submitButton = createForm?.querySelector('button[type="submit"]');
    const { role } = getUserContext();
    const isWarehouse = role === 'Warehouse';

    const shipmentId = page.getAttribute('data-shipment-id');
    const shipmentNumber = page.getAttribute('data-shipment-number');
    const presetCollectionId = page.getAttribute('data-collection-id');
    const customerMap = new Map();
    let shipmentOriginCountryId = null;

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
            if (shipmentOriginCountryId) {
                params.append('profile_country_id', shipmentOriginCountryId);
            }
            const data = await fetchJson(`${window.APP_BASE}/api/customers/list.php?${params.toString()}`);
            data.data.forEach((customer) => {
                const phoneValue = customer.phone || customer.portal_phone || '';
                const phone = phoneValue ? ` - ${phoneValue}` : '';
                const countryLabel = customer.profile_country_name ? ` | ${customer.profile_country_name}` : '';
                const label = `${customer.name} (${customer.code})${countryLabel}${phone}`;
                customerMap.set(label, {
                    id: customer.id,
                    name: customer.name || '',
                    code: customer.code || '',
                    phone: customer.phone || '',
                    portalPhone: customer.portal_phone || '',
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
            if (data.phone && data.phone.toLowerCase() === normalized) {
                return { label, data };
            }
            if (data.portalPhone && data.portalPhone.toLowerCase() === normalized) {
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
            customerIdInput.dataset.subBranchId = '';
            subBranchDisplay.value = 'Select customer first';
            return;
        }
        const match = findCustomerMatch(value);
        const selected = match ? match.data : null;
        if (!selected) {
            customerIdInput.value = '';
            customerIdInput.dataset.subBranchId = '';
            subBranchDisplay.value = '';
            return;
        }
        if (match && customerInput && match.label !== value) {
            customerInput.value = match.label;
        }
        const branchName = selected.subBranchName || '';
        const branchId = selected.subBranchId || '';
        customerIdInput.value = String(selected.id);
        customerIdInput.dataset.subBranchId = branchId ? String(branchId) : '';
        if (!branchId) {
            subBranchDisplay.value = 'No sub branch assigned';
            return;
        }
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
                    if (rateInput && data.shipment && !isWarehouse) {
                        const rateValue = data.shipment.default_rate;
                        if (rateValue !== null && rateValue !== undefined && String(rateValue) !== '') {
                            rateInput.value = rateValue;
                        }
                    }
                    shipmentOriginCountryId = data.shipment?.origin_country_id || null;
                    if (data.shipment && role === 'Warehouse' && data.shipment.status !== 'active') {
                        if (createForm) {
                            createForm.classList.add('is-hidden');
                        }
                        showNotice('Shipment is not active. Warehouse orders can only be created while status is active.', 'error');
                    }
                    loadCustomers();
                })
                .catch((error) => {
                    showNotice(`Shipment data load failed: ${error.message}`, 'error');
                });
        }
    });
    const refreshCustomers = async (query = '') => {
        if (!query) {
            await loadCustomers('');
            return;
        }
        const existingMatch = findCustomerMatch(query);
        if (existingMatch) {
            return;
        }
        await loadCustomers(query);
        if (customerInput) {
            const value = customerInput.value.trim();
            if (value) {
                syncCustomerBranch(value);
            }
        }
    };

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
            if (findCustomerMatch(value)) {
                syncCustomerBranch(value);
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
    const countryFilter = page.querySelector('[data-country-filter]');
    const prevButton = page.querySelector('[data-customers-prev]');
    const nextButton = page.querySelector('[data-customers-next]');
    const pageLabel = page.querySelector('[data-customers-page]');

    const { role, branchId } = getUserContext();
    const fullAccess = hasFullCustomerAccess(role);
    const limit = 5;
    let offset = 0;
    let lastFilters = {};
    const groupState = new Map();

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

    const loadCountries = async () => {
        if (!countryFilter) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/countries/list.php?limit=300`);
            data.data.forEach((country) => {
                const option = document.createElement('option');
                option.value = country.id;
                option.textContent = country.name;
                countryFilter.appendChild(option);
            });
        } catch (error) {
            showNotice(`Countries load failed: ${error.message}`, 'error');
        }
    };

    const renderRows = (rows) => {
        if (!tableBody) {
            return;
        }
        if (!rows.length) {
            tableBody.innerHTML = '<tr><td colspan="7" class="muted">No customers found.</td></tr>';
            return;
        }
        const groups = new Map();
        rows.forEach((row) => {
            const key = row.account_id ? `account:${row.account_id}` : `customer:${row.id}`;
            if (!groups.has(key)) {
                groups.set(key, {
                    key,
                    account_id: row.account_id,
                    portal_username: row.portal_username,
                    portal_phone: row.portal_phone,
                    profiles: [],
                });
            }
            groups.get(key).profiles.push(row);
        });

        const visibleGroups = new Set();
        groups.forEach((group) => {
            if (group.profiles.length > 1) {
                visibleGroups.add(group.key);
            }
        });
        groupState.forEach((_, key) => {
            if (!visibleGroups.has(key)) {
                groupState.delete(key);
            }
        });

        const rowsHtml = [];
        groups.forEach((group) => {
            if (group.profiles.length === 1) {
                const row = group.profiles[0];
                rowsHtml.push(
                    `<tr>
                        <td>${row.name || '-'}</td>
                        <td>${row.code || '-'}</td>
                        <td>${row.profile_country_name || '-'}</td>
                        <td>${row.sub_branch_name || '-'}</td>
                        <td>${row.balance || '0.00'}</td>
                        <td>${row.portal_username || '-'}</td>
                        <td>
                            <a class="text-link" href="${window.APP_BASE}/views/internal/customer_view?id=${row.id}">Open</a>
                            |
                            <a class="text-link" href="${window.APP_BASE}/views/internal/customer_edit?id=${row.id}">Edit</a>
                        </td>
                    </tr>`
                );
                return;
            }

            const isOpen = groupState.has(group.key) ? groupState.get(group.key) : false;
            groupState.set(group.key, isOpen);
            const toggleLabel = isOpen ? 'Hide profiles' : 'Show profiles';
            const profileLabel = `${group.profiles.length} profiles`;
            const accountLabel = group.portal_username
                ? group.portal_username
                : group.account_id
                    ? `Account #${group.account_id}`
                    : 'Account';
            const metaParts = [];
            if (group.portal_phone) {
                metaParts.push(group.portal_phone);
            }
            metaParts.push(profileLabel);
            const metaText = metaParts.join(' | ');
            rowsHtml.push(
                `<tr data-group-header data-group-key="${group.key}">
                    <td colspan="7">
                        <button class="button ghost small" type="button" data-group-toggle data-group-key="${group.key}" data-group-open="${isOpen ? 'true' : 'false'}">${toggleLabel}</button>
                        <strong>Account: ${accountLabel}</strong>
                        <span class="muted">${metaText}</span>
                    </td>
                </tr>`
            );
            group.profiles.forEach((row) => {
                rowsHtml.push(
                    `<tr data-group-item="${group.key}" class="${isOpen ? '' : 'is-hidden'}">
                        <td>${row.name || '-'}</td>
                        <td>${row.code || '-'}</td>
                        <td>${row.profile_country_name || '-'}</td>
                        <td>${row.sub_branch_name || '-'}</td>
                        <td>${row.balance || '0.00'}</td>
                        <td>${row.portal_username || '-'}</td>
                        <td>
                            <a class="text-link" href="${window.APP_BASE}/views/internal/customer_view?id=${row.id}">Open</a>
                            |
                            <a class="text-link" href="${window.APP_BASE}/views/internal/customer_edit?id=${row.id}">Edit</a>
                        </td>
                    </tr>`
                );
            });
        });

        tableBody.innerHTML = rowsHtml.join('');

        tableBody.querySelectorAll('[data-group-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const key = button.getAttribute('data-group-key');
                if (!key) {
                    return;
                }
                const currentlyOpen = button.getAttribute('data-group-open') === 'true';
                const nextOpen = !currentlyOpen;
                button.setAttribute('data-group-open', nextOpen ? 'true' : 'false');
                button.textContent = nextOpen ? 'Hide profiles' : 'Show profiles';
                groupState.set(key, nextOpen);
                tableBody.querySelectorAll(`[data-group-item="${key}"]`).forEach((row) => {
                    row.classList.toggle('is-hidden', !nextOpen);
                });
            });
        });
    };

    const loadCustomers = async (filters = {}) => {
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="7" class="muted">Loading customers...</td></tr>';
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
    if (!fullAccess && countryFilter) {
        countryFilter.classList.add('is-hidden');
    }

    if (fullAccess) {
        loadBranches();
        loadCountries();
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

function initStaffPage() {
    const page = document.querySelector('[data-staff-page]');
    if (!page) {
        return;
    }

    const filterForm = page.querySelector('[data-staff-filter]');
    const tableBody = page.querySelector('[data-staff-table]');
    const statusStack = page.querySelector('[data-staff-status]');
    const refreshButton = page.querySelector('[data-staff-refresh]');
    const branchFilter = page.querySelector('[data-branch-filter]');
    const prevButton = page.querySelector('[data-staff-prev]');
    const nextButton = page.querySelector('[data-staff-next]');
    const pageLabel = page.querySelector('[data-staff-page]');
    const addButton = page.querySelector('[data-staff-add]');
    const drawer = page.querySelector('[data-staff-drawer]');
    const form = page.querySelector('[data-staff-form]');
    const formTitle = page.querySelector('[data-staff-form-title]');
    const submitLabel = page.querySelector('[data-staff-submit-label]');
    const drawerStatus = page.querySelector('[data-staff-form-status]');
    const staffIdField = page.querySelector('[data-staff-id]');
    const branchSelect = page.querySelector('[data-branch-select]');
    const branchField = page.querySelector('[data-branch-field]');
    const baseSalaryInput = form ? form.querySelector('[name="base_salary"]') : null;
    const drawerCloseButtons = page.querySelectorAll('[data-staff-drawer-close]');
    const canEdit = page.getAttribute('data-can-edit') === '1';

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
        if (drawerStatus) {
            drawerStatus.innerHTML = '';
        }
    };

    const clearDynamicOptions = (select) => {
        if (!select) {
            return;
        }
        select.querySelectorAll('option[data-dynamic]').forEach((option) => option.remove());
    };

    const loadBranches = async () => {
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/branches/list.php?limit=200`);
            const branches = data.data || [];
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
        } catch (error) {
            showNotice(`Branches load failed: ${error.message}`, 'error');
        }
    };

    const renderRows = (rows) => {
        if (!tableBody) {
            return;
        }
        if (!rows.length) {
            tableBody.innerHTML = '<tr><td colspan="6" class="muted">No staff found.</td></tr>';
            return;
        }
        tableBody.innerHTML = rows
            .map((row) => {
                const salaryLabel = row.base_salary ?? '0.00';
                const actions = [];
                actions.push(
                    `<a class="text-link" href="${window.APP_BASE}/views/internal/staff_view?id=${row.id}">Open</a>`
                );
                if (canEdit) {
                    actions.push(
                        `<button class="text-link" type="button" data-staff-edit data-staff-id="${row.id}">Edit</button>`
                    );
                    actions.push(
                        `<button class="text-link" type="button" data-staff-delete data-staff-id="${row.id}">Delete</button>`
                    );
                }
                return `<tr>
                    <td>${row.name || '-'}</td>
                    <td>${row.branch_name || '-'}</td>
                    <td>${row.position || '-'}</td>
                    <td>${salaryLabel}</td>
                    <td>${row.status || '-'}</td>
                    <td>${actions.join(' | ')}</td>
                </tr>`;
            })
            .join('');
    };

    const loadStaff = async (filters = {}) => {
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="6" class="muted">Loading staff...</td></tr>';
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
            const data = await fetchJson(`${window.APP_BASE}/api/staff/list.php?${params.toString()}`);
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
            bindRowActions();
        } catch (error) {
            renderRows([]);
            showNotice(`Staff load failed: ${error.message}`, 'error');
        }
    };

    const fillForm = (staff = {}) => {
        if (!form) {
            return;
        }
        form.querySelector('[name="name"]').value = staff.name || '';
        form.querySelector('[name="phone"]').value = staff.phone || '';
        form.querySelector('[name="position"]').value = staff.position || '';
        if (branchSelect) {
            branchSelect.value = staff.branch_id || '';
        }
        form.querySelector('[name="base_salary"]').value = staff.base_salary ?? '';
        form.querySelector('[name="status"]').value = staff.status || 'active';
        form.querySelector('[name="hired_at"]').value = staff.hired_at || '';
        form.querySelector('[name="note"]').value = staff.note || '';
    };

    const openForCreate = () => {
        if (formTitle) {
            formTitle.textContent = 'Add staff';
        }
        if (submitLabel) {
            submitLabel.textContent = 'Add staff';
        }
        if (staffIdField) {
            staffIdField.value = '';
        }
        if (baseSalaryInput) {
            baseSalaryInput.readOnly = false;
        }
        if (form) {
            form.reset();
        }
        openDrawer();
    };

    const openForEdit = async (staffId) => {
        if (!staffId) {
            return;
        }
        if (formTitle) {
            formTitle.textContent = 'Edit staff';
        }
        if (submitLabel) {
            submitLabel.textContent = 'Save changes';
        }
        if (staffIdField) {
            staffIdField.value = staffId;
        }
        if (baseSalaryInput) {
            baseSalaryInput.readOnly = true;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/staff/view.php?staff_id=${encodeURIComponent(staffId)}`);
            fillForm(data.staff || {});
            openDrawer();
        } catch (error) {
            showFormNotice(`Load failed: ${error.message}`, 'error');
        }
    };

    const bindRowActions = () => {
        if (!tableBody) {
            return;
        }
        if (canEdit) {
            tableBody.querySelectorAll('[data-staff-edit]').forEach((button) => {
                button.addEventListener('click', () => {
                    const staffId = button.getAttribute('data-staff-id');
                    openForEdit(staffId);
                });
            });
            tableBody.querySelectorAll('[data-staff-delete]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const staffId = button.getAttribute('data-staff-id');
                    if (!staffId) {
                        return;
                    }
                    if (!window.confirm('Delete this staff member?')) {
                        return;
                    }
                    try {
                        await fetchJson(`${window.APP_BASE}/api/staff/delete.php`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ staff_id: staffId }),
                        });
                        showNotice('Staff member deleted.', 'success');
                        loadStaff(lastFilters);
                    } catch (error) {
                        showNotice(`Delete failed: ${error.message}`, 'error');
                    }
                });
            });
        }
    };

    if (!fullAccess && branchFilter) {
        branchFilter.classList.add('is-hidden');
    }
    if (!fullAccess && branchField) {
        branchField.classList.add('is-hidden');
    }

    if (fullAccess) {
        loadBranches();
    }

    if (addButton) {
        addButton.addEventListener('click', () => openForCreate());
    }

    drawerCloseButtons.forEach((button) => button.addEventListener('click', closeDrawer));

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(filterForm);
            const filters = Object.fromEntries(formData.entries());
            if (!fullAccess && branchId) {
                filters.branch_id = branchId;
            }
            offset = 0;
            lastFilters = filters;
            loadStaff(filters);
        });
    }

    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            if (filterForm) {
                const formData = new FormData(filterForm);
                const filters = Object.fromEntries(formData.entries());
                if (!fullAccess && branchId) {
                    filters.branch_id = branchId;
                }
                offset = 0;
                lastFilters = filters;
                loadStaff(filters);
            } else {
                offset = 0;
                lastFilters = {};
                loadStaff();
            }
        });
    }

    if (prevButton) {
        prevButton.addEventListener('click', () => {
            if (offset === 0) {
                return;
            }
            offset = Math.max(0, offset - limit);
            loadStaff(lastFilters);
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => {
            offset += limit;
            loadStaff(lastFilters);
        });
    }

    if (form) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (drawerStatus) {
                drawerStatus.innerHTML = '';
            }
            const formData = new FormData(form);
            const payload = Object.fromEntries(formData.entries());
            const staffId = staffIdField ? staffIdField.value : '';
            if (!payload.name) {
                showFormNotice('Name is required.', 'error');
                return;
            }
            if (fullAccess && !payload.branch_id) {
                showFormNotice('Branch is required.', 'error');
                return;
            }
            if (!fullAccess) {
                delete payload.branch_id;
                if (branchId) {
                    payload.branch_id = branchId;
                }
            }
            if (payload.base_salary !== undefined && String(payload.base_salary).trim() === '') {
                delete payload.base_salary;
            }
            if (payload.hired_at !== undefined && String(payload.hired_at).trim() === '') {
                delete payload.hired_at;
            }
            if (payload.note !== undefined && String(payload.note).trim() === '') {
                delete payload.note;
            }
            if (staffId) {
                delete payload.base_salary;
            }
            try {
                if (staffId) {
                    payload.staff_id = staffId;
                    await fetchJson(`${window.APP_BASE}/api/staff/update.php`, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    showNotice('Staff updated.', 'success');
                } else {
                    const data = await fetchJson(`${window.APP_BASE}/api/staff/create.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    showNotice(`Staff created (#${data.id}).`, 'success');
                }
                closeDrawer();
                loadStaff(lastFilters);
            } catch (error) {
                showFormNotice(`Save failed: ${error.message}`, 'error');
            }
        });
    }

    loadStaff();
}

function initStaffView() {
    const page = document.querySelector('[data-staff-view]');
    if (!page) {
        return;
    }

    const staffId = page.getAttribute('data-staff-id');
    const canEdit = page.getAttribute('data-can-edit') === '1';
    const details = page.querySelectorAll('[data-staff-detail]');
    const expensesTable = page.querySelector('[data-staff-expenses]');
    const expensesPrev = page.querySelector('[data-staff-expenses-prev]');
    const expensesNext = page.querySelector('[data-staff-expenses-next]');
    const expensesPageLabel = page.querySelector('[data-staff-expenses-page]');
    const statusStack = page.querySelector('[data-staff-view-status]');
    const salaryForm = page.querySelector('[data-staff-salary-form]');
    const advanceForm = page.querySelector('[data-staff-advance-form]');
    const bonusForm = page.querySelector('[data-staff-bonus-form]');
    const salaryStatus = page.querySelector('[data-staff-salary-status]');
    const advanceStatus = page.querySelector('[data-staff-advance-status]');
    const bonusStatus = page.querySelector('[data-staff-bonus-status]');
    const deleteButton = page.querySelector('[data-staff-delete]');
    const pageSize = 5;
    let expensesPage = 0;
    let expensesData = [];

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

    const showFormNotice = (stack, message, type = 'error') => {
        if (!stack) {
            showNotice(message, type);
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        stack.appendChild(notice);
        setTimeout(() => notice.remove(), 6000);
    };

    if (!staffId) {
        showNotice('Missing staff id.', 'error');
        return;
    }

    const paginateRows = (rows, pageIndex) => rows.slice(pageIndex * pageSize, pageIndex * pageSize + pageSize);
    const escapeHtml = (value) =>
        String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#39;');
    const formatAmount = (value) => {
        const num = Number(value ?? 0);
        return Number.isFinite(num) ? num.toFixed(2) : '0.00';
    };

    const renderExpenses = (rows) => {
        if (!expensesTable) {
            return;
        }
        if (!rows || rows.length === 0) {
            expensesTable.innerHTML = '<tr><td colspan="6" class="muted">No expenses found.</td></tr>';
            return;
        }
        const pageRows = paginateRows(rows, expensesPage);
        expensesTable.innerHTML = pageRows
            .map((exp) => {
                const typeLabel = exp.type
                    ? exp.type === 'salary_adjustment'
                        ? 'Salary adjustment'
                        : exp.type === 'advance'
                          ? 'Advance'
                          : exp.type === 'bonus'
                            ? 'Bonus'
                            : exp.type
                    : '-';
                return `<tr>
                    <td>${typeLabel}</td>
                    <td>${exp.amount ?? '0.00'}</td>
                    <td>${exp.salary_before ?? '-'}</td>
                    <td>${exp.salary_after ?? '-'}</td>
                    <td>${exp.expense_date || exp.created_at || '-'}</td>
                    <td>${exp.note || '-'}</td>
                </tr>`;
            })
            .join('');
        if (expensesPrev) {
            expensesPrev.disabled = expensesPage === 0;
        }
        if (expensesNext) {
            expensesNext.disabled = rows.length <= (expensesPage + 1) * pageSize;
        }
        if (expensesPageLabel) {
            expensesPageLabel.textContent = `Page ${expensesPage + 1}`;
        }
    };

    const loadStaffView = async () => {
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/staff/view.php?staff_id=${encodeURIComponent(staffId)}`);
            const staff = data.staff || {};
            details.forEach((el) => {
                const key = el.getAttribute('data-staff-detail');
                const value = staff[key];
                el.textContent = value !== null && value !== undefined && value !== '' ? value : '--';
            });
            expensesData = data.expenses || [];
            expensesPage = 0;
            renderExpenses(expensesData);
        } catch (error) {
            showNotice(`Load failed: ${error.message}`, 'error');
        }
    };

    if (expensesPrev) {
        expensesPrev.addEventListener('click', () => {
            if (expensesPage === 0) {
                return;
            }
            expensesPage -= 1;
            renderExpenses(expensesData);
        });
    }
    if (expensesNext) {
        expensesNext.addEventListener('click', () => {
            if (expensesData.length <= (expensesPage + 1) * pageSize) {
                return;
            }
            expensesPage += 1;
            renderExpenses(expensesData);
        });
    }

    if (deleteButton && canEdit) {
        deleteButton.addEventListener('click', async () => {
            if (!window.confirm('Delete this staff member?')) {
                return;
            }
            try {
                await fetchJson(`${window.APP_BASE}/api/staff/delete.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ staff_id: staffId }),
                });
                window.location.href = `${window.APP_BASE}/views/internal/staff`;
            } catch (error) {
                showNotice(`Delete failed: ${error.message}`, 'error');
            }
        });
    }

    if (salaryForm && canEdit) {
        salaryForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (salaryStatus) {
                salaryStatus.innerHTML = '';
            }
            const formData = new FormData(salaryForm);
            const payload = Object.fromEntries(formData.entries());
            payload.staff_id = staffId;
            try {
                await fetchJson(`${window.APP_BASE}/api/staff/adjust_salary.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showFormNotice(salaryStatus, 'Salary updated.', 'success');
                salaryForm.reset();
                loadStaffView();
            } catch (error) {
                showFormNotice(salaryStatus, `Update failed: ${error.message}`, 'error');
            }
        });
    }

    const bindExpenseForm = (formEl, type, statusEl) => {
        if (!formEl || !canEdit) {
            return;
        }
        formEl.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (statusEl) {
                statusEl.innerHTML = '';
            }
            const formData = new FormData(formEl);
            const payload = Object.fromEntries(formData.entries());
            payload.staff_id = staffId;
            payload.type = type;
            try {
                await fetchJson(`${window.APP_BASE}/api/staff/expense_create.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showFormNotice(statusEl, `${type === 'advance' ? 'Advance' : 'Bonus'} recorded.`, 'success');
                formEl.reset();
                loadStaffView();
            } catch (error) {
                showFormNotice(statusEl, `Save failed: ${error.message}`, 'error');
            }
        });
    };

    bindExpenseForm(advanceForm, 'advance', advanceStatus);
    bindExpenseForm(bonusForm, 'bonus', bonusStatus);

    loadStaffView();
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

    const loadCountries = async () => {
        if (!countryFilter) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/countries/list.php?limit=300`);
            data.data.forEach((country) => {
                const option = document.createElement('option');
                option.value = country.id;
                option.textContent = country.name;
                countryFilter.appendChild(option);
            });
        } catch (error) {
            showNotice(`Countries load failed: ${error.message}`, 'error');
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
    const countrySelect = page.querySelector('[data-country-select]');
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

    const loadCountries = async () => {
        if (!countrySelect) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/countries/list.php?limit=300`);
            data.data.forEach((country) => {
                const option = document.createElement('option');
                option.value = country.id;
                option.textContent = country.name;
                countrySelect.appendChild(option);
            });
        } catch (error) {
            showNotice(`Countries load failed: ${error.message}`, 'error');
        }
    };

    if (!fullAccess && branchField) {
        branchField.classList.add('is-hidden');
    }

    if (fullAccess) {
        loadBranches();
    }

    loadCountries();

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
            if (!payload.profile_country_id) {
                showNotice('Profile country is required.', 'error');
                return;
            }
            if (!payload.phone) {
                showNotice('Phone is required.', 'error');
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
    const countrySelect = page.querySelector('[data-country-select]');
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

    const loadCountries = async () => {
        if (!countrySelect) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/countries/list.php?limit=300`);
            data.data.forEach((country) => {
                const option = document.createElement('option');
                option.value = country.id;
                option.textContent = country.name;
                countrySelect.appendChild(option);
            });
        } catch (error) {
            showNotice(`Countries load failed: ${error.message}`, 'error');
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
        if (countrySelect && customer.profile_country_id) {
            countrySelect.value = customer.profile_country_id;
        }
    };

    if (!fullAccess && branchField) {
        branchField.classList.add('is-hidden');
    }

    if (fullAccess) {
        loadBranches();
    }
    loadCountries();

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
            if (!payload.profile_country_id) {
                showNotice('Profile country is required.', 'error');
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
    const profilesTable = page.querySelector('[data-customer-profiles]');
    const invoicesTable = page.querySelector('[data-customer-invoices]');
    const uninvoicedTable = page.querySelector('[data-customer-uninvoiced]');
    const transactionsTable = page.querySelector('[data-customer-transactions]');
    const paymentForm = page.querySelector('[data-customer-payment-form]');
    const paymentStatus = page.querySelector('[data-customer-payment-status]');
    const paymentAmountInput = page.querySelector('[data-customer-payment-amount]');
    const paymentMethodSelect = page.querySelector('[data-customer-payment-method]');
    const paymentDateInput = page.querySelector('[data-customer-payment-date]');
    const paymentInvoiceSelect = page.querySelector('[data-customer-payment-invoice]');
    const paymentWhishInput = page.querySelector('[data-customer-payment-whish]');
    const paymentNoteInput = page.querySelector('[data-customer-payment-note]');
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
    const uninvoicedPrev = page.querySelector('[data-customer-uninvoiced-prev]');
    const uninvoicedNext = page.querySelector('[data-customer-uninvoiced-next]');
    const uninvoicedPageLabel = page.querySelector('[data-customer-uninvoiced-page]');
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
    let uninvoicedPage = 0;
    let transactionsPage = 0;
    let ordersPage = 0;
    let mediaPage = 0;
    let invoicesData = [];
    let uninvoicedData = [];
    let transactionsData = [];
    let ordersData = [];
    let mediaData = [];
    let currentMediaOrderId = null;
    let currentAccountId = null;
    let currentCustomerBranchId = null;
    const invoiceMap = new Map();

    const escapeHtml = (value) =>
        String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#39;');

    const formatAmount = (value) => {
        const num = Number(value ?? 0);
        return Number.isFinite(num) ? num.toFixed(2) : '0.00';
    };

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

    const renderUninvoiced = (rows) => {
        if (!uninvoicedTable) {
            return;
        }
        if (!rows || rows.length === 0) {
            uninvoicedTable.innerHTML = '<tr><td colspan="5" class="muted">No un-invoiced orders found.</td></tr>';
            updatePager(uninvoicedPrev, uninvoicedNext, uninvoicedPageLabel, uninvoicedPage, rows || []);
            return;
        }
        const pageRows = paginateRows(rows, uninvoicedPage);
        uninvoicedTable.innerHTML = pageRows
            .map(
                (order) => `<tr>
                    <td>${escapeHtml(order.tracking_number || '-')}</td>
                    <td>${escapeHtml(order.shipment_number || order.shipment_id || '-')}</td>
                    <td>${escapeHtml(order.fulfillment_status || '-')}</td>
                    <td>${formatAmount(order.total_price)}</td>
                    <td>${escapeHtml(order.created_at || '-')}</td>
                </tr>`
            )
            .join('');
        updatePager(uninvoicedPrev, uninvoicedNext, uninvoicedPageLabel, uninvoicedPage, rows);
    };

    const renderTransactions = (rows) => {
        if (!transactionsTable) {
            return;
        }
        if (!rows || rows.length === 0) {
            transactionsTable.innerHTML = '<tr><td colspan="6" class="muted">No transactions found.</td></tr>';
            updatePager(transactionsPrev, transactionsNext, transactionsPageLabel, transactionsPage, rows || []);
            return;
        }
        const pageRows = paginateRows(rows, transactionsPage);
        transactionsTable.innerHTML = pageRows
            .map((tx) => {
                const typeKey = tx.entry_type || tx.type || '-';
                const typeLabelMap = {
                    order_charge: 'Order charge',
                    order_reversal: 'Order reversal',
                    payment: 'Payment',
                    deposit: 'Deposit',
                    refund: 'Refund',
                    adjustment: 'Adjustment',
                    admin_settlement: 'Admin settlement',
                };
                const typeLabel = typeLabelMap[typeKey] || typeKey;
                const dateLabel = tx.payment_date || tx.created_at || '-';
                let referenceLabel = '-';
                if (tx.reference_type === 'order') {
                    const tracking = tx.tracking_number || (tx.reference_id ? `Order #${tx.reference_id}` : 'Order');
                    const shipment = tx.shipment_number ? ` | ${tx.shipment_number}` : '';
                    referenceLabel = `${tracking}${shipment}`;
                } else if (tx.reference_type === 'transaction') {
                    referenceLabel = tx.invoice_nos ? `Invoice ${tx.invoice_nos}` : 'Payment';
                }
                const receiptLink =
                    tx.reference_type === 'transaction' && tx.reference_id
                        ? `<a class="text-link" target="_blank" rel="noopener" href="${window.APP_BASE}/views/internal/transaction_receipt_print?id=${tx.reference_id}">Print</a>`
                        : '-';
                return `<tr>
                    <td>${escapeHtml(typeLabel)}</td>
                    <td>${formatAmount(tx.amount)}</td>
                    <td>${escapeHtml(tx.payment_method || '-')}</td>
                    <td>${escapeHtml(dateLabel)}</td>
                    <td>${escapeHtml(referenceLabel)}</td>
                    <td>${receiptLink}</td>
                </tr>`;
            })
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

    const renderProfiles = (rows) => {
        if (!profilesTable) {
            return;
        }
        if (!rows || rows.length === 0) {
            profilesTable.innerHTML = '<tr><td colspan="7" class="muted">No profiles found.</td></tr>';
            return;
        }
        profilesTable.innerHTML = rows
            .map((profile) => {
                const isCurrent = String(profile.id) === String(customerId);
                const nameLabel = `${profile.name || '-'}${isCurrent ? ' (current)' : ''}`;
                return `<tr>
                        <td>${nameLabel}</td>
                        <td>${profile.code || '-'}</td>
                        <td>${profile.profile_country_name || '-'}</td>
                        <td>${profile.sub_branch_name || '-'}</td>
                        <td>${profile.balance || '0.00'}</td>
                        <td>${profile.portal_username || '-'}</td>
                        <td>
                            <a class="text-link" href="${window.APP_BASE}/views/internal/customer_view?id=${profile.id}">Open</a>
                            |
                            <a class="text-link" href="${window.APP_BASE}/views/internal/customer_edit?id=${profile.id}">Edit</a>
                        </td>
                    </tr>`;
            })
            .join('');
    };

    const loadProfilesForAccount = async (accountId) => {
        if (!profilesTable) {
            return;
        }
        if (!accountId) {
            profilesTable.innerHTML = '<tr><td colspan="7" class="muted">No portal account linked.</td></tr>';
            return;
        }
        profilesTable.innerHTML = '<tr><td colspan="7" class="muted">Loading profiles...</td></tr>';
        try {
            const data = await fetchJson(
                `${window.APP_BASE}/api/customers/list.php?account_id=${encodeURIComponent(accountId)}&limit=200`
            );
            renderProfiles(data.data || []);
        } catch (error) {
            profilesTable.innerHTML = '<tr><td colspan="7" class="muted">No profiles found.</td></tr>';
            showNotice(`Profiles load failed: ${error.message}`, 'error');
        }
    };

    const loadPaymentMethods = async () => {
        if (!paymentMethodSelect) {
            return;
        }
        paymentMethodSelect.innerHTML = '<option value="">Select method</option>';
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/payment_methods/list.php`);
            (data.data || []).forEach((method) => {
                const option = document.createElement('option');
                option.value = method.id;
                option.textContent = method.name;
                paymentMethodSelect.appendChild(option);
            });
        } catch (error) {
            showPaymentNotice(`Payment methods load failed: ${error.message}`, 'error');
        }
    };

    const loadPaymentInvoices = async () => {
        if (!paymentInvoiceSelect) {
            return;
        }
        paymentInvoiceSelect.innerHTML = '<option value="">No invoice</option>';
        invoiceMap.clear();
        try {
            const params = new URLSearchParams({ customer_id: customerId, limit: '200' });
            const data = await fetchJson(`${window.APP_BASE}/api/invoices/list.php?${params.toString()}`);
            const rows = (data.data || []).filter(
                (row) => row.status !== 'void' && Number(row.due_total ?? 0) > 0
            );
            rows.forEach((invoice) => {
                invoiceMap.set(String(invoice.id), invoice);
                const option = document.createElement('option');
                option.value = invoice.id;
                option.textContent = `${invoice.invoice_no} - Due ${formatAmount(invoice.due_total)}`;
                paymentInvoiceSelect.appendChild(option);
            });
        } catch (error) {
            showPaymentNotice(`Invoices load failed: ${error.message}`, 'error');
        }
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

    const loadUninvoiced = async () => {
        if (!uninvoicedTable) {
            return;
        }
        uninvoicedTable.innerHTML = '<tr><td colspan="5" class="muted">Loading orders...</td></tr>';
        try {
            const params = new URLSearchParams({
                customer_id: customerId,
                limit: '200',
                include_all: '1',
            });
            const data = await fetchJson(`${window.APP_BASE}/api/orders/uninvoiced.php?${params.toString()}`);
            uninvoicedData = data.data || [];
            uninvoicedPage = 0;
            renderUninvoiced(uninvoicedData);
        } catch (error) {
            uninvoicedTable.innerHTML = '<tr><td colspan="5" class="muted">Unable to load orders.</td></tr>';
            showNotice(`Un-invoiced orders load failed: ${error.message}`, 'error');
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
            currentCustomerBranchId = customer.sub_branch_id ? Number(customer.sub_branch_id) : null;
            const accountIdValue = customer.account_id ? String(customer.account_id) : '';
            if (accountIdValue !== currentAccountId) {
                currentAccountId = accountIdValue;
                loadProfilesForAccount(accountIdValue);
            }
            invoicesData = data.invoices || [];
            transactionsData = data.transactions || [];
            ordersData = data.orders || [];
            invoicesPage = 0;
            transactionsPage = 0;
            ordersPage = 0;
            renderInvoices(invoicesData);
            await loadUninvoiced();
            renderTransactions(transactionsData);
            renderOrders(ordersData);
            bindOrderActions();
            await loadPaymentInvoices();
        } catch (error) {
            showNotice(`Load failed: ${error.message}`, 'error');
        }
    };

    if (paymentInvoiceSelect && paymentAmountInput) {
        paymentInvoiceSelect.addEventListener('change', () => {
            const invoiceId = paymentInvoiceSelect.value;
            const invoice = invoiceMap.get(String(invoiceId));
            if (!invoice) {
                return;
            }
            const currentValue = Number(paymentAmountInput.value || 0);
            if (!currentValue || currentValue <= 0) {
                paymentAmountInput.value = formatAmount(invoice.due_total);
            }
        });
    }

    if (paymentForm) {
        paymentForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const amountValue = Number(paymentAmountInput ? paymentAmountInput.value : 0);
            if (!Number.isFinite(amountValue) || amountValue <= 0) {
                showPaymentNotice('Enter a valid amount.', 'error');
                return;
            }
            const methodId = paymentMethodSelect ? paymentMethodSelect.value : '';
            if (!methodId) {
                showPaymentNotice('Select a payment method.', 'error');
                return;
            }
            const invoiceId = paymentInvoiceSelect ? paymentInvoiceSelect.value : '';
            const invoice = invoiceId ? invoiceMap.get(String(invoiceId)) : null;
            if (invoiceId && !invoice) {
                showPaymentNotice('Selected invoice is not available.', 'error');
                return;
            }
            if (invoice && amountValue > Number(invoice.due_total ?? 0) + 0.0001) {
                showPaymentNotice('Amount exceeds invoice due total.', 'error');
                return;
            }
            const resolvedBranchId = invoice
                ? Number(invoice.branch_id || 0)
                : currentCustomerBranchId || branchId || 0;
            if (!resolvedBranchId) {
                showPaymentNotice('Branch is required to record payment.', 'error');
                return;
            }
            const payload = {
                branch_id: resolvedBranchId,
                customer_id: customerId,
                type: 'payment',
                payment_method_id: Number(methodId),
                amount: amountValue,
                payment_date: paymentDateInput ? paymentDateInput.value : null,
                whish_phone: paymentWhishInput ? paymentWhishInput.value : null,
                note: paymentNoteInput ? paymentNoteInput.value : null,
            };
            try {
                const tx = await fetchJson(`${window.APP_BASE}/api/transactions/create.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                if (invoice && tx && tx.id) {
                    try {
                        await fetchJson(`${window.APP_BASE}/api/transactions/allocate.php`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                transaction_id: tx.id,
                                allocations: [{ invoice_id: invoice.id, amount: amountValue }],
                            }),
                        });
                    } catch (error) {
                        showPaymentNotice(`Allocation failed: ${error.message}`, 'error');
                    }
                }
                showPaymentNotice('Payment recorded.', 'success');
                if (paymentForm) {
                    paymentForm.reset();
                }
                await loadCustomerView();
            } catch (error) {
                showPaymentNotice(`Payment failed: ${error.message}`, 'error');
            }
        });
    }

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
    if (uninvoicedPrev) {
        uninvoicedPrev.addEventListener('click', () => {
            if (uninvoicedPage === 0) {
                return;
            }
            uninvoicedPage -= 1;
            renderUninvoiced(uninvoicedData);
        });
    }
    if (uninvoicedNext) {
        uninvoicedNext.addEventListener('click', () => {
            if (uninvoicedData.length <= (uninvoicedPage + 1) * pageSize) {
                return;
            }
            uninvoicedPage += 1;
            renderUninvoiced(uninvoicedData);
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

    loadPaymentMethods();
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

function initExpensesPage() {
    const page = document.querySelector('[data-expenses-page]');
    if (!page) {
        return;
    }

    const filterForm = page.querySelector('[data-expenses-filter]');
    const tableBody = page.querySelector('[data-expenses-table]');
    const statusStack = page.querySelector('[data-expenses-status]');
    const refreshButton = page.querySelector('[data-expenses-refresh]');
    const branchFilter = page.querySelector('[data-branch-filter]');
    const prevButton = page.querySelector('[data-expenses-prev]');
    const nextButton = page.querySelector('[data-expenses-next]');
    const pageLabel = page.querySelector('[data-expenses-page]');
    const addButton = page.querySelector('[data-expenses-add]');
    const drawer = page.querySelector('[data-expenses-drawer]');
    const form = page.querySelector('[data-expenses-form]');
    const formTitle = page.querySelector('[data-expenses-form-title]');
    const submitLabel = page.querySelector('[data-expenses-submit-label]');
    const drawerStatus = page.querySelector('[data-expenses-form-status]');
    const expenseIdField = page.querySelector('[data-expense-id]');
    const branchSelect = page.querySelector('[data-branch-select]');
    const drawerCloseButtons = page.querySelectorAll('[data-expenses-drawer-close]');
    const canEdit = page.getAttribute('data-can-edit') === '1';

    const limit = 5;
    let offset = 0;
    let lastFilters = {};
    const expenseMap = new Map();

    const escapeHtml = (value) =>
        String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#39;');

    const formatAmount = (value) => {
        const num = Number(value ?? 0);
        return Number.isFinite(num) ? num.toFixed(2) : '0.00';
    };

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

    const showPaymentNotice = (message, type = 'error') => {
        if (!paymentStatus) {
            showNotice(message, type);
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        paymentStatus.appendChild(notice);
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
        if (drawerStatus) {
            drawerStatus.innerHTML = '';
        }
    };

    const clearDynamicOptions = (select) => {
        if (!select) {
            return;
        }
        select.querySelectorAll('option[data-dynamic]').forEach((option) => option.remove());
    };

    const loadBranches = async () => {
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/branches/list.php?limit=200`);
            const branches = data.data || [];
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
        } catch (error) {
            showNotice(`Branches load failed: ${error.message}`, 'error');
        }
    };

    const setFormValues = (expense) => {
        if (!form) {
            return;
        }
        if (expenseIdField) {
            expenseIdField.value = expense?.id ? String(expense.id) : '';
        }
        if (formTitle) {
            formTitle.textContent = expense ? 'Edit expense' : 'Add expense';
        }
        if (submitLabel) {
            submitLabel.textContent = expense ? 'Save changes' : 'Add expense';
        }
        form.querySelector('[name="title"]').value = expense?.title || '';
        form.querySelector('[name="amount"]').value = expense?.amount ?? '';
        form.querySelector('[name="expense_date"]').value = expense?.expense_date || '';
        form.querySelector('[name="note"]').value = expense?.note || '';
        if (branchSelect) {
            branchSelect.value = expense?.branch_id ? String(expense.branch_id) : '';
        }
    };

    const renderRows = (rows) => {
        if (!tableBody) {
            return;
        }
        expenseMap.clear();
        if (!rows.length) {
            tableBody.innerHTML = '<tr><td colspan="7" class="muted">No expenses found.</td></tr>';
            return;
        }
        tableBody.innerHTML = rows
            .map((row) => {
                expenseMap.set(String(row.id), row);
                const dateLabel = row.expense_date || row.created_at || '-';
                const shipmentLabel = row.shipment_number
                    ? row.shipment_number
                    : row.shipment_id
                    ? `#${row.shipment_id}`
                    : '-';
                const actions = [];
                if (canEdit) {
                    actions.push(
                        `<button class="text-link" type="button" data-expense-edit data-expense-id="${row.id}">Edit</button>`
                    );
                    actions.push(
                        `<button class="text-link" type="button" data-expense-delete data-expense-id="${row.id}">Delete</button>`
                    );
                }
                return `<tr>
                    <td>${escapeHtml(dateLabel)}</td>
                    <td>${escapeHtml(row.title || '-')}</td>
                    <td>${escapeHtml(row.branch_name || '-')}</td>
                    <td>${escapeHtml(shipmentLabel)}</td>
                    <td>${formatAmount(row.amount)}</td>
                    <td>${escapeHtml(row.note || '-')}</td>
                    <td>${actions.length ? actions.join(' | ') : '-'}</td>
                </tr>`;
            })
            .join('');

        tableBody.querySelectorAll('[data-expense-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                const id = button.getAttribute('data-expense-id');
                if (!id || !expenseMap.has(id)) {
                    return;
                }
                setFormValues(expenseMap.get(id));
                openDrawer();
            });
        });

        tableBody.querySelectorAll('[data-expense-delete]').forEach((button) => {
            button.addEventListener('click', async () => {
                const id = button.getAttribute('data-expense-id');
                if (!id) {
                    return;
                }
                try {
                    await fetchJson(`${window.APP_BASE}/api/expenses/delete.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id }),
                    });
                    showNotice('Expense removed.', 'success');
                    if (rows.length === 1 && offset > 0) {
                        offset = Math.max(0, offset - limit);
                    }
                    loadExpenses(lastFilters);
                } catch (error) {
                    showNotice(`Delete failed: ${error.message}`, 'error');
                }
            });
        });
    };

    const loadExpenses = async (filters = {}) => {
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="6" class="muted">Loading expenses...</td></tr>';
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
            const data = await fetchJson(`${window.APP_BASE}/api/expenses/list.php?${params.toString()}`);
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
            showNotice(`Expenses load failed: ${error.message}`, 'error');
        }
    };

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(filterForm);
            offset = 0;
            lastFilters = Object.fromEntries(formData.entries());
            loadExpenses(lastFilters);
        });
    }

    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            if (filterForm) {
                const formData = new FormData(filterForm);
                offset = 0;
                lastFilters = Object.fromEntries(formData.entries());
                loadExpenses(lastFilters);
            } else {
                offset = 0;
                lastFilters = {};
                loadExpenses();
            }
        });
    }

    if (prevButton) {
        prevButton.addEventListener('click', () => {
            if (offset === 0) {
                return;
            }
            offset = Math.max(0, offset - limit);
            loadExpenses(lastFilters);
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => {
            offset += limit;
            loadExpenses(lastFilters);
        });
    }

    if (addButton) {
        addButton.addEventListener('click', () => {
            setFormValues(null);
            openDrawer();
        });
    }

    if (drawerCloseButtons.length) {
        drawerCloseButtons.forEach((button) => {
            button.addEventListener('click', closeDrawer);
        });
    }

    if (form) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(form);
            const payload = Object.fromEntries(formData.entries());
            const expenseId = payload.expense_id || '';
            if (!payload.title || !payload.amount) {
                showFormNotice('Title and amount are required.', 'error');
                return;
            }
            try {
                if (expenseId) {
                    await fetchJson(`${window.APP_BASE}/api/expenses/update.php`, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    showNotice('Expense updated.', 'success');
                } else {
                    await fetchJson(`${window.APP_BASE}/api/expenses/create.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    showNotice('Expense added.', 'success');
                }
                closeDrawer();
                loadExpenses(lastFilters);
            } catch (error) {
                showFormNotice(`Save failed: ${error.message}`, 'error');
            }
        });
    }

    loadBranches();
    loadExpenses();
}

function initInvoicesPage() {
    const page = document.querySelector('[data-invoices-page]');
    if (!page) {
        return;
    }

    const filterForm = page.querySelector('[data-invoices-filter]');
    const tableBody = page.querySelector('[data-invoices-table]');
    const statusStack = page.querySelector('[data-invoices-status]');
    const refreshButton = page.querySelector('[data-invoices-refresh]');
    const branchFilter = page.querySelector('[data-branch-filter]');
    const customerFilterInput = page.querySelector('[data-invoices-customer-input]');
    const customerFilterId = page.querySelector('[data-invoices-customer-id]');
    const customerFilterList = page.querySelector('#invoice-customer-options');
    const prevButton = page.querySelector('[data-invoices-prev]');
    const nextButton = page.querySelector('[data-invoices-next]');
    const pageLabel = page.querySelector('[data-invoices-page]');
    const addButton = page.querySelector('[data-invoices-add]');
    const drawer = page.querySelector('[data-invoices-drawer]');
    const drawerCloseButtons = page.querySelectorAll('[data-invoices-drawer-close]');
    const form = page.querySelector('[data-invoices-form]');
    const formStatus = page.querySelector('[data-invoice-form-status]');
    const invoiceCustomerInput = page.querySelector('[data-invoice-customer-input]');
    const invoiceCustomerId = page.querySelector('[data-invoice-customer-id]');
    const invoiceCustomerList = page.querySelector('#invoice-create-customer-options');
    const invoiceBranchId = page.querySelector('[data-invoice-branch-id]');
    const invoiceBranchLabel = page.querySelector('[data-invoice-branch-label]');
    const invoiceOrdersTable = page.querySelector('[data-invoice-orders-table]');
    const invoiceOrdersTotal = page.querySelector('[data-invoice-orders-total]');
    const invoiceOrdersAll = page.querySelector('[data-invoice-orders-all]');
    const canEdit = page.getAttribute('data-can-edit') === '1';

    const { role, branchId } = getUserContext();
    const fullAccess = ['Admin', 'Owner', 'Main Branch'].includes(role || '');
    const canVoid = ['Admin', 'Owner'].includes(role || '');
    const limit = 5;
    let offset = 0;
    let lastFilters = {};
    let invoicesData = [];
    const customerMap = new Map();
    const orderMap = new Map();
    const selectedOrderIds = new Set();
    let ordersData = [];
    let selectedBranchId = null;
    let selectedBranchLabel = '';
    let selectedInvoiceCustomerId = null;
    let customerSearchTimer = null;

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
        if (!formStatus) {
            showNotice(message, type);
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        formStatus.appendChild(notice);
        setTimeout(() => notice.remove(), 6000);
    };

    const escapeHtml = (value) =>
        String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#39;');

    const formatAmount = (value) => {
        const num = Number(value ?? 0);
        return Number.isFinite(num) ? num.toFixed(2) : '0.00';
    };

    const updatePager = (count) => {
        if (prevButton) {
            prevButton.disabled = offset === 0;
        }
        if (nextButton) {
            nextButton.disabled = count < limit;
        }
        if (pageLabel) {
            pageLabel.textContent = `Page ${Math.floor(offset / limit) + 1}`;
        }
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
        if (formStatus) {
            formStatus.innerHTML = '';
        }
    };

    const setInvoiceOrdersPlaceholder = (message) => {
        if (!invoiceOrdersTable) {
            return;
        }
        invoiceOrdersTable.innerHTML = `<tr><td colspan="6" class="muted">${escapeHtml(message)}</td></tr>`;
    };

    const updateSelectedTotal = () => {
        let total = 0;
        selectedOrderIds.forEach((orderId) => {
            const order = orderMap.get(orderId);
            if (order) {
                total += Number(order.total_price ?? 0);
            }
        });
        if (invoiceOrdersTotal) {
            invoiceOrdersTotal.textContent = `Selected total: ${formatAmount(total)}`;
        }
        if (invoiceBranchId) {
            invoiceBranchId.value = selectedBranchId ? String(selectedBranchId) : '';
        }
        if (invoiceBranchLabel) {
            invoiceBranchLabel.value = selectedBranchId ? selectedBranchLabel : '';
        }
        if (invoiceOrdersAll) {
            invoiceOrdersAll.checked = ordersData.length > 0 && selectedOrderIds.size === ordersData.length;
        }
    };

    const resetInvoiceOrders = (message = 'Select a customer to load orders.') => {
        selectedOrderIds.clear();
        orderMap.clear();
        ordersData = [];
        selectedBranchId = null;
        selectedBranchLabel = '';
        selectedInvoiceCustomerId = null;
        if (invoiceOrdersAll) {
            invoiceOrdersAll.checked = false;
        }
        setInvoiceOrdersPlaceholder(message);
        updateSelectedTotal();
    };

    const toggleInvoiceOrder = (orderId, isSelected, checkbox) => {
        const order = orderMap.get(orderId);
        if (!order) {
            return;
        }
        const orderBranchId = Number(order.sub_branch_id || 0);
        if (!orderBranchId) {
            showFormNotice('Order branch is missing.', 'error');
            if (checkbox) {
                checkbox.checked = false;
            }
            return;
        }
        if (isSelected) {
            if (selectedBranchId && orderBranchId !== selectedBranchId) {
                showFormNotice('Orders from different branches cannot be invoiced together.', 'error');
                if (checkbox) {
                    checkbox.checked = false;
                }
                return;
            }
            selectedBranchId = selectedBranchId || orderBranchId;
            selectedBranchLabel = selectedBranchLabel || (order.sub_branch_name || `Branch #${orderBranchId}`);
            selectedOrderIds.add(orderId);
        } else {
            selectedOrderIds.delete(orderId);
            if (selectedOrderIds.size === 0) {
                selectedBranchId = null;
                selectedBranchLabel = '';
            }
        }
        updateSelectedTotal();
    };

    const renderInvoiceOrders = () => {
        if (!invoiceOrdersTable) {
            return;
        }
        orderMap.clear();
        selectedOrderIds.clear();
        selectedBranchId = null;
        selectedBranchLabel = '';
        if (invoiceOrdersAll) {
            invoiceOrdersAll.checked = false;
        }
        if (!ordersData.length) {
            setInvoiceOrdersPlaceholder('No un-invoiced orders found.');
            updateSelectedTotal();
            return;
        }
        invoiceOrdersTable.innerHTML = ordersData
            .map((order) => {
                const shipmentLabel = order.shipment_number || (order.shipment_id ? `#${order.shipment_id}` : '-');
                orderMap.set(String(order.id), order);
                return `<tr>
                    <td><input type="checkbox" data-invoice-order value="${order.id}"></td>
                    <td>${escapeHtml(order.tracking_number || '-')}</td>
                    <td>${escapeHtml(shipmentLabel)}</td>
                    <td>${formatAmount(order.total_price)}</td>
                    <td>${escapeHtml(order.created_at || '-')}</td>
                    <td>${escapeHtml(order.sub_branch_name || '-')}</td>
                </tr>`;
            })
            .join('');

        invoiceOrdersTable.querySelectorAll('[data-invoice-order]').forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                toggleInvoiceOrder(String(checkbox.value), checkbox.checked, checkbox);
            });
        });
        updateSelectedTotal();
    };

    const toggleAllOrders = () => {
        if (!invoiceOrdersAll || !invoiceOrdersTable) {
            return;
        }
        if (!ordersData.length) {
            invoiceOrdersAll.checked = false;
            return;
        }
        if (!invoiceOrdersAll.checked) {
            selectedOrderIds.clear();
            selectedBranchId = null;
            selectedBranchLabel = '';
            invoiceOrdersTable.querySelectorAll('[data-invoice-order]').forEach((checkbox) => {
                checkbox.checked = false;
            });
            updateSelectedTotal();
            return;
        }
        const branchIds = new Set(ordersData.map((order) => String(order.sub_branch_id || '')));
        if (branchIds.size > 1) {
            showFormNotice('Orders belong to multiple branches. Select one branch at a time.', 'error');
            invoiceOrdersAll.checked = false;
            return;
        }
        const onlyBranch = Number([...branchIds][0] || 0);
        if (!onlyBranch) {
            showFormNotice('Order branch is missing.', 'error');
            invoiceOrdersAll.checked = false;
            return;
        }
        selectedBranchId = onlyBranch;
        selectedBranchLabel = ordersData[0].sub_branch_name || `Branch #${onlyBranch}`;
        selectedOrderIds.clear();
        ordersData.forEach((order) => {
            selectedOrderIds.add(String(order.id));
        });
        invoiceOrdersTable.querySelectorAll('[data-invoice-order]').forEach((checkbox) => {
            checkbox.checked = true;
        });
        updateSelectedTotal();
    };

    const renderInvoices = (rows) => {
        if (!tableBody) {
            return;
        }
        if (!rows || rows.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="9" class="muted">No invoices found.</td></tr>';
            updatePager(0);
            return;
        }
        tableBody.innerHTML = rows
            .map((row) => {
                const issuedLabel = row.issued_at || '-';
                const issuedMeta = row.issued_by_name ? `${row.issued_by_name} - ${issuedLabel}` : issuedLabel;
                let statusLabel = row.status || '-';
                if (statusLabel === 'partially_paid') {
                    statusLabel = 'Partially paid';
                } else if (statusLabel !== '-' && statusLabel.length > 0) {
                    statusLabel = statusLabel.charAt(0).toUpperCase() + statusLabel.slice(1);
                }
                const actions = [
                    `<a class="text-link" href="${window.APP_BASE}/api/invoices/print.php?id=${row.id}" target="_blank" rel="noopener">Print</a>`,
                ];
                if (canVoid) {
                    actions.push(
                        `<button class="text-link" type="button" data-invoice-void data-invoice-id="${row.id}">Void</button>`
                    );
                }
                return `<tr>
                    <td>${escapeHtml(row.invoice_no || '-')}</td>
                    <td>${escapeHtml(row.customer_name || '-')}</td>
                    <td>${escapeHtml(row.branch_name || '-')}</td>
                    <td>${escapeHtml(statusLabel)}</td>
                    <td>${formatAmount(row.total)}</td>
                    <td>${formatAmount(row.paid_total)}</td>
                    <td>${formatAmount(row.due_total)}</td>
                    <td>${escapeHtml(issuedMeta)}</td>
                    <td>${actions.join(' | ')}</td>
                </tr>`;
            })
            .join('');
        updatePager(rows.length);

        if (canVoid) {
            tableBody.querySelectorAll('[data-invoice-void]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const invoiceId = button.getAttribute('data-invoice-id');
                    if (!invoiceId) {
                        return;
                    }
                    if (!window.confirm('Void this invoice?')) {
                        return;
                    }
                    try {
                        await fetchJson(`${window.APP_BASE}/api/invoices/delete.php`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ invoice_id: invoiceId }),
                        });
                        showNotice('Invoice voided.', 'success');
                        if (offset > 0 && invoicesData.length === 1) {
                            offset = Math.max(0, offset - limit);
                        }
                        loadInvoices(lastFilters);
                    } catch (error) {
                        showNotice(`Void failed: ${error.message}`, 'error');
                    }
                });
            });
        }
    };

    const loadInvoices = async (filters = {}) => {
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="9" class="muted">Loading invoices...</td></tr>';
        }
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== null && value !== undefined && String(value).trim() !== '') {
                params.append(key, String(value));
            }
        });
        if (!fullAccess && branchId) {
            params.set('branch_id', String(branchId));
        }
        params.append('limit', String(limit));
        params.append('offset', String(offset));
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/invoices/list.php?${params.toString()}`);
            invoicesData = data.data || [];
            renderInvoices(invoicesData);
        } catch (error) {
            invoicesData = [];
            renderInvoices([]);
            showNotice(`Invoices load failed: ${error.message}`, 'error');
        }
    };

    const loadBranches = async () => {
        if (!branchFilter || !fullAccess) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/branches/list.php?limit=200`);
            const rows = data.data || [];
            rows.forEach((branch) => {
                const option = document.createElement('option');
                option.value = branch.id;
                option.textContent = branch.name;
                branchFilter.appendChild(option);
            });
        } catch (error) {
            showNotice(`Branches load failed: ${error.message}`, 'error');
        }
    };

    const loadCustomers = async (query = '') => {
        if (!customerFilterList && !invoiceCustomerList) {
            return;
        }
        if (customerFilterList) {
            customerFilterList.innerHTML = '';
        }
        if (invoiceCustomerList) {
            invoiceCustomerList.innerHTML = '';
        }
        customerMap.clear();
        try {
            const params = new URLSearchParams({ limit: '200' });
            if (query) {
                params.append('q', query);
            }
            const data = await fetchJson(`${window.APP_BASE}/api/customers/list.php?${params.toString()}`);
            (data.data || []).forEach((customer) => {
                const phoneValue = customer.phone || customer.portal_phone || '';
                const phone = phoneValue ? ` - ${phoneValue}` : '';
                const countryLabel = customer.profile_country_name ? ` | ${customer.profile_country_name}` : '';
                const label = `${customer.name} (${customer.code})${countryLabel}${phone}`;
                customerMap.set(label, {
                    id: customer.id,
                    name: customer.name || '',
                    code: customer.code || '',
                    phone: customer.phone || '',
                    portalPhone: customer.portal_phone || '',
                });
                if (customerFilterList) {
                    const option = document.createElement('option');
                    option.value = label;
                    customerFilterList.appendChild(option);
                }
                if (invoiceCustomerList) {
                    const option = document.createElement('option');
                    option.value = label;
                    invoiceCustomerList.appendChild(option);
                }
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
            if (data.phone && data.phone.toLowerCase() === normalized) {
                return { label, data };
            }
            if (data.portalPhone && data.portalPhone.toLowerCase() === normalized) {
                return { label, data };
            }
        }
        return null;
    };

    const syncCustomerSelection = (inputEl, idField) => {
        if (!inputEl || !idField) {
            return null;
        }
        const value = String(inputEl.value || '').trim();
        if (!value) {
            idField.value = '';
            return null;
        }
        const match = findCustomerMatch(value);
        if (!match) {
            idField.value = '';
            return null;
        }
        if (match.label !== value) {
            inputEl.value = match.label;
        }
        idField.value = String(match.data.id);
        return match.data;
    };

    const scheduleCustomerSearch = (value) => {
        if (customerSearchTimer) {
            window.clearTimeout(customerSearchTimer);
        }
        customerSearchTimer = window.setTimeout(() => {
            loadCustomers(value);
        }, 250);
    };

    const loadUninvoicedOrders = async (customerIdValue) => {
        if (!invoiceOrdersTable) {
            return;
        }
        setInvoiceOrdersPlaceholder('Loading orders...');
        try {
            const params = new URLSearchParams({ customer_id: String(customerIdValue), limit: '200' });
            const data = await fetchJson(`${window.APP_BASE}/api/orders/uninvoiced.php?${params.toString()}`);
            ordersData = data.data || [];
            renderInvoiceOrders();
        } catch (error) {
            ordersData = [];
            renderInvoiceOrders();
            showFormNotice(`Orders load failed: ${error.message}`, 'error');
        }
    };

    if (!fullAccess && branchFilter) {
        branchFilter.classList.add('is-hidden');
    }

    if (customerFilterInput) {
        customerFilterInput.addEventListener('input', () => {
            if (customerFilterId) {
                customerFilterId.value = '';
            }
            scheduleCustomerSearch(customerFilterInput.value.trim());
        });
        customerFilterInput.addEventListener('change', () => {
            syncCustomerSelection(customerFilterInput, customerFilterId);
        });
    }

    if (invoiceCustomerInput) {
        invoiceCustomerInput.addEventListener('input', () => {
            if (invoiceCustomerId) {
                invoiceCustomerId.value = '';
            }
            resetInvoiceOrders('Select a customer to load orders.');
            const match = findCustomerMatch(invoiceCustomerInput.value.trim());
            if (match) {
                if (invoiceCustomerId) {
                    invoiceCustomerId.value = String(match.data.id);
                }
                if (selectedInvoiceCustomerId !== String(match.data.id)) {
                    selectedInvoiceCustomerId = String(match.data.id);
                    loadUninvoicedOrders(match.data.id);
                }
            }
            scheduleCustomerSearch(invoiceCustomerInput.value.trim());
        });
        invoiceCustomerInput.addEventListener('change', () => {
            const match = syncCustomerSelection(invoiceCustomerInput, invoiceCustomerId);
            if (!match) {
                resetInvoiceOrders('Select a customer to load orders.');
                return;
            }
            selectedInvoiceCustomerId = String(match.data.id);
            loadUninvoicedOrders(match.data.id);
        });
    }

    if (invoiceOrdersAll) {
        invoiceOrdersAll.addEventListener('change', toggleAllOrders);
    }

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            if (customerFilterInput && customerFilterId) {
                syncCustomerSelection(customerFilterInput, customerFilterId);
            }
            const formData = new FormData(filterForm);
            const filters = Object.fromEntries(formData.entries());
            if (!fullAccess && branchId) {
                filters.branch_id = branchId;
            }
            offset = 0;
            lastFilters = filters;
            loadInvoices(filters);
        });
    }

    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            if (filterForm) {
                const formData = new FormData(filterForm);
                const filters = Object.fromEntries(formData.entries());
                if (!fullAccess && branchId) {
                    filters.branch_id = branchId;
                }
                offset = 0;
                lastFilters = filters;
                loadInvoices(filters);
            } else {
                offset = 0;
                lastFilters = {};
                loadInvoices();
            }
        });
    }

    if (prevButton) {
        prevButton.addEventListener('click', () => {
            if (offset === 0) {
                return;
            }
            offset = Math.max(0, offset - limit);
            loadInvoices(lastFilters);
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => {
            offset += limit;
            loadInvoices(lastFilters);
        });
    }

    if (addButton && canEdit) {
        addButton.addEventListener('click', () => {
            resetInvoiceOrders('Select a customer to load orders.');
            if (form) {
                form.reset();
            }
            openDrawer();
        });
    }

    if (drawerCloseButtons.length) {
        drawerCloseButtons.forEach((button) => {
            button.addEventListener('click', closeDrawer);
        });
    }

    if (form && canEdit) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const payload = Object.fromEntries(new FormData(form).entries());
            const customerIdValue = payload.customer_id ? String(payload.customer_id).trim() : '';
            if (!customerIdValue) {
                showFormNotice('Customer is required.', 'error');
                return;
            }
            if (selectedOrderIds.size === 0) {
                showFormNotice('Select at least one order to invoice.', 'error');
                return;
            }
            if (!selectedBranchId) {
                showFormNotice('Branch is required for invoicing.', 'error');
                return;
            }
            payload.customer_id = customerIdValue;
            payload.branch_id = String(selectedBranchId);
            payload.order_ids = Array.from(selectedOrderIds, (value) => Number(value));
            if (payload.issued_at) {
                const issuedAt = String(payload.issued_at);
                if (issuedAt.includes('T')) {
                    const parts = issuedAt.split('T');
                    const timePart = parts[1] || '';
                    payload.issued_at = timePart.length === 5 ? `${parts[0]} ${timePart}:00` : `${parts[0]} ${timePart}`;
                } else {
                    payload.issued_at = issuedAt;
                }
            } else {
                delete payload.issued_at;
            }
            if (!payload.invoice_no || String(payload.invoice_no).trim() === '') {
                delete payload.invoice_no;
            }
            if (!payload.note || String(payload.note).trim() === '') {
                delete payload.note;
            }
            try {
                const data = await fetchJson(`${window.APP_BASE}/api/invoices/create.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showNotice(`Invoice ${data.invoice_no || ''} created.`, 'success');
                closeDrawer();
                resetInvoiceOrders('Select a customer to load orders.');
                if (form) {
                    form.reset();
                }
                loadInvoices(lastFilters);
            } catch (error) {
                showFormNotice(`Create failed: ${error.message}`, 'error');
            }
        });
    }

    loadBranches();
    loadCustomers();
    resetInvoiceOrders('Select a customer to load orders.');
    loadInvoices();
}

function initTransactionsPage() {
    const page = document.querySelector('[data-transactions-page]');
    if (!page) {
        return;
    }

    const filterForm = page.querySelector('[data-transactions-filter]');
    const fromInput = page.querySelector('[data-transactions-from]');
    const toInput = page.querySelector('[data-transactions-to]');
    const tableBody = page.querySelector('[data-transactions-table]');
    const statusStack = page.querySelector('[data-transactions-status]');
    const refreshButton = page.querySelector('[data-transactions-refresh]');

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

    const formatAmount = (value) => {
        const num = Number(value ?? 0);
        return Number.isFinite(num) ? num.toFixed(2) : '0.00';
    };

    const setDefaultDates = () => {
        if (!fromInput || !toInput) {
            return;
        }
        if (!fromInput.value) {
            const start = new Date();
            start.setDate(1);
            fromInput.value = start.toISOString().slice(0, 10);
        }
        if (!toInput.value) {
            const end = new Date(fromInput.value || new Date());
            end.setMonth(end.getMonth() + 1, 0);
            toInput.value = end.toISOString().slice(0, 10);
        }
    };

    const renderRows = (rows) => {
        if (!tableBody) {
            return;
        }
        if (!rows || rows.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="9" class="muted">No transactions found.</td></tr>';
            return;
        }
        tableBody.innerHTML = rows
            .map((row) => {
                const dateLabel = row.payment_date || row.created_at || '-';
                const receiptLink = row.id
                    ? `<a class="text-link" target="_blank" rel="noopener" href="${window.APP_BASE}/views/internal/transaction_receipt_print?id=${row.id}">Print</a>`
                    : '-';
                return `<tr>
                    <td>${escapeHtml(row.id)}</td>
                    <td>${escapeHtml(row.customer_name || '-')}</td>
                    <td>${escapeHtml(row.branch_name || '-')}</td>
                    <td>${escapeHtml(row.type || '-')}</td>
                    <td>${escapeHtml(row.payment_method || '-')}</td>
                    <td>${formatAmount(row.amount)}</td>
                    <td>${escapeHtml(dateLabel)}</td>
                    <td>${escapeHtml(row.note || '-')}</td>
                    <td>${receiptLink}</td>
                </tr>`;
            })
            .join('');
    };

    const loadTransactions = async () => {
        if (!fromInput || !toInput) {
            return;
        }
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="9" class="muted">Loading transactions...</td></tr>';
        }
        const params = new URLSearchParams({
            date_from: fromInput.value,
            date_to: toInput.value,
            limit: '200',
        });
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/transactions/list.php?${params.toString()}`);
            renderRows(data.data || []);
        } catch (error) {
            renderRows([]);
            showNotice(`Transactions load failed: ${error.message}`, 'error');
        }
    };

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            loadTransactions();
        });
    }
    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            loadTransactions();
        });
    }

    setDefaultDates();
    loadTransactions();
}

function initReportsPage() {
    const page = document.querySelector('[data-reports-page]');
    if (!page) {
        return;
    }

    const shipmentSelects = page.querySelectorAll('[data-report-shipment-select]');
    const branchSelects = page.querySelectorAll('[data-report-branch-select]');

    const clearDynamicOptions = (select) => {
        if (!select) {
            return;
        }
        select.querySelectorAll('option[data-dynamic]').forEach((option) => option.remove());
    };

    const loadShipments = async () => {
        if (!shipmentSelects.length) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/shipments/list.php?limit=200`);
            const rows = data.data || [];
            shipmentSelects.forEach((select) => {
                clearDynamicOptions(select);
                rows.forEach((shipment) => {
                    const option = document.createElement('option');
                    option.value = shipment.id;
                    option.textContent = shipment.shipment_number || `#${shipment.id}`;
                    option.setAttribute('data-dynamic', 'true');
                    select.appendChild(option);
                });
            });
        } catch (error) {
            console.warn('Shipments load failed', error);
        }
    };

    const loadBranches = async () => {
        if (!branchSelects.length) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/branches/list.php?limit=200`);
            const rows = data.data || [];
            branchSelects.forEach((select) => {
                clearDynamicOptions(select);
                rows.forEach((branch) => {
                    const option = document.createElement('option');
                    option.value = branch.id;
                    option.textContent = branch.name;
                    option.setAttribute('data-dynamic', 'true');
                    select.appendChild(option);
                });
            });
        } catch (error) {
            console.warn('Branches load failed', error);
        }
    };

    loadShipments();
    loadBranches();
}

function initCompanyPage() {
    const page = document.querySelector('[data-company-settings]');
    if (!page) {
        return;
    }

    const form = page.querySelector('[data-company-form]');
    const status = page.querySelector('[data-company-status]');
    const fields = {
        name: page.querySelector('[name="name"]'),
        phone: page.querySelector('[name="phone"]'),
        address: page.querySelector('[name="address"]'),
        email: page.querySelector('[name="email"]'),
        website: page.querySelector('[name="website"]'),
        logo_url: page.querySelector('[name="logo_url"]'),
    };
    const logoPreview = page.querySelector('[data-company-logo-preview]');
    const logoInput = page.querySelector('[data-company-logo-input]');
    const logoUpload = page.querySelector('[data-company-logo-upload]');
    const logoDelete = page.querySelector('[data-company-logo-delete]');

    const showNotice = (message, type = 'error') => {
        if (!status) {
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        status.appendChild(notice);
        setTimeout(() => notice.remove(), 6000);
    };

    const setField = (key, value) => {
        if (fields[key]) {
            fields[key].value = value || '';
        }
    };

    const updateLogo = (url) => {
        const safeUrl = url || '';
        if (fields.logo_url) {
            fields.logo_url.value = safeUrl;
        }
        if (logoPreview) {
            if (safeUrl) {
                logoPreview.hidden = false;
                logoPreview.src = safeUrl;
            } else {
                logoPreview.hidden = true;
                logoPreview.removeAttribute('src');
            }
            const name = fields.name ? fields.name.value.trim() : '';
            logoPreview.alt = name ? `${name} logo` : 'Company logo';
        }
    };

    const loadCompany = async () => {
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/company/get.php`);
            const company = data.data || {};
            Object.keys(fields).forEach((key) => {
                setField(key, company[key] || '');
            });
            updateLogo(company.logo_url || '');
        } catch (error) {
            showNotice(`Company load failed: ${error.message}`, 'error');
        }
    };

    if (form) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const payload = {};
            Object.keys(fields).forEach((key) => {
                payload[key] = fields[key] ? fields[key].value.trim() : '';
            });
            if (!payload.name) {
                showNotice('Company name is required.', 'error');
                return;
            }
            try {
                await fetchJson(`${window.APP_BASE}/api/company/update.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showNotice('Company settings saved.', 'success');
            } catch (error) {
                showNotice(`Save failed: ${error.message}`, 'error');
            }
        });
    }

    if (logoUpload && logoInput) {
        logoUpload.addEventListener('click', async () => {
            const file = logoInput.files && logoInput.files[0];
            if (!file) {
                showNotice('Select a logo file to upload.', 'error');
                return;
            }
            const formData = new FormData();
            formData.append('logo', file);
            logoUpload.disabled = true;
            try {
                const data = await fetchJson(`${window.APP_BASE}/api/company/logo_upload.php`, {
                    method: 'POST',
                    body: formData,
                });
                updateLogo((data.data && data.data.logo_url) || '');
                logoInput.value = '';
                showNotice('Logo uploaded.', 'success');
            } catch (error) {
                showNotice(`Logo upload failed: ${error.message}`, 'error');
            } finally {
                logoUpload.disabled = false;
            }
        });
    }

    if (logoDelete) {
        logoDelete.addEventListener('click', async () => {
            if (!confirm('Remove the current logo?')) {
                return;
            }
            logoDelete.disabled = true;
            try {
                const data = await fetchJson(`${window.APP_BASE}/api/company/logo_delete.php`, {
                    method: 'POST',
                });
                updateLogo((data.data && data.data.logo_url) || '');
                if (logoInput) {
                    logoInput.value = '';
                }
                showNotice('Logo removed.', 'success');
            } catch (error) {
                showNotice(`Logo removal failed: ${error.message}`, 'error');
            } finally {
                logoDelete.disabled = false;
            }
        });
    }

    loadCompany();
}

function initPartnersPage() {
    const page = document.querySelector('[data-partners-page]');
    if (!page) {
        return;
    }

    const filterForm = page.querySelector('[data-partners-filter]');
    const tableBody = page.querySelector('[data-partners-table]');
    const statusStack = page.querySelector('[data-partners-status]');
    const refreshButton = page.querySelector('[data-partners-refresh]');
    const countryFilter = page.querySelector('[data-country-filter]');
    const prevButton = page.querySelector('[data-partners-prev]');
    const nextButton = page.querySelector('[data-partners-next]');
    const pageLabel = page.querySelector('[data-partners-page]');
    const addButton = page.querySelector('[data-partners-add]');
    const drawer = page.querySelector('[data-partners-drawer]');
    const form = page.querySelector('[data-partners-form]');
    const formTitle = page.querySelector('[data-partners-form-title]');
    const submitLabel = page.querySelector('[data-partners-submit-label]');
    const drawerStatus = page.querySelector('[data-partners-form-status]');
    const partnerIdField = page.querySelector('[data-partner-id]');
    const countrySelect = page.querySelector('[data-country-select]');
    const drawerCloseButtons = page.querySelectorAll('[data-partners-drawer-close]');
    const canEdit = page.getAttribute('data-can-edit') === '1';

    const limit = 5;
    let offset = 0;
    let lastFilters = {};
    const partnerMap = new Map();

    const escapeHtml = (value) =>
        String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#39;');

    const formatAmount = (value) => {
        const num = Number(value ?? 0);
        return Number.isFinite(num) ? num.toFixed(2) : '0.00';
    };

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
        if (drawerStatus) {
            drawerStatus.innerHTML = '';
        }
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
            const rows = data.data || [];
            if (countryFilter) {
                clearDynamicOptions(countryFilter);
                rows.forEach((country) => {
                    const option = document.createElement('option');
                    option.value = country.id;
                    option.textContent = country.name;
                    option.setAttribute('data-dynamic', 'true');
                    countryFilter.appendChild(option);
                });
            }
            if (countrySelect) {
                clearDynamicOptions(countrySelect);
                rows.forEach((country) => {
                    const option = document.createElement('option');
                    option.value = country.id;
                    option.textContent = country.name;
                    option.setAttribute('data-dynamic', 'true');
                    countrySelect.appendChild(option);
                });
            }
        } catch (error) {
            showNotice(`Countries load failed: ${error.message}`, 'error');
        }
    };

    const setFormValues = (partner) => {
        if (!form) {
            return;
        }
        if (partnerIdField) {
            partnerIdField.value = partner?.id ? String(partner.id) : '';
        }
        if (formTitle) {
            formTitle.textContent = partner ? 'Edit partner' : 'Add partner';
        }
        if (submitLabel) {
            submitLabel.textContent = partner ? 'Save changes' : 'Add partner';
        }
        form.querySelector('[name="type"]').value = partner?.type || '';
        form.querySelector('[name="name"]').value = partner?.name || '';
        form.querySelector('[name="phone"]').value = partner?.phone || '';
        form.querySelector('[name="address"]').value = partner?.address || '';
        if (countrySelect) {
            countrySelect.value = partner?.country_id ? String(partner.country_id) : '';
        }
    };

    const renderRows = (rows) => {
        if (!tableBody) {
            return;
        }
        partnerMap.clear();
        if (!rows.length) {
            tableBody.innerHTML = '<tr><td colspan="6" class="muted">No partners found.</td></tr>';
            return;
        }
        tableBody.innerHTML = rows
            .map((row) => {
                partnerMap.set(String(row.id), row);
                const typeLabel = row.type === 'shipper' ? 'Shipper' : row.type === 'consignee' ? 'Consignee' : row.type;
                const actions = [];
                actions.push(
                    `<a class="text-link" href="${window.APP_BASE}/views/internal/partner_view?id=${row.id}">Open</a>`
                );
                if (canEdit) {
                    actions.push(
                        `<button class="text-link" type="button" data-partner-edit data-partner-id="${row.id}">Edit</button>`
                    );
                    actions.push(
                        `<button class="text-link" type="button" data-partner-delete data-partner-id="${row.id}">Delete</button>`
                    );
                }
                return `<tr>
                    <td>${escapeHtml(row.name || '-')}</td>
                    <td>${escapeHtml(typeLabel || '-')}</td>
                    <td>${escapeHtml(row.country_name || '-')}</td>
                    <td>${escapeHtml(row.phone || '-')}</td>
                    <td>${formatAmount(row.balance)}</td>
                    <td>${actions.join(' | ')}</td>
                </tr>`;
            })
            .join('');

        tableBody.querySelectorAll('[data-partner-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                const id = button.getAttribute('data-partner-id');
                if (!id || !partnerMap.has(id)) {
                    return;
                }
                setFormValues(partnerMap.get(id));
                openDrawer();
            });
        });

        tableBody.querySelectorAll('[data-partner-delete]').forEach((button) => {
            button.addEventListener('click', async () => {
                const id = button.getAttribute('data-partner-id');
                if (!id) {
                    return;
                }
                try {
                    await fetchJson(`${window.APP_BASE}/api/partners/delete.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id }),
                    });
                    showNotice('Partner removed.', 'success');
                    if (rows.length === 1 && offset > 0) {
                        offset = Math.max(0, offset - limit);
                    }
                    loadPartners(lastFilters);
                } catch (error) {
                    showNotice(`Delete failed: ${error.message}`, 'error');
                }
            });
        });
    };

    const loadPartners = async (filters = {}) => {
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="6" class="muted">Loading partners...</td></tr>';
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
            const data = await fetchJson(`${window.APP_BASE}/api/partners/list.php?${params.toString()}`);
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
            showNotice(`Partners load failed: ${error.message}`, 'error');
        }
    };

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(filterForm);
            offset = 0;
            lastFilters = Object.fromEntries(formData.entries());
            loadPartners(lastFilters);
        });
    }

    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            if (filterForm) {
                const formData = new FormData(filterForm);
                offset = 0;
                lastFilters = Object.fromEntries(formData.entries());
                loadPartners(lastFilters);
            } else {
                offset = 0;
                lastFilters = {};
                loadPartners();
            }
        });
    }

    if (prevButton) {
        prevButton.addEventListener('click', () => {
            if (offset === 0) {
                return;
            }
            offset = Math.max(0, offset - limit);
            loadPartners(lastFilters);
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => {
            offset += limit;
            loadPartners(lastFilters);
        });
    }

    if (addButton) {
        addButton.addEventListener('click', () => {
            setFormValues(null);
            openDrawer();
        });
    }

    if (drawerCloseButtons.length) {
        drawerCloseButtons.forEach((button) => {
            button.addEventListener('click', closeDrawer);
        });
    }

    if (form) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(form);
            const payload = Object.fromEntries(formData.entries());
            const partnerId = payload.partner_id || '';
            if (!payload.type || !payload.name || !payload.country_id) {
                showFormNotice('Type, name, and country are required.', 'error');
                return;
            }
            try {
                if (partnerId) {
                    await fetchJson(`${window.APP_BASE}/api/partners/update.php`, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    showNotice('Partner updated.', 'success');
                } else {
                    await fetchJson(`${window.APP_BASE}/api/partners/create.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    showNotice('Partner added.', 'success');
                }
                closeDrawer();
                loadPartners(lastFilters);
            } catch (error) {
                showFormNotice(`Save failed: ${error.message}`, 'error');
            }
        });
    }

    loadCountries();
    loadPartners();
}
