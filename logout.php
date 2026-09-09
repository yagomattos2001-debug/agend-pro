<?php
/**
 * AGEND PRO - Logout do Usuário
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

logoutUser();
setFlashMessage('success', 'Você saiu do sistema com segurança.');
header('Location: /login.php');
exit;
