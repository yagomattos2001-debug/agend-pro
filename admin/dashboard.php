<?php
/**
 * AGEND PRO - Dashboard Administrativo
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();

$pdo = getDBConnection();

$today = date('Y-m-d');

// 1. Contagens dos Cards (Dados reais do MySQL)
// Total de agendamentos hoje
$stmtHoje = $pdo->prepare("SELECT COUNT(*) FROM `agendamentos` WHERE `data` = :today");
$stmtHoje->execute([':today' => $today]);
$totalHoje = (int)$stmtHoje->fetchColumn();

// Agendamentos pendentes
$stmtPendentes = $pdo->query("SELECT COUNT(*) FROM `agendamentos` WHERE `status` = 'pendente'");
$totalPendentes = (int)$stmtPendentes->fetchColumn();

// Agendamentos confirmados
$stmtConfirmados = $pdo->query("SELECT COUNT(*) FROM `agendamentos` WHERE `status` = 'confirmado'");
$totalConfirmados = (int)$stmtConfirmados->fetchColumn();

// Agendamentos concluídos
$stmtConcluidos = $pdo->query("SELECT COUNT(*) FROM `agendamentos` WHERE `status` = 'concluido'");
$totalConcluidos = (int)$stmtConcluidos->fetchColumn();

// Agendamentos cancelados
$stmtCancelados = $pdo->query("SELECT COUNT(*) FROM `agendamentos` WHERE `status` = 'cancelado'");
$totalCancelados = (int)$stmtCancelados->fetchColumn();

// 2. Próximos agendamentos (a partir de hoje, ordenados por data e hora_inicio ASC)
$sqlProximos = "SELECT 
                    a.id,
                    a.data,
                    a.hora_inicio,
                    a.hora_fim,
                    a.status,
                    a.observacao,
                    c.id AS cliente_id,
                    c.nome AS cliente_nome,
                    c.telefone AS cliente_telefone,
                    p.id AS profissional_id,
                    p.nome AS profissional_nome,
                    s.nome AS servico_nome,
                    s.duracao_minutos,
                    s.preco
                FROM `agendamentos` a
                INNER JOIN `clientes` c ON a.cliente_id = c.id
                INNER JOIN `profissionais` p ON a.profissional_id = p.id
                INNER JOIN `servicos` s ON a.servico_id = s.id
                WHERE a.data >= :today
                ORDER BY a.data ASC, a.hora_inicio ASC
                LIMIT 10";

$stmtProx = $pdo->prepare($sqlProximos);
$stmtProx->execute([':today' => $today]);
$proximosAgendamentos = $stmtProx->fetchAll();

$pageTitle = 'Dashboard';
$pageSubtitle = 'Visão geral operacional dos agendamentos e atendimentos';

include __DIR__ . '/../includes/header.php';
?>

<div class="space-y-8">
    
    <!-- Top Greeting & Quick Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
        <div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">
                Olá, <?= sanitize($authUser['nome'] ?? 'Administrador') ?>!
            </h2>
            <p class="text-slate-500 text-sm mt-0.5">
                Hoje é <?= getDayOfWeekName((int)date('w')) ?>, <?= date('d/m/Y') ?>. Confira a situação da sua clínica/agenda.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="/admin/agendamentos/criar.php" class="btn btn-primary shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>Novo Agendamento</span>
            </a>
            <a href="/public/index.php" target="_blank" class="btn btn-secondary shadow-sm">
                <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                <span class="hidden sm:inline">Página Pública</span>
            </a>
        </div>
    </div>

    <!-- Cards de Métricas (5 Cards Reais) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        
        <!-- Agendamentos Hoje -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider">Hoje</span>
                <span class="p-2 rounded-xl bg-teal-50 text-teal-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </span>
            </div>
            <div>
                <div class="text-3xl font-extrabold text-slate-900"><?= $totalHoje ?></div>
                <div class="text-xs text-slate-500 mt-1">Atendimentos marcados hoje</div>
            </div>
        </div>

        <!-- Pendentes -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between text-amber-600 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Pendentes</span>
                <span class="p-2 rounded-xl bg-amber-50 text-amber-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </span>
            </div>
            <div>
                <div class="text-3xl font-extrabold text-amber-600"><?= $totalPendentes ?></div>
                <div class="text-xs text-slate-500 mt-1">Aguardando confirmação</div>
            </div>
        </div>

        <!-- Confirmados -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between text-blue-600 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Confirmados</span>
                <span class="p-2 rounded-xl bg-blue-50 text-blue-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </span>
            </div>
            <div>
                <div class="text-3xl font-extrabold text-blue-600"><?= $totalConfirmados ?></div>
                <div class="text-xs text-slate-500 mt-1">Prontos para atendimento</div>
            </div>
        </div>

        <!-- Concluídos -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between text-emerald-600 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Concluídos</span>
                <span class="p-2 rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </span>
            </div>
            <div>
                <div class="text-3xl font-extrabold text-emerald-600"><?= $totalConcluidos ?></div>
                <div class="text-xs text-slate-500 mt-1">Realizados com sucesso</div>
            </div>
        </div>

        <!-- Cancelados -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col justify-between col-span-2 sm:col-span-1">
            <div class="flex items-center justify-between text-rose-600 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Cancelados</span>
                <span class="p-2 rounded-xl bg-rose-50 text-rose-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </span>
            </div>
            <div>
                <div class="text-3xl font-extrabold text-rose-600"><?= $totalCancelados ?></div>
                <div class="text-xs text-slate-500 mt-1">Não ocupam horários</div>
            </div>
        </div>

    </div>

    <!-- Tabela: Próximos Agendamentos -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        
        <div class="px-6 py-4.5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="font-bold text-slate-900 text-base">Próximos Agendamentos</h3>
                <p class="text-slate-500 text-xs mt-0.5">Listagem cronológica a partir da data de hoje</p>
            </div>
            <a href="/admin/agendamentos/index.php" class="text-xs font-semibold text-teal-600 hover:text-teal-700 flex items-center gap-1 self-start sm:self-auto">
                <span>Ver todos os agendamentos</span>
                <span>&rarr;</span>
            </a>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Data & Horário</th>
                        <th>Cliente</th>
                        <th>Serviço</th>
                        <th>Profissional</th>
                        <th>Status</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($proximosAgendamentos)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-10 text-slate-400">
                                <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3 text-slate-400">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <p class="text-sm font-semibold text-slate-600">Nenhum agendamento futuro encontrado</p>
                                <p class="text-xs text-slate-400 mt-1">Crie um agendamento manual ou aguarde solicitações do cliente.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($proximosAgendamentos as $ag): ?>
                            <tr>
                                <td>
                                    <div class="font-semibold text-slate-900">
                                        <?= formatTime($ag['hora_inicio']) ?> - <?= formatTime($ag['hora_fim']) ?>
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        <?= formatDate($ag['data']) ?> 
                                        <?php if ($ag['data'] === $today): ?>
                                            <span class="inline-block ml-1 font-bold text-teal-600">(Hoje)</span>
                                        <?php endif; ?>
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
                                    <div class="text-xs text-slate-500"><?= $ag['duracao_minutos'] ?> min &bull; <?= formatMoney((float)$ag['preco']) ?></div>
                                </td>
                                <td>
                                    <div class="text-sm font-medium text-slate-700"><?= sanitize($ag['profissional_nome']) ?></div>
                                </td>
                                <td>
                                    <?= getStatusBadge($ag['status']) ?>
                                </td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="/admin/agendamentos/visualizar.php?id=<?= $ag['id'] ?>" class="btn btn-secondary btn-sm" title="Detalhes do Agendamento">
                                            Visualizar
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
