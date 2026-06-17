<?php
require_once '../config/firebase.php';
require_once '../config/auth.php';
require_login();

$success = $error = '';

function local_categories_get(): array
{
    $file = __DIR__ . '/../data/categories.json';
    if (!is_file($file)) {
        return [];
    }

    $categories = json_decode(file_get_contents($file), true);
    return is_array($categories) ? $categories : [];
}

function local_foods_file(): string
{
    return __DIR__ . '/../data/foods.json';
}

function local_foods_get(): array
{
    $file = local_foods_file();
    if (!is_file($file)) {
        return [];
    }

    $foods = json_decode(file_get_contents($file), true);
    return is_array($foods) ? $foods : [];
}

function local_foods_save(array $foods): void
{
    file_put_contents(local_foods_file(), json_encode(array_values($foods), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function local_foods_add(array $food): void
{
    $foods = local_foods_get();
    $food['id'] = 'local_food_' . uniqid();
    $food['created_at'] = date('Y-m-d H:i:s');
    $foods[] = $food;
    local_foods_save($foods);
}

function local_foods_exists(string $name, string $except_id = ''): bool
{
    foreach (local_foods_get() as $food) {
        if (($food['id'] ?? '') !== $except_id && strtolower($food['name'] ?? '') === strtolower($name)) {
            return true;
        }
    }

    return false;
}

function local_foods_update(string $id, array $data): bool
{
    $foods = local_foods_get();
    foreach ($foods as &$food) {
        if (($food['id'] ?? '') === $id) {
            $food = array_merge($food, $data);
            local_foods_save($foods);
            return true;
        }
    }

    return false;
}

function local_foods_delete(string $id): bool
{
    $foods = local_foods_get();
    $filtered = array_values(array_filter($foods, fn($food) => ($food['id'] ?? '') !== $id));
    if (count($filtered) === count($foods)) {
        return false;
    }

    local_foods_save($filtered);
    return true;
}

function find_food_by_id(array $foods, string $id): ?array
{
    foreach ($foods as $food) {
        if (($food['id'] ?? '') === $id) {
            return $food;
        }
    }

    return null;
}

// CREATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $name        = trim($_POST['name']        ?? '');
    $price       = (float)($_POST['price']    ?? 0);
    $category_id = trim($_POST['category_id'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $image       = trim($_POST['image']       ?? '');

    if (!$name || $price <= 0 || !$category_id) {
        $error = 'Plotësoni emrin, çmimin dhe kategorinë.';
    } elseif (local_foods_exists($name)) {
        $error = 'Ky produkt ekziston tashmë.';
    } else {
        $foodData = [
            'name'        => $name,
            'price'       => $price,
            'category_id' => $category_id,
            'description' => $description,
            'image'       => $image,
            'created_at'  => date('Y-m-d H:i:s'),
        ];

        $res = firebase_post('foods', $foodData);
        if (!isset($res['name'])) {
            local_foods_add($foodData);
        }
        $success = 'Produkti u shtua me sukses!';
    }
}

// UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id          = trim($_POST['id']          ?? '');
    $name        = trim($_POST['name']        ?? '');
    $price       = (float)($_POST['price']    ?? 0);
    $category_id = trim($_POST['category_id'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $image       = trim($_POST['image']       ?? '');

    if (!$id || !$name || $price <= 0 || !$category_id) {
        $error = 'Plotësoni emrin, çmimin dhe kategorinë.';
    } elseif (local_foods_exists($name, $id)) {
        $error = 'Ky produkt ekziston tashmë.';
    } else {
        $data = [
            'name' => $name,
            'price' => $price,
            'category_id' => $category_id,
            'description' => $description,
            'image' => $image,
        ];

        $updated = str_starts_with($id, 'local_') || in_array($id, ['1', '2', '3', '4', '5', '6', '7', '8'], true)
            ? local_foods_update($id, $data)
            : isset(firebase_patch('foods', $id, $data)['name']);

        $updated ? $success = 'Produkti u përditësua!' : $error = 'Produkti nuk u përditësua.';
    }
}

// DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = trim($_POST['id'] ?? '');
    if ($id) {
        $deleted = str_starts_with($id, 'local_') || in_array($id, ['1', '2', '3', '4', '5', '6', '7', '8'], true)
            ? local_foods_delete($id)
            : firebase_delete('foods', $id);

        $deleted ? $success = 'Produkti u fshi!' : $error = 'Produkti nuk u fshi.';
    }
}

$remote_foods = firebase_list_to_array(firebase_get('foods'));
$remote_categories = firebase_list_to_array(firebase_get('categories'));
$foods = merge_by_id($remote_foods, local_foods_get());
$categories = merge_by_id($remote_categories, local_categories_get());
$cat_map    = array_column($categories, 'name', 'id');
$edit_food = find_food_by_id($foods, trim($_GET['edit'] ?? ''));
?>
<!DOCTYPE html>
<html lang="sq">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produktet - FoodOrder</title>
    <link rel="stylesheet" href="css/admin.css">
</head>

<body>
    <?php include 'partials/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <h1>Produktet</h1>
            <a class="btn btn-primary" href="foods.php?add=1">+ Shto Produkt</a>
        </div>

        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <section class="panel-card">
            <div class="panel-header">
                <h2>Lista e produkteve</h2>
                <span><?= count($foods) ?> produkte në total</span>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Imazhi</th>
                            <th>Emri</th>
                            <th>Kategoria</th>
                            <th>Çmimi</th>
                            <th>Veprimet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($foods)): ?>
                            <tr>
                                <td colspan="6" class="empty-state">Nuk ka produkte në sistem.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($foods as $i => $food): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td>
                            <?php if (!empty($food['image'])): ?>
                                <img src="<?= htmlspecialchars($food['image']) ?>" alt="" style="width:50px;height:50px;object-fit:cover;border-radius:8px;">
                            <?php else: ?>
                                <span style="color:rgba(255,255,255,.45);font-size:.85rem;">Pa imazh</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($food['name']) ?></td>
                        <td><?= htmlspecialchars($cat_map[$food['category_id'] ?? ''] ?? '-') ?></td>
                        <td><?= number_format((float)($food['price'] ?? 0), 2) ?> L</td>
                        <td>
                            <a class="btn btn-sm btn-warning" href="foods.php?edit=<?= urlencode($food['id']) ?>">Ndrysho</a>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Fshi produktin?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($food['id']) ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Fshi</button>
                            </form>
                        </td>
                    </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- Add Modal -->
    <div id="addModal" class="modal <?= isset($_GET['add']) ? 'active' : '' ?>">
        <div class="modal-box">
            <h2>Shto Produkt</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label>Emri *</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Çmimi (L) *</label>
                    <input type="number" name="price" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Kategoria *</label>
                    <select name="category_id" required>
                        <option value="">-- Zgjidh --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Përshkrimi</label>
                    <textarea name="description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Linku i imazhit</label>
                    <input type="url" name="image" placeholder="https://...">
                </div>
                <div class="form-actions">
                    <a class="btn btn-secondary" href="foods.php">Anulo</a>
                    <button type="submit" class="btn btn-primary">Shto</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal <?= $edit_food ? 'active' : '' ?>">
        <div class="modal-box">
            <h2>Ndrysho Produkt</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editFoodId" value="<?= htmlspecialchars($edit_food['id'] ?? '') ?>">
                <div class="form-group">
                    <label>Emri *</label>
                    <input type="text" name="name" id="editFoodName" required value="<?= htmlspecialchars($edit_food['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Çmimi (L) *</label>
                    <input type="number" name="price" id="editFoodPrice" step="0.01" required value="<?= htmlspecialchars($edit_food['price'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Kategoria *</label>
                    <select name="category_id" id="editFoodCategory" required>
                        <option value="">-- Zgjidh --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['id']) ?>" <?= (($edit_food['category_id'] ?? '') === ($cat['id'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Përshkrimi</label>
                    <textarea name="description" id="editFoodDesc" rows="3"><?= htmlspecialchars($edit_food['description'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Linku i imazhit</label>
                    <input type="url" name="image" value="<?= htmlspecialchars($edit_food['image'] ?? '') ?>">
                </div>
                <div class="form-actions">
                    <a class="btn btn-secondary" href="foods.php">Anulo</a>
                    <button type="submit" class="btn btn-primary">Ruaj</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/admin.js"></script>
    <script>
        function openEditFood(id, name, price, catId, desc) {
            document.getElementById('editFoodId').value = id;
            document.getElementById('editFoodName').value = name;
            document.getElementById('editFoodPrice').value = price;
            document.getElementById('editFoodCategory').value = catId;
            document.getElementById('editFoodDesc').value = desc;
            openModal('editModal');
        }
    </script>
</body>

</html>

