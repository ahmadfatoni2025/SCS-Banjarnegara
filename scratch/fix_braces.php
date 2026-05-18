<?php
$file = 'e:/SCSBANJARNEGARA/jurnal_umum.php';
$content = file_get_contents($file);
// We are looking for the end of the simpan_transaksi_multi block
// It ends with mysqli_autocommit($koneksi, true); followed by 2 braces.
// We need 3.
$old = 'mysqli_autocommit($koneksi, true);
    }
}';
$new = 'mysqli_autocommit($koneksi, true);
        }
    }
}';
$content = str_replace($old, $new, $content);
file_put_contents($file, $content);
echo "Fixed braces in jurnal_umum.php" . PHP_EOL;
?>
