<?php
/**
 * AGEND PRO - Endpoint API para Consulta de Horários Disponíveis
 * Implementa o Motor de Disponibilidade e prevenção de conflitos
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Permite requisições via GET ou POST
$profissionalId = (int)($_REQUEST['profissional_id'] ?? 0);
$servicoId      = (int)($_REQUEST['servico_id'] ?? 0);
$data           = trim((string)($_REQUEST['data'] ?? ''));

if ($profissionalId <= 0 || $servicoId <= 0 || empty($data)) {
    jsonResponse([
        'success' => false,
        'message' => 'Parâmetros obrigatórios ausentes: profissional_id, servico_id e data.',
        'slots'   => [],
    ], 400);
}

try {
    $pdo = getDBConnection();
    $resultado = getAvailableSlots($pdo, $profissionalId, $servicoId, $data);

    jsonResponse($resultado, 200);
} catch (Exception $e) {
    error_log("Erro na API de horários disponíveis: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'Erro interno ao consultar horários disponíveis.',
        'slots'   => []
    ], 500);
}
