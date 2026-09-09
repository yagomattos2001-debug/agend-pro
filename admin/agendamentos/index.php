<?php
/**
 * AGEND PRO - Gestão Completa de Agendamentos (Listagem, Filtros e Ações Rápidas de Status)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pdo = getDBConnection();

// Ação Rápida de Atualização de Status via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_status') {
    $agId = (int)($_POST['id'] ?? 0);
    $novoStatus = trim($_POST['status'] ?? '');
    $validStatuses = ['pendente', 'confirmado', 'concluido', 'cancelado'];

    if ($agId > 0 && in_array($novoStatus, $validStatuses, true)) {
        // Se estiver confirmando ou reativando para pendente/confirmado, verifica se há conflito
        if (in_array($novoStatus, ['pendente', 'confirmado'], true)) {
            $stmtAg = $pdo->prepare("SELECT profissional_id, data, hora_inicio, hora_fim FROM `agendamentos` WHERE id = :id");
            $stmtAg->execute([':id' => $agId]);
            $ag = $stmtAg->fetch();

            if ($ag) {
                $hasConflict = checkAppointmentConflict($pdo, (int)$ag['profissional_id'], $ag['data'], $ag['hora_inicio'], $ag['hora_fim'], $agId);
                if ($hasConflict) {
                    setFlashMessage('error', 'Não foi possível alterar status para ' . $novoStatus . ': existe outro agendamento ativo no mesmo horário para este profissional.');
                    header('Location: ' . $_SERVER['REQUEST_URI']);
                    exit;
                }
            }
        }

        $stmtUp = $pdo->prepare("UPDATE `agendamentos` SET status = :status, updated_at = NOW() WHERE id = :id");
        $stmtUp->execute([':status' => $novoStatus, ':id' => $agId]);

        setFlashMessage('success', 'Status do agendamento #' . $agId . ' atualizado para "' . ucfirst($novoStatus) . '".');
    }
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/agendamentos/index.php'));
    exit;
}

// Filtros da listagem
$filtroData         = trim($_GET['data'] ?? '');
$filtroProfissional = (int)($_GET['profissional_id'] ?? 0);
$filtroStatus       = trim($_GET['status'] ?? '');
$filtroBusca        = trim($_GET['busca'] ?? '');

$sql = "SELECT 
            a.id,
            a.data,
            a.hora_inicio,
            a.hora_fim,
            a.status,
            a.observacao,
            a.created_at,
            c.id AS cliente_id,
            c.nome AS cliente_nome,
            c.telefone AS cliente_telefone,
            p.id AS profissional_id,
            p.nome AS profissional_nome,
            s.id AS servico_id,
            s.nome AS servico_nome,
            s.duracao_minutos,
            s.preco
        FROM `agendamentos` a
        INNER JOIN `clientes` c ON a.cliente_id = c.id
        INNER JOIN `profissionais` p ON a.profissional_id = p.id
        INNER JOIN `servicos` s ON a.servico_id = s.id
        WHERE 1=1";

$params = [];

if (!empty($filtroData)) {
    $sql .= " AND a.data = :data";
    $params[':data'] = $filtroData;
}

if ($filtroProfissional > 0) {
    $sql .= " AND a.profissional_id = :prof_id";
    $params[':prof_id'] = $filtroProfissional;
}

if (!empty($filtroStatus)) {
    $sql .= " AND a.status = :status";
    $params[':status'] = $filtroStatus;
}

if (!empty($filtroBusca)) {
    $sql .= " AND (c.nome LIKE :busca OR c.telefone LIKE :busca OR s.nome LIKE :busca)";
    $params[':busca'] = '%' . $filtroBusca . '%';
}

$sql .= " ORDER BY a.data DESC, a.hora_inicio DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$agendamentos = $stmt->fetchAll();

// Dados para os selects dos filtros
$profissionais = $pdo->query("SELECT id, nome FROM `profissionais` ORDER BY nome ASC")->fetchAll();

$pageTitle = 'Agendamentos';
$pageSubtitle = 'Gerenciamento operacional de horários, confirmações e atendimentos';

include __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Grade de Agendamentos</h2>
            <p class="text-xs text-slate-500 mt-0.5">Total de <?= count($agendamentos) ?> agendamento(s) listado(s)</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/admin/agendamentos/criar.php" class="btn btn-primary shadow-sm self-start sm:self-auto">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>Novo Agendamento</span>
            </a>
        </div>
    </div>

    <!-- Filtros de Pesquisa -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
        <form method="GET" action="/admin/agendamentos/index.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Data</label>
                <input type="date" name="data" value="<?= sanitize($filtroData) ?>" class="text-sm">
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Profissional</label>
                <select name="profissional_id" class="text-sm">
                    <option value="">Todos os profissionais</option>
                    <?php foreach ($profissionais as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $filtroProfissional === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= sanitize($p['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Status</label>
                <select name="status" class="text-sm">
                    <option value="">Todos os status</option>
                    <option value="pendente" <?= $filtroStatus === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="confirmado" <?= $filtroStatus === 'confirmado' ? 'selected' : '' ?>>Confirmado</option>
                    <option value="concluido" <?= $filtroStatus === 'concluido' ? 'selected' : '' ?>>Concluído</option>
                    <option value="cancelado" <?= $filtroStatus === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Buscar</label>
                <input type="text" name="busca" value="<?= sanitize($filtroBusca) ?>" placeholder="Cliente, telefone..." class="text-sm">
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="btn btn-secondary text-sm flex-1">
                    Filtrar
                </button>
                <?php if (!empty($filtroData) || $filtroProfissional > 0 || !empty($filtroStatus) || !empty($filtroBusca)): ?>
                    <a href="/admin/agendamentos/index.php" class="btn btn-secondary text-sm text-slate-500">Limpar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabela de Agendamentos -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Data & Horário</th>
                        <th>Cliente</th>
                        <th>Serviço</th>
                        <th>Profissional</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($agendamentos)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-10 text-slate-400">
                                Nenhum agendamento encontrado com os filtros selecionados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($agendamentos as $ag): ?>
                            <tr>
                                <td>
                                    <div class="font-bold text-slate-900">
                                        <?= formatDate($ag['data']) ?>
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        <?= formatTime($ag['hora_inicio']) ?> às <?= formatTime($ag['hora_fim']) ?>
                                    </div>
                                </td>
                                <td>
                                    <a href="/admin/clientes/visualizar.php?id=<?= $ag['cliente_id'] ?>" class="font-semibold text-slate-900 hover:text-teal-600 transition-colors">
                                        <?= sanitize($ag['cliente_nome']) ?>
                                    </a>
                                    <div class="text-xs text-slate-400"><?= sanitize($ag['cliente_telefone']) ?></div>
                                </td>
                                <td>
                                    <div class="font-medium text-slate-800"><?= sanitize($ag['servico_nome']) ?></div>
                                    <div class="text-xs text-slate-500"><?= $ag['duracao_minutos'] ?> min</div>
                                </td>
                                <td>
                                    <div class="text-sm text-slate-700 font-medium"><?= sanitize($ag['profissional_nome']) ?></div>
                                </td>
                                <td>
                                    <span class="font-bold text-slate-900 text-sm">
                                        <?= formatMoney((float)$ag['preco']) ?>
                                    </span>
                                </td>
                                <td>
                                    <!-- Status Badge + Form de Alteração Rápida -->
                                    <div class="flex items-center gap-2">
                                        <?= getStatusBadge($ag['status']) ?>
                                    </div>
                                </td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        
                                        <!-- Ações rápidas de status -->
                                        <?php if ($ag['status'] === 'pendente'): ?>
                                            <form method="POST" action="/admin/agendamentos/index.php" class="inline">
                                                <input type="hidden" name="action" value="quick_status">
                                                <input type="hidden" name="id" value="<?= $ag['id'] ?>">
                                                <input type="hidden" name="status" value="confirmado">
                                                <button type="submit" class="btn btn-sm btn-secondary text-blue-700 border-blue-200 hover:bg-blue-50" title="Confirmar Agendamento">
                                                    Confirmar
                                                </button>
                                            </form>
                                        <?php elseif ($ag['status'] === 'confirmado'): ?>
                                            <form method="POST" action="/admin/agendamentos/index.php" class="inline">
                                                <input type="hidden" name="action" value="quick_status">
                                                <input type="hidden" name="id" value="<?= $ag['id'] ?>">
                                                <input type="hidden" name="status" value="concluido">
                                                <button type="submit" class="btn btn-sm btn-secondary text-emerald-700 border-emerald-200 hover:bg-emerald-50" title="Concluir Atendimento">
                                                    Concluir
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($ag['status'] !== 'cancelado' && $ag['status'] !== 'concluido'): ?>
                                            <form method="POST" action="/admin/agendamentos/index.php" onsubmit="return confirm('Deseja realmente cancelar este agendamento? O horário ficará livre novamente.');" class="inline">
                                                <input type="hidden" name="action" value="quick_status">
                                                <input type="hidden" name="id" value="<?= $ag['id'] ?>">
                                                <input type="hidden" name="status" value="cancelado">
                                                <button type="submit" class="btn btn-sm btn-secondary text-rose-700 border-rose-200 hover:bg-rose-50" title="Cancelar Agendamento">
                                                    Cancelar
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <a href="/admin/agendamentos/visualizar.php?id=<?= $ag['id'] ?>" class="btn btn-secondary btn-sm" title="Ver Detalhes">
                                            Ver
                                        </a>
                                        <a href="/admin/agendamentos/editar.php?id=<?= $ag['id'] ?>" class="btn btn-secondary btn-sm" title="Editar Agendamento">
                                            Editar
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
