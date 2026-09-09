<?php
/**
 * AGEND PRO - Configuração da Grade Semanal de Horários de Trabalho
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pdo = getDBConnection();

$profId = (int)($_GET['profissional_id'] ?? 0);
if ($profId <= 0) {
    setFlashMessage('error', 'Profissional não especificado.');
    header('Location: /admin/horarios/index.php');
    exit;
}

// Busca profissional
$stmt = $pdo->prepare("SELECT * FROM `profissionais` WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $profId]);
$profissional = $stmt->fetch();

if (!$profissional) {
    setFlashMessage('error', 'Profissional não encontrado.');
    header('Location: /admin/horarios/index.php');
    exit;
}

// Busca horários cadastrados
$stmtHorarios = $pdo->prepare("SELECT * FROM `horarios_trabalho` WHERE profissional_id = :id ORDER BY dia_semana ASC");
$stmtHorarios->execute([':id' => $profId]);
$horariosRaw = $stmtHorarios->fetchAll();

// Mapeia por dia da semana (0 a 6)
$grade = [];
for ($d = 0; $d <= 6; $d++) {
    $grade[$d] = [
        'dia_semana'  => $d,
        'hora_inicio' => '08:00',
        'hora_fim'    => '18:00',
        'ativo'       => ($d === 0) ? 0 : 1 // Domingo folga por padrão
    ];
}

foreach ($horariosRaw as $hr) {
    $d = (int)$hr['dia_semana'];
    $grade[$d] = [
        'dia_semana'  => $d,
        'hora_inicio' => substr($hr['hora_inicio'], 0, 5),
        'hora_fim'    => substr($hr['hora_fim'], 0, 5),
        'ativo'       => (int)$hr['ativo'],
    ];
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $diasPost = $_POST['dias'] ?? [];
    $errosValidacao = [];

    // Validação
    for ($d = 0; $d <= 6; $d++) {
        $ativo = isset($diasPost[$d]['ativo']) ? 1 : 0;
        $inicio = trim($diasPost[$d]['hora_inicio'] ?? '08:00');
        $fim = trim($diasPost[$d]['hora_fim'] ?? '18:00');

        $grade[$d]['ativo'] = $ativo;
        $grade[$d]['hora_inicio'] = $inicio;
        $grade[$d]['hora_fim'] = $fim;

        if ($ativo === 1) {
            if (empty($inicio) || empty($fim)) {
                $errosValidacao[] = "Preencha início e fim para " . getDayOfWeekName($d) . ".";
            } elseif ($fim <= $inicio) {
                $errosValidacao[] = "Em " . getDayOfWeekName($d) . ", o horário final ($fim) deve ser posterior ao inicial ($inicio).";
            }
        }
    }

    if (!empty($errosValidacao)) {
        $erro = implode('<br>', $errosValidacao);
    } else {
        try {
            $pdo->beginTransaction();

            $stmtUpsert = $pdo->prepare("INSERT INTO `horarios_trabalho` (profissional_id, dia_semana, hora_inicio, hora_fim, ativo)
                                         VALUES (:prof_id, :dia, :inicio, :fim, :ativo)
                                         ON DUPLICATE KEY UPDATE
                                            hora_inicio = VALUES(hora_inicio),
                                            hora_fim = VALUES(hora_fim),
                                            ativo = VALUES(ativo),
                                            updated_at = NOW()");

            for ($d = 0; $d <= 6; $d++) {
                $stmtUpsert->execute([
                    ':prof_id' => $profId,
                    ':dia'     => $d,
                    ':inicio'  => $grade[$d]['hora_inicio'] . ':00',
                    ':fim'     => $grade[$d]['hora_fim'] . ':00',
                    ':ativo'   => $grade[$d]['ativo'],
                ]);
            }

            $pdo->commit();

            setFlashMessage('success', 'Grade semanal de horários de ' . htmlspecialchars($profissional['nome']) . ' atualizada com sucesso!');
            header('Location: /admin/horarios/index.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erro ao salvar horários de trabalho: " . $e->getMessage());
            $erro = 'Erro ao salvar os horários no banco de dados.';
        }
    }
}

$pageTitle = 'Configurar Horários';
$pageSubtitle = 'Grade semanal de atendimento para ' . $profissional['nome'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Grade de Atendimento: <?= sanitize($profissional['nome']) ?></h2>
            <p class="text-xs text-slate-500 mt-0.5"><?= sanitize($profissional['especialidade']) ?> &bull; Defina os turnos para cálculo da agenda</p>
        </div>
        <a href="/admin/horarios/index.php" class="btn btn-secondary text-sm">
            &larr; Voltar
        </a>
    </div>

    <?php if (!empty($erro)): ?>
        <div class="p-4 rounded-xl text-sm bg-rose-50 border border-rose-200 text-rose-800">
            <?= $erro ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/admin/horarios/configurar.php?profissional_id=<?= $profId ?>" class="space-y-6">
        
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="font-bold text-slate-900 text-base">Dias e Turnos da Semana</h3>
                    <p class="text-xs text-slate-500">Marque os dias trabalhados e estipule a janela de atendimento</p>
                </div>
                <!-- Botões de Atalho Rápido -->
                <div class="flex items-center gap-2">
                    <button type="button" id="btn-seg-sex" class="btn btn-secondary btn-sm text-xs">
                        Padrão Seg a Sex (08h-18h)
                    </button>
                </div>
            </div>

            <div class="divide-y divide-slate-100">
                <?php for ($d = 0; $d <= 6; $d++): ?>
                    <?php $item = $grade[$d]; ?>
                    <div class="p-4 sm:px-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-slate-50/60 transition-colors">
                        
                        <!-- Dia e Checkbox Ativo -->
                        <div class="w-48 flex items-center gap-3">
                            <input type="checkbox" id="dia_<?= $d ?>" name="dias[<?= $d ?>][ativo]" value="1" <?= $item['ativo'] === 1 ? 'checked' : '' ?> class="dia-check" data-dia="<?= $d ?>">
                            <label for="dia_<?= $d ?>" class="cursor-pointer">
                                <span class="font-bold text-sm text-slate-900 block"><?= getDayOfWeekName($d) ?></span>
                                <span class="text-xs text-slate-400 font-normal status-label-<?= $d ?>">
                                    <?= $item['ativo'] === 1 ? 'Dia de expediente' : 'Dia de folga' ?>
                                </span>
                            </label>
                        </div>

                        <!-- Campos Horário Início / Fim -->
                        <div class="flex items-center gap-3 flex-1 max-w-sm times-group-<?= $d ?> <?= $item['ativo'] === 1 ? '' : 'opacity-40 pointer-events-none' ?>">
                            <div class="flex-1">
                                <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Início</label>
                                <input type="time" name="dias[<?= $d ?>][hora_inicio]" value="<?= sanitize($item['hora_inicio']) ?>" class="text-sm font-mono py-1.5 px-3">
                            </div>
                            <span class="text-slate-400 text-sm mt-5">até</span>
                            <div class="flex-1">
                                <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Término</label>
                                <input type="time" name="dias[<?= $d ?>][hora_fim]" value="<?= sanitize($item['hora_fim']) ?>" class="text-sm font-mono py-1.5 px-3">
                            </div>
                        </div>

                    </div>
                <?php endfor; ?>
            </div>

        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="/admin/horarios/index.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Salvar Grade de Horários</button>
        </div>

    </form>

</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Atualiza estado visual quando checkbox de ativo é alterado
        document.querySelectorAll('.dia-check').forEach(cb => {
            cb.addEventListener('change', (e) => {
                const dia = e.target.getAttribute('data-dia');
                const group = document.querySelector('.times-group-' + dia);
                const label = document.querySelector('.status-label-' + dia);
                if (e.target.checked) {
                    group?.classList.remove('opacity-40', 'pointer-events-none');
                    if (label) label.textContent = 'Dia de expediente';
                } else {
                    group?.classList.add('opacity-40', 'pointer-events-none');
                    if (label) label.textContent = 'Dia de folga';
                }
            });
        });

        // Atalho padrão comercial Seg-Sex 08-18, Sab 08-12, Dom off
        document.getElementById('btn-seg-sex')?.addEventListener('click', () => {
            for (let d = 0; d <= 6; d++) {
                const cb = document.getElementById('dia_' + d);
                const inicioInput = document.querySelector(`input[name="dias[${d}][hora_inicio]"]`);
                const fimInput = document.querySelector(`input[name="dias[${d}][hora_fim]"]`);

                if (d === 0) {
                    // Domingo
                    if (cb) cb.checked = false;
                } else if (d === 6) {
                    // Sábado
                    if (cb) cb.checked = true;
                    if (inicioInput) inicioInput.value = '08:00';
                    if (fimInput) fimInput.value = '12:00';
                } else {
                    // Seg a Sex
                    if (cb) cb.checked = true;
                    if (inicioInput) inicioInput.value = '08:00';
                    if (fimInput) fimInput.value = '18:00';
                }
                cb?.dispatchEvent(new Event('change'));
            }
        });
    });
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
