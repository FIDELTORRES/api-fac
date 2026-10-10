<?php
// Detectar ambiente
//if (strpos(__DIR__, 'xampp') !== false) {
//    define('AMBIENTE', 'desarrollo');
//    define('RUTA_DATA', 'C:/xampp/htdocs/api/data/');  //'C:\Program Files\NetMake\v9-php81\wwwroot');  //
//} else {
//    define('AMBIENTE', 'produccion');
//    define('RUTA_DATA', '/home/elfideco/public_html/api/data/');
//}
// Obtener APP_ENV desde la BD o un archivo de par¨¢metros
$APP_ENV = 2; // 2 = producci¨®n, 1 = desarrollo (simulaci¨®n)

if ($APP_ENV == 1) {
    define('AMBIENTE', 'desarrollo');
    define('RUTA_DATA', 'C:/xampp/htdocs/api/data/');
} else {
    define('AMBIENTE', 'produccion');
    // define('RUTA_DATA', '/home/elfideco/public_html/api/data/');
    define('RUTA_DATA', '/home/elfideco/10413820532/data/');
}

// ConexiÃ³n BD
function obtenerConexionBD() {
    $host = (AMBIENTE === 'desarrollo') ? 'localhost' : 'localhost';
    $db   = 'nombre_bd';
    $user = 'usuario_bd';
    $pass = 'clave_bd';

    $dsn = "mysql:host=$host;dbname=$db;charset=utf8";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
}
