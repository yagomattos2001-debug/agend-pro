<?php
/**
 * AGEND PRO - Configurações Gerais do Sistema
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pdo = getDBConnection();

// Carrega configurações existentes
$stmt = $pdo->query("SELECT chave, valor FROM `configuracoes`");
$configsRaw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$erro = '';
$sucesso = '';

$empresaNome      = $configsRaw['empresa_nome'] ?? 'Clínica & Consultoria AGEND PRO';
$empresaTelefone  = $configsRaw['empresa_telefone'] ?? '(11) 3456-7890';
$empresaEmail     = $configsRaw['empresa_email'] ?? 'contato@agendpro.com.br';
$empresaEndereco  = $configsRaw['empresa_endereco'] ?? 'Av. Paulista, 1000, Cj. 501 - Bela Vista, São Paulo - SP';
$horarioAbertura  = $configsRaw['horario_abertura'] ?? '08:00';
$horarioFechamento= $configsRaw['horario_fechamento'] ?? '18:00';
$intervaloSlots   = $configsRaw['intervalo_slots'] ?? '30';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empresaNome       = trim($_POST['empresa_nome'] ?? '');
    $empresaTelefone   = trim($_POST['empresa_telefone'] ?? '');
    $empresaEmail      = trim($_POST['empresa_email'] ?? '');
    $empresaEndereco   = trim($_POST['empresa_endereco'] ?? '');
    $horarioAbertura   = trim($_POST['horario_abertura'] ?? '08:00');
    $horarioFechamento = trim($_POST['horario_fechamento'] ?? '18:00');
    $intervaloSlots    = trim($_POST['intervalo_slots'] ?? '30');

    if (empty($empresaNome)) {
        $erro = 'O nome do estabelecimento é obrigatório.';
    } else {
        try {
            $pdo->beginTransaction();

            $novasConfigs = [
                'empresa_nome'       => $empresaNome,
                'empresa_telefone'   => $empresaTelefone,
                'empresa_email'      => $empresaEmail,
                'empresa_endereco'   => $empresaEndereco,
                'horario_abertura'   => $horarioAbertura,
                'horario_fechamento' => $horarioFechamento,
                'intervalo_slots'    => $intervaloSlots,
            ];

            $stmtUpsert = $pdo->prepare("INSERT INTO `configuracoes` (chave, valor) 
                                         VALUES (:chave, :valor) 
                                         ON DUPLICATE KEY UPDATE valor = VALUES(valor), updated_at = NOW()");

            foreach ($novasConfigs as $ch => $vl) {
                $stmtUpsert->execute([':chave' => $ch, ':valor' => $vl]);
            }

            $pdo->commit();
            setFlashMessage('success', 'Configurações do sistema atualizadas com sucesso!');
            header('Location: /admin/configuracoes/index.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erro ao salvar configuracoes: " . $e->getMessage());
            $erro = 'Erro ao salvar as configurações no banco de dados.';
        }
    }
}

$pageTitle = 'Configurações do Sistema';
$pageSubtitle = 'Parâmetros institucionais, horários padrão e intervalo de atendimento';

include __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-3xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Configurações Gerais</h2>
            <p class="text-xs text-slate-500 mt-0.5">Gerenciamento dos dados do estabelecimento e parâmetros operacionais</p>
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

    <form method="POST" action="/admin/configuracoes/index.php" class="space-y-6">
        
        <!-- 1. Dados Institucionais -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
            <h3 class="font-bold text-slate-900 text-base border-b border-slate-100 pb-3">Identificação do Estabelecimento</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label for="empresa_nome" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Nome da Empresa / Clínica *</label>
                    <input type="text" id="empresa_nome" name="empresa_nome" value="<?= sanitize($empresaNome) ?>" required class="text-sm">
                </div>

                <div>
                    <label for="empresa_telefone" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Telefone Comercial / WhatsApp</label>
                    <input type="text" id="empresa_telefone" name="empresa_telefone" value="<?= sanitize($empresaTelefone) ?>" class="text-sm">
                </div>

                <div>
                    <label for="empresa_email" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">E-mail Institucional</label>
                    <input type="email" id="empresa_email" name="empresa_email" value="<?= sanitize($empresaEmail) ?>" class="text-sm">
                </div>

                <div class="sm:col-span-2">
                    <label for="empresa_endereco" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Endereço Completo</label>
                    <input type="text" id="empresa_endereco" name="empresa_endereco" value="<?= sanitize($empresaEndereco) ?>" class="text-sm">
                </div>
            </div>
        </div>

        <!-- 2. Parâmetros de Funcionamento -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
            <h3 class="font-bold text-slate-900 text-base border-b border-slate-100 pb-3">Parâmetros de Horário e Atendimento</h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="horario_abertura" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Abertura Geral Padrão</label>
                    <input type="time" id="horario_abertura" name="horario_abertura" value="<?= sanitize($horarioAbertura) ?>" class="text-sm font-mono">
                </div>

                <div>
                    <label for="horario_fechamento" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Fechamento Geral Padrão</label>
                    <input type="time" id="horario_fechamento" name="horario_fechamento" value="<?= sanitize($horarioFechamento) ?>" class="text-sm font-mono">
                </div>

                <div>
                    <label for="intervalo_slots" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Intervalo dos Slots (Minutos)</label>
                    <select id="intervalo_slots" name="intervalo_slots" class="text-sm">
                        <option value="15" <?= $intervaloSlots === '15' ? 'selected' : '' ?>>15 minutos</option>
                        <option value="30" <?= $intervaloSlots === '30' ? 'selected' : '' ?>>30 minutos (Recomendado)</option>
                        <option value="45" <?= $intervaloSlots === '45' ? 'selected' : '' ?>>45 minutos</option>
                        <option value="60" <?= $intervaloSlots === '60' ? 'selected' : '' ?>>60 minutos</option>
                    </select>
                </div>
            </div>
            
            <p class="text-xs text-slate-400">
                Dica: O expediente individual de cada profissional pode ser personalizado na aba <strong>Horários de Trabalho</strong>.
            </p>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <button type="submit" class="btn btn-primary shadow-sm">
                Salvar Configurações
            </button>
        </div>

    </form>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
