<?php
declare(strict_types=1);

function order_has_active_invoice(PDO $db, int $orderId): bool
{
    if ($orderId <= 0) {
        return false;
    }

    $stmt = $db->prepare(
        'SELECT 1 FROM invoice_items ii '
        . 'JOIN invoices i ON i.id = ii.invoice_id '
        . 'WHERE ii.order_id = ? AND i.deleted_at IS NULL AND i.status <> \'void\' '
        . 'LIMIT 1'
    );
    $stmt->execute([$orderId]);
    return (bool) $stmt->fetchColumn();
}
