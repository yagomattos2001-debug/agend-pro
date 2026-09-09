<?php
/**
 * AGEND PRO - Gestão de Horários de Trabalho (Visão Geral por Profissional)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pdo = getDBConnection();

// Busca todos os profissionais com contagem de dias trabalhados
$sql = "SELECT p.*,
            (SELECT COUNT(*) FROM `horarios_trabalho` ht WHERE ht.profissional_id = p.id AND ht.ativo = 1) AS dias_ativos
        FROM `profissionais` p
        ORDER BY p.ativo DESC, p.nome ASC";

$stmt = $pdo->query($sql);
$profissionais = $stmt->fetchAll();

$pageTitle = 'Horários de Trabalho';
$pageSubtitle = 'Configure o expediente semanal e turnos de atendimento de cada profissional';

include __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Expediente dos Profissionais</h2>
            <p class="text-xs text-slate-500 mt-0.5">Defina os dias trabalhados e a faixa de horários para a geração de slots da agenda</p>
        </div>
    </div>

    <!-- Tabela -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Profissional</th>
                        <th>Especialidade</th>
                        <th>Status Cadastral</th>
                        <th>Dias Trabalhados</th>
                        <th class="text-right">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($profissionais)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-10 text-slate-400">
                                Nenhum profissional cadastrado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($profissionais as $p): ?>
                            <tr>
                                <td>
                                    <div class="font-bold text-slate-900"><?= sanitize($p['nome']) ?></div>
                                    <div class="text-xs text-slate-500"><?= sanitize($p['email']) ?></div>
                                </td>
                                <td>
                                    <span class="text-sm font-medium text-slate-700"><?= sanitize($p['especialidade']) ?></span>
                                </td>
                                <td>
                                    <?php if ((int)$p['ativo'] === 1): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                            Ativo
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 border border-rose-300">
                                            Inativo
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        <?= $p['dias_ativos'] ?> de 7 dias da semana
                                    </span>
                                </td>
                                <td class="text-right">
                                    <a href="/admin/horarios/configurar.php?profissional_id=<?= $p['id'] ?>" class="btn btn-primary btn-sm">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        <span>Configurar Grade Semanal</span>
                                    </a>
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
