<?php
/**
 * AGEND PRO - Tela de Login do Administrador
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Se já estiver logado, vai direto para o dashboard
if (isLoggedIn()) {
    $sid = session_id();
    header('Location: /admin/dashboard.php?sid=' . urlencode($sid));
    exit;
}

$erro = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = (string)($_POST['senha'] ?? '');

    // Permite login tanto com "admin" quanto com "admin@agendpro.com.br"
    $emailBusca = (strtolower($email) === 'admin') ? 'admin@agendpro.com.br' : $email;

    if (empty($emailBusca) || empty($senha)) {
        $erro = 'Por favor, informe seu e-mail ou usuário e a senha.';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id, nome, email, senha FROM `usuarios` WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $emailBusca]);
            $usuario = $stmt->fetch();

            $senhaValida = false;
            if ($usuario) {
                // Aceita admin123, admin ou o hash gravado no banco de dados
                if ($senha === 'admin123' || $senha === 'admin' || password_verify($senha, $usuario['senha'])) {
                    $senhaValida = true;
                }
            }

            if ($usuario && $senhaValida) {
                loginUser($usuario);
                $sid = session_id();
                setFlashMessage('success', 'Bem-vindo de volta, ' . htmlspecialchars($usuario['nome']) . '!');
                header('Location: /admin/dashboard.php?sid=' . urlencode($sid));
                exit;
            } else {
                $erro = 'Credenciais incorretas. Use o e-mail "admin@agendpro.com.br" (ou "admin") e a senha "admin123".';
            }
        } catch (Exception $e) {
            error_log("Erro no login: " . $e->getMessage());
            $erro = 'Ocorreu um erro ao processar a autenticação. Tente novamente.';
        }
    }
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrativo - AGEND PRO</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex flex-col justify-between antialiased selection:bg-teal-500 selection:text-white">

    <!-- Top Navigation Bar / Brand Header -->
    <header class="w-full border-b border-slate-800 bg-slate-900/80 backdrop-blur px-4 sm:px-6 py-3.5 flex items-center justify-between">
        <a href="/public/index.php" class="flex items-center gap-3 text-white font-semibold text-lg hover:text-teal-400 transition-colors">
            <div class="w-9 h-9 rounded-lg bg-teal-600 flex items-center justify-center font-bold text-white shadow-md shadow-teal-500/20">
                AP
            </div>
            <span>AGEND <span class="text-teal-400 font-bold">PRO</span></span>
        </a>
        <div class="flex items-center gap-3">
            <a href="/login.php" target="_blank" rel="noopener noreferrer" class="text-xs text-teal-400 hover:text-teal-300 border border-teal-500/40 hover:bg-teal-500/10 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5" title="Abrir em aba cheia sem restrições de iframe">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                </svg>
                <span class="hidden sm:inline">Abrir em Nova Aba</span>
            </a>
            <a href="/public/index.php" class="text-xs sm:text-sm text-slate-400 hover:text-white transition-colors flex items-center gap-1.5">
                <span>Agendamento Público &rarr;</span>
            </a>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1 flex items-center justify-center p-4 sm:p-6">
        <div class="w-full max-w-md bg-slate-800/90 border border-slate-700/80 rounded-2xl p-6 sm:p-8 shadow-2xl shadow-black/40">
            
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-teal-500/10 border border-teal-500/30 text-teal-400 mb-3">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Painel Administrativo</h1>
                <p class="text-slate-400 text-xs sm:text-sm mt-1">Acesso do gestor aos agendamentos e cadastros</p>
            </div>

            <?php if ($flash): ?>
                <div class="mb-5 p-4 rounded-xl text-sm border <?= $flash['type'] === 'success' ? 'bg-emerald-950/60 border-emerald-500/40 text-emerald-300' : 'bg-rose-950/60 border-rose-500/40 text-rose-300' ?>">
                    <?= sanitize($flash['message']) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($erro)): ?>
                <div class="mb-5 p-4 rounded-xl text-sm bg-rose-950/60 border border-rose-500/40 text-rose-300 flex items-start gap-2.5">
                    <svg class="w-5 h-5 shrink-0 text-rose-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span><?= sanitize($erro) ?></span>
                </div>
            <?php endif; ?>

            <!-- Botão de Acesso Imediato em 1 Clique (Demonstração) -->
            <form method="POST" action="/login.php" class="mb-5">
                <input type="hidden" name="email" value="admin@agendpro.com.br">
                <input type="hidden" name="senha" value="admin123">
                <button type="submit"
                    class="w-full bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold py-3 px-4 rounded-xl shadow-lg shadow-emerald-500/20 hover:shadow-emerald-500/30 transition-all duration-150 flex items-center justify-center gap-2 cursor-pointer text-sm">
                    <svg class="w-4 h-4 text-slate-950 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span>Entrar Direto (1 Clique - Administrador)</span>
                </button>
            </form>

            <div class="relative flex py-2 items-center mb-5">
                <div class="flex-grow border-t border-slate-700/80"></div>
                <span class="flex-shrink mx-3 text-xs uppercase tracking-wider text-slate-400 font-semibold">Ou digite abaixo</span>
                <div class="flex-grow border-t border-slate-700/80"></div>
            </div>

            <!-- Formulário Manual -->
            <form method="POST" action="/login.php" class="space-y-4" autocomplete="on">
                <div>
                    <label for="email" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">E-mail ou Usuário</label>
                    <div class="relative">
                        <input type="text" id="email" name="email" value="<?= sanitize($email ?: 'admin@agendpro.com.br') ?>" required autofocus
                            placeholder="admin@agendpro.com.br ou admin"
                            class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition-all text-sm">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="senha" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Senha</label>
                        <span class="text-[11px] text-teal-400 font-mono">padrão: admin123</span>
                    </div>
                    <div class="relative">
                        <input type="password" id="senha" name="senha" value="admin123" required
                            placeholder="••••••••"
                            class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 pr-11 text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition-all text-sm">
                        <button type="button" id="btn-toggle-senha" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-teal-300 text-xs cursor-pointer" title="Mostrar/Ocultar Senha">
                            <svg id="icon-eye" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit"
                    class="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold py-2.5 px-4 rounded-xl shadow-lg shadow-teal-500/20 hover:shadow-teal-500/30 transition-all duration-150 flex items-center justify-center gap-2 cursor-pointer text-sm">
                    <span>Acessar Painel</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                    </svg>
                </button>
            </form>

            <!-- Box com Credenciais Oficiais de Acesso -->
            <div class="mt-6 pt-5 border-t border-slate-700/60 bg-slate-900/50 -mx-6 sm:-mx-8 -mb-6 sm:-mb-8 p-5 rounded-b-2xl">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-teal-400 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-teal-400 inline-block animate-pulse"></span>
                        Credenciais Oficiais
                    </span>
                    <button type="button" id="btn-preencher" class="text-xs text-slate-400 hover:text-teal-300 underline cursor-pointer">
                        Preencher campos
                    </button>
                </div>
                <div class="text-xs text-slate-300 space-y-1 bg-slate-800/80 p-3 rounded-lg border border-slate-700 font-mono">
                    <p><span class="text-slate-400 font-sans">E-mail:</span> <span class="text-teal-300">admin@agendpro.com.br</span> <span class="text-slate-500 font-sans text-[11px]">(ou "admin")</span></p>
                    <p><span class="text-slate-400 font-sans">Senha:</span> <span class="text-teal-300">admin123</span></p>
                </div>
                <p class="text-[11px] text-slate-400 mt-2 text-center">
                    Caso o navegador restrinja cookies dentro do preview, utilize o botão <a href="/login.php" target="_blank" class="text-teal-400 underline font-medium">Abrir em Nova Aba</a>.
                </p>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="w-full text-center py-3.5 text-xs text-slate-500 border-t border-slate-800/60">
        AGEND PRO &bull; Sistema de Gestão de Agendamentos &bull; PHP 8 + MySQL PDO
    </footer>

    <script>
        // Toggle de visualização da senha
        const btnToggle = document.getElementById('btn-toggle-senha');
        const inputSenha = document.getElementById('senha');
        if (btnToggle && inputSenha) {
            btnToggle.addEventListener('click', () => {
                const isPassword = inputSenha.getAttribute('type') === 'password';
                inputSenha.setAttribute('type', isPassword ? 'text' : 'password');
                btnToggle.classList.toggle('text-teal-400', isPassword);
            });
        }

        // Preenchimento rápido dos campos
        document.getElementById('btn-preencher')?.addEventListener('click', () => {
            document.getElementById('email').value = 'admin@agendpro.com.br';
            document.getElementById('senha').value = 'admin123';
        });
    </script>
</body>
</html>
