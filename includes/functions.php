<?php
/**
 * AGEND PRO - Funções Auxiliares e Regras Centrais de Negócio
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Sanitiza dados de saída para proteção contra XSS.
 */
function sanitize(?string $value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

/**
 * Formata data de YYYY-MM-DD para DD/MM/YYYY.
 */
function formatDate(?string $date): string
{
    if (!$date) {
        return '-';
    }
    $timestamp = strtotime($date);
    return $timestamp ? date('d/m/Y', $timestamp) : $date;
}

/**
 * Formata horário de HH:MM:SS para HH:MM.
 */
function formatTime(?string $time): string
{
    if (!$time) {
        return '-';
    }
    return substr($time, 0, 5);
}

/**
 * Formata valores monetários em Real (R$).
 */
function formatMoney(float $amount): string
{
    return 'R$ ' . number_format($amount, 2, ',', '.');
}

/**
 * Retorna o nome do dia da semana (0=Domingo, 6=Sábado).
 */
function getDayOfWeekName(int $day): string
{
    $days = [
        0 => 'Domingo',
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado'
    ];
    return $days[$day] ?? 'Desconhecido';
}

/**
 * Retorna o badge HTML estilizado para o status do agendamento.
 */
function getStatusBadge(string $status): string
{
    $badges = [
        'pendente'   => '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-300">Pendente</span>',
        'confirmado' => '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 border border-blue-300">Confirmado</span>',
        'concluido'  => '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-300">Concluído</span>',
        'cancelado'  => '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 border border-rose-300">Cancelado</span>',
    ];

    return $badges[$status] ?? '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">' . sanitize($status) . '</span>';
}

/**
 * Define mensagem flash na sessão.
 */
function setFlashMessage(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'error', 'warning', 'info'
        'message' => $message,
    ];
}

/**
 * Recupera e consome mensagem flash da sessão.
 */
function getFlashMessage(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Calcula o horário de término com base no horário de início e na duração em minutos.
 */
function calculateEndTime(string $horaInicio, int $duracaoMinutos): string
{
    $timestamp = strtotime("1970-01-01 " . $horaInicio);
    if ($timestamp === false) {
        throw new InvalidArgumentException("Horário de início inválido.");
    }
    $endTimestamp = $timestamp + ($duracaoMinutos * 60);
    return date('H:i:s', $endTimestamp);
}

/**
 * Verifica se há conflito de agendamento para o profissional na data e intervalo especificados.
 * O agendamento é conflitante se:
 * novo_inicio < agendamento_fim E novo_fim > agendamento_inicio
 * Considerando apenas status 'pendente' e 'confirmado'.
 * 
 * @return bool true se houver conflito, false se o horário estiver livre.
 */
function checkAppointmentConflict(
    PDO $pdo,
    int $profissionalId,
    string $data,
    string $horaInicio,
    string $horaFim,
    ?int $excludeAgendamentoId = null
): bool {
    $sql = "SELECT COUNT(*) FROM `agendamentos`
            WHERE `profissional_id` = :profissional_id
              AND `data` = :data
              AND `status` IN ('pendente', 'confirmado')
              AND (:hora_inicio < `hora_fim` AND :hora_fim > `hora_inicio`)";

    if ($excludeAgendamentoId !== null) {
        $sql .= " AND `id` != :exclude_id";
    }

    $stmt = $pdo->prepare($sql);
    $params = [
        ':profissional_id' => $profissionalId,
        ':data'            => $data,
        ':hora_inicio'     => $horaInicio,
        ':hora_fim'        => $horaFim,
    ];

    if ($excludeAgendamentoId !== null) {
        $params[':exclude_id'] = $excludeAgendamentoId;
    }

    $stmt->execute($params);
    $count = (int)$stmt->fetchColumn();

    return $count > 0;
}

/**
 * MOTOR DE DISPONIBILIDADE
 * Implementa rigorosamente as 9 regras de negócio descritas na Seção 12 e 13:
 * 1. Verifica se o profissional está ativo
 * 2. Verifica se o serviço está ativo
 * 3. Verifica se o profissional realiza aquele serviço (tabela profissional_servicos)
 * 4. Determina o dia da semana da data escolhida (0=Dom a 6=Sáb)
 * 5. Verifica se o profissional trabalha naquele dia e se o expediente está ativo
 * 6. Identifica o horário de expediente (hora_inicio até hora_fim)
 * 7. Considera a duração do serviço
 * 8. Busca agendamentos ocupados (pendente / confirmado)
 * 9. Retorna apenas horários válidos e sem conflitos, que caibam dentro do expediente.
 * 
 * @return array Lista de horários disponíveis no formato ['hora_inicio' => '09:00', 'hora_fim' => '10:00']
 */
function getAvailableSlots(PDO $pdo, int $profissionalId, int $servicoId, string $data): array
{
    // Validação básica da data (formato YYYY-MM-DD)
    $d = DateTime::createFromFormat('Y-m-d', $data);
    if (!$d || $d->format('Y-m-d') !== $data) {
        return ['success' => false, 'message' => 'Data em formato inválido.', 'slots' => []];
    }

    // 1. Verifica se profissional está ativo
    $stmtProf = $pdo->prepare("SELECT id, nome, ativo FROM `profissionais` WHERE id = :id LIMIT 1");
    $stmtProf->execute([':id' => $profissionalId]);
    $prof = $stmtProf->fetch();
    if (!$prof || (int)$prof['ativo'] !== 1) {
        return ['success' => false, 'message' => 'Profissional inativo ou não encontrado.', 'slots' => []];
    }

    // 2. Verifica se serviço está ativo
    $stmtServ = $pdo->prepare("SELECT id, nome, duracao_minutos, preco, ativo FROM `servicos` WHERE id = :id LIMIT 1");
    $stmtServ->execute([':id' => $servicoId]);
    $servico = $stmtServ->fetch();
    if (!$servico || (int)$servico['ativo'] !== 1) {
        return ['success' => false, 'message' => 'Serviço inativo ou não encontrado.', 'slots' => []];
    }

    // 3. Verifica se o profissional realiza o serviço
    $stmtRel = $pdo->prepare("SELECT COUNT(*) FROM `profissional_servicos` WHERE profissional_id = :prof_id AND servico_id = :serv_id");
    $stmtRel->execute([':prof_id' => $profissionalId, ':serv_id' => $servicoId]);
    if ((int)$stmtRel->fetchColumn() === 0) {
        return ['success' => false, 'message' => 'Este serviço não é realizado por este profissional.', 'slots' => []];
    }

    // 4. Dia da semana (0=Domingo, 6=Sábado)
    $diaSemana = (int)date('w', strtotime($data));

    // 5 e 6. Expediente de trabalho
    $stmtHorario = $pdo->prepare("SELECT hora_inicio, hora_fim, ativo FROM `horarios_trabalho` WHERE profissional_id = :prof_id AND dia_semana = :dia LIMIT 1");
    $stmtHorario->execute([':prof_id' => $profissionalId, ':dia' => $diaSemana]);
    $horarioTrabalho = $stmtHorario->fetch();

    if (!$horarioTrabalho || (int)$horarioTrabalho['ativo'] !== 1) {
        return ['success' => false, 'message' => 'Este profissional não atende neste dia.', 'slots' => []];
    }

    $workStart = $horarioTrabalho['hora_inicio'];
    $workEnd   = $horarioTrabalho['hora_fim'];
    $duracao   = (int)$servico['duracao_minutos'];

    // 8. Buscar agendamentos existentes no dia que ocupam horário (pendente ou confirmado)
    $stmtAg = $pdo->prepare("SELECT hora_inicio, hora_fim FROM `agendamentos`
                             WHERE profissional_id = :prof_id
                               AND data = :data
                               AND status IN ('pendente', 'confirmado')
                             ORDER BY hora_inicio ASC");
    $stmtAg->execute([':prof_id' => $profissionalId, ':data' => $data]);
    $agendamentosOcupados = $stmtAg->fetchAll();

    // 9. Geração dos slots com intervalo padrão (30 minutos)
    $stepMinutes = 30;
    if ($duracao < 30) {
        $stepMinutes = $duracao;
    }

    $slots = [];
    $currentSec = strtotime("1970-01-01 " . $workStart);
    $workEndSec = strtotime("1970-01-01 " . $workEnd);
    $duracaoSec = $duracao * 60;
    $stepSec    = $stepMinutes * 60;

    $isToday = ($data === date('Y-m-d'));
    $nowMinutes = (int)date('H') * 60 + (int)date('i');

    while (($currentSec + $duracaoSec) <= $workEndSec) {
        $slotStartStr = date('H:i:s', $currentSec);
        $slotEndStr   = date('H:i:s', $currentSec + $duracaoSec);

        // Se a data for hoje, não permite horários passados
        if ($isToday) {
            $slotStartMinutes = (int)date('H', $currentSec) * 60 + (int)date('i', $currentSec);
            if ($slotStartMinutes <= $nowMinutes + 10) {
                $currentSec += $stepSec;
                continue;
            }
        }

        // Verifica conflito contra todos os agendamentos ocupados do dia
        $hasConflict = false;
        foreach ($agendamentosOcupados as $ag) {
            $agStart = $ag['hora_inicio'];
            $agEnd   = $ag['hora_fim'];

            // Regra central de conflito: novo_inicio < ag_fim E novo_fim > ag_inicio
            if ($slotStartStr < $agEnd && $slotEndStr > $agStart) {
                $hasConflict = true;
                break;
            }
        }

        if (!$hasConflict) {
            $slots[] = [
                'hora_inicio' => substr($slotStartStr, 0, 5),
                'hora_fim'    => substr($slotEndStr, 0, 5),
                'hora_inicio_completo' => $slotStartStr,
                'hora_fim_completo'    => $slotEndStr,
            ];
        }

        $currentSec += $stepSec;
    }

    return [
        'success'      => true,
        'message'      => count($slots) > 0 ? 'Horários disponíveis carregados com sucesso.' : 'Nenhum horário disponível para esta data.',
        'slots'        => $slots,
        'expediente'   => [
            'inicio' => substr($workStart, 0, 5),
            'fim'    => substr($workEnd, 0, 5),
        ],
        'duracao'      => $duracao,
        'preco'        => (float)$servico['preco'],
        'servico_nome' => $servico['nome'],
        'prof_nome'    => $prof['nome'],
    ];
}

/**
 * Resposta JSON padronizada.
 */
function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
