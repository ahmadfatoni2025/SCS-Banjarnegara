<?php
$file = 'e:/SCSBANJARNEGARA/jurnal_umum.php';
$content = file_get_contents($file);

// 1. Correct the edit block start
$old_edit = "if (isset(\$_POST['edit_transaksi_multi'])) {
    \$no_reff = \$_POST['no_reff'];
    \$tgl = \$_POST['tanggal'];
    \$ket_umum = mysqli_real_escape_string(\$koneksi, \$_POST['keterangan_umum']);";

$new_edit = "if (isset(\$_POST['edit_transaksi_multi'])) {
    \$no_reff = \$_POST['no_reff'];
    \$tgl = \$_POST['tanggal'];
    
    if (isDateLocked(\$koneksi, \$tgl)) {
        \$pesan = \"<script>Swal.fire({icon: 'error', title: 'Terkunci!', text: 'Periode transaksi ini sudah ditutup. Tidak dapat diubah.'});</script>\";
    } else {
        \$ket_umum = mysqli_real_escape_string(\$koneksi, \$_POST['keterangan_umum']);";

// 2. Correct the edit block end (3 braces needed now)
$old_end = "mysqli_autocommit(\$koneksi, true);\n        }\n    }\n}";
$new_end = "mysqli_autocommit(\$koneksi, true);\n            }\n        }\n    }";

$content = str_replace($old_edit, $new_edit, $content);
// We want to make sure we only have 3 braces total at the end of that block.
// Let's check what's actually there.
$content = preg_replace('/mysqli_autocommit\(\$koneksi, true\);\s*\}\s*\}\s*\}/s', "mysqli_autocommit(\$koneksi, true);\n            }\n        }\n    }", $content);

file_put_contents($file, $content);
echo "Final attempt at fixing jurnal_umum.php logic." . PHP_EOL;
?>
