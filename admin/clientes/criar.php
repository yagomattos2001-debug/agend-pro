<?php
/**
 * AGEND PRO - Cadastro de Cliente
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pdo = getDBConnection();

$erro = '';
$nome = '';
$email = '';
$telefone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');

    if (empty($nome) || empty($telefone)) {
        $erro = 'Nome e Telefone são campos obrigatórios.';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Formato de e-mail inválido.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO `clientes` (nome, email, telefone) VALUES (:nome, :email, :telefone)");
            $stmt->execute([
                ':nome'     => $nome,
                ':email'    => $email ?: null,
                ':telefone' => $telefone,
            ]);

            setFlashMessage('success', 'Cliente "' . htmlspecialchars($nome) . '" cadastrado com sucesso!');
            header('Location: /admin/clientes/index.php');
            exit;
        } catch (Exception $e) {
            error_log("Erro ao cadastrar cliente: " . $e->getMessage());
            $erro = 'Erro ao salvar cliente. Verifique se o telefone ou e-mail já não estão cadastrados.';
        }
    }
}

$pageTitle = 'Novo Cliente';
$pageSubtitle = 'Cadastro manual de cliente na base de atendimentos';

include __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Cadastrar Cliente</h2>
            <p class="text-xs text-slate-500 mt-0.5">Preencha os dados de identificação e contato</p>
        </div>
        <a href="/admin/clientes/index.php" class="btn btn-secondary text-sm">
            &larr; Voltar
        </a>
    </div>

    <?php if (!empty($erro)): ?>
        <div class="p-4 rounded-xl text-sm bg-rose-50 border border-rose-200 text-rose-800 flex items-center gap-2.5">
            <svg class="w-5 h-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span><?= sanitize($erro) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="/admin/clientes/criar.php" class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-5">
        
        <div>
            <label for="nome" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Nome Completo *</label>
            <input type="text" id="nome" name="nome" value="<?= sanitize($nome) ?>" required placeholder="Ex: Juliana Mendes" class="text-sm">
        </div>

        <div>
            <label for="telefone" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Telefone / WhatsApp *</label>
            <input type="tel" id="telefone" name="telefone" value="<?= sanitize($telefone) ?>" required placeholder="(11) 98765-4321" class="text-sm">
        </div>

        <div>
            <label for="email" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">E-mail (Opcional)</label>
            <input type="email" id="email" name="email" value="<?= sanitize($email) ?>" placeholder="juliana@exemplo.com.br" class="text-sm">
        </div>

        <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
            <a href="/admin/clientes/index.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Salvar Cliente</button>
        </div>

    </form>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
