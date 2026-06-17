<?php
require_once '../config/firebase.php';
require_once '../config/auth.php';
require_login();

$success = $error = '';

function local_admins_file(): string
{
    return __DIR__ . '/../data/admins.json';
}

function local_admins_get(): array
{
    $file = local_admins_file();
    if (!is_file($file)) {
        return [];
    }

    $admins = json_decode(file_get_contents($file), true);
    return is_array($admins) ? decrypt_admins($admins) : [];
}

function local_admins_save(array $admins): void
{
    $admins = array_map('encrypt_admin_data', $admins);
    file_put_contents(local_admins_file(), json_encode(array_values($admins), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function local_admins_add(string $name, string $email, string $password): void
{
    $admins = local_admins_get();
    $admins[] = [
        'id' => 'local_admin_' . uniqid(),
        'name' => $name,
        'email' => $email,
        'email_lookup' => email_lookup($email),
        'password' => password_hash($password, PASSWORD_BCRYPT),
        'created_at' => date('Y-m-d H:i:s'),
    ];
    local_admins_save($admins);
}

function local_admins_email_exists(string $email, string $except_id = ''): bool
{
    foreach (local_admins_get() as $admin) {
        if (($admin['id'] ?? '') !== $except_id && strtolower($admin['email'] ?? '') === strtolower($email)) {
            return true;
        }
    }

    return false;
}

function local_admins_update(string $id, array $data): bool
{
    $admins = local_admins_get();
    foreach ($admins as &$admin) {
        if (($admin['id'] ?? '') === $id) {
            $admin = array_merge($admin, $data);
            local_admins_save($admins);
            return true;
        }
    }

    return false;
}

function local_admins_delete(string $id): bool
{
    $admins = local_admins_get();
    $filtered = array_values(array_filter($admins, fn($admin) => ($admin['id'] ?? '') !== $id));
    if (count($filtered) === count($admins)) {
        return false;
    }

    local_admins_save($filtered);
    return true;
}

function find_admin_by_id(array $admins, string $id): ?array
{
    foreach ($admins as $admin) {
        if (($admin['id'] ?? '') === $id) {
            return $admin;
        }
    }

    return null;
}

// CREATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$name || !$email || !$password) {
        $error = 'Plotëso të gjitha fushat.';
    } elseif (strlen($password) < 6) {
        $error = 'Fjalëkalimi duhet të ketë të paktën 6 karaktere.';
    } elseif (local_admins_email_exists($email)) {
        $error = 'Ky email ekziston tashmë.';
    } else {
        $adminData = [
            'name' => $name,
            'email' => $email,
            'email_lookup' => email_lookup($email),
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $res = firebase_post('admins', mask_admin_data($adminData));
        if (!isset($res['name'])) {
            local_admins_add($name, $email, $password);
        }

        $success = 'Admini u shtua!';
    }
}

// UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id    = trim($_POST['id']    ?? '');
    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');
    if (!$id || !$name || !$email) {
        $error = 'Plotëso të gjitha fushat.';
    } elseif (local_admins_email_exists($email, $id)) {
        $error = 'Ky email ekziston tashmë.';
    } else {
        $updated = str_starts_with($id, 'local_')
            ? local_admins_update($id, ['name' => $name, 'email' => $email, 'email_lookup' => email_lookup($email)])
            : isset(firebase_patch('admins', $id, mask_admin_data(['name' => $name, 'email' => $email, 'email_lookup' => email_lookup($email)]))['name']);

        $updated ? $success = 'Admini u përditësua!' : $error = 'Admini nuk u gjet ose nuk u përditësua.';
    }
}

// CHANGE PASSWORD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $id       = trim($_POST['id']       ?? '');
    $password = trim($_POST['password'] ?? '');
    if ($id && strlen($password) >= 6) {
        $data = ['password' => password_hash($password, PASSWORD_BCRYPT)];
        $updated = str_starts_with($id, 'local_')
            ? local_admins_update($id, $data)
            : isset(firebase_patch('admins', $id, mask_admin_data($data))['name']);

        $updated ? $success = 'Fjalëkalimi u ndryshua!' : $error = 'Fjalëkalimi nuk u ndryshua.';
    } else {
        $error = 'Fjalëkalimi duhet të ketë të paktën 6 karaktere.';
    }
}

// DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = trim($_POST['id'] ?? '');
    if ($id && $id !== current_admin()['id']) {
        $deleted = str_starts_with($id, 'local_')
            ? local_admins_delete($id)
            : firebase_delete('admins', $id);

        $deleted ? $success = 'Admini u fshi!' : $error = 'Admini nuk u fshi.';
    } else {
        $error = 'Nuk mund të fshish veten!';
    }
}

$remote_admins = firebase_list_to_array(firebase_get('admins'));
$admins = decrypt_admins(merge_by_id($remote_admins, local_admins_get()));
$edit_admin = find_admin_by_id($admins, trim($_GET['edit'] ?? ''));
$password_admin = find_admin_by_id($admins, trim($_GET['password'] ?? ''));
?>
<!DOCTYPE html>
<html lang="sq">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adminët - FoodOrder</title>
    <link rel="stylesheet" href="css/admin.css">
</head>

<body>
    <?php include 'partials/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <h1>Adminët</h1>
            <a class="btn btn-primary" href="admins.php?add=1">+ Shto Admin</a>
        </div>

        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <section class="panel-card">
            <div class="panel-header">
                <h2>Lista e adminëve</h2>
                <span><?= count($admins) ?> adminë në total</span>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Emri</th>
                            <th>Email</th>
                            <th>Data</th>
                            <th>Veprimet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($admins)): ?>
                            <tr>
                                <td colspan="5" class="empty-state">Nuk ka adminë në sistem.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($admins as $i => $admin): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($admin['name']) ?></td>
                        <td><?= htmlspecialchars($admin['email']) ?></td>
                        <td><?= htmlspecialchars($admin['created_at'] ?? '-') ?></td>
                        <td>
                            <a class="btn btn-sm btn-warning" href="admins.php?edit=<?= urlencode($admin['id']) ?>">Ndrysho</a>
                            <a class="btn btn-sm btn-info" href="admins.php?password=<?= urlencode($admin['id']) ?>">Fjalëkalimi</a>
                            <?php if ($admin['id'] !== current_admin()['id']): ?>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Fshi adminin?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($admin['id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Fshi</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- Add -->
    <div id="addModal" class="modal <?= isset($_GET['add']) ? 'active' : '' ?>">
        <div class="modal-box">
            <h2>Shto Admin</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="form-group"><label>Emri</label><input type="text" name="name" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
                <div class="form-group"><label>Fjalëkalimi</label><input type="password" name="password" required minlength="6"></div>
                <div class="form-actions">
                    <a class="btn btn-secondary" href="admins.php">Anulo</a>
                    <button type="submit" class="btn btn-primary">Shto</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit -->
    <div id="editModal" class="modal <?= $edit_admin ? 'active' : '' ?>">
        <div class="modal-box">
            <h2>Ndrysho Admin</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editAdminId" value="<?= htmlspecialchars($edit_admin['id'] ?? '') ?>">
                <div class="form-group"><label>Emri</label><input type="text" name="name" id="editAdminName" required value="<?= htmlspecialchars($edit_admin['name'] ?? '') ?>"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" id="editAdminEmail" required value="<?= htmlspecialchars($edit_admin['email'] ?? '') ?>"></div>
                <div class="form-actions">
                    <a class="btn btn-secondary" href="admins.php">Anulo</a>
                    <button type="submit" class="btn btn-primary">Ruaj</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Password -->
    <div id="passModal" class="modal <?= $password_admin ? 'active' : '' ?>">
        <div class="modal-box">
            <h2>Ndrysho Fjalëkalimin</h2>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="id" id="passAdminId" value="<?= htmlspecialchars($password_admin['id'] ?? '') ?>">
                <div class="form-group"><label>Fjalëkalimi i Ri</label><input type="password" name="password" required minlength="6"></div>
                <div class="form-actions">
                    <a class="btn btn-secondary" href="admins.php">Anulo</a>
                    <button type="submit" class="btn btn-primary">Ruaj</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/admin.js"></script>
    <script>
    </script>
</body>

</html>

