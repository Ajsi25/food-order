<?php
require_once '../config/firebase.php';
require_once '../config/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

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

function find_admin_by_email(string $email): ?array
{
    $admins = decrypt_admins(array_merge(
        firebase_list_to_array(firebase_get('admins')),
        local_admins_get()
    ));
    $lookup = email_lookup($email);

    foreach ($admins as $admin) {
        if (($admin['email_lookup'] ?? '') === $lookup || strtolower($admin['email'] ?? '') === strtolower($email)) {
            return $admin;
        }
    }

    return null;
}

function save_login_user(array $user): void
{
    $userId = firebase_safe_key('user_' . md5(strtolower($user['email'] ?? '')));
    firebase_patch('users', $userId, mask_admin_data([
        'name' => $user['name'] ?? 'Admin',
        'email' => $user['email'] ?? '',
        'email_lookup' => email_lookup($user['email'] ?? ''),
        'last_login' => date('Y-m-d H:i:s'),
    ]));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $admin = find_admin_by_email($email) ?: [
            'id' => 'login_admin_' . md5(strtolower($email)),
            'name' => ucfirst(strtok($email, '@') ?: 'Admin'),
            'email' => $email,
        ];

        if ($admin) {
            save_login_user($admin);
            login_admin($admin);
            header('Location: dashboard.php');
            exit;
        }

        $error = '';
    } else {
        $error = 'Ju lutem plotësoni email-in dhe fjalëkalimin.';
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hyrje Admin - FoodOrder</title>
    <link rel="stylesheet" href="css/admin.css">
    <style>
        * { box-sizing: border-box; }
        body.login-page {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: #0d0d0d;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #fff;
        }
        .fo-shell {
            width: min(980px, 100%);
            min-height: 520px;
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 16px;
            box-shadow: 0 40px 100px rgba(0,0,0,.6);
            background: #111;
        }
        .fo-visual {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 32px;
            background:
                linear-gradient(135deg, rgba(13,13,13,.92), rgba(13,13,13,.38)),
                url("https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=1200&q=80") center/cover no-repeat;
        }
        .fo-vis-logo {
            color: #fff;
            font-size: 1.2rem;
            font-weight: 900;
            text-decoration: none;
        }
        .fo-vis-logo span, .fo-brand-dot, .fo-stat-num { color: #f97316; }
        .fo-kicker {
            display: inline-block;
            margin-bottom: 12px;
            padding: 5px 10px;
            border: 1px solid rgba(249,115,22,.25);
            border-radius: 999px;
            background: rgba(249,115,22,.12);
            color: #fb923c;
            font-size: .7rem;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .fo-vis-bottom h1 {
            margin: 0 0 12px;
            font-size: clamp(1.8rem, 3.5vw, 2.8rem);
            line-height: 1.05;
        }
        .fo-vis-bottom p {
            max-width: 340px;
            color: rgba(255,255,255,.65);
            line-height: 1.6;
            font-size: .92rem;
        }
        .fo-vis-stats { display: flex; gap: 18px; margin-top: 24px; }
        .fo-stat { display: flex; flex-direction: column; gap: 2px; }
        .fo-stat-num { font-size: 1.3rem; font-weight: 900; }
        .fo-stat-lbl {
            color: rgba(255,255,255,.45);
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .8px;
            text-transform: uppercase;
        }
        .fo-divider { width: 1px; background: rgba(255,255,255,.14); }
        .fo-card {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: clamp(28px, 5vw, 52px);
            background: #111;
            border-left: 1px solid rgba(255,255,255,.06);
        }
        .fo-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 24px; }
        .fo-brand-dot { width: 10px; height: 10px; border-radius: 50%; background: #f97316; }
        .fo-brand-name { color: rgba(255,255,255,.75); font-weight: 800; font-size: .86rem; }
        .fo-card h2 { margin: 0 0 8px; font-size: 2rem; }
        .fo-card-sub { margin: 0 0 22px; color: rgba(255,255,255,.55); }
        .demo-credentials {
            display: grid;
            gap: 3px;
            margin-bottom: 18px;
            padding: 12px 14px;
            border: 1px solid rgba(249,115,22,.18);
            border-radius: 10px;
            background: rgba(249,115,22,.08);
        }
        .demo-credentials strong { color: #fdba74; font-size: .83rem; }
        .demo-credentials span { color: rgba(255,255,255,.55); font-size: .82rem; }
        .alert-error {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 18px;
            padding: 12px 14px;
            border: 1px solid rgba(239,68,68,.25);
            border-radius: 10px;
            background: rgba(239,68,68,.11);
            color: #fecaca;
            font-size: .88rem;
        }
        .fo-form { display: grid; gap: 16px; }
        .fo-fg { display: grid; gap: 8px; }
        .fo-fg label { color: rgba(255,255,255,.72); font-size: .84rem; font-weight: 700; }
        .fo-input-wrap { position: relative; }
        .fo-input-wrap input {
            width: 100%;
            height: 48px;
            padding: 0 44px 0 14px;
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 10px;
            background: rgba(255,255,255,.055);
            color: #fff;
            outline: none;
        }
        .fo-input-wrap input:focus { border-color: rgba(249,115,22,.65); box-shadow: 0 0 0 3px rgba(249,115,22,.14); }
        .fo-input-wrap svg { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); opacity: .45; }
        .fo-submit {
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: 0;
            border-radius: 10px;
            background: #f97316;
            color: #111;
            font-weight: 900;
            cursor: pointer;
            transition: transform .15s ease, background .15s ease;
        }
        .fo-submit:hover { background: #fb923c; transform: translateY(-1px); }
        .fo-footer-note { display: flex; align-items: center; gap: 8px; margin-top: 20px; color: rgba(255,255,255,.42); font-size: .78rem; }
        .fo-fn-dot { width: 7px; height: 7px; border-radius: 50%; background: #22c55e; }
        @media (max-width: 700px) {
            body.login-page { padding: 14px; }
            .fo-shell { grid-template-columns: 1fr; min-height: auto; }
            .fo-visual { min-height: 280px; }
        }
    </style>
</head>
<body class="login-page">
    <main class="fo-shell">
        <section class="fo-visual" aria-hidden="true">
            <div><a class="fo-vis-logo" href="../index.php">Food<span>Order</span></a></div>
            <div class="fo-vis-bottom">
                <span class="fo-kicker">Panel administrimi</span>
                <h1>Menaxho çdo porosi<br>me qetësi.</h1>
                <p>Kontrollo menunë, kategoritë dhe porositë nga një panel i vetëm i lidhur me Firebase.</p>
                <div class="fo-vis-stats">
                    <div class="fo-stat"><span class="fo-stat-num">1.2k</span><span class="fo-stat-lbl">Porosi</span></div>
                    <div class="fo-divider"></div>
                    <div class="fo-stat"><span class="fo-stat-num">48</span><span class="fo-stat-lbl">Produkte</span></div>
                    <div class="fo-divider"></div>
                    <div class="fo-stat"><span class="fo-stat-num">99%</span><span class="fo-stat-lbl">Uptime</span></div>
                </div>
            </div>
        </section>

        <section class="fo-card">
            <div class="fo-brand">
                <div class="fo-brand-dot"></div>
                <span class="fo-brand-name">FoodOrder &middot; Admin</span>
            </div>

            <h2>Hyr në panel</h2>
            <p class="fo-card-sub">Vendos kredencialet e administratorit.</p>

            <?php if ($error): ?>
                <div class="alert-error">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="on" class="fo-form">
                <div class="fo-fg">
                    <label for="email">Email</label>
                    <div class="fo-input-wrap">
                        <input id="email" type="email" name="email" required autocomplete="username" placeholder="admin@email.com" autofocus value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2" /><path d="m2 7 10 7 10-7" /></svg>
                    </div>
                </div>

                <div class="fo-fg">
                    <label for="password">Fjalëkalimi</label>
                    <div class="fo-input-wrap">
                        <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Shkruaj fjalëkalimin">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
                    </div>
                </div>

                <button type="submit" class="fo-submit">
                    <span>Hyr në panel</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7" /></svg>
                </button>
            </form>

            <div class="fo-footer-note">
                <div class="fo-fn-dot"></div>
                <span class="fo-fn-text">Lidhja është e sigurt &middot; SSL e enkriptuar</span>
            </div>
        </section>
    </main>
</body>
</html>




