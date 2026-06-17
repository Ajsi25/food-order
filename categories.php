<?php
require_once '../config/firebase.php';
require_once '../config/auth.php';
require_login();

$success = $error = '';

function local_categories_file(): string
{
    return __DIR__ . '/../data/categories.json';
}

function local_categories_get(): array
{
    $file = local_categories_file();
    if (!is_file($file)) {
        return [];
    }

    $categories = json_decode(file_get_contents($file), true);
    return is_array($categories) ? $categories : [];
}

function local_categories_save(array $categories): void
{
    file_put_contents(local_categories_file(), json_encode(array_values($categories), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function local_categories_add(string $name): void
{
    $categories = local_categories_get();
    $categories[] = [
        'id' => 'local_cat_' . uniqid(),
        'name' => $name,
        'created_at' => date('Y-m-d H:i:s'),
    ];
    local_categories_save($categories);
}

function local_categories_exists(string $name, string $except_id = ''): bool
{
    foreach (local_categories_get() as $category) {
        if (($category['id'] ?? '') !== $except_id && strtolower($category['name'] ?? '') === strtolower($name)) {
            return true;
        }
    }

    return false;
}

function local_categories_update(string $id, string $name): bool
{
    $categories = local_categories_get();
    foreach ($categories as &$category) {
        if (($category['id'] ?? '') === $id) {
            $category['name'] = $name;
            local_categories_save($categories);
            return true;
        }
    }

    return false;
}

function local_categories_delete(string $id): bool
{
    $categories = local_categories_get();
    $filtered = array_values(array_filter($categories, fn($category) => ($category['id'] ?? '') !== $id));
    if (count($filtered) === count($categories)) {
        return false;
    }

    local_categories_save($filtered);
    return true;
}

function find_category_by_id(array $categories, string $id): ?array
{
    foreach ($categories as $category) {
        if (($category['id'] ?? '') === $id) {
            return $category;
        }
    }

    return null;
}

// CREATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $name = trim($_POST['name'] ?? '');
    if (!$name) {
        $error = 'Emri i kategorisë është i detyrueshëm.';
    } elseif (local_categories_exists($name)) {
        $error = 'Kjo kategori ekziston tashmë.';
    } else {
        $res = firebase_post('categories', ['name' => $name, 'created_at' => date('Y-m-d H:i:s')]);
        if (!isset($res['name'])) {
            local_categories_add($name);
        }
        $success = 'Kategoria u shtua me sukses!';
    }
}

// UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id   = trim($_POST['id']   ?? '');
    $name = trim($_POST['name'] ?? '');
    if (!$id || !$name) {
        $error = 'Plotëso të gjitha fushat.';
    } elseif (local_categories_exists($name, $id)) {
        $error = 'Kjo kategori ekziston tashmë.';
    } else {
        $updated = str_starts_with($id, 'local_') || in_array($id, ['pizza', 'burger', 'sallata', 'pasta', 'embelsira', 'supe', 'sushi', 'fast-food'], true)
            ? local_categories_update($id, $name)
            : isset(firebase_patch('categories', $id, ['name' => $name])['name']);

        $updated ? $success = 'Kategoria u përditësua!' : $error = 'Kategoria nuk u përditësua.';
    }
}

// DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = trim($_POST['id'] ?? '');
    if ($id) {
        $deleted = str_starts_with($id, 'local_') || in_array($id, ['pizza', 'burger', 'sallata', 'pasta', 'embelsira', 'supe', 'sushi', 'fast-food'], true)
            ? local_categories_delete($id)
            : firebase_delete('categories', $id);

        $deleted ? $success = 'Kategoria u fshi!' : $error = 'Kategoria nuk u fshi.';
    }
}

$remote_categories = firebase_list_to_array(firebase_get('categories'));
$categories = merge_by_id($remote_categories, local_categories_get());
$edit_category = find_category_by_id($categories, trim($_GET['edit'] ?? ''));
?>
<!DOCTYPE html>
<html lang="sq">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategoritë - FoodOrder</title>
    <link rel="stylesheet" href="css/admin.css">
</head>

<body>
    <?php include 'partials/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <h1>Kategoritë</h1>
            <a class="btn btn-primary" href="categories.php?add=1">+ Shto Kategori</a>
        </div>

        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <section class="panel-card">
            <div class="panel-header">
                <h2>Lista e kategorive</h2>
                <span><?= count($categories) ?> kategori në total</span>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Emri</th>
                            <th>Data</th>
                            <th>Veprimet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="4" class="empty-state">Nuk ka kategori në sistem.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($categories as $i => $cat): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($cat['name']) ?></td>
                        <td><?= htmlspecialchars($cat['created_at'] ?? '-') ?></td>
                        <td>
                            <a class="btn btn-sm btn-warning" href="categories.php?edit=<?= urlencode($cat['id']) ?>">Ndrysho</a>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Fshi kategorinë?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($cat['id']) ?>">
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
            <h2>Shto Kategori</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label>Emri i kategorisë</label>
                    <input type="text" name="name" required placeholder="p.sh. Pica, Burger...">
                </div>
                <div class="form-actions">
                    <a class="btn btn-secondary" href="categories.php">Anulo</a>
                    <button type="submit" class="btn btn-primary">Shto</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal <?= $edit_category ? 'active' : '' ?>">
        <div class="modal-box">
            <h2>Ndrysho Kategori</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editId" value="<?= htmlspecialchars($edit_category['id'] ?? '') ?>">
                <div class="form-group">
                    <label>Emri i kategorisë</label>
                    <input type="text" name="name" id="editName" required value="<?= htmlspecialchars($edit_category['name'] ?? '') ?>">
                </div>
                <div class="form-actions">
                    <a class="btn btn-secondary" href="categories.php">Anulo</a>
                    <button type="submit" class="btn btn-primary">Ruaj</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/admin.js"></script>
</body>

</html>


