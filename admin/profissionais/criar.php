<?php
/**
 * AGEND PRO - Cadastro de Profissional
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pdo = getDBConnection();

// Busca serviços ativos para vincular no cadastro
$stmtServicos = $pdo->query("SELECT id, nome, duracao_minutos, preco FROM `servicos` ORDER BY nome ASC");
$todosServicos = $stmtServicos->fetchAll();

$erro = '';
$nome = '';
$email = '';
$telefone = '';
$especialidade = '';
$ativo = 1;
$servicosSelecionados = [];

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

            // Insere profissional
            $stmt = $pdo->prepare("INSERT INTO `profissionais` (nome, email, telefone, especialidade, ativo) VALUES (:nome, :email, :telefone, :especialidade, :ativo)");
            $stmt->execute([
                ':nome'          => $nome,
                ':email'         => $email,
                ':telefone'      => $telefone,
                ':especialidade' => $especialidade,
                ':ativo'         => $ativo,
            ]);
            $profId = (int)$pdo->lastInsertId();

            // Vincula os serviços selecionados
            if (!empty($servicosSelecionados)) {
                $stmtRel = $pdo->prepare("INSERT INTO `profissional_servicos` (profissional_id, servico_id) VALUES (:prof_id, :serv_id)");
                foreach ($servicosSelecionados as $servId) {
                    $stmtRel->execute([':prof_id' => $profId, ':serv_id' => $servId]);
                }
            }

            // Inicializa grade semanal padrão de trabalho (Segunda a Sexta: 08:00 - 18:00, Sáb: 08:00 - 12:00, Dom: Folga)
            $stmtHorario = $pdo->prepare("INSERT INTO `horarios_trabalho` (profissional_id, dia_semana, hora_inicio, hora_fim, ativo) VALUES (:prof_id, :dia, :inicio, :fim, :ativo)");
            $diasPadrao = [
                ['dia' => 0, 'inicio' => '08:00:00', 'fim' => '12:00:00', 'ativo' => 0], // Domingo folga
                ['dia' => 1, 'inicio' => '08:00:00', 'fim' => '18:00:00', 'ativo' => 1], // Segunda
                ['dia' => 2, 'inicio' => '08:00:00', 'fim' => '18:00:00', 'ativo' => 1], // Terça
                ['dia' => 3, 'inicio' => '08:00:00', 'fim' => '18:00:00', 'ativo' => 1], // Quarta
                ['dia' => 4, 'inicio' => '08:00:00', 'fim' => '18:00:00', 'ativo' => 1], // Quinta
                ['dia' => 5, 'inicio' => '08:00:00', 'fim' => '18:00:00', 'ativo' => 1], // Sexta
                ['dia' => 6, 'inicio' => '08:00:00', 'fim' => '13:00:00', 'ativo' => 1], // Sábado
            ];

            foreach ($diasPadrao as $dp) {
                $stmtHorario->execute([
                    ':prof_id' => $profId,
                    ':dia'     => $dp['dia'],
                    ':inicio'  => $dp['inicio'],
                    ':fim'     => $dp['fim'],
                    ':ativo'   => $dp['ativo']
                ]);
            }

            $pdo->commit();

            setFlashMessage('success', 'Profissional "' . htmlspecialchars($nome) . '" cadastrado com sucesso!');
            header('Location: /admin/profissionais/index.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erro ao cadastrar profissional: " . $e->getMessage());
            $erro = 'Não foi possível cadastrar o profissional. Tente novamente.';
        }
    }
}

$pageTitle = 'Novo Profissional';
$pageSubtitle = 'Cadastre um novo especialista e vincule seus serviços';

include __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Novo Profissional</h2>
            <p class="text-xs text-slate-500 mt-0.5">Preencha os dados e selecione os serviços habilitados</p>
        </div>
        <a href="/admin/profissionais/index.php" class="btn btn-secondary text-sm">
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

    <form method="POST" action="/admin/profissionais/criar.php" class="space-y-6">
        
        <!-- Dados Gerais -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
            <h3 class="font-bold text-slate-900 text-base border-b border-slate-100 pb-3">Informações Cadastrais</h3>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="nome" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Nome Completo *</label>
                    <input type="text" id="nome" name="nome" value="<?= sanitize($nome) ?>" required placeholder="Ex: Dr. Roberto Guimarães" class="text-sm">
                </div>

                <div>
                    <label for="especialidade" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Especialidade *</label>
                    <input type="text" id="especialidade" name="especialidade" value="<?= sanitize($especialidade) ?>" required placeholder="Ex: Cardiologia, Fisioterapia..." class="text-sm">
                </div>

                <div>
                    <label for="email" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">E-mail Profissional *</label>
                    <input type="email" id="email" name="email" value="<?= sanitize($email) ?>" required placeholder="roberto@clinica.com.br" class="text-sm">
                </div>

                <div>
                    <label for="telefone" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Telefone / Celular *</label>
                    <input type="tel" id="telefone" name="telefone" value="<?= sanitize($telefone) ?>" required placeholder="(11) 98765-4321" class="text-sm">
                </div>
            </div>

            <div class="pt-2">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="ativo" value="1" <?= $ativo === 1 ? 'checked' : '' ?>>
                    <div>
                        <span class="text-sm font-semibold text-slate-800">Profissional Ativo</span>
                        <p class="text-xs text-slate-500">Permite que o profissional receba novos agendamentos na plataforma pública.</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- Relação N:N Serviços -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h3 class="font-bold text-slate-900 text-base">Serviços Realizados</h3>
                    <p class="text-xs text-slate-500">Selecione quais serviços este profissional está habilitado a realizar</p>
                </div>
                <span class="text-xs text-slate-400">Relação N:N</span>
            </div>

            <?php if (empty($todosServicos)): ?>
                <p class="text-sm text-slate-400 py-2">Nenhum serviço cadastrado ainda. Cadastre serviços primeiro.</p>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?php foreach ($todosServicos as $s): ?>
                        <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 hover:border-teal-400 hover:bg-teal-50/30 transition-colors cursor-pointer">
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
            <button type="submit" class="btn btn-primary">Salvar Profissional</button>
        </div>

    </form>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
