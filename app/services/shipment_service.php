<?php
declare(strict_types=1);

function compute_qty(
    string $unitType,
    string $weightType,
    ?float $actualWeight,
    ?float $w,
    ?float $d,
    ?float $h
): float {
    if ($weightType === 'actual') {
        return max(0.0, (float) $actualWeight);
    }

    $volume = (float) $w * (float) $d * (float) $h;
    return max(0.0, $volume);
}

function compute_base_price(float $qty, float $rate): float
{
    return round($qty * $rate, 2);
}

function compute_adjustments_total(array $adjustments, float $basePrice): float
{
    $total = 0.0;
    foreach ($adjustments as $adjustment) {
        $calcType = $adjustment['calc_type'] ?? 'amount';
        $kind = $adjustment['kind'] ?? 'cost';
        $value = (float) ($adjustment['value'] ?? 0);

        $amount = $calcType === 'percentage'
            ? ($basePrice * ($value / 100))
            : $value;

        if ($kind === 'discount') {
            $amount *= -1;
        }

        $total += $amount;
    }

    return round($total, 2);
}

function update_shipment_totals(int $shipmentId): void
{
    $db = db();
    $stmt = $db->prepare(
        'SELECT '
        . "SUM(CASE WHEN weight_type = 'actual' THEN COALESCE(actual_weight, 0) ELSE 0 END) AS total_weight, "
        . "SUM(CASE WHEN weight_type = 'volumetric' THEN COALESCE(w, 0) * COALESCE(d, 0) * COALESCE(h, 0) ELSE 0 END) "
        . 'AS total_volume '
        . 'FROM orders WHERE shipment_id = ? AND deleted_at IS NULL'
    );
    $stmt->execute([$shipmentId]);
    $totals = $stmt->fetch() ?: ['total_weight' => 0, 'total_volume' => 0];

    $weight = round((float) ($totals['total_weight'] ?? 0), 3);
    $volume = round((float) ($totals['total_volume'] ?? 0), 3);

    $update = $db->prepare('UPDATE shipments SET weight = ?, size = ?, updated_at = NOW() WHERE id = ?');
    $update->execute([$weight, $volume, $shipmentId]);
}
