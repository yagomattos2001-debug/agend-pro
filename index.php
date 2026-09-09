<?php
/**
 * AGEND PRO - Ponto de Entrada Principal
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: /admin/dashboard.php');
    exit;
}

header('Location: /public/index.php');
exit;
