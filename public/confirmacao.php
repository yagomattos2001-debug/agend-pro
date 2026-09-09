<?php
/**
 * AGEND PRO - Confirmação do Agendamento (Comprovante do Cliente)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /public/index.php');
    exit;
}

$pdo = getDBConnection();

$sql = "SELECT 
            a.*,
            c.nome AS cliente_nome,
            c.telefone AS cliente_telefone,
            c.email AS cliente_email,
            p.nome AS profissional_nome,
            p.especialidade AS profissional_especialidade,
            p.email AS profissional_email,
            p.telefone AS profissional_telefone,
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
    header('Location: /public/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprovante de Agendamento #<?= $ag['id'] ?> - AGEND PRO</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        @media print {
            body { background: #fff !important; }
            header, footer, .no-print { display: none !important; }
            .print-container { border: 1px solid #e2e8f0 !important; box-shadow: none !important; }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col antialiased">

    <!-- Top Navigation -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs no-print">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <a href="/public/index.php" class="flex items-center gap-3 text-slate-900 font-bold tracking-tight">
                <div class="w-8 h-8 rounded-lg bg-teal-600 flex items-center justify-center font-extrabold text-white text-sm shadow-sm">
                    AP
                </div>
                <span>AGEND <span class="text-teal-600 font-bold">PRO</span></span>
            </a>
            <div class="flex items-center gap-3">
                <a href="/public/index.php" class="btn btn-secondary btn-sm">
                    Novo Agendamento
                </a>
            </div>
        </div>
    </header>

    <main class="flex-1 py-8 px-4 sm:px-6 flex items-center justify-center">
        <div class="w-full max-w-2xl bg-white border border-slate-200/80 rounded-3xl p-6 sm:p-10 shadow-xl print-container space-y-6">
            
            <!-- Success Badge & Protocol -->
            <div class="text-center space-y-2 border-b border-slate-100 pb-6">
                <div class="w-16 h-16 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto border-2 border-emerald-200 shadow-sm">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Agendamento Realizado com Sucesso!</h1>
                <p class="text-sm text-slate-500">
                    Sua solicitação foi registrada no sistema. Guarde o número do seu comprovante.
                </p>
                <div class="inline-block mt-2 px-4 py-1.5 rounded-full bg-slate-100 text-slate-700 text-xs font-mono font-bold tracking-wider">
                    PROTOCOLO #<?= str_pad((string)$ag['id'], 6, '0', STR_PAD_LEFT) ?>
                </div>
            </div>

            <!-- Detalhes do Atendimento -->
            <div class="space-y-4">
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Resumo do Atendimento</h2>

                <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200/70 space-y-3">
                    
                    <div class="flex items-center justify-between border-b border-slate-200/60 pb-3">
                        <div>
                            <span class="text-xs text-slate-400 block">Serviço Solicitado</span>
                            <span class="font-bold text-slate-900 text-base"><?= sanitize($ag['servico_nome']) ?></span>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-slate-400 block">Valor</span>
                            <span class="font-bold text-teal-700 text-base"><?= formatMoney((float)$ag['preco']) ?></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 text-sm">
                        <div>
                            <span class="text-xs text-slate-400 block">Data & Horário</span>
                            <div class="font-bold text-slate-900 mt-0.5">
                                <?= formatDate($ag['data']) ?> (<?= getDayOfWeekName((int)date('w', strtotime($ag['data']))) ?>)
                            </div>
                            <div class="text-xs font-semibold text-teal-700">
                                <?= formatTime($ag['hora_inicio']) ?> às <?= formatTime($ag['hora_fim']) ?> (<?= $ag['duracao_minutos'] ?> min)
                            </div>
                        </div>

                        <div>
                            <span class="text-xs text-slate-400 block">Profissional</span>
                            <div class="font-bold text-slate-900 mt-0.5"><?= sanitize($ag['profissional_nome']) ?></div>
                            <div class="text-xs text-slate-500"><?= sanitize($ag['profissional_especialidade']) ?></div>
                        </div>
                    </div>

                </div>

                <!-- Dados do Paciente -->
                <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200/70 space-y-1 text-sm">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-2">Dados do Paciente / Cliente</span>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Nome:</span>
                        <span class="font-bold text-slate-900"><?= sanitize($ag['cliente_nome']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Telefone:</span>
                        <span class="font-medium text-slate-800"><?= sanitize($ag['cliente_telefone']) ?></span>
                    </div>
                    <?php if ($ag['cliente_email']): ?>
                        <div class="flex justify-between">
                            <span class="text-slate-500">E-mail:</span>
                            <span class="font-medium text-slate-800"><?= sanitize($ag['cliente_email']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="flex justify-between pt-2 border-t border-slate-200/60">
                        <span class="text-slate-500">Status atual:</span>
                        <div><?= getStatusBadge($ag['status']) ?></div>
                    </div>
                </div>

                <!-- Orientações -->
                <div class="p-4 rounded-xl bg-teal-50 border border-teal-200 text-teal-900 text-xs space-y-1.5">
                    <div class="font-bold flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-teal-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>Orientações importantes para seu atendimento:</span>
                    </div>
                    <ul class="list-disc list-inside space-y-1 text-teal-800 ml-1">
                        <li>Recomendamos chegar com <strong>10 minutos de antecedência</strong> ao horário marcado.</li>
                        <li>Caso necessite cancelar ou reagendar, entre em contato com a equipe com antecedência.</li>
                        <li>Traga um documento de identificação com foto.</li>
                    </ul>
                </div>
            </div>

            <!-- Botões de Ação -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-4 border-t border-slate-100 no-print">
                <a href="/public/index.php" class="btn btn-secondary w-full sm:w-auto text-sm">
                    &larr; Realizar Outro Agendamento
                </a>
                <button type="button" onclick="window.print()" class="btn btn-primary w-full sm:w-auto text-sm shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                    <span>Imprimir / Salvar Comprovante</span>
                </button>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-500 no-print">
        AGEND PRO &bull; Sistema de Gestão de Agendamentos &bull; PHP 8 + MySQL PDO
    </footer>

</body>
</html>
