<?php
$file = 'e:/SCSBANJARNEGARA/jurnal_umum.php';
$content = file_get_contents($file);

// Find the line with mysqli_autocommit($koneksi, true);
// and check how many braces follow it.
// We want to make sure the block starting at line 93 (else {) is closed.

$pattern = '/mysqli_autocommit\(\$koneksi, true\);\s*\}\s*\}/s';
$replacement = "mysqli_autocommit(\$koneksi, true);\n        }\n    }\n}";

$content = preg_replace($pattern, $replacement, $content);
file_put_contents($file, $content);

echo "Attempted regex fix for braces in jurnal_umum.php" . PHP_EOL;
?>
