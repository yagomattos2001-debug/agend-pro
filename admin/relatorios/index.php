<?php
/**
 * AGEND PRO - Relatórios e Métricas Operacionais
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pdo = getDBConnection();

// Filtro de período (padrão: mês atual)
$dataInicio = trim($_GET['data_inicio'] ?? date('Y-m-01'));
$dataFim    = trim($_GET['data_fim'] ?? date('Y-m-t'));

// 1. Total de agendamentos no período e contagem por status
$sqlStatus = "SELECT 
                status, 
                COUNT(*) AS total,
                COALESCE(SUM(s.preco), 0) AS valor_total
              FROM `agendamentos` a
              INNER JOIN `servicos` s ON a.servico_id = s.id
              WHERE a.data BETWEEN :inicio AND :fim
              GROUP BY status";

$stmtStatus = $pdo->prepare($sqlStatus);
$stmtStatus->execute([':inicio' => $dataInicio, ':fim' => $dataFim]);
$statusRows = $stmtStatus->fetchAll();

$totaisPorStatus = [
    'pendente'   => ['total' => 0, 'valor' => 0.0],
    'confirmado' => ['total' => 0, 'valor' => 0.0],
    'concluido'  => ['total' => 0, 'valor' => 0.0],
    'cancelado'  => ['total' => 0, 'valor' => 0.0],
];

$totalGeral = 0;
$faturamentoConcluido = 0.0;
$faturamentoPrevisto = 0.0;

foreach ($statusRows as $row) {
    $st = $row['status'];
    $cnt = (int)$row['total'];
    $val = (float)$row['valor_total'];

    if (isset($totaisPorStatus[$st])) {
        $totaisPorStatus[$st]['total'] = $cnt;
        $totaisPorStatus[$st]['valor'] = $val;
    }

    $totalGeral += $cnt;

    if ($st === 'concluido') {
        $faturamentoConcluido += $val;
    }
    if ($st === 'confirmado' || $st === 'concluido') {
        $faturamentoPrevisto += $val;
    }
}

// 2. Ranking de Serviços mais agendados no período
$sqlServicos = "SELECT 
                  s.nome,
                  s.duracao_minutos,
                  s.preco,
                  COUNT(a.id) AS total_agendamentos,
                  COUNT(CASE WHEN a.status = 'concluido' THEN 1 END) AS total_concluidos,
                  COALESCE(SUM(CASE WHEN a.status = 'concluido' THEN s.preco ELSE 0 END), 0) AS faturamento_realizado
                FROM `agendamentos` a
                INNER JOIN `servicos` s ON a.servico_id = s.id
                WHERE a.data BETWEEN :inicio AND :fim
                GROUP BY s.id
                ORDER BY total_agendamentos DESC, faturamento_realizado DESC";

$stmtServ = $pdo->prepare($sqlServicos);
$stmtServ->execute([':inicio' => $dataInicio, ':fim' => $dataFim]);
$rankingServicos = $stmtServ->fetchAll();

// 3. Ranking de Profissionais no período
$sqlProf = "SELECT 
              p.nome,
              p.especialidade,
              COUNT(a.id) AS total_agendamentos,
              COUNT(CASE WHEN a.status = 'concluido' THEN 1 END) AS total_concluidos,
              COALESCE(SUM(CASE WHEN a.status = 'concluido' THEN s.preco ELSE 0 END), 0) AS faturamento_realizado
            FROM `agendamentos` a
            INNER JOIN `profissionais` p ON a.profissional_id = p.id
            INNER JOIN `servicos` s ON a.servico_id = s.id
            WHERE a.data BETWEEN :inicio AND :fim
            GROUP BY p.id
            ORDER BY total_concluidos DESC, total_agendamentos DESC";

$stmtProf = $pdo->prepare($sqlProf);
$stmtProf->execute([':inicio' => $dataInicio, ':fim' => $dataFim]);
$rankingProfissionais = $stmtProf->fetchAll();

$pageTitle = 'Relatórios e Métricas';
$pageSubtitle = 'Desempenho de atendimentos, faturamento e rankings por período';

include __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Top Bar & Filtro por Período -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold text-slate-900 tracking-tight">Período de Análise</h2>
            <p class="text-xs text-slate-500 mt-0.5">Defina a janela de datas para filtrar métricas e relatórios</p>
        </div>

        <form method="GET" action="/admin/relatorios/index.php" class="flex flex-wrap items-center gap-3">
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">De</label>
                <input type="date" name="data_inicio" value="<?= sanitize($dataInicio) ?>" class="text-sm">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Até</label>
                <input type="date" name="data_fim" value="<?= sanitize($dataFim) ?>" class="text-sm">
            </div>
            <div class="flex items-end gap-2 pt-4 sm:pt-0">
                <button type="submit" class="btn btn-secondary text-sm">Atualizar</button>
                <button type="button" onclick="window.print()" class="btn btn-secondary text-sm" title="Imprimir Relatório">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                    <span>Imprimir</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Cards de Métricas Principais -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- Total no Período -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Agendamentos no Período</span>
            <div class="mt-3">
                <div class="text-3xl font-extrabold text-slate-900"><?= $totalGeral ?></div>
                <div class="text-xs text-slate-500 mt-1">Total de solicitações</div>
            </div>
        </div>

        <!-- Faturamento Concluído -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Faturamento Realizado</span>
            <div class="mt-3">
                <div class="text-2xl font-extrabold text-emerald-600"><?= formatMoney($faturamentoConcluido) ?></div>
                <div class="text-xs text-slate-500 mt-1"><?= $totaisPorStatus['concluido']['total'] ?> atendimento(s) concluído(s)</div>
            </div>
        </div>

        <!-- Faturamento Previsto -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-blue-700">Faturamento Previsto</span>
            <div class="mt-3">
                <div class="text-2xl font-extrabold text-blue-600"><?= formatMoney($faturamentoPrevisto) ?></div>
                <div class="text-xs text-slate-500 mt-1">Concluídos + Confirmados</div>
            </div>
        </div>

        <!-- Taxa de Conclusão -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-teal-700">Taxa de Conclusão</span>
            <div class="mt-3">
                <div class="text-3xl font-extrabold text-teal-600">
                    <?= $totalGeral > 0 ? round(($totaisPorStatus['concluido']['total'] / $totalGeral) * 100, 1) : 0 ?>%
                </div>
                <div class="text-xs text-slate-500 mt-1"><?= $totaisPorStatus['cancelado']['total'] ?> cancelamento(s)</div>
            </div>
        </div>

    </div>

    <!-- Distribuição por Status -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
        <h3 class="font-bold text-slate-900 text-base border-b border-slate-100 pb-3">Distribuição por Situação</h3>
        
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="p-4 rounded-xl bg-amber-50/50 border border-amber-200/80">
                <div class="text-xs font-semibold text-amber-800 uppercase">Pendentes</div>
                <div class="text-2xl font-bold text-amber-900 mt-1"><?= $totaisPorStatus['pendente']['total'] ?></div>
                <div class="text-xs text-amber-700 mt-0.5"><?= formatMoney((float)$totaisPorStatus['pendente']['valor']) ?></div>
            </div>

            <div class="p-4 rounded-xl bg-blue-50/50 border border-blue-200/80">
                <div class="text-xs font-semibold text-blue-800 uppercase">Confirmados</div>
                <div class="text-2xl font-bold text-blue-900 mt-1"><?= $totaisPorStatus['confirmado']['total'] ?></div>
                <div class="text-xs text-blue-700 mt-0.5"><?= formatMoney((float)$totaisPorStatus['confirmado']['valor']) ?></div>
            </div>

            <div class="p-4 rounded-xl bg-emerald-50/50 border border-emerald-200/80">
                <div class="text-xs font-semibold text-emerald-800 uppercase">Concluídos</div>
                <div class="text-2xl font-bold text-emerald-900 mt-1"><?= $totaisPorStatus['concluido']['total'] ?></div>
                <div class="text-xs text-emerald-700 mt-0.5"><?= formatMoney((float)$totaisPorStatus['concluido']['valor']) ?></div>
            </div>

            <div class="p-4 rounded-xl bg-rose-50/50 border border-rose-200/80">
                <div class="text-xs font-semibold text-rose-800 uppercase">Cancelados</div>
                <div class="text-2xl font-bold text-rose-900 mt-1"><?= $totaisPorStatus['cancelado']['total'] ?></div>
                <div class="text-xs text-rose-700 mt-0.5"><?= formatMoney((float)$totaisPorStatus['cancelado']['valor']) ?></div>
            </div>
        </div>
    </div>

    <!-- Duas Colunas: Ranking de Serviços & Ranking de Profissionais -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Ranking de Serviços -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden flex flex-col">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-900 text-base">Serviços Mais Agendados</h3>
                    <p class="text-xs text-slate-500">Volume de procura e faturamento gerado</p>
                </div>
            </div>

            <div class="table-responsive flex-1">
                <table>
                    <thead>
                        <tr>
                            <th>Serviço</th>
                            <th class="text-center">Agendados</th>
                            <th class="text-center">Concluídos</th>
                            <th class="text-right">Faturamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rankingServicos)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-8 text-slate-400">
                                    Nenhum agendamento de serviço no período.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rankingServicos as $rs): ?>
                                <tr>
                                    <td>
                                        <div class="font-semibold text-slate-900"><?= sanitize($rs['nome']) ?></div>
                                        <div class="text-xs text-slate-400"><?= $rs['duracao_minutos'] ?> min &bull; <?= formatMoney((float)$rs['preco']) ?></div>
                                    </td>
                                    <td class="text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                                            <?= $rs['total_agendamentos'] ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-xs font-bold text-emerald-600">
                                            <?= $rs['total_concluidos'] ?>
                                        </span>
                                    </td>
                                    <td class="text-right font-bold text-slate-900 text-sm">
                                        <?= formatMoney((float)$rs['faturamento_realizado']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Ranking de Profissionais -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden flex flex-col">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-900 text-base">Desempenho dos Profissionais</h3>
                    <p class="text-xs text-slate-500">Atendimentos realizados e receita produzida</p>
                </div>
            </div>

            <div class="table-responsive flex-1">
                <table>
                    <thead>
                        <tr>
                            <th>Profissional</th>
                            <th class="text-center">Agendados</th>
                            <th class="text-center">Concluídos</th>
                            <th class="text-right">Faturamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rankingProfissionais)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-8 text-slate-400">
                                    Nenhum atendimento realizado no período.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rankingProfissionais as $rp): ?>
                                <tr>
                                    <td>
                                        <div class="font-semibold text-slate-900"><?= sanitize($rp['nome']) ?></div>
                                        <div class="text-xs text-slate-400"><?= sanitize($rp['especialidade']) ?></div>
                                    </td>
                                    <td class="text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                                            <?= $rp['total_agendamentos'] ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-xs font-bold text-emerald-600">
                                            <?= $rp['total_concluidos'] ?>
                                        </span>
                                    </td>
                                    <td class="text-right font-bold text-slate-900 text-sm">
                                        <?= formatMoney((float)$rp['faturamento_realizado']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
