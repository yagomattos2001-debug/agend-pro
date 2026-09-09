<?php
/**
 * AGEND PRO - Configuração e Conexão de Banco de Dados (PDO + MySQL)
 * 
 * Conexão segura utilizando PDO, Prepared Statements reais, UTF-8 (utf8mb4)
 * e tratamento profissional de exceções.
 */

declare(strict_types=1);

// Definições de parâmetros de conexão com MySQL
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'agend_pro');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

/**
 * Retorna uma instância singleton de conexão PDO com MySQL.
 * 
 * @return PDO
 * @throws RuntimeException em caso de falha de conexão
 */
function getDBConnection(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        PDO::ATTR_TIMEOUT            => 5,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // Se o banco agend_pro ainda não foi criado, tenta criar automaticamente
        if ($e->getCode() === 1049 || str_contains($e->getMessage(), 'Unknown database')) {
            try {
                $rootDsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT);
                $initPdo = new PDO($rootDsn, DB_USER, DB_PASS, $options);
                $initPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                
                $schemaFile = __DIR__ . '/../database/schema.sql';
                $seedFile = __DIR__ . '/../database/seed.sql';
                
                $initPdo->exec("USE `" . DB_NAME . "`");
                if (file_exists($schemaFile)) {
                    $initPdo->exec(file_get_contents($schemaFile));
                }
                if (file_exists($seedFile)) {
                    $initPdo->exec(file_get_contents($seedFile));
                }
                
                // Reconecta no banco recém-criado
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                return $pdo;
            } catch (Exception $innerException) {
                error_log("Erro na auto-inicialização do banco: " . $innerException->getMessage());
            }
        }

        error_log("Erro de Conexão PDO MySQL: " . $e->getMessage());
        throw new RuntimeException("Não foi possível conectar ao banco de dados MySQL. Verifique as configurações no arquivo config/database.php.");
    }
}
