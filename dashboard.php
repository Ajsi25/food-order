<?php
require_once '../config/firebase.php';
require_once '../config/auth.php';

require_login();

$admin = current_admin();

function local_orders_get(): array
{
    $file = __DIR__ . '/../data/orders.json';
    if (!is_file($file)) {
        return [];
    }

    $orders = json_decode(file_get_contents($file), true);
    return is_array($orders) ? decrypt_orders($orders) : [];
}

$remote_orders = firebase_list_to_array(firebase_get('orders'));
$orders = merge_by_id(local_orders_get(), decrypt_orders($remote_orders));
$foods = firebase_list_to_array(firebase_get('foods'));
$categories = firebase_list_to_array(firebase_get('categories'));

usort($orders, function ($a, $b) {
    return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
});

$total_revenue = array_sum(array_map(fn($o) => (float)($o['total_price'] ?? 0), $orders));
$recent_orders = array_slice($orders, 0, 10);

function dashboard_items_text($items): string
{
    if (!$items) return '-';

    $decoded = is_string($items) ? json_decode($items, true) : $items;
    if (!is_array($decoded)) return '-';

    $parts = [];
    foreach ($decoded as $item) {
        $name = $item['name'] ?? 'Produkt';
        $qty = $item['qty'] ?? 1;
        $parts[] = $name . ' x' . $qty;
    }

    return implode(', ', $parts);
}

function dashboard_status_badge($status): string
{
    $status = strtolower($status ?: 'pending');
    $labels = [
        'pending' => 'Në pritje',
        'processing' => 'Duke u bërë',
        'completed' => 'Përfunduar',
        'cancelled' => 'Anuluar',
    ];
    $safe_status = htmlspecialchars($status);
    $label = htmlspecialchars($labels[$status] ?? $status);

    return '<span class="badge badge-' . $safe_status . '">' . $label . '</span>';
}
?>
<!DOCTYPE html>
<html lang="sq">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - FoodOrder Admin</title>
    <link rel="stylesheet" href="css/admin.css">
</head>

<body>
    <?php include 'partials/sidebar.php'; ?>

    <!-- Main content -->
    <main class="main-content">
        <div class="page-header">
            <div>
                <h1>Mirë se erdhe, <?= htmlspecialchars(explode(' ', $admin['name'])[0] ?: 'Admin') ?>!</h1>
                <p>Këtu është një përmbledhje e aktivitetit të sotëm.</p>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 11l3 3L22 4" />
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
                    </svg>
                </div>
                <div>
                    <h3><?= count($orders) ?></h3>
                    <p>Porosi gjithsej</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                    </svg>
                </div>
                <div>
                    <h3><?= count($foods) ?></h3>
                    <p>Produkte</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="8" y1="6" x2="21" y2="6" />
                        <line x1="8" y1="12" x2="21" y2="12" />
                        <line x1="8" y1="18" x2="21" y2="18" />
                    </svg>
                </div>
                <div>
                    <h3><?= count($categories) ?></h3>
                    <p>Kategori</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2L2 7l10 5 10-5-10-5z" />
                        <path d="M2 17l10 5 10-5" />
                        <path d="M2 12l10 5 10-5" />
                    </svg>
                </div>
                <div>
                    <h3><?= number_format($total_revenue, 2) ?> L</h3>
                    <p>Të ardhura</p>
                </div>
            </div>
        </div>

        <!-- Recent orders table -->
        <section class="panel-card">
            <div class="panel-header">
                <h2>Porositë e fundit</h2>
                <span><?= count($orders) ?> porosi në total</span>
            </div>
            <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Klienti</th>
                        <th>Produktet</th>
                        <th>Totali</th>
                        <th>Statusi</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_orders)): ?>
                        <tr>
                            <td colspan="6" class="empty-state">Nuk ka porosi akoma.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($recent_orders as $order): ?>
                        <tr>
                            <td style="color:rgba(255,255,255,.4);font-size:.75rem"><?= htmlspecialchars(substr($order['id'] ?? '', 0, 8)) ?>...</td>
                            <td><?= htmlspecialchars($order['customer_name'] ?? '-') ?></td>
                            <td class="items-cell"><?= htmlspecialchars(dashboard_items_text($order['items'] ?? '')) ?></td>
                            <td style="color:#f97316;font-weight:700"><?= number_format((float)($order['total_price'] ?? 0), 2) ?> L</td>
                            <td><?= dashboard_status_badge($order['status'] ?? 'pending') ?></td>
                            <td><?= htmlspecialchars($order['created_at'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        </section>
    </main>

</body>

</html>



