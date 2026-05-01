<?php
/*
 * HELPERS DE SEGURIDAD · rutas físicas de uploads
 * ------------------------------------------------
 * Las URLs en BD vienen como '../uploads/pacientes/5/foto.jpg' o
 * '../uploads/perfil/u3/foto.jpg'. Varios endpoints concatenan esa URL
 * a __DIR__ para construir la ruta física que pasan a unlink/is_file.
 *
 * Sin validación, una URL envenenada en BD (por ej. introducida por un
 * bug futuro) podría escapar de uploads/ y borrar archivos del sistema.
 * Estas dos funciones resuelven la ruta con realpath() y solo la
 * devuelven/usan si está confinada bajo `dentacita/uploads/`.
 */


/**
 * Resuelve una URL relativa de upload a su ruta física canónica,
 * verificando que NO escape del directorio uploads/.
 *
 * @param string $urlRelativa  Ej. '../uploads/pacientes/5/foto.jpg'
 * @param string $baseDir      Típicamente __DIR__ del archivo que llama.
 *                             El upload root se calcula como $baseDir/../uploads.
 * @return string|null  Ruta física absoluta si es segura y existe; null si no.
 */
function rutaFisicaUploadSegura($urlRelativa, $baseDir) {
    if (!is_string($urlRelativa) || $urlRelativa === '') return null;
    if (!str_starts_with($urlRelativa, '../uploads/')) return null;

    $rutaPropuesta = $baseDir . '/' . $urlRelativa;
    $rutaCanonica  = realpath($rutaPropuesta);
    if ($rutaCanonica === false) return null;

    $uploadsRoot = realpath($baseDir . '/../uploads');
    if ($uploadsRoot === false) return null;

    // Confinamiento: la ruta canónica DEBE empezar exactamente con uploadsRoot/
    if (strpos($rutaCanonica, $uploadsRoot . DIRECTORY_SEPARATOR) !== 0) {
        return null;
    }

    return $rutaCanonica;
}


/**
 * Borra un archivo físico solo si la URL relativa pasa la validación
 * de confinamiento. Best-effort (no lanza error si falla).
 *
 * @return bool  true si se borró, false en cualquier otro caso.
 */
function borrarArchivoUploadSeguro($urlRelativa, $baseDir) {
    $ruta = rutaFisicaUploadSegura($urlRelativa, $baseDir);
    if ($ruta === null)   return false;
    if (!is_file($ruta))  return false;
    return @unlink($ruta);
}
