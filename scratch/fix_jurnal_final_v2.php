<?php
$file = 'e:/SCSBANJARNEGARA/jurnal_umum.php';
$content = file_get_contents($file);

$old = "            }
        }
    }
}

// ===================== 5. LOGIC EDIT TRANSAKSI MULTI AKUN =====================";

$new = "        }
    }
}

// ===================== 5. LOGIC EDIT TRANSAKSI MULTI AKUN =====================";

$content = str_replace($old, $new, $content);
file_put_contents($file, $content);
echo "Final final attempt at fixing jurnal_umum.php" . PHP_EOL;
?>
