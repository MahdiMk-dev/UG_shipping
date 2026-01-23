(() => {
    if (!window.APP_BASE) {
        window.APP_BASE = '.';
    }

    if (!window.formatCustomerLabel) {
        window.formatCustomerLabel = (customer) => {
            if (!customer) {
                return '';
            }
            const phoneValue = customer.phone || customer.portal_phone || '';
            const phone = phoneValue ? ` - ${phoneValue}` : '';
            const countryLabel = customer.profile_country_name ? ` | ${customer.profile_country_name}` : '';
            return `${customer.name} (${customer.code})${countryLabel}${phone}`;
        };
    }
    const formatCustomerLabel = window.formatCustomerLabel;

    if (!window.formatQty) {
        window.formatQty = (value) => {
            const num = Number(value ?? 0);
            return Number.isFinite(num) ? num.toFixed(3) : '0.000';
        };
    }

    const showConfirmDialog = (options = {}) =>
        new Promise((resolve) => {
            const title = options.title || 'Confirm';
            const message = options.message || 'Are you sure?';
            const confirmLabel = options.confirmLabel || 'Confirm';
            const cancelLabel = options.cancelLabel || 'Cancel';
            const requireInput = options.requireInput === true;
            const inputPlaceholder = options.inputPlaceholder || '';

            let overlay = document.querySelector('[data-confirm-dialog]');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.className = 'confirm-overlay';
                overlay.setAttribute('data-confirm-dialog', '');
                overlay.innerHTML = `
                    <div class="confirm-backdrop" data-confirm-cancel></div>
                    <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
                        <div class="confirm-header">
                            <h3 id="confirm-title"></h3>
                        </div>
                        <div class="confirm-body">
                            <p data-confirm-message></p>
                            <input type="text" class="confirm-input is-hidden" data-confirm-input>
                        </div>
                        <div class="confirm-actions">
                            <button class="button ghost" type="button" data-confirm-cancel></button>
                            <button class="button primary" type="button" data-confirm-ok></button>
                        </div>
                    </div>`;
                document.body.appendChild(overlay);
            }

            const titleEl = overlay.querySelector('#confirm-title');
            const messageEl = overlay.querySelector('[data-confirm-message]');
            const inputEl = overlay.querySelector('[data-confirm-input]');
            const okButton = overlay.querySelector('[data-confirm-ok]');
            const cancelButtons = overlay.querySelectorAll('[data-confirm-cancel]');
            const cancelButton = overlay.querySelector('[data-confirm-cancel]');

            if (titleEl) {
                titleEl.textContent = title;
            }
            if (messageEl) {
                messageEl.textContent = message;
            }
            if (okButton) {
                okButton.textContent = confirmLabel;
            }
            if (cancelButton) {
                cancelButton.textContent = cancelLabel;
            }
            if (inputEl) {
                inputEl.value = '';
                inputEl.placeholder = inputPlaceholder;
                inputEl.classList.toggle('is-hidden', !requireInput);
            }

            const cleanup = (value) => {
                overlay.classList.remove('is-open');
                overlay.querySelectorAll('[data-confirm-ok],[data-confirm-cancel]').forEach((button) => {
                    button.replaceWith(button.cloneNode(true));
                });
                resolve(value);
            };

            overlay.classList.add('is-open');
            if (requireInput && inputEl) {
                inputEl.focus();
            } else if (okButton) {
                okButton.focus();
            }

            overlay.querySelector('[data-confirm-ok]').addEventListener('click', () => {
                if (requireInput) {
                    const value = inputEl ? inputEl.value.trim() : '';
                    if (!value) {
                        return;
                    }
                    cleanup(value);
                    return;
                }
                cleanup(true);
            });
            cancelButtons.forEach((button) => {
                button.addEventListener('click', () => cleanup(requireInput ? null : false));
            });
        });

    const initGlobalNotices = () => {
        const container = document.querySelector('[data-global-notice]');
        const stack = document.querySelector('[data-global-notice-stack]');
        if (!container || !stack) {
            return;
        }
        const closeButtons = container.querySelectorAll('[data-global-notice-close]');

        const hideIfEmpty = () => {
            if (!stack.children.length) {
                container.classList.remove('is-visible');
            }
        };

        const showContainer = () => {
            container.classList.add('is-visible');
        };

        const moveNotice = (notice) => {
            if (!notice || !(notice instanceof HTMLElement)) {
                return;
            }
            if (!notice.classList.contains('error')) {
                return;
            }
            if (stack.contains(notice)) {
                return;
            }
            stack.appendChild(notice);
            showContainer();
        };

        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (!(node instanceof HTMLElement)) {
                        return;
                    }
                    if (node.classList.contains('notice')) {
                        moveNotice(node);
                        return;
                    }
                    node.querySelectorAll?.('.notice.error').forEach((notice) => moveNotice(notice));
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });

        const stackObserver = new MutationObserver(() => {
            hideIfEmpty();
        });
        stackObserver.observe(stack, { childList: true });

        closeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                stack.querySelectorAll('.notice').forEach((notice) => notice.remove());
                container.classList.remove('is-visible');
            });
        });
    };

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
        initGlobalNotices();
        const logoutButtons = document.querySelectorAll('[data-logout]');
        if (logoutButtons.length === 0) {
            initSidebarToggle();
            initPasswordChange();
            initShipmentsPage();
            initShipmentCreate();
            initShipmentView();
            initShipmentCustomerOrders();
            initShipmentOrdersPage();
            initOrdersPage();
            initOrderCreate();
            initCustomersPage();
            initCustomerBalancesPage();
            initBalancesPage();
            initBranchOverviewPage();
            initBranchesPage();
            initAccountsPage();
            initAccountView();
            initUsersPage();
            initStaffPage();
            initStaffView();
            initCustomerCreate();
            initCustomerEdit();
            initCustomerInfoEdit();
            initCustomerView();
            initAuditPage();
            initReceivingPage();
            initAttachmentsPage();
            initInvoicesPage();
            initTransactionsPage();
            initExpensesPage();
            initReportsPage();
            initSuppliersPage();
            initSupplierView();
            initPartnersPage();
            initPartnerCreate();
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
        initPasswordChange();
        initShipmentsPage();
        initShipmentCreate();
        initShipmentView();
        initShipmentCustomerOrders();
        initShipmentOrdersPage();
        initOrdersPage();
        initOrderCreate();
        initCustomersPage();
        initCustomerBalancesPage();
        initBalancesPage();
        initBranchOverviewPage();
        initBranchesPage();
        initAccountsPage();
        initAccountView();
        initUsersPage();
        initStaffPage();
        initStaffView();
        initCustomerCreate();
        initCustomerEdit();
        initCustomerInfoEdit();
        initCustomerView();
        initAuditPage();
        initReceivingPage();
        initAttachmentsPage();
        initInvoicesPage();
        initTransactionsPage();
        initExpensesPage();
        initReportsPage();
        initSuppliersPage();
        initSupplierView();
        initPartnersPage();
        initPartnerCreate();
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
    initCustomerBalancesPage();
    initBalancesPage();
    initBranchOverviewPage();
    initCustomerCreate();
    initCustomerEdit();
    initCustomerInfoEdit();
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

function initPasswordChange() {
    const drawer = document.querySelector('[data-password-drawer]');
    if (!drawer) {
        return;
    }
    const openButtons = document.querySelectorAll('[data-password-open]');
    if (!openButtons.length) {
        return;
    }
    const form = drawer.querySelector('[data-password-form]');
    const statusStack = drawer.querySelector('[data-password-status]');
    const closeButtons = drawer.querySelectorAll('[data-password-close]');

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

    const openDrawer = () => {
        drawer.classList.add('is-open');
        document.body.classList.add('drawer-open');
        if (statusStack) {
            statusStack.innerHTML = '';
        }
        if (form) {
            form.reset();
            const firstField = form.querySelector('[name="old_password"]');
            if (firstField) {
                firstField.focus();
            }
        }
    };

    const closeDrawer = () => {
        drawer.classList.remove('is-open');
        document.body.classList.remove('drawer-open');
        if (statusStack) {
            statusStack.innerHTML = '';
        }
    };

    openButtons.forEach((button) => button.addEventListener('click', openDrawer));
    closeButtons.forEach((button) => button.addEventListener('click', closeDrawer));

    if (form) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (statusStack) {
                statusStack.innerHTML = '';
            }
            const formData = new FormData(form);
            const oldPassword = (formData.get('old_password') || '').toString();
            const newPassword = (formData.get('new_password') || '').toString();
            const confirmPassword = (formData.get('confirm_password') || '').toString();
            if (!oldPassword || !newPassword) {
                showNotice('Current and new password are required.', 'error');
                return;
            }
            if (newPassword !== confirmPassword) {
                showNotice('New passwords do not match.', 'error');
                return;
            }
            try {
                await fetchJson(`${window.APP_BASE}/api/users/change_password.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ old_password: oldPassword, new_password: newPassword }),
                });
                showNotice('Password updated.', 'success');
                form.reset();
            } catch (error) {
                showNotice(`Password update failed: ${error.message}`, 'error');
            }
        });
    }
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
    const transactionsTable = shell.querySelector('[data-portal-transactions]');
    const greetingEl = shell.querySelector('[data-portal-greeting]');
    const userName = shell.querySelector('[data-portal-user-name]');
    const userCode = shell.querySelector('[data-portal-user-code]');
    const ordersPrev = shell.querySelector('[data-portal-orders-prev]');
    const ordersNext = shell.querySelector('[data-portal-orders-next]');
    const ordersPageLabel = shell.querySelector('[data-portal-orders-page]');
    const invoicesPrev = shell.querySelector('[data-portal-invoices-prev]');
    const invoicesNext = shell.querySelector('[data-portal-invoices-next]');
    const invoicesPageLabel = shell.querySelector('[data-portal-invoices-page]');
    const transactionsPrev = shell.querySelector('[data-portal-transactions-prev]');
    const transactionsNext = shell.querySelector('[data-portal-transactions-next]');
    const transactionsPageLabel = shell.querySelector('[data-portal-transactions-page]');
    const pageSize = 5;
    let ordersPage = 0;
    let invoicesPage = 0;
    let transactionsPage = 0;
    let ordersData = [];
    let invoicesData = [];
    let transactionsData = [];

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

    const formatInvoiceStatus = (value) => {
        if (!value) {
            return '-';
        }
        if (value === 'partially_paid') {
            return 'Partially paid';
        }
        if (value === 'void') {
            return 'Canceled';
        }
        return value.charAt(0).toUpperCase() + value.slice(1);
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
                    <td>${formatInvoiceStatus(inv.status)}</td>
                    <td>${inv.total || '0.00'}</td>
                    <td>${inv.due_total || '0.00'}</td>
                    <td>${inv.issued_at || '-'}</td>
                </tr>`;
                }
            )
            .join('');
        updatePager(invoicesPrev, invoicesNext, invoicesPageLabel, invoicesPage, rows);
    };

    const renderTransactions = (rows) => {
        if (!transactionsTable) {
            return;
        }
        if (!rows || rows.length === 0) {
            transactionsTable.innerHTML = '<tr><td colspan="5" class="muted">No transactions found.</td></tr>';
            updatePager(transactionsPrev, transactionsNext, transactionsPageLabel, transactionsPage, rows || []);
            return;
        }
        const typeLabelMap = {
            payment: 'Payment',
            deposit: 'Deposit',
            refund: 'Refund',
            adjustment: 'Adjustment',
            admin_settlement: 'Admin settlement',
            charge: 'Charge',
            discount: 'Discount',
        };
        const pageRows = paginateRows(rows, transactionsPage);
        transactionsTable.innerHTML = pageRows
            .map((tx) => {
                const profileLabel = tx.customer_name
                    ? tx.customer_code
                        ? `${tx.customer_name} (${tx.customer_code})`
                        : tx.customer_name
                    : '-';
                const typeLabel = typeLabelMap[tx.type] || tx.type || '-';
                const dateLabel = tx.payment_date || tx.created_at || '-';
                return `<tr>
                    <td>${escapeHtml(profileLabel)}</td>
                    <td>${escapeHtml(typeLabel)}</td>
                    <td>${escapeHtml(tx.account_label || tx.payment_method || '-')}</td>
                    <td>${tx.amount || '0.00'}</td>
                    <td>${escapeHtml(dateLabel)}</td>
                </tr>`;
            })
            .join('');
        updatePager(transactionsPrev, transactionsNext, transactionsPageLabel, transactionsPage, rows);
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
            transactionsData = data.transactions || [];
            ordersPage = 0;
            invoicesPage = 0;
            transactionsPage = 0;
            renderOrders(ordersData);
            renderInvoices(invoicesData);
            renderTransactions(transactionsData);
        } catch (error) {
            showNotice(`Portal load failed: ${error.message}`, 'error');
            if (ordersTable) {
                ordersTable.innerHTML = '<tr><td colspan="6" class="muted">Unable to load orders.</td></tr>';
            }
            if (invoicesTable) {
                invoicesTable.innerHTML = '<tr><td colspan="6" class="muted">Unable to load invoices.</td></tr>';
            }
            if (transactionsTable) {
                transactionsTable.innerHTML = '<tr><td colspan="5" class="muted">Unable to load transactions.</td></tr>';
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
    const { role, branchId } = getUserContext();
    const canEditProfile = role === 'Admin';
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
            tableBody.innerHTML =
                `<tr><td colspan="${columnCount}" class="loading-cell">` +
                '<div class="loading-inline">' +
                '<span class="spinner" aria-hidden="true"></span>' +
                '<span class="loading-text">Shipments are loading, please wait...</span>' +
                '</div></td></tr>';
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

    const collapsiblePanels = page.querySelectorAll('[data-collapsible-panel]');
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
    const SupplierSelects = page.querySelectorAll('[data-supplier-select]');
    const goodsSelects = page.querySelectorAll('[data-goods-select]');
    const details = page.querySelectorAll('[data-detail]');
    const stats = page.querySelectorAll('[data-customer-stat]');
    const collectionsTable = page.querySelector('[data-collections-table]');
    const ordersTable = page.querySelector('[data-orders-table]');
    const attachmentsTable = page.querySelector('[data-attachments-table]');
    const ordersSearchForm = page.querySelector('[data-orders-search-form]');
    const ordersSearchInput = page.querySelector('[data-orders-search]');
    const ordersClearButton = page.querySelector('[data-orders-clear]');
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
    const shipmentTabs = page.querySelector('[data-shipment-tabs]');
    const shipmentTabButtons = shipmentTabs ? Array.from(shipmentTabs.querySelectorAll('[data-shipment-tab]')) : [];
    const shipmentTabPanels = shipmentTabs ? Array.from(shipmentTabs.querySelectorAll('[data-shipment-tab-panel]')) : [];
    const { role, branchId } = getUserContext();
    const canEditRole = ['Admin', 'Owner', 'Main Branch', 'Warehouse'].includes(role || '');
    const canDistributeRole = ['Admin', 'Owner', 'Main Branch'].includes(role || '');
    let canEdit = canEditRole;
    const canSeeIncome = page.getAttribute('data-show-income') !== '0';
    const pageSize = 5;
    let collectionsPage = 0;
    let ordersPage = 0;
    let attachmentsPage = 0;
    let shipmentMediaPage = 0;
    let ordersFilter = '';
    let collectionsData = [];
    let ordersData = [];
    let attachmentsData = [];
    let shipmentMediaData = [];
    const shipmentExpensesLimit = 5;
    let shipmentExpensesOffset = 0;
    let shipmentExpensesData = [];
    const shipmentExpenseMap = new Map();
    let warehouseLockNotified = false;
    let pendingGoodsValue = '';
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
    const formatQty = (value) => {
        const num = Number(value ?? 0);
        return Number.isFinite(num) ? num.toFixed(3) : '0.000';
    };
    const normalizeWhatsAppPhone = (value) => {
        const digits = String(value || '').replace(/\D/g, '');
        if (digits.length === 8) {
            return `961${digits}`;
        }
        return digits.length >= 6 ? digits : '';
    };
    const whatsappStatuses = new Set(['distributed', 'partially_distributed']);
    const buildWhatsAppMessage = (customerName, shipment, orders) => {
        const shipmentLabel = shipment?.shipment_number
            ? `Shipment ${shipment.shipment_number}`
            : shipment?.id
                ? `Shipment #${shipment.id}`
                : 'Shipment';
        const filteredOrders = (orders || []).filter(
            (order) => !['with_delivery', 'picked_up'].includes(order.fulfillment_status || '')
        );
        const lines = [
            `UG Shipping - ${shipmentLabel}`,
            `Customer: ${customerName || 'Customer'}`,
            'Orders received:',
        ];
        let total = 0;
        filteredOrders.forEach((order, index) => {
            const tracking = order.tracking_number || `Order #${order.id}`;
            const qtyValue = Number(order.qty ?? 0);
            const qtyLabel = Number.isFinite(qtyValue) && qtyValue > 0
                ? `${qtyValue} ${order.unit_type || ''}`.trim()
                : '';
            let line = `${index + 1}) ${tracking}`;
            if (qtyLabel) {
                line += ` - ${qtyLabel}`;
            }
            if (canSeeIncome && order.total_price !== undefined && order.total_price !== null) {
                const priceValue = Number(order.total_price);
                if (Number.isFinite(priceValue)) {
                    total += priceValue;
                    line += ` - ${formatAmount(priceValue)}`;
                }
            }
            lines.push(line);
        });
        lines.push(`Count: ${filteredOrders.length}`);
        if (canSeeIncome) {
            lines.push(`Total: ${formatAmount(total)}`);
        }
        return lines.join('\n');
    };
    const whatsappIcon = `
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.86 19.86 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.86 19.86 0 0 1 2.08 4.18 2 2 0 0 1 4.06 2h3a2 2 0 0 1 2 1.72c.12.81.34 1.6.65 2.35a2 2 0 0 1-.45 2.11L8.09 9.09a16 16 0 0 0 6 6l1.41-1.41a2 2 0 0 1 2.11-.45c.75.31 1.54.53 2.35.65A2 2 0 0 1 22 16.92z"></path>
        </svg>`;

    const initCollapsiblePanels = () => {
        if (!collapsiblePanels.length) {
            return;
        }
        collapsiblePanels.forEach((panel, index) => {
            const toggle = panel.querySelector('[data-panel-toggle]');
            const body = panel.querySelector('.panel-body');
            if (!toggle || !body) {
                return;
            }
            const bodyId = body.id || `panel-body-${index}`;
            body.id = bodyId;
            toggle.setAttribute('aria-controls', bodyId);

            const setCollapsed = (collapsed) => {
                panel.setAttribute('data-collapsed', collapsed ? '1' : '0');
                toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            };

            if (!panel.hasAttribute('data-collapsed')) {
                setCollapsed(false);
            } else {
                setCollapsed(panel.getAttribute('data-collapsed') === '1');
            }

            toggle.addEventListener('click', () => {
                const isCollapsed = panel.getAttribute('data-collapsed') === '1';
                setCollapsed(!isCollapsed);
            });
        });
    };

    const setActiveShipmentTab = (tabId) => {
        if (!tabId) {
            return;
        }
        shipmentTabButtons.forEach((button) => {
            const isActive = button.getAttribute('data-shipment-tab') === tabId;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        shipmentTabPanels.forEach((panel) => {
            const isActive = panel.getAttribute('data-shipment-tab-panel') === tabId;
            panel.classList.toggle('is-active', isActive);
        });
    };

    const initShipmentTabs = () => {
        if (!shipmentTabs || !shipmentTabButtons.length || !shipmentTabPanels.length) {
            return;
        }
        const activeButton = shipmentTabButtons.find((button) => button.classList.contains('is-active'));
        const initialTab = activeButton ? activeButton.getAttribute('data-shipment-tab') : shipmentTabButtons[0].getAttribute('data-shipment-tab');
        setActiveShipmentTab(initialTab);
        shipmentTabButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const tabId = button.getAttribute('data-shipment-tab');
                setActiveShipmentTab(tabId);
            });
        });
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
    const pendingSupplierIds = { shipper: null, consignee: null };

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
    const getFilteredOrders = () => {
        const query = ordersFilter.trim().toLowerCase();
        if (!query) {
            return ordersData;
        }
        return ordersData.filter((row) => {
            const name = String(row.customer_name || '').toLowerCase();
            const code = String(row.customer_code || '').toLowerCase();
            const tracking = String(row.tracking_numbers || '').toLowerCase();
            return name.includes(query) || code.includes(query) || tracking.includes(query);
        });
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
        const columnCount = canSeeIncome ? 6 : 5;
        const filteredOrders = getFilteredOrders();
        if (!filteredOrders.length) {
            ordersTable.innerHTML = `<tr><td colspan="${columnCount}" class="muted">No orders found.</td></tr>`;
            updatePager(ordersPrev, ordersNext, ordersPageLabel, ordersPage, filteredOrders);
            return;
        }
        const canMessage = whatsappStatuses.has(shipment?.status || '');
        const rows = paginateRows(filteredOrders, ordersPage);
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
                const totalCell = canSeeIncome ? `<td>${formatAmount(row.total_price)}</td>` : '';
                const phoneValue = normalizeWhatsAppPhone(row.customer_phone || '');
                let whatsappCell = '<td class="muted">-</td>';
                if (canMessage) {
                    if (phoneValue && row.customer_id) {
                        const safeName = escapeHtml(row.customer_name || '');
                        whatsappCell = `<td>
                            <button class="icon-button small whatsapp" type="button"
                                data-whatsapp-customer
                                data-customer-id="${row.customer_id}"
                                data-customer-name="${safeName}"
                                data-customer-phone="${phoneValue}"
                                title="Send WhatsApp message"
                                aria-label="Send WhatsApp message">
                                ${whatsappIcon}
                            </button>
                        </td>`;
                    } else {
                        whatsappCell = '<td class="muted">No phone</td>';
                    }
                }
                return `<tr>
                    <td>${row.customer_name || '-'}</td>
                    <td>${row.order_count || 0}</td>
                    <td>${qtyLabel}</td>
                    ${totalCell}
                    ${whatsappCell}
                    <td><a class="text-link" href="${link}">View orders</a></td>
                </tr>`;
            })
            .join('');
        updatePager(ordersPrev, ordersNext, ordersPageLabel, ordersPage, filteredOrders);
    };

    if (ordersTable) {
        ordersTable.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-whatsapp-customer]');
            if (!button) {
                return;
            }
            const customerId = parseInt(button.getAttribute('data-customer-id') || '', 10);
            const customerName = button.getAttribute('data-customer-name') || 'Customer';
            const phone = button.getAttribute('data-customer-phone') || '';
            if (!phone) {
                showNotice('Customer phone is missing for WhatsApp.', 'error');
                return;
            }
            if (!currentShipmentId || !customerId) {
                showNotice('Missing shipment or customer data.', 'error');
                return;
            }
            const params = new URLSearchParams({
                shipment_id: String(currentShipmentId),
                customer_id: String(customerId),
                fulfillment_status: 'received_subbranch',
                limit: '200',
            });
            try {
                const data = await fetchJson(`${window.APP_BASE}/api/orders/list.php?${params.toString()}`);
                const orders = data.data || [];
                if (!orders.length) {
                    showNotice('No received orders found for this customer.', 'error');
                    return;
                }
                const message = buildWhatsAppMessage(customerName, currentShipment, orders);
                const url = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
                window.open(url, 'whatsapp', 'noopener');
            } catch (error) {
                showNotice(`WhatsApp message failed: ${error.message}`, 'error');
            }
        });
    }

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

    const applySupplierSelection = () => {
        if (!SupplierSelects.length) {
            return;
        }
        SupplierSelects.forEach((select) => {
            const type = select.getAttribute('data-supplier-type');
            const pendingValue = type ? pendingSupplierIds[type] : null;
            if (pendingValue !== null && pendingValue !== undefined && String(pendingValue) !== '') {
                select.value = String(pendingValue);
            } else {
                select.value = '';
            }
        });
    };

    const loadSuppliers = async () => {
        if (!SupplierSelects.length) {
            return;
        }
        const cache = new Map();
        const types = new Set();
        SupplierSelects.forEach((select) => {
            const type = select.getAttribute('data-supplier-type');
            if (type) {
                types.add(type);
            }
        });
        for (const type of types) {
            try {
                const params = new URLSearchParams({ type, limit: '200' });
                const data = await fetchJson(`${window.APP_BASE}/api/suppliers/list.php?${params.toString()}`);
                cache.set(type, data.data || []);
            } catch (error) {
                showNotice(`Suppliers load failed: ${error.message}`, 'error');
                cache.set(type, []);
            }
        }
        SupplierSelects.forEach((select) => {
            const type = select.getAttribute('data-supplier-type');
            select.querySelectorAll('option[data-dynamic]').forEach((option) => option.remove());
            const rows = cache.get(type) || [];
            rows.forEach((Supplier) => {
                const option = document.createElement('option');
                option.value = Supplier.id;
                option.textContent = Supplier.name;
                option.setAttribute('data-dynamic', 'true');
                select.appendChild(option);
            });
        });
        applySupplierSelection();
    };

    const applyGoodsSelection = () => {
        if (!goodsSelects.length) {
            return;
        }
        goodsSelects.forEach((select) => {
            const pendingValue = pendingGoodsValue || '';
            if (pendingValue && !Array.from(select.options).some((option) => option.value === pendingValue)) {
                const option = document.createElement('option');
                option.value = pendingValue;
                option.textContent = pendingValue;
                option.setAttribute('data-dynamic', 'true');
                select.appendChild(option);
            }
            select.value = pendingValue;
        });
    };

    const loadGoodsTypes = async () => {
        if (!goodsSelects.length) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/goods_types/list.php?limit=300`);
            goodsSelects.forEach((select) => {
                select.querySelectorAll('option[data-dynamic]').forEach((option) => option.remove());
                (data.data || []).forEach((goodsType) => {
                    const option = document.createElement('option');
                    option.value = goodsType.name;
                    option.textContent = goodsType.name;
                    option.setAttribute('data-dynamic', 'true');
                    select.appendChild(option);
                });
            });
            applyGoodsSelection();
        } catch (error) {
            showEditNotice(`Goods types load failed: ${error.message}`, 'error');
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
        const shippingTypeValue = shipment.shipping_type || '';
        const shippingTypeInputs = editForm.querySelectorAll('[name="shipping_type"]');
        if (shippingTypeInputs.length) {
            shippingTypeInputs.forEach((input) => {
                input.checked = input.value === shippingTypeValue;
            });
        } else {
            const shippingTypeField = editForm.querySelector('[name="shipping_type"]');
            if (shippingTypeField) {
                shippingTypeField.value = shippingTypeValue;
            }
        }
        editForm.querySelector('[name="status"]').value = shipment.status || 'active';
        editForm.querySelector('[name="departure_date"]').value = shipment.departure_date || '';
        editForm.querySelector('[name="arrival_date"]').value = shipment.arrival_date || '';
        const defaultRateKgField = editForm.querySelector('[name="default_rate_kg"]');
        if (defaultRateKgField) {
            defaultRateKgField.value = shipment.default_rate_kg ?? '';
        }
        const defaultRateCbmField = editForm.querySelector('[name="default_rate_cbm"]');
        if (defaultRateCbmField) {
            defaultRateCbmField.value = shipment.default_rate_cbm ?? '';
        }
        pendingGoodsValue = shipment.type_of_goods || '';
        applyGoodsSelection();
        editForm.querySelector('[name="note"]').value = shipment.note || '';
        pendingOriginId = shipment.origin_country_id || '';
        pendingSupplierIds.shipper = shipment.shipper_profile_id || '';
        pendingSupplierIds.consignee = shipment.consignee_profile_id || '';
        originSelects.forEach((select) => {
            if (pendingOriginId) {
                select.value = String(pendingOriginId);
            }
        });
        applySupplierSelection();
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
                showEditNotice('Expected arrival date must be greater than expected departure date.', 'error');
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
            if (ordersSearchInput) {
                ordersFilter = ordersSearchInput.value.trim();
            }
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
            if (currentShipment) {
                const originalNumber = String(currentShipment.shipment_number || '').trim();
                const submittedNumber = String(payload.shipment_number || '').trim();
                if (submittedNumber === originalNumber) {
                    delete payload.shipment_number;
                }
            }
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
            if (getFilteredOrders().length <= (ordersPage + 1) * pageSize) {
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

    const applyOrdersFilter = () => {
        ordersFilter = ordersSearchInput ? ordersSearchInput.value.trim() : '';
        ordersPage = 0;
        if (currentShipment) {
            renderOrders(currentShipment);
        }
    };

    if (ordersSearchForm) {
        ordersSearchForm.addEventListener('submit', (event) => {
            event.preventDefault();
            applyOrdersFilter();
        });
    }

    if (ordersSearchInput) {
        ordersSearchInput.addEventListener('input', applyOrdersFilter);
    }

    if (ordersClearButton) {
        ordersClearButton.addEventListener('click', () => {
            if (ordersSearchInput) {
                ordersSearchInput.value = '';
            }
            applyOrdersFilter();
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
                if (updatedCount > 0 && Array.isArray(data.branches)) {
                    data.branches.forEach((branch) => {
                        if (!branch?.id) {
                            return;
                        }
                        const params = new URLSearchParams({
                            shipment_id: String(currentShipmentId),
                            sub_branch_id: String(branch.id),
                        });
                        window.open(
                            `${window.APP_BASE}/views/internal/shipment_distribution_print.php?${params.toString()}`,
                            '_blank',
                            'noopener'
                        );
                    });
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

    initCollapsiblePanels();
    initShipmentTabs();
    loadCountries();
    loadGoodsTypes();
    loadSuppliers();
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

    const reportedTable = page.querySelector('[data-receiving-reported-table]');
    const reportedPrev = page.querySelector('[data-receiving-reported-prev]');
    const reportedNext = page.querySelector('[data-receiving-reported-next]');
    const reportedPageLabel = page.querySelector('[data-receiving-reported-page]');
    const reportedRefresh = page.querySelector('[data-receiving-reported-refresh]');

    const { role, branchId } = getUserContext();
    const isMainBranch = role === 'Main Branch';
    const canManage = ['Admin', 'Owner', 'Main Branch'].includes(role || '');
    const branchIdValue = branchId ? parseInt(branchId, 10) : null;
    const limit = 6;
    const ordersLimit = 200;
    const receivingStatus = isMainBranch ? 'in_shipment' : 'pending_receipt';
    let shipmentsPage = 0;
    let unmatchedPage = 0;
    let reportedPage = 0;
    let shipmentsData = [];
    let unmatchedData = [];
    let reportedData = [];
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

    const showWeightReportDialog = (options = {}) =>
        new Promise((resolve) => {
            const tracking = options.trackingNumber || '';
            const systemWeight = Number(options.systemWeight ?? 0);
            const weightUnit = options.weightUnit || 'kg';

            let overlay = document.querySelector('[data-weight-dialog]');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.className = 'confirm-overlay';
                overlay.setAttribute('data-weight-dialog', '');
                overlay.innerHTML = `
                    <div class="confirm-backdrop" data-weight-close></div>
                    <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="weight-title">
                        <div class="confirm-header">
                            <h3 id="weight-title">Confirm received weight</h3>
                        </div>
                        <div class="confirm-body">
                            <p data-weight-message></p>
                            <label class="confirm-field">
                                <span>Reported weight</span>
                                <input type="number" step="0.001" min="0" data-weight-input placeholder="Enter actual weight">
                            </label>
                            <p class="muted" data-weight-hint>Confirm the system weight or report a different value.</p>
                        </div>
                        <div class="confirm-actions">
                            <button class="button ghost" type="button" data-weight-confirm>Confirm weight</button>
                            <button class="button primary" type="button" data-weight-report>Report difference</button>
                        </div>
                    </div>`;
                document.body.appendChild(overlay);
            }

            const messageEl = overlay.querySelector('[data-weight-message]');
            const inputEl = overlay.querySelector('[data-weight-input]');
            const confirmButton = overlay.querySelector('[data-weight-confirm]');
            const reportButton = overlay.querySelector('[data-weight-report]');
            const closeTargets = overlay.querySelectorAll('[data-weight-close]');

            if (messageEl) {
                const label = systemWeight ? formatQty(systemWeight) : '--';
                messageEl.textContent = `Tracking ${tracking} system weight: ${label} ${weightUnit}.`;
            }
            if (inputEl) {
                inputEl.value = '';
            }

            const cleanup = (result) => {
                overlay.classList.remove('is-open');
                overlay.querySelectorAll('[data-weight-confirm],[data-weight-report],[data-weight-close]').forEach((button) => {
                    button.replaceWith(button.cloneNode(true));
                });
                resolve(result);
            };

            const handleReport = () => {
                const rawValue = inputEl ? inputEl.value.trim() : '';
                const value = rawValue !== '' ? Number(rawValue) : NaN;
                if (!Number.isFinite(value) || value <= 0) {
                    if (inputEl) {
                        inputEl.focus();
                    }
                    return;
                }
                cleanup({ action: 'report', reportedWeight: value });
            };

            if (confirmButton) {
                confirmButton.addEventListener('click', () => cleanup({ action: 'confirm' }));
            }
            if (reportButton) {
                reportButton.addEventListener('click', handleReport);
            }
            closeTargets.forEach((button) => {
                button.addEventListener('click', () => cleanup({ action: 'confirm' }));
            });

            overlay.classList.add('is-open');
            if (inputEl) {
                inputEl.focus();
            }
        });

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
        const colspan = canManage ? 7 : 6;
        if (!unmatchedData.length) {
            unmatchedTable.innerHTML = `<tr><td colspan="${colspan}" class="muted">No unmatched scans.</td></tr>`;
            updatePager(unmatchedPrev, unmatchedNext, unmatchedPageLabel, unmatchedPage, unmatchedData);
            return;
        }
        const matchLabel = (row) => {
            if (row.match_type === 'wrong_branch') {
                const branchLabel = row.expected_branch_name || (row.expected_branch_id ? `Branch #${row.expected_branch_id}` : '');
                return branchLabel ? `Other branch: ${branchLabel}` : 'Other branch';
            }
            if (row.match_type === 'other_shipment') {
                const shipmentLabel =
                    row.other_shipment_number || (row.other_shipment_id ? `Shipment #${row.other_shipment_id}` : '');
                return shipmentLabel ? `Other shipment: ${shipmentLabel}` : 'Other shipment';
            }
            if (row.match_type === 'status_mismatch') {
                return row.order_status ? `Status: ${row.order_status}` : 'Status mismatch';
            }
            return 'No match';
        };
        unmatchedTable.innerHTML = unmatchedData
            .map((row) => {
                const shipmentLabel = row.shipment_number || row.shipment_id || '-';
                const branchLabel = row.branch_name || row.branch_id || '-';
                const lockedStatuses = new Set(['received_subbranch', 'with_delivery', 'picked_up']);
                const canReturn = row.order_id && !lockedStatuses.has(row.order_status || '');
                const actionCell = canManage
                    ? `<td>${
                          canReturn
                              ? `<button class="button ghost small" type="button" data-return-scan="${row.id}">Return</button>`
                              : '-'
                      }</td>`
                    : '';
                return `<tr>
                    <td>${row.tracking_number || '-'}</td>
                    <td>${shipmentLabel}</td>
                    <td>${branchLabel}</td>
                    <td>${matchLabel(row)}</td>
                    <td>${row.scanned_at || '-'}</td>
                    <td>${row.note || '-'}</td>
                    ${actionCell}
                </tr>`;
            })
            .join('');
        updatePager(unmatchedPrev, unmatchedNext, unmatchedPageLabel, unmatchedPage, unmatchedData);
    };

    const renderReported = () => {
        if (!reportedTable) {
            return;
        }
        const colspan = canManage ? 7 : 6;
        if (!reportedData.length) {
            reportedTable.innerHTML = `<tr><td colspan="${colspan}" class="muted">No reported orders.</td></tr>`;
            updatePager(reportedPrev, reportedNext, reportedPageLabel, reportedPage, reportedData);
            return;
        }
        reportedTable.innerHTML = reportedData
            .map((row) => {
                const shipmentLabel = row.shipment_number || row.shipment_id || '-';
                const branchLabel = row.branch_name || row.branch_id || '-';
                const systemWeight = Number(row.system_weight ?? 0);
                const systemUnit = row.weight_unit || 'kg';
                const systemLabel = `${formatQty(systemWeight)} ${systemUnit}`;
                const reportedLabel = `${formatQty(row.reported_weight ?? 0)} ${systemUnit}`;
                const reportedByCell = canManage ? `<td>${row.reported_by_name || '-'}</td>` : '';
                return `<tr>
                    <td>${row.tracking_number || '-'}</td>
                    <td>${shipmentLabel}</td>
                    <td>${branchLabel}</td>
                    <td>${systemLabel}</td>
                    <td>${reportedLabel}</td>
                    <td>${row.reported_at || '-'}</td>
                    ${reportedByCell}
                </tr>`;
            })
            .join('');
        updatePager(reportedPrev, reportedNext, reportedPageLabel, reportedPage, reportedData);
    };

    const loadShipmentOrders = async (shipmentId) => {
        if (!shipmentsTable) {
            return;
        }
        const tbody = shipmentsTable.querySelector(`[data-receiving-orders="${shipmentId}"]`);
        if (!tbody) {
            return;
        }
        tbody.innerHTML = '<tr><td colspan="5" class="loading-cell"><div class="loading-inline"><span class="spinner" aria-hidden="true"></span><span class="loading-text">Orders are loading, please wait...</span></div></td></tr>';
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
            const colspan = canManage ? 7 : 6;
            unmatchedTable.innerHTML = `<tr><td colspan="${colspan}" class="muted">Loading unmatched scans...</td></tr>`;
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

    const loadReported = async () => {
        if (reportedTable) {
            const colspan = canManage ? 7 : 6;
            reportedTable.innerHTML = `<tr><td colspan="${colspan}" class="muted">Loading reported orders...</td></tr>`;
        }
        const params = new URLSearchParams();
        params.append('limit', String(limit));
        params.append('offset', String(reportedPage * limit));
        if (canManage && branchSelect && branchSelect.value) {
            params.append('branch_id', branchSelect.value);
        } else if (branchIdValue) {
            params.append('branch_id', String(branchIdValue));
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/receiving/reported.php?${params.toString()}`);
            reportedData = data.data || [];
            renderReported();
        } catch (error) {
            reportedData = [];
            renderReported();
            showNotice(`Reported orders load failed: ${error.message}`, 'error');
        }
    };

    const returnToMainBranch = async (scanId) => {
        if (!scanId) {
            return;
        }
        try {
            await fetchJson(`${window.APP_BASE}/api/receiving/return.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ scan_id: scanId }),
            });
            showNotice('Order returned to main branch.', 'success');
            loadShipments();
            loadUnmatched();
        } catch (error) {
            showNotice(`Return failed: ${error.message}`, 'error');
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

    const focusShipmentTracking = (value) => {
        if (!value) {
            return;
        }
        const input = page.querySelector(
            `[data-receiving-scan-form][data-shipment-id="${value}"] input[name="tracking_number"]`
        );
        if (input && typeof input.focus === 'function') {
            input.focus();
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
                if (!isMainBranch && !canManage && data.scan_id) {
                    const result = await showWeightReportDialog({
                        trackingNumber: data.tracking_number || payload.tracking_number || '',
                        systemWeight: data.system_weight,
                        weightUnit: data.weight_unit || 'kg',
                    });
                    if (result?.action === 'report') {
                        try {
                            await fetchJson(`${window.APP_BASE}/api/receiving/report.php`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    scan_id: data.scan_id,
                                    reported_weight: result.reportedWeight,
                                }),
                            });
                            showScanNotice(statusEl, 'Reported weight saved.', 'success');
                        } catch (error) {
                            showScanNotice(statusEl, `Report failed: ${error.message}`, 'error');
                        }
                    }
                }
            } else {
                showScanNotice(statusEl, 'Scan recorded but no order matched.', 'error');
            }
            await loadShipments();
            if (shipmentId) {
                await loadShipmentOrders(shipmentId);
            }
            await loadUnmatched();
            await loadReported();
        } catch (error) {
            showScanNotice(statusEl, `Scan failed: ${error.message}`, 'error');
        } finally {
            if (shipmentId) {
                if (focusInput && typeof focusInput.focus === 'function' && document.contains(focusInput)) {
                    focusInput.focus();
                }
                window.requestAnimationFrame(() => focusShipmentTracking(shipmentId));
            } else if (focusInput && typeof focusInput.focus === 'function') {
                focusInput.focus();
            }
        }
    }

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            shipmentsPage = 0;
            unmatchedPage = 0;
            reportedPage = 0;
            openShipments.clear();
            loadShipments();
            loadUnmatched();
            loadReported();
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

    if (unmatchedTable && canManage) {
        unmatchedTable.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            const button = target.closest('[data-return-scan]');
            if (!button) {
                return;
            }
            const scanId = button.getAttribute('data-return-scan') || '';
            if (!scanId) {
                return;
            }
            returnToMainBranch(scanId);
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
    if (reportedRefresh) {
        reportedRefresh.addEventListener('click', () => {
            reportedPage = 0;
            loadReported();
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

    if (reportedPrev) {
        reportedPrev.addEventListener('click', () => {
            if (reportedPage === 0) {
                return;
            }
            reportedPage -= 1;
            loadReported();
        });
    }
    if (reportedNext) {
        reportedNext.addEventListener('click', () => {
            if (reportedData.length <= (reportedPage + 1) * limit) {
                return;
            }
            reportedPage += 1;
            loadReported();
        });
    }

    loadBranches();
    loadShipments();
    loadUnmatched();
    loadReported();
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
    const mediaPanel = page.querySelector('[data-order-edit-media-panel]');
    const mediaTitle = page.querySelector('[data-order-edit-media-title]');
    const mediaForm = page.querySelector('[data-order-edit-media-form]');
    const mediaIdField = page.querySelector('[data-order-edit-media-id]');
    const mediaTable = page.querySelector('[data-order-edit-media-table]');
    const mediaStatus = page.querySelector('[data-order-edit-media-status]');
    const mediaPrev = page.querySelector('[data-order-edit-media-prev]');
    const mediaNext = page.querySelector('[data-order-edit-media-next]');
    const mediaPageLabel = page.querySelector('[data-order-edit-media-page]');
    const packageTypeInputs = page.querySelectorAll('[data-order-package-type]');
    const weightTypeSelect = page.querySelector('[data-order-weight-type]');
    const weightTypeInputs = page.querySelectorAll('[data-order-weight-type-input]');
    const weightActualField = page.querySelector('[data-order-weight-actual]');
    const weightDimensionFields = page.querySelectorAll('[data-order-weight-dimension]');
    const adjustmentsList = page.querySelector('[data-adjustments-list]');
    const adjustmentAddButton = page.querySelector('[data-adjustment-add]');
    const shipmentId = page.getAttribute('data-shipment-id');
    const customerId = page.getAttribute('data-customer-id');
    const canEditAttr = page.getAttribute('data-can-edit') === '1';
    const canPrintLabel = page.getAttribute('data-can-print-label') === '1';
    const canSeeIncome = page.getAttribute('data-show-income') !== '0';
    const pageSize = 5;
    const mediaPageSize = 5;
    let ordersPage = 0;
    let ordersData = [];
    let currentOrder = null;
    let currentMediaOrderId = null;
    let mediaPage = 0;
    let mediaData = [];
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

    const openLabelPrint = (trackingNumber = '', orderId = '') => {
        const params = new URLSearchParams();
        if (trackingNumber) {
            params.append('tracking_number', trackingNumber);
        } else if (orderId) {
            params.append('order_id', orderId);
        } else {
            showNotice('Tracking number is required to print a label.', 'error');
            return;
        }
        window.open(`${window.APP_BASE}/views/internal/order_label_print.php?${params.toString()}`, '_blank', 'noopener');
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

    const showMediaNotice = (message, type = 'error') => {
        if (!mediaStatus) {
            showNotice(message, type);
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        mediaStatus.appendChild(notice);
        setTimeout(() => notice.remove(), 7000);
    };

    const updateMediaPager = () => {
        if (mediaPrev) {
            mediaPrev.disabled = mediaPage === 0;
        }
        if (mediaNext) {
            mediaNext.disabled = mediaData.length <= (mediaPage + 1) * mediaPageSize;
        }
        if (mediaPageLabel) {
            mediaPageLabel.textContent = `Page ${mediaPage + 1}`;
        }
    };

    const renderMediaTable = () => {
        if (!mediaTable) {
            return;
        }
        if (!mediaData.length) {
            mediaTable.innerHTML = '<tr><td colspan="5" class="muted">No attachments yet.</td></tr>';
            updateMediaPager();
            return;
        }
        const rows = mediaData.slice(mediaPage * mediaPageSize, mediaPage * mediaPageSize + mediaPageSize);
        mediaTable.innerHTML = rows
            .map((att) => {
                const downloadUrl =
                    att.download_url || `${window.APP_BASE}/api/attachments/download.php?id=${att.id}`;
                return `<tr>
                    <td>${att.title || att.original_name || '-'}</td>
                    <td>${att.mime_type || '-'}</td>
                    <td>${att.created_at || '-'}</td>
                    <td><a class="text-link" href="${downloadUrl}">Download</a></td>
                    <td><button class="button ghost small" type="button" data-attachment-delete data-attachment-id="${att.id}">Delete</button></td>
                </tr>`;
            })
            .join('');
        updateMediaPager();
        if (currentMediaOrderId) {
            bindMediaDeletes(currentMediaOrderId);
        }
    };

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
                    await loadMedia(orderId);
                } catch (error) {
                    showMediaNotice(`Delete failed: ${error.message}`, 'error');
                }
            });
        });
    };

    const loadMedia = async (orderId) => {
        if (!mediaTable) {
            return;
        }
        mediaTable.innerHTML = '<tr><td colspan="5" class="muted">Loading attachments...</td></tr>';
        try {
            const data = await fetchJson(
                `${window.APP_BASE}/api/attachments/list.php?entity_type=order&entity_id=${encodeURIComponent(
                    String(orderId)
                )}`
            );
            mediaData = data.data || [];
            mediaPage = 0;
            renderMediaTable();
        } catch (error) {
            mediaData = [];
            renderMediaTable();
            showMediaNotice(`Attachments load failed: ${error.message}`, 'error');
        }
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

    const setWeightTypeValue = (weightType) => {
        if (weightTypeSelect) {
            weightTypeSelect.value = weightType;
        } else if (weightTypeInputs.length) {
            weightTypeInputs.forEach((input) => {
                input.checked = input.value === weightType;
            });
        }
        setWeightFields(weightType);
    };

    const setPackageTypeValue = (packageType) => {
        if (!packageTypeInputs.length) {
            return;
        }
        packageTypeInputs.forEach((input) => {
            input.checked = input.value === packageType;
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
            if (weightTypeSelect || weightTypeInputs.length) {
                setWeightTypeValue(currentOrder.weight_type || 'actual');
            }
            const actualInput = editForm.querySelector('[name="actual_weight"]');
            const wInput = editForm.querySelector('[name="w"]');
            const dInput = editForm.querySelector('[name="d"]');
            const hInput = editForm.querySelector('[name="h"]');
            const rateKgInput = editForm.querySelector('[name="rate_kg"]');
            const rateCbmInput = editForm.querySelector('[name="rate_cbm"]');
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
            if (rateKgInput) {
                rateKgInput.value = currentOrder.rate_kg ?? '';
            }
            if (rateCbmInput) {
                rateCbmInput.value = currentOrder.rate_cbm ?? '';
            }
            if (packageTypeInputs.length) {
                setPackageTypeValue(currentOrder.package_type || 'bag');
            }
            if (adjustmentsList) {
                adjustmentsList.innerHTML = '';
                const adjustments = data.adjustments || [];
                adjustments.forEach((adj) => addAdjustmentRow(adj));
            }
            if (currentOrder?.id) {
                currentMediaOrderId = String(currentOrder.id);
                if (mediaIdField) {
                    mediaIdField.value = currentMediaOrderId;
                }
                if (mediaTitle) {
                    const tracking = currentOrder.tracking_number || currentMediaOrderId;
                    mediaTitle.textContent = `Attachments for order #${tracking}.`;
                }
                if (mediaPanel) {
                    mediaPanel.classList.remove('is-hidden');
                }
                if (mediaTable) {
                    loadMedia(currentOrder.id);
                }
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
            const baseCols = canSeeIncome ? 5 : 4;
            const colspan = baseCols + (canEdit ? 1 : 0);
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
                let actionCell = '';
                if (canEdit || canPrintLabel) {
                    const actions = [];
                    if (canEdit) {
                        actions.push(
                            `<button class="button ghost small" type="button" data-order-edit-open data-order-id="${row.id}">Edit</button>`
                        );
                    }
                    if (canPrintLabel) {
                        actions.push(
                            `<button class="button ghost small" type="button" data-order-print-label data-order-id="${row.id}" data-order-tracking="${row.tracking_number || ''}">Print label</button>`
                        );
                    }
                    actionCell = `<td>${actions.join(' ')}</td>`;
                }
                const totalCell = canSeeIncome ? `<td>${row.total_price || '0.00'}</td>` : '';
                return `<tr>
                    <td>${row.tracking_number || '-'}</td>
                    <td>${row.delivery_type || '-'}</td>
                    <td>${qtyLabel}</td>
                    ${totalCell}
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
                if (canSeeIncome) {
                    setDetail('total_price', formatNumber(totalPrice, 2));
                }
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

    if (tableBody && canPrintLabel) {
        tableBody.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            const button = target.closest('[data-order-print-label]');
            if (!button) {
                return;
            }
            const trackingNumber = button.getAttribute('data-order-tracking') || '';
            const orderIdValue = button.getAttribute('data-order-id') || '';
            openLabelPrint(trackingNumber, orderIdValue);
        });
    }

    if (weightTypeSelect) {
        weightTypeSelect.addEventListener('change', (event) => {
            setWeightFields(event.target.value);
        });
    } else if (weightTypeInputs.length) {
        weightTypeInputs.forEach((input) => {
            input.addEventListener('change', () => {
                if (input.checked) {
                    setWeightFields(input.value);
                }
            });
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
            const weightType =
                editForm.querySelector('[name="weight_type"]:checked')?.value || 'actual';
            const packageType =
                editForm.querySelector('[name="package_type"]:checked')?.value || 'bag';
            const rateKgInput = editForm.querySelector('[name="rate_kg"]');
            const rateCbmInput = editForm.querySelector('[name="rate_cbm"]');
            const rateKgValue = rateKgInput?.value || '';
            const rateCbmValue = rateCbmInput?.value || '';
            const payload = {
                order_id: currentOrder.id,
                package_type: packageType,
                weight_type: weightType,
            };
            if (canSeeIncome && rateKgInput) {
                payload.rate_kg = rateKgValue !== '' ? parseFloat(rateKgValue) : null;
            }
            if (canSeeIncome && rateCbmInput) {
                payload.rate_cbm = rateCbmValue !== '' ? parseFloat(rateCbmValue) : null;
            }
            if (canSeeIncome) {
                payload.adjustments = [];
            }
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

            if (canSeeIncome && adjustmentsList) {
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

    if (mediaPrev) {
        mediaPrev.addEventListener('click', () => {
            if (mediaPage === 0) {
                return;
            }
            mediaPage -= 1;
            renderMediaTable();
        });
    }

    if (mediaNext) {
        mediaNext.addEventListener('click', () => {
            if (mediaData.length <= (mediaPage + 1) * mediaPageSize) {
                return;
            }
            mediaPage += 1;
            renderMediaTable();
        });
    }

    if (mediaForm) {
        mediaForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const orderId = currentMediaOrderId || '';
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
                await loadMedia(orderId);
            } catch (error) {
                showMediaNotice(`Upload failed: ${error.message}`, 'error');
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

function initShipmentOrdersPage() {
    const page = document.querySelector('[data-shipment-orders-page]');
    if (!page) {
        return;
    }

    const shipmentIdAttr = page.getAttribute('data-shipment-id');
    const shipmentNumberAttr = page.getAttribute('data-shipment-number');
    const canSeeIncome = page.getAttribute('data-show-income') !== '0';
    const canReturn = page.getAttribute('data-can-return') === '1';
    const tableBody = page.querySelector('[data-shipment-orders-table]');
    const statusStack = page.querySelector('[data-shipment-orders-status]');
    const filterForm = page.querySelector('[data-shipment-orders-filter]');
    const statusFilter = page.querySelector('[data-shipment-orders-status-filter]');
    const searchInput = page.querySelector('[data-shipment-orders-search]');
    const clearButton = page.querySelector('[data-shipment-orders-clear]');
    const prevButton = page.querySelector('[data-shipment-orders-prev]');
    const nextButton = page.querySelector('[data-shipment-orders-next]');
    const pageLabel = page.querySelector('[data-shipment-orders-page]');
    const details = page.querySelectorAll('[data-detail]');
    const mediaPanel = page.querySelector('[data-order-media-panel]');
    const mediaTitle = page.querySelector('[data-order-media-title]');
    const mediaForm = page.querySelector('[data-order-media-form]');
    const mediaIdField = page.querySelector('[data-order-media-id]');
    const mediaTable = page.querySelector('[data-order-media-table]');
    const mediaStatus = page.querySelector('[data-order-media-status]');
    const mediaPrev = page.querySelector('[data-order-media-prev]');
    const mediaNext = page.querySelector('[data-order-media-next]');
    const mediaPageLabel = page.querySelector('[data-order-media-page]');

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
        setTimeout(() => notice.remove(), 6000);
    };

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

    const setDetail = (key, value) => {
        const target = detailMap[key];
        if (target) {
            target.textContent = value !== null && value !== undefined && value !== '' ? value : '--';
        }
    };

    const formatNumber = (value, digits) => {
        if (!Number.isFinite(value)) {
            return '0';
        }
        return value.toFixed(digits).replace(/\.?0+$/, '');
    };

    const formatAmount = (value) => {
        const num = Number(value ?? 0);
        return Number.isFinite(num) ? num.toFixed(2) : '0.00';
    };

    const limit = 20;
    const mediaPageSize = 5;
    let offset = 0;
    let lastCount = 0;
    let resolvedShipmentId = shipmentIdAttr || '';
    let activeFilters = {
        status: '',
        q: '',
    };
    let mediaPage = 0;
    let mediaData = [];

    const updatePager = () => {
        if (prevButton) {
            prevButton.disabled = offset === 0;
        }
        if (nextButton) {
            nextButton.disabled = lastCount < limit;
        }
        if (pageLabel) {
            pageLabel.textContent = `Page ${Math.floor(offset / limit) + 1}`;
        }
    };

    const updateMediaPager = () => {
        if (mediaPrev) {
            mediaPrev.disabled = mediaPage === 0;
        }
        if (mediaNext) {
            mediaNext.disabled = mediaData.length <= (mediaPage + 1) * mediaPageSize;
        }
        if (mediaPageLabel) {
            mediaPageLabel.textContent = `Page ${mediaPage + 1}`;
        }
    };

    const renderMediaTable = (orderId) => {
        if (!mediaTable) {
            return;
        }
        if (!mediaData.length) {
            mediaTable.innerHTML = '<tr><td colspan="5" class="muted">No attachments yet.</td></tr>';
            updateMediaPager();
            return;
        }
        const rows = mediaData.slice(mediaPage * mediaPageSize, mediaPage * mediaPageSize + mediaPageSize);
        mediaTable.innerHTML = rows
            .map((att) => {
                const downloadUrl =
                    att.download_url || `${window.APP_BASE}/api/attachments/download.php?id=${att.id}`;
                return `<tr>
                    <td>${att.title || att.original_name || '-'}</td>
                    <td>${att.mime_type || '-'}</td>
                    <td>${att.created_at || '-'}</td>
                    <td><a class="text-link" href="${downloadUrl}">Download</a></td>
                    <td><button class="button ghost small" type="button" data-attachment-delete data-attachment-id="${att.id}">Delete</button></td>
                </tr>`;
            })
            .join('');
        updateMediaPager();
        bindMediaDeletes(orderId);
    };

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
                    await loadMedia(orderId);
                } catch (error) {
                    showMediaNotice(`Delete failed: ${error.message}`, 'error');
                }
            });
        });
    };

    const loadMedia = async (orderId) => {
        if (!mediaTable) {
            return;
        }
        mediaTable.innerHTML = '<tr><td colspan="5" class="muted">Loading attachments...</td></tr>';
        try {
            const data = await fetchJson(
                `${window.APP_BASE}/api/attachments/list.php?entity_type=order&entity_id=${encodeURIComponent(
                    String(orderId)
                )}`
            );
            mediaData = data.data || [];
            mediaPage = 0;
            renderMediaTable(orderId);
        } catch (error) {
            mediaData = [];
            renderMediaTable(orderId);
            showMediaNotice(`Attachments load failed: ${error.message}`, 'error');
        }
    };

    const openMediaPanel = (orderId, trackingLabel = '') => {
        if (mediaPanel) {
            mediaPanel.classList.remove('is-hidden');
        }
        if (mediaIdField) {
            mediaIdField.value = String(orderId);
        }
        if (mediaTitle) {
            const label = trackingLabel ? `Order ${trackingLabel}` : `Order #${orderId}`;
            mediaTitle.textContent = `Attachments for ${label}.`;
        }
        loadMedia(orderId);
    };

    const bindOrderMediaButtons = () => {
        if (!tableBody) {
            return;
        }
        tableBody.querySelectorAll('[data-order-media]').forEach((button) => {
            button.addEventListener('click', () => {
                const orderId = button.getAttribute('data-order-id');
                if (!orderId) {
                    return;
                }
                const tracking = button.getAttribute('data-order-tracking') || '';
                openMediaPanel(orderId, tracking ? `#${tracking}` : '');
            });
        });
    };

    const renderOrders = (rows) => {
        if (!tableBody) {
            return;
        }
        const columnCount = canSeeIncome ? (canReturn ? 9 : 8) : (canReturn ? 8 : 7);
        if (!rows.length) {
            tableBody.innerHTML = `<tr><td colspan="${columnCount}" class="muted">No orders found.</td></tr>`;
            updatePager();
            return;
        }
        tableBody.innerHTML = rows
            .map((row) => {
                const customerLabel = row.customer_name
                    ? row.customer_code
                        ? `${row.customer_name} (${row.customer_code})`
                        : row.customer_name
                    : '-';
                const qtyValue = row.qty !== null && row.qty !== undefined ? row.qty : null;
                const qtyLabel = qtyValue !== null && qtyValue !== '' ? `${qtyValue} ${row.unit_type || ''}`.trim() : '-';
                const totalCell = canSeeIncome ? `<td>${formatAmount(row.total_price)}</td>` : '';
                const canReturnRow =
                    canReturn
                    && row.sub_branch_id
                    && !['with_delivery', 'picked_up'].includes(row.fulfillment_status || '');
                const returnCell = canReturn
                    ? `<td>${
                          canReturnRow
                              ? `<button class="button ghost small" type="button" data-order-return="${row.id}">Return</button>`
                              : '-'
                      }</td>`
                    : '';
                return `<tr>
                    <td>${row.tracking_number || '-'}</td>
                    <td>${customerLabel}</td>
                    <td>${row.sub_branch_name || '-'}</td>
                    <td>${qtyLabel}</td>
                    ${totalCell}
                    <td>${row.fulfillment_status || '-'}</td>
                    <td>${row.created_at || '-'}</td>
                    <td>
                        <button class="button ghost small" type="button" data-order-media data-order-id="${row.id}"
                            data-order-tracking="${row.tracking_number || ''}">
                            Manage
                        </button>
                    </td>
                    ${returnCell}
                </tr>`;
            })
            .join('');
        updatePager();
        bindOrderMediaButtons();
    };

    const loadOrders = async () => {
        if (!resolvedShipmentId) {
            return;
        }
        if (tableBody) {
            const columnCount = canSeeIncome ? 8 : 7;
            tableBody.innerHTML = `<tr><td colspan="${columnCount}" class="loading-cell"><div class="loading-inline"><span class="spinner" aria-hidden="true"></span><span class="loading-text">Orders are loading, please wait...</span></div></td></tr>`;
        }
        const params = new URLSearchParams({
            shipment_id: String(resolvedShipmentId),
            limit: String(limit),
            offset: String(offset),
        });
        if (activeFilters.status) {
            params.append('fulfillment_status', activeFilters.status);
        }
        if (activeFilters.q) {
            params.append('q', activeFilters.q);
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/orders/list.php?${params.toString()}`);
            const rows = data.data || [];
            lastCount = rows.length;
            renderOrders(rows);
        } catch (error) {
            lastCount = 0;
            renderOrders([]);
            showNotice(`Orders load failed: ${error.message}`, 'error');
        }
    };

    const loadShipment = async () => {
        if (!resolvedShipmentId && !shipmentNumberAttr) {
            showNotice('Shipment is required.', 'error');
            return;
        }
        try {
            const query = resolvedShipmentId
                ? `shipment_id=${encodeURIComponent(resolvedShipmentId)}`
                : `shipment_number=${encodeURIComponent(shipmentNumberAttr)}`;
            const data = await fetchJson(`${window.APP_BASE}/api/shipments/view.php?${query}`);
            const shipment = data.shipment || {};
            resolvedShipmentId = shipment.id || resolvedShipmentId;
            setDetail('shipment_number', shipment.shipment_number || '--');
            setDetail('status', shipment.status || '--');
            setDetail('origin_country', shipment.origin_country || '--');
            setDetail('shipping_type', shipment.shipping_type || '--');

            const orders = data.orders || [];
            let totalQty = 0;
            let totalPrice = 0;
            const unitTypes = new Set();
            orders.forEach((row) => {
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
            setDetail('order_count', orders.length);
            setDetail('total_qty', `${formatNumber(totalQty, 3)}${unitLabel}`);
            if (canSeeIncome) {
                setDetail('total_price', formatAmount(totalPrice));
            }
        } catch (error) {
            showNotice(`Shipment load failed: ${error.message}`, 'error');
        }
    };

    if (prevButton) {
        prevButton.addEventListener('click', () => {
            if (offset === 0) {
                return;
            }
            offset = Math.max(0, offset - limit);
            loadOrders();
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => {
            if (lastCount < limit) {
                return;
            }
            offset += limit;
            loadOrders();
        });
    }

    const applyFilters = () => {
        activeFilters = {
            status: statusFilter ? statusFilter.value : '',
            q: searchInput ? searchInput.value.trim() : '',
        };
        offset = 0;
        loadOrders();
    };

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            applyFilters();
        });
    }

    if (statusFilter) {
        statusFilter.addEventListener('change', applyFilters);
    }

    if (clearButton) {
        clearButton.addEventListener('click', () => {
            if (statusFilter) {
                statusFilter.value = '';
            }
            if (searchInput) {
                searchInput.value = '';
            }
            applyFilters();
        });
    }

    if (mediaPrev) {
        mediaPrev.addEventListener('click', () => {
            if (mediaPage === 0) {
                return;
            }
            mediaPage -= 1;
            renderMediaTable(mediaIdField ? mediaIdField.value : '');
        });
    }

    if (mediaNext) {
        mediaNext.addEventListener('click', () => {
            if (mediaData.length <= (mediaPage + 1) * mediaPageSize) {
                return;
            }
            mediaPage += 1;
            renderMediaTable(mediaIdField ? mediaIdField.value : '');
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
                await loadMedia(orderId);
            } catch (error) {
                showMediaNotice(`Upload failed: ${error.message}`, 'error');
            }
        });
    }

    if (tableBody && canReturn) {
        tableBody.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-order-return]');
            if (!button) {
                return;
            }
            const orderId = button.getAttribute('data-order-return');
            if (!orderId) {
                return;
            }
            const confirmed = await showConfirmDialog({
                title: 'Return order',
                message: 'Return this order to the main branch?',
                confirmLabel: 'Return',
            });
            if (!confirmed) {
                return;
            }
            try {
                await fetchJson(`${window.APP_BASE}/api/orders/return_to_main.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_id: orderId }),
                });
                showNotice('Order returned to main branch.', 'success');
                loadShipment().then(loadOrders);
            } catch (error) {
                showNotice(`Return failed: ${error.message}`, 'error');
            }
        });
    }

    loadShipment().then(loadOrders);
}

function initShipmentCreate() {
    const page = document.querySelector('[data-shipment-create]');
    if (!page) {
        return;
    }

    const createForm = page.querySelector('[data-shipments-create]');
    const statusStack = page.querySelector('[data-shipments-status]');
    const originSelects = page.querySelectorAll('[data-origin-select]');
    const SupplierSelects = page.querySelectorAll('[data-supplier-select]');
    const goodsSelects = page.querySelectorAll('[data-goods-select]');
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

    const loadSuppliers = async () => {
        if (!SupplierSelects.length) {
            return;
        }
        const cache = new Map();
        const types = new Set();
        SupplierSelects.forEach((select) => {
            const type = select.getAttribute('data-supplier-type');
            if (type) {
                types.add(type);
            }
        });
        for (const type of types) {
            try {
                const params = new URLSearchParams({ type, limit: '200' });
                const data = await fetchJson(`${window.APP_BASE}/api/suppliers/list.php?${params.toString()}`);
                cache.set(type, data.data || []);
            } catch (error) {
                showNotice(`Suppliers load failed: ${error.message}`, 'error');
                cache.set(type, []);
            }
        }
        SupplierSelects.forEach((select) => {
            const type = select.getAttribute('data-supplier-type');
            const current = select.value;
            select.querySelectorAll('option[data-dynamic]').forEach((option) => option.remove());
            const rows = cache.get(type) || [];
            rows.forEach((Supplier) => {
                const option = document.createElement('option');
                option.value = Supplier.id;
                option.textContent = Supplier.name;
                option.setAttribute('data-dynamic', 'true');
                select.appendChild(option);
            });
            if (current) {
                select.value = current;
            }
        });
    };

    const loadGoodsTypes = async () => {
        if (!goodsSelects.length) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/goods_types/list.php?limit=300`);
            goodsSelects.forEach((select) => {
                const current = select.value;
                select.querySelectorAll('option[data-dynamic]').forEach((option) => option.remove());
                (data.data || []).forEach((goodsType) => {
                    const option = document.createElement('option');
                    option.value = goodsType.name;
                    option.textContent = goodsType.name;
                    option.setAttribute('data-dynamic', 'true');
                    select.appendChild(option);
                });
                if (current) {
                    select.value = current;
                }
            });
        } catch (error) {
            showNotice(`Goods types load failed: ${error.message}`, 'error');
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
                showNotice('Expected arrival date must be greater than expected departure date.', 'error');
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
    loadGoodsTypes();
    loadSuppliers();
}

function initSupplierView() {
    const page = document.querySelector('[data-supplier-view]');
    if (!page) {
        return;
    }

    const supplierId = page.getAttribute('data-supplier-id');
    if (!supplierId) {
        return;
    }

    const statusStack = page.querySelector('[data-supplier-status]');
    const details = page.querySelectorAll('[data-supplier-detail]');
    const stats = page.querySelectorAll('[data-supplier-stat]');
    const shipmentsTable = page.querySelector('[data-supplier-shipments]');
    const invoiceForm = page.querySelector('[data-supplier-invoice-form]');
    const invoiceStatus = page.querySelector('[data-supplier-invoice-status]');
    const invoicesTable = page.querySelector('[data-supplier-invoices]');
    const invoicesPrev = page.querySelector('[data-supplier-invoices-prev]');
    const invoicesNext = page.querySelector('[data-supplier-invoices-next]');
    const invoicesPageLabel = page.querySelector('[data-supplier-invoices-page]');
    const transactionForm = page.querySelector('[data-supplier-transaction-form]');
    const transactionStatus = page.querySelector('[data-supplier-transaction-status]');
    const transactionsTable = page.querySelector('[data-supplier-transactions]');
    const transactionsPrev = page.querySelector('[data-supplier-transactions-prev]');
    const transactionsNext = page.querySelector('[data-supplier-transactions-next]');
    const transactionsPageLabel = page.querySelector('[data-supplier-transactions-page]');
    const invoiceSelect = page.querySelector('[data-supplier-invoice-select]');
    const invoiceIdInput = page.querySelector('[data-supplier-invoice-id]');
    const invoiceCurrencySelect = page.querySelector('[data-supplier-invoice-currency]');
    const invoiceSubmitButton = page.querySelector('[data-supplier-invoice-submit]');
    const invoiceAccountSelect = page.querySelector('[data-supplier-invoice-account]');
    const invoiceCancelEditButton = page.querySelector('[data-supplier-invoice-cancel-edit]');
    const shipmentSearchInput = page.querySelector('[data-shipment-search]');
    const shipmentSelect = page.querySelector('[data-shipment-select]');
    const paymentAccountSelect = page.querySelector('[data-supplier-payment-account]');
    const invoiceRateKgInput = page.querySelector('[data-supplier-rate-kg]');
    const invoiceRateCbmInput = page.querySelector('[data-supplier-rate-cbm]');
    const invoiceWeightInput = page.querySelector('[data-supplier-invoice-weight]');
    const invoiceVolumeInput = page.querySelector('[data-supplier-invoice-volume]');
    const invoiceTotalInput = page.querySelector('[data-supplier-invoice-total]');
    const transactionItemsBody = page.querySelector('[data-supplier-transaction-items]');
    const transactionAddButton = page.querySelector('[data-supplier-add-transaction-line]');
    const transactionTotalInput = page.querySelector('[data-supplier-transaction-total]');
    const transactionTypeSelect = transactionForm?.querySelector('[name="type"]');
    const transactionReasonField = page.querySelector('[data-supplier-reason-field]');
    const transactionReasonSelect = page.querySelector('[data-supplier-transaction-reason]');
    const transactionSubmitButton = transactionForm?.querySelector('button[type="submit"]');
    const canEdit = page.getAttribute('data-can-edit') === '1';

    const limit = 5;
    let invoicesPage = 0;
    let transactionsPage = 0;
    let shipmentSearchTimer = null;
    let editingInvoiceId = null;
    const accountMap = new Map();
    const shipmentMap = new Map();

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

    const applyShipmentTotals = (shipment) => {
        if (!invoiceWeightInput || !invoiceVolumeInput) {
            return;
        }
        const weight = shipment ? Number(shipment.weight ?? 0) : 0;
        const volume = shipment ? Number(shipment.size ?? 0) : 0;
        invoiceWeightInput.value = formatQty(weight);
        invoiceVolumeInput.value = formatQty(volume);
    };

    const updateInvoiceTotals = () => {
        if (!invoiceTotalInput) {
            return;
        }
        const rateKg = Number(invoiceRateKgInput?.value ?? 0);
        const rateCbm = Number(invoiceRateCbmInput?.value ?? 0);
        const weight = Number(invoiceWeightInput?.value ?? 0);
        const volume = Number(invoiceVolumeInput?.value ?? 0);
        const total = (Number.isFinite(rateKg) ? rateKg : 0) * (Number.isFinite(weight) ? weight : 0)
            + (Number.isFinite(rateCbm) ? rateCbm : 0) * (Number.isFinite(volume) ? volume : 0);
        invoiceTotalInput.value = formatAmount(total);
    };

    const updateSupplierTransactionTypeUI = () => {
        if (!transactionTypeSelect) {
            return;
        }
        const typeValue = transactionTypeSelect.value;
        const isRefund = typeValue === 'refund';
        const isBalanceAdjust = typeValue === 'charge' || typeValue === 'discount';
        const allowInvoice = typeValue === 'payment';
        if (transactionReasonField) {
            transactionReasonField.classList.toggle('is-hidden', !isRefund);
        }
        if (transactionReasonSelect) {
            transactionReasonSelect.required = isRefund;
            if (!isRefund) {
                transactionReasonSelect.value = '';
            }
        }
        if (invoiceSelect) {
            invoiceSelect.disabled = !allowInvoice;
            if (!allowInvoice) {
                invoiceSelect.value = '';
            }
        }
        if (paymentAccountSelect) {
            paymentAccountSelect.disabled = isBalanceAdjust;
            paymentAccountSelect.required = !isBalanceAdjust;
            if (isBalanceAdjust) {
                paymentAccountSelect.value = '';
            }
        }
        if (transactionSubmitButton) {
            if (typeValue === 'refund') {
                transactionSubmitButton.textContent = 'Add refund';
            } else if (typeValue === 'adjustment') {
                transactionSubmitButton.textContent = 'Add adjustment';
            } else if (typeValue === 'charge') {
                transactionSubmitButton.textContent = 'Add charge';
            } else if (typeValue === 'discount') {
                transactionSubmitButton.textContent = 'Add discount';
            } else {
                transactionSubmitButton.textContent = 'Add payment';
            }
        }
    };

    const buildLineRow = () => {
        const row = document.createElement('tr');
        row.setAttribute('data-line-item', '');
        row.innerHTML =
            '<td><input type="text" name="item_description" placeholder="Description" required></td>' +
            '<td><input type="number" step="0.01" name="item_amount" placeholder="0.00" required></td>' +
            '<td><button class="button ghost small" type="button" data-line-remove>Remove</button></td>';
        return row;
    };

    const initLineItems = (tbody, totalInput) => {
        if (!tbody) {
            return null;
        }

        const updateTotal = () => {
            let total = 0;
            tbody.querySelectorAll('[data-line-item]').forEach((row) => {
                const amountInput = row.querySelector('[name="item_amount"]');
                if (!amountInput) {
                    return;
                }
                const value = parseFloat(amountInput.value);
                if (Number.isFinite(value)) {
                    total += value;
                }
            });
            if (totalInput) {
                totalInput.value = total.toFixed(2);
            }
        };

        const bindRow = (row) => {
            const amountInput = row.querySelector('[name="item_amount"]');
            const descInput = row.querySelector('[name="item_description"]');
            if (amountInput) {
                amountInput.addEventListener('input', updateTotal);
            }
            if (descInput) {
                descInput.addEventListener('input', updateTotal);
            }
            const removeButton = row.querySelector('[data-line-remove]');
            if (removeButton) {
                removeButton.addEventListener('click', () => {
                    row.remove();
                    if (!tbody.querySelector('[data-line-item]')) {
                        const freshRow = buildLineRow();
                        tbody.appendChild(freshRow);
                        bindRow(freshRow);
                    }
                    updateTotal();
                });
            }
        };

        tbody.querySelectorAll('[data-line-item]').forEach(bindRow);
        updateTotal();

        const collect = () => {
            const items = [];
            tbody.querySelectorAll('[data-line-item]').forEach((row) => {
                const descInput = row.querySelector('[name="item_description"]');
                const amountInput = row.querySelector('[name="item_amount"]');
                const description = descInput ? descInput.value.trim() : '';
                const amountRaw = amountInput ? amountInput.value.trim() : '';
                if (!description && !amountRaw) {
                    return;
                }
                const amountValue = parseFloat(amountRaw);
                items.push({
                    description,
                    amount: Number.isFinite(amountValue) ? amountValue : NaN,
                });
            });
            return items;
        };

        return {
            addRow: () => {
                const row = buildLineRow();
                tbody.appendChild(row);
                bindRow(row);
                updateTotal();
            },
            collect,
            updateTotal,
        };
    };

    const renderSupplier = (Supplier) => {
        details.forEach((el) => {
            const key = el.getAttribute('data-supplier-detail');
            let value = Supplier[key];
            if (key === 'balance') {
                value = formatAmount(value);
            }
            if (key === 'type') {
                value =
                    value === 'shipper' ? 'Shipper' : value === 'consignee' ? 'Consignee' : value;
            }
            el.textContent = value !== null && value !== undefined && value !== '' ? value : '--';
        });
    };

    const renderSupplierStats = (statData) => {
        if (!stats.length) {
            return;
        }
        stats.forEach((el) => {
            const key = el.getAttribute('data-supplier-stat');
            let value = statData[key];
            if (['total_invoiced', 'total_paid', 'total_due'].includes(key)) {
                value = formatAmount(value);
            }
            el.textContent = value !== null && value !== undefined && value !== '' ? value : '--';
        });
    };

    const transactionLineManager = initLineItems(transactionItemsBody, transactionTotalInput);

    const resetInvoiceForm = () => {
        editingInvoiceId = null;
        if (invoiceIdInput) {
            invoiceIdInput.value = '';
        }
        if (invoiceForm) {
            invoiceForm.reset();
        }
        if (invoiceCurrencySelect) {
            invoiceCurrencySelect.value = 'USD';
        }
        if (invoiceAccountSelect) {
            invoiceAccountSelect.disabled = false;
            invoiceAccountSelect.required = false;
            invoiceAccountSelect.value = '';
        }
        if (shipmentSearchInput) {
            shipmentSearchInput.disabled = false;
        }
        if (shipmentSelect) {
            shipmentSelect.disabled = false;
        }
        if (invoiceRateKgInput) {
            invoiceRateKgInput.value = '';
        }
        if (invoiceRateCbmInput) {
            invoiceRateCbmInput.value = '';
        }
        if (invoiceWeightInput) {
            invoiceWeightInput.value = formatQty(0);
        }
        if (invoiceVolumeInput) {
            invoiceVolumeInput.value = formatQty(0);
        }
        if (invoiceTotalInput) {
            invoiceTotalInput.value = formatAmount(0);
        }
        if (invoiceSubmitButton) {
            invoiceSubmitButton.textContent = 'Add invoice';
        }
        if (invoiceCancelEditButton) {
            invoiceCancelEditButton.classList.add('is-hidden');
        }
    };

    const enterInvoiceEditMode = (invoice) => {
        editingInvoiceId = Number(invoice.id || 0) || null;
        if (invoiceIdInput) {
            invoiceIdInput.value = editingInvoiceId ? String(editingInvoiceId) : '';
        }
        if (invoiceCurrencySelect) {
            invoiceCurrencySelect.value = (invoice.currency || 'USD').toUpperCase();
        }
        if (invoiceAccountSelect) {
            invoiceAccountSelect.disabled = true;
            invoiceAccountSelect.required = false;
            invoiceAccountSelect.value = '';
        }
        if (invoiceForm) {
            const noteInput = invoiceForm.querySelector('[name="note"]');
            const issuedInput = invoiceForm.querySelector('[name="issued_at"]');
            if (noteInput) {
                noteInput.value = invoice.note || '';
            }
            if (issuedInput && invoice.issued_at) {
                issuedInput.value = invoice.issued_at.replace(' ', 'T').slice(0, 16);
            }
        }
        if (shipmentSelect) {
            const shipmentId = invoice.shipment_id ? String(invoice.shipment_id) : '';
            if (shipmentId) {
                const hasOption = Boolean(shipmentSelect.querySelector(`option[value="${shipmentId}"]`));
                if (!hasOption) {
                    const option = document.createElement('option');
                    option.value = shipmentId;
                    option.textContent = `Shipment #${shipmentId}`;
                    shipmentSelect.appendChild(option);
                }
                shipmentSelect.value = shipmentId;
            } else {
                shipmentSelect.value = '';
            }
            shipmentSelect.disabled = true;
        }
        if (shipmentSearchInput) {
            shipmentSearchInput.disabled = true;
            shipmentSearchInput.value = '';
        }
        if (invoiceRateKgInput) {
            invoiceRateKgInput.value = formatAmount(invoice.rate_kg ?? 0);
        }
        if (invoiceRateCbmInput) {
            invoiceRateCbmInput.value = formatAmount(invoice.rate_cbm ?? 0);
        }
        if (invoiceWeightInput) {
            invoiceWeightInput.value = formatQty(invoice.total_weight ?? 0);
        }
        if (invoiceVolumeInput) {
            invoiceVolumeInput.value = formatQty(invoice.total_volume ?? 0);
        }
        updateInvoiceTotals();
        if (invoiceSubmitButton) {
            invoiceSubmitButton.textContent = 'Update invoice';
        }
        if (invoiceCancelEditButton) {
            invoiceCancelEditButton.classList.remove('is-hidden');
        }
    };

    if (invoiceCancelEditButton) {
        invoiceCancelEditButton.addEventListener('click', () => {
            resetInvoiceForm();
        });
    }

    if (invoiceRateKgInput) {
        invoiceRateKgInput.addEventListener('input', updateInvoiceTotals);
    }
    if (invoiceRateCbmInput) {
        invoiceRateCbmInput.addEventListener('input', updateInvoiceTotals);
    }
    if (shipmentSelect) {
        shipmentSelect.addEventListener('change', () => {
            const shipment = shipmentMap.get(String(shipmentSelect.value || ''));
            applyShipmentTotals(shipment);
            updateInvoiceTotals();
        });
    }

    if (transactionAddButton && transactionLineManager) {
        transactionAddButton.addEventListener('click', () => {
            transactionLineManager.addRow();
        });
    }

    const syncInvoiceSelect = () => {
        if (!invoiceSelect || !transactionTypeSelect) {
            return;
        }
        const isPayment = transactionTypeSelect.value === 'payment';
        invoiceSelect.disabled = !isPayment;
        if (!isPayment) {
            invoiceSelect.value = '';
        }
    };

    if (transactionTypeSelect) {
        transactionTypeSelect.addEventListener('change', () => {
            syncInvoiceSelect();
            updateSupplierTransactionTypeUI();
            loadPaymentAccounts();
        });
        syncInvoiceSelect();
        updateSupplierTransactionTypeUI();
    }

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
                    row.supplier_role === 'shipper' ? 'Shipper' : row.supplier_role === 'consignee' ? 'Consignee' : '-';
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
              invoicesTable.innerHTML = '<tr><td colspan="10" class="muted">No invoices found.</td></tr>';
              return;
          }
          invoicesTable.innerHTML = rows
              .map((row) => {
                  const actions = [
                      `<a class="text-link" href="${window.APP_BASE}/views/internal/supplier_invoice_print?id=${row.id}" target="_blank" rel="noreferrer">Print</a>`,
                  ];
                  if (canEdit && row.status !== 'void') {
                      actions.push(
                          `<button class="text-link" type="button" data-supplier-invoice-edit data-invoice-id="${row.id}">Edit</button>`
                      );
                      actions.push(
                          `<button class="text-link" type="button" data-supplier-invoice-regenerate data-invoice-id="${row.id}">Regenerate</button>`
                      );
                  }
                  if (canEdit && Number(row.paid_total ?? 0) === 0 && row.status !== 'void') {
                      actions.push(
                          `<button class="text-link" type="button" data-supplier-invoice-cancel data-invoice-id="${row.id}">Cancel</button>`
                      );
                  }
                  let statusLabel = row.status || '-';
                  if (statusLabel === 'partially_paid') {
                      statusLabel = 'Partially paid';
                  } else if (statusLabel === 'void') {
                      statusLabel = 'Canceled';
                } else if (statusLabel !== '-' && statusLabel.length > 0) {
                    statusLabel = statusLabel.charAt(0).toUpperCase() + statusLabel.slice(1);
                  }
                  return `<tr>
                      <td>${escapeHtml(row.invoice_no || '-')}</td>
                      <td>${escapeHtml(row.shipment_number || '-')}</td>
                      <td>${escapeHtml(statusLabel)}</td>
                      <td>${escapeHtml(row.currency || 'USD')}</td>
                      <td>${formatAmount(row.rate_kg)}</td>
                      <td>${formatAmount(row.rate_cbm)}</td>
                      <td>${formatAmount(row.total)}</td>
                      <td>${formatAmount(row.due_total)}</td>
                      <td>${escapeHtml(row.issued_at || '-')}</td>
                      <td>${actions.join(' | ')}</td>
                  </tr>`;
              })
              .join('');

          invoicesTable.querySelectorAll('[data-supplier-invoice-edit]').forEach((button) => {
              button.addEventListener('click', async () => {
                  const id = button.getAttribute('data-invoice-id');
                  if (!id) {
                      return;
                  }
                  try {
                      const data = await fetchJson(
                          `${window.APP_BASE}/api/supplier_invoices/view.php?id=${encodeURIComponent(id)}`
                      );
                      if (!data.invoice) {
                          showNotice(invoiceStatus || statusStack, 'Invoice not found.', 'error');
                          return;
                      }
                      enterInvoiceEditMode(data.invoice);
                      if (invoiceForm) {
                          invoiceForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
                      }
                  } catch (error) {
                      showNotice(invoiceStatus || statusStack, `Edit load failed: ${error.message}`, 'error');
                  }
              });
          });

          invoicesTable.querySelectorAll('[data-supplier-invoice-regenerate]').forEach((button) => {
              button.addEventListener('click', async () => {
                  const id = button.getAttribute('data-invoice-id');
                  if (!id) {
                      return;
                  }
                  try {
                      await fetchJson(`${window.APP_BASE}/api/supplier_invoices/regenerate.php`, {
                          method: 'POST',
                          headers: { 'Content-Type': 'application/json' },
                          body: JSON.stringify({ id }),
                      });
                      showNotice(invoiceStatus || statusStack, 'Invoice totals updated.', 'success');
                      loadSupplier();
                      loadInvoices();
                      loadInvoiceOptions();
                  } catch (error) {
                      showNotice(invoiceStatus || statusStack, `Regenerate failed: ${error.message}`, 'error');
                  }
              });
          });

          invoicesTable.querySelectorAll('[data-supplier-invoice-cancel]').forEach((button) => {
              button.addEventListener('click', async () => {
                const id = button.getAttribute('data-invoice-id');
                if (!id) {
                    return;
                }
                const reason = await showConfirmDialog({
                    title: 'Cancel invoice',
                    message: 'Cancel this invoice? Add a reason.',
                    confirmLabel: 'Cancel invoice',
                    requireInput: true,
                    inputPlaceholder: 'Reason'
                });
                if (!reason || !reason.trim()) {
                    return;
                }
                try {
                    await fetchJson(`${window.APP_BASE}/api/supplier_invoices/delete.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id, reason: reason.trim() }),
                    });
                    showNotice(invoiceStatus || statusStack, 'Invoice canceled.', 'success');
                    invoicesPage = 0;
                    loadSupplier();
                    loadInvoices();
                    loadInvoiceOptions();
                } catch (error) {
                    showNotice(invoiceStatus || statusStack, `Cancel failed: ${error.message}`, 'error');
                }
            });
        });
    };

    const renderTransactions = (rows) => {
        if (!transactionsTable) {
            return;
        }
        if (!rows.length) {
            transactionsTable.innerHTML = '<tr><td colspan="10" class="muted">No transactions found.</td></tr>';
            return;
        }
        transactionsTable.innerHTML = rows
            .map((row) => {
                let typeLabel = 'Payment';
                if (row.type === 'invoice_create') {
                    typeLabel = 'Invoice';
                } else if (row.type === 'invoice_regenerate') {
                    typeLabel = 'Invoice Regenerated';
                } else if (row.type === 'refund') {
                    typeLabel = 'Refund';
                } else if (row.type === 'adjustment') {
                    typeLabel = 'Adjustment';
                } else if (row.type === 'charge') {
                    typeLabel = 'Charge';
                } else if (row.type === 'discount') {
                    typeLabel = 'Discount';
                }
                const statusLabel = row.status === 'canceled' ? 'Canceled' : 'Active';
                let noteLabel = row.note || '-';
                if (row.status === 'canceled') {
                    noteLabel = row.canceled_reason ? `Canceled: ${row.canceled_reason}` : 'Canceled';
                }
                const actions = [];
                if (canEdit && row.status === 'active' && row.type === 'payment') {
                    actions.push(
                        `<button class="text-link" type="button" data-supplier-transaction-cancel data-transaction-id="${row.id}">Cancel</button>`
                    );
                }
                const printLink = row.type === 'payment'
                    ? `<a class="text-link" href="${window.APP_BASE}/views/internal/supplier_payment_print?id=${row.id}" target="_blank" rel="noreferrer">Print</a>`
                    : '-';
                return `<tr>
                    <td>${escapeHtml(row.payment_date || row.created_at || '-')}</td>
                    <td>${escapeHtml(typeLabel)}</td>
                    <td>${escapeHtml(statusLabel)}</td>
                    <td>${escapeHtml(row.account_label || row.payment_method_name || '-')}</td>
                    <td>${formatAmount(row.amount)}</td>
                    <td>${escapeHtml(row.invoice_no || '-')}</td>
                    <td>${escapeHtml(row.reason || '-')}</td>
                    <td>${escapeHtml(noteLabel)}</td>
                    <td>${printLink}</td>
                    <td>${actions.length ? actions.join(' | ') : '-'}</td>
                </tr>`;
            })
            .join('');

        transactionsTable.querySelectorAll('[data-supplier-transaction-cancel]').forEach((button) => {
            button.addEventListener('click', async () => {
                const id = button.getAttribute('data-transaction-id');
                if (!id) {
                    return;
                }
                const reason = await showConfirmDialog({
                    title: 'Cancel transaction',
                    message: 'Cancel this transaction? Add a reason.',
                    confirmLabel: 'Cancel transaction',
                    requireInput: true,
                    inputPlaceholder: 'Reason'
                });
                if (!reason || !reason.trim()) {
                    return;
                }
                try {
                    await fetchJson(`${window.APP_BASE}/api/supplier_transactions/delete.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id, reason: reason.trim() }),
                    });
                    showNotice(transactionStatus || statusStack, 'Transaction canceled.', 'success');
                    transactionsPage = 0;
                    loadSupplier();
                    loadTransactions();
                    loadInvoiceOptions();
                } catch (error) {
                    showNotice(transactionStatus || statusStack, `Cancel failed: ${error.message}`, 'error');
                }
            });
        });
    };

    const loadSupplier = async () => {
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/suppliers/view.php?id=${encodeURIComponent(supplierId)}`);
            renderSupplier(data.supplier || {});
            renderSupplierStats(data.stats || {});
            renderShipments(data.shipments || []);
        } catch (error) {
            showNotice(statusStack, `Supplier load failed: ${error.message}`, 'error');
        }
    };

    const loadInvoices = async () => {
        try {
            const params = new URLSearchParams({
                supplier_id: supplierId,
                limit: String(limit),
                offset: String(invoicesPage * limit),
            });
            const data = await fetchJson(`${window.APP_BASE}/api/supplier_invoices/list.php?${params.toString()}`);
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
            const params = new URLSearchParams({ supplier_id: supplierId, limit: '200' });
            const data = await fetchJson(`${window.APP_BASE}/api/supplier_invoices/list.php?${params.toString()}`);
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
                supplier_id: supplierId,
                limit: String(limit),
                offset: String(transactionsPage * limit),
            });
            const data = await fetchJson(`${window.APP_BASE}/api/supplier_transactions/list.php?${params.toString()}`);
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
            const params = new URLSearchParams({ limit: '50', supplier_id: supplierId });
            if (query) {
                params.set('q', query);
            }
            const data = await fetchJson(`${window.APP_BASE}/api/shipments/list.php?${params.toString()}`);
            shipmentSelect.querySelectorAll('option[data-dynamic]').forEach((option) => option.remove());
            const rows = data.data || [];
            shipmentMap.clear();
            if (!rows.length && query) {
                const option = document.createElement('option');
                option.textContent = 'No shipments found';
                option.disabled = true;
                option.setAttribute('data-dynamic', 'true');
                shipmentSelect.appendChild(option);
                return;
            }
            rows.forEach((shipment) => {
                shipmentMap.set(String(shipment.id), shipment);
                const option = document.createElement('option');
                option.value = shipment.id;
                option.textContent = `${shipment.shipment_number || 'Shipment'} (${shipment.status || 'status'})`;
                option.setAttribute('data-dynamic', 'true');
                shipmentSelect.appendChild(option);
            });
            if (currentValue) {
                shipmentSelect.value = currentValue;
            }
            const currentShipment = shipmentMap.get(String(shipmentSelect.value || ''));
            applyShipmentTotals(currentShipment);
            updateInvoiceTotals();
        } catch (error) {
            showNotice(statusStack, `Shipments load failed: ${error.message}`, 'error');
        }
    };

    const renderAccountOptions = (select, accounts, placeholder) => {
        if (!select) {
            return;
        }
        select.innerHTML = `<option value="">${placeholder}</option>`;
        (accounts || []).forEach((account) => {
            const option = document.createElement('option');
            option.value = account.id;
            const methodLabel = account.payment_method_name ? ` (${account.payment_method_name})` : '';
            option.textContent = `${account.name}${methodLabel}`;
            select.appendChild(option);
            accountMap.set(String(account.id), account);
        });
    };

    const loadPaymentAccounts = async () => {
        if (!paymentAccountSelect) {
            return;
        }
        accountMap.clear();
        try {
            const adminData = await fetchJson(`${window.APP_BASE}/api/accounts/list.php?owner_type=admin&is_active=1`);
            const adminAccounts = adminData.data || [];
            renderAccountOptions(paymentAccountSelect, adminAccounts, 'Select admin account');
        } catch (error) {
            showNotice(statusStack, `Accounts load failed: ${error.message}`, 'error');
        }
    };

    if (invoiceForm && canEdit) {
        invoiceForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const shipmentValue = invoiceForm.querySelector('[name="shipment_id"]')?.value || '';
            const issuedValue = invoiceForm.querySelector('[name="issued_at"]')?.value || '';
            const noteValue = invoiceForm.querySelector('[name="note"]')?.value?.trim() || '';
            const rateKgRaw = invoiceRateKgInput ? invoiceRateKgInput.value.trim() : '';
            const rateCbmRaw = invoiceRateCbmInput ? invoiceRateCbmInput.value.trim() : '';
            if (rateKgRaw === '' || rateCbmRaw === '') {
                showNotice(invoiceStatus, 'Enter both kg and cbm rates.', 'error');
                return;
            }
            const rateKg = Number(rateKgRaw);
            const rateCbm = Number(rateCbmRaw);
            if (!Number.isFinite(rateKg) || !Number.isFinite(rateCbm)) {
                showNotice(invoiceStatus, 'Rates must be valid numbers.', 'error');
                return;
            }
            if (rateKg < 0 || rateCbm < 0) {
                showNotice(invoiceStatus, 'Rates must be zero or greater.', 'error');
                return;
            }
            if (rateKg <= 0 && rateCbm <= 0) {
                showNotice(invoiceStatus, 'At least one rate must be greater than zero.', 'error');
                return;
            }

            const isEditing = Boolean(editingInvoiceId);
            const payload = {
                supplier_id: supplierId,
                currency: invoiceCurrencySelect ? invoiceCurrencySelect.value || 'USD' : 'USD',
                rate_kg: rateKg,
                rate_cbm: rateCbm,
            };
            if (!isEditing) {
                if (!shipmentValue) {
                    showNotice(invoiceStatus, 'Select a shipment.', 'error');
                    return;
                }
                payload.shipment_id = shipmentValue;
            }
            const normalizedIssued = normalizeDateTime(issuedValue);
            if (normalizedIssued) {
                payload.issued_at = normalizedIssued;
            }
            if (isEditing) {
                payload.invoice_id = editingInvoiceId;
                payload.note = noteValue;
            } else if (noteValue) {
                payload.note = noteValue;
            }
            try {
                await fetchJson(`${window.APP_BASE}/api/supplier_invoices/${isEditing ? 'update' : 'create'}.php`, {
                    method: isEditing ? 'PATCH' : 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showNotice(invoiceStatus, isEditing ? 'Invoice updated.' : 'Invoice created.', 'success');
                resetInvoiceForm();
                invoicesPage = 0;
                loadSupplier();
                loadInvoices();
                loadInvoiceOptions();
            } catch (error) {
                showNotice(
                    invoiceStatus,
                    `${isEditing ? 'Update' : 'Create'} failed: ${error.message}`,
                    'error'
                );
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
            const invoiceValue = transactionForm.querySelector('[name="invoice_id"]')?.value || '';
            const typeValue = transactionForm.querySelector('[name="type"]')?.value || 'payment';
            const isBalanceAdjust = typeValue === 'charge' || typeValue === 'discount';
            const paymentDateValue = transactionForm.querySelector('[name="payment_date"]')?.value || '';
            const noteValue = transactionForm.querySelector('[name="note"]')?.value?.trim() || '';
            const reasonValue = transactionReasonSelect ? String(transactionReasonSelect.value || '') : '';
            if (typeValue === 'refund' && !reasonValue) {
                showNotice(transactionStatus, 'Select a refund reason.', 'error');
                return;
            }
            const rawItems = transactionLineManager ? transactionLineManager.collect() : [];
            const items = [];
            for (const item of rawItems) {
                const description = item.description ? item.description.trim() : '';
                const amount = item.amount;
                if (!description) {
                    showNotice(transactionStatus, 'Line item description is required.', 'error');
                    return;
                }
                if (!Number.isFinite(amount) || amount === 0) {
                    showNotice(transactionStatus, 'Line item amount is required.', 'error');
                    return;
                }
                items.push({ description, amount });
            }
            if (!items.length) {
                showNotice(transactionStatus, 'Add at least one line item.', 'error');
                return;
            }
            let adminAccount = null;
            let accountId = '';
            if (!isBalanceAdjust) {
                accountId = paymentAccountSelect ? paymentAccountSelect.value : '';
                if (!accountId) {
                    showNotice(transactionStatus, 'Select an admin account.', 'error');
                    return;
                }
                adminAccount = accountMap.get(String(accountId));
                if (!adminAccount || !adminAccount.payment_method_id) {
                    showNotice(transactionStatus, 'Selected account is missing a payment method.', 'error');
                    return;
                }
            }

            const payload = {
                supplier_id: supplierId,
                type: typeValue,
                items,
            };
            if (invoiceValue && typeValue === 'payment') {
                payload.invoice_id = invoiceValue;
            } else if (invoiceValue && typeValue !== 'payment') {
                showNotice(transactionStatus, 'Invoice can only be applied to payments.', 'error');
                return;
            }
            if (paymentDateValue) {
                payload.payment_date = paymentDateValue;
            }
            if (typeValue === 'refund') {
                payload.reason = reasonValue;
            }
            if (noteValue) {
                payload.note = noteValue;
            }
            if (!isBalanceAdjust && adminAccount) {
                payload.admin_account_id = Number(accountId);
                payload.payment_method_id = Number(adminAccount.payment_method_id);
            }
            try {
                await fetchJson(`${window.APP_BASE}/api/supplier_transactions/create.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showNotice(transactionStatus, 'Transaction recorded.', 'success');
                transactionForm.reset();
                if (transactionLineManager) {
                    transactionLineManager.updateTotal();
                }
                syncInvoiceSelect();
                updateSupplierTransactionTypeUI();
                transactionsPage = 0;
                loadSupplier();
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

    if (invoiceForm) {
        resetInvoiceForm();
    }
    loadSupplier();
    loadInvoices();
    loadInvoiceOptions();
    loadTransactions();
    loadShipmentOptions();
    initSupplierTabUi();
    loadPaymentAccounts();
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
                          collection: 'Collection',
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
    const tableBody = page.querySelector('[data-orders-shipments-table]');
    const statusStack = page.querySelector('[data-orders-status]');
    const refreshButton = page.querySelector('[data-orders-refresh]');
    const prevButton = page.querySelector('[data-orders-prev]');
    const nextButton = page.querySelector('[data-orders-next]');
    const pageLabel = page.querySelector('[data-orders-page]');
    const canSeeIncome = page.getAttribute('data-show-income') !== '0';

    const limit = 10;
    let offset = 0;
    let lastFilters = {};
    let lastCount = 0;

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

    const formatAmount = (value) => {
        const num = Number(value ?? 0);
        return Number.isFinite(num) ? num.toFixed(2) : '0.00';
    };

    const renderRows = (rows) => {
        if (!tableBody) {
            return;
        }
        if (!rows.length) {
            tableBody.innerHTML = `<tr><td colspan="${canSeeIncome ? 6 : 5}" class="muted">No shipments found.</td></tr>`;
            return;
        }
        tableBody.innerHTML = rows
            .map((row) => {
                const viewUrl = row.id
                    ? `${window.APP_BASE}/views/internal/shipment_orders?shipment_id=${encodeURIComponent(row.id)}`
                    : '#';
                const totalCell = canSeeIncome ? `<td>${formatAmount(row.total_price)}</td>` : '';
                return `<tr>
                    <td>${row.shipment_number || '-'}</td>
                    <td>${row.origin_country || '-'}</td>
                    <td>${row.status || '-'}</td>
                    <td>${row.order_count || 0}</td>
                    ${totalCell}
                    <td><a class="text-link" href="${viewUrl}">View orders</a></td>
                </tr>`;
            })
            .join('');
    };

    const loadShipments = async (filters = {}) => {
        if (tableBody) {
            tableBody.innerHTML = `<tr><td colspan="${canSeeIncome ? 6 : 5}" class="muted">Loading shipments...</td></tr>`;
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
            const data = await fetchJson(`${window.APP_BASE}/api/orders/grouped.php?${params.toString()}`);
            const rows = data.data || [];
            lastCount = rows.length;
            renderRows(rows);
            if (prevButton) {
                prevButton.disabled = offset === 0;
            }
            if (nextButton) {
                nextButton.disabled = rows.length < limit;
            }
            if (pageLabel) {
                pageLabel.textContent = `Page ${Math.floor(offset / limit) + 1}`;
            }
        } catch (error) {
            renderRows([]);
            showNotice(`Orders load failed: ${error.message}`, 'error');
        }
    };

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(filterForm);
            lastFilters = Object.fromEntries(formData.entries());
            offset = 0;
            loadShipments(lastFilters);
        });
    }

    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            offset = 0;
            loadShipments(lastFilters);
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
            if (lastCount < limit) {
                return;
            }
            offset += limit;
            loadShipments(lastFilters);
        });
    }

    loadShipments();
}

function initOrderCreate() {
    const page = document.querySelector('[data-order-create]');
    if (!page) {
        return;
    }

    const createForm = page.querySelector('[data-orders-create]');
    const statusStack = page.querySelector('[data-orders-status]');
    const collectionSelect = page.querySelector('[data-collection-select]');
    const customerSelect = page.querySelector('[data-customer-select]');
    const subBranchDisplay = page.querySelector('[data-sub-branch-display]');
    const weightTypeInputs = page.querySelectorAll('[data-weight-type]');
    const unitTypeInput = page.querySelector('[data-unit-type]');
    const unitDisplay = page.querySelector('[data-unit-display]');
    const actualGroups = page.querySelectorAll('[data-weight-actual]');
    const volumeGroups = page.querySelectorAll('[data-weight-volume]');
    const rateKgInput = createForm?.querySelector('[name="rate_kg"]');
    const rateCbmInput = createForm?.querySelector('[name="rate_cbm"]');
    const trackingInput = createForm?.querySelector('[name="tracking_number"]');
    const trackingGenerateButton = page.querySelector('[data-tracking-generate]');
    const trackingPrintButton = page.querySelector('[data-tracking-print]');
    const submitButton = createForm?.querySelector('button[type="submit"]');
    const mediaPanel = page.querySelector('[data-order-create-media-panel]');
    const mediaTitle = page.querySelector('[data-order-create-media-title]');
    const mediaForm = page.querySelector('[data-order-create-media-form]');
    const mediaIdField = page.querySelector('[data-order-create-media-id]');
    const mediaTable = page.querySelector('[data-order-create-media-table]');
    const mediaStatus = page.querySelector('[data-order-create-media-status]');
    const mediaPrev = page.querySelector('[data-order-create-media-prev]');
    const mediaNext = page.querySelector('[data-order-create-media-next]');
    const mediaPageLabel = page.querySelector('[data-order-create-media-page]');
    const { role } = getUserContext();
    const isWarehouse = role === 'Warehouse';
    const minCustomerQuery = 2;
    const mediaPageSize = 5;

    const shipmentId = page.getAttribute('data-shipment-id');
    const shipmentNumber = page.getAttribute('data-shipment-number');
    const presetCollectionId = page.getAttribute('data-collection-id');
    let customerSelectize = null;
    let shipmentOriginCountryId = null;
    let shipmentDefaultRates = { kg: null, cbm: null };
    let lastAppliedRateKg = null;
    let lastAppliedRateCbm = null;
    let mediaPage = 0;
    let mediaData = [];

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

    const showMediaNotice = (message, type = 'error') => {
        if (!mediaStatus) {
            showNotice(message, type);
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        mediaStatus.appendChild(notice);
        setTimeout(() => notice.remove(), 7000);
    };

    const setTrackingPrintState = (enabled) => {
        if (trackingPrintButton) {
            trackingPrintButton.disabled = !enabled;
        }
    };

    const openLabelPrint = (trackingNumber = '', orderId = '') => {
        const params = new URLSearchParams();
        if (trackingNumber) {
            params.append('tracking_number', trackingNumber);
        } else if (orderId) {
            params.append('order_id', orderId);
        } else {
            showNotice('Tracking number is required to print a label.', 'error');
            return;
        }
        window.open(`${window.APP_BASE}/views/internal/order_label_print.php?${params.toString()}`, '_blank', 'noopener');
    };

    const updateMediaPager = () => {
        if (mediaPrev) {
            mediaPrev.disabled = mediaPage === 0;
        }
        if (mediaNext) {
            mediaNext.disabled = mediaData.length <= (mediaPage + 1) * mediaPageSize;
        }
        if (mediaPageLabel) {
            mediaPageLabel.textContent = `Page ${mediaPage + 1}`;
        }
    };

    const renderMediaTable = () => {
        if (!mediaTable) {
            return;
        }
        if (!mediaData.length) {
            mediaTable.innerHTML = '<tr><td colspan="5" class="muted">No attachments yet.</td></tr>';
            updateMediaPager();
            return;
        }
        const rows = mediaData.slice(mediaPage * mediaPageSize, mediaPage * mediaPageSize + mediaPageSize);
        mediaTable.innerHTML = rows
            .map((att) => {
                const downloadUrl =
                    att.download_url || `${window.APP_BASE}/api/attachments/download.php?id=${att.id}`;
                return `<tr>
                    <td>${att.title || att.original_name || '-'}</td>
                    <td>${att.mime_type || '-'}</td>
                    <td>${att.created_at || '-'}</td>
                    <td><a class="text-link" href="${downloadUrl}">Download</a></td>
                    <td><button class="button ghost small" type="button" data-attachment-delete data-attachment-id="${att.id}">Delete</button></td>
                </tr>`;
            })
            .join('');
        updateMediaPager();
    };

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
                    await loadMedia(orderId);
                } catch (error) {
                    showMediaNotice(`Delete failed: ${error.message}`, 'error');
                }
            });
        });
    };

    const loadMedia = async (orderId) => {
        if (!mediaTable) {
            return;
        }
        mediaTable.innerHTML = '<tr><td colspan="5" class="muted">Loading attachments...</td></tr>';
        try {
            const data = await fetchJson(
                `${window.APP_BASE}/api/attachments/list.php?entity_type=order&entity_id=${encodeURIComponent(
                    String(orderId)
                )}`
            );
            mediaData = data.data || [];
            mediaPage = 0;
            renderMediaTable();
            bindMediaDeletes(orderId);
        } catch (error) {
            mediaData = [];
            renderMediaTable();
            showMediaNotice(`Attachments load failed: ${error.message}`, 'error');
        }
    };

    const openMediaPanel = (orderId, trackingLabel = '') => {
        if (mediaPanel) {
            mediaPanel.classList.remove('is-hidden');
        }
        if (mediaIdField) {
            mediaIdField.value = String(orderId);
        }
        if (mediaTitle) {
            const label = trackingLabel ? `Order ${trackingLabel}` : `Order #${orderId}`;
            mediaTitle.textContent = `Attachments for ${label}.`;
        }
        loadMedia(orderId);
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

    const buildCustomerLabel = (customer) => {
        const phoneValue = customer.phone || customer.portal_phone || '';
        const phone = phoneValue ? ` - ${phoneValue}` : '';
        const countryLabel = customer.profile_country_name ? ` | ${customer.profile_country_name}` : '';
        const nameLabel = customer.name || 'Customer';
        const codeLabel = customer.code ? ` (${customer.code})` : '';
        return `${nameLabel}${codeLabel}${countryLabel}${phone}`;
    };

    const syncCustomerBranchByValue = (value = '') => {
        if (!subBranchDisplay) {
            return;
        }
        const normalized = Array.isArray(value) ? value[0] : value;
        if (!normalized) {
            subBranchDisplay.value = 'Select customer first';
            return;
        }
        const key = String(normalized);
        const option = customerSelectize?.options?.[key];
        if (!option) {
            subBranchDisplay.value = '';
            return;
        }
        const branchId = option.subBranchId ? String(option.subBranchId) : '';
        if (!branchId) {
            subBranchDisplay.value = 'No sub branch assigned';
            return;
        }
        subBranchDisplay.value = option.subBranchName || `Branch #${branchId}`;
    };

    const loadCustomerOptions = async (query, callback) => {
        const trimmedQuery = query.trim();
        if (trimmedQuery.length < minCustomerQuery) {
            callback([]);
            return;
        }
        try {
            const params = new URLSearchParams({ limit: '100' });
            params.append('q', trimmedQuery);
            if (shipmentOriginCountryId) {
                params.append('profile_country_id', shipmentOriginCountryId);
            }
            const data = await fetchJson(`${window.APP_BASE}/api/customers/list.php?${params.toString()}`);
            const options = (data.data || []).map((customer) => ({
                value: String(customer.id),
                text: buildCustomerLabel(customer),
                subBranchId: customer.sub_branch_id ?? '',
                subBranchName: customer.sub_branch_name ?? '',
            }));
            callback(options);
        } catch (error) {
            showNotice(`Customers load failed: ${error.message}`, 'error');
            callback([]);
        }
    };

    const initCustomerSelect = () => {
        if (!customerSelect) {
            return;
        }
        if (!window.TomSelect) {
            customerSelect.addEventListener('change', () => {
                syncCustomerBranchByValue(customerSelect.value);
                if (customerSelect.value) {
                    focusWeightField();
                }
            });
            return;
        }
        if (customerSelectize) {
            return;
        }
        customerSelectize = new TomSelect(customerSelect, {
            valueField: 'value',
            labelField: 'text',
            searchField: ['text'],
            maxItems: 1,
            create: false,
            allowEmptyOption: true,
            closeAfterSelect: true,
            placeholder: `Type at least ${minCustomerQuery} characters to search.`,
            shouldLoad: (query) => query.length >= minCustomerQuery,
            load: (query, callback) => {
                loadCustomerOptions(query, callback);
            },
            onChange: (value) => {
                syncCustomerBranchByValue(value);
                if (value) {
                    focusWeightField();
                }
            },
            render: {
                no_results: (data, escape) => {
                    const hint =
                        data.query.length < minCustomerQuery
                            ? `Type at least ${minCustomerQuery} characters to search.`
                            : 'No matching customers.';
                    return `<div class="no-results">${escape(hint)}</div>`;
                },
            },
        });
    };

    const getWeightMode = () => {
        const selected = page.querySelector('[data-weight-type]:checked');
        return selected ? selected.value : 'actual';
    };

    const applyDefaultRates = () => {
        if (!rateKgInput && !rateCbmInput) {
            return;
        }
        const applyRate = (input, nextRate, lastRate) => {
            if (!input) {
                return lastRate;
            }
            if (nextRate === null || nextRate === undefined || Number.isNaN(Number(nextRate))) {
                return lastRate;
            }
            const currentValue = input.value.trim();
            const currentNumber = currentValue === '' ? null : Number(currentValue);
            if (currentNumber === null || (lastRate !== null && currentNumber === lastRate)) {
                input.value = nextRate;
                return Number(nextRate);
            }
            return lastRate;
        };
        lastAppliedRateKg = applyRate(rateKgInput, shipmentDefaultRates.kg, lastAppliedRateKg);
        lastAppliedRateCbm = applyRate(rateCbmInput, shipmentDefaultRates.cbm, lastAppliedRateCbm);
    };

    const applyWeightMode = () => {
        const mode = getWeightMode();
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
        applyDefaultRates();
    };

    const focusWeightField = () => {
        if (!createForm) {
            return;
        }
        const target =
            getWeightMode() === 'volumetric'
                ? createForm.querySelector('[name="w"]')
                : createForm.querySelector('[name="actual_weight"]');
        if (target) {
            target.focus();
        }
    };

    if (createForm) {
        createForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const selectedCustomer = customerSelectize ? customerSelectize.getValue() : customerSelect?.value;
            if (selectedCustomer) {
                syncCustomerBranchByValue(selectedCustomer);
            }
            if (!selectedCustomer) {
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
                const trackingLabel = payload.tracking_number ? `#${payload.tracking_number}` : '';
                if (data.id && mediaPanel) {
                    openMediaPanel(data.id, trackingLabel);
                }
                createForm.reset();
                if (collectionSelect) {
                    collectionSelect.value = lastCollection;
                }
                if (customerSelectize) {
                    customerSelectize.clear(true);
                    customerSelectize.clearOptions();
                }
                if (customerSelect) {
                    customerSelect.value = '';
                }
                syncCustomerBranchByValue('');
                applyWeightMode();
                if (trackingInput) {
                    trackingInput.focus();
                }
                setTrackingPrintState(false);
            } catch (error) {
                showNotice(`Create failed: ${error.message}`, 'error');
            }
        });
    }

    if (mediaPrev) {
        mediaPrev.addEventListener('click', () => {
            if (mediaPage === 0) {
                return;
            }
            mediaPage -= 1;
            renderMediaTable();
        });
    }

    if (mediaNext) {
        mediaNext.addEventListener('click', () => {
            if (mediaData.length <= (mediaPage + 1) * mediaPageSize) {
                return;
            }
            mediaPage += 1;
            renderMediaTable();
        });
    }

    if (mediaForm) {
        mediaForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const orderId = mediaIdField ? mediaIdField.value : '';
            if (!orderId) {
                showMediaNotice('Create an order first.', 'error');
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
                await loadMedia(orderId);
            } catch (error) {
                showMediaNotice(`Upload failed: ${error.message}`, 'error');
            }
        });
    }

    initCustomerSelect();
    resolveShipmentId().then((resolvedId) => {
        if (resolvedId) {
            loadCollections(resolvedId);
            fetchJson(`${window.APP_BASE}/api/shipments/view.php?shipment_id=${encodeURIComponent(resolvedId)}`)
                .then((data) => {
                    if (data.shipment) {
                        shipmentDefaultRates = {
                            kg: data.shipment.default_rate_kg ?? null,
                            cbm: data.shipment.default_rate_cbm ?? null,
                        };
                        applyDefaultRates();
                    }
                    shipmentOriginCountryId = data.shipment?.origin_country_id || null;
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
    weightTypeInputs.forEach((input) => {
        input.addEventListener('change', applyWeightMode);
    });
    if (subBranchDisplay) {
        subBranchDisplay.value = 'Select customer first';
    }
    applyWeightMode();

    const focusCustomerSelect = () => {
        if (customerSelectize) {
            customerSelectize.focus();
            return;
        }
        if (customerSelect) {
            customerSelect.focus();
        }
    };

    if (trackingInput) {
        trackingInput.focus();
        trackingInput.addEventListener('input', () => {
            setTrackingPrintState(Boolean(trackingInput.value.trim()));
        });
        trackingInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === 'Tab') {
                event.preventDefault();
                focusCustomerSelect();
            }
        });
    }

    if (trackingGenerateButton) {
        trackingGenerateButton.addEventListener('click', async () => {
            trackingGenerateButton.disabled = true;
            try {
                const data = await fetchJson(`${window.APP_BASE}/api/orders/generate_tracking.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({}),
                });
                if (trackingInput) {
                    trackingInput.value = data.tracking_number || '';
                }
                setTrackingPrintState(Boolean(data.tracking_number));
                showNotice('Tracking number generated.', 'success');
            } catch (error) {
                showNotice(`Generate failed: ${error.message}`, 'error');
            } finally {
                trackingGenerateButton.disabled = false;
            }
        });
    }

    if (trackingPrintButton) {
        trackingPrintButton.addEventListener('click', () => {
            const trackingNumber = trackingInput ? trackingInput.value.trim() : '';
            if (!trackingNumber) {
                showNotice('Enter a tracking number first.', 'error');
                return;
            }
            openLabelPrint(trackingNumber);
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
    const profilesDrawer = page.querySelector('[data-customer-profiles-drawer]');
    const profilesTable = page.querySelector('[data-customer-profiles-table]');
    const profilesTitle = page.querySelector('[data-customer-profiles-title]');
    const profilesSubtitle = page.querySelector('[data-customer-profiles-subtitle]');
    const profilesCloseButtons = page.querySelectorAll('[data-customer-profiles-close]');

    const { role, branchId } = getUserContext();
    const fullAccess = hasFullCustomerAccess(role);
    const canCreateProfile = role === 'Admin';
    const canOpenProfile = role !== 'Warehouse';
    const canEditProfile = role === 'Admin';
    const showBalance = role !== 'Warehouse';
    const limit = 5;
    let offset = 0;
    let lastFilters = {};
    const columnCount =
        tableBody?.closest('table')?.querySelectorAll('thead th').length || (showBalance ? 8 : 7);
    const profileColumnCount = 5;

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

    const openProfilesDrawer = () => {
        if (!profilesDrawer) {
            return;
        }
        profilesDrawer.classList.add('is-open');
        document.body.classList.add('drawer-open');
    };

    const closeProfilesDrawer = () => {
        if (!profilesDrawer) {
            return;
        }
        profilesDrawer.classList.remove('is-open');
        document.body.classList.remove('drawer-open');
        if (profilesTable) {
            profilesTable.innerHTML = `<tr><td colspan="${profileColumnCount}" class="muted">Select a customer to view profiles.</td></tr>`;
        }
    };

    if (profilesCloseButtons.length) {
        profilesCloseButtons.forEach((button) => button.addEventListener('click', closeProfilesDrawer));
    }

    const renderProfiles = (rows) => {
        if (!profilesTable) {
            return;
        }
        if (!rows.length) {
            profilesTable.innerHTML = `<tr><td colspan="${profileColumnCount}" class="muted">No profiles found.</td></tr>`;
            return;
        }
        profilesTable.innerHTML = rows
            .map((profile) => {
                const createdLabel = profile.created_at ? String(profile.created_at).slice(0, 10) : '-';
                const ordersCount = profile.orders_count ?? 0;
                const actions = [];
                if (canOpenProfile) {
                    actions.push(
                        `<a class="text-link" href="${window.APP_BASE}/views/internal/customer_view?id=${profile.id}">Open</a>`
                    );
                }
                if (canEditProfile) {
                    actions.push(
                        `<a class="text-link" href="${window.APP_BASE}/views/internal/customer_edit?id=${profile.id}">Edit code</a>`
                    );
                    actions.push(
                        `<a class="text-link" href="${window.APP_BASE}/views/internal/customer_info_edit?id=${profile.id}">Edit info</a>`
                    );
                }
                const actionsHtml = actions.length ? actions.join(' | ') : '-';
                return `<tr>
                    <td>${escapeHtml(profile.profile_country_name || '-')}</td>
                    <td>${escapeHtml(String(ordersCount))}</td>
                    <td>${escapeHtml(createdLabel)}</td>
                    <td>${escapeHtml(profile.code || '-')}</td>
                    <td>${actionsHtml}</td>
                </tr>`;
            })
            .join('');
    };

    const loadProfiles = async (query) => {
        if (!profilesTable) {
            return;
        }
        profilesTable.innerHTML = `<tr><td colspan="${profileColumnCount}" class="muted">Loading profiles...</td></tr>`;
        const params = new URLSearchParams(query);
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/customers/profiles.php?${params.toString()}`);
            renderProfiles(data.data || []);
        } catch (error) {
            profilesTable.innerHTML = `<tr><td colspan="${profileColumnCount}" class="muted">Unable to load profiles.</td></tr>`;
            showNotice(`Profiles load failed: ${error.message}`, 'error');
        }
    };

      const buildProfileLink = (row) => {
          if (!canCreateProfile || !row.id) {
              return '';
          }
          const params = new URLSearchParams();
          params.set('customer_id', String(row.id));
          params.set('add_profile', '1');
          return `${window.APP_BASE}/views/internal/customer_create?${params.toString()}`;
      };

    const renderRows = (rows) => {
        if (!tableBody) {
            return;
        }
        if (!rows.length) {
            tableBody.innerHTML = `<tr><td colspan="${columnCount}" class="muted">No customers found.</td></tr>`;
            return;
        }
        tableBody.innerHTML = rows
            .map((row) => {
                const nameLabel = row.customer_name
                    ? row.customer_code
                        ? `${row.customer_name} (${row.customer_code})`
                        : row.customer_name
                    : row.portal_username || '-';
                const portalLabel = row.portal_username || '-';
                const phoneLabel = row.portal_phone || row.customer_phone || '-';
                const branchLabel = row.sub_branch_name || '-';
                const profilesLabel = row.profile_count ? String(row.profile_count) : '0';
                const countriesLabel = row.profile_countries || '-';
                const balanceLabel = formatAmount(row.balance);
                const subtitleParts = [];
                if (row.portal_username) {
                    subtitleParts.push(row.portal_username);
                }
                if (phoneLabel && phoneLabel !== '-') {
                    subtitleParts.push(phoneLabel);
                }
                subtitleParts.push(`${profilesLabel} profiles`);
                if (countriesLabel && countriesLabel !== '-') {
                    subtitleParts.push(countriesLabel);
                }
                const subtitle = subtitleParts.join(' | ');
                const actionParts = [];
                actionParts.push(
                    `<button class="button ghost small" type="button" data-view-profiles data-account-id="${row.account_id ?? ''}" data-customer-id="${row.primary_customer_id ?? ''}" data-customer-label="${encodeURIComponent(nameLabel)}" data-profiles-subtitle="${encodeURIComponent(subtitle)}">View profiles</button>`
                );
                if (canOpenProfile && row.primary_customer_id) {
                    actionParts.push(
                        `<a class="text-link" href="${window.APP_BASE}/views/internal/customer_view?id=${row.primary_customer_id}">Open</a>`
                    );
                }
                const addProfileLink = buildProfileLink(row);
                if (addProfileLink) {
                    actionParts.push(`<a class="text-link" href="${addProfileLink}">Add profile</a>`);
                }
                const balanceCell = showBalance ? `<td>${escapeHtml(balanceLabel)}</td>` : '';
                return `<tr>
                    <td>${escapeHtml(nameLabel)}</td>
                    <td>${escapeHtml(portalLabel)}</td>
                    <td>${escapeHtml(phoneLabel)}</td>
                    <td>${escapeHtml(branchLabel)}</td>
                    <td>${escapeHtml(profilesLabel)}</td>
                    <td>${escapeHtml(countriesLabel)}</td>
                    ${balanceCell}
                    <td>${actionParts.join(' | ')}</td>
                </tr>`;
            })
            .join('');

        tableBody.querySelectorAll('[data-view-profiles]').forEach((button) => {
            button.addEventListener('click', async () => {
                const accountId = button.getAttribute('data-account-id') || '';
                const customerId = button.getAttribute('data-customer-id') || '';
                const label = decodeURIComponent(button.getAttribute('data-customer-label') || 'Customer profiles');
                const subtitle = decodeURIComponent(button.getAttribute('data-profiles-subtitle') || '');
                if (profilesTitle) {
                    profilesTitle.textContent = label;
                }
                if (profilesSubtitle) {
                    profilesSubtitle.textContent = subtitle || 'Profile details.';
                }
                if (accountId) {
                    await loadProfiles({ account_id: accountId });
                } else if (customerId) {
                    await loadProfiles({ customer_id: customerId });
                } else {
                    renderProfiles([]);
                }
                openProfilesDrawer();
            });
        });
    };

    const loadCustomers = async (filters = {}) => {
        if (tableBody) {
            tableBody.innerHTML = `<tr><td colspan="${columnCount}" class="muted">Loading customers...</td></tr>`;
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
            const data = await fetchJson(`${window.APP_BASE}/api/customers/accounts.php?${params.toString()}`);
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
            if (!fullAccess && role !== 'Warehouse' && branchId) {
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
                if (!fullAccess && role !== 'Warehouse' && branchId) {
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

function initCustomerBalancesPage() {
    const page = document.querySelector('[data-customer-balances-page]');
    if (!page) {
        return;
    }

    const filterForm = page.querySelector('[data-customer-balances-filter]');
    const branchSelect = page.querySelector('[data-customer-balances-branch]');
    const refreshButton = page.querySelector('[data-customer-balances-refresh]');
    const tableBody = page.querySelector('[data-customer-balances-table]');
    const statusStack = page.querySelector('[data-customer-balances-status]');
    const prevButton = page.querySelector('[data-customer-balances-prev]');
    const nextButton = page.querySelector('[data-customer-balances-next]');
    const pageLabel = page.querySelector('[data-customer-balances-page]');
    const { role } = getUserContext();
    const pageSize = 50;
    let offset = 0;
    let lastFilters = {};

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

    const updatePager = (rows) => {
        if (prevButton) {
            prevButton.disabled = offset === 0;
        }
        if (nextButton) {
            nextButton.disabled = rows.length < pageSize;
        }
        if (pageLabel) {
            pageLabel.textContent = `Page ${Math.floor(offset / pageSize) + 1}`;
        }
    };

    const renderRows = (rows) => {
        if (!tableBody) {
            return;
        }
        if (!rows.length) {
            tableBody.innerHTML = '<tr><td colspan="6" class="muted">No balances found.</td></tr>';
            updatePager(rows);
            return;
        }
        tableBody.innerHTML = rows
            .map((row) => {
                const nameLabel = row.customer_name
                    ? row.customer_code
                        ? `${row.customer_name} (${row.customer_code})`
                        : row.customer_name
                    : '-';
                const branchLabel = row.sub_branch_name || '-';
                const countriesLabel = row.profile_countries || '-';
                const profilesLabel = row.profile_count ? String(row.profile_count) : '0';
                const balanceValue = Number(row.balance ?? 0);
                const balanceLabel = formatAmount(balanceValue);
                const balanceClass = balanceValue < 0 ? 'amount-negative' : balanceValue > 0 ? 'amount-positive' : '';
                const viewLink = row.primary_customer_id
                    ? `<a class="text-link" href="${window.APP_BASE}/views/internal/customer_view?id=${row.primary_customer_id}">View</a>`
                    : '-';
                return `<tr>
                    <td>${escapeHtml(nameLabel)}</td>
                    <td>${escapeHtml(branchLabel)}</td>
                    <td>${escapeHtml(profilesLabel)}</td>
                    <td>${escapeHtml(countriesLabel)}</td>
                    <td class="${balanceClass}">${escapeHtml(balanceLabel)}</td>
                    <td>${viewLink}</td>
                </tr>`;
            })
            .join('');
        updatePager(rows);
    };

    const loadBranches = async () => {
        if (!branchSelect || !['Admin', 'Owner'].includes(role || '')) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/branches/list.php?type=sub&limit=200`);
            const rows = data.data || [];
            branchSelect.innerHTML = '<option value="">All branches</option>';
            rows.forEach((branch) => {
                const option = document.createElement('option');
                option.value = branch.id;
                option.textContent = branch.name || `Branch #${branch.id}`;
                branchSelect.appendChild(option);
            });
        } catch (error) {
            showNotice(error.message || 'Failed to load branches.');
        }
    };

    const loadBalances = async (filters = {}) => {
        if (!tableBody) {
            return;
        }
        tableBody.innerHTML = '<tr><td colspan="6" class="muted">Loading balances...</td></tr>';
        const params = new URLSearchParams();
        params.set('limit', String(pageSize));
        params.set('offset', String(offset));
        params.set('non_zero', '1');
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== null && value !== undefined && String(value).trim() !== '') {
                params.set(key, String(value));
            }
        });
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/customers/accounts.php?${params.toString()}`);
            renderRows(data.data || []);
        } catch (error) {
            showNotice(error.message || 'Failed to load balances.');
            if (tableBody) {
                tableBody.innerHTML = '<tr><td colspan="6" class="muted">Failed to load balances.</td></tr>';
            }
        }
    };

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(filterForm);
            offset = 0;
            lastFilters = Object.fromEntries(formData.entries());
            loadBalances(lastFilters);
        });
    }

    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            if (filterForm) {
                filterForm.reset();
            }
            offset = 0;
            lastFilters = {};
            loadBalances(lastFilters);
        });
    }

    if (prevButton) {
        prevButton.addEventListener('click', () => {
            if (offset === 0) {
                return;
            }
            offset = Math.max(0, offset - pageSize);
            loadBalances(lastFilters);
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => {
            offset += pageSize;
            loadBalances(lastFilters);
        });
    }

    loadBranches();
    loadBalances(lastFilters);
}

function initBalancesPage() {
    const page = document.querySelector('[data-balances-page]');
    if (!page) {
        return;
    }

    const { role } = getUserContext();
    const canRecordPayment = ['Admin', 'Owner'].includes(role || '');
    const filterForm = page.querySelector('[data-balances-filter]');
    const countrySelect = page.querySelector('[data-balances-country]');
    const refreshButton = page.querySelector('[data-balances-refresh]');
    const tableBody = page.querySelector('[data-balances-table]');
    const statusStack = page.querySelector('[data-balances-status]');
    const prevButton = page.querySelector('[data-balances-prev]');
    const nextButton = page.querySelector('[data-balances-next]');
    const pageLabel = page.querySelector('[data-balances-page]');
    const pageSize = 25;
    let offset = 0;
    let lastFilters = {};

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

    const updatePager = (rows) => {
        if (prevButton) {
            prevButton.disabled = offset === 0;
        }
        if (nextButton) {
            nextButton.disabled = rows.length < pageSize;
        }
        if (pageLabel) {
            pageLabel.textContent = `Page ${Math.floor(offset / pageSize) + 1}`;
        }
    };

    const renderCustomerDetails = (cell, customers) => {
        if (!customers.length) {
            cell.innerHTML = '<div class="muted">No customer balances found.</div>';
            return;
        }
        const rowsHtml = customers
            .map((row) => {
                const nameLabel = row.customer_name
                    ? row.customer_code
                        ? `${row.customer_name} (${row.customer_code})`
                        : row.customer_name
                    : '-';
                const countriesLabel = row.profile_countries || '-';
                const profilesLabel = row.profile_count ? String(row.profile_count) : '0';
                const balanceValue = Number(row.balance ?? 0);
                const balanceLabel = formatAmount(balanceValue);
                const balanceClass = balanceValue < 0 ? 'amount-negative' : balanceValue > 0 ? 'amount-positive' : '';
                const viewLink = row.primary_customer_id
                    ? `<a class="text-link" href="${window.APP_BASE}/views/internal/customer_view?id=${row.primary_customer_id}">View</a>`
                    : '-';
                return `<tr>
                    <td>${escapeHtml(nameLabel)}</td>
                    <td>${escapeHtml(profilesLabel)}</td>
                    <td>${escapeHtml(countriesLabel)}</td>
                    <td class="${balanceClass}">${escapeHtml(balanceLabel)}</td>
                    <td>${viewLink}</td>
                </tr>`;
            })
            .join('');
        cell.innerHTML = `
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Profiles</th>
                            <th>Countries</th>
                            <th>Balance</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml}
                    </tbody>
                </table>
            </div>`;
    };

    const attachRowHandlers = () => {
        if (!tableBody) {
            return;
        }
        tableBody.querySelectorAll('[data-branch-balances-view]').forEach((button) => {
            button.addEventListener('click', async () => {
                const branchId = button.getAttribute('data-branch-id');
                if (!branchId) {
                    return;
                }
                const row = button.closest('tr');
                if (!row) {
                    return;
                }
                const nextRow = row.nextElementSibling;
                if (nextRow && nextRow.hasAttribute('data-branch-balances-details')) {
                    nextRow.remove();
                    return;
                }

                const detailsRow = document.createElement('tr');
                detailsRow.setAttribute('data-branch-balances-details', '1');
                const detailsCell = document.createElement('td');
                detailsCell.colSpan = 7;
                detailsCell.innerHTML = '<div class="muted">Loading customer balances...</div>';
                detailsRow.appendChild(detailsCell);
                row.insertAdjacentElement('afterend', detailsRow);

                try {
                    const params = new URLSearchParams({
                        sub_branch_id: branchId,
                        limit: '200',
                        non_zero: '1',
                    });
                    const data = await fetchJson(`${window.APP_BASE}/api/customers/accounts.php?${params.toString()}`);
                    renderCustomerDetails(detailsCell, data.data || []);
                } catch (error) {
                    detailsCell.innerHTML = '<div class="muted">Failed to load customer balances.</div>';
                    showNotice(error.message || 'Failed to load customer balances.');
                }
            });
        });
    };

    const renderBranches = (rows) => {
        if (!tableBody) {
            return;
        }
        if (!rows.length) {
            tableBody.innerHTML = '<tr><td colspan="7" class="muted">No branches found.</td></tr>';
            updatePager(rows);
            return;
        }
        tableBody.innerHTML = rows
            .map((row) => {
                const branchLabel = row.name || `Branch #${row.id}`;
                const balanceValue = Number(row.balance ?? 0);
                const balanceLabel = formatAmount(balanceValue);
                const balanceClass = balanceValue < 0 ? 'amount-negative' : balanceValue > 0 ? 'amount-positive' : '';
                const dueValue = Number(row.customer_due_total ?? 0);
                const dueLabel = formatAmount(dueValue);
                const dueClass = dueValue > 0 ? 'amount-positive' : '';
                const countLabel = row.customer_balance_count ? String(row.customer_balance_count) : '0';
                const canPay = canRecordPayment && row.type === 'sub';
                const paymentLink = canPay
                    ? `<a class="text-link" href="${window.APP_BASE}/views/internal/branches?payment=1&branch_id=${row.id}">Record payment</a>`
                    : '-';
                return `<tr>
                    <td>${escapeHtml(branchLabel)}</td>
                    <td>${escapeHtml(row.type || '-')}</td>
                    <td>${escapeHtml(row.country_name || '-')}</td>
                    <td class="${balanceClass}">${escapeHtml(balanceLabel)}</td>
                    <td class="${dueClass}">${escapeHtml(dueLabel)}</td>
                    <td>${escapeHtml(countLabel)}</td>
                    <td>
                        <button class="button ghost small" type="button" data-branch-balances-view data-branch-id="${row.id}">
                            View
                        </button>
                        <span class="muted">|</span>
                        ${paymentLink}
                    </td>
                </tr>`;
            })
            .join('');
        attachRowHandlers();
        updatePager(rows);
    };

    const loadCountries = async () => {
        if (!countrySelect) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/countries/list.php?limit=200`);
            const rows = data.data || [];
            countrySelect.innerHTML = '<option value="">All countries</option>';
            rows.forEach((country) => {
                const option = document.createElement('option');
                option.value = country.id;
                option.textContent = country.name || `Country #${country.id}`;
                countrySelect.appendChild(option);
            });
        } catch (error) {
            showNotice(error.message || 'Failed to load countries.');
        }
    };

    const loadBranches = async (filters = {}) => {
        if (!tableBody) {
            return;
        }
        tableBody.innerHTML = '<tr><td colspan="7" class="muted">Loading branches...</td></tr>';
        const params = new URLSearchParams();
        params.set('limit', String(pageSize));
        params.set('offset', String(offset));
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== null && value !== undefined && String(value).trim() !== '') {
                params.set(key, String(value));
            }
        });
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/branches/list.php?${params.toString()}`);
            renderBranches(data.data || []);
        } catch (error) {
            showNotice(error.message || 'Failed to load branches.');
            if (tableBody) {
                tableBody.innerHTML = '<tr><td colspan="7" class="muted">Failed to load branches.</td></tr>';
            }
        }
    };

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(filterForm);
            offset = 0;
            lastFilters = Object.fromEntries(formData.entries());
            loadBranches(lastFilters);
        });
    }

    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            if (filterForm) {
                filterForm.reset();
            }
            offset = 0;
            lastFilters = {};
            loadBranches(lastFilters);
        });
    }

    if (prevButton) {
        prevButton.addEventListener('click', () => {
            if (offset === 0) {
                return;
            }
            offset = Math.max(0, offset - pageSize);
            loadBranches(lastFilters);
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => {
            offset += pageSize;
            loadBranches(lastFilters);
        });
    }

    loadCountries();
    loadBranches(lastFilters);
}

function initBranchOverviewPage() {
    const page = document.querySelector('[data-branch-overview-page]');
    if (!page) {
        return;
    }

    const branchId = page.getAttribute('data-branch-id') || '';
    const branchIdValue = Number(branchId);
    const detailEls = page.querySelectorAll('[data-branch-detail]');
    const customersTable = page.querySelector('[data-branch-overview-customers]');
    const statusStack = page.querySelector('[data-branch-overview-status]');
    const prevButton = page.querySelector('[data-branch-overview-prev]');
    const nextButton = page.querySelector('[data-branch-overview-next]');
    const pageLabel = page.querySelector('[data-branch-overview-page]');
    const pageSize = 25;
    let offset = 0;
    const detailMap = {};

    detailEls.forEach((el) => {
        const key = el.getAttribute('data-branch-detail');
        if (key) {
            detailMap[key] = el;
        }
    });

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

    const updatePager = (rows) => {
        if (prevButton) {
            prevButton.disabled = offset === 0;
        }
        if (nextButton) {
            nextButton.disabled = rows.length < pageSize;
        }
        if (pageLabel) {
            pageLabel.textContent = `Page ${Math.floor(offset / pageSize) + 1}`;
        }
    };

    const renderCustomers = (rows) => {
        if (!customersTable) {
            return;
        }
        if (!rows.length) {
            customersTable.innerHTML = '<tr><td colspan="5" class="muted">No customer balances found.</td></tr>';
            updatePager(rows);
            return;
        }
        customersTable.innerHTML = rows
            .map((row) => {
                const nameLabel = row.customer_name
                    ? row.customer_code
                        ? `${row.customer_name} (${row.customer_code})`
                        : row.customer_name
                    : '-';
                const countriesLabel = row.profile_countries || '-';
                const profilesLabel = row.profile_count ? String(row.profile_count) : '0';
                const balanceValue = Number(row.balance ?? 0);
                const balanceLabel = formatAmount(balanceValue);
                const balanceClass = balanceValue < 0 ? 'amount-negative' : balanceValue > 0 ? 'amount-positive' : '';
                const viewLink = row.primary_customer_id
                    ? `<a class="text-link" href="${window.APP_BASE}/views/internal/customer_view?id=${row.primary_customer_id}">View</a>`
                    : '-';
                return `<tr>
                    <td>${escapeHtml(nameLabel)}</td>
                    <td>${escapeHtml(profilesLabel)}</td>
                    <td>${escapeHtml(countriesLabel)}</td>
                    <td class="${balanceClass}">${escapeHtml(balanceLabel)}</td>
                    <td>${viewLink}</td>
                </tr>`;
            })
            .join('');
        updatePager(rows);
    };

    const loadSummary = async () => {
        if (!Number.isFinite(branchIdValue) || branchIdValue <= 0) {
            showNotice('Branch scope is required.');
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/branches/summary.php?branch_id=${branchIdValue}`);
            const branch = data.data?.branch || {};
            const stats = data.data?.stats || {};
            const fields = {
                name: branch.name || '--',
                type: branch.type || '--',
                country_name: branch.country_name || '--',
                parent_branch_name: branch.parent_branch_name || '--',
                phone: branch.phone || '--',
                address: branch.address || '--',
                balance: formatAmount(branch.balance ?? 0),
                due_total: formatAmount(stats.due_total ?? 0),
                credit_total: formatAmount(stats.credit_total ?? 0),
                profile_count: stats.profile_count ?? '0',
                account_count: stats.account_count ?? '0',
                balance_count: stats.balance_count ?? '0',
            };
            Object.entries(fields).forEach(([key, value]) => {
                if (detailMap[key]) {
                    detailMap[key].textContent = String(value);
                }
            });
        } catch (error) {
            showNotice(error.message || 'Failed to load branch overview.');
        }
    };

    const loadCustomers = async () => {
        if (!customersTable) {
            return;
        }
        customersTable.innerHTML = '<tr><td colspan="5" class="muted">Loading customers...</td></tr>';
        if (!Number.isFinite(branchIdValue) || branchIdValue <= 0) {
            customersTable.innerHTML = '<tr><td colspan="5" class="muted">Branch scope is required.</td></tr>';
            showNotice('Branch scope is required.');
            return;
        }
        const params = new URLSearchParams({
            sub_branch_id: String(branchIdValue),
            limit: String(pageSize),
            offset: String(offset),
            non_zero: '1',
        });
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/customers/accounts.php?${params.toString()}`);
            renderCustomers(data.data || []);
        } catch (error) {
            showNotice(error.message || 'Failed to load customers.');
            customersTable.innerHTML = '<tr><td colspan="5" class="muted">Failed to load customers.</td></tr>';
        }
    };

    if (prevButton) {
        prevButton.addEventListener('click', () => {
            if (offset === 0) {
                return;
            }
            offset = Math.max(0, offset - pageSize);
            loadCustomers();
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => {
            offset += pageSize;
            loadCustomers();
        });
    }

    loadSummary();
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
    const userToggleField = page.querySelector('[data-staff-user-toggle-field]');
    const userToggle = page.querySelector('[data-staff-user-toggle]');
    const userFields = page.querySelectorAll('[data-staff-user-field]');
    const userNote = page.querySelector('[data-staff-user-note]');
    const userRoleSelect = page.querySelector('[data-staff-user-role]');
    const userUsernameInput = form ? form.querySelector('[name="user_username"]') : null;
    const userPasswordInput = form ? form.querySelector('[name="user_password"]') : null;
    const baseSalaryInput = form ? form.querySelector('[name="base_salary"]') : null;
    const drawerCloseButtons = page.querySelectorAll('[data-staff-drawer-close]');
    const canEdit = page.getAttribute('data-can-edit') === '1';
    const createMode = page.getAttribute('data-create-mode') === '1';

    const { role, branchId } = getUserContext();
    const fullAccess = hasFullCustomerAccess(role);
    const canManageUser = ['Admin', 'Owner'].includes(role || '');
    const limit = 5;
    let offset = 0;
    let lastFilters = {};
    let staffUserId = null;
    let pendingUserRoleId = '';

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

    const setUserFieldsVisible = (visible, hasUser) => {
        userFields.forEach((field) => field.classList.toggle('is-hidden', !visible));
        if (userUsernameInput) {
            userUsernameInput.required = visible;
        }
        if (userRoleSelect) {
            userRoleSelect.required = visible;
        }
        if (userPasswordInput) {
            userPasswordInput.required = visible && !hasUser;
        }
    };

    const resetUserFields = () => {
        staffUserId = null;
        pendingUserRoleId = '';
        if (userToggle) {
            userToggle.checked = false;
            userToggle.disabled = false;
        }
        if (userNote) {
            userNote.textContent = 'Optional: create a linked user account for staff access.';
        }
        if (userUsernameInput) {
            userUsernameInput.value = '';
        }
        if (userPasswordInput) {
            userPasswordInput.value = '';
        }
        if (userRoleSelect) {
            userRoleSelect.value = '';
        }
        setUserFieldsVisible(false, false);
    };

    const openDrawer = () => {
        if (!drawer) {
            return;
        }
        drawer.classList.add('is-open');
        document.body.classList.add('drawer-open');
        if (invoiceCurrencySelect && !invoiceCurrencySelect.value) {
            invoiceCurrencySelect.value = 'USD';
        }
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

    const loadRoles = async () => {
        if (!userRoleSelect) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/roles/list.php`);
            clearDynamicOptions(userRoleSelect);
            (data.data || []).forEach((role) => {
                const option = document.createElement('option');
                option.value = role.id;
                option.textContent = role.name;
                option.setAttribute('data-dynamic', 'true');
                userRoleSelect.appendChild(option);
            });
            if (pendingUserRoleId) {
                userRoleSelect.value = pendingUserRoleId;
            }
        } catch (error) {
            showFormNotice(`Roles load failed: ${error.message}`, 'error');
        }
    };

    const loadAccounts = async () => {
        if (!accountSelect) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/accounts/list.php?owner_type=admin&is_active=1`);
            clearDynamicOptions(accountSelect);
            (data.data || []).forEach((account) => {
                const option = document.createElement('option');
                option.value = account.id;
                const methodLabel = account.payment_method_name ? ` (${account.payment_method_name})` : '';
                option.textContent = `${account.name}${methodLabel}`;
                option.setAttribute('data-dynamic', 'true');
                accountSelect.appendChild(option);
            });
        } catch (error) {
            showNotice(`Accounts load failed: ${error.message}`, 'error');
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
        if (canManageUser) {
            staffUserId = staff.user_id ? String(staff.user_id) : null;
            const hasUser = Boolean(staffUserId);
            if (userToggle) {
                userToggle.checked = hasUser;
                userToggle.disabled = hasUser;
            }
            if (userNote) {
                userNote.textContent = hasUser
                    ? 'Linked login found. Update credentials below.'
                    : 'Optional: create a linked user account for staff access.';
            }
            if (userUsernameInput) {
                userUsernameInput.value = staff.user_username || '';
            }
            if (userRoleSelect) {
                pendingUserRoleId = staff.user_role_id ? String(staff.user_role_id) : '';
                userRoleSelect.value = pendingUserRoleId;
            }
            if (userPasswordInput) {
                userPasswordInput.value = '';
            }
            setUserFieldsVisible(hasUser, hasUser);
        }
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
        if (canManageUser) {
            resetUserFields();
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
                    const confirmed = await showConfirmDialog({
                        title: 'Delete staff',
                        message: 'Delete this staff member?',
                        confirmLabel: 'Delete'
                    });
                    if (!confirmed) {
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
    if (!canManageUser && userToggleField) {
        userToggleField.classList.add('is-hidden');
    }
    if (!canManageUser) {
        userFields.forEach((field) => field.classList.add('is-hidden'));
    }

    if (fullAccess) {
        loadBranches();
    }
    if (canManageUser) {
        loadRoles();
    }

    if (addButton) {
        addButton.addEventListener('click', () => openForCreate());
    }

    if (userToggle && canManageUser) {
        userToggle.addEventListener('change', () => {
            if (staffUserId) {
                return;
            }
            const enabled = userToggle.checked;
            if (!enabled) {
                resetUserFields();
                return;
            }
            if (userNote) {
                userNote.textContent = 'Provide a username, password, and role for this staff login.';
            }
            setUserFieldsVisible(true, false);
        });
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
            const wantsUser = Boolean(userToggle && userToggle.checked);
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
            if (!canManageUser) {
                delete payload.create_user;
                delete payload.user_username;
                delete payload.user_password;
                delete payload.user_role_id;
            } else {
                if (!wantsUser && !staffUserId) {
                    delete payload.create_user;
                    delete payload.user_username;
                    delete payload.user_password;
                    delete payload.user_role_id;
                } else if (!staffUserId && wantsUser) {
                    payload.create_user = '1';
                    if (!payload.user_username || !payload.user_password || !payload.user_role_id) {
                        showFormNotice('Username, password, and role are required for staff login.', 'error');
                        return;
                    }
                } else if (staffUserId) {
                    delete payload.create_user;
                    if (payload.user_password !== undefined && String(payload.user_password).trim() === '') {
                        delete payload.user_password;
                    }
                    if (payload.user_username !== undefined && String(payload.user_username).trim() === '') {
                        showFormNotice('Username cannot be empty.', 'error');
                        return;
                    }
                    if (payload.user_role_id !== undefined && String(payload.user_role_id).trim() === '') {
                        showFormNotice('Role is required for staff login.', 'error');
                        return;
                    }
                }
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

    if (createMode && canEdit) {
        openForCreate();
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
    const payForm = page.querySelector('[data-staff-pay-form]');
    const advanceForm = page.querySelector('[data-staff-advance-form]');
    const bonusForm = page.querySelector('[data-staff-bonus-form]');
    const salaryStatus = page.querySelector('[data-staff-salary-status]');
    const payStatus = page.querySelector('[data-staff-pay-status]');
    const advanceStatus = page.querySelector('[data-staff-advance-status]');
    const bonusStatus = page.querySelector('[data-staff-bonus-status]');
    const payFromSelect = page.querySelector('[data-staff-pay-from]');
    const advanceFromSelect = page.querySelector('[data-staff-advance-from]');
    const bonusFromSelect = page.querySelector('[data-staff-bonus-from]');
    const deleteButton = page.querySelector('[data-staff-delete]');
    const pageSize = 5;
    let expensesPage = 0;
    let expensesData = [];
    const accountMap = new Map();

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

    const renderAccountOptions = (select, accounts, placeholder) => {
        if (!select) {
            return;
        }
        select.innerHTML = `<option value="">${placeholder}</option>`;
        (accounts || []).forEach((account) => {
            const option = document.createElement('option');
            option.value = account.id;
            const methodLabel = account.payment_method_name ? ` (${account.payment_method_name})` : '';
            option.textContent = `${account.name}${methodLabel}`;
            select.appendChild(option);
            accountMap.set(String(account.id), account);
        });
    };

    const loadPaymentAccounts = async () => {
        accountMap.clear();
        try {
            const adminData = await fetchJson(`${window.APP_BASE}/api/accounts/list.php?owner_type=admin&is_active=1`);
            const adminAccounts = adminData.data || [];
            renderAccountOptions(payFromSelect, adminAccounts, 'Select admin account');
            renderAccountOptions(advanceFromSelect, adminAccounts, 'Select admin account');
            renderAccountOptions(bonusFromSelect, adminAccounts, 'Select admin account');
        } catch (error) {
            showNotice(`Accounts load failed: ${error.message}`, 'error');
        }
    };

    const renderExpenses = (rows) => {
        if (!expensesTable) {
            return;
        }
        if (!rows || rows.length === 0) {
            expensesTable.innerHTML = '<tr><td colspan="7" class="muted">No expenses found.</td></tr>';
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
                            : exp.type === 'salary_payment'
                              ? 'Salary payment'
                            : exp.type
                    : '-';
                const salaryMonth = exp.salary_month ? String(exp.salary_month).slice(0, 7) : '-';
                return `<tr>
                    <td>${typeLabel}</td>
                    <td>${formatAmount(exp.amount)}</td>
                    <td>${exp.salary_before ?? '-'}</td>
                    <td>${exp.salary_after ?? '-'}</td>
                    <td>${salaryMonth}</td>
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
            renderInvoices([]);
            renderUninvoiced([]);
            renderTransactions([]);
            renderOrders([]);
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
            const confirmed = await showConfirmDialog({
                title: 'Delete staff',
                message: 'Delete this staff member?',
                confirmLabel: 'Delete'
            });
            if (!confirmed) {
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

    if (payForm && canEdit) {
        payForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (payStatus) {
                payStatus.innerHTML = '';
            }
            const formData = new FormData(payForm);
            const payload = Object.fromEntries(formData.entries());
            payload.staff_id = staffId;
            if (!payload.from_account_id) {
                showFormNotice(payStatus, 'Select an admin account.', 'error');
                return;
            }
            try {
                await fetchJson(`${window.APP_BASE}/api/staff/pay_salary.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showFormNotice(payStatus, 'Salary payment recorded.', 'success');
                payForm.reset();
                loadStaffView();
            } catch (error) {
                showFormNotice(payStatus, `Payment failed: ${error.message}`, 'error');
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
            if (!payload.from_account_id) {
                showFormNotice(statusEl, 'Select an admin account.', 'error');
                return;
            }
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

    loadPaymentAccounts();
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
    const paymentDrawer = page.querySelector('[data-branch-payment-drawer]');
    const paymentOpenButton = page.querySelector('[data-branch-payment-open]');
    const paymentForm = page.querySelector('[data-branch-payment-form]');
    const paymentStatus = page.querySelector('[data-branch-payment-status]');
    const paymentCloseButtons = page.querySelectorAll('[data-branch-payment-close]');
    const paymentFromSelect = page.querySelector('[data-branch-payment-from]');
    const paymentFromAccountSelect = page.querySelector('[data-branch-payment-from-account]');
    const paymentToAccountSelect = page.querySelector('[data-branch-payment-to-account]');
    const paymentDescriptionSelect = page.querySelector('[data-branch-payment-description]');
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
    const createMode = page.getAttribute('data-create-mode') === '1';

    const limit = 5;
    let offset = 0;
    let lastFilters = {};
    const branchMap = new Map();
    const accountMap = new Map();
    const urlParams = new URLSearchParams(window.location.search || '');
    const pendingPaymentBranchId = urlParams.get('branch_id');
    const shouldOpenPayment = urlParams.get('payment') === '1';
    let paymentOpened = false;

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

    const showPaymentNotice = (message, type = 'error') => {
        if (!paymentStatus) {
            showNotice(message, type);
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        paymentStatus.appendChild(notice);
        setTimeout(() => notice.remove(), 7000);
    };

    const formatAmount = (value) => {
        const num = Number(value ?? 0);
        return Number.isFinite(num) ? num.toFixed(2) : '0.00';
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

    const openPaymentDrawer = () => {
        if (!paymentDrawer) {
            return;
        }
        paymentDrawer.classList.add('is-open');
        document.body.classList.add('drawer-open');
        if (paymentFromSelect) {
            loadPaymentAccounts(paymentFromSelect.value);
        }
    };

    const closePaymentDrawer = () => {
        if (!paymentDrawer) {
            return;
        }
        paymentDrawer.classList.remove('is-open');
        document.body.classList.remove('drawer-open');
        if (paymentStatus) {
            paymentStatus.innerHTML = '';
        }
        if (paymentForm) {
            paymentForm.reset();
        }
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

    const renderAccountOptions = (select, accounts, placeholder) => {
        if (!select) {
            return;
        }
        select.innerHTML = `<option value="">${placeholder}</option>`;
        (accounts || []).forEach((account) => {
            const option = document.createElement('option');
            option.value = account.id;
            const methodLabel = account.payment_method_name ? ` (${account.payment_method_name})` : '';
            option.textContent = `${account.name}${methodLabel}`;
            select.appendChild(option);
            accountMap.set(String(account.id), account);
        });
    };

    const loadPaymentBranches = async () => {
        if (!paymentFromSelect) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/branches/list.php?limit=200`);
            const branches = data.data || [];
            paymentFromSelect.innerHTML = '<option value="">Select sub branch</option>';
            branches.forEach((branch) => {
                if (branch.type === 'sub') {
                    const option = document.createElement('option');
                    option.value = branch.id;
                    option.textContent = branch.name;
                    paymentFromSelect.appendChild(option);
                }
            });
            if (shouldOpenPayment && pendingPaymentBranchId && !paymentOpened) {
                paymentFromSelect.value = String(pendingPaymentBranchId);
                openPaymentDrawer();
                paymentOpened = true;
            }
        } catch (error) {
            showPaymentNotice(`Branches load failed: ${error.message}`, 'error');
        }
    };

    const loadPaymentAccounts = async (branchId) => {
        if (!paymentFromAccountSelect || !paymentToAccountSelect) {
            return;
        }
        accountMap.clear();
        renderAccountOptions(paymentFromAccountSelect, [], 'Select branch account');
        renderAccountOptions(paymentToAccountSelect, [], 'Select admin account');
        if (!branchId) {
            return;
        }
        try {
            const [branchAccounts, adminAccounts] = await Promise.all([
                fetchJson(
                    `${window.APP_BASE}/api/accounts/list.php?owner_type=branch&owner_id=${encodeURIComponent(branchId)}&is_active=1`
                ),
                fetchJson(`${window.APP_BASE}/api/accounts/list.php?owner_type=admin&is_active=1`),
            ]);
            renderAccountOptions(paymentFromAccountSelect, branchAccounts.data || [], 'Select branch account');
            renderAccountOptions(paymentToAccountSelect, adminAccounts.data || [], 'Select admin account');
        } catch (error) {
            showPaymentNotice(`Accounts load failed: ${error.message}`, 'error');
        }
    };

    const renderRows = (rows) => {
        if (!tableBody) {
            return;
        }
        branchMap.clear();
        if (!rows.length) {
            const colspan = canEdit ? 7 : 6;
            tableBody.innerHTML = `<tr><td colspan="${colspan}" class="muted">No branches found.</td></tr>`;
            return;
        }
        tableBody.innerHTML = rows
            .map((branch) => {
                branchMap.set(String(branch.id), branch);
                const typeLabel = branch.type ? branch.type.charAt(0).toUpperCase() + branch.type.slice(1) : '-';
                const contact = branch.phone || branch.address || '-';
                const balanceLabel = formatAmount(branch.balance);
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
                        <td>${balanceLabel}</td>
                        ${actions}
                    </tr>`;
            })
            .join('');
    };

    const loadBranches = async (filters = {}) => {
        if (tableBody) {
            const colspan = canEdit ? 7 : 6;
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

    if (paymentOpenButton) {
        paymentOpenButton.addEventListener('click', () => openPaymentDrawer());
    }

    if (paymentFromSelect) {
        paymentFromSelect.addEventListener('change', () => {
            loadPaymentAccounts(paymentFromSelect.value);
        });
    }

    if (drawerCloseButtons.length) {
        drawerCloseButtons.forEach((button) => button.addEventListener('click', closeDrawer));
    }

    if (paymentCloseButtons.length) {
        paymentCloseButtons.forEach((button) => button.addEventListener('click', closePaymentDrawer));
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

    if (paymentForm) {
        paymentForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const payload = Object.fromEntries(new FormData(paymentForm).entries());
            const fromBranchId = payload.from_branch_id ? Number(payload.from_branch_id) : 0;
            const fromAccountId = payload.from_account_id ? Number(payload.from_account_id) : 0;
            const toAccountId = payload.to_account_id ? Number(payload.to_account_id) : 0;
            const amountValue = payload.amount ? Number(payload.amount) : 0;
            const descriptionValue = payload.description ? String(payload.description).trim() : '';
            const noteValue = payload.note ? String(payload.note).trim() : '';
            const combinedNote = descriptionValue && descriptionValue !== 'Other'
                ? noteValue
                    ? `${descriptionValue} - ${noteValue}`
                    : descriptionValue
                : noteValue || null;
            if (!fromBranchId || !fromAccountId || !toAccountId || !Number.isFinite(amountValue) || amountValue <= 0) {
                showPaymentNotice('Select accounts and enter a valid amount.', 'error');
                return;
            }
            try {
                const data = await fetchJson(`${window.APP_BASE}/api/branch_transfers/create.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        from_branch_id: fromBranchId,
                        from_account_id: fromAccountId,
                        to_account_id: toAccountId,
                        amount: amountValue,
                        transfer_date: payload.transfer_date || null,
                        note: combinedNote,
                    }),
                });
                showPaymentNotice('Branch payment recorded.', 'success');
                if (data.id) {
                    window.open(
                        `${window.APP_BASE}/views/internal/branch_transfer_receipt?id=${encodeURIComponent(data.id)}`,
                        '_blank',
                        'noopener'
                    );
                }
                closePaymentDrawer();
                loadBranches(lastFilters);
            } catch (error) {
                showPaymentNotice(`Payment failed: ${error.message}`, 'error');
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
                const confirmed = await showConfirmDialog({
                    title: 'Delete branch',
                    message: `Delete branch "${branch.name}"?`,
                    confirmLabel: 'Delete'
                });
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

    if (createMode && canEdit) {
        openForm(null);
    }

    loadCountries();
    loadParentBranches();
    loadPaymentBranches();
    if (paymentFromSelect && paymentFromSelect.value) {
        loadPaymentAccounts(paymentFromSelect.value);
    }
    loadBranches();
}

function initAccountsPage() {
    const page = document.querySelector('[data-accounts-page]');
    if (!page) {
        return;
    }

    const { role, branchId } = getUserContext();
    const isBranchRole = role === 'Sub Branch' || role === 'Main Branch';
    const filterForm = page.querySelector('[data-accounts-filter]');
    const tableBody = page.querySelector('[data-accounts-table]');
    const statusStack = page.querySelector('[data-accounts-status]');
    const refreshButton = page.querySelector('[data-accounts-refresh]');
    const addButton = page.querySelector('[data-account-add]');
    const drawer = page.querySelector('[data-account-drawer]');
    const form = page.querySelector('[data-account-form]');
    const formTitle = page.querySelector('[data-account-form-title]');
    const submitLabel = page.querySelector('[data-account-submit-label]');
    const drawerStatus = page.querySelector('[data-account-form-status]');
    const drawerCloseButtons = page.querySelectorAll('[data-account-drawer-close]');
    const ownerTypeFilter = page.querySelector('[data-account-owner-type-filter]');
    const ownerFilter = page.querySelector('[data-account-owner-filter]');
    const methodFilter = page.querySelector('[data-account-method-filter]');
    const statusFilter = page.querySelector('[data-account-status-filter]');
    const ownerTypeSelect = page.querySelector('[data-account-owner-type]');
    const ownerSelect = page.querySelector('[data-account-owner]');
    const ownerField = page.querySelector('[data-account-owner-field]');
    const methodSelect = page.querySelector('[data-account-method]');
    const currencySelect = page.querySelector('[data-account-currency]');
    const statusSelect = page.querySelector('[data-account-active]');
    const accountIdField = page.querySelector('[data-account-id]');
    const previewPanel = page.querySelector('[data-account-preview]');
    const previewCloseButton = page.querySelector('[data-account-preview-close]');
    const canEdit = page.getAttribute('data-can-edit') === '1';
    const createMode = page.getAttribute('data-create-mode') === '1';

    const accountMap = new Map();
    const ownerLists = {
        branch: [],
    };
    const previewFields = {};
    let paymentMethods = [];
    let isCreateMode = true;

    if (isBranchRole && filterForm) {
        filterForm.classList.add('is-hidden');
    }

    const ownerTypeLabels = {
        admin: 'Admin',
        branch: 'Branch',
    };

    if (previewPanel) {
        previewPanel.querySelectorAll('[data-preview]').forEach((el) => {
            const key = el.getAttribute('data-preview');
            if (key) {
                previewFields[key] = el;
            }
        });
    }

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

    const formatAmount = (value) => {
        const num = Number(value ?? 0);
        return Number.isFinite(num) ? num.toFixed(2) : '0.00';
    };

    const formatOwnerLabel = (account) => {
        const ownerType = account.owner_type || '';
        const ownerName = account.owner_name || '';
        let ownerLabel = ownerTypeLabels[ownerType] || ownerType;
        if (ownerType === 'admin') {
            ownerLabel = 'Admin';
        } else if (ownerName) {
            ownerLabel = `${ownerLabel}: ${ownerName}`;
        } else if (account.owner_id) {
            ownerLabel = `${ownerLabel}: #${account.owner_id}`;
        }
        return ownerLabel || '-';
    };

    const formatStatus = (value) => (value ? 'Active' : 'Inactive');

    const formatMethod = (account) => account.payment_method_name || account.account_type || '-';

    const openPreview = (account) => {
        if (!previewPanel || !account) {
            return;
        }
        if (previewFields.name) {
            previewFields.name.textContent = account.name || '-';
        }
        if (previewFields.owner) {
            previewFields.owner.textContent = formatOwnerLabel(account);
        }
        if (previewFields.type) {
            previewFields.type.textContent = account.account_type || '-';
        }
        if (previewFields.method) {
            previewFields.method.textContent = formatMethod(account);
        }
        if (previewFields.currency) {
            previewFields.currency.textContent = account.currency || 'USD';
        }
        if (previewFields.status) {
            previewFields.status.textContent = formatStatus(account.is_active);
        }
        if (previewFields.balance) {
            previewFields.balance.textContent = formatAmount(account.balance);
        }
        previewPanel.classList.remove('is-hidden');
        previewPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const setOwnerOptions = (select, owners, placeholder) => {
        if (!select) {
            return;
        }
        clearDynamicOptions(select);
        if (placeholder) {
            const placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = placeholder;
            placeholderOption.setAttribute('data-dynamic', 'true');
            select.appendChild(placeholderOption);
        }
        owners.forEach((owner) => {
            const option = document.createElement('option');
            option.value = owner.id;
            option.textContent = owner.label;
            option.setAttribute('data-dynamic', 'true');
            select.appendChild(option);
        });
    };

    const ensureOwnerSelection = (select, ownerId, fallbackLabel) => {
        if (!select || !ownerId) {
            return;
        }
        const existing = Array.from(select.options).some((option) => option.value === String(ownerId));
        if (!existing) {
            const option = document.createElement('option');
            option.value = ownerId;
            option.textContent = fallbackLabel;
            option.setAttribute('data-dynamic', 'true');
            select.appendChild(option);
        }
        select.value = String(ownerId);
    };

    const ensureMethodSelection = (select, methodId, fallbackLabel) => {
        if (!select || !methodId) {
            return;
        }
        const existing = Array.from(select.options).some((option) => option.value === String(methodId));
        if (!existing) {
            const option = document.createElement('option');
            option.value = methodId;
            option.textContent = fallbackLabel;
            option.setAttribute('data-dynamic', 'true');
            select.appendChild(option);
        }
        select.value = String(methodId);
    };

    const updateOwnerVisibility = (ownerType, selectEl, fieldEl, placeholder) => {
        if (!selectEl) {
            return;
        }
        const showOwners = ownerType && ownerType !== 'admin';
        selectEl.disabled = !showOwners;
        if (fieldEl) {
            fieldEl.classList.toggle('is-hidden', !showOwners);
        } else {
            selectEl.classList.toggle('is-hidden', !showOwners);
        }
        if (!showOwners) {
            selectEl.value = '';
            return;
        }
        const owners = ownerLists[ownerType] || [];
        setOwnerOptions(selectEl, owners, placeholder);
    };

    const loadPaymentMethods = async () => {
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/payment_methods/list.php`);
            paymentMethods = data.data || [];
            if (methodSelect) {
                clearDynamicOptions(methodSelect);
                paymentMethods.forEach((method) => {
                    const option = document.createElement('option');
                    option.value = method.id;
                    option.textContent = method.name;
                    option.setAttribute('data-dynamic', 'true');
                    methodSelect.appendChild(option);
                });
            }
            if (methodFilter) {
                clearDynamicOptions(methodFilter);
                paymentMethods.forEach((method) => {
                    const option = document.createElement('option');
                    option.value = method.id;
                    option.textContent = method.name;
                    option.setAttribute('data-dynamic', 'true');
                    methodFilter.appendChild(option);
                });
            }
        } catch (error) {
            showNotice(`Payment methods load failed: ${error.message}`, 'error');
        }
    };

    const loadOwnerList = async (type, url, formatter) => {
        try {
            const data = await fetchJson(url);
            const rows = data.data || [];
            const owners = rows.map((row) => formatter(row)).filter(Boolean);
            ownerLists[type] = owners;
            if (ownerTypeSelect && ownerTypeSelect.value === type) {
                updateOwnerVisibility(type, ownerSelect, ownerField, 'Select owner');
            }
            if (ownerTypeFilter && ownerTypeFilter.value === type) {
                updateOwnerVisibility(type, ownerFilter, null, 'All owners');
            }
        } catch (error) {
            showNotice(`${ownerTypeLabels[type]} list failed: ${error.message}`, 'error');
        }
    };

    const loadOwners = () => {
        loadOwnerList(
            'branch',
            `${window.APP_BASE}/api/branches/list.php?type=sub&limit=200`,
            (row) => ({ id: row.id, label: row.name })
        );
    };

    const setFormMode = (mode, account = null) => {
        isCreateMode = mode === 'create';
        if (formTitle) {
            formTitle.textContent = isCreateMode ? 'Add account' : 'Edit account';
        }
        if (submitLabel) {
            submitLabel.textContent = isCreateMode ? 'Add account' : 'Save changes';
        }
        if (ownerTypeSelect) {
            ownerTypeSelect.disabled = !isCreateMode;
        }
        if (methodSelect) {
            methodSelect.disabled = !isCreateMode;
        }
        if (ownerSelect) {
            const isAdminOwner = (ownerTypeSelect && ownerTypeSelect.value === 'admin');
            ownerSelect.disabled = !isCreateMode || isAdminOwner;
        }
        if (statusSelect && isCreateMode) {
            statusSelect.value = '1';
        }
        if (currencySelect && isCreateMode) {
            currencySelect.value = 'USD';
        }
        if (account && !isCreateMode) {
            if (ownerTypeSelect) {
                ownerTypeSelect.value = account.owner_type || 'admin';
            }
            updateOwnerVisibility(account.owner_type, ownerSelect, ownerField, 'Select owner');
            if (ownerSelect && account.owner_type !== 'admin') {
                const fallbackLabel = `Unknown (#${account.owner_id})`;
                ensureOwnerSelection(ownerSelect, account.owner_id, fallbackLabel);
            }
            if (methodSelect) {
                const methodLabel = account.payment_method_name || account.account_type || 'Unknown method';
                ensureMethodSelection(methodSelect, account.payment_method_id, methodLabel);
            }
            if (currencySelect) {
                currencySelect.value = account.currency || 'USD';
            }
            if (statusSelect) {
                statusSelect.value = account.is_active ? '1' : '0';
            }
        }
    };

    const renderAccounts = (accounts) => {
        if (!tableBody) {
            return;
        }
        tableBody.innerHTML = '';
        if (!accounts.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 8;
            cell.className = 'muted';
            cell.textContent = 'No accounts found.';
            row.appendChild(cell);
            tableBody.appendChild(row);
            return;
        }
        accounts.forEach((account) => {
            const row = document.createElement('tr');
            const ownerLabel = formatOwnerLabel(account);
            const statusLabel = formatStatus(account.is_active);
            const methodName = formatMethod(account);
            const isBranchAccount = account.owner_type === 'branch';
            const isAdminAccount = account.owner_type === 'admin';

            const cells = [
                account.name || '',
                ownerLabel,
                account.account_type || '',
                methodName,
                account.currency || 'USD',
                formatAmount(account.balance),
                statusLabel,
            ];
            cells.forEach((value) => {
                const cell = document.createElement('td');
                cell.textContent = value;
                row.appendChild(cell);
            });

            const actionCell = document.createElement('td');
            const viewLink = document.createElement('a');
            viewLink.className = 'button ghost small';
            viewLink.textContent = 'View';
            viewLink.href = `${window.APP_BASE}/views/internal/account_view?id=${encodeURIComponent(
                account.id
            )}`;
            actionCell.appendChild(viewLink);

            const previewButton = document.createElement('button');
            previewButton.type = 'button';
            previewButton.className = 'button ghost small';
            previewButton.textContent = 'Preview';
            previewButton.addEventListener('click', () => {
                openPreview(account);
            });
            actionCell.appendChild(previewButton);

            if (canEdit) {
                if (isAdminAccount) {
                    const editButton = document.createElement('button');
                    editButton.type = 'button';
                    editButton.className = 'button ghost small';
                    editButton.textContent = 'Edit';
                    editButton.addEventListener('click', () => {
                        if (!form) {
                            return;
                        }
                        if (drawerStatus) {
                            drawerStatus.innerHTML = '';
                        }
                        form.reset();
                        if (accountIdField) {
                            accountIdField.value = account.id;
                        }
                        setFormMode('edit', account);
                        if (form.elements.name) {
                            form.elements.name.value = account.name || '';
                        }
                        openDrawer();
                    });

                    const deleteButton = document.createElement('button');
                    deleteButton.type = 'button';
                    deleteButton.className = 'button ghost small';
                    deleteButton.textContent = 'Delete';
                    const balance = Number(account.balance || 0);
                    if (Math.abs(balance) > 0.0001) {
                        deleteButton.disabled = true;
                        deleteButton.title = 'Balance must be zero before deletion.';
                    }
                    deleteButton.addEventListener('click', async () => {
                        if (!account.id) {
                            return;
                        }
                        const confirmed = await showConfirmDialog({
                            title: 'Delete account',
                            message: 'Delete this account? Balance must be zero.',
                            confirmLabel: 'Delete'
                        });
                        if (!confirmed) {
                            return;
                        }
                        try {
                            await fetchJson(`${window.APP_BASE}/api/accounts/delete.php`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ account_id: account.id }),
                            });
                            showNotice('Account deleted.', 'success');
                            loadAccounts();
                        } catch (error) {
                            showNotice(`Delete failed: ${error.message}`, 'error');
                        }
                    });

                    actionCell.appendChild(editButton);
                    actionCell.appendChild(deleteButton);
                } else if (isBranchAccount) {
                    const toggleButton = document.createElement('button');
                    toggleButton.type = 'button';
                    toggleButton.className = 'button ghost small';
                    toggleButton.textContent = account.is_active ? 'Deactivate' : 'Activate';
                    const balance = Number(account.balance || 0);
                    if (account.is_active && Math.abs(balance) > 0.0001) {
                        toggleButton.disabled = true;
                        toggleButton.title = 'Balance must be zero before deactivation.';
                    }
                    toggleButton.addEventListener('click', async () => {
                        if (!account.id) {
                            return;
                        }
                        const nextState = account.is_active ? 0 : 1;
                        if (nextState === 0) {
                            const confirmed = await showConfirmDialog({
                                title: 'Deactivate account',
                                message: 'Deactivate this account? Balance must be zero.',
                                confirmLabel: 'Deactivate'
                            });
                            if (!confirmed) {
                                return;
                            }
                        }
                        try {
                            await fetchJson(`${window.APP_BASE}/api/accounts/update.php`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ account_id: account.id, is_active: nextState }),
                            });
                            showNotice('Account updated.', 'success');
                            loadAccounts();
                        } catch (error) {
                            showNotice(`Update failed: ${error.message}`, 'error');
                        }
                    });
                    actionCell.appendChild(toggleButton);
                }
            }

            row.appendChild(actionCell);
            tableBody.appendChild(row);
        });
    };

    const loadAccounts = async () => {
        if (statusStack) {
            statusStack.innerHTML = '';
        }
        const params = new URLSearchParams();
        if (!isBranchRole && filterForm) {
            const formData = new FormData(filterForm);
            const filters = Object.fromEntries(formData.entries());
            if (filters.q) {
                params.set('q', filters.q.toString().trim());
            }
            if (filters.owner_type) {
                params.set('owner_type', filters.owner_type.toString());
            }
            if (filters.owner_id) {
                params.set('owner_id', filters.owner_id.toString());
            }
            if (filters.payment_method_id) {
                params.set('payment_method_id', filters.payment_method_id.toString());
            }
            if (filters.is_active !== '' && filters.is_active !== undefined) {
                params.set('is_active', filters.is_active.toString());
            }
        }
        if (isBranchRole) {
            if (!branchId) {
                showNotice('Branch scope is required to load accounts.', 'error');
                return;
            }
            params.set('owner_type', 'branch');
            params.set('owner_id', String(branchId));
        }

        try {
            const url = `${window.APP_BASE}/api/accounts/list.php?${params.toString()}`;
            const data = await fetchJson(url);
            const accounts = data.data || [];
            accountMap.clear();
            accounts.forEach((account) => {
                accountMap.set(String(account.id), account);
            });
            renderAccounts(accounts);
        } catch (error) {
            showNotice(`Accounts load failed: ${error.message}`, 'error');
        }
    };

    if (ownerTypeFilter) {
        ownerTypeFilter.addEventListener('change', () => {
            updateOwnerVisibility(ownerTypeFilter.value, ownerFilter, null, 'All owners');
        });
    }

    if (ownerTypeSelect) {
        ownerTypeSelect.addEventListener('change', () => {
            updateOwnerVisibility(ownerTypeSelect.value, ownerSelect, ownerField, 'Select owner');
        });
    }

    if (ownerTypeFilter) {
        updateOwnerVisibility(ownerTypeFilter.value, ownerFilter, null, 'All owners');
    }
    if (ownerTypeSelect) {
        updateOwnerVisibility(ownerTypeSelect.value, ownerSelect, ownerField, 'Select owner');
    }

    if (addButton && form) {
        addButton.addEventListener('click', () => {
            if (drawerStatus) {
                drawerStatus.innerHTML = '';
            }
            form.reset();
            if (accountIdField) {
                accountIdField.value = '';
            }
            if (ownerTypeSelect) {
                ownerTypeSelect.value = 'admin';
            }
            updateOwnerVisibility(ownerTypeSelect ? ownerTypeSelect.value : 'admin', ownerSelect, ownerField, 'Select owner');
            setFormMode('create');
            openDrawer();
        });
    }

    if (drawerCloseButtons.length) {
        drawerCloseButtons.forEach((button) => {
            button.addEventListener('click', () => {
                closeDrawer();
                if (drawerStatus) {
                    drawerStatus.innerHTML = '';
                }
            });
        });
    }

    if (previewCloseButton && previewPanel) {
        previewCloseButton.addEventListener('click', () => {
            previewPanel.classList.add('is-hidden');
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

            if (isCreateMode) {
                const ownerType = payload.owner_type ? payload.owner_type.toString() : '';
                if (!ownerType) {
                    showFormNotice('Owner type is required.', 'error');
                    return;
                }
                if (ownerType !== 'admin' && !payload.owner_id) {
                    showFormNotice('Owner is required.', 'error');
                    return;
                }
                if (!payload.payment_method_id) {
                    showFormNotice('Payment method is required.', 'error');
                    return;
                }
                const method = paymentMethods.find(
                    (item) => String(item.id) === String(payload.payment_method_id)
                );
                const accountPayload = {
                    owner_type: ownerType,
                    owner_id: ownerType === 'admin' ? null : payload.owner_id,
                    name: payload.name ? payload.name.toString().trim() : '',
                    payment_method_id: payload.payment_method_id,
                    account_type: method ? method.name : undefined,
                    currency: payload.currency ? payload.currency.toString() : 'USD',
                    is_active: payload.is_active ? parseInt(payload.is_active, 10) : 1,
                };
                if (!accountPayload.name) {
                    delete accountPayload.name;
                }
                if (!accountPayload.account_type) {
                    delete accountPayload.account_type;
                }
                try {
                    await fetchJson(`${window.APP_BASE}/api/accounts/create.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(accountPayload),
                    });
                    showFormNotice('Account created.', 'success');
                    closeDrawer();
                    loadAccounts();
                } catch (error) {
                    showFormNotice(`Save failed: ${error.message}`, 'error');
                }
                return;
            }

            const accountId = payload.account_id ? parseInt(payload.account_id, 10) : null;
            if (!accountId) {
                showFormNotice('Account id is required.', 'error');
                return;
            }
            const updatePayload = {
                account_id: accountId,
                name: payload.name ? payload.name.toString().trim() : '',
                currency: payload.currency ? payload.currency.toString() : undefined,
                is_active: payload.is_active ? parseInt(payload.is_active, 10) : 0,
            };
            if (!updatePayload.currency) {
                delete updatePayload.currency;
            }
            try {
                await fetchJson(`${window.APP_BASE}/api/accounts/update.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(updatePayload),
                });
                showFormNotice('Account updated.', 'success');
                closeDrawer();
                loadAccounts();
            } catch (error) {
                showFormNotice(`Save failed: ${error.message}`, 'error');
            }
        });
    }

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            loadAccounts();
        });
    }

    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            if (filterForm) {
                filterForm.reset();
                if (ownerFilter) {
                    ownerFilter.classList.add('is-hidden');
                    ownerFilter.disabled = true;
                }
            }
            loadAccounts();
        });
    }

    if (createMode && canEdit) {
        if (form) {
            form.reset();
        }
        if (accountIdField) {
            accountIdField.value = '';
        }
        if (ownerTypeSelect) {
            ownerTypeSelect.value = 'admin';
        }
        if (currencySelect) {
            currencySelect.value = 'USD';
        }
        updateOwnerVisibility(ownerTypeSelect ? ownerTypeSelect.value : 'admin', ownerSelect, ownerField, 'Select owner');
        setFormMode('create');
        openDrawer();
    }

    loadPaymentMethods();
    loadOwners();
    loadAccounts();
}

function initAccountView() {
    const page = document.querySelector('[data-account-view]');
    if (!page) {
        return;
    }

    const accountId = page.getAttribute('data-account-id');
    const statusStack = page.querySelector('[data-account-view-status]');
    const details = page.querySelectorAll('[data-detail]');
    const entriesTable = page.querySelector('[data-account-entries]');
    const entriesPrev = page.querySelector('[data-account-entries-prev]');
    const entriesNext = page.querySelector('[data-account-entries-next]');
    const entriesPageLabel = page.querySelector('[data-account-entries-page]');
    const adjustForm = page.querySelector('[data-account-adjust-form]');
    const adjustStatus = page.querySelector('[data-account-adjust-status]');
    const detailMap = {};
    const pageSize = 25;
    let entriesPage = 0;

    details.forEach((el) => {
        const key = el.getAttribute('data-detail');
        if (key) {
            detailMap[key] = el;
        }
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

    const showAdjustNotice = (message, type = 'error') => {
        if (!adjustStatus) {
            showNotice(message, type);
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        adjustStatus.appendChild(notice);
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

    const formatOwnerLabel = (account) => {
        const ownerTypeLabels = {
            admin: 'Admin',
            branch: 'Branch',
            staff: 'Staff',
            supplier: 'Supplier',
        };
        const ownerType = account.owner_type || '';
        const ownerName = account.owner_name || '';
        let ownerLabel = ownerTypeLabels[ownerType] || ownerType;
        if (ownerType === 'admin') {
            ownerLabel = 'Admin';
        } else if (ownerName) {
            ownerLabel = `${ownerLabel}: ${ownerName}`;
        } else if (account.owner_id) {
            ownerLabel = `${ownerLabel}: #${account.owner_id}`;
        }
        return ownerLabel || '-';
    };

    const formatEntryType = (value) => {
        const labels = {
            customer_payment: 'Customer payment',
            branch_transfer: 'Branch transfer',
            supplier_transaction: 'Supplier transaction',
            staff_expense: 'Staff expense',
            general_expense: 'General expense',
            shipment_expense: 'Shipment expense',
            adjustment: 'Adjustment',
            other: 'Other',
        };
        return labels[value] || (value ? value.replace(/_/g, ' ') : '-');
    };

    const updatePager = (rows) => {
        if (entriesPrev) {
            entriesPrev.disabled = entriesPage === 0;
        }
        if (entriesNext) {
            entriesNext.disabled = rows.length < pageSize;
        }
        if (entriesPageLabel) {
            entriesPageLabel.textContent = `Page ${entriesPage + 1}`;
        }
    };

    const renderEntries = (rows) => {
        if (!entriesTable) {
            return;
        }
        if (!rows.length) {
            entriesTable.innerHTML = '<tr><td colspan="7" class="muted">No entries found.</td></tr>';
            updatePager(rows);
            return;
        }
        entriesTable.innerHTML = rows
            .map((entry) => {
                const dateLabel = entry.entry_date || entry.transfer_date || entry.created_at || '-';
                const typeLabel = formatEntryType(entry.entry_type);
                const amountLabel = formatAmount(entry.amount);
                let transferLabel = '-';
                if (entry.from_account_name && entry.to_account_name) {
                    transferLabel = `${entry.from_account_name} -> ${entry.to_account_name}`;
                } else if (entry.from_account_name || entry.to_account_name) {
                    transferLabel = entry.from_account_name || entry.to_account_name;
                }
                const statusLabel = entry.status === 'canceled' ? 'Canceled' : 'Active';
                let referenceLabel = entry.reference_label || '-';
                if (!entry.reference_label && entry.reference_type && entry.reference_id) {
                    if (entry.reference_type === 'transaction' && entry.customer_name) {
                        const codeLabel = entry.customer_code ? ` (${entry.customer_code})` : '';
                        referenceLabel = `${entry.customer_name}${codeLabel}`;
                    } else {
                        referenceLabel = `${entry.reference_type} #${entry.reference_id}`;
                    }
                }
                const noteLabel = entry.transfer_note || entry.reference_note || '-';
                return `<tr>
                    <td>${escapeHtml(dateLabel)}</td>
                    <td>${escapeHtml(typeLabel)}</td>
                    <td>${escapeHtml(amountLabel)}</td>
                    <td>${escapeHtml(transferLabel)}</td>
                    <td>${escapeHtml(statusLabel)}</td>
                    <td>${escapeHtml(referenceLabel)}</td>
                    <td>${escapeHtml(noteLabel)}</td>
                </tr>`;
            })
            .join('');
        updatePager(rows);
    };

    const loadAccount = async () => {
        try {
            const data = await fetchJson(
                `${window.APP_BASE}/api/accounts/view.php?account_id=${encodeURIComponent(accountId)}`
            );
            const account = data.account || {};
            if (detailMap.id) {
                detailMap.id.textContent = account.id ?? '-';
            }
            if (detailMap.name) {
                detailMap.name.textContent = account.name || '-';
            }
            if (detailMap.owner_label) {
                detailMap.owner_label.textContent = formatOwnerLabel(account);
            }
            if (detailMap.account_type) {
                detailMap.account_type.textContent = account.account_type || '-';
            }
            if (detailMap.payment_method_name) {
                detailMap.payment_method_name.textContent =
                    account.payment_method_name || account.account_type || '-';
            }
            if (detailMap.currency) {
                detailMap.currency.textContent = account.currency || 'USD';
            }
            if (detailMap.status) {
                detailMap.status.textContent = account.is_active ? 'Active' : 'Inactive';
            }
            if (detailMap.balance) {
                detailMap.balance.textContent = formatAmount(account.balance);
            }
        } catch (error) {
            showNotice(`Account load failed: ${error.message}`, 'error');
        }
    };

    const loadEntries = async () => {
        try {
            const offset = entriesPage * pageSize;
            const data = await fetchJson(
                `${window.APP_BASE}/api/accounts/entries.php?account_id=${encodeURIComponent(
                    accountId
                )}&limit=${pageSize}&offset=${offset}`
            );
            const rows = data.data || [];
            renderEntries(rows);
        } catch (error) {
            renderEntries([]);
            showNotice(`Entries load failed: ${error.message}`, 'error');
        }
    };

    if (!accountId) {
        showNotice('Missing account id.', 'error');
        return;
    }

    if (entriesPrev) {
        entriesPrev.addEventListener('click', () => {
            if (entriesPage === 0) {
                return;
            }
            entriesPage = Math.max(0, entriesPage - 1);
            loadEntries();
        });
    }

    if (entriesNext) {
        entriesNext.addEventListener('click', () => {
            entriesPage += 1;
            loadEntries();
        });
    }

    if (adjustForm) {
        adjustForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (adjustStatus) {
                adjustStatus.innerHTML = '';
            }
            const formData = new FormData(adjustForm);
            const payload = {
                account_id: Number(accountId),
                type: formData.get('type'),
                amount: Number(formData.get('amount') || 0),
                title: (formData.get('title') || '').toString().trim(),
                adjustment_date: (formData.get('adjustment_date') || '').toString() || null,
                note: (formData.get('note') || '').toString().trim() || null,
            };
            if (!payload.type || !payload.title || !Number.isFinite(payload.amount) || payload.amount <= 0) {
                showAdjustNotice('Type, title, and a valid amount are required.', 'error');
                return;
            }
            try {
                await fetchJson(`${window.APP_BASE}/api/accounts/adjust.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showAdjustNotice('Account adjustment saved.', 'success');
                adjustForm.reset();
                loadAccount();
                loadEntries();
            } catch (error) {
                showAdjustNotice(`Adjustment failed: ${error.message}`, 'error');
            }
        });
    }

    loadAccount();
    loadEntries();
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
    const createMode = page.getAttribute('data-create-mode') === '1';

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
                const confirmed = await showConfirmDialog({
                    title: 'Delete user',
                    message: `Delete user "${user.name}"?`,
                    confirmLabel: 'Delete'
                });
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

    if (createMode && canEdit) {
        openForm(null);
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
      const queryParams = new URLSearchParams(window.location.search);
      const accountIdField = form ? form.querySelector('[name="account_id"]') : null;
      const profileAccountId =
          (accountIdField && accountIdField.value ? accountIdField.value : '') ||
          queryParams.get('account_id') ||
          '';
      const profileMode =
          profileAccountId !== '' ||
          queryParams.get('customer_id') !== null ||
          page.getAttribute('data-profile-mode') === '1';
      const prefillPortalUsername = profileMode ? '' : queryParams.get('portal_username') || '';
      const prefillPhone = profileMode ? '' : queryParams.get('portal_phone') || queryParams.get('phone') || '';
      const prefillBranchId = profileMode ? '' : queryParams.get('sub_branch_id') || '';

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
            if (prefillBranchId) {
                branchSelect.value = prefillBranchId;
            }
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

      const applyProfileMode = () => {
          if (!profileMode || !form) {
              return;
          }
          const fieldsToHide = [
              'name',
              'phone',
              'address',
              'note',
              'portal_username',
              'portal_password',
              'sub_branch_id',
          ];
          fieldsToHide.forEach((name) => {
              const input = form.querySelector(`[name="${name}"]`);
              if (!input) {
                  return;
              }
              if (input.required) {
                  input.required = false;
              }
              const label = input.closest('label');
              if (label) {
                  label.classList.add('is-hidden');
              }
          });
          const accountIdInput = form.querySelector('[name="account_id"]');
          if (accountIdInput && profileAccountId) {
              accountIdInput.value = profileAccountId;
          }
      };

      applyProfileMode();

      if (!profileMode && prefillPortalUsername && portalUsernameInput) {
          portalUsernameInput.value = prefillPortalUsername;
          portalUsernameInput.dataset.manual = 'true';
      }
      if (!profileMode && prefillPhone && phoneInput) {
          phoneInput.value = prefillPhone;
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

      if (portalUsernameInput && !profileMode) {
          portalUsernameInput.addEventListener('input', () => {
              portalUsernameInput.dataset.manual = portalUsernameInput.value.trim() === '' ? 'false' : 'true';
          });
      }

      if (codeInput && !profileMode) {
          codeInput.addEventListener('input', syncPortalUsername);
          syncPortalUsername();
      }

    if (form) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(form);
            const payload = Object.fromEntries(formData.entries());
              if (!profileMode && portalUsernameInput && !payload.portal_username) {
                  showNotice('Portal username is required.', 'error');
                  return;
              }
              if (!payload.profile_country_id) {
                  showNotice('Profile country is required.', 'error');
                  return;
              }
              if (!profileMode) {
                  if (!payload.phone) {
                      showNotice('Phone is required.', 'error');
                      return;
                  }
                  if (phoneInput && payload.phone && String(payload.phone).trim().length < 8) {
                      showNotice('Phone number must be at least 8 characters.', 'error');
                      return;
                  }
              }
              if (!fullAccess && branchId) {
                  payload.sub_branch_id = branchId;
              }
              if (profileMode && profileAccountId) {
                  payload.account_id = profileAccountId;
                  delete payload.portal_username;
                  delete payload.portal_password;
                  delete payload.phone;
                  delete payload.address;
                  delete payload.note;
                  delete payload.sub_branch_id;
                  if (!payload.name) {
                      delete payload.name;
                  }
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
    const codeInput = form ? form.querySelector('[name="code"]') : null;

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

    initCustomerTabUi();

    if (!customerId) {
        showNotice('Missing customer id.', 'error');
        return;
    }

    const populateForm = (customer) => {
        if (!form || !codeInput) {
            return;
        }
        codeInput.value = customer.code || '';
    };

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
            if (!codeInput) {
                showNotice('Code is required.', 'error');
                return;
            }
            const payload = {
                customer_id: customerId,
                code: codeInput.value.trim(),
            };
            if (!payload.code) {
                showNotice('Code is required.', 'error');
                return;
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

function initCustomerInfoEdit() {
    const page = document.querySelector('[data-customer-info-edit]');
    if (!page) {
        return;
    }

    const customerId = page.getAttribute('data-customer-id');
    const form = page.querySelector('[data-customer-info-edit-form]');
    const statusStack = page.querySelector('[data-customer-info-edit-status]');
    const branchSelect = page.querySelector('[data-branch-select]');
    const nameInput = form ? form.querySelector('[name="name"]') : null;
    const phoneInput = form ? form.querySelector('[name="phone"]') : null;
    const addressInput = form ? form.querySelector('[name="address"]') : null;
    const noteInput = form ? form.querySelector('[name="note"]') : null;

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
        if (nameInput) {
            nameInput.value = customer.name || '';
        }
        if (phoneInput) {
            phoneInput.value = customer.phone || '';
        }
        if (addressInput) {
            addressInput.value = customer.address || '';
        }
        if (noteInput) {
            noteInput.value = customer.note || '';
        }
        if (branchSelect) {
            branchSelect.value = customer.sub_branch_id || '';
        }
    };

    loadBranches();

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
            if (!nameInput) {
                showNotice('Name is required.', 'error');
                return;
            }
            const payload = {
                customer_id: customerId,
                name: nameInput.value.trim(),
            };
            if (!payload.name) {
                showNotice('Name is required.', 'error');
                return;
            }
            if (phoneInput) {
                const phoneValue = phoneInput.value.trim();
                if (phoneValue) {
                    if (phoneValue.length < 8) {
                        showNotice('Phone number must be at least 8 characters.', 'error');
                        return;
                    }
                    payload.phone = phoneValue;
                }
            }
            if (addressInput) {
                const addressValue = addressInput.value.trim();
                payload.address = addressValue ? addressValue : null;
            }
            if (noteInput) {
                const noteValue = noteInput.value.trim();
                payload.note = noteValue ? noteValue : null;
            }
            if (branchSelect) {
                payload.sub_branch_id = branchSelect.value ? branchSelect.value : null;
            }
            try {
                await fetchJson(`${window.APP_BASE}/api/customers/update_info.php`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showNotice('Customer info updated.', 'success');
            } catch (error) {
                showNotice(`Update failed: ${error.message}`, 'error');
            }
        });
    }
}

function initCustomerTabUi() {
    const tabGroups = document.querySelectorAll('[data-customer-tabs]');
    if (!document.body.dataset.customerTabDelegated) {
        document.body.addEventListener('click', (event) => {
            const button = event.target.closest?.('[data-customer-tab]');
            if (!button) {
                return;
            }
            const tabs = button.closest('[data-customer-tabs]');
            if (!tabs) {
                return;
            }
            const tabId = button.getAttribute('data-customer-tab');
            if (!tabId) {
                return;
            }
            const buttons = Array.from(tabs.querySelectorAll('[data-customer-tab]'));
            const panels = Array.from(tabs.querySelectorAll('[data-customer-tab-panel]'));
            buttons.forEach((item) => {
                const isActive = item.getAttribute('data-customer-tab') === tabId;
                item.classList.toggle('is-active', isActive);
                item.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
            panels.forEach((panel) => {
                const isActive = panel.getAttribute('data-customer-tab-panel') === tabId;
                panel.classList.toggle('is-active', isActive);
            });
        });
        document.body.dataset.customerTabDelegated = 'true';
    }
    if (!tabGroups.length) {
        return;
    }
    tabGroups.forEach((tabs) => {
        if (tabs.dataset.tabsReady === 'true') {
            return;
        }
        const buttons = Array.from(tabs.querySelectorAll('[data-customer-tab]'));
        const panels = Array.from(tabs.querySelectorAll('[data-customer-tab-panel]'));
        if (!buttons.length || !panels.length) {
            return;
        }
        const setActive = (tabId) => {
            if (!tabId) {
                return;
            }
            buttons.forEach((button) => {
                const isActive = button.getAttribute('data-customer-tab') === tabId;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
            panels.forEach((panel) => {
                const isActive = panel.getAttribute('data-customer-tab-panel') === tabId;
                panel.classList.toggle('is-active', isActive);
            });
        };
        const activeButton = buttons.find((button) => button.classList.contains('is-active'));
        const initialTab = activeButton ? activeButton.getAttribute('data-customer-tab') : buttons[0].getAttribute('data-customer-tab');
        setActive(initialTab);
        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                const tabId = button.getAttribute('data-customer-tab');
                setActive(tabId);
            });
        });
        tabs.dataset.tabsReady = 'true';
    });
}

function initSupplierTabUi() {
    const tabGroups = document.querySelectorAll('[data-supplier-tabs]');
    if (!document.body.dataset.supplierTabDelegated) {
        document.body.addEventListener('click', (event) => {
            const button = event.target.closest?.('[data-supplier-tab]');
            if (!button) {
                return;
            }
            const tabs = button.closest('[data-supplier-tabs]');
            if (!tabs) {
                return;
            }
            const tabId = button.getAttribute('data-supplier-tab');
            if (!tabId) {
                return;
            }
            const buttons = Array.from(tabs.querySelectorAll('[data-supplier-tab]'));
            const panels = Array.from(tabs.querySelectorAll('[data-supplier-tab-panel]'));
            buttons.forEach((item) => {
                const isActive = item.getAttribute('data-supplier-tab') === tabId;
                item.classList.toggle('is-active', isActive);
                item.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
            panels.forEach((panel) => {
                const isActive = panel.getAttribute('data-supplier-tab-panel') === tabId;
                panel.classList.toggle('is-active', isActive);
            });
        });
        document.body.dataset.supplierTabDelegated = 'true';
    }
    if (!tabGroups.length) {
        return;
    }
    tabGroups.forEach((tabs) => {
        if (tabs.dataset.tabsReady === 'true') {
            return;
        }
        const buttons = Array.from(tabs.querySelectorAll('[data-supplier-tab]'));
        const panels = Array.from(tabs.querySelectorAll('[data-supplier-tab-panel]'));
        if (!buttons.length || !panels.length) {
            return;
        }
        const setActive = (tabId) => {
            if (!tabId) {
                return;
            }
            buttons.forEach((button) => {
                const isActive = button.getAttribute('data-supplier-tab') === tabId;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
            panels.forEach((panel) => {
                const isActive = panel.getAttribute('data-supplier-tab-panel') === tabId;
                panel.classList.toggle('is-active', isActive);
            });
        };
        const activeButton = buttons.find((button) => button.classList.contains('is-active'));
        const initialTab = activeButton ? activeButton.getAttribute('data-supplier-tab') : buttons[0].getAttribute('data-supplier-tab');
        setActive(initialTab);
        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                const tabId = button.getAttribute('data-supplier-tab');
                setActive(tabId);
            });
        });
        tabs.dataset.tabsReady = 'true';
    });
}

function initCustomerView() {
    const page = document.querySelector('[data-customer-view]');
    if (!page) {
        return;
    }

    initCustomerTabUi();

    const customerId = page.getAttribute('data-customer-id');
    const statusStack = page.querySelector('[data-customer-view-status]');
    const details = page.querySelectorAll('[data-detail]');
    const stats = page.querySelectorAll('[data-customer-stat]');
    const profilesTable = page.querySelector('[data-customer-profiles]');
    const invoicesTable = page.querySelector('[data-customer-invoices]');
    const uninvoicedTable = page.querySelector('[data-customer-uninvoiced]');
    const transactionsTable = page.querySelector('[data-customer-transactions]');
    const paymentForm = page.querySelector('[data-customer-payment-form]');
    const paymentStatus = page.querySelector('[data-customer-payment-status]');
    const paymentAmountInput = page.querySelector('[data-customer-payment-amount]');
    const paymentTypeSelect = page.querySelector('[data-customer-payment-type]');
    const paymentReasonSelect = page.querySelector('[data-customer-payment-reason]');
    const paymentSubmitButton = paymentForm ? paymentForm.querySelector('button[type="submit"]') : null;
    const paymentFromAccountSelect = page.querySelector('[data-customer-payment-from]');
    const paymentToAccountSelect = page.querySelector('[data-customer-payment-to]');
    const paymentDateInput = page.querySelector('[data-customer-payment-date]');
    const paymentInvoiceSelect = page.querySelector('[data-customer-payment-invoice]');
    const paymentWhishInput = page.querySelector('[data-customer-payment-whish]');
    const paymentNoteInput = page.querySelector('[data-customer-payment-note]');
    const paymentFromField = page.querySelector('[data-payment-from-field]');
    const paymentFromLabel = page.querySelector('[data-payment-from-label]');
    const paymentToField = page.querySelector('[data-payment-to-field]');
    const paymentToLabel = page.querySelector('[data-payment-to-label]');
    const paymentTypeField = page.querySelector('[data-payment-type-field]');
    const paymentReasonField = page.querySelector('[data-payment-reason-field]');
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
    const uninvoicedSelectAll = page.querySelector('[data-customer-uninvoiced-select-all]');
    const createInvoiceSelectedButton = page.querySelector('[data-customer-invoice-selected]');
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
    const addProfileLink = page.querySelector('[data-add-profile]');
    const { role, branchId } = getUserContext();
    const isBranchRole = role === 'Sub Branch' || role === 'Main Branch';
    const canEditProfile = role === 'Admin';
    const canRecordPayments = isBranchRole;
    const canRecordRefunds = isBranchRole;
    const canRecordCustomerPayment = isBranchRole || role === 'Admin' || role === 'Owner';
    const canBalanceAdjust = role === 'Admin' || role === 'Owner' || role === 'Main Branch';
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
    let visibleUninvoicedIds = [];
    const selectedUninvoicedIds = new Set();
    let currentMediaOrderId = null;
    let currentAccountId = null;
    let currentCustomerBranchId = null;
    const invoiceMap = new Map();
    const accountMap = new Map();

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

    const formatPoints = (value) => {
        const num = Number(value ?? 0);
        if (!Number.isFinite(num)) {
            return '0';
        }
        return String(Math.max(0, Math.floor(num)));
    };

    const formatCustomerLabel = (customer) => {
        const phoneValue = customer.phone || customer.portal_phone || '';
        const phone = phoneValue ? ` - ${phoneValue}` : '';
        const countryLabel = customer.profile_country_name ? ` | ${customer.profile_country_name}` : '';
        return `${customer.name} (${customer.code})${countryLabel}${phone}`;
    };

    const renderCustomerStats = (statData) => {
        if (!stats.length) {
            return;
        }
        stats.forEach((el) => {
            const key = el.getAttribute('data-customer-stat');
            let value = statData[key];
            if (['total_invoiced', 'total_paid', 'total_due'].includes(key)) {
                value = formatAmount(value);
            }
            el.textContent = value !== null && value !== undefined && value !== '' ? value : '--';
        });
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

    const disablePaymentForm = () => {
        if (!paymentForm) {
            return;
        }
        paymentForm.querySelectorAll('input, select, button').forEach((el) => {
            el.disabled = true;
        });
    };

    const getPaymentType = () => (paymentTypeSelect ? paymentTypeSelect.value : 'payment');

    const updatePaymentTypeUI = () => {
        const typeValue = getPaymentType();
        const isRefund = typeValue === 'refund';
        const isBalanceAdjust = typeValue === 'charge' || typeValue === 'discount';
        if (paymentReasonField) {
            paymentReasonField.classList.toggle('is-hidden', !isRefund);
        }
        if (paymentReasonSelect) {
            paymentReasonSelect.required = isRefund;
            if (!isRefund) {
                paymentReasonSelect.value = '';
            }
        }
        if (paymentInvoiceSelect) {
            paymentInvoiceSelect.disabled = isRefund || isBalanceAdjust;
            if (isRefund || isBalanceAdjust) {
                paymentInvoiceSelect.value = '';
            }
        }
        if (paymentFromField) {
            paymentFromField.classList.toggle('is-hidden', isBalanceAdjust);
        }
        if (paymentToField) {
            paymentToField.classList.toggle('is-hidden', isBalanceAdjust);
        }
        if (paymentFromAccountSelect) {
            paymentFromAccountSelect.disabled = isBalanceAdjust;
            paymentFromAccountSelect.required = !isBalanceAdjust;
            if (isBalanceAdjust) {
                paymentFromAccountSelect.value = '';
            }
        }
        if (paymentToAccountSelect) {
            paymentToAccountSelect.disabled = isBalanceAdjust;
            paymentToAccountSelect.required = false;
            if (isBalanceAdjust) {
                paymentToAccountSelect.value = '';
            }
        }
        if (paymentSubmitButton) {
            if (typeValue === 'refund') {
                paymentSubmitButton.textContent = 'Record refund';
            } else if (typeValue === 'charge') {
                paymentSubmitButton.textContent = 'Record charge';
            } else if (typeValue === 'discount') {
                paymentSubmitButton.textContent = 'Record discount';
            } else {
                paymentSubmitButton.textContent = 'Record payment';
            }
        }
    };

    if (paymentTypeSelect) {
        if (!canRecordPayments) {
            const paymentOption = paymentTypeSelect.querySelector('option[value="payment"]');
            if (paymentOption) {
                paymentOption.remove();
            }
        }
        if (!canRecordRefunds) {
            const refundOption = paymentTypeSelect.querySelector('option[value="refund"]');
            if (refundOption) {
                refundOption.remove();
            }
        }
        if (!canBalanceAdjust) {
            const chargeOption = paymentTypeSelect.querySelector('option[value="charge"]');
            if (chargeOption) {
                chargeOption.remove();
            }
            const discountOption = paymentTypeSelect.querySelector('option[value="discount"]');
            if (discountOption) {
                discountOption.remove();
            }
        }
        if (!paymentTypeSelect.value) {
            const firstOption = paymentTypeSelect.querySelector('option');
            if (firstOption) {
                paymentTypeSelect.value = firstOption.value;
            }
        }
        if (paymentTypeSelect.options.length <= 1 && paymentTypeField) {
            paymentTypeField.classList.add('is-hidden');
            paymentTypeSelect.disabled = true;
        }
        updatePaymentTypeUI();
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

    const formatInvoiceStatus = (value) => {
        if (!value) {
            return '-';
        }
        if (value === 'partially_paid') {
            return 'Partially paid';
        }
        if (value === 'void') {
            return 'Canceled';
        }
        return value.charAt(0).toUpperCase() + value.slice(1);
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
                    <td>${formatInvoiceStatus(inv.status)}</td>
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
            uninvoicedTable.innerHTML = '<tr><td colspan="7" class="muted">No un-invoiced orders found.</td></tr>';
            if (uninvoicedSelectAll) {
                uninvoicedSelectAll.checked = false;
            }
            selectedUninvoicedIds.clear();
            if (createInvoiceSelectedButton) {
                createInvoiceSelectedButton.disabled = true;
            }
            updatePager(uninvoicedPrev, uninvoicedNext, uninvoicedPageLabel, uninvoicedPage, rows || []);
            return;
        }
        const pageRows = paginateRows(rows, uninvoicedPage);
        visibleUninvoicedIds = pageRows
            .map((order) => (order && order.id !== undefined && order.id !== null ? String(order.id) : ''))
            .filter(Boolean);
        const createInvoiceUrl = `${window.APP_BASE}/views/internal/invoices?create=1&customer_id=${encodeURIComponent(
            String(customerId || '')
        )}`;
        const visibleIds = new Set(visibleUninvoicedIds);
        uninvoicedTable.innerHTML = pageRows
            .map(
                (order) => `<tr>
                    <td>
                        <input type="checkbox" data-customer-uninvoiced-select value="${order.id}" ${
                            selectedUninvoicedIds.has(String(order.id)) ? 'checked' : ''
                        }>
                    </td>
                    <td>${escapeHtml(order.tracking_number || '-')}</td>
                    <td>${escapeHtml(order.shipment_number || order.shipment_id || '-')}</td>
                    <td>${escapeHtml(order.fulfillment_status || '-')}</td>
                    <td>${formatAmount(order.total_price)}</td>
                    <td>${escapeHtml(order.created_at || '-')}</td>
                    <td><a class="button ghost small" href="${createInvoiceUrl}">Create invoice</a></td>
                </tr>`
            )
            .join('');
        uninvoicedTable.querySelectorAll('[data-customer-uninvoiced-select]').forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                const idValue = String(checkbox.value || '');
                if (!idValue) {
                    return;
                }
                if (checkbox.checked) {
                    selectedUninvoicedIds.add(idValue);
                } else {
                    selectedUninvoicedIds.delete(idValue);
                }
                if (createInvoiceSelectedButton) {
                    createInvoiceSelectedButton.disabled = selectedUninvoicedIds.size === 0;
                }
                if (uninvoicedSelectAll) {
                    let allVisibleSelected = visibleIds.size > 0;
                    visibleIds.forEach((id) => {
                        if (!selectedUninvoicedIds.has(id)) {
                            allVisibleSelected = false;
                        }
                    });
                    uninvoicedSelectAll.checked = allVisibleSelected;
                }
            });
        });
        if (uninvoicedSelectAll) {
            let allVisibleSelected = visibleIds.size > 0;
            visibleIds.forEach((id) => {
                if (!selectedUninvoicedIds.has(id)) {
                    allVisibleSelected = false;
                }
            });
            uninvoicedSelectAll.checked = allVisibleSelected;
        }
        if (createInvoiceSelectedButton) {
            createInvoiceSelectedButton.disabled = selectedUninvoicedIds.size === 0;
        }
        updatePager(uninvoicedPrev, uninvoicedNext, uninvoicedPageLabel, uninvoicedPage, rows);
    };

    const renderTransactions = (rows) => {
        if (!transactionsTable) {
            return;
        }
        if (!rows || rows.length === 0) {
            transactionsTable.innerHTML = '<tr><td colspan="7" class="muted">No transactions found.</td></tr>';
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
                    charge: 'Charge',
                    discount: 'Discount',
                };
                const typeLabel = typeLabelMap[typeKey] || typeKey;
                const dateLabel = tx.payment_date || tx.created_at || '-';
                const reasonLabel = tx.reason || tx.note || '-';
                let referenceLabel = '-';
                if (tx.reference_type === 'order') {
                    const tracking = tx.tracking_number || (tx.reference_id ? `Order #${tx.reference_id}` : 'Order');
                    const shipment = tx.shipment_number ? ` | ${tx.shipment_number}` : '';
                    referenceLabel = `${tracking}${shipment}`;
                } else if (tx.reference_type === 'transaction') {
                    referenceLabel = tx.invoice_nos ? `Invoice ${tx.invoice_nos}` : 'Payment';
                } else if (tx.reference_type === 'invoice') {
                    referenceLabel = tx.invoice_no ? `Invoice ${tx.invoice_no}` : `Invoice #${tx.reference_id || ''}`.trim();
                }
                const receiptLink =
                    tx.reference_type === 'transaction' && tx.reference_id
                        ? `<a class="text-link" target="_blank" rel="noopener" href="${window.APP_BASE}/views/internal/transaction_receipt_print?id=${tx.reference_id}">Print</a>`
                        : '-';
                return `<tr>
                    <td>${escapeHtml(typeLabel)}</td>
                    <td>${formatAmount(tx.amount)}</td>
                    <td>${escapeHtml(tx.account_label || tx.payment_method || '-')}</td>
                    <td>${escapeHtml(dateLabel)}</td>
                    <td>${escapeHtml(reasonLabel)}</td>
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
                const actions = [
                    `<a class="text-link" href="${window.APP_BASE}/views/internal/customer_view?id=${profile.id}">Open</a>`,
                ];
                if (canEditProfile) {
                    actions.push(
                        `<a class="text-link" href="${window.APP_BASE}/views/internal/customer_edit?id=${profile.id}">Edit code</a>`
                    );
                    actions.push(
                        `<a class="text-link" href="${window.APP_BASE}/views/internal/customer_info_edit?id=${profile.id}">Edit info</a>`
                    );
                }
                return `<tr>
                        <td>${nameLabel}</td>
                        <td>${profile.code || '-'}</td>
                        <td>${profile.profile_country_name || '-'}</td>
                        <td>${profile.sub_branch_name || '-'}</td>
                        <td>${profile.balance || '0.00'}</td>
                        <td>${profile.portal_username || '-'}</td>
                        <td>${actions.join(' | ')}</td>
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

    const renderAccountOptions = (select, accounts, placeholder) => {
        if (!select) {
            return;
        }
        select.innerHTML = `<option value="">${placeholder}</option>`;
        (accounts || []).forEach((account) => {
            const option = document.createElement('option');
            option.value = account.id;
            const methodLabel = account.payment_method_name ? ` (${account.payment_method_name})` : '';
            option.textContent = `${account.name}${methodLabel}`;
            select.appendChild(option);
            accountMap.set(String(account.id), account);
        });
    };

    const loadPaymentAccounts = async () => {
        if (!paymentFromAccountSelect || !paymentToAccountSelect) {
            return;
        }
        if (!canRecordCustomerPayment) {
            disablePaymentForm();
            return;
        }
        updatePaymentTypeUI();
        const typeValue = getPaymentType();
        const isBalanceAdjust = typeValue === 'charge' || typeValue === 'discount';
        if (isBalanceAdjust) {
            accountMap.clear();
            renderAccountOptions(paymentFromAccountSelect, [], 'Select account');
            renderAccountOptions(paymentToAccountSelect, [], 'Select account');
            return;
        }
        const fromBranchId = currentCustomerBranchId || branchId || null;
        if (!fromBranchId) {
            showPaymentNotice('Branch is required to record payment.', 'error');
            disablePaymentForm();
            return;
        }

        accountMap.clear();
        renderAccountOptions(paymentFromAccountSelect, [], 'Select account');
        renderAccountOptions(paymentToAccountSelect, [], 'Select account');

          const showRefund = typeValue === 'refund';
          if (isBranchRole) {
              if (paymentToField) {
                  paymentToField.classList.add('is-hidden');
              }
              paymentToAccountSelect.required = false;
              paymentToAccountSelect.disabled = true;
              paymentToAccountSelect.value = '';
              if (paymentFromLabel) {
                  paymentFromLabel.textContent = 'Branch account';
              }
          } else {
              if (paymentToField) {
                  paymentToField.classList.toggle('is-hidden', !showRefund);
              }
              paymentToAccountSelect.required = showRefund;
              paymentToAccountSelect.disabled = !showRefund;
              if (!showRefund) {
                  paymentToAccountSelect.value = '';
              }
              if (paymentFromLabel) {
                  paymentFromLabel.textContent = showRefund ? 'Admin account' : 'Branch account';
              }
              if (paymentToLabel) {
                  paymentToLabel.textContent = 'Branch account';
              }
          }

          try {
              if (showRefund) {
                  if (isBranchRole) {
                      const fromParams = new URLSearchParams({ is_active: '1' });
                      fromParams.set('owner_type', 'branch');
                      fromParams.set('owner_id', String(fromBranchId));
                      const fromData = await fetchJson(
                          `${window.APP_BASE}/api/accounts/list.php?${fromParams.toString()}`
                      );
                      renderAccountOptions(paymentFromAccountSelect, fromData.data || [], 'Select branch account');
                  } else {
                      const [adminAccounts, branchAccounts] = await Promise.all([
                          fetchJson(`${window.APP_BASE}/api/accounts/list.php?owner_type=admin&is_active=1`),
                          fetchJson(
                              `${window.APP_BASE}/api/accounts/list.php?owner_type=branch&owner_id=${encodeURIComponent(
                                  fromBranchId
                              )}&is_active=1`
                          ),
                      ]);
                      renderAccountOptions(paymentFromAccountSelect, adminAccounts.data || [], 'Select admin account');
                      renderAccountOptions(paymentToAccountSelect, branchAccounts.data || [], 'Select branch account');
                  }
              } else {
                  const fromParams = new URLSearchParams({ is_active: '1' });
                  fromParams.set('owner_type', 'branch');
                  fromParams.set('owner_id', String(fromBranchId));
                  const fromData = await fetchJson(`${window.APP_BASE}/api/accounts/list.php?${fromParams.toString()}`);
                  renderAccountOptions(paymentFromAccountSelect, fromData.data || [], 'Select branch account');
              }
          } catch (error) {
              showPaymentNotice(`Accounts load failed: ${error.message}`, 'error');
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
        uninvoicedTable.innerHTML = '<tr><td colspan="7" class="loading-cell"><div class="loading-inline"><span class="spinner" aria-hidden="true"></span><span class="loading-text">Orders are loading, please wait...</span></div></td></tr>';
        try {
            const params = new URLSearchParams({
                customer_id: customerId,
                limit: '200',
                include_all: '1',
            });
            const data = await fetchJson(`${window.APP_BASE}/api/orders/uninvoiced.php?${params.toString()}`);
            uninvoicedData = data.data || [];
            uninvoicedPage = 0;
            visibleUninvoicedIds = [];
            selectedUninvoicedIds.clear();
            if (uninvoicedSelectAll) {
                uninvoicedSelectAll.checked = false;
            }
            if (createInvoiceSelectedButton) {
                createInvoiceSelectedButton.disabled = true;
            }
            renderUninvoiced(uninvoicedData);
        } catch (error) {
            uninvoicedTable.innerHTML = '<tr><td colspan="7" class="muted">Unable to load orders.</td></tr>';
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
                    } else if (key === 'points_balance') {
                        value = formatPoints(value);
                    }
                    el.textContent = value !== null && value !== undefined && value !== '' ? value : '--';
                });
                renderCustomerStats(data.stats || {});
              if (addProfileLink) {
                  if (customer.id) {
                      const profileParams = new URLSearchParams();
                      profileParams.set('customer_id', String(customer.id));
                      profileParams.set('add_profile', '1');
                      addProfileLink.href = `${window.APP_BASE}/views/internal/customer_create?${profileParams.toString()}`;
                      addProfileLink.classList.remove('is-hidden');
                  } else {
                      addProfileLink.classList.add('is-hidden');
                      addProfileLink.removeAttribute('href');
                  }
              }
              currentCustomerBranchId = customer.sub_branch_id ? Number(customer.sub_branch_id) : null;
            await loadPaymentAccounts();
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

    if (paymentTypeSelect) {
        paymentTypeSelect.addEventListener('change', () => {
            updatePaymentTypeUI();
            loadPaymentAccounts();
        });
    }

    if (uninvoicedSelectAll) {
        uninvoicedSelectAll.addEventListener('change', () => {
            if (!visibleUninvoicedIds.length) {
                uninvoicedSelectAll.checked = false;
                return;
            }
            if (uninvoicedSelectAll.checked) {
                visibleUninvoicedIds.forEach((id) => selectedUninvoicedIds.add(id));
                if (uninvoicedTable) {
                    uninvoicedTable
                        .querySelectorAll('[data-customer-uninvoiced-select]')
                        .forEach((checkbox) => {
                            checkbox.checked = true;
                        });
                }
            } else {
                visibleUninvoicedIds.forEach((id) => selectedUninvoicedIds.delete(id));
                if (uninvoicedTable) {
                    uninvoicedTable
                        .querySelectorAll('[data-customer-uninvoiced-select]')
                        .forEach((checkbox) => {
                            checkbox.checked = false;
                        });
                }
            }
            if (createInvoiceSelectedButton) {
                createInvoiceSelectedButton.disabled = selectedUninvoicedIds.size === 0;
            }
        });
    }

    if (createInvoiceSelectedButton) {
        createInvoiceSelectedButton.addEventListener('click', () => {
            if (!selectedUninvoicedIds.size) {
                return;
            }
            const params = new URLSearchParams({
                create: '1',
                customer_id: String(customerId || ''),
                order_ids: Array.from(selectedUninvoicedIds).join(','),
            });
            window.location.href = `${window.APP_BASE}/views/internal/invoices?${params.toString()}`;
        });
    }

    if (paymentForm) {
        paymentForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!canRecordCustomerPayment) {
                return;
            }
            const typeValue = getPaymentType();
            const isBalanceAdjust = typeValue === 'charge' || typeValue === 'discount';
            if (typeValue === 'payment' && !canRecordPayments) {
                showPaymentNotice('Customer payments can only be recorded by branch users.', 'error');
                return;
            }
              if (typeValue === 'refund' && !canRecordRefunds) {
                  showPaymentNotice('Refunds can only be recorded by branch users.', 'error');
                  return;
              }
              if (isBalanceAdjust && !canBalanceAdjust) {
                  showPaymentNotice('Balance adjustments require Admin, Owner, or Main Branch access.', 'error');
                  return;
              }
              const amountValue = Number(paymentAmountInput ? paymentAmountInput.value : 0);
              if (!Number.isFinite(amountValue) || amountValue <= 0) {
                  showPaymentNotice('Enter a valid amount.', 'error');
                  return;
              }
              const reasonValue =
                  paymentReasonSelect && typeValue === 'refund' ? String(paymentReasonSelect.value || '') : '';
              if (typeValue === 'refund' && !reasonValue) {
                  showPaymentNotice('Select a refund reason.', 'error');
                  return;
              }
              let fromAccountId = '';
              let toAccountId = '';
              let fromAccount = null;
              if (!isBalanceAdjust) {
                  fromAccountId = paymentFromAccountSelect ? paymentFromAccountSelect.value : '';
                  toAccountId = paymentToAccountSelect ? paymentToAccountSelect.value : '';
                  if (!fromAccountId) {
                      showPaymentNotice('Select the branch account.', 'error');
                      return;
                  }
                  if (typeValue === 'refund' && !isBranchRole && !toAccountId) {
                      showPaymentNotice('Select the branch account.', 'error');
                      return;
                  }
                  fromAccount = accountMap.get(String(fromAccountId));
                  if (!fromAccount || !fromAccount.payment_method_id) {
                      showPaymentNotice('Selected account is missing a payment method.', 'error');
                      return;
                  }
              }
            const invoiceId = typeValue === 'payment' && paymentInvoiceSelect ? paymentInvoiceSelect.value : '';
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
                  type: typeValue,
                  from_account_id: null,
                  to_account_id: null,
                  payment_method_id: fromAccount ? Number(fromAccount.payment_method_id) : null,
                  amount: amountValue,
                  payment_date: paymentDateInput ? paymentDateInput.value : null,
                  whish_phone: paymentWhishInput ? paymentWhishInput.value : null,
                  note: paymentNoteInput ? paymentNoteInput.value : null,
              };
              if (typeValue === 'payment' && invoice) {
                  payload.invoice_id = Number(invoice.id);
              }
              if (!isBalanceAdjust) {
                  if (isBranchRole) {
                      payload.from_account_id = typeValue === 'refund' ? Number(fromAccountId) : null;
                      payload.to_account_id = typeValue === 'refund' ? null : Number(fromAccountId);
                  } else {
                      payload.from_account_id = typeValue === 'refund' ? Number(fromAccountId) : null;
                      payload.to_account_id = typeValue === 'refund' ? Number(toAccountId) : Number(fromAccountId);
                  }
              }
              if (typeValue === 'refund') {
                  payload.reason = reasonValue;
              }
            try {
                const tx = await fetchJson(`${window.APP_BASE}/api/transactions/create.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const typeLabel =
                    typeValue === 'refund'
                        ? 'Refund'
                        : typeValue === 'charge'
                          ? 'Charge'
                          : typeValue === 'discount'
                            ? 'Discount'
                            : 'Payment';
                showPaymentNotice(`${typeLabel} recorded.`, 'success');
                if (paymentForm) {
                    paymentForm.reset();
                    updatePaymentTypeUI();
                }
                await loadCustomerView();
            } catch (error) {
                const typeLabel =
                    typeValue === 'refund'
                        ? 'Refund'
                        : typeValue === 'charge'
                          ? 'Charge'
                          : typeValue === 'discount'
                            ? 'Discount'
                            : 'Payment';
                showPaymentNotice(`${typeLabel} failed: ${error.message}`, 'error');
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

    loadPaymentAccounts();
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
    const unpaidTable = page.querySelector('[data-expenses-unpaid-table]');
    const paymentsTable = page.querySelector('[data-expenses-payments-table]');
    const statusStack = page.querySelector('[data-expenses-status]');
    const refreshButton = page.querySelector('[data-expenses-refresh]');
    const branchFilter = page.querySelector('[data-branch-filter]');
    const prevButton = page.querySelector('[data-expenses-prev]');
    const nextButton = page.querySelector('[data-expenses-next]');
    const pageLabel = page.querySelector('[data-expenses-page]');
    const unpaidPrevButton = page.querySelector('[data-expenses-unpaid-prev]');
    const unpaidNextButton = page.querySelector('[data-expenses-unpaid-next]');
    const unpaidPageLabel = page.querySelector('[data-expenses-unpaid-page]');
    const paymentsPrevButton = page.querySelector('[data-expenses-payments-prev]');
    const paymentsNextButton = page.querySelector('[data-expenses-payments-next]');
    const paymentsPageLabel = page.querySelector('[data-expenses-payments-page]');
    const paymentsTypeSelect = page.querySelector('[data-expenses-payments-type]');
    const paymentsRefreshButton = page.querySelector('[data-expenses-payments-refresh]');
    const tabs = page.querySelector('[data-expenses-tabs]');
    const tabButtons = tabs ? Array.from(tabs.querySelectorAll('[data-expenses-tab]')) : [];
    const tabPanels = tabs ? Array.from(tabs.querySelectorAll('[data-expenses-panel]')) : [];
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
    const createMode = page.getAttribute('data-create-mode') === '1';

    const limit = 5;
    const paymentsLimit = 5;
    let offset = 0;
    let unpaidOffset = 0;
    let paymentsOffset = 0;
    let lastFilters = {};
    let lastPaymentsFilters = {};
    const expenseMap = new Map();
    const unpaidExpenseMap = new Map();
    let adminAccounts = [];

    const escapeHtml = (value) =>
        String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

    const formatAmount = (value) => {
        const num = Number(value ?? 0);
        return Number.isFinite(num) ? num.toFixed(2) : '0.00';
    };

    const setActiveTab = (tabId) => {
        if (!tabId) {
            return;
        }
        tabButtons.forEach((button) => {
            const isActive = button.getAttribute('data-expenses-tab') === tabId;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        tabPanels.forEach((panel) => {
            const isActive = panel.getAttribute('data-expenses-panel') === tabId;
            panel.classList.toggle('is-active', isActive);
        });
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

    const renderAccountSelect = (selectedId = '') => {
        const options = adminAccounts
            .map((account) => {
                const methodLabel = account.payment_method_name ? ` (${account.payment_method_name})` : '';
                const isSelected = String(account.id) === String(selectedId) ? ' selected' : '';
                return `<option value="${account.id}"${isSelected}>${escapeHtml(
                    `${account.name}${methodLabel}`
                )}</option>`;
            })
            .join('');
        return `<select data-expense-pay-account>
            <option value="">Select account</option>
            ${options}
        </select>`;
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

    const loadAccounts = async () => {
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/accounts/list.php?owner_type=admin&is_active=1`);
            adminAccounts = data.data || [];
        } catch (error) {
            showNotice(`Accounts load failed: ${error.message}`, 'error');
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
            tableBody.innerHTML = '<tr><td colspan="8" class="muted">No expenses found.</td></tr>';
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
                const paidLabel = row.is_paid ? 'Yes' : 'No';
                const actions = [];
                if (canEdit && !row.is_paid) {
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
                    <td>${escapeHtml(paidLabel)}</td>
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

    const renderUnpaidRows = (rows) => {
        if (!unpaidTable) {
            return;
        }
        unpaidExpenseMap.clear();
        if (!rows.length) {
            unpaidTable.innerHTML = '<tr><td colspan="7" class="muted">No unpaid expenses found.</td></tr>';
            return;
        }
        unpaidTable.innerHTML = rows
            .map((row) => {
                unpaidExpenseMap.set(String(row.id), row);
                const dateLabel = row.expense_date || row.created_at || '-';
                const shipmentLabel = row.shipment_number
                    ? row.shipment_number
                    : row.shipment_id
                    ? `#${row.shipment_id}`
                    : '-';
                const payControls = canEdit
                    ? `${renderAccountSelect()}<button class="button ghost small" type="button" data-expense-pay data-expense-id="${row.id}">Pay</button>`
                    : '-';
                return `<tr>
                    <td>${escapeHtml(dateLabel)}</td>
                    <td>${escapeHtml(row.title || '-')}</td>
                    <td>${escapeHtml(row.branch_name || '-')}</td>
                    <td>${escapeHtml(shipmentLabel)}</td>
                    <td>${formatAmount(row.amount)}</td>
                    <td>${escapeHtml(row.note || '-')}</td>
                    <td>${payControls}</td>
                </tr>`;
            })
            .join('');
    };

    const renderPayments = (rows) => {
        if (!paymentsTable) {
            return;
        }
        if (!rows.length) {
            paymentsTable.innerHTML = '<tr><td colspan="8" class="muted">No payments found.</td></tr>';
            return;
        }
        const typeLabels = {
            general_expense: 'General expense',
            shipment_expense: 'Shipment expense',
            staff_expense: 'Staff expense',
        };
        paymentsTable.innerHTML = rows
            .map((row) => {
                const dateLabel = row.transfer_date || row.created_at || '-';
                const typeLabel = typeLabels[row.entry_type] || row.entry_type || '-';
                const sourceLabel = row.from_account_name || '-';
                let expenseLabel = row.expense_title || '-';
                if (row.entry_type === 'staff_expense') {
                    const staffLabel = row.staff_name ? `${row.staff_name}` : 'Staff expense';
                    expenseLabel = row.staff_expense_type
                        ? `${staffLabel} (${row.staff_expense_type})`
                        : staffLabel;
                }
                const shipmentLabel = row.shipment_number || '-';
                const statusLabel = row.status || '-';
                let actions = '-';
                if (canEdit && row.status === 'active' && row.entry_type !== 'staff_expense') {
                    if (row.reference_type === 'general_expense' && row.reference_id) {
                        actions = `<button class="text-link" type="button" data-expense-payment-cancel data-expense-id="${row.reference_id}">Cancel</button>`;
                    }
                }
                return `<tr>
                    <td>${escapeHtml(dateLabel)}</td>
                    <td>${escapeHtml(typeLabel)}</td>
                    <td>${escapeHtml(sourceLabel)}</td>
                    <td>${formatAmount(row.amount)}</td>
                    <td>${escapeHtml(expenseLabel)}</td>
                    <td>${escapeHtml(shipmentLabel)}</td>
                    <td>${escapeHtml(statusLabel)}</td>
                    <td>${actions}</td>
                </tr>`;
            })
            .join('');
    };

    const loadExpenses = async (filters = {}) => {
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="8" class="muted">Loading expenses...</td></tr>';
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

    const loadUnpaidExpenses = async (filters = {}) => {
        if (unpaidTable) {
            unpaidTable.innerHTML = '<tr><td colspan="7" class="muted">Loading unpaid expenses...</td></tr>';
        }
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== null && value !== undefined && String(value).trim() !== '') {
                params.append(key, String(value));
            }
        });
        params.append('is_paid', '0');
        params.append('limit', String(limit));
        params.append('offset', String(unpaidOffset));
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/expenses/list.php?${params.toString()}`);
            renderUnpaidRows(data.data || []);
            if (unpaidPrevButton) {
                unpaidPrevButton.disabled = unpaidOffset === 0;
            }
            if (unpaidNextButton) {
                unpaidNextButton.disabled = (data.data || []).length < limit;
            }
            if (unpaidPageLabel) {
                unpaidPageLabel.textContent = `Page ${Math.floor(unpaidOffset / limit) + 1}`;
            }
        } catch (error) {
            renderUnpaidRows([]);
            showNotice(`Unpaid expenses load failed: ${error.message}`, 'error');
        }
    };

    const loadPayments = async (filters = {}) => {
        if (paymentsTable) {
            paymentsTable.innerHTML = '<tr><td colspan="8" class="muted">Loading payments...</td></tr>';
        }
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== null && value !== undefined && String(value).trim() !== '') {
                params.append(key, String(value));
            }
        });
        params.append('limit', String(paymentsLimit));
        params.append('offset', String(paymentsOffset));
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/expenses/payments.php?${params.toString()}`);
            renderPayments(data.data || []);
            if (paymentsPrevButton) {
                paymentsPrevButton.disabled = paymentsOffset === 0;
            }
            if (paymentsNextButton) {
                paymentsNextButton.disabled = (data.data || []).length < paymentsLimit;
            }
            if (paymentsPageLabel) {
                paymentsPageLabel.textContent = `Page ${Math.floor(paymentsOffset / paymentsLimit) + 1}`;
            }
        } catch (error) {
            renderPayments([]);
            showNotice(`Payments load failed: ${error.message}`, 'error');
        }
    };

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(filterForm);
            offset = 0;
            unpaidOffset = 0;
            lastFilters = Object.fromEntries(formData.entries());
            loadExpenses(lastFilters);
            loadUnpaidExpenses(lastFilters);
        });
    }

    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            if (filterForm) {
                const formData = new FormData(filterForm);
                offset = 0;
                unpaidOffset = 0;
                lastFilters = Object.fromEntries(formData.entries());
                loadExpenses(lastFilters);
                loadUnpaidExpenses(lastFilters);
            } else {
                offset = 0;
                unpaidOffset = 0;
                lastFilters = {};
                loadExpenses();
                loadUnpaidExpenses();
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

    if (unpaidPrevButton) {
        unpaidPrevButton.addEventListener('click', () => {
            if (unpaidOffset === 0) {
                return;
            }
            unpaidOffset = Math.max(0, unpaidOffset - limit);
            loadUnpaidExpenses(lastFilters);
        });
    }

    if (unpaidNextButton) {
        unpaidNextButton.addEventListener('click', () => {
            unpaidOffset += limit;
            loadUnpaidExpenses(lastFilters);
        });
    }

    if (paymentsRefreshButton) {
        paymentsRefreshButton.addEventListener('click', () => {
            paymentsOffset = 0;
            const filters = {
                type: paymentsTypeSelect ? paymentsTypeSelect.value : '',
            };
            lastPaymentsFilters = filters;
            loadPayments(filters);
        });
    }

    if (paymentsPrevButton) {
        paymentsPrevButton.addEventListener('click', () => {
            if (paymentsOffset === 0) {
                return;
            }
            paymentsOffset = Math.max(0, paymentsOffset - paymentsLimit);
            loadPayments(lastPaymentsFilters);
        });
    }

    if (paymentsNextButton) {
        paymentsNextButton.addEventListener('click', () => {
            paymentsOffset += paymentsLimit;
            loadPayments(lastPaymentsFilters);
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
                loadUnpaidExpenses(lastFilters);
            } catch (error) {
                showFormNotice(`Save failed: ${error.message}`, 'error');
            }
        });
    }

    if (unpaidTable) {
        unpaidTable.addEventListener('click', async (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            const payButton = target.closest('[data-expense-pay]');
            if (!payButton) {
                return;
            }
            const expenseId = payButton.getAttribute('data-expense-id');
            if (!expenseId) {
                return;
            }
            const row = payButton.closest('tr');
            const accountSelect = row ? row.querySelector('[data-expense-pay-account]') : null;
            const accountId = accountSelect ? accountSelect.value : '';
            if (!accountId) {
                showNotice('Select an admin account to pay this expense.', 'error');
                return;
            }
            try {
                await fetchJson(`${window.APP_BASE}/api/expenses/pay.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        expense_id: expenseId,
                        from_account_id: accountId,
                        payment_date: new Date().toISOString().slice(0, 10),
                    }),
                });
                showNotice('Expense paid.', 'success');
                loadExpenses(lastFilters);
                loadUnpaidExpenses(lastFilters);
                loadPayments(lastPaymentsFilters);
            } catch (error) {
                showNotice(`Payment failed: ${error.message}`, 'error');
            }
        });
    }

    if (paymentsTable) {
        paymentsTable.addEventListener('click', async (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            const cancelButton = target.closest('[data-expense-payment-cancel]');
            if (!cancelButton) {
                return;
            }
            const expenseId = cancelButton.getAttribute('data-expense-id');
            if (!expenseId) {
                return;
            }
            try {
                await fetchJson(`${window.APP_BASE}/api/expenses/cancel_payment.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ expense_id: expenseId }),
                });
                showNotice('Expense payment canceled.', 'success');
                loadExpenses(lastFilters);
                loadUnpaidExpenses(lastFilters);
                loadPayments(lastPaymentsFilters);
            } catch (error) {
                showNotice(`Cancel failed: ${error.message}`, 'error');
            }
        });
    }

    if (tabButtons.length) {
        const activeButton = tabButtons.find((button) => button.classList.contains('is-active'));
        const initialTab = activeButton ? activeButton.getAttribute('data-expenses-tab') : tabButtons[0].getAttribute('data-expenses-tab');
        setActiveTab(initialTab);
        tabButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const tabId = button.getAttribute('data-expenses-tab');
                setActiveTab(tabId);
            });
        });
    }

    if (createMode && canEdit) {
        openDrawer();
    }

    loadBranches();
    loadAccounts();
    loadExpenses();
    loadUnpaidExpenses();
    loadPayments();
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
      const invoiceFormTitle = page.querySelector('[data-invoice-form-title]');
      const invoiceIdInput = page.querySelector('[data-invoice-id]');
      const invoiceSubmitButton = page.querySelector('[data-invoice-submit]');
      const invoiceEditCancelButton = page.querySelector('[data-invoice-edit-cancel]');
    const invoiceCustomerInput = page.querySelector('[data-invoice-customer-input]');
    const invoiceCustomerId = page.querySelector('[data-invoice-customer-id]');
    const invoiceCustomerList = page.querySelector('#invoice-create-customer-options');
    const invoiceCurrencySelect = page.querySelector('[data-invoice-currency]');
      const invoiceBranchId = page.querySelector('[data-invoice-branch-id]');
      const invoiceBranchLabel = page.querySelector('[data-invoice-branch-label]');
      const invoiceOrdersTable = page.querySelector('[data-invoice-orders-table]');
      const invoiceOrdersTotal = page.querySelector('[data-invoice-orders-total]');
      const invoiceOrdersAll = page.querySelector('[data-invoice-orders-all]');
      const pointsInput = page.querySelector('[data-invoice-points-input]');
      const pointsAvailable = page.querySelector('[data-invoice-points-available]');
      const pointsSummary = page.querySelector('[data-invoice-points-summary]');
      const canEdit = page.getAttribute('data-can-edit') === '1';

    const { role, branchId } = getUserContext();
    const fullAccess = ['Admin', 'Owner', 'Main Branch'].includes(role || '');
    const canCancel = ['Admin', 'Owner', 'Main Branch', 'Sub Branch'].includes(role || '');
    const limit = 5;
    let offset = 0;
    let lastFilters = {};
    let invoicesData = [];
        const customerMap = new Map();
        const orderMap = new Map();
        const selectedOrderIds = new Set();
        let ordersData = [];
        let editingInvoiceId = null;
        let selectedBranchId = null;
      let selectedBranchLabel = '';
      let selectedInvoiceCustomerId = null;
      let customerSearchTimer = null;
      let selectedOrdersTotal = 0;
      let availablePoints = 0;
      let pointsValue = 0;

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

    const formatPoints = (value) => {
        const num = Number(value ?? 0);
        if (!Number.isFinite(num)) {
            return '0';
        }
        return String(Math.max(0, Math.floor(num)));
    };

    const setAvailablePoints = (value) => {
        availablePoints = Number(value ?? 0);
        if (!Number.isFinite(availablePoints) || availablePoints < 0) {
            availablePoints = 0;
        }
        if (pointsAvailable) {
            pointsAvailable.value = formatPoints(availablePoints);
        }
    };

    const getPointsUsed = () => {
        if (!pointsInput) {
            return 0;
        }
        const raw = Number(pointsInput.value || 0);
        if (!Number.isFinite(raw)) {
            return 0;
        }
        return Math.max(0, Math.floor(raw));
    };

    const sanitizePointsUsed = (total) => {
        let used = getPointsUsed();
        const maxAvailable = Math.floor(Math.max(0, availablePoints));
        if (used > maxAvailable) {
            used = maxAvailable;
        }
        if (pointsValue > 0) {
            const maxByTotal = Math.floor(total / pointsValue);
            if (used > maxByTotal) {
                used = maxByTotal;
            }
        } else if (used > 0) {
            used = 0;
        }
        if (pointsInput) {
            pointsInput.value = used > 0 ? String(used) : '';
        }
        return used;
    };

    const updatePointsSummary = (total) => {
        const used = sanitizePointsUsed(total);
        const valuePerPoint = pointsValue > 0 ? pointsValue : 0;
        const discount = used > 0 ? used * valuePerPoint : 0;
        const due = Math.max(0, total - discount);
        if (pointsSummary) {
            pointsSummary.textContent = `Points discount: ${formatAmount(discount)} | Due after points: ${formatAmount(
                due
            )} | Point value: ${formatAmount(valuePerPoint)}`;
        }
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
          if (editingInvoiceId) {
              editingInvoiceId = null;
              setInvoiceFormMode(false);
          }
      };

    const setInvoiceOrdersPlaceholder = (message, withSpinner = false) => {
        if (!invoiceOrdersTable) {
            return;
        }
        if (!withSpinner) {
            invoiceOrdersTable.innerHTML = `<tr><td colspan="6" class="muted">${escapeHtml(message)}</td></tr>`;
            return;
        }
        invoiceOrdersTable.innerHTML = `<tr><td colspan="6" class="loading-cell"><div class="loading-inline"><span class="spinner" aria-hidden="true"></span><span class="loading-text">${escapeHtml(message)}</span></div></td></tr>`;
    };

    const updateSelectedTotal = () => {
        let total = 0;
        selectedOrderIds.forEach((orderId) => {
            const order = orderMap.get(orderId);
            if (order) {
                total += Number(order.total_price ?? 0);
            }
        });
        selectedOrdersTotal = total;
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
        updatePointsSummary(total);
    };

      const resetInvoiceOrders = (message = 'Select a customer to load orders.') => {
          selectedOrderIds.clear();
          orderMap.clear();
          ordersData = [];
          selectedBranchId = null;
          selectedBranchLabel = '';
          selectedInvoiceCustomerId = null;
          setAvailablePoints(0);
          if (pointsInput) {
              pointsInput.value = '';
          }
          if (invoiceOrdersAll) {
              invoiceOrdersAll.checked = false;
          }
          setInvoiceOrdersPlaceholder(message);
          updateSelectedTotal();
      };

      const setInvoiceFormMode = (isEditing) => {
          if (invoiceFormTitle) {
              invoiceFormTitle.textContent = isEditing ? 'Edit invoice' : 'Create invoice';
          }
          if (invoiceSubmitButton) {
              invoiceSubmitButton.textContent = isEditing ? 'Update invoice' : 'Create invoice';
          }
          if (invoiceEditCancelButton) {
              invoiceEditCancelButton.classList.toggle('is-hidden', !isEditing);
          }
          if (invoiceIdInput) {
              invoiceIdInput.value = isEditing && editingInvoiceId ? String(editingInvoiceId) : '';
          }
        if (invoiceCustomerInput) {
            invoiceCustomerInput.readOnly = isEditing;
        }
        if (pointsInput) {
            pointsInput.disabled = false;
        }
          if (form) {
              const invoiceNoInput = form.querySelector('[name="invoice_no"]');
              const issuedAtInput = form.querySelector('[name="issued_at"]');
              if (invoiceNoInput) {
                  invoiceNoInput.disabled = isEditing;
              }
              if (issuedAtInput) {
                  issuedAtInput.disabled = isEditing;
              }
              form.querySelectorAll('input[name="delivery_type"]').forEach((input) => {
                  input.disabled = isEditing;
              });
          }
      };

      const findCustomerLabelById = (customerIdValue) => {
          const idValue = String(customerIdValue);
          for (const [label, data] of customerMap.entries()) {
              if (String(data.id) === idValue) {
                  return label;
              }
          }
          return `Customer #${idValue}`;
      };

      const enterInvoiceEditMode = async (invoiceIdValue, invoiceRow) => {
          if (!invoiceIdValue) {
              return;
          }
          try {
              const data = await fetchJson(
                  `${window.APP_BASE}/api/invoices/view.php?id=${encodeURIComponent(invoiceIdValue)}`
              );
              const invoice = data.invoice;
              const items = data.items || [];
              if (!invoice) {
                  showFormNotice('Invoice not found.', 'error');
                  return;
              }
              if (invoice.status === 'void' || Number(invoice.paid_total ?? 0) > 0) {
                  showFormNotice('Invoices with payments cannot be edited.', 'error');
                  return;
              }
              editingInvoiceId = Number(invoice.id || 0);
              if (invoiceCustomerInput) {
                  invoiceCustomerInput.value = findCustomerLabelById(invoice.customer_id);
              }
              if (invoiceCustomerId) {
                  invoiceCustomerId.value = String(invoice.customer_id);
              }
              selectedInvoiceCustomerId = String(invoice.customer_id);
              if (invoiceCurrencySelect) {
                  invoiceCurrencySelect.value = (invoice.currency || 'USD').toUpperCase();
              }
              if (form) {
                  const noteInput = form.querySelector('[name="note"]');
                  if (noteInput) {
                      noteInput.value = invoice.note || '';
                  }
                  const invoiceNoInput = form.querySelector('[name="invoice_no"]');
                  if (invoiceNoInput) {
                      invoiceNoInput.value = invoice.invoice_no || '';
                  }
                  const issuedAtInput = form.querySelector('[name="issued_at"]');
                  if (issuedAtInput && invoice.issued_at) {
                      issuedAtInput.value = invoice.issued_at.replace(' ', 'T').slice(0, 16);
                  }
                  const deliveryType = items[0]?.order_snapshot?.delivery_type;
                  if (deliveryType) {
                      const deliveryInput = form.querySelector(
                          `input[name="delivery_type"][value="${deliveryType}"]`
                      );
                      if (deliveryInput) {
                          deliveryInput.checked = true;
                      }
                  }
              }
              const pointsUsedValue = Number(invoice.points_used ?? 0);
              if (pointsInput) {
                  pointsInput.value = pointsUsedValue > 0 ? String(pointsUsedValue) : '';
              }
              await loadCustomerPoints(invoice.customer_id, pointsUsedValue);
              const orderIds = items.map((item) => String(item.order_id)).filter(Boolean);
              await loadUninvoicedOrders(invoice.customer_id, invoice.id, orderIds);
              setInvoiceFormMode(true);
              if (invoiceFormTitle && invoiceRow) {
                  invoiceFormTitle.textContent = `Edit invoice ${invoiceRow.invoice_no || ''}`.trim();
              }
              openDrawer();
          } catch (error) {
              showFormNotice(`Edit load failed: ${error.message}`, 'error');
          }
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

      const renderInvoiceOrders = (preselectedIds = null) => {
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
          if (Array.isArray(preselectedIds) && preselectedIds.length) {
              preselectedIds.forEach((orderId) => {
                  const checkbox = invoiceOrdersTable.querySelector(
                      `[data-invoice-order][value="${orderId}"]`
                  );
                  if (!checkbox) {
                      return;
                  }
                  checkbox.checked = true;
                  toggleInvoiceOrder(String(orderId), true, checkbox);
              });
          }
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
            tableBody.innerHTML = '<tr><td colspan="10" class="muted">No invoices found.</td></tr>';
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
                } else if (statusLabel === 'void') {
                    statusLabel = 'Canceled';
                } else if (statusLabel !== '-' && statusLabel.length > 0) {
                    statusLabel = statusLabel.charAt(0).toUpperCase() + statusLabel.slice(1);
                }
                  const actions = [
                      `<a class="text-link" href="${window.APP_BASE}/api/invoices/print.php?id=${row.id}" target="_blank" rel="noopener">Print</a>`,
                  ];
                  if (canEdit && Number(row.paid_total ?? 0) === 0 && row.status !== 'void') {
                      actions.push(
                          `<button class="text-link" type="button" data-invoice-edit data-invoice-id="${row.id}">Edit</button>`
                      );
                  }
                  if (canCancel && row.status !== 'void') {
                      actions.push(
                          `<button class="text-link" type="button" data-invoice-cancel data-invoice-id="${row.id}">Cancel</button>`
                      );
                  }
                return `<tr>
                    <td>${escapeHtml(row.invoice_no || '-')}</td>
                    <td>${escapeHtml(row.customer_name || '-')}</td>
                    <td>${escapeHtml(row.branch_name || '-')}</td>
                    <td>${escapeHtml(statusLabel)}</td>
                    <td>${escapeHtml(row.currency || 'USD')}</td>
                    <td>${formatAmount(row.total)}</td>
                    <td>${formatAmount(row.paid_total)}</td>
                    <td>${formatAmount(row.due_total)}</td>
                    <td>${escapeHtml(issuedMeta)}</td>
                    <td>${actions.join(' | ')}</td>
                </tr>`;
            })
            .join('');
        updatePager(rows.length);

          if (canEdit) {
              tableBody.querySelectorAll('[data-invoice-edit]').forEach((button) => {
                  button.addEventListener('click', async () => {
                      const invoiceId = button.getAttribute('data-invoice-id');
                      if (!invoiceId) {
                          return;
                      }
                      const row = rows.find((item) => String(item.id) === String(invoiceId));
                      await enterInvoiceEditMode(invoiceId, row || null);
                  });
              });
          }

          if (canCancel) {
              tableBody.querySelectorAll('[data-invoice-cancel]').forEach((button) => {
                  button.addEventListener('click', async () => {
                      const invoiceId = button.getAttribute('data-invoice-id');
                    if (!invoiceId) {
                        return;
                    }
                    const reason = await showConfirmDialog({
                        title: 'Cancel invoice',
                        message: 'Cancel this invoice? Add a reason.',
                        confirmLabel: 'Cancel invoice',
                        requireInput: true,
                        inputPlaceholder: 'Reason'
                    });
                    if (!reason || !reason.trim()) {
                        return;
                    }
                    try {
                        await fetchJson(`${window.APP_BASE}/api/invoices/delete.php`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ invoice_id: invoiceId, reason: reason.trim() }),
                        });
                        showNotice('Invoice canceled.', 'success');
                        if (offset > 0 && invoicesData.length === 1) {
                            offset = Math.max(0, offset - limit);
                        }
                        loadInvoices(lastFilters);
                    } catch (error) {
                        showNotice(`Cancel failed: ${error.message}`, 'error');
                    }
                });
            });
        }
    };

    const loadInvoices = async (filters = {}) => {
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="10" class="muted">Loading invoices...</td></tr>';
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
                const label = formatCustomerLabel(customer);
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

      const loadPointsSettings = async () => {
          try {
              const data = await fetchJson(`${window.APP_BASE}/api/company/points.php`);
              pointsValue = Number(data.data?.points_value ?? 0);
              if (!Number.isFinite(pointsValue) || pointsValue < 0) {
                  pointsValue = 0;
              }
          } catch (error) {
              pointsValue = 0;
          }
          updateSelectedTotal();
      };

    const loadCustomerPoints = async (customerIdValue, extraPoints = 0) => {
        if (!customerIdValue) {
            setAvailablePoints(0);
            updateSelectedTotal();
            return;
        }
        try {
            const data = await fetchJson(
                `${window.APP_BASE}/api/customers/view.php?customer_id=${encodeURIComponent(customerIdValue)}`
            );
            const points = Number(data.customer?.points_balance ?? 0);
            const extraValue = Number(extraPoints ?? 0);
            const totalPoints = points + (Number.isFinite(extraValue) ? extraValue : 0);
            setAvailablePoints(totalPoints);
        } catch (error) {
            setAvailablePoints(0);
        }
        updateSelectedTotal();
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

      const loadUninvoicedOrders = async (customerIdValue, includeInvoiceId = null, preselectedIds = null) => {
          if (!invoiceOrdersTable) {
              return;
          }
          setInvoiceOrdersPlaceholder('Orders are loading, please wait...', true);
          try {
              const params = new URLSearchParams({ customer_id: String(customerIdValue), limit: '200' });
              if (includeInvoiceId) {
                  params.set('include_invoice_id', String(includeInvoiceId));
              }
              const data = await fetchJson(`${window.APP_BASE}/api/orders/uninvoiced.php?${params.toString()}`);
              ordersData = data.data || [];
              renderInvoiceOrders(preselectedIds);
          } catch (error) {
              ordersData = [];
              renderInvoiceOrders(preselectedIds);
              showFormNotice(`Orders load failed: ${error.message}`, 'error');
          }
      };

    const applyInvoiceCustomer = async (customerIdValue, preselectedIds = null) => {
        if (!customerIdValue) {
            return;
        }
        try {
            const data = await fetchJson(
                `${window.APP_BASE}/api/customers/view.php?customer_id=${encodeURIComponent(customerIdValue)}`
            );
            const customer = data.customer;
            if (!customer) {
                showFormNotice('Customer not found.', 'error');
                return;
            }
            const label = formatCustomerLabel(customer);
            if (invoiceCustomerInput) {
                invoiceCustomerInput.value = label;
            }
            if (invoiceCustomerId) {
                invoiceCustomerId.value = String(customer.id);
            }
            selectedInvoiceCustomerId = String(customer.id);
            await loadUninvoicedOrders(customer.id, null, preselectedIds);
            await loadCustomerPoints(customer.id);
        } catch (error) {
            showFormNotice(`Customer load failed: ${error.message}`, 'error');
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
                      loadCustomerPoints(match.data.id);
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
              loadCustomerPoints(match.data.id);
          });
      }

      if (invoiceOrdersAll) {
          invoiceOrdersAll.addEventListener('change', toggleAllOrders);
      }

      if (pointsInput) {
          pointsInput.addEventListener('input', () => {
              updateSelectedTotal();
          });
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
              editingInvoiceId = null;
              setInvoiceFormMode(false);
              resetInvoiceOrders('Select a customer to load orders.');
              if (form) {
                  form.reset();
              }
              if (invoiceCurrencySelect) {
                  invoiceCurrencySelect.value = 'USD';
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
              const isEditing = Boolean(editingInvoiceId);
              if (selectedOrderIds.size === 0) {
                  showFormNotice('Select at least one order to invoice.', 'error');
                  return;
              }

                if (isEditing) {
                    const currencyValue = invoiceCurrencySelect ? invoiceCurrencySelect.value : '';
                    if (!currencyValue) {
                        showFormNotice('Currency is required.', 'error');
                        return;
                    }
                    const noteInput = form.querySelector('[name="note"]');
                    const pointsUsed = pointsInput ? sanitizePointsUsed(selectedOrdersTotal) : 0;
                    const payload = {
                        invoice_id: editingInvoiceId,
                        order_ids: Array.from(selectedOrderIds, (value) => Number(value)),
                        currency: currencyValue,
                        note: noteInput ? String(noteInput.value || '') : '',
                        points_used: String(pointsUsed),
                    };
                    try {
                        await fetchJson(`${window.APP_BASE}/api/invoices/update.php`, {
                            method: 'PATCH',
                          headers: { 'Content-Type': 'application/json' },
                          body: JSON.stringify(payload),
                      });
                      showNotice('Invoice updated.', 'success');
                      closeDrawer();
                      editingInvoiceId = null;
                      setInvoiceFormMode(false);
                      resetInvoiceOrders('Select a customer to load orders.');
                      if (form) {
                          form.reset();
                      }
                      loadInvoices(lastFilters);
                  } catch (error) {
                      showFormNotice(`Update failed: ${error.message}`, 'error');
                  }
                  return;
              }

              const payload = Object.fromEntries(new FormData(form).entries());
              const customerIdValue = payload.customer_id ? String(payload.customer_id).trim() : '';
              if (!customerIdValue) {
                  showFormNotice('Customer is required.', 'error');
                  return;
              }
              if (!selectedBranchId) {
                  showFormNotice('Branch is required for invoicing.', 'error');
                  return;
              }
              const deliveryTypeValue = payload.delivery_type ? String(payload.delivery_type).trim() : '';
              if (!deliveryTypeValue) {
                  showFormNotice('Select a delivery method.', 'error');
                  return;
              }
              payload.customer_id = customerIdValue;
              payload.branch_id = String(selectedBranchId);
              payload.delivery_type = deliveryTypeValue;
              payload.order_ids = Array.from(selectedOrderIds, (value) => Number(value));
              if (!payload.currency || String(payload.currency).trim() === '') {
                  delete payload.currency;
              }
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
              if (pointsInput) {
                  const pointsUsed = sanitizePointsUsed(selectedOrdersTotal);
                  if (pointsUsed > 0) {
                      payload.points_used = String(pointsUsed);
                  } else {
                      delete payload.points_used;
                  }
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

      setInvoiceFormMode(false);
      loadBranches();
        loadPointsSettings();
      loadCustomers();
      resetInvoiceOrders('Select a customer to load orders.');
      loadInvoices();
      (async () => {
          const params = new URLSearchParams(window.location.search || '');
          const shouldCreate = params.get('create') === '1';
          const requestedCustomerId = params.get('customer_id');
          const requestedOrderIds = (params.get('order_ids') || '')
              .split(',')
              .map((value) => value.trim())
              .filter(Boolean);
          if (!shouldCreate && !requestedCustomerId) {
              return;
          }
          if (!canEdit) {
              return;
          }
          openDrawer();
          if (requestedCustomerId) {
              await applyInvoiceCustomer(requestedCustomerId, requestedOrderIds.length ? requestedOrderIds : null);
          }
      })();
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
    const branchBalancePanel = page.querySelector('[data-branch-balance-panel]');
    const branchBalanceValue = page.querySelector('[data-branch-balance]');
    const customerBalancePanel = page.querySelector('[data-customer-balance-panel]');
    const customerBalanceTable = page.querySelector('[data-customer-balance-table]');
    const canCancel = page.getAttribute('data-can-cancel') === '1';
    const { role } = getUserContext();

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

    const formatStatus = (value) => {
        if (!value) {
            return 'Active';
        }
        return value === 'canceled' ? 'Canceled' : 'Active';
    };

    const setBranchBalance = (value) => {
        if (!branchBalancePanel || !branchBalanceValue) {
            return;
        }
        if (value === null || value === undefined) {
            branchBalancePanel.classList.add('is-hidden');
            return;
        }
        branchBalanceValue.textContent = formatAmount(value);
        branchBalancePanel.classList.remove('is-hidden');
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
            tableBody.innerHTML = '<tr><td colspan="13" class="muted">No transactions found.</td></tr>';
            return;
        }
        tableBody.innerHTML = rows
            .map((row) => {
                const dateLabel = row.payment_date || row.created_at || '-';
                const receiptLink = row.id
                    ? `<a class="text-link" target="_blank" rel="noopener" href="${window.APP_BASE}/views/internal/transaction_receipt_print?id=${row.id}">Print</a>`
                    : '-';
                const noteLabel =
                    row.status === 'canceled'
                        ? row.canceled_reason
                            ? `Canceled: ${row.canceled_reason}`
                            : 'Canceled'
                        : row.note || '-';
                const actions =
                    canCancel && row.status === 'active'
                        ? `<button class="text-link" type="button" data-transaction-cancel data-transaction-id="${row.id}">Cancel</button>`
                        : '-';
                return `<tr>
                    <td>${escapeHtml(row.id)}</td>
                    <td>${escapeHtml(row.customer_name || '-')}</td>
                    <td>${escapeHtml(row.branch_name || '-')}</td>
                    <td>${escapeHtml(row.type || '-')}</td>
                    <td>${escapeHtml(formatStatus(row.status))}</td>
                    <td>${escapeHtml(row.account_label || row.payment_method || '-')}</td>
                    <td>${escapeHtml(row.currency || 'USD')}</td>
                    <td>${formatAmount(row.amount)}</td>
                    <td>${escapeHtml(dateLabel)}</td>
                    <td>${escapeHtml(row.reason || '-')}</td>
                    <td>${escapeHtml(noteLabel)}</td>
                    <td>${receiptLink}</td>
                    <td>${actions}</td>
                </tr>`;
            })
            .join('');

        if (canCancel) {
            tableBody.querySelectorAll('[data-transaction-cancel]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const id = button.getAttribute('data-transaction-id');
                    if (!id) {
                        return;
                    }
                    const reason = await showConfirmDialog({
                        title: 'Cancel receipt',
                        message: 'Cancel this receipt? Add a reason.',
                        confirmLabel: 'Cancel receipt',
                        requireInput: true,
                        inputPlaceholder: 'Reason'
                    });
                    if (!reason || !reason.trim()) {
                        return;
                    }
                    try {
                        await fetchJson(`${window.APP_BASE}/api/transactions/delete.php`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ transaction_id: id, reason: reason.trim() }),
                        });
                        showNotice('Transaction canceled.', 'success');
                        loadTransactions();
                    } catch (error) {
                        showNotice(`Cancel failed: ${error.message}`, 'error');
                    }
                });
            });
        }
    };

    const loadTransactions = async () => {
        if (!fromInput || !toInput) {
            return;
        }
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="13" class="muted">Loading transactions...</td></tr>';
        }
        const params = new URLSearchParams({
            date_from: fromInput.value,
            date_to: toInput.value,
            limit: '200',
        });
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/transactions/list.php?${params.toString()}`);
            setBranchBalance(data.branch_balance);
            renderRows(data.data || []);
        } catch (error) {
            setBranchBalance(null);
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

    const renderCustomerBalances = (rows) => {
        if (!customerBalanceTable) {
            return;
        }
        if (!rows || rows.length === 0) {
            customerBalanceTable.innerHTML = '<tr><td colspan="5" class="muted">No customer balances found.</td></tr>';
            return;
        }
        customerBalanceTable.innerHTML = rows
            .map((row) => {
                const viewLink = row.id
                    ? `<a class="text-link" href="${window.APP_BASE}/views/internal/customer_view?id=${row.id}">Open</a>`
                    : '-';
                return `<tr>
                    <td>${escapeHtml(row.name || '-')}</td>
                    <td>${escapeHtml(row.code || '-')}</td>
                    <td>${escapeHtml(row.phone || row.portal_phone || '-')}</td>
                    <td>${formatAmount(row.balance)}</td>
                    <td>${viewLink}</td>
                </tr>`;
            })
            .join('');
    };

    const loadCustomerBalances = async () => {
        if (!customerBalancePanel || !customerBalanceTable || role !== 'Sub Branch') {
            return;
        }
        customerBalancePanel.classList.remove('is-hidden');
        customerBalanceTable.innerHTML = '<tr><td colspan="5" class="muted">Loading customers...</td></tr>';
        try {
            const params = new URLSearchParams({ non_zero: '1', limit: '200' });
            const data = await fetchJson(`${window.APP_BASE}/api/customers/list.php?${params.toString()}`);
            renderCustomerBalances(data.data || []);
        } catch (error) {
            customerBalanceTable.innerHTML =
                '<tr><td colspan="5" class="muted">Unable to load customers.</td></tr>';
        }
    };
    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            loadTransactions();
        });
    }

    setDefaultDates();
    loadTransactions();
    loadCustomerBalances();
}

function initReportsPage() {
    const page = document.querySelector('[data-reports-page]');
    if (!page) {
        return;
    }

    const form = page.querySelector('[data-reports-form]');
    const reportSelect = page.querySelector('[data-report-type]');
    const shipmentSelect = page.querySelector('[data-report-shipment-select]');
    const branchSelect = page.querySelector('[data-report-branch-select]');
    const branchFixedInput = page.querySelector('[data-report-branch-fixed]');
    const shipmentField = page.querySelector('[data-report-field="shipment"]');
    const originField = page.querySelector('[data-report-field="origin"]');
    const branchField = page.querySelector('[data-report-field="branch"]');
    const modeField = page.querySelector('[data-report-field="mode"]');
    const modeSelect = page.querySelector('[data-report-mode]');
    const dateFrom = page.querySelector('[data-report-date-from]');
    const dateTo = page.querySelector('[data-report-date-to]');
    const originSelect = page.querySelector('[data-report-origin-select]');
    const frame = page.querySelector('[data-report-frame]');
    const openLink = page.querySelector('[data-report-open]');
    const printButton = page.querySelector('[data-report-print]');
    const statusStack = page.querySelector('[data-report-status]');
    const descriptionEl = page.querySelector('[data-report-description]');

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

    const setDefaultDates = () => {
        if (!dateFrom || !dateTo) {
            return;
        }
        if (!dateFrom.value) {
            const start = new Date();
            start.setDate(1);
            dateFrom.value = start.toISOString().slice(0, 10);
        }
        if (!dateTo.value) {
            const end = new Date(dateFrom.value || new Date());
            end.setMonth(end.getMonth() + 1, 0);
            dateTo.value = end.toISOString().slice(0, 10);
        }
    };

    const clearDynamicOptions = (select) => {
        if (!select) {
            return;
        }
        select.querySelectorAll('option[data-dynamic]').forEach((option) => option.remove());
    };

    const loadShipments = async () => {
        if (!shipmentSelect) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/shipments/list.php?limit=200`);
            const rows = data.data || [];
            clearDynamicOptions(shipmentSelect);
            rows.forEach((shipment) => {
                const option = document.createElement('option');
                option.value = shipment.id;
                option.textContent = shipment.shipment_number || `#${shipment.id}`;
                option.setAttribute('data-dynamic', 'true');
                shipmentSelect.appendChild(option);
            });
        } catch (error) {
            console.warn('Shipments load failed', error);
        }
    };

    const loadBranches = async () => {
        if (!branchSelect) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/branches/list.php?limit=200`);
            const rows = data.data || [];
            clearDynamicOptions(branchSelect);
            rows.forEach((branch) => {
                const option = document.createElement('option');
                option.value = branch.id;
                option.textContent = branch.name;
                option.setAttribute('data-dynamic', 'true');
                branchSelect.appendChild(option);
            });
        } catch (error) {
            console.warn('Branches load failed', error);
        }
    };

    let originsLoaded = false;
    const loadOrigins = async () => {
        if (!originSelect) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/shipments/origins.php`);
            const rows = data.data || [];
            clearDynamicOptions(originSelect);
            rows.forEach((origin) => {
                const option = document.createElement('option');
                option.value = origin.id;
                option.textContent = origin.name;
                option.setAttribute('data-dynamic', 'true');
                originSelect.appendChild(option);
            });
            originsLoaded = true;
        } catch (error) {
            console.warn('Origins load failed', error);
        }
    };

    const getMeta = () => {
        const option = reportSelect?.selectedOptions?.[0];
        return {
            url: option?.dataset.url || '',
            branch: option?.dataset.branch || 'none',
            shipment: option?.dataset.shipment || 'none',
            mode: option?.dataset.mode === '1',
            description: option?.dataset.description || '',
        };
    };

    const updateFields = () => {
        const meta = getMeta();
        const isShipmentExpenses = reportSelect?.value === 'expenses_shipment';
        if (shipmentField) {
            shipmentField.classList.toggle('is-hidden', meta.shipment === 'none');
        }
        if (originField) {
            originField.classList.toggle('is-hidden', !isShipmentExpenses);
        }
        if (branchField) {
            branchField.classList.toggle('is-hidden', meta.branch === 'none');
        }
        if (modeField) {
            modeField.classList.toggle('is-hidden', !meta.mode);
        }
        if (branchSelect) {
            branchSelect.required = meta.branch === 'required';
        }
        if (branchFixedInput) {
            branchFixedInput.required = meta.branch === 'required';
        }
        if (descriptionEl) {
            descriptionEl.textContent = meta.description || 'Choose a report to preview it here.';
        }
        if (isShipmentExpenses && !originsLoaded) {
            loadOrigins();
        }
    };

    const buildUrl = () => {
        const meta = getMeta();
        if (!meta.url) {
            return '';
        }
        const params = new URLSearchParams();
        if (dateFrom?.value) {
            params.append('date_from', dateFrom.value);
        }
        if (dateTo?.value) {
            params.append('date_to', dateTo.value);
        }
        if (meta.shipment !== 'none' && shipmentSelect?.value) {
            params.append('shipment_id', shipmentSelect.value);
        }
        if (reportSelect?.value === 'expenses_shipment' && originSelect?.value) {
            params.append('origin_country_id', originSelect.value);
        }
        if (meta.branch !== 'none') {
            const branchValue = branchSelect?.value || branchFixedInput?.value || '';
            if (branchValue) {
                params.append('branch_id', branchValue);
            }
        }
        if (meta.mode && modeSelect?.value) {
            params.append('mode', modeSelect.value);
        }
        return `${meta.url}?${params.toString()}`;
    };

    const setPreview = (url) => {
        if (!frame || !openLink || !printButton) {
            return;
        }
        if (!url) {
            frame.removeAttribute('src');
            openLink.href = '#';
            openLink.classList.add('is-disabled');
            printButton.disabled = true;
            return;
        }
        frame.src = url;
        openLink.href = url;
        openLink.classList.remove('is-disabled');
        printButton.disabled = false;
    };

    if (printButton) {
        printButton.addEventListener('click', () => {
            if (frame?.contentWindow) {
                frame.contentWindow.print();
            }
        });
    }

    if (form) {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const meta = getMeta();
            if (!meta.url) {
                showNotice('Select a report to generate.', 'error');
                return;
            }
            if (meta.branch === 'required') {
                const branchValue = branchSelect?.value || branchFixedInput?.value || '';
                if (!branchValue) {
                    showNotice('Branch is required for this report.', 'error');
                    return;
                }
            }
            if (meta.shipment === 'required' && !shipmentSelect?.value) {
                showNotice('Shipment is required for this report.', 'error');
                return;
            }
            const url = buildUrl();
            setPreview(url);
        });
    }

    if (reportSelect) {
        reportSelect.addEventListener('change', () => {
            updateFields();
            setPreview('');
        });
    }

    if (reportSelect) {
        reportSelect.value = '';
    }
    setPreview('');
    updateFields();
    setDefaultDates();
    loadShipments();
    loadBranches();
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
    const prevButton = page.querySelector('[data-partners-prev]');
    const nextButton = page.querySelector('[data-partners-next]');
    const pageLabel = page.querySelector('[data-partners-page-label]');

    const limit = 10;
    let offset = 0;
    let lastFilters = {};

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

    const formatBalanceBadge = (value) => {
        const num = Number(value ?? 0);
        if (!Number.isFinite(num)) {
            return '<span class=\"badge neutral\">Unknown</span>';
        }
        if (num > 0) {
            return `<span class=\"badge info\">Payable (we owe) ${formatAmount(num)}</span>`;
        }
        if (num < 0) {
            return `<span class=\"badge warning\">Receivable/Refund ${formatAmount(Math.abs(num))}</span>`;
        }
        return '<span class=\"badge neutral\">Settled</span>';
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

    const renderRows = (rows = []) => {
        if (!tableBody) {
            return;
        }
        if (!rows.length) {
            tableBody.innerHTML = '<tr><td colspan=\"5\" class=\"muted\">No partners found.</td></tr>';
            return;
        }
        tableBody.innerHTML = rows
            .map((row) => {
                return `<tr>
                    <td>${escapeHtml(row.name || '-')}</td>
                    <td>${escapeHtml(row.type || '-')}</td>
                    <td>${formatBalanceBadge(row.current_balance)}</td>
                    <td>${escapeHtml(row.status || '-')}</td>
                    <td><a class=\"text-link\" href=\"${window.APP_BASE}/views/internal/partner_view?id=${row.id}\">Open</a></td>
                </tr>`;
            })
            .join('');
    };

    const loadPartners = async (filters = {}) => {
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan=\"5\" class=\"muted\">Loading partners...</td></tr>';
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
                loadPartners(lastFilters);
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

    loadPartners();
}

function initPartnerCreate() {
    const page = document.querySelector('[data-partner-create]');
    if (!page) {
        return;
    }

    const form = page.querySelector('[data-partner-create-form]');
    const statusStack = page.querySelector('[data-partner-create-status]');

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

    if (form) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (statusStack) {
                statusStack.innerHTML = '';
            }
            const formData = new FormData(form);
            const payload = Object.fromEntries(formData.entries());
            if (!payload.type || !payload.name) {
                showNotice('Type and name are required.', 'error');
                return;
            }
            try {
                await fetchJson(`${window.APP_BASE}/api/partners/create.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showNotice('Partner created.', 'success');
                form.reset();
            } catch (error) {
                showNotice(`Create failed: ${error.message}`, 'error');
            }
        });
    }
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

    const canEdit = page.getAttribute('data-can-edit') === '1';
    const detailFields = page.querySelectorAll('[data-partner-detail]');
    const balanceLabel = page.querySelector('[data-partner-balance-label]');
    const balanceDate = page.querySelector('[data-partner-balance-date]');
    const statusStack = page.querySelector('[data-partner-status]');
    const updateForm = page.querySelector('[data-partner-update-form]');
    const updateStatus = page.querySelector('[data-partner-update-status]');
    const txForm = page.querySelector('[data-partner-tx-form]');
    const txStatus = page.querySelector('[data-partner-tx-status]');
    const transferForm = page.querySelector('[data-partner-transfer-form]');
    const transferStatus = page.querySelector('[data-partner-transfer-status]');
    const statementForm = page.querySelector('[data-partner-statement-filter]');
    const statementRefresh = page.querySelector('[data-partner-statement-refresh]');
    const transactionsTable = page.querySelector('[data-partner-transactions]');
    const transactionsPrev = page.querySelector('[data-partner-transactions-prev]');
    const transactionsNext = page.querySelector('[data-partner-transactions-next]');
    const transactionsPageLabel = page.querySelector('[data-partner-transactions-page]');
    const transactionsStatus = page.querySelector('[data-partner-transactions-status]');
    const fromAccountField = page.querySelector('[data-partner-from-account]');
    const toAccountField = page.querySelector('[data-partner-to-account]');
    const fromAccountSelect = fromAccountField ? fromAccountField.querySelector('select') : null;
    const toAccountSelect = toAccountField ? toAccountField.querySelector('select') : null;

    const limit = 10;
    let offset = 0;
    let currentFilters = {};
    const partnerOptions = new Map();

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

    const formatSigned = (value) => {
        if (value === null || value === undefined) {
            return '--';
        }
        const num = Number(value ?? 0);
        if (!Number.isFinite(num)) {
            return '--';
        }
        const sign = num > 0 ? '+' : num < 0 ? '-' : '';
        return `${sign}${formatAmount(Math.abs(num))}`;
    };

    const formatBalanceLabel = (value) => {
        const num = Number(value ?? 0);
        if (!Number.isFinite(num)) {
            return 'Unknown';
        }
        if (num > 0) {
            return `Payable (we owe) ${formatAmount(num)}`;
        }
        if (num < 0) {
            return `Receivable/Refund (partner owes us) ${formatAmount(Math.abs(num))}`;
        }
        return 'Settled';
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

    const loadPartner = async () => {
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/partners/get.php?id=${encodeURIComponent(partnerId)}`);
            const partner = data.partner || {};
            detailFields.forEach((el) => {
                const key = el.getAttribute('data-partner-detail');
                let value = partner[key];
                if (['opening_balance', 'current_balance'].includes(key)) {
                    value = formatAmount(value);
                }
                el.textContent = value !== null && value !== undefined && value !== '' ? value : '--';
            });
            if (balanceLabel) {
                balanceLabel.textContent = formatBalanceLabel(partner.current_balance);
            }
            if (balanceDate) {
                const now = new Date();
                balanceDate.textContent = now.toLocaleDateString();
            }
            if (updateForm) {
                updateForm.querySelector('[name=\"type\"]').value = partner.type || '';
                updateForm.querySelector('[name=\"name\"]').value = partner.name || '';
                updateForm.querySelector('[name=\"phone\"]').value = partner.phone || '';
                updateForm.querySelector('[name=\"email\"]').value = partner.email || '';
                updateForm.querySelector('[name=\"address\"]').value = partner.address || '';
                updateForm.querySelector('[name=\"status\"]').value = partner.status || 'active';
            }
        } catch (error) {
            showNotice(statusStack, `Partner load failed: ${error.message}`, 'error');
        }
    };

    const loadAdminAccounts = async () => {
        if (!fromAccountSelect && !toAccountSelect) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/accounts/list.php?owner_type=admin&is_active=1`);
            const accounts = data.data || [];
            const buildOptions = (select) => {
                if (!select) {
                    return;
                }
                const current = select.value;
                select.querySelectorAll('option[data-dynamic]').forEach((option) => option.remove());
                accounts.forEach((account) => {
                    const option = document.createElement('option');
                    option.value = account.id;
                    const currency = account.currency ? ` ${account.currency}` : '';
                    option.textContent = `${account.name}${currency}`;
                    option.setAttribute('data-dynamic', 'true');
                    select.appendChild(option);
                });
                if (current) {
                    select.value = current;
                }
            };
            buildOptions(fromAccountSelect);
            buildOptions(toAccountSelect);
        } catch (error) {
            showNotice(txStatus || statusStack, `Admin accounts load failed: ${error.message}`, 'error');
        }
    };

    const loadPartnerOptions = async () => {
        if (!transferForm) {
            return;
        }
        const fromSelect = transferForm.querySelector('[name=\"from_partner_id\"]');
        const toSelect = transferForm.querySelector('[name=\"to_partner_id\"]');
        if (!fromSelect || !toSelect) {
            return;
        }
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/partners/list.php?limit=500`);
            const partners = data.data || [];
            partnerOptions.clear();
            const updateSelect = (select) => {
                const current = select.value;
                select.querySelectorAll('option[data-dynamic]').forEach((option) => option.remove());
                partners.forEach((partner) => {
                    partnerOptions.set(String(partner.id), partner);
                    const option = document.createElement('option');
                    option.value = partner.id;
                    option.textContent = `${partner.name} (${partner.type})`;
                    option.setAttribute('data-dynamic', 'true');
                    select.appendChild(option);
                });
                if (current) {
                    select.value = current;
                }
            };
            updateSelect(fromSelect);
            updateSelect(toSelect);
            if (!fromSelect.value) {
                fromSelect.value = partnerId;
            }
        } catch (error) {
            showNotice(transferStatus || statusStack, `Partner list load failed: ${error.message}`, 'error');
        }
    };

    const updateTxAccountFields = () => {
        if (!txForm) {
            return;
        }
        const txType = txForm.querySelector('[name=\"tx_type\"]')?.value || '';
        const isPayPartner = txType === 'WE_PAY_PARTNER';
        const isPartnerPays = txType === 'PARTNER_PAYS_US';
        if (fromAccountField) {
            fromAccountField.classList.toggle('is-hidden', !isPayPartner);
            if (fromAccountSelect) {
                fromAccountSelect.required = isPayPartner;
                if (!isPayPartner) {
                    fromAccountSelect.value = '';
                }
            }
        }
        if (toAccountField) {
            toAccountField.classList.toggle('is-hidden', !isPartnerPays);
            if (toAccountSelect) {
                toAccountSelect.required = isPartnerPays;
                if (!isPartnerPays) {
                    toAccountSelect.value = '';
                }
            }
        }
    };

    const renderTransactions = (rows = []) => {
        if (!transactionsTable) {
            return;
        }
        if (!rows.length) {
            const colspan = canEdit ? 8 : 7;
            transactionsTable.innerHTML = `<tr><td colspan=\"${colspan}\" class=\"muted\">No transactions found.</td></tr>`;
            return;
        }
        transactionsTable.innerHTML = rows
            .map((row) => {
                const actions = [];
                if (canEdit && row.status === 'posted' && row.tx_type !== 'REVERSAL') {
                    actions.push(`<button class=\"text-link\" type=\"button\" data-partner-void data-id=\"${row.id}\">Void</button>`);
                }
                return `<tr>
                    <td>${escapeHtml(row.tx_date || '-')}</td>
                    <td>${escapeHtml(row.display_type || row.tx_type || '-')}</td>
                    <td>${formatSigned(row.movement)}</td>
                    <td>${formatSigned(row.payment)}</td>
                    <td>${escapeHtml(row.admin_account || '--')}</td>
                    <td>${escapeHtml(row.description || '--')}</td>
                    <td>${escapeHtml(row.status || '--')}</td>
                    ${canEdit ? `<td>${actions.join(' ') || '--'}</td>` : ''}
                </tr>`;
            })
            .join('');

        transactionsTable.querySelectorAll('[data-partner-void]').forEach((button) => {
            button.addEventListener('click', async () => {
                const id = button.getAttribute('data-id');
                if (!id) {
                    return;
                }
                const reason = await showConfirmDialog({
                    title: 'Void transaction',
                    message: 'Void this transaction? Add a reason.',
                    confirmLabel: 'Void transaction',
                    requireInput: true,
                    inputPlaceholder: 'Reason'
                });
                if (!reason || !reason.trim()) {
                    return;
                }
                try {
                    await fetchJson(`${window.APP_BASE}/api/partners/tx/void.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id, reason: reason.trim() }),
                    });
                    showNotice(transactionsStatus || statusStack, 'Transaction voided.', 'success');
                    loadPartner();
                    loadTransactions();
                } catch (error) {
                    showNotice(transactionsStatus || statusStack, `Void failed: ${error.message}`, 'error');
                }
            });
        });
    };

    const loadTransactions = async () => {
        if (!transactionsTable) {
            return;
        }
        const colspan = canEdit ? 8 : 7;
        transactionsTable.innerHTML = `<tr><td colspan=\"${colspan}\" class=\"muted\">Loading transactions...</td></tr>`;
        const params = new URLSearchParams({ partner_id: String(partnerId) });
        Object.entries(currentFilters).forEach(([key, value]) => {
            if (value !== null && value !== undefined && String(value).trim() !== '') {
                params.append(key, String(value));
            }
        });
        params.append('limit', String(limit));
        params.append('offset', String(offset));
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/partners/statement.php?${params.toString()}`);
            renderTransactions(data.data || []);
            if (transactionsPrev) {
                transactionsPrev.disabled = offset === 0;
            }
            if (transactionsNext) {
                transactionsNext.disabled = (data.data || []).length < limit;
            }
            if (transactionsPageLabel) {
                transactionsPageLabel.textContent = `Page ${Math.floor(offset / limit) + 1}`;
            }
        } catch (error) {
            renderTransactions([]);
            showNotice(transactionsStatus || statusStack, `Statement load failed: ${error.message}`, 'error');
        }
    };

    if (statementForm) {
        statementForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(statementForm);
            offset = 0;
            currentFilters = Object.fromEntries(formData.entries());
            loadTransactions();
        });
    }

    if (statementRefresh) {
        statementRefresh.addEventListener('click', () => {
            if (statementForm) {
                const formData = new FormData(statementForm);
                offset = 0;
                currentFilters = Object.fromEntries(formData.entries());
            } else {
                offset = 0;
                currentFilters = {};
            }
            loadTransactions();
        });
    }

    if (transactionsPrev) {
        transactionsPrev.addEventListener('click', () => {
            if (offset === 0) {
                return;
            }
            offset = Math.max(0, offset - limit);
            loadTransactions();
        });
    }

    if (transactionsNext) {
        transactionsNext.addEventListener('click', () => {
            offset += limit;
            loadTransactions();
        });
    }

    if (updateForm) {
        updateForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (updateStatus) {
                updateStatus.innerHTML = '';
            }
            const formData = new FormData(updateForm);
            const payload = Object.fromEntries(formData.entries());
            if (!payload.type || !payload.name) {
                showNotice(updateStatus, 'Type and name are required.', 'error');
                return;
            }
            try {
                await fetchJson(`${window.APP_BASE}/api/partners/update.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showNotice(updateStatus, 'Partner updated.', 'success');
                loadPartner();
            } catch (error) {
                showNotice(updateStatus, `Update failed: ${error.message}`, 'error');
            }
        });
    }

    if (txForm) {
        const txTypeSelect = txForm.querySelector('[name=\"tx_type\"]');
        const currencyInput = txForm.querySelector('[name=\"currency_code\"]');
        if (txTypeSelect) {
            txTypeSelect.addEventListener('change', updateTxAccountFields);
        }
        if (currencyInput) {
            currencyInput.addEventListener('input', () => {
                currencyInput.value = currencyInput.value.toUpperCase();
            });
        }
        updateTxAccountFields();

        txForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (txStatus) {
                txStatus.innerHTML = '';
            }
            const formData = new FormData(txForm);
            const payload = Object.fromEntries(formData.entries());
            payload.partner_id = partnerId;
            if (!payload.tx_type || !payload.amount || !payload.currency_code) {
                showNotice(txStatus, 'Type, amount, and currency are required.', 'error');
                return;
            }
            payload.currency_code = String(payload.currency_code).toUpperCase();
            try {
                await fetchJson(`${window.APP_BASE}/api/partners/tx/create.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showNotice(txStatus, 'Transaction recorded.', 'success');
                txForm.reset();
                if (currencyInput) {
                    currencyInput.value = 'USD';
                }
                updateTxAccountFields();
                loadPartner();
                loadTransactions();
            } catch (error) {
                showNotice(txStatus, `Save failed: ${error.message}`, 'error');
            }
        });
    }

    if (transferForm) {
        const currencyInput = transferForm.querySelector('[name=\"currency_code\"]');
        if (currencyInput) {
            currencyInput.addEventListener('input', () => {
                currencyInput.value = currencyInput.value.toUpperCase();
            });
        }
        transferForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (transferStatus) {
                transferStatus.innerHTML = '';
            }
            const formData = new FormData(transferForm);
            const payload = Object.fromEntries(formData.entries());
            payload.tx_type = 'PARTNER_TO_PARTNER_TRANSFER';
            if (!payload.from_partner_id || !payload.to_partner_id) {
                showNotice(transferStatus, 'From and to partners are required.', 'error');
                return;
            }
            if (payload.from_partner_id === payload.to_partner_id) {
                showNotice(transferStatus, 'Partners must be different.', 'error');
                return;
            }
            if (!payload.amount || !payload.currency_code) {
                showNotice(transferStatus, 'Amount and currency are required.', 'error');
                return;
            }
            payload.currency_code = String(payload.currency_code).toUpperCase();
            try {
                await fetchJson(`${window.APP_BASE}/api/partners/tx/create.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showNotice(transferStatus, 'Transfer recorded.', 'success');
                transferForm.reset();
                if (currencyInput) {
                    currencyInput.value = 'USD';
                }
                loadPartner();
                loadTransactions();
            } catch (error) {
                showNotice(transferStatus, `Transfer failed: ${error.message}`, 'error');
            }
        });
    }

    loadPartner();
    loadAdminAccounts();
    loadPartnerOptions();
    loadTransactions();
}

function initCompanyPage() {
    const page = document.querySelector('[data-company-settings]');
    if (!page) {
        return;
    }

    const form = page.querySelector('[data-company-form]');
    const status = page.querySelector('[data-company-status]');
    const goodsPanel = document.querySelector('[data-goods-types-panel]');
    const goodsForm = goodsPanel?.querySelector('[data-goods-types-form]');
    const goodsInput = goodsPanel?.querySelector('[data-goods-types-input]');
    const goodsTable = goodsPanel?.querySelector('[data-goods-types-table]');
    const goodsStatus = goodsPanel?.querySelector('[data-goods-types-status]');
    const rolesPanel = document.querySelector('[data-roles-panel]');
    const rolesForm = rolesPanel?.querySelector('[data-roles-form]');
    const rolesInput = rolesPanel?.querySelector('[data-roles-input]');
    const rolesTable = rolesPanel?.querySelector('[data-roles-table]');
    const rolesStatus = rolesPanel?.querySelector('[data-roles-status]');
        const fields = {
            name: page.querySelector('[name="name"]'),
            phone: page.querySelector('[name="phone"]'),
            address: page.querySelector('[name="address"]'),
            email: page.querySelector('[name="email"]'),
            website: page.querySelector('[name="website"]'),
            logo_url: page.querySelector('[name="logo_url"]'),
            points_price: page.querySelector('[name="points_price"]'),
            points_value: page.querySelector('[name="points_value"]'),
            usd_to_lbp: page.querySelector('[name="usd_to_lbp"]'),
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

    const showGoodsNotice = (message, type = 'error') => {
        if (!goodsStatus) {
            showNotice(message, type);
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        goodsStatus.appendChild(notice);
        setTimeout(() => notice.remove(), 6000);
    };

    const showRolesNotice = (message, type = 'error') => {
        if (!rolesStatus) {
            showNotice(message, type);
            return;
        }
        const notice = document.createElement('div');
        notice.className = `notice ${type}`;
        notice.textContent = message;
        rolesStatus.appendChild(notice);
        setTimeout(() => notice.remove(), 6000);
    };

      const setField = (key, value) => {
          if (fields[key]) {
              fields[key].value = value !== null && value !== undefined ? value : '';
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

    const loadGoodsTypes = async () => {
        if (!goodsTable) {
            return;
        }
        goodsTable.innerHTML = '<tr><td colspan="2" class="muted">Loading goods types...</td></tr>';
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/goods_types/list.php?limit=500`);
            const rows = data.data || [];
            if (!rows.length) {
                goodsTable.innerHTML = '<tr><td colspan="2" class="muted">No goods types yet.</td></tr>';
                return;
            }
            goodsTable.innerHTML = rows
                .map(
                    (row) => `<tr>
                        <td>${row.name || '-'}</td>
                        <td>
                            <button class="button ghost small" type="button" data-goods-delete data-id="${row.id}">
                                Delete
                            </button>
                        </td>
                    </tr>`
                )
                .join('');
        } catch (error) {
            goodsTable.innerHTML = '<tr><td colspan="2" class="muted">Failed to load goods types.</td></tr>';
            showGoodsNotice(`Goods types load failed: ${error.message}`, 'error');
        }
    };

    const loadRoles = async () => {
        if (!rolesTable) {
            return;
        }
        rolesTable.innerHTML = '<tr><td colspan="2" class="muted">Loading roles...</td></tr>';
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/roles/list.php`);
            const rows = data.data || [];
            if (!rows.length) {
                rolesTable.innerHTML = '<tr><td colspan="2" class="muted">No roles yet.</td></tr>';
                return;
            }
            rolesTable.innerHTML = rows
                .map(
                    (row) => `<tr>
                        <td>${row.name || '-'}</td>
                        <td>
                            <button class="button ghost small" type="button" data-role-delete data-id="${row.id}">
                                Delete
                            </button>
                        </td>
                    </tr>`
                )
                .join('');
        } catch (error) {
            rolesTable.innerHTML = '<tr><td colspan="2" class="muted">Failed to load roles.</td></tr>';
            showRolesNotice(`Roles load failed: ${error.message}`, 'error');
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
            const confirmed = await showConfirmDialog({
                title: 'Remove logo',
                message: 'Remove the current logo?',
                confirmLabel: 'Remove'
            });
            if (!confirmed) {
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
    loadGoodsTypes();
    loadRoles();

    if (goodsForm) {
        goodsForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!goodsInput) {
                return;
            }
            const name = goodsInput.value.trim();
            if (!name) {
                showGoodsNotice('Type name is required.', 'error');
                return;
            }
            try {
                await fetchJson(`${window.APP_BASE}/api/goods_types/create.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name }),
                });
                goodsInput.value = '';
                showGoodsNotice('Goods type added.', 'success');
                loadGoodsTypes();
            } catch (error) {
                showGoodsNotice(`Add failed: ${error.message}`, 'error');
            }
        });
    }

    if (goodsTable) {
        goodsTable.addEventListener('click', async (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            const button = target.closest('[data-goods-delete]');
            if (!button) {
                return;
            }
            const id = button.getAttribute('data-id');
            if (!id) {
                return;
            }
            const confirmed = await showConfirmDialog({
                title: 'Delete goods type',
                message: 'Delete this goods type?',
                confirmLabel: 'Delete'
            });
            if (!confirmed) {
                return;
            }
            try {
                await fetchJson(`${window.APP_BASE}/api/goods_types/delete.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id }),
                });
                showGoodsNotice('Goods type removed.', 'success');
                loadGoodsTypes();
            } catch (error) {
                showGoodsNotice(`Delete failed: ${error.message}`, 'error');
            }
        });
    }

    if (rolesForm) {
        rolesForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!rolesInput) {
                return;
            }
            const name = rolesInput.value.trim();
            if (!name) {
                showRolesNotice('Role name is required.', 'error');
                return;
            }
            try {
                await fetchJson(`${window.APP_BASE}/api/roles/create.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name }),
                });
                rolesInput.value = '';
                showRolesNotice('Role added.', 'success');
                loadRoles();
            } catch (error) {
                showRolesNotice(`Add failed: ${error.message}`, 'error');
            }
        });
    }

    if (rolesTable) {
        rolesTable.addEventListener('click', async (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            const button = target.closest('[data-role-delete]');
            if (!button) {
                return;
            }
            const id = button.getAttribute('data-id');
            if (!id) {
                return;
            }
            const confirmed = await showConfirmDialog({
                title: 'Delete role',
                message: 'Delete this role?',
                confirmLabel: 'Delete'
            });
            if (!confirmed) {
                return;
            }
            try {
                await fetchJson(`${window.APP_BASE}/api/roles/delete.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id }),
                });
                showRolesNotice('Role removed.', 'success');
                loadRoles();
            } catch (error) {
                showRolesNotice(`Delete failed: ${error.message}`, 'error');
            }
        });
    }
}

function initSuppliersPage() {
    const page = document.querySelector('[data-suppliers-page]');
    if (!page) {
        return;
    }

    const filterForm = page.querySelector('[data-suppliers-filter]');
    const tableShippers = page.querySelector('[data-suppliers-table-shipper]');
    const tableConsignees = page.querySelector('[data-suppliers-table-consignee]');
    const statusStack = page.querySelector('[data-suppliers-status]');
    const refreshButton = page.querySelector('[data-suppliers-refresh]');
    const prevShipper = page.querySelector('[data-suppliers-prev-shipper]');
    const nextShipper = page.querySelector('[data-suppliers-next-shipper]');
    const pageLabelShipper = page.querySelector('[data-suppliers-page-shipper]');
    const prevConsignee = page.querySelector('[data-suppliers-prev-consignee]');
    const nextConsignee = page.querySelector('[data-suppliers-next-consignee]');
    const pageLabelConsignee = page.querySelector('[data-suppliers-page-consignee]');
    const addButton = page.querySelector('[data-suppliers-add]');
    const drawer = page.querySelector('[data-suppliers-drawer]');
    const form = page.querySelector('[data-suppliers-form]');
    const formTitle = page.querySelector('[data-suppliers-form-title]');
    const submitLabel = page.querySelector('[data-suppliers-submit-label]');
    const drawerStatus = page.querySelector('[data-suppliers-form-status]');
    const SupplierIdField = page.querySelector('[data-supplier-id]');
    const drawerCloseButtons = page.querySelectorAll('[data-suppliers-drawer-close]');
    const canEdit = page.getAttribute('data-can-edit') === '1';
    const createMode = page.getAttribute('data-create-mode') === '1';

    const limit = 5;
    const offsets = { shipper: 0, consignee: 0 };
    let lastFilters = {};
    const SupplierMap = new Map();

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

    const setFormValues = (Supplier) => {
        if (!form) {
            return;
        }
        if (SupplierIdField) {
            SupplierIdField.value = Supplier?.id ? String(Supplier.id) : '';
        }
        if (formTitle) {
            formTitle.textContent = Supplier ? 'Edit Supplier' : 'Add Supplier';
        }
        if (submitLabel) {
            submitLabel.textContent = Supplier ? 'Save changes' : 'Add Supplier';
        }
        form.querySelector('[name="type"]').value = Supplier?.type || '';
        form.querySelector('[name="name"]').value = Supplier?.name || '';
        form.querySelector('[name="phone"]').value = Supplier?.phone || '';
        form.querySelector('[name="address"]').value = Supplier?.address || '';
        const noteField = form.querySelector('[name="note"]');
        if (noteField) {
            noteField.value = Supplier?.note || '';
        }
    };

    const renderRows = (rows, type, tableBody) => {
        if (!tableBody) {
            return;
        }
        if (!rows.length) {
            tableBody.innerHTML =
                '<tr><td colspan="4" class="muted">No Suppliers found.</td></tr>';
            return;
        }
        tableBody.innerHTML = rows
            .map((row) => {
                SupplierMap.set(String(row.id), row);
                const actions = [
                    `<a class="text-link" href="${window.APP_BASE}/views/internal/supplier_view?id=${row.id}">Open</a>`,
                ];
                if (canEdit) {
                    actions.push(
                        `<button class="text-link" type="button" data-supplier-edit data-supplier-id="${row.id}">Edit</button>`
                    );
                    actions.push(
                        `<button class="text-link" type="button" data-supplier-delete data-supplier-id="${row.id}">Delete</button>`
                    );
                }
                return `<tr>
                    <td>${escapeHtml(row.name || '-')}</td>
                    <td>${escapeHtml(row.phone || '-')}</td>
                    <td>${formatAmount(row.balance)}</td>
                    <td>${actions.join(' | ')}</td>
                </tr>`;
            })
            .join('');

        tableBody.querySelectorAll('[data-supplier-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                const id = button.getAttribute('data-supplier-id');
                if (!id || !SupplierMap.has(id)) {
                    return;
                }
                setFormValues(SupplierMap.get(id));
                openDrawer();
            });
        });

        tableBody.querySelectorAll('[data-supplier-delete]').forEach((button) => {
            button.addEventListener('click', async () => {
                const id = button.getAttribute('data-supplier-id');
                if (!id) {
                    return;
                }
                try {
                    await fetchJson(`${window.APP_BASE}/api/suppliers/delete.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id }),
                    });
                    showNotice('Supplier removed.', 'success');
                    if (rows.length === 1 && offsets[type] > 0) {
                        offsets[type] = Math.max(0, offsets[type] - limit);
                    }
                    loadSuppliers(type, lastFilters);
                } catch (error) {
                    showNotice(`Delete failed: ${error.message}`, 'error');
                }
            });
        });
    };

    const loadSuppliers = async (type, filters = {}) => {
        const tableBody = type === 'shipper' ? tableShippers : tableConsignees;
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="4" class="muted">Loading Suppliers...</td></tr>';
        }
        const params = new URLSearchParams({ type });
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== null && value !== undefined && String(value).trim() !== '') {
                params.append(key, String(value));
            }
        });
        params.append('limit', String(limit));
        params.append('offset', String(offsets[type] || 0));
        try {
            const data = await fetchJson(`${window.APP_BASE}/api/suppliers/list.php?${params.toString()}`);
            renderRows(data.data || [], type, tableBody);
            if (type === 'shipper') {
                if (prevShipper) {
                    prevShipper.disabled = offsets.shipper === 0;
                }
                if (nextShipper) {
                    nextShipper.disabled = (data.data || []).length < limit;
                }
                if (pageLabelShipper) {
                    pageLabelShipper.textContent = `Page ${Math.floor((offsets.shipper || 0) / limit) + 1}`;
                }
            } else {
                if (prevConsignee) {
                    prevConsignee.disabled = offsets.consignee === 0;
                }
                if (nextConsignee) {
                    nextConsignee.disabled = (data.data || []).length < limit;
                }
                if (pageLabelConsignee) {
                    pageLabelConsignee.textContent = `Page ${Math.floor((offsets.consignee || 0) / limit) + 1}`;
                }
            }
        } catch (error) {
            renderRows([], type, tableBody);
            showNotice(`Suppliers load failed: ${error.message}`, 'error');
        }
    };

    if (filterForm) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(filterForm);
            offsets.shipper = 0;
            offsets.consignee = 0;
            lastFilters = Object.fromEntries(formData.entries());
            loadSuppliers('shipper', lastFilters);
            loadSuppliers('consignee', lastFilters);
        });
    }

    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            if (filterForm) {
                const formData = new FormData(filterForm);
                offsets.shipper = 0;
                offsets.consignee = 0;
                lastFilters = Object.fromEntries(formData.entries());
                loadSuppliers('shipper', lastFilters);
                loadSuppliers('consignee', lastFilters);
            } else {
                offsets.shipper = 0;
                offsets.consignee = 0;
                lastFilters = {};
                loadSuppliers('shipper');
                loadSuppliers('consignee');
            }
        });
    }

    if (prevShipper) {
        prevShipper.addEventListener('click', () => {
            if (offsets.shipper === 0) {
                return;
            }
            offsets.shipper = Math.max(0, offsets.shipper - limit);
            loadSuppliers('shipper', lastFilters);
        });
    }

    if (nextShipper) {
        nextShipper.addEventListener('click', () => {
            offsets.shipper += limit;
            loadSuppliers('shipper', lastFilters);
        });
    }

    if (prevConsignee) {
        prevConsignee.addEventListener('click', () => {
            if (offsets.consignee === 0) {
                return;
            }
            offsets.consignee = Math.max(0, offsets.consignee - limit);
            loadSuppliers('consignee', lastFilters);
        });
    }

    if (nextConsignee) {
        nextConsignee.addEventListener('click', () => {
            offsets.consignee += limit;
            loadSuppliers('consignee', lastFilters);
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
            const SupplierId = payload.supplier_id || '';
            if (!payload.type || !payload.name) {
                showFormNotice('Type and name are required.', 'error');
                return;
            }
            try {
                if (SupplierId) {
                    await fetchJson(`${window.APP_BASE}/api/suppliers/update.php`, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    showNotice('Supplier updated.', 'success');
                } else {
                    await fetchJson(`${window.APP_BASE}/api/suppliers/create.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    showNotice('Supplier added.', 'success');
                }
                closeDrawer();
                loadSuppliers('shipper', lastFilters);
                loadSuppliers('consignee', lastFilters);
            } catch (error) {
                showFormNotice(`Save failed: ${error.message}`, 'error');
            }
        });
    }

    if (createMode && canEdit) {
        setFormValues(null);
        openDrawer();
    }

    loadSuppliers('shipper');
    loadSuppliers('consignee');
}



