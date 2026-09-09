<?php
/**
 * AGEND PRO - Controle de Autenticação e Sessão Segura
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    // Configurações de segurança para a sessão com suporte a iframes (AI Studio)
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '0');
    ini_set('session.use_cookies', '1');
    
    // Cookie SameSite=None e Secure para funcionar em iframes cross-origin e abas
    session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'path'     => '/',
        'domain'   => '',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'None'
    ]);

    // Fallback: caso o navegador bloqueie cookies de terceiros no iframe,
    // recupera o session_id enviado via query string, post ou header
    $incomingSid = $_GET['sid'] ?? $_POST['sid'] ?? $_SERVER['HTTP_X_SESSION_ID'] ?? null;
    if ($incomingSid && is_string($incomingSid) && preg_match('/^[a-zA-Z0-9,-]{16,128}$/', $incomingSid)) {
        session_id($incomingSid);
    }

    session_start();
}

require_once __DIR__ . '/functions.php';

/**
 * Verifica se há um usuário autenticado na sessão atual.
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['usuario_id']);
}

/**
 * Exige autenticação. Redireciona para /login.php caso o usuário não esteja logado.
 */
function requireAuth(): void
{
    if (!isLoggedIn()) {
        setFlashMessage('error', 'Acesso restrito. Faça login para acessar o painel administrativo.');
        header('Location: /login.php');
        exit;
    }
}

/**
 * Retorna as informações do usuário autenticado.
 */
function getAuthUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'id'    => (int)$_SESSION['usuario_id'],
        'nome'  => $_SESSION['usuario_nome'] ?? 'Administrador',
        'email' => $_SESSION['usuario_email'] ?? '',
    ];
}

/**
 * Registra o login do usuário e regenera o ID da sessão para evitar Session Fixation.
 */
function loginUser(array $usuario): void
{
    session_regenerate_id(true);
    $_SESSION['usuario_id']    = $usuario['id'];
    $_SESSION['usuario_nome']  = $usuario['nome'];
    $_SESSION['usuario_email'] = $usuario['email'];

    setcookie(session_name(), session_id(), [
        'expires'  => time() + (86400 * 7),
        'path'     => '/',
        'domain'   => '',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'None'
    ]);
}

/**
 * Encerra a sessão do usuário de forma segura.
 */
function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params["path"] ?: '/',
            'domain'   => $params["domain"] ?: '',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'None'
        ]);
    }

    session_destroy();
}
