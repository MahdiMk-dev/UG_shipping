<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
internal_page_start($user, 'invoices', 'Invoices', 'Issue and track customer invoices.');
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h3>Create invoice</h3>
            <p>Bundle orders and issue a printable statement.</p>
        </div>
        <button class="button ghost small" type="button">New invoice</button>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Due</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="5" class="muted">Wire this table to `api/invoices/view.php`.</td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="table-pagination">
        <button class="button ghost small" type="button" disabled>Previous</button>
        <span class="page-label">Page 1</span>
        <button class="button ghost small" type="button" disabled>Next</button>
    </div>
</section>
<?php
internal_page_end();
