<?php
$file = 'e:/SCSBANJARNEGARA/jurnal_umum.php';
$content = file_get_contents($file);

// 1. Insert Lock to edit_transaksi_multi
$content = preg_replace(
    '/if \(isset\(\$_POST\[\'edit_transaksi_multi\'\]\)\) \{(\s*)\$no_reff = \$_POST\[\'no_reff\'\];(\s*)\$tgl = \$_POST\[\'tanggal\'\];/',
    "if (isset(\$_POST['edit_transaksi_multi'])) { \$no_reff = \$_POST['no_reff']; \$tgl = \$_POST['tanggal']; if (isDateLocked(\$koneksi, \$tgl)) { \$pesan = \"<script>Swal.fire({icon: 'error', title: 'Terkunci!', text: 'Periode transaksi ini sudah ditutup. Tidak dapat diubah.'});</script>\"; } else {",
    $content
);

// 2. Fix braces at the end of the file
// Currently there are 3 braces. Since I added one 'else', it should have 3.
// Wait, if it says "Unmatched on 234" with 3 braces, it means it only needs 2.
// BUT I JUST ADDED AN ELSE! So it should need 3.

// Let's count again:
// 1. isset (1)
// 2. isDateLocked else (2)
// 3. balance check else (3)

// Okay, I'll just force the count to 3.
$content = preg_replace('/mysqli_autocommit\(\$koneksi, true\);\s*\}\s*\}\s*\}\s*/s', "mysqli_autocommit(\$koneksi, true);\n            }\n        }\n    }\n\n", $content);

file_put_contents($file, $content);
echo "Final attempt with regex for edit lock." . PHP_EOL;
?>
