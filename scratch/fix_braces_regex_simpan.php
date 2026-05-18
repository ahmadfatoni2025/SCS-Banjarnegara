<?php
$file = 'e:/SCSBANJARNEGARA/jurnal_umum.php';
$content = file_get_contents($file);

// Find the area around line 158-162
$pattern = '/mysqli_autocommit\(\$koneksi, true\);\s*\}\s*\}\s*\}\s*\}/s';
$replacement = "mysqli_autocommit(\$koneksi, true);\n        }\n    }\n}";

$content = preg_replace($pattern, $replacement, $content);
file_put_contents($file, $content);

echo "Regex fix for simpan block braces." . PHP_EOL;
?>
