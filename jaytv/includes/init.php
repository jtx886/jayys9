&lt;?php
if (!file_exists(__DIR__ . '/../config.php')) {
    header('Location: /install.php');
    exit;
}

if (file_exists(__DIR__ . '/../install.php')) {
    @unlink(__DIR__ . '/../install.php');
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/smtp.php';
require_once __DIR__ . '/functions.php';

Database::getInstance();

$themeColor = getThemeColor();
