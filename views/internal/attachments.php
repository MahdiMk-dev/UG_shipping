<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
internal_page_start($user, 'attachments', 'Attachments', 'Upload and manage documents tied to shipments and orders.');
?>
<div data-attachments-page>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Upload file</h3>
                <p>Attach documents to shipments, orders, shopping orders, or invoices.</p>
            </div>
        </div>
        <form class="grid-form" data-attachments-upload-form enctype="multipart/form-data">
            <label>
                <span>Entity type</span>
                <select name="entity_type" required>
                    <option value="shipment">Shipment</option>
                    <option value="order">Order</option>
                    <option value="shopping_order">Shopping order</option>
                    <option value="invoice">Invoice</option>
                </select>
            </label>
            <label>
                <span>Entity ID</span>
                <input type="number" name="entity_id" placeholder="Entity ID" required>
            </label>
            <label>
                <span>Title</span>
                <input type="text" name="title" placeholder="Attachment title">
            </label>
            <label>
                <span>Description</span>
                <input type="text" name="description" placeholder="Optional notes">
            </label>
            <label>
                <span>File</span>
                <input type="file" name="file" required>
            </label>
            <button class="button primary" type="submit">Upload</button>
        </form>
        <div class="notice-stack" data-attachments-upload-status></div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Recent uploads</h3>
                <p>Latest files across all entities.</p>
            </div>
        </div>
        <form class="filter-bar" data-attachments-filter>
            <input type="text" name="q" placeholder="Shipment # or tracking #">
            <select name="entity_type">
                <option value="">All types</option>
                <option value="shipment">Shipment</option>
                <option value="order">Order</option>
                <option value="shopping_order">Shopping order</option>
                <option value="invoice">Invoice</option>
            </select>
            <button class="button primary" type="submit">Search</button>
            <button class="button ghost" type="button" data-attachments-refresh>Refresh</button>
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Entity</th>
                        <th>Type</th>
                        <th>Uploaded</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody data-attachments-table>
                    <tr>
                        <td colspan="5" class="muted">Loading attachments...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination">
            <button class="button ghost small" type="button" data-attachments-prev>Previous</button>
            <span class="page-label" data-attachments-page-label>Page 1</span>
            <button class="button ghost small" type="button" data-attachments-next>Next</button>
        </div>
        <div class="notice-stack" data-attachments-status></div>
    </section>
</div>
<?php
internal_page_end();
