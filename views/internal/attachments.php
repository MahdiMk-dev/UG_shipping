<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
internal_page_start($user, 'attachments', 'Attachments', 'Upload and manage documents tied to shipments and orders.');
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h3>Upload file</h3>
            <p>Attach documents to shipments, orders, or invoices.</p>
        </div>
    </div>
    <form class="grid-form" enctype="multipart/form-data">
        <label>
            <span>Entity type</span>
            <select>
                <option>Shipment</option>
                <option>Order</option>
                <option>Shopping order</option>
                <option>Invoice</option>
            </select>
        </label>
        <label>
            <span>Entity ID</span>
            <input type="text" placeholder="Entity ID">
        </label>
        <label>
            <span>Title</span>
            <input type="text" placeholder="Attachment title">
        </label>
        <label>
            <span>File</span>
            <input type="file">
        </label>
        <button class="button primary" type="button">Upload</button>
    </form>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3>Recent uploads</h3>
            <p>Latest files across all entities.</p>
        </div>
    </div>
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
            <tbody>
                <tr>
                    <td colspan="5" class="muted">Connect to `api/attachments/list.php`.</td>
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
