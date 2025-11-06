<?php
// Configuración de subida de archivos
define("UPLOAD_MAX_SIZE", 50 * 1024 * 1024); // 50MB
define("UPLOAD_DIR", "../../uploads/documents/");
define("ALLOWED_EXTENSIONS", ["pdf", "doc", "docx", "xls", "xlsx", "ppt", "pptx", "txt", "jpg", "jpeg", "png", "gif"]);
define("ALLOWED_MIME_TYPES", [
    "application/pdf",
    "application/msword",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    "application/vnd.ms-excel",
    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    "application/vnd.ms-powerpoint",
    "application/vnd.openxmlformats-officedocument.presentationml.presentation",
    "text/plain",
    "image/jpeg",
    "image/jpg",
    "image/png",
    "image/gif"
]);

// Crear directorio de uploads si no existe
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}


// Validar archivo subido
function validateUploadedFile($file)
{
    $errors = [];

    // Verificar si hay errores en la subida
    if ($file["error"] !== UPLOAD_ERR_OK) {
        switch ($file["error"]) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = "El archivo es demasiado grande";
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors[] = "El archivo se subió parcialmente";
                break;
            case UPLOAD_ERR_NO_FILE:
                $errors[] = "No se seleccionó ningún archivo";
                break;
            default:
                $errors[] = "Error desconocido en la subida";
        }
        return $errors;
    }

    // Verificar tamaño
    if ($file["size"] > UPLOAD_MAX_SIZE) {
        $errors[] = "El archivo excede el tamaño máximo permitido (" . (UPLOAD_MAX_SIZE / 1024 / 1024) . "MB)";
    }

    // Verificar extensión
    $extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        $errors[] = "Tipo de archivo no permitido. Extensiones permitidas: " . implode(", ", ALLOWED_EXTENSIONS);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file["tmp_name"]);
    finfo_close($finfo);

    if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
        $errors[] = "Tipo de archivo no válido";
    }

    return $errors;
}

// Generar nombre único para archivo
function generateUniqueFileName($originalName)
{
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $basename = pathinfo($originalName, PATHINFO_FILENAME);

    // Limpiar nombre
    $basename = preg_replace("/[^a-zA-Z0-9_-]/", "_", $basename);
    $basename = substr($basename, 0, 50); // Limitar longitud

    // Generar nombre único
    $timestamp = time();
    $random = substr(md5(uniqid()), 0, 8);

    return $basename . "_" . $timestamp . "_" . $random . "." . $extension;
}


function getFileInfo($filePath)
{
    if (!file_exists($filePath)) {
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath);
    finfo_close($finfo);

    return [
        "size" => filesize($filePath),
        "mime_type" => $mimeType,
        "extension" => strtolower(pathinfo($filePath, PATHINFO_EXTENSION))
    ];
}
