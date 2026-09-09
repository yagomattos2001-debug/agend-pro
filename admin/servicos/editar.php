<?php
/**
 * AGEND PRO - Edição de Serviço
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pdo = getDBConnection();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlashMessage('error', 'Serviço não especificado.');
    header('Location: /admin/servicos/index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM `servicos` WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$servico = $stmt->fetch();

if (!$servico) {
    setFlashMessage('error', 'Serviço não encontrado.');
    header('Location: /admin/servicos/index.php');
    exit;
}

// Profissionais disponíveis
$stmtProf = $pdo->query("SELECT id, nome, especialidade FROM `profissionais` ORDER BY nome ASC");
$todosProfissionais = $stmtProf->fetchAll();

// Profissionais atualmente vinculados a este serviço
$stmtVinculados = $pdo->prepare("SELECT profissional_id FROM `profissional_servicos` WHERE servico_id = :id");
$stmtVinculados->execute([':id' => $id]);
$profsVinculados = $stmtVinculados->fetchAll(PDO::FETCH_COLUMN);

$erro = '';
$nome = $servico['nome'];
$descricao = $servico['descricao'];
$duracao = (int)$servico['duracao_minutos'];
$preco = number_format((float)$servico['preco'], 2, ',', '');
$ativo = (int)$servico['ativo'];
$profsSelecionados = $profsVinculados;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $duracao = (int)($_POST['duracao_minutos'] ?? 0);
    $precoRaw = trim($_POST['preco'] ?? '');
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $profsSelecionados = isset($_POST['profissionais']) && is_array($_POST['profissionais']) ? array_map('intval', $_POST['profissionais']) : [];

    $precoClean = str_replace(['R$', ' ', '.'], '', $precoRaw);
    $precoClean = str_replace(',', '.', $precoClean);
    $precoFloat = (float)$precoClean;

    if (empty($nome)) {
        $erro = 'O nome do serviço é obrigatório.';
    } elseif ($duracao < 5 || $duracao > 480) {
        $erro = 'A duração deve estar entre 5 e 480 minutos.';
    } elseif ($precoFloat < 0) {
        $erro = 'O valor do serviço não pode ser negativo.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmtUpdate = $pdo->prepare("UPDATE `servicos` 
                                         SET nome = :nome, descricao = :descricao, duracao_minutos = :duracao, preco = :preco, ativo = :ativo, updated_at = NOW() 
                                         WHERE id = :id");
            $stmtUpdate->execute([
                ':nome'      => $nome,
                ':descricao' => $descricao,
                ':duracao'   => $duracao,
                ':preco'     => $precoFloat,
                ':ativo'     => $ativo,
                ':id'        => $id,
            ]);

            // Atualiza vínculos N:N
            $stmtDeleteRel = $pdo->prepare("DELETE FROM `profissional_servicos` WHERE servico_id = :id");
            $stmtDeleteRel->execute([':id' => $id]);

            if (!empty($profsSelecionados)) {
                $stmtInsertRel = $pdo->prepare("INSERT INTO `profissional_servicos` (profissional_id, servico_id) VALUES (:prof_id, :serv_id)");
                foreach ($profsSelecionados as $profId) {
                    $stmtInsertRel->execute([':prof_id' => $profId, ':serv_id' => $id]);
                }
            }

            $pdo->commit();

            setFlashMessage('success', 'Serviço "' . htmlspecialchars($nome) . '" atualizado com sucesso!');
            header('Location: /admin/servicos/index.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erro ao atualizar serviço: " . $e->getMessage());
            $erro = 'Erro ao atualizar dados do serviço.';
        }
    }
}

$pageTitle = 'Editar Serviço';
$pageSubtitle = 'Atualize informações de duração, precificação e equipe';

include __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Editar: <?= sanitize($servico['nome']) ?></h2>
            <p class="text-xs text-slate-500 mt-0.5">Ajuste duração, precificação e profissionais vinculados</p>
        </div>
        <a href="/admin/servicos/index.php" class="btn btn-secondary text-sm">
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

    <form method="POST" action="/admin/servicos/editar.php?id=<?= $id ?>" class="space-y-6">
        
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
            <h3 class="font-bold text-slate-900 text-base border-b border-slate-100 pb-3">Parâmetros do Atendimento</h3>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label for="nome" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Nome do Serviço *</label>
                    <input type="text" id="nome" name="nome" value="<?= sanitize($nome) ?>" required class="text-sm">
                </div>

                <div class="sm:col-span-2">
                    <label for="descricao" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Descrição Detalhada</label>
                    <textarea id="descricao" name="descricao" rows="3" class="text-sm"><?= sanitize($descricao) ?></textarea>
                </div>

                <div>
                    <label for="duracao_minutos" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Duração (Minutos) *</label>
                    <select id="duracao_minutos" name="duracao_minutos" class="text-sm" required>
                        <option value="15" <?= $duracao === 15 ? 'selected' : '' ?>>15 minutos</option>
                        <option value="30" <?= $duracao === 30 ? 'selected' : '' ?>>30 minutos</option>
                        <option value="45" <?= $duracao === 45 ? 'selected' : '' ?>>45 minutos</option>
                        <option value="60" <?= $duracao === 60 ? 'selected' : '' ?>>60 minutos (1 hora)</option>
                        <option value="90" <?= $duracao === 90 ? 'selected' : '' ?>>90 minutos (1h 30min)</option>
                        <option value="120" <?= $duracao === 120 ? 'selected' : '' ?>>120 minutos (2 horas)</option>
                    </select>
                </div>

                <div>
                    <label for="preco" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Preço (R$) *</label>
                    <input type="text" id="preco" name="preco" value="<?= sanitize($preco) ?>" required class="text-sm">
                </div>
            </div>

            <div class="pt-2">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="ativo" value="1" <?= $ativo === 1 ? 'checked' : '' ?>>
                    <div>
                        <span class="text-sm font-semibold text-slate-800">Serviço Ativo</span>
                        <p class="text-xs text-slate-500">Se desmarcado, não aparecerá para agendamento pelos clientes.</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- Profissionais Habilitados -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
            <div class="border-b border-slate-100 pb-3">
                <h3 class="font-bold text-slate-900 text-base">Profissionais Vinculados</h3>
                <p class="text-xs text-slate-500">Selecione quais profissionais estão habilitados a prestar este serviço</p>
            </div>

            <?php if (empty($todosProfissionais)): ?>
                <p class="text-sm text-slate-400 py-2">Nenhum profissional cadastrado.</p>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?php foreach ($todosProfissionais as $p): ?>
                        <label class="flex items-start gap-3 p-3 rounded-xl border <?= in_array((int)$p['id'], $profsSelecionados, true) ? 'border-teal-500 bg-teal-50/20' : 'border-slate-200' ?> hover:border-teal-400 hover:bg-teal-50/30 transition-colors cursor-pointer">
                            <input type="checkbox" name="profissionais[]" value="<?= $p['id'] ?>" <?= in_array((int)$p['id'], $profsSelecionados, true) ? 'checked' : '' ?> class="mt-0.5">
                            <div>
                                <span class="text-sm font-semibold text-slate-900"><?= sanitize($p['nome']) ?></span>
                                <div class="text-xs text-slate-500 mt-0.5"><?= sanitize($p['especialidade']) ?></div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Submit -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="/admin/servicos/index.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        </div>

    </form>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
