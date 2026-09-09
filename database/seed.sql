-- =======================================================
-- AGEND PRO - Carga Inicial de Dados (Seed Data)
-- =======================================================

USE `agend_pro`;

-- Limpeza prévia para evitar duplicidades em re-execuções
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `agendamentos`;
TRUNCATE TABLE `horarios_trabalho`;
TRUNCATE TABLE `profissional_servicos`;
TRUNCATE TABLE `servicos`;
TRUNCATE TABLE `profissionais`;
TRUNCATE TABLE `clientes`;
TRUNCATE TABLE `usuarios`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Usuário Administrador (Senha: admin123)
INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`) VALUES
(1, 'Administrador AgendPro', 'admin@agendpro.com.br', '$2y$10$Rtoe8Xf3gWhEDCtpcsJFfu/BIKvyeyKa77naZnXphiArUgpWT/3Xq');

-- 2. Profissionais
INSERT INTO `profissionais` (`id`, `nome`, `email`, `telefone`, `especialidade`, `ativo`) VALUES
(1, 'Dra. Ana Oliveira', 'ana.oliveira@agendpro.com.br', '(11) 98765-4321', 'Dermatologia', 1),
(2, 'Dr. Carlos Mendes', 'carlos.mendes@agendpro.com.br', '(11) 97654-3210', 'Fisioterapia e Reabilitação', 1),
(3, 'Dra. Juliana Costa', 'juliana.costa@agendpro.com.br', '(11) 96543-2109', 'Nutrição Clínica', 1);

-- 3. Serviços
INSERT INTO `servicos` (`id`, `nome`, `descricao`, `duracao_minutos`, `preco`, `ativo`) VALUES
(1, 'Avaliação Inicial', 'Anamnese completa, avaliação clínica diagnóstica e planejamento terapêutico personalizado.', 60, 180.00, 1),
(2, 'Consulta Dermatológica', 'Exame detalhado de pele, couro cabeludo e unhas com prescrição e conduta específica.', 45, 220.00, 1),
(3, 'Sessão de Retorno', 'Acompanhamento da evolução clínica e reavaliação das metas estabelecidas.', 30, 110.00, 1),
(4, 'Sessão de Fisioterapia', 'Tratamento fisioterápico focado em alívio da dor, mobilidade e reabilitação postural.', 50, 150.00, 1),
(5, 'Avaliação Nutricional', 'Bioimpedância, avaliação de rotina alimentar e elaboração de plano dietético individual.', 60, 200.00, 1);

-- 4. Relacionamento Profissional x Serviços (N:N)
-- Dra. Ana Oliveira: Avaliação Inicial (1), Consulta Dermatológica (2), Sessão de Retorno (3)
INSERT INTO `profissional_servicos` (`profissional_id`, `servico_id`) VALUES
(1, 1),
(1, 2),
(1, 3);

-- Dr. Carlos Mendes: Avaliação Inicial (1), Sessão de Retorno (3), Fisioterapia (4)
INSERT INTO `profissional_servicos` (`profissional_id`, `servico_id`) VALUES
(2, 1),
(2, 3),
(2, 4);

-- Dra. Juliana Costa: Avaliação Inicial (1), Sessão de Retorno (3), Nutrição (5)
INSERT INTO `profissional_servicos` (`profissional_id`, `servico_id`) VALUES
(3, 1),
(3, 3),
(3, 5);

-- 5. Horários de Trabalho (0=Domingo, 1=Segunda ... 6=Sábado)
-- Dra. Ana Oliveira (Seg a Sex: 08:00 - 18:00, Sáb: 08:00 - 13:00, Dom: Folga)
INSERT INTO `horarios_trabalho` (`profissional_id`, `dia_semana`, `hora_inicio`, `hora_fim`, `ativo`) VALUES
(1, 0, '08:00:00', '12:00:00', 0),
(1, 1, '08:00:00', '18:00:00', 1),
(1, 2, '08:00:00', '18:00:00', 1),
(1, 3, '08:00:00', '18:00:00', 1),
(1, 4, '08:00:00', '18:00:00', 1),
(1, 5, '08:00:00', '18:00:00', 1),
(1, 6, '08:00:00', '13:00:00', 1);

-- Dr. Carlos Mendes (Seg a Sex: 09:00 - 18:00, Sáb: 09:00 - 14:00, Dom: Folga)
INSERT INTO `horarios_trabalho` (`profissional_id`, `dia_semana`, `hora_inicio`, `hora_fim`, `ativo`) VALUES
(2, 0, '09:00:00', '13:00:00', 0),
(2, 1, '09:00:00', '18:00:00', 1),
(2, 2, '09:00:00', '18:00:00', 1),
(2, 3, '09:00:00', '13:00:00', 1),
(2, 4, '09:00:00', '18:00:00', 1),
(2, 5, '09:00:00', '18:00:00', 1),
(2, 6, '09:00:00', '14:00:00', 1);

-- Dra. Juliana Costa (Seg a Sex: 08:30 - 17:30, Sáb e Dom: Folga)
INSERT INTO `horarios_trabalho` (`profissional_id`, `dia_semana`, `hora_inicio`, `hora_fim`, `ativo`) VALUES
(3, 0, '08:30:00', '12:00:00', 0),
(3, 1, '08:30:00', '17:30:00', 1),
(3, 2, '08:30:00', '17:30:00', 1),
(3, 3, '08:30:00', '17:30:00', 1),
(3, 4, '08:30:00', '17:30:00', 1),
(3, 5, '08:30:00', '17:30:00', 1),
(3, 6, '09:00:00', '13:00:00', 0);

-- 6. Clientes Iniciais
INSERT INTO `clientes` (`id`, `nome`, `email`, `telefone`) VALUES
(1, 'João Silva', 'joao.silva@email.com', '(11) 99123-4567'),
(2, 'Maria Souza', 'maria.souza@email.com', '(11) 98234-5678'),
(3, 'Lucas Fernandes', 'lucas.fernandes@email.com', '(11) 97345-6789'),
(4, 'Beatriz Lima', 'beatriz.lima@email.com', '(11) 96456-7890'),
(5, 'Ricardo Martins', 'ricardo.martins@email.com', '(11) 95567-8901');

-- 7. Agendamentos de Demonstração (Hoje, futuros e passados)
INSERT INTO `agendamentos` (`id`, `cliente_id`, `profissional_id`, `servico_id`, `data`, `hora_inicio`, `hora_fim`, `status`, `observacao`) VALUES
(1, 1, 1, 1, CURRENT_DATE, '09:00:00', '10:00:00', 'confirmado', 'Primeira consulta de avaliação dermatológica.'),
(2, 2, 2, 4, CURRENT_DATE, '10:30:00', '11:20:00', 'pendente', 'Paciente relatou dor crônica no joelho direito.'),
(3, 3, 3, 5, CURRENT_DATE, '14:00:00', '15:00:00', 'confirmado', 'Foco em reeducação alimentar esportiva.'),
(4, 4, 1, 3, CURRENT_DATE, '16:00:00', '16:30:00', 'pendente', 'Retorno pós 30 dias de tratamento tópico.'),
(5, 5, 2, 1, DATE_ADD(CURRENT_DATE, INTERVAL 1 DAY), '09:30:00', '10:30:00', 'confirmado', 'Avaliação para reabilitação física.'),
(6, 1, 3, 3, DATE_ADD(CURRENT_DATE, INTERVAL 2 DAY), '11:00:00', '11:30:00', 'pendente', 'Acompanhamento do plano alimentar.'),
(7, 2, 1, 2, DATE_SUB(CURRENT_DATE, INTERVAL 2 DAY), '14:00:00', '14:45:00', 'concluido', 'Atendimento realizado e prescrição entregue.'),
(8, 3, 2, 4, DATE_SUB(CURRENT_DATE, INTERVAL 3 DAY), '15:00:00', '15:50:00', 'cancelado', 'Cliente desmarcou por imprevisto familiar.');
