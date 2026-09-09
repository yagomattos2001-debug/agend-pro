<?php
/**
 * AGEND PRO - Fluxo Público de Agendamento
 * Interface limpa, moderna e responsiva para clientes
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDBConnection();

// Busca serviços ativos
$stmtServ = $pdo->query("SELECT id, nome, descricao, duracao_minutos, preco FROM `servicos` WHERE ativo = 1 ORDER BY nome ASC");
$servicos = $stmtServ->fetchAll();

// Busca profissionais ativos e suas relações com serviços
$stmtProf = $pdo->query("SELECT p.id, p.nome, p.especialidade, ps.servico_id 
                         FROM `profissionais` p 
                         INNER JOIN `profissional_servicos` ps ON p.id = ps.profissional_id 
                         WHERE p.ativo = 1 
                         ORDER BY p.nome ASC");
$profissionaisServicos = $stmtProf->fetchAll();

// Agrupa profissionais por serviço
$profPorServico = [];
foreach ($profissionaisServicos as $ps) {
    $sId = (int)$ps['servico_id'];
    if (!isset($profPorServico[$sId])) {
        $profPorServico[$sId] = [];
    }
    $profPorServico[$sId][] = [
        'id'            => (int)$ps['id'],
        'nome'          => $ps['nome'],
        'especialidade' => $ps['especialidade'],
    ];
}

$erro = '';
$servicoId = (int)($_POST['servico_id'] ?? 0);
$profissionalId = (int)($_POST['profissional_id'] ?? 0);
$data = trim($_POST['data'] ?? date('Y-m-d'));
$horaInicio = trim($_POST['hora_inicio'] ?? '');
$nomeCliente = trim($_POST['cliente_nome'] ?? '');
$telefoneCliente = trim($_POST['cliente_telefone'] ?? '');
$emailCliente = trim($_POST['cliente_email'] ?? '');
$observacao = trim($_POST['observacao'] ?? '');

// Processamento da reserva
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($servicoId <= 0) {
        $erro = 'Selecione o serviço desejado.';
    } elseif ($profissionalId <= 0) {
        $erro = 'Selecione o profissional de atendimento.';
    } elseif (empty($data) || empty($horaInicio)) {
        $erro = 'Escolha uma data e um horário disponível.';
    } elseif (empty($nomeCliente) || empty($telefoneCliente)) {
        $erro = 'Informe seu nome e telefone para contato.';
    } elseif (!empty($emailCliente) && !filter_var($emailCliente, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Formato de e-mail inválido.';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Busca ou cadastra o cliente (reutiliza por telefone)
            $stmtBuscaC = $pdo->prepare("SELECT id FROM `clientes` WHERE telefone = :tel LIMIT 1");
            $stmtBuscaC->execute([':tel' => $telefoneCliente]);
            $clienteExistente = $stmtBuscaC->fetch();

            if ($clienteExistente) {
                $clienteId = (int)$clienteExistente['id'];
                // Atualiza nome e email se fornecido
                $stmtUpC = $pdo->prepare("UPDATE `clientes` SET nome = :nome, email = COALESCE(:email, email), updated_at = NOW() WHERE id = :id");
                $stmtUpC->execute([
                    ':nome'  => $nomeCliente,
                    ':email' => $emailCliente ?: null,
                    ':id'    => $clienteId
                ]);
            } else {
                $stmtInsC = $pdo->prepare("INSERT INTO `clientes` (nome, email, telefone) VALUES (:nome, :email, :telefone)");
                $stmtInsC->execute([
                    ':nome'     => $nomeCliente,
                    ':email'    => $emailCliente ?: null,
                    ':telefone' => $telefoneCliente,
                ]);
                $clienteId = (int)$pdo->lastInsertId();
            }

            // 2. Busca duração do serviço
            $stmtS = $pdo->prepare("SELECT duracao_minutos FROM `servicos` WHERE id = :id AND ativo = 1");
            $stmtS->execute([':id' => $servicoId]);
            $sData = $stmtS->fetch();

            if (!$sData) {
                throw new Exception('Serviço indisponível ou inativo no momento.');
            }

            $duracao = (int)$sData['duracao_minutos'];
            $horaInicioFmt = strlen($horaInicio) === 5 ? $horaInicio . ':00' : $horaInicio;
            $horaFimFmt = calculateEndTime($horaInicioFmt, $duracao);

            // 3. Validação final de integridade e concorrência no banco
            $temConflito = checkAppointmentConflict($pdo, $profissionalId, $data, $horaInicioFmt, $horaFimFmt);
            if ($temConflito) {
                throw new Exception('Desculpe, este horário acabou de ser reservado por outro cliente. Por favor, selecione outro horário.');
            }

            // 4. Insere agendamento com status inicial 'pendente'
            $stmtAg = $pdo->prepare("INSERT INTO `agendamentos` (cliente_id, profissional_id, servico_id, data, hora_inicio, hora_fim, status, observacao)
                                     VALUES (:cliente_id, :prof_id, :serv_id, :data, :inicio, :fim, 'pendente', :obs)");
            $stmtAg->execute([
                ':cliente_id' => $clienteId,
                ':prof_id'    => $profissionalId,
                ':serv_id'    => $servicoId,
                ':data'       => $data,
                ':inicio'     => $horaInicioFmt,
                ':fim'        => $horaFimFmt,
                ':obs'        => $observacao ?: null,
            ]);
            $agendamentoId = (int)$pdo->lastInsertId();

            $pdo->commit();

            header('Location: /public/confirmacao.php?id=' . $agendamentoId);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendamento Online - AGEND PRO</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col antialiased">

    <!-- Top Navigation Header -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <a href="/public/index.php" class="flex items-center gap-3 text-slate-900 font-bold tracking-tight">
                <div class="w-8 h-8 rounded-lg bg-teal-600 flex items-center justify-center font-extrabold text-white text-sm shadow-sm">
                    AP
                </div>
                <div class="leading-none">
                    <span class="text-base text-slate-900">AGEND <span class="text-teal-600">PRO</span></span>
                    <span class="block text-[10px] text-slate-400 font-semibold tracking-wider uppercase mt-0.5">Agendamento Online</span>
                </div>
            </a>
            <div class="flex items-center gap-3">
                <a href="/login.php" class="text-xs font-semibold text-slate-600 hover:text-teal-600 flex items-center gap-1.5 transition-colors">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                    <span>Área Administrativa</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content Flow -->
    <main class="flex-1 py-8 px-4 sm:px-6">
        <div class="max-w-4xl mx-auto space-y-6">

            <!-- Banner Informativo -->
            <div class="bg-slate-900 text-white rounded-3xl p-6 sm:p-8 shadow-xl relative overflow-hidden">
                <div class="relative z-10 max-w-xl">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-teal-500/20 text-teal-300 border border-teal-500/30 mb-3">
                        <span class="w-2 h-2 rounded-full bg-teal-400 animate-pulse"></span>
                        Horários em tempo real
                    </span>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Agende seu Atendimento</h1>
                    <p class="text-slate-300 text-sm mt-2 leading-relaxed">
                        Escolha o serviço desejado, selecione o profissional de sua preferência e marque um horário conveniente sem complicações.
                    </p>
                </div>
            </div>

            <?php if (!empty($erro)): ?>
                <div class="p-4 rounded-xl text-sm bg-rose-50 border border-rose-200 text-rose-800 flex items-center gap-2.5 shadow-sm">
                    <svg class="w-5 h-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span><?= sanitize($erro) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="/public/index.php" id="form-public-booking" class="space-y-6">
                
                <!-- ETAPA 1: ESCOLHA DO SERVIÇO -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
                    <div class="flex items-center gap-3 border-b border-slate-100 pb-3">
                        <div class="w-7 h-7 rounded-full bg-teal-50 text-teal-700 font-bold text-xs flex items-center justify-center border border-teal-200">
                            1
                        </div>
                        <div>
                            <h2 class="font-bold text-slate-900 text-base">Selecione o Serviço</h2>
                            <p class="text-xs text-slate-500">Escolha o tipo de procedimento que deseja realizar</p>
                        </div>
                    </div>

                    <input type="hidden" name="servico_id" id="servico_id" value="<?= $servicoId ?>" required>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" id="services-grid">
                        <?php foreach ($servicos as $s): ?>
                            <div class="service-card p-4 rounded-xl border border-slate-200 hover:border-teal-500 transition-all cursor-pointer <?= $servicoId === (int)$s['id'] ? 'border-teal-600 bg-teal-50/20 ring-2 ring-teal-500/20' : '' ?>"
                                 data-id="<?= $s['id'] ?>"
                                 data-nome="<?= sanitize($s['nome']) ?>"
                                 data-duracao="<?= $s['duracao_minutos'] ?>"
                                 data-preco="<?= $s['preco'] ?>">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="font-bold text-slate-900 text-sm"><?= sanitize($s['nome']) ?></h3>
                                    <span class="text-sm font-bold text-teal-700 whitespace-nowrap"><?= formatMoney((float)$s['preco']) ?></span>
                                </div>
                                <p class="text-xs text-slate-500 mt-1.5 line-clamp-2"><?= sanitize($s['descricao'] ?: 'Atendimento com horário reservado.') ?></p>
                                <div class="flex items-center gap-2 text-[11px] font-semibold text-slate-400 mt-3 pt-2 border-t border-slate-100">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span><?= $s['duracao_minutos'] ?> minutos de atendimento</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ETAPA 2: ESCOLHA DO PROFISSIONAL -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
                    <div class="flex items-center gap-3 border-b border-slate-100 pb-3">
                        <div class="w-7 h-7 rounded-full bg-teal-50 text-teal-700 font-bold text-xs flex items-center justify-center border border-teal-200">
                            2
                        </div>
                        <div>
                            <h2 class="font-bold text-slate-900 text-base">Selecione o Profissional</h2>
                            <p class="text-xs text-slate-500">Filtrado automaticamente conforme o serviço selecionado</p>
                        </div>
                    </div>

                    <input type="hidden" name="profissional_id" id="profissional_id" value="<?= $profissionalId ?>" required>

                    <div id="profissionais-container" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="col-span-full text-xs text-slate-400 p-4 bg-slate-50 rounded-xl border border-dashed border-slate-200 text-center">
                            Selecione primeiro um serviço acima para visualizar os especialistas habilitados.
                        </div>
                    </div>
                </div>

                <!-- ETAPA 3: ESCOLHA DA DATA E HORÁRIO DISPONÍVEL -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
                    <div class="flex items-center gap-3 border-b border-slate-100 pb-3">
                        <div class="w-7 h-7 rounded-full bg-teal-50 text-teal-700 font-bold text-xs flex items-center justify-center border border-teal-200">
                            3
                        </div>
                        <div>
                            <h2 class="font-bold text-slate-900 text-base">Data & Horário</h2>
                            <p class="text-xs text-slate-500">Apenas horários sem conflito de agenda serão exibidos</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                        <div>
                            <label for="data" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Data Desejada</label>
                            <input type="date" id="data" name="data" value="<?= sanitize($data) ?>" min="<?= date('Y-m-d') ?>" class="text-sm font-semibold">
                        </div>
                        <div class="sm:col-span-2">
                            <span class="text-xs text-slate-500">Os horários são carregados dinamicamente com base na escala de atendimento do profissional.</span>
                        </div>
                    </div>

                    <input type="hidden" name="hora_inicio" id="hora_inicio" value="<?= sanitize($horaInicio) ?>" required>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Horários Disponíveis</label>
                        <div id="slots-grid" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2 min-h-[50px] items-center">
                            <div class="col-span-full text-xs text-slate-400 p-4 bg-slate-50 rounded-xl border border-dashed border-slate-200 text-center">
                                Selecione serviço, profissional e data para consultar os horários.
                            </div>
                        </div>
                        <div id="slot-feedback" class="mt-2 text-xs font-semibold text-teal-700"></div>
                    </div>
                </div>

                <!-- ETAPA 4: DADOS DO CLIENTE -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
                    <div class="flex items-center gap-3 border-b border-slate-100 pb-3">
                        <div class="w-7 h-7 rounded-full bg-teal-50 text-teal-700 font-bold text-xs flex items-center justify-center border border-teal-200">
                            4
                        </div>
                        <div>
                            <h2 class="font-bold text-slate-900 text-base">Seus Dados de Contato</h2>
                            <p class="text-xs text-slate-500">Usaremos estes dados para identificar e confirmar sua reserva</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label for="cliente_nome" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Nome Completo *</label>
                            <input type="text" id="cliente_nome" name="cliente_nome" value="<?= sanitize($nomeCliente) ?>" required placeholder="Ex: Lucas Ribeiro" class="text-sm">
                        </div>

                        <div>
                            <label for="cliente_telefone" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Telefone / WhatsApp *</label>
                            <input type="tel" id="cliente_telefone" name="cliente_telefone" value="<?= sanitize($telefoneCliente) ?>" required placeholder="(11) 98765-4321" class="text-sm">
                        </div>

                        <div>
                            <label for="cliente_email" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">E-mail (Opcional)</label>
                            <input type="email" id="cliente_email" name="cliente_email" value="<?= sanitize($emailCliente) ?>" placeholder="lucas@exemplo.com" class="text-sm">
                        </div>

                        <div class="sm:col-span-3">
                            <label for="observacao" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Observações ou Necessidades Especiais (Opcional)</label>
                            <textarea id="observacao" name="observacao" rows="2" placeholder="Alguma observação relevante para o profissional antes da consulta..." class="text-sm"><?= sanitize($observacao) ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- ETAPA 5: RESUMO & CONFIRMAÇÃO -->
                <div class="bg-slate-900 text-white p-6 rounded-2xl border border-slate-800 shadow-xl space-y-4">
                    <h3 class="font-bold text-base text-white border-b border-slate-800 pb-3">Resumo da Reserva</h3>
                    
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                        <div>
                            <span class="text-slate-400 block uppercase">Serviço</span>
                            <span class="font-bold text-slate-100 text-sm mt-0.5 block" id="resumo-servico">-</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block uppercase">Profissional</span>
                            <span class="font-bold text-slate-100 text-sm mt-0.5 block" id="resumo-prof">-</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block uppercase">Data & Horário</span>
                            <span class="font-bold text-teal-400 text-sm mt-0.5 block" id="resumo-horario">-</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block uppercase">Valor Total</span>
                            <span class="font-bold text-slate-100 text-sm mt-0.5 block" id="resumo-preco">-</span>
                        </div>
                    </div>

                    <div class="pt-2 flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-slate-800">
                        <div class="text-xs text-slate-400">
                            Ao confirmar, sua solicitação será registrada com segurança no sistema.
                        </div>
                        <button type="submit" id="btn-finalizar" class="w-full sm:w-auto bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold py-3 px-8 rounded-xl shadow-lg shadow-teal-500/20 hover:shadow-teal-500/30 transition-all text-sm cursor-pointer">
                            Confirmar Agendamento
                        </button>
                    </div>
                </div>

            </form>

        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-500 mt-12">
        <div class="max-w-4xl mx-auto px-4">
            <span class="font-semibold text-slate-700">AGEND PRO</span> &bull; Sistema de Gestão de Agendamentos &bull; Desenvolvido em PHP 8 + MySQL PDO
        </div>
    </footer>

    <script>
        // Mapeamento dos profissionais por serviço injetado pelo PHP
        const profPorServicoMap = <?= json_encode($profPorServico, JSON_UNESCAPED_UNICODE) ?>;

        document.addEventListener('DOMContentLoaded', () => {
            const servicoInput = document.getElementById('servico_id');
            const profInput = document.getElementById('profissional_id');
            const dataInput = document.getElementById('data');
            const horaInput = document.getElementById('hora_inicio');
            const serviceCards = document.querySelectorAll('.service-card');
            const profsContainer = document.getElementById('profissionais-container');
            const slotsGrid = document.getElementById('slots-grid');
            const slotFeedback = document.getElementById('slot-feedback');

            // Resumo
            const resumoServico = document.getElementById('resumo-servico');
            const resumoProf = document.getElementById('resumo-prof');
            const resumoHorario = document.getElementById('resumo-horario');
            const resumoPreco = document.getElementById('resumo-preco');

            let currentServicoNome = '';
            let currentServicoPreco = '';
            let currentProfNome = '';
            let currentHoraInicio = '';

            function updateResumo() {
                resumoServico.textContent = currentServicoNome || '-';
                resumoProf.textContent = currentProfNome || '-';
                resumoPreco.textContent = currentServicoPreco ? `R$ ${parseFloat(currentServicoPreco).toFixed(2).replace('.', ',')}` : '-';
                
                if (dataInput.value && currentHoraInicio) {
                    const partes = dataInput.value.split('-');
                    const dataFormatada = `${partes[2]}/${partes[1]}/${partes[0]}`;
                    resumoHorario.textContent = `${dataFormatada} às ${currentHoraInicio.substr(0, 5)}`;
                } else {
                    resumoHorario.textContent = '-';
                }
            }

            // Seleção de Serviço
            serviceCards.forEach(card => {
                card.addEventListener('click', () => {
                    serviceCards.forEach(c => c.classList.remove('border-teal-600', 'bg-teal-50/20', 'ring-2', 'ring-teal-500/20'));
                    card.classList.add('border-teal-600', 'bg-teal-50/20', 'ring-2', 'ring-teal-500/20');
                    
                    const servId = card.getAttribute('data-id');
                    servicoInput.value = servId;
                    currentServicoNome = card.getAttribute('data-nome');
                    currentServicoPreco = card.getAttribute('data-preco');
                    
                    // Reseta profissional e horário
                    profInput.value = '';
                    horaInput.value = '';
                    currentProfNome = '';
                    currentHoraInicio = '';
                    slotFeedback.textContent = '';
                    slotsGrid.innerHTML = '<div class="col-span-full text-xs text-slate-400 p-4 bg-slate-50 rounded-xl border border-dashed border-slate-200 text-center">Selecione o profissional abaixo para buscar os horários.</div>';

                    carregarProfissionaisParaServico(servId);
                    updateResumo();
                });
            });

            function carregarProfissionaisParaServico(servId) {
                const lista = profPorServicoMap[servId] || [];
                if (lista.length === 0) {
                    profsContainer.innerHTML = '<div class="col-span-full text-xs text-amber-700 p-3 bg-amber-50 rounded-xl border border-amber-200 text-center">Nenhum profissional disponível para este serviço no momento.</div>';
                    return;
                }

                profsContainer.innerHTML = '';
                lista.forEach(p => {
                    const card = document.createElement('div');
                    card.className = 'prof-card p-3 rounded-xl border border-slate-200 hover:border-teal-500 transition-all cursor-pointer';
                    card.innerHTML = `
                        <div class="font-bold text-slate-900 text-sm">${p.nome}</div>
                        <div class="text-xs text-slate-500 mt-0.5">${p.especialidade}</div>
                    `;

                    card.addEventListener('click', () => {
                        document.querySelectorAll('.prof-card').forEach(c => c.classList.remove('border-teal-600', 'bg-teal-50/20', 'ring-2', 'ring-teal-500/20'));
                        card.classList.add('border-teal-600', 'bg-teal-50/20', 'ring-2', 'ring-teal-500/20');
                        
                        profInput.value = p.id;
                        currentProfNome = p.nome;
                        updateResumo();
                        carregarHorarios();
                    });

                    profsContainer.appendChild(card);
                });
            }

            async function carregarHorarios() {
                const sId = servicoInput.value;
                const pId = profInput.value;
                const dataVal = dataInput.value;

                if (!sId || !pId || !dataVal) {
                    return;
                }

                slotsGrid.innerHTML = '<div class="col-span-full text-xs text-slate-500 p-4 text-center">Consultando horários disponíveis...</div>';
                horaInput.value = '';
                currentHoraInicio = '';
                slotFeedback.textContent = '';
                updateResumo();

                try {
                    const res = await fetch(`/api/horarios-disponiveis.php?servico_id=${sId}&profissional_id=${pId}&data=${dataVal}`);
                    const json = await res.json();

                    if (!json.success || !json.slots || json.slots.length === 0) {
                        slotsGrid.innerHTML = `<div class="col-span-full text-xs text-amber-800 p-3 bg-amber-50 rounded-xl border border-amber-200 text-center">${json.message || 'Nenhum horário disponível para esta data.'}</div>`;
                        return;
                    }

                    slotsGrid.innerHTML = '';
                    json.slots.forEach(slot => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'time-slot-btn';
                        btn.textContent = slot.hora_inicio;
                        btn.title = `De ${slot.hora_inicio} às ${slot.hora_fim}`;

                        btn.addEventListener('click', () => {
                            document.querySelectorAll('.time-slot-btn').forEach(b => b.classList.remove('selected'));
                            btn.classList.add('selected');
                            horaInput.value = slot.hora_inicio_completo;
                            currentHoraInicio = slot.hora_inicio;
                            slotFeedback.textContent = `Horário escolhido: ${slot.hora_inicio} às ${slot.hora_fim} (${json.duracao} min)`;
                            updateResumo();
                        });

                        slotsGrid.appendChild(btn);
                    });

                } catch (err) {
                    console.error(err);
                    slotsGrid.innerHTML = '<div class="col-span-full text-xs text-rose-700 p-3 bg-rose-50 rounded-xl border border-rose-200 text-center">Erro ao buscar horários. Tente novamente.</div>';
                }
            }

            dataInput?.addEventListener('change', () => {
                if (servicoInput.value && profInput.value) {
                    carregarHorarios();
                }
            });

            // Se já tiver serviço pré-selecionado (e.g. após erro no POST)
            if (servicoInput.value) {
                const card = document.querySelector(`.service-card[data-id="${servicoInput.value}"]`);
                if (card) card.click();
            }
        });
    </script>
</body>
</html>
