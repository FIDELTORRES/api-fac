<?php
function cargarEnv($path) {
    if (!file_exists($path)) return;
    $lineas = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lineas as $linea) {
        $linea = trim($linea);
        if ($linea === '' || strpos($linea, '#') === 0) continue;

        // Separar por '=' y eliminar comentarios al final de la línea
        if (strpos($linea, '=') !== false) {
            list($nombre, $valor) = explode('=', $linea, 2);
            $nombre = trim($nombre);
            // Quitar cualquier comentario después de un '#'
            $valor = trim(explode('#', $valor)[0]);
            $_ENV[$nombre] = $valor;
        }
    }
}

cargarEnv(__DIR__ . '/.env');

// =========================
// CONEXIÓN BD
// =========================
function getPDO() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset={$_ENV['DB_CHARSET']}";
        try {
            $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            error_log("Error de conexión: " . $e->getMessage());
            die(json_encode(["estado" => "error", "mensaje" => "Error de conexión a la BD"]));
        }
    }
    return $pdo;
}

// =========================
// LOGGING
// =========================
function apiLog($tipo, $mensaje, $datos = null) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("INSERT INTO api_log (fecha, tipo, mensaje, datos, ip_cliente) VALUES (NOW(), ?, ?, ?, ?)");
        $stmt->execute([
            $tipo,
            $mensaje,
            $datos ? json_encode($datos, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("No se pudo guardar en api_log: " . $e->getMessage());
    }
}

// =========================
// RUTA JSON SEGÚN AMBIENTE
// =========================
/**
 * Devuelve la ruta de almacenamiento de archivos seg  n el entorno
 * APP_ENV=2 -> producci  n
 * APP_ENV=1 -> desarrollo
 */
function getPathArchivos() {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        return rtrim($_ENV['PATH_ARCHIVOS_DEV'], '/') . '/';
    }
    $env = (int) ($_ENV['APP_ENV'] ?? 2);
    return ($env === 2)
        ? rtrim($_ENV['PATH_ARCHIVOS_PROD'], '/') . '/'
        : rtrim($_ENV['PATH_ARCHIVOS_DEV'], '/') . '/';
}

