<?php
require_once '../config/firebase.php';
require_once '../config/auth.php';
require_login();

function local_orders_file(): string
{
    return __DIR__ . '/../data/orders.json';
}

function local_orders_get(): array
{
    $file = local_orders_file();
    if (!is_file($file)) {
        return [];
    }

    $orders = json_decode(file_get_contents($file), true);
    return is_array($orders) ? decrypt_orders($orders) : [];
}

function local_orders_update_status(string $id, string $status): bool
{
    $orders = local_orders_get();
    $changed = false;

    foreach ($orders as &$order) {
        if (($order['id'] ?? '') === $id) {
            $order['status'] = $status;
            $order['updated_at'] = date('Y-m-d H:i:s');
            $changed = true;
            break;
        }
    }

    if ($changed) {
        file_put_contents(local_orders_file(), json_encode(array_map('encrypt_order_data', $orders), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    return $changed;
}

function orders_get_all(): array
{
    $remote_orders = firebase_list_to_array(firebase_get('orders'));

    return merge_by_id(local_orders_get(), decrypt_orders($remote_orders));
}

$success = '';
$error = '';
$allowed_statuses = ['pending', 'processing', 'completed', 'cancelled'];
$status_labels = [
    'pending' => 'Në pritje',
    'processing' => 'Në përgatitje',
    'completed' => 'E përfunduar',
    'cancelled' => 'E anuluar',
];

// PËRDITËSIMI I STATUSIT (Logjika për Firestore)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $id = trim($_POST['id'] ?? '');
    $status = trim($_POST['status'] ?? '');

    if ($id && in_array($status, $allowed_statuses, true)) {
        // Përditësojmë dokumentin ekzistues në Firestore.
        $updateData = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $res = str_starts_with($id, 'local_')
            ? (local_orders_update_status($id, $status) ? ['name' => $id] : [])
            : firebase_patch('orders', $id, $updateData);

        if (isset($res['name'])) {
            $success = 'Statusi i porosisë u përditësua me sukses.';
        } else {
            $error = 'Gabim gjatë përditësimit në Firebase.';
        }
    } else {
        $error = 'Të dhënat e statusit nuk janë të sakta.';
    }
}

// MARRJA E POROSIVE
$orders = orders_get_all();

// Renditja: Nga më e reja te më e vjetra
usort($orders, function ($a, $b) {
    return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
});

// Llogaritja e Statistikave
$total_orders = count($orders);
$pending_orders = count(array_filter($orders, fn($o) => ($o['status'] ?? 'pending') === 'pending'));
$completed_orders = count(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'completed'));
$total_revenue = array_sum(array_map(fn($o) => (float)($o['total_price'] ?? 0), $orders));

/**
 * Funksion për formatimin e produkteve brenda tabelës
 */
function order_items_text($items): string
{
    if (!$items) return '-';

    // Nëse vijnë si JSON string (nga ndonjë formë), i dekodojmë
    $decoded = is_string($items) ? json_decode($items, true) : $items;

    // Në Firestore, array-t ndonjëherë vijnë si objekte të lëfshme
    if (!is_array($decoded)) return '-';

    $parts = [];
    foreach ($decoded as $item) {
        $name = $item['name'] ?? 'Produkt';
        $qty = $item['qty'] ?? 1;
        $price = number_format((float)($item['price'] ?? 0), 2);
        $parts[] = $name . ' x' . $qty . ' (' . $price . ' L)';
    }

    return implode(', ', $parts);
}
?>
<!DOCTYPE html>
<html lang="sq">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Porositë - Admin</title>
    <link rel="stylesheet" href="css/admin.css">
    <style>.items-cell { font-size: .9em; max-width: 250px; }</style>
</head>

<body>
    <?php include 'partials/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1>Menaxhimi i Porosive</h1>
                <p>Shiko porositë, kontrollo detajet dhe ndrysho statusin e tyre.</p>
            </div>
        </div>

        <div class="stats-grid compact-stats">
            <div class="stat-card blue">
                <div class="stat-info">
                    <h3><?= $total_orders ?></h3>
                    <p>Porosi gjithsej</p>
                </div>
            </div>
            <div class="stat-card orange">
                <div class="stat-info">
                    <h3><?= $pending_orders ?></h3>
                    <p>Në pritje</p>
                </div>
            </div>
            <div class="stat-card green">
                <div class="stat-info">
                    <h3><?= $completed_orders ?></h3>
                    <p>Të përfunduara</p>
                </div>
            </div>
            <div class="stat-card purple">
                <div class="stat-info">
                    <h3><?= number_format($total_revenue, 2) ?> L</h3>
                    <p>Të ardhura</p>
                </div>
            </div>
        </div>

        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <section class="panel-card">
            <div class="panel-header">
                <h2>Lista e porosive</h2>
                <span><?= $total_orders ?> porosi në total</span>
            </div>

            <div class="table-wrap">
                <table class="data-table orders-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Klienti</th>
                            <th>Kontakt</th>
                            <th>Adresa</th>
                            <th>Produktet</th>
                            <th>Mënyra</th>
                            <th>Ora Arritjes</th>
                            <th>Totali</th>
                            <th>Statusi</th>
                            <th>Data/Ora</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="10" class="empty-state">Nuk ka ende porosi në sistem.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($orders as $i => $order): ?>
                            <?php $status = $order['status'] ?? 'pending'; ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($order['customer_name'] ?? '-') ?></strong>
                                    <?php if (!empty($order['notes'])): ?>
                                        <br><small style="color: #666;">Shënim: <?= htmlspecialchars($order['notes']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($order['phone'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($order['address'] ?? '-') ?></td>
                                <td class="items-cell"><?= htmlspecialchars(order_items_text($order['items'] ?? '')) ?></td>
                                <td>
                                    <?= htmlspecialchars($order['payment_method'] ?? 'Cash') ?>
                                    <?php if (($order['payment_method'] ?? '') === 'Card' && !empty($order['card_last4'])): ?>
                                        <small>Kartë **** <?= htmlspecialchars($order['card_last4']) ?> · <?= htmlspecialchars($order['payment_status'] ?? 'paid_demo') ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($order['delivery_time'] ?? '-') ?></td>
                                <td><strong><?= number_format((float)($order['total_price'] ?? 0), 2) ?> L</strong></td>
                                <td>
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($order['id']) ?>">
                                        <select name="status" class="status-select status-<?= htmlspecialchars($status) ?>" onchange="this.form.submit()">
                                            <?php foreach ($allowed_statuses as $s): ?>
                                                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= htmlspecialchars($status_labels[$s]) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td><small><?= htmlspecialchars($order['created_at'] ?? '-') ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>

</html>

