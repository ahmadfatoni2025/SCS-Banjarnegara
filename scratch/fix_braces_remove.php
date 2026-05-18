<?php
$file = 'e:/SCSBANJARNEGARA/jurnal_umum.php';
$content = file_get_contents($file);

// Remove one brace from the end of that block
$pattern = '/mysqli_autocommit\(\$koneksi, true\);\s*\}\s*\}\s*\}/s';
$replacement = "mysqli_autocommit(\$koneksi, true);\n    }\n}";

$content = preg_replace($pattern, $replacement, $content);
file_put_contents($file, $content);

echo "Attempted to remove one brace in jurnal_umum.php" . PHP_EOL;
?>
