<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
internal_page_start($user, 'transactions', 'Transactions', 'Record payments and allocate them to invoices.');
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h3>New payment</h3>
            <p>Capture customer deposits or payments.</p>
        </div>
    </div>
    <form class="grid-form">
        <label>
            <span>Customer ID</span>
            <input type="text" placeholder="Customer ID">
        </label>
        <label>
            <span>Branch ID</span>
            <input type="text" placeholder="Branch ID">
        </label>
        <label>
            <span>Payment method</span>
            <select>
                <option>Cash</option>
                <option>Credit</option>
                <option>Whish</option>
            </select>
        </label>
        <label>
            <span>Amount</span>
            <input type="number" step="0.01" placeholder="0.00">
        </label>
        <label>
            <span>Payment date</span>
            <input type="date">
        </label>
        <label>
            <span>Note</span>
            <input type="text" placeholder="Optional note">
        </label>
        <button class="button primary" type="button">Save payment</button>
    </form>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3>Allocate to invoices</h3>
            <p>Assign partial payments to open invoices.</p>
        </div>
    </div>
    <form class="grid-form">
        <label>
            <span>Transaction ID</span>
            <input type="text" placeholder="Transaction ID">
        </label>
        <label>
            <span>Invoice ID</span>
            <input type="text" placeholder="Invoice ID">
        </label>
        <label>
            <span>Amount</span>
            <input type="number" step="0.01" placeholder="0.00">
        </label>
        <button class="button ghost" type="button">Add allocation</button>
    </form>
</section>
<?php
internal_page_end();
