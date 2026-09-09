<?php
/**
 * AGEND PRO - Cadastro de Novo Serviço
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pdo = getDBConnection();

// Busca profissionais para vinculação opcional imediata
$stmtProf = $pdo->query("SELECT id, nome, especialidade FROM `profissionais` WHERE ativo = 1 ORDER BY nome ASC");
$profissionaisAtivos = $stmtProf->fetchAll();

$erro = '';
$nome = '';
$descricao = '';
$duracao = 60;
$preco = '150,00';
$ativo = 1;
$profsSelecionados = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $duracao = (int)($_POST['duracao_minutos'] ?? 0);
    $precoRaw = trim($_POST['preco'] ?? '');
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $profsSelecionados = isset($_POST['profissionais']) && is_array($_POST['profissionais']) ? array_map('intval', $_POST['profissionais']) : [];

    // Converte formato de preço de R$ / vírgula para decimal float
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

            $stmt = $pdo->prepare("INSERT INTO `servicos` (nome, descricao, duracao_minutos, preco, ativo) VALUES (:nome, :descricao, :duracao, :preco, :ativo)");
            $stmt->execute([
                ':nome'      => $nome,
                ':descricao' => $descricao,
                ':duracao'   => $duracao,
                ':preco'     => $precoFloat,
                ':ativo'     => $ativo,
            ]);
            $servicoId = (int)$pdo->lastInsertId();

            if (!empty($profsSelecionados)) {
                $stmtRel = $pdo->prepare("INSERT INTO `profissional_servicos` (profissional_id, servico_id) VALUES (:prof_id, :serv_id)");
                foreach ($profsSelecionados as $profId) {
                    $stmtRel->execute([':prof_id' => $profId, ':serv_id' => $servicoId]);
                }
            }

            $pdo->commit();

            setFlashMessage('success', 'Serviço "' . htmlspecialchars($nome) . '" cadastrado com sucesso!');
            header('Location: /admin/servicos/index.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erro ao cadastrar serviço: " . $e->getMessage());
            $erro = 'Erro ao salvar o serviço no banco de dados.';
        }
    }
}

$pageTitle = 'Novo Serviço';
$pageSubtitle = 'Defina duração, valor e profissionais capacitados';

include __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Novo Serviço</h2>
            <p class="text-xs text-slate-500 mt-0.5">Cadastre procedimentos disponíveis para atendimento</p>
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

    <form method="POST" action="/admin/servicos/criar.php" class="space-y-6">
        
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
            <h3 class="font-bold text-slate-900 text-base border-b border-slate-100 pb-3">Parâmetros do Atendimento</h3>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label for="nome" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Nome do Serviço *</label>
                    <input type="text" id="nome" name="nome" value="<?= sanitize($nome) ?>" required placeholder="Ex: Avaliação Inicial, Sessão de Fisioterapia, Limpeza de Pele..." class="text-sm">
                </div>

                <div class="sm:col-span-2">
                    <label for="descricao" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Descrição Detalhada</label>
                    <textarea id="descricao" name="descricao" rows="3" placeholder="Detalhes do que está incluso no atendimento, orientações ao cliente, etc." class="text-sm"><?= sanitize($descricao) ?></textarea>
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
                    <input type="text" id="preco" name="preco" value="<?= sanitize($preco) ?>" required placeholder="150,00" class="text-sm">
                </div>
            </div>

            <div class="pt-2">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="ativo" value="1" <?= $ativo === 1 ? 'checked' : '' ?>>
                    <div>
                        <span class="text-sm font-semibold text-slate-800">Serviço Ativo</span>
                        <p class="text-xs text-slate-500">Aparecerá para seleção de agendamento pelos clientes.</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- Profissionais Habilitados -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
            <div class="border-b border-slate-100 pb-3">
                <h3 class="font-bold text-slate-900 text-base">Vincular Profissionais</h3>
                <p class="text-xs text-slate-500">Selecione quais profissionais prestam este serviço</p>
            </div>

            <?php if (empty($profissionaisAtivos)): ?>
                <p class="text-sm text-slate-400 py-2">Nenhum profissional ativo cadastrado.</p>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?php foreach ($profissionaisAtivos as $p): ?>
                        <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 hover:border-teal-400 hover:bg-teal-50/30 transition-colors cursor-pointer">
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
            <button type="submit" class="btn btn-primary">Salvar Serviço</button>
        </div>

    </form>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
