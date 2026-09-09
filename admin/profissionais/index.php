<?php
/**
 * AGEND PRO - Gestão de Profissionais (Listagem, Ativação/Desativação)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pdo = getDBConnection();

// Ação de Toggle Ativo / Desativo via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $profId = (int)($_POST['id'] ?? 0);
    if ($profId > 0) {
        $stmt = $pdo->prepare("SELECT ativo FROM `profissionais` WHERE id = :id");
        $stmt->execute([':id' => $profId]);
        $current = $stmt->fetchColumn();

        if ($current !== false) {
            $newStatus = ((int)$current === 1) ? 0 : 1;
            $update = $pdo->prepare("UPDATE `profissionais` SET ativo = :ativo, updated_at = NOW() WHERE id = :id");
            $update->execute([':ativo' => $newStatus, ':id' => $profId]);

            setFlashMessage('success', $newStatus === 1 ? 'Profissional ativado com sucesso!' : 'Profissional desativado com sucesso.');
        }
    }
    header('Location: /admin/profissionais/index.php');
    exit;
}

// Filtro de busca simples por nome/especialidade
$busca = trim($_GET['busca'] ?? '');
$statusFiltro = $_GET['status'] ?? '';

$sql = "SELECT p.*, 
            (SELECT COUNT(*) FROM `profissional_servicos` ps WHERE ps.profissional_id = p.id) AS total_servicos,
            (SELECT COUNT(*) FROM `agendamentos` a WHERE a.profissional_id = p.id) AS total_agendamentos
        FROM `profissionais` p
        WHERE 1=1";

$params = [];

if (!empty($busca)) {
    $sql .= " AND (p.nome LIKE :busca OR p.especialidade LIKE :busca OR p.email LIKE :busca)";
    $params[':busca'] = '%' . $busca . '%';
}

if ($statusFiltro === '1' || $statusFiltro === '0') {
    $sql .= " AND p.ativo = :status";
    $params[':status'] = (int)$statusFiltro;
}

$sql .= " ORDER BY p.ativo DESC, p.nome ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$profissionais = $stmt->fetchAll();

$pageTitle = 'Profissionais';
$pageSubtitle = 'Gerencie o corpo clínico, especialidades e status';

include __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Header Actions & Search Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Equipe de Profissionais</h2>
            <p class="text-xs text-slate-500 mt-0.5">Total de <?= count($profissionais) ?> profissional(is) cadastrado(s)</p>
        </div>
        <a href="/admin/profissionais/criar.php" class="btn btn-primary shadow-sm self-start sm:self-auto">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            <span>Cadastrar Profissional</span>
        </a>
    </div>

    <!-- Filter Card -->
    <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-sm">
        <form method="GET" action="/admin/profissionais/index.php" class="flex flex-col sm:flex-row items-center gap-3">
            <div class="flex-1 w-full">
                <input type="text" name="busca" value="<?= sanitize($busca) ?>" placeholder="Buscar por nome, especialidade ou e-mail..." class="text-sm">
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
                    <a href="/admin/profissionais/index.php" class="btn btn-secondary text-sm text-slate-500">Limpar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Table List -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Nome & Especialidade</th>
                        <th>Contato</th>
                        <th>Serviços Vinculados</th>
                        <th>Status</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($profissionais)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-10 text-slate-400">
                                Nenhum profissional encontrado com os filtros aplicados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($profissionais as $p): ?>
                            <tr>
                                <td>
                                    <div class="font-bold text-slate-900"><?= sanitize($p['nome']) ?></div>
                                    <div class="text-xs text-slate-500"><?= sanitize($p['especialidade']) ?></div>
                                </td>
                                <td>
                                    <div class="text-sm text-slate-700"><?= sanitize($p['email']) ?></div>
                                    <div class="text-xs text-slate-500"><?= sanitize($p['telefone']) ?></div>
                                </td>
                                <td>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                        <?= $p['total_servicos'] ?> serviço(s)
                                    </span>
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
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Configurar Horários -->
                                        <a href="/admin/horarios/configurar.php?profissional_id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm" title="Configurar Grade Semanal">
                                            Horários
                                        </a>

                                        <!-- Editar -->
                                        <a href="/admin/profissionais/editar.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm" title="Editar dados e serviços">
                                            Editar
                                        </a>

                                        <!-- Ativar / Desativar Form -->
                                        <form method="POST" action="/admin/profissionais/index.php" onsubmit="return confirm('Deseja realmente <?= (int)$p['ativo'] === 1 ? 'desativar' : 'ativar' ?> este profissional? <?= (int)$p['ativo'] === 1 ? 'Profissionais inativos não ficam visíveis para novos agendamentos.' : '' ?>');" class="inline">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn btn-sm <?= (int)$p['ativo'] === 1 ? 'btn-danger' : 'btn-primary' ?>" title="<?= (int)$p['ativo'] === 1 ? 'Desativar Profissional' : 'Ativar Profissional' ?>">
                                                <?= (int)$p['ativo'] === 1 ? 'Desativar' : 'Ativar' ?>
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
