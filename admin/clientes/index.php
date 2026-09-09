<?php
/**
 * AGEND PRO - Gestão de Clientes (Listagem e Busca)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pdo = getDBConnection();

$busca = trim($_GET['busca'] ?? '');

$sql = "SELECT c.*,
            (SELECT COUNT(*) FROM `agendamentos` a WHERE a.cliente_id = c.id) AS total_agendamentos,
            (SELECT MAX(a.data) FROM `agendamentos` a WHERE a.cliente_id = c.id) AS ultimo_agendamento
        FROM `clientes` c
        WHERE 1=1";

$params = [];

if (!empty($busca)) {
    $sql .= " AND (c.nome LIKE :busca OR c.email LIKE :busca OR c.telefone LIKE :busca)";
    $params[':busca'] = '%' . $busca . '%';
}

$sql .= " ORDER BY c.nome ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll();

$pageTitle = 'Clientes';
$pageSubtitle = 'Base de pacientes e histórico de atendimentos';

include __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Base de Clientes</h2>
            <p class="text-xs text-slate-500 mt-0.5">Total de <?= count($clientes) ?> cliente(s) cadastrado(s)</p>
        </div>
        <a href="/admin/clientes/criar.php" class="btn btn-primary shadow-sm self-start sm:self-auto">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
            </svg>
            <span>Novo Cliente</span>
        </a>
    </div>

    <!-- Filtro -->
    <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-sm">
        <form method="GET" action="/admin/clientes/index.php" class="flex flex-col sm:flex-row items-center gap-3">
            <div class="flex-1 w-full">
                <input type="text" name="busca" value="<?= sanitize($busca) ?>" placeholder="Buscar cliente por nome, e-mail ou telefone..." class="text-sm">
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <button type="submit" class="btn btn-secondary text-sm flex-1 sm:flex-none">Buscar</button>
                <?php if (!empty($busca)): ?>
                    <a href="/admin/clientes/index.php" class="btn btn-secondary text-sm text-slate-500">Limpar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabela -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Nome Completo</th>
                        <th>Contato</th>
                        <th>Histórico</th>
                        <th>Último Atendimento</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clientes)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-10 text-slate-400">
                                Nenhum cliente encontrado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clientes as $c): ?>
                            <tr>
                                <td>
                                    <a href="/admin/clientes/visualizar.php?id=<?= $c['id'] ?>" class="font-bold text-slate-900 hover:text-teal-600 transition-colors">
                                        <?= sanitize($c['nome']) ?>
                                    </a>
                                    <div class="text-xs text-slate-400">Cadastrado em <?= formatDate($c['created_at']) ?></div>
                                </td>
                                <td>
                                    <div class="text-sm text-slate-700"><?= sanitize($c['telefone']) ?></div>
                                    <div class="text-xs text-slate-500"><?= sanitize($c['email'] ?: 'Sem e-mail') ?></div>
                                </td>
                                <td>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                        <?= $c['total_agendamentos'] ?> agendamento(s)
                                    </span>
                                </td>
                                <td>
                                    <span class="text-xs text-slate-600">
                                        <?= $c['ultimo_agendamento'] ? formatDate($c['ultimo_agendamento']) : 'Nenhum' ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="/admin/clientes/visualizar.php?id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm" title="Ver Histórico Completo">
                                            Histórico
                                        </a>
                                        <a href="/admin/clientes/editar.php?id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm" title="Editar Cadastro">
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
