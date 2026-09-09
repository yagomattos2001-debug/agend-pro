<?php
/**
 * AGEND PRO - Edição de Profissional e Serviços Habilitados
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pdo = getDBConnection();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlashMessage('error', 'Profissional não especificado.');
    header('Location: /admin/profissionais/index.php');
    exit;
}

// Busca o profissional
$stmt = $pdo->prepare("SELECT * FROM `profissionais` WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$profissional = $stmt->fetch();

if (!$profissional) {
    setFlashMessage('error', 'Profissional não encontrado.');
    header('Location: /admin/profissionais/index.php');
    exit;
}

// Busca todos os serviços
$stmtTodosServicos = $pdo->query("SELECT id, nome, duracao_minutos, preco FROM `servicos` ORDER BY nome ASC");
$todosServicos = $stmtTodosServicos->fetchAll();

// Busca os serviços atualmente vinculados a este profissional
$stmtVinculados = $pdo->prepare("SELECT servico_id FROM `profissional_servicos` WHERE profissional_id = :id");
$stmtVinculados->execute([':id' => $id]);
$servicosVinculados = $stmtVinculados->fetchAll(PDO::FETCH_COLUMN);

$erro = '';
$nome = $profissional['nome'];
$email = $profissional['email'];
$telefone = $profissional['telefone'];
$especialidade = $profissional['especialidade'];
$ativo = (int)$profissional['ativo'];
$servicosSelecionados = $servicosVinculados;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $especialidade = trim($_POST['especialidade'] ?? '');
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $servicosSelecionados = isset($_POST['servicos']) && is_array($_POST['servicos']) ? array_map('intval', $_POST['servicos']) : [];

    if (empty($nome) || empty($email) || empty($telefone) || empty($especialidade)) {
        $erro = 'Todos os campos de identificação são obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Formato de e-mail inválido.';
    } else {
        try {
            $pdo->beginTransaction();

            // Atualiza dados do profissional
            $stmtUpdate = $pdo->prepare("UPDATE `profissionais` 
                                         SET nome = :nome, email = :email, telefone = :telefone, especialidade = :especialidade, ativo = :ativo, updated_at = NOW() 
                                         WHERE id = :id");
            $stmtUpdate->execute([
                ':nome'          => $nome,
                ':email'         => $email,
                ':telefone'      => $telefone,
                ':especialidade' => $especialidade,
                ':ativo'         => $ativo,
                ':id'            => $id,
            ]);

            // Atualiza relação N:N (apaga vínculos antigos e recria os selecionados)
            $stmtDeleteRel = $pdo->prepare("DELETE FROM `profissional_servicos` WHERE profissional_id = :id");
            $stmtDeleteRel->execute([':id' => $id]);

            if (!empty($servicosSelecionados)) {
                $stmtInsertRel = $pdo->prepare("INSERT INTO `profissional_servicos` (profissional_id, servico_id) VALUES (:prof_id, :serv_id)");
                foreach ($servicosSelecionados as $servId) {
                    $stmtInsertRel->execute([':prof_id' => $id, ':serv_id' => $servId]);
                }
            }

            $pdo->commit();

            setFlashMessage('success', 'Profissional "' . htmlspecialchars($nome) . '" atualizado com sucesso!');
            header('Location: /admin/profissionais/index.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erro ao atualizar profissional: " . $e->getMessage());
            $erro = 'Ocorreu um erro ao atualizar os dados do profissional.';
        }
    }
}

$pageTitle = 'Editar Profissional';
$pageSubtitle = 'Atualize dados e serviços habilitados';

include __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Editar: <?= sanitize($profissional['nome']) ?></h2>
            <p class="text-xs text-slate-500 mt-0.5">Gerencie os dados cadastrais e o catálogo de serviços atendidos</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/admin/horarios/configurar.php?profissional_id=<?= $id ?>" class="btn btn-secondary text-sm">
                Configurar Horários
            </a>
            <a href="/admin/profissionais/index.php" class="btn btn-secondary text-sm">
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

    <form method="POST" action="/admin/profissionais/editar.php?id=<?= $id ?>" class="space-y-6">
        
        <!-- Dados Gerais -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
            <h3 class="font-bold text-slate-900 text-base border-b border-slate-100 pb-3">Informações Cadastrais</h3>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="nome" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Nome Completo *</label>
                    <input type="text" id="nome" name="nome" value="<?= sanitize($nome) ?>" required class="text-sm">
                </div>

                <div>
                    <label for="especialidade" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Especialidade *</label>
                    <input type="text" id="especialidade" name="especialidade" value="<?= sanitize($especialidade) ?>" required class="text-sm">
                </div>

                <div>
                    <label for="email" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">E-mail *</label>
                    <input type="email" id="email" name="email" value="<?= sanitize($email) ?>" required class="text-sm">
                </div>

                <div>
                    <label for="telefone" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Telefone / Celular *</label>
                    <input type="tel" id="telefone" name="telefone" value="<?= sanitize($telefone) ?>" required class="text-sm">
                </div>
            </div>

            <div class="pt-2">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="ativo" value="1" <?= $ativo === 1 ? 'checked' : '' ?>>
                    <div>
                        <span class="text-sm font-semibold text-slate-800">Profissional Ativo</span>
                        <p class="text-xs text-slate-500">Se desmarcado, não aparecerá para novas reservas pelo cliente.</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- Relação N:N Serviços -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h3 class="font-bold text-slate-900 text-base">Serviços Habilitados</h3>
                    <p class="text-xs text-slate-500">Selecione quais serviços este profissional está habilitado a prestar</p>
                </div>
                <span class="text-xs text-slate-400">Relação N:N</span>
            </div>

            <?php if (empty($todosServicos)): ?>
                <p class="text-sm text-slate-400 py-2">Nenhum serviço cadastrado.</p>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?php foreach ($todosServicos as $s): ?>
                        <label class="flex items-start gap-3 p-3 rounded-xl border <?= in_array((int)$s['id'], $servicosSelecionados, true) ? 'border-teal-500 bg-teal-50/20' : 'border-slate-200' ?> hover:border-teal-400 hover:bg-teal-50/30 transition-colors cursor-pointer">
                            <input type="checkbox" name="servicos[]" value="<?= $s['id'] ?>" <?= in_array((int)$s['id'], $servicosSelecionados, true) ? 'checked' : '' ?> class="mt-0.5">
                            <div>
                                <span class="text-sm font-semibold text-slate-900"><?= sanitize($s['nome']) ?></span>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    <?= $s['duracao_minutos'] ?> min &bull; <?= formatMoney((float)$s['preco']) ?>
                                </div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Botões de Ação -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="/admin/profissionais/index.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        </div>

    </form>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
