<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

$portalUrl = BASE_URL . '/views/portal/home';
$internalUrl = BASE_URL . '/views/internal/dashboard';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>UG Shipping Login</title>
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>/assets/css/main.css">
</head>
<body class="login-body">
    <main class="login-shell">
        <section class="brand-panel">
            <div class="brand">
                <div class="brand-mark">
                    <img class="brand-logo" src="<?= PUBLIC_URL ?>/assets/img/ug-logo.jpg"
                         onerror="this.onerror=null;this.src='<?= PUBLIC_URL ?>/assets/img/ug-logo.svg';"
                         alt="United Group">
                </div>
                <div>
                    <p class="brand-kicker">United Group</p>
                    <h1>UG Shipping</h1>
                </div>
            </div>
            <p class="brand-copy">
                Manage shipments, finance, and receiving in a single, dependable workspace.
            </p>
            <div class="brand-stats">
                <div>
                    <span class="stat-value">Realtime</span>
                    <span class="stat-label">Shipment visibility</span>
                </div>
                <div>
                    <span class="stat-value">Flexible</span>
                    <span class="stat-label">Partial payments</span>
                </div>
                <div>
                    <span class="stat-value">Secure</span>
                    <span class="stat-label">Attachment vault</span>
                </div>
            </div>
        </section>
        <section class="login-card">
            <div class="card-header">
                <h2>Sign in</h2>
                <p>Internal users only. Customer portal access is separate.</p>
            </div>
            <form class="login-form" data-login-form>
                <label class="field">
                    <span>Username</span>
                    <input type="text" name="username" autocomplete="username" required>
                </label>
                <label class="field">
                    <span>Password</span>
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>
                <div class="form-actions">
                    <button type="submit" class="button primary">Sign in</button>
                    <a class="button ghost" href="<?= htmlspecialchars($portalUrl, ENT_QUOTES) ?>">Customer Portal</a>
                </div>
                <p class="form-status" data-login-status></p>
            </form>
            <p class="login-note">
                Need access? Ask an admin to create your role and branch permissions.
            </p>
        </section>
    </main>

    <script>
        window.APP_BASE = <?= json_encode(BASE_URL) ?>;
        window.PUBLIC_BASE = <?= json_encode(PUBLIC_URL) ?>;
        window.INTERNAL_HOME = <?= json_encode($internalUrl) ?>;
    </script>
    <script src="<?= PUBLIC_URL ?>/assets/js/app.js"></script>
</body>
</html>
