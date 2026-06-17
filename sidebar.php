<nav class="sidebar">
    <a href="../index.php" class="sidebar-logo">FoodOrder</a>
    <ul class="sidebar-menu">
        <li><a href="dashboard.php" <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'class="active"' : '' ?>>Dashboard</a></li>
        <li><a href="orders.php" <?= basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'class="active"' : '' ?>>Porositë</a></li>
        <li><a href="foods.php" <?= basename($_SERVER['PHP_SELF']) === 'foods.php' ? 'class="active"' : '' ?>>Produktet</a></li>
        <li><a href="categories.php" <?= basename($_SERVER['PHP_SELF']) === 'categories.php' ? 'class="active"' : '' ?>>Kategoritë</a></li>
        <li><a href="admins.php" <?= basename($_SERVER['PHP_SELF']) === 'admins.php' ? 'class="active"' : '' ?>>Adminët</a></li>
        <li class="sidebar-divider"></li>
        <li><a href="logout.php">Dil</a></li>
    </ul>
</nav>
