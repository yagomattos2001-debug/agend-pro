<?php
/**
 * AGEND PRO - Configuração e Conexão de Banco de Dados
 * Produção - InfinityFree
 */

declare(strict_types=1);

// Credenciais do banco de dados - InfinityFree
define('DB_HOST', 'sql203.infinityfree.com');
define('DB_PORT', '3306');

// COLOQUE AQUI O NOME EXATO DO SEU BANCO NO INFINITYFREE
define('DB_NAME', 'if0_42876819_if0_XXXXXXXX_agendpro');

// Usuário do MySQL
define('DB_USER', 'if0_42876819');

// COLOQUE AQUI A SUA SENHA DO MYSQL
define('DB_PASS', '8GhM6QD4EJbp');

/**
 * Retorna uma instância singleton de conexão PDO com MySQL.
 */
function getDBConnection(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        PDO::ATTR_TIMEOUT            => 10,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

        return $pdo;

    } catch (PDOException $e) {

        error_log(
            "Erro de Conexão PDO MySQL: " . $e->getMessage()
        );

        throw new RuntimeException(
            "Não foi possível conectar ao banco de dados."
        );
    }
}
