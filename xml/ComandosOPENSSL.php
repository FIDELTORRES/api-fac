<?php
/**
 * Clase para extraer Archivos .PEM del certificado Digital en formato .PFX o .P12
 */
class ComandosOPENSSL
{
    public static function extraerArchivos($certificado, $carpeta = 'archivosPEM')
    {

        $mensajeError = '';

        if (isset($certificado['password']) && isset($certificado['name'])) {

            // Contraseña del certificado
            $contraseña = $certificado['password'];

            // Nombre del certificado con el formato .pfx
            $nombreArchivo = $certificado['name'];

            // Devolvemos el el certificado como un string (cadena)
            $pkcs12 = file_get_contents($nombreArchivo);

            // Declaramos un arreglo para recuperar los certificados
            $certificados = array();

            // Pasamos los parámetros openssl
            $respuesta = openssl_pkcs12_read($pkcs12, $certificados, $contraseña);

            //Si la respuesta es TRUE es porque se ha recuperado correctamente los certificados
            if ($respuesta) {

                $publicKeyPem  = $certificados['cert']; //Archivo público
                $privateKeyPem = $certificados['pkey']; //Archivo privado

                //Preguntamos si no existe para crear el directorio
                if (!file_exists($carpeta)) {
                    //Creamos el directorio
                    mkdir($carpeta, 0777, true);
                }

                //guardo la clave publica y privada en mi directorio en formato .PEM
                file_put_contents($carpeta . '/private_key.pem', $privateKeyPem);
                file_put_contents($carpeta . '/public_key.pem', $publicKeyPem);

                $mensajeError = 'Los certificados fueron extraídos correctamente';

            } else {
                $mensajeError = 'Error al extraer los certificados. ' . openssl_error_string();
            }

        } else {
            $mensajeError = 'Los parámetros solicitados no son los correctos';
        }

        return $mensajeError;
    }
}
