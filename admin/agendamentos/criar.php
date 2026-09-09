<?php
/**
 * AGEND PRO - Criação Manual de Agendamento pelo Administrador
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pdo = getDBConnection();

// Pré-seleção via query string se vier da tela de cliente
$clienteIdPre = (int)($_GET['cliente_id'] ?? 0);

// Busca dados para os selects
$clientes = $pdo->query("SELECT id, nome, telefone, email FROM `clientes` ORDER BY nome ASC")->fetchAll();
$servicos = $pdo->query("SELECT id, nome, duracao_minutos, preco FROM `servicos` WHERE ativo = 1 ORDER BY nome ASC")->fetchAll();
$profissionais = $pdo->query("SELECT id, nome, especialidade FROM `profissionais` WHERE ativo = 1 ORDER BY nome ASC")->fetchAll();

$erro = '';
$clienteTipo = 'existente'; // 'existente' ou 'novo'
$clienteId = $clienteIdPre;
$novoNome = '';
$novoTelefone = '';
$novoEmail = '';
$servicoId = 0;
$profissionalId = 0;
$data = date('Y-m-d');
$horaInicio = '';
$status = 'confirmado';
$observacao = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clienteTipo     = $_POST['cliente_tipo'] ?? 'existente';
    $clienteId       = (int)($_POST['cliente_id'] ?? 0);
    $novoNome        = trim($_POST['novo_nome'] ?? '');
    $novoTelefone    = trim($_POST['novo_telefone'] ?? '');
    $novoEmail       = trim($_POST['novo_email'] ?? '');
    $servicoId       = (int)($_POST['servico_id'] ?? 0);
    $profissionalId  = (int)($_POST['profissional_id'] ?? 0);
    $data            = trim($_POST['data'] ?? '');
    $horaInicio      = trim($_POST['hora_inicio'] ?? '');
    $status          = trim($_POST['status'] ?? 'confirmado');
    $observacao      = trim($_POST['observacao'] ?? '');

    // Validação
    if ($clienteTipo === 'existente' && $clienteId <= 0) {
        $erro = 'Por favor, selecione um cliente existente.';
    } elseif ($clienteTipo === 'novo' && (empty($novoNome) || empty($novoTelefone))) {
        $erro = 'Para novo cliente, informe ao menos o Nome e o Telefone.';
    } elseif ($servicoId <= 0) {
        $erro = 'Selecione um serviço.';
    } elseif ($profissionalId <= 0) {
        $erro = 'Selecione um profissional.';
    } elseif (empty($data) || empty($horaInicio)) {
        $erro = 'Selecione a data e o horário desejado.';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Identifica ou cria o cliente
            if ($clienteTipo === 'novo') {
                $stmtC = $pdo->prepare("INSERT INTO `clientes` (nome, email, telefone) VALUES (:nome, :email, :telefone)");
                $stmtC->execute([
                    ':nome'     => $novoNome,
                    ':email'    => $novoEmail ?: null,
                    ':telefone' => $novoTelefone,
                ]);
                $clienteId = (int)$pdo->lastInsertId();
            }

            // 2. Busca duração do serviço para calcular hora_fim
            $stmtS = $pdo->prepare("SELECT duracao_minutos FROM `servicos` WHERE id = :id AND ativo = 1");
            $stmtS->execute([':id' => $servicoId]);
            $servicoInfo = $stmtS->fetch();

            if (!$servicoInfo) {
                throw new Exception('Serviço inválido ou inativo.');
            }

            $duracao = (int)$servicoInfo['duracao_minutos'];
            $horaInicioFmt = strlen($horaInicio) === 5 ? $horaInicio . ':00' : $horaInicio;
            $horaFimFmt = calculateEndTime($horaInicioFmt, $duracao);

            // 3. Regra de ouro: Prevenção rigorosa de conflito
            $temConflito = checkAppointmentConflict($pdo, $profissionalId, $data, $horaInicioFmt, $horaFimFmt);
            if ($temConflito) {
                throw new Exception('Horário indisponível! O profissional já possui outro agendamento neste mesmo período.');
            }

            // 4. Insere agendamento
            $stmtAg = $pdo->prepare("INSERT INTO `agendamentos` (cliente_id, profissional_id, servico_id, data, hora_inicio, hora_fim, status, observacao)
                                     VALUES (:cliente_id, :prof_id, :serv_id, :data, :inicio, :fim, :status, :obs)");
            $stmtAg->execute([
                ':cliente_id' => $clienteId,
                ':prof_id'    => $profissionalId,
                ':serv_id'    => $servicoId,
                ':data'       => $data,
                ':inicio'     => $horaInicioFmt,
                ':fim'        => $horaFimFmt,
                ':status'     => $status,
                ':obs'        => $observacao ?: null,
            ]);
            $agId = (int)$pdo->lastInsertId();

            $pdo->commit();

            setFlashMessage('success', 'Agendamento #' . $agId . ' criado com sucesso!');
            header('Location: /admin/agendamentos/visualizar.php?id=' . $agId);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = $e->getMessage();
        }
    }
}

$pageTitle = 'Novo Agendamento';
$pageSubtitle = 'Criação direta no painel com validação de horários em tempo real';

include __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Criar Agendamento</h2>
            <p class="text-xs text-slate-500 mt-0.5">Selecione o cliente, serviço, profissional e horário disponível</p>
        </div>
        <a href="/admin/agendamentos/index.php" class="btn btn-secondary text-sm">
            &larr; Voltar
        </a>
    </div>

    <?php if (!empty($erro)): ?>
        <div class="p-4 rounded-xl text-sm bg-rose-50 border border-rose-200 text-rose-800 flex items-center gap-2.5">
            <svg class="w-5 h-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span><?= sanitize($erro) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="/admin/agendamentos/criar.php" id="form-agendamento" class="space-y-6">
        
        <!-- 1. Identificação do Cliente -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
            <h3 class="font-bold text-slate-900 text-base border-b border-slate-100 pb-3">1. Cliente</h3>
            
            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 text-sm font-medium text-slate-800 cursor-pointer">
                    <input type="radio" name="cliente_tipo" value="existente" <?= $clienteTipo === 'existente' ? 'checked' : '' ?> id="tipo-existente">
                    <span>Selecionar Cliente Existente</span>
                </label>
                <label class="flex items-center gap-2 text-sm font-medium text-slate-800 cursor-pointer">
                    <input type="radio" name="cliente_tipo" value="novo" <?= $clienteTipo === 'novo' ? 'checked' : '' ?> id="tipo-novo">
                    <span>Cadastrar Novo Cliente</span>
                </label>
            </div>

            <!-- Seleção de Cliente Existente -->
            <div id="secao-cliente-existente" class="<?= $clienteTipo === 'novo' ? 'hidden' : '' ?>">
                <label for="cliente_id" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Pesquisar Cliente *</label>
                <select id="cliente_id" name="cliente_id" class="text-sm">
                    <option value="">-- Escolha um cliente cadastrado --</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $clienteId === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= sanitize($c['nome']) ?> &bull; <?= sanitize($c['telefone']) ?> <?= $c['email'] ? '(' . sanitize($c['email']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Formulário Rápido de Novo Cliente -->
            <div id="secao-cliente-novo" class="grid grid-cols-1 sm:grid-cols-3 gap-3 <?= $clienteTipo === 'existente' ? 'hidden' : '' ?>">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Nome do Cliente *</label>
                    <input type="text" name="novo_nome" value="<?= sanitize($novoNome) ?>" placeholder="Ex: Camila Soares" class="text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Telefone / WhatsApp *</label>
                    <input type="tel" name="novo_telefone" value="<?= sanitize($novoTelefone) ?>" placeholder="(11) 98888-7777" class="text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">E-mail (Opcional)</label>
                    <input type="email" name="novo_email" value="<?= sanitize($novoEmail) ?>" placeholder="camila@email.com" class="text-sm">
                </div>
            </div>

        </div>

        <!-- 2. Serviço e Profissional -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
            <h3 class="font-bold text-slate-900 text-base border-b border-slate-100 pb-3">2. Serviço & Profissional</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="servico_id" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Serviço *</label>
                    <select id="servico_id" name="servico_id" class="text-sm" required>
                        <option value="">-- Selecione o serviço --</option>
                        <?php foreach ($servicos as $s): ?>
                            <option value="<?= $s['id'] ?>" data-duracao="<?= $s['duracao_minutos'] ?>" data-preco="<?= $s['preco'] ?>" <?= $servicoId === (int)$s['id'] ? 'selected' : '' ?>>
                                <?= sanitize($s['nome']) ?> (<?= $s['duracao_minutos'] ?> min - <?= formatMoney((float)$s['preco']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="profissional_id" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Profissional *</label>
                    <select id="profissional_id" name="profissional_id" class="text-sm" required>
                        <option value="">-- Selecione o profissional --</option>
                        <?php foreach ($profissionais as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $profissionalId === (int)$p['id'] ? 'selected' : '' ?>>
                                <?= sanitize($p['nome']) ?> (<?= sanitize($p['especialidade']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- 3. Data e Seleção de Horário Disponível -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
            <h3 class="font-bold text-slate-900 text-base border-b border-slate-100 pb-3">3. Data & Horário Disponível</h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                <div>
                    <label for="data" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Data do Atendimento *</label>
                    <input type="date" id="data" name="data" value="<?= sanitize($data) ?>" min="<?= date('Y-m-d') ?>" required class="text-sm font-medium">
                </div>
                <div class="sm:col-span-2">
                    <button type="button" id="btn-consultar-horarios" class="btn btn-secondary text-sm w-full sm:w-auto">
                        <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <span>Buscar Horários Livres</span>
                    </button>
                </div>
            </div>

            <!-- Container Dinâmico dos Slots Livres -->
            <div id="slots-container" class="pt-2">
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Horários Disponíveis (Sem Conflitos)</label>
                
                <input type="hidden" name="hora_inicio" id="hora_inicio_selecionada" value="<?= sanitize($horaInicio) ?>" required>
                
                <div id="slots-grid" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2 min-h-[50px] items-center">
                    <div class="col-span-full text-xs text-slate-400 p-3 bg-slate-50 rounded-xl border border-dashed border-slate-200 text-center">
                        Selecione Serviço, Profissional e Data para carregar os horários disponíveis.
                    </div>
                </div>

                <div id="slot-feedback" class="mt-2 text-xs font-semibold text-teal-700"></div>
            </div>
        </div>

        <!-- 4. Status e Observações -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
            <h3 class="font-bold text-slate-900 text-base border-b border-slate-100 pb-3">4. Finalização</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="status" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Status Inicial *</label>
                    <select id="status" name="status" class="text-sm">
                        <option value="confirmado" <?= $status === 'confirmado' ? 'selected' : '' ?>>Confirmado</option>
                        <option value="pendente" <?= $status === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    </select>
                </div>

                <div>
                    <label for="observacao" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Observações Internas (Opcional)</label>
                    <input type="text" id="observacao" name="observacao" value="<?= sanitize($observacao) ?>" placeholder="Ex: Paciente com encaminhamento, primeira consulta..." class="text-sm">
                </div>
            </div>
        </div>

        <!-- Submissão -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="/admin/agendamentos/index.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" id="btn-submit" class="btn btn-primary shadow-md">Confirmar e Salvar Agendamento</button>
        </div>

    </form>

</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const tipoExistente = document.getElementById('tipo-existente');
        const tipoNovo = document.getElementById('tipo-novo');
        const secaoExistente = document.getElementById('secao-cliente-existente');
        const secaoNovo = document.getElementById('secao-cliente-novo');

        function toggleClienteTipo() {
            if (tipoNovo.checked) {
                secaoExistente.classList.add('hidden');
                secaoNovo.classList.remove('hidden');
            } else {
                secaoExistente.classList.remove('hidden');
                secaoNovo.classList.add('hidden');
            }
        }

        tipoExistente?.addEventListener('change', toggleClienteTipo);
        tipoNovo?.addEventListener('change', toggleClienteTipo);

        // Carregamento dinâmico de horários
        const servicoSelect = document.getElementById('servico_id');
        const profSelect = document.getElementById('profissional_id');
        const dataInput = document.getElementById('data');
        const btnConsultar = document.getElementById('btn-consultar-horarios');
        const slotsGrid = document.getElementById('slots-grid');
        const horaInput = document.getElementById('hora_inicio_selecionada');
        const slotFeedback = document.getElementById('slot-feedback');

        async function carregarHorarios() {
            const servicoId = servicoSelect.value;
            const profId = profSelect.value;
            const dataVal = dataInput.value;

            if (!servicoId || !profId || !dataVal) {
                slotsGrid.innerHTML = '<div class="col-span-full text-xs text-amber-700 p-3 bg-amber-50 rounded-xl border border-amber-200 text-center">Por favor, selecione Serviço, Profissional e uma Data válida para listar os horários.</div>';
                horaInput.value = '';
                slotFeedback.textContent = '';
                return;
            }

            slotsGrid.innerHTML = '<div class="col-span-full text-xs text-slate-500 p-3 text-center">Consultando agenda e verificando disponibilidade...</div>';
            horaInput.value = '';
            slotFeedback.textContent = '';

            try {
                const response = await fetch(`/api/horarios-disponiveis.php?servico_id=${servicoId}&profissional_id=${profId}&data=${dataVal}`);
                const data = await response.json();

                if (!data.success || !data.slots || data.slots.length === 0) {
                    slotsGrid.innerHTML = `<div class="col-span-full text-xs text-rose-700 p-3 bg-rose-50 rounded-xl border border-rose-200 text-center">${data.message || 'Nenhum horário disponível para esta combinação/data.'}</div>`;
                    return;
                }

                slotsGrid.innerHTML = '';
                data.slots.forEach(slot => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'time-slot-btn';
                    btn.textContent = slot.hora_inicio;
                    btn.title = `${slot.hora_inicio} até ${slot.hora_fim}`;
                    
                    btn.addEventListener('click', () => {
                        document.querySelectorAll('.time-slot-btn').forEach(b => b.classList.remove('selected'));
                        btn.classList.add('selected');
                        horaInput.value = slot.hora_inicio_completo;
                        slotFeedback.textContent = `Horário selecionado: ${slot.hora_inicio} às ${slot.hora_fim} (${data.duracao} minutos)`;
                    });

                    slotsGrid.appendChild(btn);
                });

            } catch (err) {
                console.error(err);
                slotsGrid.innerHTML = '<div class="col-span-full text-xs text-rose-700 p-3 bg-rose-50 rounded-xl border border-rose-200 text-center">Erro ao consultar disponibilidade. Tente novamente.</div>';
            }
        }

        btnConsultar?.addEventListener('click', carregarHorarios);
        servicoSelect?.addEventListener('change', carregarHorarios);
        profSelect?.addEventListener('change', carregarHorarios);
        dataInput?.addEventListener('change', carregarHorarios);

        // Se já tiver valores pré-selecionados ao abrir
        if (servicoSelect.value && profSelect.value && dataInput.value) {
            carregarHorarios();
        }
    });
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
