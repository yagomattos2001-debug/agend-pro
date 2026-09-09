<?php
/**
 * AGEND PRO - Edição de Agendamento
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pdo = getDBConnection();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlashMessage('error', 'Agendamento não especificado.');
    header('Location: /admin/agendamentos/index.php');
    exit;
}

// Busca agendamento existente
$stmt = $pdo->prepare("SELECT a.*, c.nome AS cliente_nome, c.telefone AS cliente_telefone
                       FROM `agendamentos` a
                       INNER JOIN `clientes` c ON a.cliente_id = c.id
                       WHERE a.id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$ag = $stmt->fetch();

if (!$ag) {
    setFlashMessage('error', 'Agendamento não encontrado.');
    header('Location: /admin/agendamentos/index.php');
    exit;
}

// Busca serviços e profissionais
$servicos = $pdo->query("SELECT id, nome, duracao_minutos, preco FROM `servicos` ORDER BY nome ASC")->fetchAll();
$profissionais = $pdo->query("SELECT id, nome, especialidade FROM `profissionais` ORDER BY nome ASC")->fetchAll();

$erro = '';
$servicoId = (int)$ag['servico_id'];
$profissionalId = (int)$ag['profissional_id'];
$data = $ag['data'];
$horaInicio = substr($ag['hora_inicio'], 0, 5);
$status = $ag['status'];
$observacao = $ag['observacao'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $servicoId      = (int)($_POST['servico_id'] ?? 0);
    $profissionalId = (int)($_POST['profissional_id'] ?? 0);
    $data           = trim($_POST['data'] ?? '');
    $horaInicio     = trim($_POST['hora_inicio'] ?? '');
    $status         = trim($_POST['status'] ?? 'confirmado');
    $observacao     = trim($_POST['observacao'] ?? '');

    if ($servicoId <= 0 || $profissionalId <= 0 || empty($data) || empty($horaInicio)) {
        $erro = 'Todos os campos de serviço, profissional, data e horário são obrigatórios.';
    } else {
        try {
            $pdo->beginTransaction();

            // Busca duração do serviço
            $stmtS = $pdo->prepare("SELECT duracao_minutos FROM `servicos` WHERE id = :id");
            $stmtS->execute([':id' => $servicoId]);
            $serv = $stmtS->fetch();

            if (!$serv) {
                throw new Exception('Serviço inválido.');
            }

            $duracao = (int)$serv['duracao_minutos'];
            $horaInicioFmt = strlen($horaInicio) === 5 ? $horaInicio . ':00' : $horaInicio;
            $horaFimFmt = calculateEndTime($horaInicioFmt, $duracao);

            // Validação de conflito se status for pendente ou confirmado (excluindo este próprio agendamento)
            if (in_array($status, ['pendente', 'confirmado'], true)) {
                $hasConflict = checkAppointmentConflict($pdo, $profissionalId, $data, $horaInicioFmt, $horaFimFmt, $id);
                if ($hasConflict) {
                    throw new Exception('Conflito de horário! Já existe outro agendamento ativo para este profissional neste intervalo.');
                }
            }

            $stmtUpdate = $pdo->prepare("UPDATE `agendamentos`
                                         SET servico_id = :servico_id,
                                             profissional_id = :prof_id,
                                             data = :data,
                                             hora_inicio = :hora_inicio,
                                             hora_fim = :hora_fim,
                                             status = :status,
                                             observacao = :observacao,
                                             updated_at = NOW()
                                         WHERE id = :id");
            $stmtUpdate->execute([
                ':servico_id' => $servicoId,
                ':prof_id'    => $profissionalId,
                ':data'       => $data,
                ':hora_inicio'=> $horaInicioFmt,
                ':hora_fim'   => $horaFimFmt,
                ':status'     => $status,
                ':observacao' => $observacao ?: null,
                ':id'         => $id,
            ]);

            $pdo->commit();

            setFlashMessage('success', 'Agendamento #' . $id . ' atualizado com sucesso!');
            header('Location: /admin/agendamentos/visualizar.php?id=' . $id);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = $e->getMessage();
        }
    }
}

$pageTitle = 'Editar Agendamento #' . $id;
$pageSubtitle = 'Ajuste horários, profissional responsável ou status do atendimento';

include __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Editar Agendamento #<?= $id ?></h2>
            <p class="text-xs text-slate-500 mt-0.5">Cliente: <span class="font-semibold text-slate-800"><?= sanitize($ag['cliente_nome']) ?></span> (<?= sanitize($ag['cliente_telefone']) ?>)</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/admin/agendamentos/visualizar.php?id=<?= $id ?>" class="btn btn-secondary text-sm">
                Visualizar
            </a>
            <a href="/admin/agendamentos/index.php" class="btn btn-secondary text-sm">
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

    <form method="POST" action="/admin/agendamentos/editar.php?id=<?= $id ?>" class="space-y-6">
        
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
            <h3 class="font-bold text-slate-900 text-base border-b border-slate-100 pb-3">Serviço & Profissional</h3>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="servico_id" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Serviço *</label>
                    <select id="servico_id" name="servico_id" class="text-sm" required>
                        <?php foreach ($servicos as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $servicoId === (int)$s['id'] ? 'selected' : '' ?>>
                                <?= sanitize($s['nome']) ?> (<?= $s['duracao_minutos'] ?> min - <?= formatMoney((float)$s['preco']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="profissional_id" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Profissional *</label>
                    <select id="profissional_id" name="profissional_id" class="text-sm" required>
                        <?php foreach ($profissionais as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $profissionalId === (int)$p['id'] ? 'selected' : '' ?>>
                                <?= sanitize($p['nome']) ?> (<?= sanitize($p['especialidade']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
            <h3 class="font-bold text-slate-900 text-base border-b border-slate-100 pb-3">Data, Horário & Situação</h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="data" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Data *</label>
                    <input type="date" id="data" name="data" value="<?= sanitize($data) ?>" required class="text-sm">
                </div>

                <div>
                    <label for="hora_inicio" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Horário de Início *</label>
                    <input type="time" id="hora_inicio" name="hora_inicio" value="<?= sanitize($horaInicio) ?>" required class="text-sm font-mono">
                    <span class="text-[11px] text-slate-400 mt-1 block">O término será calculado pela duração do serviço.</span>
                </div>

                <div>
                    <label for="status" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Status *</label>
                    <select id="status" name="status" class="text-sm" required>
                        <option value="pendente" <?= $status === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                        <option value="confirmado" <?= $status === 'confirmado' ? 'selected' : '' ?>>Confirmado</option>
                        <option value="concluido" <?= $status === 'concluido' ? 'selected' : '' ?>>Concluído</option>
                        <option value="cancelado" <?= $status === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>

                <div class="sm:col-span-3">
                    <label for="observacao" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Observações / Anotações</label>
                    <textarea id="observacao" name="observacao" rows="3" class="text-sm"><?= sanitize($observacao) ?></textarea>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="/admin/agendamentos/visualizar.php?id=<?= $id ?>" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        </div>

    </form>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
