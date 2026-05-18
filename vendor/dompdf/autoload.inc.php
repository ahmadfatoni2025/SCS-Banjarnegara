<?php
/**
 * Autoloader sederhana untuk Dompdf (tanpa Composer)
 */
spl_autoload_register(function ($class) {
    // Hanya handle namespace Dompdf
    $prefix = 'Dompdf\\';
    $base_dir = __DIR__ . '/src/';
    $lib_dir  = __DIR__ . '/lib/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
        return;
    }

    // Cek di lib/ untuk class seperti Cpdf
    $file_lib = $lib_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file_lib)) {
        require $file_lib;
    }
});
