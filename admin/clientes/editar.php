<?php
/**
 * AGEND PRO - Edição de Cliente
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pdo = getDBConnection();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlashMessage('error', 'Cliente não especificado.');
    header('Location: /admin/clientes/index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM `clientes` WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$cliente = $stmt->fetch();

if (!$cliente) {
    setFlashMessage('error', 'Cliente não encontrado.');
    header('Location: /admin/clientes/index.php');
    exit;
}

$erro = '';
$nome = $cliente['nome'];
$email = $cliente['email'] ?? '';
$telefone = $cliente['telefone'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');

    if (empty($nome) || empty($telefone)) {
        $erro = 'Nome e Telefone são obrigatórios.';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Formato de e-mail inválido.';
    } else {
        try {
            $stmtUpdate = $pdo->prepare("UPDATE `clientes` SET nome = :nome, email = :email, telefone = :telefone, updated_at = NOW() WHERE id = :id");
            $stmtUpdate->execute([
                ':nome'     => $nome,
                ':email'    => $email ?: null,
                ':telefone' => $telefone,
                ':id'       => $id,
            ]);

            setFlashMessage('success', 'Cliente "' . htmlspecialchars($nome) . '" atualizado com sucesso!');
            header('Location: /admin/clientes/index.php');
            exit;
        } catch (Exception $e) {
            error_log("Erro ao atualizar cliente: " . $e->getMessage());
            $erro = 'Erro ao atualizar dados do cliente.';
        }
    }
}

$pageTitle = 'Editar Cliente';
$pageSubtitle = 'Atualize os dados de ' . $cliente['nome'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Editar Cliente</h2>
            <p class="text-xs text-slate-500 mt-0.5">Atualize os dados de identificação e contato</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/admin/clientes/visualizar.php?id=<?= $id ?>" class="btn btn-secondary text-sm">
                Histórico
            </a>
            <a href="/admin/clientes/index.php" class="btn btn-secondary text-sm">
                &larr; Voltar
            </a>
        </div>
    </div>

    <?php if (!empty($erro)): ?>
        <div class="p-4 rounded-xl text-sm bg-rose-50 border border-rose-200 text-rose-800 flex items-center gap-2.5">
            <svg class="w-5 h-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span><?= sanitize($erro) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="/admin/clientes/editar.php?id=<?= $id ?>" class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-5">
        
        <div>
            <label for="nome" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Nome Completo *</label>
            <input type="text" id="nome" name="nome" value="<?= sanitize($nome) ?>" required class="text-sm">
        </div>

        <div>
            <label for="telefone" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Telefone / WhatsApp *</label>
            <input type="tel" id="telefone" name="telefone" value="<?= sanitize($telefone) ?>" required class="text-sm">
        </div>

        <div>
            <label for="email" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">E-mail</label>
            <input type="email" id="email" name="email" value="<?= sanitize($email) ?>" class="text-sm">
        </div>

        <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
            <a href="/admin/clientes/index.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        </div>

    </form>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
