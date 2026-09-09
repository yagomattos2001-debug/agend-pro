<?php
/**
 * AGEND PRO - Detalhes do Agendamento
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

$sql = "SELECT 
            a.*,
            c.id AS cliente_id,
            c.nome AS cliente_nome,
            c.telefone AS cliente_telefone,
            c.email AS cliente_email,
            p.id AS profissional_id,
            p.nome AS profissional_nome,
            p.especialidade AS profissional_especialidade,
            p.email AS profissional_email,
            p.telefone AS profissional_telefone,
            s.id AS servico_id,
            s.nome AS servico_nome,
            s.descricao AS servico_descricao,
            s.duracao_minutos,
            s.preco
        FROM `agendamentos` a
        INNER JOIN `clientes` c ON a.cliente_id = c.id
        INNER JOIN `profissionais` p ON a.profissional_id = p.id
        INNER JOIN `servicos` s ON a.servico_id = s.id
        WHERE a.id = :id
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$ag = $stmt->fetch();

if (!$ag) {
    setFlashMessage('error', 'Agendamento não encontrado.');
    header('Location: /admin/agendamentos/index.php');
    exit;
}

$pageTitle = 'Agendamento #' . $ag['id'];
$pageSubtitle = 'Detalhes operacionais do atendimento';

include __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">

    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-teal-50 border border-teal-200 text-teal-700 flex items-center justify-center font-bold text-lg">
                #<?= $ag['id'] ?>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-bold text-slate-900 tracking-tight">Atendimento Agendado</h2>
                    <?= getStatusBadge($ag['status']) ?>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Criado em <?= date('d/m/Y H:i', strtotime($ag['created_at'])) ?></p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="/admin/agendamentos/editar.php?id=<?= $ag['id'] ?>" class="btn btn-secondary text-sm">
                Editar
            </a>
            <a href="/admin/agendamentos/index.php" class="btn btn-secondary text-sm">
                &larr; Voltar
            </a>
        </div>
    </div>

    <!-- Quick Status Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="text-xs text-slate-600">
            <span class="font-semibold text-slate-900">Alterar Situação Rápida:</span>
            <span>Atualize o andamento do atendimento em um clique</span>
        </div>
        <div class="flex items-center gap-2">
            <?php if ($ag['status'] !== 'pendente'): ?>
                <form method="POST" action="/admin/agendamentos/index.php" class="inline">
                    <input type="hidden" name="action" value="quick_status">
                    <input type="hidden" name="id" value="<?= $ag['id'] ?>">
                    <input type="hidden" name="status" value="pendente">
                    <button type="submit" class="btn btn-sm btn-secondary text-amber-700">Mudar para Pendente</button>
                </form>
            <?php endif; ?>

            <?php if ($ag['status'] !== 'confirmado'): ?>
                <form method="POST" action="/admin/agendamentos/index.php" class="inline">
                    <input type="hidden" name="action" value="quick_status">
                    <input type="hidden" name="id" value="<?= $ag['id'] ?>">
                    <input type="hidden" name="status" value="confirmado">
                    <button type="submit" class="btn btn-sm btn-secondary text-blue-700">Confirmar</button>
                </form>
            <?php endif; ?>

            <?php if ($ag['status'] !== 'concluido'): ?>
                <form method="POST" action="/admin/agendamentos/index.php" class="inline">
                    <input type="hidden" name="action" value="quick_status">
                    <input type="hidden" name="id" value="<?= $ag['id'] ?>">
                    <input type="hidden" name="status" value="concluido">
                    <button type="submit" class="btn btn-sm btn-secondary text-emerald-700">Concluir</button>
                </form>
            <?php endif; ?>

            <?php if ($ag['status'] !== 'cancelado'): ?>
                <form method="POST" action="/admin/agendamentos/index.php" onsubmit="return confirm('Deseja cancelar este agendamento?');" class="inline">
                    <input type="hidden" name="action" value="quick_status">
                    <input type="hidden" name="id" value="<?= $ag['id'] ?>">
                    <input type="hidden" name="status" value="cancelado">
                    <button type="submit" class="btn btn-sm btn-danger">Cancelar</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Details Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <!-- Cartão de Horário & Procedimento -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
            <h3 class="font-bold text-slate-900 text-base border-b border-slate-100 pb-3">Horário & Procedimento</h3>
            
            <div class="space-y-3 text-sm">
                <div>
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Data do Atendimento</span>
                    <div class="text-base font-bold text-slate-900 mt-0.5">
                        <?= formatDate($ag['data']) ?> (<?= getDayOfWeekName((int)date('w', strtotime($ag['data']))) ?>)
                    </div>
                </div>

                <div>
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Janela de Horário</span>
                    <div class="text-base font-bold text-teal-700 mt-0.5">
                        <?= formatTime($ag['hora_inicio']) ?> às <?= formatTime($ag['hora_fim']) ?>
                        <span class="text-xs font-normal text-slate-500 ml-1">(<?= $ag['duracao_minutos'] ?> minutos)</span>
                    </div>
                </div>

                <div>
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Serviço</span>
                    <div class="font-bold text-slate-900 mt-0.5"><?= sanitize($ag['servico_nome']) ?></div>
                    <?php if ($ag['servico_descricao']): ?>
                        <div class="text-xs text-slate-500 mt-0.5"><?= sanitize($ag['servico_descricao']) ?></div>
                    <?php endif; ?>
                </div>

                <div>
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Valor do Procedimento</span>
                    <div class="text-lg font-extrabold text-slate-900 mt-0.5"><?= formatMoney((float)$ag['preco']) ?></div>
                </div>
            </div>
        </div>

        <!-- Cartão do Cliente & Profissional -->
        <div class="space-y-6">
            
            <!-- Cliente -->
            <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="font-bold text-slate-900 text-base">Cliente</h3>
                    <a href="/admin/clientes/visualizar.php?id=<?= $ag['cliente_id'] ?>" class="text-xs font-semibold text-teal-600 hover:text-teal-700">
                        Ver histórico &rarr;
                    </a>
                </div>
                <div class="text-sm space-y-1">
                    <div class="font-bold text-slate-900 text-base"><?= sanitize($ag['cliente_nome']) ?></div>
                    <div class="text-slate-600 flex items-center gap-1.5">
                        <span class="text-slate-400 text-xs">Telefone:</span>
                        <span class="font-semibold"><?= sanitize($ag['cliente_telefone']) ?></span>
                    </div>
                    <?php if ($ag['cliente_email']): ?>
                        <div class="text-slate-600 flex items-center gap-1.5">
                            <span class="text-slate-400 text-xs">E-mail:</span>
                            <span><?= sanitize($ag['cliente_email']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profissional -->
            <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="font-bold text-slate-900 text-base">Profissional Responsável</h3>
                    <a href="/admin/profissionais/editar.php?id=<?= $ag['profissional_id'] ?>" class="text-xs font-semibold text-teal-600 hover:text-teal-700">
                        Ver perfil &rarr;
                    </a>
                </div>
                <div class="text-sm space-y-1">
                    <div class="font-bold text-slate-900 text-base"><?= sanitize($ag['profissional_nome']) ?></div>
                    <div class="text-xs text-slate-500 font-medium"><?= sanitize($ag['profissional_especialidade']) ?></div>
                    <div class="text-xs text-slate-400 mt-1"><?= sanitize($ag['profissional_email']) ?> &bull; <?= sanitize($ag['profissional_telefone']) ?></div>
                </div>
            </div>

        </div>

    </div>

    <!-- Observações -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-2">
        <h3 class="font-bold text-slate-900 text-base border-b border-slate-100 pb-3">Observações / Anotações</h3>
        <p class="text-sm text-slate-700 whitespace-pre-wrap">
            <?= $ag['observacao'] ? sanitize($ag['observacao']) : '<span class="text-slate-400 italic">Nenhuma observação informada para este agendamento.</span>' ?>
        </p>
    </div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
