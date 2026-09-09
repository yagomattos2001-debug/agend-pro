<?php
/**
 * AGEND PRO - Detalhes do Cliente e Histórico de Atendimentos
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

// Busca dados do cliente
$stmt = $pdo->prepare("SELECT * FROM `clientes` WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$cliente = $stmt->fetch();

if (!$cliente) {
    setFlashMessage('error', 'Cliente não encontrado.');
    header('Location: /admin/clientes/index.php');
    exit;
}

// Histórico de agendamentos do cliente (ordenado por data decrescente)
$sqlAg = "SELECT 
            a.id,
            a.data,
            a.hora_inicio,
            a.hora_fim,
            a.status,
            a.observacao,
            a.created_at,
            p.nome AS profissional_nome,
            p.especialidade AS profissional_especialidade,
            s.nome AS servico_nome,
            s.duracao_minutos,
            s.preco
          FROM `agendamentos` a
          INNER JOIN `profissionais` p ON a.profissional_id = p.id
          INNER JOIN `servicos` s ON a.servico_id = s.id
          WHERE a.cliente_id = :cliente_id
          ORDER BY a.data DESC, a.hora_inicio DESC";

$stmtAg = $pdo->prepare($sqlAg);
$stmtAg->execute([':cliente_id' => $id]);
$historico = $stmtAg->fetchAll();

// Métricas do cliente
$totalAg = count($historico);
$concluidos = 0;
$pendentes = 0;
$cancelados = 0;
$confirmados = 0;
$totalInvestido = 0.0;

foreach ($historico as $h) {
    if ($h['status'] === 'concluido') {
        $concluidos++;
        $totalInvestido += (float)$h['preco'];
    } elseif ($h['status'] === 'pendente') {
        $pendentes++;
    } elseif ($h['status'] === 'confirmado') {
        $confirmados++;
    } elseif ($h['status'] === 'cancelado') {
        $cancelados++;
    }
}

$pageTitle = 'Histórico do Cliente';
$pageSubtitle = 'Visualização do perfil e registro completo de atendimentos';

include __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight"><?= sanitize($cliente['nome']) ?></h2>
            <p class="text-xs text-slate-500 mt-0.5">Cliente cadastrado em <?= formatDate($cliente['created_at']) ?></p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/admin/agendamentos/criar.php?cliente_id=<?= $id ?>" class="btn btn-primary text-sm shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>Agendar para este Cliente</span>
            </a>
            <a href="/admin/clientes/editar.php?id=<?= $id ?>" class="btn btn-secondary text-sm">
                Editar Cadastro
            </a>
            <a href="/admin/clientes/index.php" class="btn btn-secondary text-sm">
                &larr; Voltar
            </a>
        </div>
    </div>

    <!-- Perfil e Métricas em Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        
        <!-- Cartão de Dados de Contato -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-3">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Contato</span>
            <div class="space-y-1 text-sm">
                <div>
                    <span class="text-xs text-slate-400 block">Telefone</span>
                    <span class="font-bold text-slate-900"><?= sanitize($cliente['telefone']) ?></span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 block">E-mail</span>
                    <span class="font-medium text-slate-700"><?= sanitize($cliente['email'] ?: 'Não informado') ?></span>
                </div>
            </div>
        </div>

        <!-- Total de Agendamentos -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total de Reservas</span>
            <div>
                <div class="text-3xl font-extrabold text-slate-900"><?= $totalAg ?></div>
                <div class="text-xs text-slate-500 mt-0.5"><?= $confirmados ?> confirmado(s), <?= $pendentes ?> pendente(s)</div>
            </div>
        </div>

        <!-- Concluídos -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Atendimentos Concluídos</span>
            <div>
                <div class="text-3xl font-extrabold text-emerald-600"><?= $concluidos ?></div>
                <div class="text-xs text-slate-500 mt-0.5">Realizados com sucesso</div>
            </div>
        </div>

        <!-- Investimento Acumulado -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-teal-700">Total em Atendimentos</span>
            <div>
                <div class="text-2xl font-extrabold text-teal-600"><?= formatMoney($totalInvestido) ?></div>
                <div class="text-xs text-slate-500 mt-0.5"><?= $cancelados ?> cancelamento(s)</div>
            </div>
        </div>

    </div>

    <!-- Tabela de Histórico -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-200 flex items-center justify-between">
            <h3 class="font-bold text-slate-900 text-base">Histórico Completo de Agendamentos</h3>
            <span class="text-xs text-slate-500"><?= $totalAg ?> registro(s) encontrado(s)</span>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Data & Horário</th>
                        <th>Serviço</th>
                        <th>Profissional</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Observações</th>
                        <th class="text-right">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($historico)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-10 text-slate-400">
                                Nenhum agendamento registrado para este cliente até o momento.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($historico as $item): ?>
                            <tr>
                                <td>
                                    <div class="font-semibold text-slate-900">
                                        <?= formatDate($item['data']) ?>
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        <?= formatTime($item['hora_inicio']) ?> às <?= formatTime($item['hora_fim']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="font-medium text-slate-900"><?= sanitize($item['servico_nome']) ?></div>
                                    <div class="text-xs text-slate-500"><?= $item['duracao_minutos'] ?> min</div>
                                </td>
                                <td>
                                    <div class="text-sm font-medium text-slate-800"><?= sanitize($item['profissional_nome']) ?></div>
                                    <div class="text-xs text-slate-400"><?= sanitize($item['profissional_especialidade']) ?></div>
                                </td>
                                <td>
                                    <span class="font-semibold text-slate-900 text-sm">
                                        <?= formatMoney((float)$item['preco']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= getStatusBadge($item['status']) ?>
                                </td>
                                <td class="max-w-xs">
                                    <span class="text-xs text-slate-600 block truncate" title="<?= sanitize($item['observacao'] ?? '') ?>">
                                        <?= sanitize($item['observacao'] ?: '-') ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="/admin/agendamentos/visualizar.php?id=<?= $item['id'] ?>" class="btn btn-secondary btn-sm">
                                            Ver
                                        </a>
                                        <a href="/admin/agendamentos/editar.php?id=<?= $item['id'] ?>" class="btn btn-secondary btn-sm">
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
