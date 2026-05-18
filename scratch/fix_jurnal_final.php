<?php
$file = 'e:/SCSBANJARNEGARA/jurnal_umum.php';
$content = file_get_contents($file);

// 1. Fix simpan_transaksi_multi block
// We need to ensure it's closed before edit_transaksi_multi starts.

// 2. Add isDateLocked to edit_transaksi_multi
$old_edit = "if (isset(\$_POST['edit_transaksi_multi'])) {
    \$no_reff = \$_POST['no_reff'];
    \$tgl = \$_POST['tanggal'];";

$new_edit = "if (isset(\$_POST['edit_transaksi_multi'])) {
    \$no_reff = \$_POST['no_reff'];
    \$tgl = \$_POST['tanggal'];
    
    if (isDateLocked(\$koneksi, \$tgl)) {
        \$pesan = \"<script>Swal.fire({icon: 'error', title: 'Terkunci!', text: 'Periode transaksi ini sudah ditutup. Tidak dapat diubah.'});</script>\";
    } else {";

// Since I added an 'else' to edit_transaksi_multi, I also need to close it at the end of that block.
$old_edit_end = "mysqli_autocommit(\$koneksi, true);
    }
}";

$new_edit_end = "mysqli_autocommit(\$koneksi, true);
        }
    }
}";

// I'll do this very carefully.
// Let's first close the isset(simpan) block.

$search_simpan_end = "mysqli_autocommit(\$koneksi, true);\n    }\n}\n\n// ===================== 5. LOGIC EDIT TRANSAKSI MULTI AKUN =====================";
$replace_simpan_end = "mysqli_autocommit(\$koneksi, true);\n    }\n}\n}\n\n// ===================== 5. LOGIC EDIT TRANSAKSI MULTI AKUN =====================";

$content = str_replace($search_simpan_end, $replace_simpan_end, $content);
$content = str_replace($old_edit, $new_edit, $content);
$content = str_replace($old_edit_end, $new_edit_end, $content);

file_put_contents($file, $content);
echo "Final attempt at fixing jurnal_umum.php structure." . PHP_EOL;
?>
