<?php
$file = 'e:/SCSBANJARNEGARA/jurnal_umum.php';
$content = file_get_contents($file);

// 1. Add lock to edit logic (it's currently missing in the file)
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

// 2. Fix the braces at the end of the file to match the new structure (now needs 3 braces)
$search_end = "mysqli_autocommit(\$koneksi, true);\n            }\n        }\n    }\n\n// ===================== 6. AMBIL DATA AKUN COA =====================";
$replace_end = "mysqli_autocommit(\$koneksi, true);\n            }\n        }\n    }\n\n// ===================== 6. AMBIL DATA AKUN COA =====================";

// Actually, I'll just use regex to make it clean.
$content = str_replace($old_edit, $new_edit, $content);

// Ensure there are 3 braces after the autocommit in the edit block
$content = preg_replace('/(if \(isset\(\$_POST\[\'edit_transaksi_multi\'\)\].*?mysqli_autocommit\(\$koneksi, true\);)\s*\}\s*\}\s*\}\s*/s', "$1\n            }\n        }\n    }\n\n", $content);

file_put_contents($file, $content);
echo "Final attempt at fixing jurnal_umum.php logic with edit lock." . PHP_EOL;
?>
