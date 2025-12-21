<?php
declare(strict_types=1);

function invoice_status_from_totals(float $paidTotal, float $invoiceTotal): string
{
    if ($paidTotal <= 0.0) {
        return 'open';
    }

    if ($paidTotal + 0.0001 < $invoiceTotal) {
        return 'partially_paid';
    }

    return 'paid';
}
