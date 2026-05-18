<?php
$file = 'e:/SCSBANJARNEGARA/jurnal_umum.php';
$content = file_get_contents($file);

// 1. Add lock to edit_transaksi_multi
$old_edit_start = "if (isset(\$_POST['edit_transaksi_multi'])) {
    \$no_reff = \$_POST['no_reff'];
    \$tgl = \$_POST['tanggal'];
    \$ket_umum = mysqli_real_escape_string(\$koneksi, \$_POST['keterangan_umum']);";

$new_edit_start = "if (isset(\$_POST['edit_transaksi_multi'])) {
    \$no_reff = \$_POST['no_reff'];
    \$tgl = \$_POST['tanggal'];
    
    if (isDateLocked(\$koneksi, \$tgl)) {
        \$pesan = \"<script>Swal.fire({icon: 'error', title: 'Terkunci!', text: 'Periode transaksi ini sudah ditutup. Tidak dapat diubah.'});</script>\";
    } else {
        \$ket_umum = mysqli_real_escape_string(\$koneksi, \$_POST['keterangan_umum']);";

// 2. Fix the braces at the end of edit block
// Current state around 230:
// mysqli_autocommit($koneksi, true);
//         }
//     }
// }

$old_end = "mysqli_autocommit(\$koneksi, true);\n        }\n    }\n}";
$new_end = "mysqli_autocommit(\$koneksi, true);\n            }\n        }\n    }\n}";

$content = str_replace($old_edit_start, $new_edit_start, $content);
$content = str_replace($old_end, $new_end, $content);

file_put_contents($file, $content);
echo "Final syntax fix for jurnal_umum.php" . PHP_EOL;
?>
