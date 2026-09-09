<?php
/**
 * AGEND PRO - Gestão de Serviços (Listagem, Filtros, Ativação)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pdo = getDBConnection();

// Toggle de Ativação / Desativação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $servicoId = (int)($_POST['id'] ?? 0);
    if ($servicoId > 0) {
        $stmt = $pdo->prepare("SELECT ativo FROM `servicos` WHERE id = :id");
        $stmt->execute([':id' => $servicoId]);
        $current = $stmt->fetchColumn();

        if ($current !== false) {
            $newStatus = ((int)$current === 1) ? 0 : 1;
            $update = $pdo->prepare("UPDATE `servicos` SET ativo = :ativo, updated_at = NOW() WHERE id = :id");
            $update->execute([':ativo' => $newStatus, ':id' => $servicoId]);

            setFlashMessage('success', $newStatus === 1 ? 'Serviço ativado com sucesso!' : 'Serviço desativado com sucesso.');
        }
    }
    header('Location: /admin/servicos/index.php');
    exit;
}

$busca = trim($_GET['busca'] ?? '');
$statusFiltro = $_GET['status'] ?? '';

$sql = "SELECT s.*,
            (SELECT COUNT(*) FROM `profissional_servicos` ps WHERE ps.servico_id = s.id) AS total_profissionais,
            (SELECT COUNT(*) FROM `agendamentos` a WHERE a.servico_id = s.id) AS total_agendamentos
        FROM `servicos` s
        WHERE 1=1";

$params = [];

if (!empty($busca)) {
    $sql .= " AND (s.nome LIKE :busca OR s.descricao LIKE :busca)";
    $params[':busca'] = '%' . $busca . '%';
}

if ($statusFiltro === '1' || $statusFiltro === '0') {
    $sql .= " AND s.ativo = :status";
    $params[':status'] = (int)$statusFiltro;
}

$sql .= " ORDER BY s.ativo DESC, s.nome ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$servicos = $stmt->fetchAll();

$pageTitle = 'Serviços';
$pageSubtitle = 'Catálogo de procedimentos, durações e valores de atendimento';

include __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Catálogo de Serviços</h2>
            <p class="text-xs text-slate-500 mt-0.5">Total de <?= count($servicos) ?> serviço(s) configurado(s)</p>
        </div>
        <a href="/admin/servicos/criar.php" class="btn btn-primary shadow-sm self-start sm:self-auto">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            <span>Cadastrar Serviço</span>
        </a>
    </div>

    <!-- Filter Card -->
    <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-sm">
        <form method="GET" action="/admin/servicos/index.php" class="flex flex-col sm:flex-row items-center gap-3">
            <div class="flex-1 w-full">
                <input type="text" name="busca" value="<?= sanitize($busca) ?>" placeholder="Buscar por título ou descrição do serviço..." class="text-sm">
            </div>
            <div class="w-full sm:w-48">
                <select name="status" class="text-sm">
                    <option value="">Todos os status</option>
                    <option value="1" <?= $statusFiltro === '1' ? 'selected' : '' ?>>Somente Ativos</option>
                    <option value="0" <?= $statusFiltro === '0' ? 'selected' : '' ?>>Somente Inativos</option>
                </select>
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <button type="submit" class="btn btn-secondary text-sm flex-1 sm:flex-none">Filtrar</button>
                <?php if (!empty($busca) || $statusFiltro !== ''): ?>
                    <a href="/admin/servicos/index.php" class="btn btn-secondary text-sm text-slate-500">Limpar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Nome & Descrição</th>
                        <th>Duração</th>
                        <th>Preço</th>
                        <th>Profissionais Vinculados</th>
                        <th>Status</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($servicos)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-10 text-slate-400">
                                Nenhum serviço encontrado com os filtros informados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($servicos as $s): ?>
                            <tr>
                                <td class="max-w-md">
                                    <div class="font-bold text-slate-900"><?= sanitize($s['nome']) ?></div>
                                    <div class="text-xs text-slate-500 line-clamp-1 mt-0.5"><?= sanitize($s['descricao'] ?: 'Sem descrição informada.') ?></div>
                                </td>
                                <td>
                                    <span class="inline-flex items-center gap-1 font-semibold text-slate-800 text-sm">
                                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <?= (int)$s['duracao_minutos'] ?> min
                                    </span>
                                </td>
                                <td>
                                    <span class="font-bold text-teal-700 text-sm">
                                        <?= formatMoney((float)$s['preco']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                        <?= $s['total_profissionais'] ?> profissional(is)
                                    </span>
                                </td>
                                <td>
                                    <?php if ((int)$s['ativo'] === 1): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                            Ativo
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 border border-rose-300">
                                            Inativo
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="/admin/servicos/editar.php?id=<?= $s['id'] ?>" class="btn btn-secondary btn-sm" title="Editar Serviço">
                                            Editar
                                        </a>

                                        <form method="POST" action="/admin/servicos/index.php" onsubmit="return confirm('Deseja realmente <?= (int)$s['ativo'] === 1 ? 'desativar' : 'ativar' ?> este serviço? <?= (int)$s['ativo'] === 1 ? 'Serviços inativos não aparecem para reserva.' : '' ?>');" class="inline">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-sm <?= (int)$s['ativo'] === 1 ? 'btn-danger' : 'btn-primary' ?>">
                                                <?= (int)$s['ativo'] === 1 ? 'Desativar' : 'Ativar' ?>
                                            </button>
                                        </form>
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
