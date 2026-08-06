<?php

require_once __DIR__ . '/db.php';

function getServerPDO(): PDO
{
    $dsn = sprintf('mysql:host=%s;charset=%s', DB_HOST, DB_CHARSET);

    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function testMysqlConnection(): array
{
    try {
        getServerPDO();
        return ['ok' => true, 'message' => 'Conexión a MySQL correcta.'];
    } catch (PDOException $e) {
        return ['ok' => false, 'message' => 'No se pudo conectar a MySQL: ' . $e->getMessage()];
    }
}

function databaseExists(): bool
{
    try {
        $pdo = getServerPDO();
        $stmt = $pdo->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
        $stmt->execute([DB_NAME]);
        return (bool) $stmt->fetch();
    } catch (PDOException) {
        return false;
    }
}

function getInstallStatus(): array
{
    $status = [
        'mysql' => testMysqlConnection(),
        'database' => ['ok' => false, 'message' => 'Base de datos no creada.'],
        'tables' => ['ok' => false, 'message' => 'Tablas no encontradas.'],
        'tipos_pago' => 0,
        'cuentas' => 0,
        'ready' => false,
    ];

    if (!$status['mysql']['ok']) {
        return $status;
    }

    if (!databaseExists()) {
        return $status;
    }

    $status['database'] = ['ok' => true, 'message' => 'Base de datos "' . DB_NAME . '" encontrada.'];

    try {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $required = ['tipos_pago', 'cuentas'];
        $missing = array_diff($required, $tables);

        if (empty($missing)) {
            $status['tables'] = ['ok' => true, 'message' => 'Tablas tipos_pago y cuentas listas.'];
            $status['tipos_pago'] = (int) $pdo->query('SELECT COUNT(*) FROM tipos_pago')->fetchColumn();
            $status['cuentas'] = (int) $pdo->query('SELECT COUNT(*) FROM cuentas')->fetchColumn();
            $status['ready'] = true;
        } else {
            $status['tables'] = [
                'ok' => false,
                'message' => 'Faltan tablas: ' . implode(', ', $missing),
            ];
        }
    } catch (PDOException $e) {
        $status['database'] = ['ok' => false, 'message' => $e->getMessage()];
    }

    return $status;
}

function createDatabase(): void
{
    $pdo = getServerPDO();
    $name = str_replace('`', '``', DB_NAME);
    $pdo->exec(sprintf(
        "CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        $name
    ));
}

function runSchemaFromFile(): array
{
    $path = __DIR__ . '/../database/schema.sql';
    if (!is_readable($path)) {
        throw new RuntimeException('No se encontró database/schema.sql');
    }

    $sql = file_get_contents($path);
    $sql = preg_replace('/--.*$/m', '', $sql);
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $pdo = getServerPDO();
    $log = [];
    $usingDatabase = false;

    foreach ($statements as $statement) {
        if ($statement === '') {
            continue;
        }

        if (stripos($statement, 'USE ') === 0) {
            $usingDatabase = true;
            continue;
        }

        if (stripos($statement, 'CREATE DATABASE') === 0) {
            $pdo->exec($statement);
            $log[] = 'Base de datos creada o verificada.';
            continue;
        }

        if (!$usingDatabase) {
            createDatabase();
            $usingDatabase = true;
        }

        $dbPdo = getDatabasePDO();

        if (stripos($statement, 'INSERT INTO tipos_pago') === 0 && databaseHasTiposPago()) {
            $log[] = 'Tipos de pago ya existían, inserción omitida.';
            continue;
        }

        $dbPdo->exec($statement);
        $log[] = describeStatement($statement);
    }

    return $log;
}

function getDatabasePDO(): PDO
{
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function databaseHasTiposPago(): bool
{
    if (!databaseExists()) {
        return false;
    }

    try {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->query('SELECT 1 FROM tipos_pago LIMIT 1');
        return (int) $pdo->query('SELECT COUNT(*) FROM tipos_pago')->fetchColumn() > 0;
    } catch (PDOException) {
        return false;
    }
}

function describeStatement(string $statement): string
{
    if (stripos($statement, 'CREATE TABLE IF NOT EXISTS tipos_pago') === 0) {
        return 'Tabla tipos_pago creada o verificada.';
    }
    if (stripos($statement, 'CREATE TABLE IF NOT EXISTS cuentas') === 0) {
        return 'Tabla cuentas creada o verificada.';
    }
    if (stripos($statement, 'INSERT INTO tipos_pago') === 0) {
        return 'Tipos de pago iniciales insertados.';
    }

    return 'Consulta ejecutada correctamente.';
}

function runInstall(): array
{
    $log = [];

    $connection = testMysqlConnection();
    if (!$connection['ok']) {
        throw new RuntimeException($connection['message']);
    }
    $log[] = $connection['message'];

    createDatabase();
    $log[] = 'Base de datos "' . DB_NAME . '" lista.';

    $schemaLog = runSchemaFromFile();
    $log = array_merge($log, $schemaLog);

    require_once __DIR__ . '/database.php';
    getDB();

    $final = getInstallStatus();
    $log[] = 'Importación completada: ' . $final['tipos_pago'] . ' tipos de pago, ' . $final['cuentas'] . ' cuentas.';

    return $log;
}

function importSqlFromUrl(string $url): array
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('La URL no es válida.');
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'follow_location' => 1,
            'max_redirects' => 3,
        ],
    ]);

    $sql = @file_get_contents($url, false, $context);
    if ($sql === false) {
        throw new RuntimeException('No se pudo descargar el archivo SQL desde la URL.');
    }

    createDatabase();

    $sql = preg_replace('/--.*$/m', '', $sql);
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $pdo = getServerPDO();
    $dbPdo = getDatabasePDO();
    $log = ['Archivo descargado desde URL.'];

    foreach ($statements as $statement) {
        if ($statement === '' || stripos($statement, 'USE ') === 0) {
            continue;
        }

        if (stripos($statement, 'CREATE DATABASE') === 0) {
            $pdo->exec($statement);
            $log[] = 'Base de datos creada o verificada.';
            continue;
        }

        $dbPdo->exec($statement);
        $log[] = describeStatement($statement);
    }

    return $log;
}
