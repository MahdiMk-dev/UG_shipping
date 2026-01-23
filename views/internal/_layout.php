<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/company.php';

function internal_require_user(): array
{
    $user = auth_user();
    if (!$user) {
        header('Location: ' . BASE_URL . '/');
        exit;
    }

    return $user;
}

function internal_page_start(array $user, string $active, string $title, string $subtitle = ''): void
{
    $titleSafe = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $subtitleSafe = htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8');
    $userName = htmlspecialchars((string) ($user['name'] ?? 'User'), ENT_QUOTES, 'UTF-8');
    $roleName = htmlspecialchars((string) ($user['role'] ?? ''), ENT_QUOTES, 'UTF-8');

    $role = $user['role'] ?? '';
    $isCreateMode = !empty($GLOBALS['nav_create_mode']) || isset($_GET['create']);
    $normalizePath = static function ($value): string {
        $path = parse_url((string) $value, PHP_URL_PATH);
        return $path ? rtrim($path, '/') : '';
    };
    $currentPath = $normalizePath($_SERVER['REQUEST_URI'] ?? '');

    $navGroups = [
        'shipments' => [
            'label' => 'Shipments',
            'view' => BASE_URL . '/views/internal/shipments',
            'create' => BASE_URL . '/views/internal/shipment_create',
            'create_roles' => ['Admin', 'Owner', 'Main Branch', 'Warehouse'],
        ],
        'orders' => [
            'label' => 'Orders',
            'view' => BASE_URL . '/views/internal/orders',
        ],
        'customers' => [
            'label' => 'Customers',
            'view' => BASE_URL . '/views/internal/customers',
            'create' => BASE_URL . '/views/internal/customer_create',
            'create_roles' => ['Admin', 'Owner', 'Main Branch', 'Sub Branch'],
        ],
        'staff' => [
            'label' => 'Staff',
            'view' => BASE_URL . '/views/internal/staff',
            'create' => BASE_URL . '/views/internal/staff_create',
            'create_roles' => ['Admin', 'Owner', 'Sub Branch'],
        ],
        'suppliers' => [
            'label' => 'Suppliers',
            'view' => BASE_URL . '/views/internal/suppliers',
            'create' => BASE_URL . '/views/internal/supplier_create',
            'create_roles' => ['Admin', 'Owner', 'Main Branch'],
        ],
        'partners' => [
            'label' => 'Partners',
            'view' => BASE_URL . '/views/internal/partners',
            'create' => BASE_URL . '/views/internal/partner_create',
            'create_roles' => ['Admin', 'Owner'],
        ],
        'branches' => [
            'label' => 'Branches',
            'view' => BASE_URL . '/views/internal/branches',
            'create' => BASE_URL . '/views/internal/branch_create',
            'create_roles' => ['Admin', 'Owner'],
        ],
        'users' => [
            'label' => 'Users',
            'view' => BASE_URL . '/views/internal/users',
            'create' => BASE_URL . '/views/internal/user_create',
            'create_roles' => ['Admin', 'Owner'],
        ],
        'expenses' => [
            'label' => 'Expenses',
            'view' => BASE_URL . '/views/internal/expenses',
            'create' => BASE_URL . '/views/internal/expense_create',
            'create_roles' => ['Admin', 'Owner'],
        ],
        'accounts' => [
            'label' => 'Accounts',
            'view' => BASE_URL . '/views/internal/accounts',
            'create' => BASE_URL . '/views/internal/account_create',
            'create_roles' => ['Admin', 'Owner'],
        ],
    ];

    $navItems = [
        'dashboard' => ['label' => 'Dashboard', 'href' => BASE_URL . '/views/internal/dashboard'],
        'receiving' => ['label' => 'Receiving', 'href' => BASE_URL . '/views/internal/receiving'],
        'invoices' => ['label' => 'Invoices', 'href' => BASE_URL . '/views/internal/invoices'],
        'transactions' => ['label' => 'Transactions', 'href' => BASE_URL . '/views/internal/transactions'],
        'customer_balances' => ['label' => 'Customer Balances', 'href' => BASE_URL . '/views/internal/customer_balances'],
        'balances' => ['label' => 'Branch Balances', 'href' => BASE_URL . '/views/internal/balances'],
        'branch_overview' => ['label' => 'Branch Overview', 'href' => BASE_URL . '/views/internal/branch_overview'],
        'reports' => ['label' => 'Reports', 'href' => BASE_URL . '/views/internal/reports'],
        'attachments' => ['label' => 'Attachments', 'href' => BASE_URL . '/views/internal/attachments'],
        'company' => ['label' => 'Company', 'href' => BASE_URL . '/views/internal/company'],
        'roles' => ['label' => 'Roles', 'href' => BASE_URL . '/views/internal/roles'],
    ];

    if ($role !== 'Owner') {
        unset($navItems['roles']);
    }
    if (!in_array($role, ['Admin', 'Owner', 'Sub Branch'], true)) {
        unset($navGroups['accounts']);
    }
    if (!in_array($role, ['Admin', 'Owner'], true)) {
        unset($navGroups['expenses']);
    }
    if (!in_array($role, ['Admin', 'Owner', 'Sub Branch'], true)) {
        unset($navItems['reports']);
    }
    if (!in_array($role, ['Admin', 'Owner', 'Sub Branch'], true)) {
        unset($navItems['customer_balances']);
    }
    if (!in_array($role, ['Admin', 'Owner'], true)) {
        unset($navItems['balances']);
    }
    if (!in_array($role, ['Owner', 'Sub Branch'], true)) {
        unset($navItems['branch_overview']);
    }
    if (!in_array($role, ['Admin', 'Owner'], true)) {
        unset($navItems['company']);
    }
    if (!in_array($role, ['Admin', 'Owner', 'Main Branch'], true)) {
        unset($navGroups['suppliers']);
    }
    if (!in_array($role, ['Admin', 'Owner', 'Main Branch', 'Sub Branch'], true)) {
        unset($navGroups['partners']);
    }
    if (in_array($role, ['Staff', 'Sub Branch', 'Warehouse', 'Main Branch'], true)) {
        unset($navGroups['branches'], $navGroups['users']);
    }
    if (in_array($role, ['Staff', 'Warehouse', 'Main Branch'], true)) {
        unset($navGroups['staff']);
    }
    if ($role === 'Warehouse') {
        unset($navItems['invoices'], $navItems['transactions'], $navItems['attachments'], $navItems['receiving']);
    }
    if ($role === 'Owner') {
        $navItems['audit'] = ['label' => 'Audit', 'href' => BASE_URL . '/views/internal/audit'];
    }
    $navIcons = [
        'dashboard' => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">'
            . '<rect x="3" y="3" width="7" height="7" rx="1.5"></rect>'
            . '<rect x="14" y="3" width="7" height="7" rx="1.5"></rect>'
            . '<rect x="3" y="14" width="7" height="7" rx="1.5"></rect>'
            . '<rect x="14" y="14" width="7" height="7" rx="1.5"></rect>'
            . '</svg>',
        'shipments' => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">'
            . '<path d="M3 7h18v10H3z"></path>'
            . '<path d="M8 7V5h8v2"></path>'
            . '<path d="M7 12h10"></path>'
            . '</svg>',
        'orders' => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">'
            . '<path d="M8 4h8l3 3v13H5V4h3z"></path>'
            . '<path d="M8 11h8"></path><path d="M8 15h8"></path>'
            . '</svg>',
        'receiving' => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">'
            . '<path d="M4 7h4"></path><path d="M4 12h10"></path><path d="M4 17h6"></path>'
            . '<path d="M17 7h3v10h-3"></path>'
            . '</svg>',
        'invoices' => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">'
            . '<path d="M6 3h9l3 3v15H6z"></path>'
            . '<path d="M9 11h6"></path><path d="M9 15h6"></path>'
            . '</svg>',
        'transactions' => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">'
            . '<path d="M7 7h10"></path><path d="M7 17h10"></path>'
            . '<path d="M7 7l-3 3"></path><path d="M7 17l-3-3"></path>'
            . '<path d="M17 7l3-3"></path><path d="M17 17l3 3"></path>'
            . '</svg>',
        'accounts' => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">'
            . '<path d="M4 8h16v10H4z"></path>'
            . '<path d="M4 8l2-4h12l2 4"></path>'
            . '<path d="M9 13h6"></path>'
            . '</svg>',
        'expenses' => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">'
            . '<path d="M6 3h12v4H6z"></path>'
            . '<path d="M4 7h16v12H4z"></path>'
            . '<path d="M8 13h8"></path>'
            . '</svg>',
        'reports' => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">'
            . '<path d="M4 20h16"></path>'
            . '<path d="M7 16V8"></path><path d="M12 16V5"></path><path d="M17 16v-6"></path>'
            . '</svg>',
        'attachments' => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">'
            . '<path d="M7 12l6-6a3 3 0 014 4l-7 7a4 4 0 11-6-6l7-7"></path>'
            . '</svg>',
        'staff' => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">'
            . '<circle cx="9" cy="7" r="3"></circle>'
            . '<path d="M2 20a7 7 0 0114 0"></path>'
            . '<path d="M16 7h6"></path><path d="M19 4v6"></path>'
            . '</svg>',
        'customers' => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">'
            . '<circle cx="9" cy="8" r="3"></circle>'
            . '<path d="M3 20a6 6 0 0112 0"></path>'
            . '<path d="M17 11h4"></path><path d="M19 9v4"></path>'
            . '</svg>',
        'suppliers' => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">'
            . '<circle cx="7" cy="8" r="3"></circle>'
            . '<circle cx="17" cy="8" r="3"></circle>'
            . '<path d="M2 20a5 5 0 0110 0"></path>'
            . '<path d="M12 20a5 5 0 0110 0"></path>'
            . '</svg>',
        'partners' => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">'
            . '<path d="M7 7h10"></path>'
            . '<path d="M7 12h6"></path>'
            . '<path d="M7 17h10"></path>'
            . '<circle cx="5" cy="7" r="2"></circle>'
            . '<circle cx="5" cy="17" r="2"></circle>'
            . '<circle cx="19" cy="7" r="2"></circle>'
            . '<circle cx="19" cy="17" r="2"></circle>'
            . '</svg>',
        'company' => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">'
            . '<path d="M4 20h16"></path>'
            . '<path d="M6 20V7l6-3 6 3v13"></path>'
            . '<path d="M9 11h2"></path><path d="M13 11h2"></path><path d="M9 15h2"></path><path d="M13 15h2"></path>'
            . '</svg>',
        'branches' => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">'
            . '<path d="M4 20h16"></path>'
            . '<path d="M6 20V7l6-3 6 3v13"></path>'
            . '<path d="M9 11h2"></path><path d="M13 11h2"></path><path d="M9 15h2"></path><path d="M13 15h2"></path>'
            . '</svg>',
        'users' => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">'
            . '<circle cx="8" cy="8" r="3"></circle>'
            . '<circle cx="16" cy="10" r="2"></circle>'
            . '<path d="M2 20a6 6 0 0112 0"></path>'
            . '<path d="M14 20a4 4 0 018 0"></path>'
            . '</svg>',
        'roles' => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">'
            . '<path d="M12 3l8 4v6c0 5-4 8-8 8s-8-3-8-8V7l8-4z"></path>'
            . '<path d="M9 12l2 2 4-4"></path>'
            . '</svg>',
        'audit' => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">'
            . '<circle cx="11" cy="11" r="7"></circle>'
            . '<path d="M11 8v4l3 2"></path>'
            . '<path d="M20 20l-3-3"></path>'
            . '</svg>',
        'default' => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">'
            . '<circle cx="12" cy="12" r="9"></circle><path d="M8 12h8"></path>'
            . '</svg>',
    ];

    echo "<!doctype html>\n";
    echo "<html lang=\"en\">\n";
    echo "<head>\n";
    echo "    <meta charset=\"utf-8\">\n";
    echo "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
    echo "    <title>{$titleSafe} - UG Shipping</title>\n";
    echo "    <link rel=\"stylesheet\" href=\"" . PUBLIC_URL . "/assets/css/main.css\">\n";
    $extraStyles = $GLOBALS['internal_styles'] ?? [];
    foreach ($extraStyles as $href) {
        $hrefSafe = htmlspecialchars((string) $href, ENT_QUOTES, 'UTF-8');
        echo "    <link rel=\"stylesheet\" href=\"{$hrefSafe}\">\n";
    }
    echo "</head>\n";
    echo "<body>\n";
    $branchId = htmlspecialchars((string) ($user['branch_id'] ?? ''), ENT_QUOTES, 'UTF-8');
    $branchCountry = htmlspecialchars((string) (get_branch_country_id($user) ?? ''), ENT_QUOTES, 'UTF-8');
    echo "<div class=\"app-shell internal-shell\" data-user-role=\"{$roleName}\" data-branch-id=\"{$branchId}\" "
        . "data-branch-country-id=\"{$branchCountry}\">\n";
    echo "    <aside class=\"sidebar\" data-sidebar>\n";
    $company = company_settings();
    $logoUrl = !empty($company['logo_url']) ? $company['logo_url'] : (PUBLIC_URL . '/assets/img/ug-logo.jpg');
    $expiryNotice = '';
    $expiryDateRaw = $company['domain_expiry'] ?? null;
    if ($expiryDateRaw) {
        try {
            $expiryDate = new DateTimeImmutable((string) $expiryDateRaw);
            $today = new DateTimeImmutable('today');
            $isExpired = $expiryDate < $today;
            $daysUntil = (int) $today->diff($expiryDate)->days;
            if ($isExpired) {
                $expiryLabel = $expiryDate->format('F j, Y');
                $expiryNotice = "Domain expired on {$expiryLabel}. If not renewed before the expiry date, data may be lost.";
            } elseif ($daysUntil <= 30) {
                $expiryLabel = $expiryDate->format('F j, Y');
                $dayLabel = $daysUntil === 1 ? 'day' : 'days';
                $expiryNotice = "Domain expires in {$daysUntil} {$dayLabel} on {$expiryLabel}. "
                    . 'If not renewed before the expiry date, data may be lost.';
            }
        } catch (Throwable $e) {
            $expiryNotice = '';
        }
    }
    $logoEsc = htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8');
    $alt = htmlspecialchars($company['name'] ?? 'United Group', ENT_QUOTES, 'UTF-8');
    echo "        <div class=\"sidebar-header\">\n";
    echo "            <div class=\"sidebar-brand\">\n";
    echo "                <img class=\"nav-logo\" src=\"{$logoEsc}\" "
        . "onerror=\"this.onerror=null;this.src='" . PUBLIC_URL . "/assets/img/ug-logo.svg';\" "
        . "alt=\"{$alt}\">\n";
    echo "                <span>UG Shipping</span>\n";
    echo "            </div>\n";
    echo "            <button class=\"icon-button sidebar-toggle\" type=\"button\" data-sidebar-toggle aria-label=\"Close navigation\">\n";
    echo "                <svg viewBox=\"0 0 24 24\" aria-hidden=\"true\"><path d=\"M6 6l12 12\"></path><path d=\"M18 6l-12 12\"></path></svg>\n";
    echo "            </button>\n";
    echo "        </div>\n";
    echo "        <nav class=\"sidebar-nav\">\n";

    $navSequence = [
        ['item', 'dashboard'],
        ['item', 'branch_overview'],
        ['group', 'shipments'],
        ['group', 'orders'],
        ['item', 'receiving'],
        ['item', 'invoices'],
        ['item', 'transactions'],
        ['group', 'customers'],
        ['item', 'customer_balances'],
        ['group', 'accounts'],
        ['item', 'balances'],
        ['group', 'expenses'],
        ['item', 'reports'],
        ['item', 'attachments'],
        ['group', 'staff'],
        ['group', 'suppliers'],
        ['group', 'partners'],
        ['group', 'branches'],
        ['group', 'users'],
        ['item', 'company'],
        ['item', 'roles'],
        ['item', 'audit'],
    ];

    foreach ($navSequence as $entry) {
        [$type, $key] = $entry;
        if ($type === 'item') {
            if (!isset($navItems[$key])) {
                continue;
            }
            $isActive = $key === $active ? 'active' : '';
            $label = htmlspecialchars($navItems[$key]['label'], ENT_QUOTES, 'UTF-8');
            $href = htmlspecialchars($navItems[$key]['href'], ENT_QUOTES, 'UTF-8');
            $icon = $navIcons[$key] ?? $navIcons['default'];
            echo "            <a class=\"sidebar-link {$isActive}\" href=\"{$href}\">{$icon}<span>{$label}</span></a>\n";
            continue;
        }

        if (!isset($navGroups[$key])) {
            continue;
        }
        $group = $navGroups[$key];
        $label = htmlspecialchars($group['label'], ENT_QUOTES, 'UTF-8');
        $viewHref = $group['view'];
        $createHref = $group['create'] ?? null;
        $viewPath = $normalizePath($viewHref);
        $createPath = $createHref ? $normalizePath($createHref) : '';
        $isCreateActive = $currentPath === $createPath || ($key === $active && $isCreateMode);
        $isViewActive = $currentPath === $viewPath && !$isCreateActive;
        $groupActive = $key === $active || $isCreateActive || $isViewActive;
        $icon = $navIcons[$key] ?? $navIcons['default'];
        $summaryClass = 'sidebar-link sidebar-summary' . ($groupActive ? ' active' : '');
        $createRoles = $group['create_roles'] ?? [];
        $canCreate = empty($createRoles) || in_array($role, $createRoles, true);

        echo "            <details class=\"sidebar-group\" " . ($groupActive ? 'open' : '') . ">\n";
        echo "                <summary class=\"{$summaryClass}\">{$icon}<span class=\"sidebar-label\">{$label}</span>"
            . "<span class=\"sidebar-chevron\"><svg viewBox=\"0 0 24 24\" aria-hidden=\"true\">"
            . "<path d=\"M6 9l6 6 6-6\"></path></svg></span></summary>\n";
        echo "                <div class=\"sidebar-subnav\">\n";
        $viewLabel = htmlspecialchars('View', ENT_QUOTES, 'UTF-8');
        $viewClass = 'sidebar-sublink' . ($isViewActive ? ' active' : '');
        $viewIcon = '<svg class="sidebar-subicon" viewBox="0 0 24 24" aria-hidden="true">'
            . '<path d="M2 12s4-6 10-6 10 6 10 6-4 6-10 6-10-6-10-6"></path>'
            . '<circle cx="12" cy="12" r="3"></circle>'
            . '</svg>';
        echo "                    <a class=\"{$viewClass}\" href=\"" . htmlspecialchars($viewHref, ENT_QUOTES, 'UTF-8')
            . "\">{$viewIcon}<span>{$viewLabel}</span></a>\n";
        if ($canCreate && $createHref) {
            $createLabel = htmlspecialchars('Create', ENT_QUOTES, 'UTF-8');
            $createClass = 'sidebar-sublink' . ($isCreateActive ? ' active' : '');
            $createIcon = '<svg class="sidebar-subicon" viewBox="0 0 24 24" aria-hidden="true">'
                . '<path d="M12 5v14"></path><path d="M5 12h14"></path>'
                . '</svg>';
            echo "                    <a class=\"{$createClass}\" href=\"" . htmlspecialchars($createHref, ENT_QUOTES, 'UTF-8')
                . "\">{$createIcon}<span>{$createLabel}</span></a>\n";
        }
        echo "                </div>\n";
        echo "            </details>\n";
    }

    echo "        </nav>\n";
    echo "    </aside>\n";
    echo "    <div class=\"sidebar-scrim\" data-sidebar-scrim></div>\n";
    echo "    <div class=\"main-area\">\n";
    echo "        <header class=\"app-toolbar\">\n";
    echo "            <button class=\"icon-button\" type=\"button\" data-sidebar-toggle aria-label=\"Open navigation\">\n";
    echo "                <svg viewBox=\"0 0 24 24\" aria-hidden=\"true\"><path d=\"M4 7h16\"></path><path d=\"M4 12h16\"></path><path d=\"M4 17h16\"></path></svg>\n";
    echo "            </button>\n";
    echo "            <div class=\"toolbar-title\">{$titleSafe}</div>\n";
    echo "            <div class=\"user-chip\">\n";
    echo "                <div>\n";
    echo "                    <div class=\"user-name\">{$userName}</div>\n";
    echo "                    <div class=\"user-role\">{$roleName}</div>\n";
    echo "                </div>\n";
    echo "                <button class=\"button ghost small\" type=\"button\" data-password-open>Change password</button>\n";
    echo "                <button class=\"button ghost small\" data-logout>Logout</button>\n";
    echo "            </div>\n";
    echo "        </header>\n";
    echo "        <main class=\"content\">\n";
    echo "            <section class=\"page-hero\">\n";
    echo "                <div>\n";
    echo "                    <h1>{$titleSafe}</h1>\n";
    if ($subtitleSafe !== '') {
        echo "                    <p>{$subtitleSafe}</p>\n";
    }
    echo "                </div>\n";
    echo "            </section>\n";
    if ($expiryNotice !== '') {
        $expirySafe = htmlspecialchars($expiryNotice, ENT_QUOTES, 'UTF-8');
        echo "            <section class=\"expiry-banner\" role=\"alert\">\n";
        echo "                <div class=\"expiry-banner-icon\">\n";
        echo "                    <svg viewBox=\"0 0 24 24\" aria-hidden=\"true\">"
            . "<path d=\"M12 8v5\"></path>"
            . "<path d=\"M12 16h.01\"></path>"
            . "<path d=\"M10 3h4l1 2h5v6c0 5-4 9-8 9s-8-4-8-9V5h5l1-2z\"></path>"
            . "</svg>\n";
        echo "                </div>\n";
        echo "                <div class=\"expiry-banner-text\">{$expirySafe}</div>\n";
        echo "            </section>\n";
    }
}

function internal_page_end(): void
{
    echo "            <div class=\"drawer\" data-password-drawer>\n";
    echo "                <div class=\"drawer-scrim\" data-password-close></div>\n";
    echo "                <div class=\"drawer-panel\" role=\"dialog\" aria-modal=\"true\" aria-labelledby=\"password-form-title\">\n";
    echo "                    <div class=\"drawer-header\">\n";
    echo "                        <div>\n";
    echo "                            <h3 id=\"password-form-title\">Change password</h3>\n";
    echo "                            <p>Update your own password. Current password is required.</p>\n";
    echo "                        </div>\n";
    echo "                        <button class=\"icon-button\" type=\"button\" data-password-close aria-label=\"Close password panel\">\n";
    echo "                            <svg viewBox=\"0 0 24 24\" aria-hidden=\"true\"><path d=\"M6 6l12 12\"></path><path d=\"M18 6l-12 12\"></path></svg>\n";
    echo "                        </button>\n";
    echo "                    </div>\n";
    echo "                    <form class=\"grid-form\" data-password-form>\n";
    echo "                        <label>\n";
    echo "                            <span>Current password</span>\n";
    echo "                            <input type=\"password\" name=\"old_password\" autocomplete=\"current-password\" required>\n";
    echo "                        </label>\n";
    echo "                        <label>\n";
    echo "                            <span>New password</span>\n";
    echo "                            <input type=\"password\" name=\"new_password\" autocomplete=\"new-password\" required>\n";
    echo "                        </label>\n";
    echo "                        <label>\n";
    echo "                            <span>Confirm new password</span>\n";
    echo "                            <input type=\"password\" name=\"confirm_password\" autocomplete=\"new-password\" required>\n";
    echo "                        </label>\n";
    echo "                        <button class=\"button primary small\" type=\"submit\">Update password</button>\n";
    echo "                    </form>\n";
    echo "                    <div class=\"notice-stack\" data-password-status></div>\n";
    echo "                </div>\n";
    echo "            </div>\n";
    echo "            <div class=\"global-notice\" data-global-notice>\n";
    echo "                <div class=\"global-notice-scrim\" data-global-notice-close></div>\n";
    echo "                <div class=\"global-notice-panel\" role=\"alert\" aria-live=\"assertive\">\n";
    echo "                    <div class=\"global-notice-header\">\n";
    echo "                        <strong>System notice</strong>\n";
    echo "                        <button class=\"icon-button\" type=\"button\" data-global-notice-close aria-label=\"Close notice\">\n";
    echo "                            <svg viewBox=\"0 0 24 24\" aria-hidden=\"true\"><path d=\"M6 6l12 12\"></path><path d=\"M18 6l-12 12\"></path></svg>\n";
    echo "                        </button>\n";
    echo "                    </div>\n";
    echo "                    <div class=\"global-notice-stack\" data-global-notice-stack></div>\n";
    echo "                </div>\n";
    echo "            </div>\n";
    echo "        </main>\n";
    echo "    </div>\n";
    echo "</div>\n";
    echo "<script>window.APP_BASE = " . json_encode(BASE_URL) . "; window.PUBLIC_BASE = " . json_encode(PUBLIC_URL) . ";</script>\n";
    $extraScripts = $GLOBALS['internal_scripts'] ?? [];
    foreach ($extraScripts as $src) {
        $srcSafe = htmlspecialchars((string) $src, ENT_QUOTES, 'UTF-8');
        echo "<script src=\"{$srcSafe}\"></script>\n";
    }
    $appJsPath = __DIR__ . '/../../public/assets/js/app.js';
    $appJsVersion = @filemtime($appJsPath);
    $appJsSuffix = $appJsVersion ? "?v={$appJsVersion}" : '';
    echo "<script src=\"" . PUBLIC_URL . "/assets/js/app.js{$appJsSuffix}\"></script>\n";
    echo "</body>\n";
    echo "</html>\n";
}



