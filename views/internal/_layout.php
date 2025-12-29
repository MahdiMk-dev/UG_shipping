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

    $navItems = [
        'dashboard' => ['label' => 'Dashboard', 'href' => BASE_URL . '/views/internal/dashboard'],
        'shipments' => ['label' => 'Shipments', 'href' => BASE_URL . '/views/internal/shipments'],
        'orders' => ['label' => 'Orders', 'href' => BASE_URL . '/views/internal/orders'],
        'receiving' => ['label' => 'Receiving', 'href' => BASE_URL . '/views/internal/receiving'],
        'invoices' => ['label' => 'Invoices', 'href' => BASE_URL . '/views/internal/invoices'],
        'transactions' => ['label' => 'Transactions', 'href' => BASE_URL . '/views/internal/transactions'],
        'expenses' => ['label' => 'Expenses', 'href' => BASE_URL . '/views/internal/expenses'],
        'reports' => ['label' => 'Reports', 'href' => BASE_URL . '/views/internal/reports'],
        'attachments' => ['label' => 'Attachments', 'href' => BASE_URL . '/views/internal/attachments'],
        'staff' => ['label' => 'Staff', 'href' => BASE_URL . '/views/internal/staff'],
        'customers' => ['label' => 'Customers', 'href' => BASE_URL . '/views/internal/customers'],
        'partners' => ['label' => 'Partners', 'href' => BASE_URL . '/views/internal/partners'],
        'company' => ['label' => 'Company', 'href' => BASE_URL . '/views/internal/company'],
        'branches' => ['label' => 'Branches', 'href' => BASE_URL . '/views/internal/branches'],
        'users' => ['label' => 'Users', 'href' => BASE_URL . '/views/internal/users'],
        'roles' => ['label' => 'Roles', 'href' => BASE_URL . '/views/internal/roles'],
    ];
    if (($user['role'] ?? '') !== 'Owner') {
        unset($navItems['roles']);
    }
    if (!in_array($user['role'] ?? '', ['Admin', 'Owner'], true)) {
        unset($navItems['expenses']);
    }
    if (!in_array($user['role'] ?? '', ['Admin', 'Owner', 'Sub Branch'], true)) {
        unset($navItems['reports']);
    }
    if (!in_array($user['role'] ?? '', ['Admin', 'Owner'], true)) {
        unset($navItems['company']);
    }
    if (!in_array($user['role'] ?? '', ['Admin', 'Owner', 'Main Branch', 'Warehouse'], true)) {
        unset($navItems['partners']);
    }
    if (in_array($user['role'] ?? '', ['Staff', 'Sub Branch', 'Warehouse', 'Main Branch'], true)) {
        unset($navItems['branches'], $navItems['users']);
    }
    if (in_array($user['role'] ?? '', ['Staff', 'Warehouse', 'Main Branch'], true)) {
        unset($navItems['staff']);
    }
    if (($user['role'] ?? '') === 'Warehouse') {
        unset($navItems['customers'], $navItems['invoices'], $navItems['transactions'], $navItems['attachments'], $navItems['receiving']);
    }
    if (($user['role'] ?? '') === 'Owner') {
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
        'partners' => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">'
            . '<circle cx="7" cy="8" r="3"></circle>'
            . '<circle cx="17" cy="8" r="3"></circle>'
            . '<path d="M2 20a5 5 0 0110 0"></path>'
            . '<path d="M12 20a5 5 0 0110 0"></path>'
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
    echo "</head>\n";
    echo "<body>\n";
    $branchId = htmlspecialchars((string) ($user['branch_id'] ?? ''), ENT_QUOTES, 'UTF-8');
    $branchCountry = htmlspecialchars((string) (get_branch_country_id($user) ?? ''), ENT_QUOTES, 'UTF-8');
    echo "<div class=\"app-shell internal-shell\" data-user-role=\"{$roleName}\" data-branch-id=\"{$branchId}\" "
        . "data-branch-country-id=\"{$branchCountry}\">\n";
    echo "    <aside class=\"sidebar\" data-sidebar>\n";
    $company = company_settings();
    $logoUrl = !empty($company['logo_url']) ? $company['logo_url'] : (PUBLIC_URL . '/assets/img/ug-logo.jpg');
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

    foreach ($navItems as $key => $item) {
        $isActive = $key === $active ? 'active' : '';
        $label = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
        $href = htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8');
        $icon = $navIcons[$key] ?? $navIcons['default'];
        echo "            <a class=\"sidebar-link {$isActive}\" href=\"{$href}\">{$icon}<span>{$label}</span></a>\n";
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
}

function internal_page_end(): void
{
    echo "        </main>\n";
    echo "    </div>\n";
    echo "</div>\n";
    echo "<script>window.APP_BASE = " . json_encode(BASE_URL) . "; window.PUBLIC_BASE = " . json_encode(PUBLIC_URL) . ";</script>\n";
    echo "<script src=\"" . PUBLIC_URL . "/assets/js/app.js\"></script>\n";
    echo "</body>\n";
    echo "</html>\n";
}
