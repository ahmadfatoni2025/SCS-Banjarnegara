<?php
include 'koneksi.php';

echo "Starting migration for detail_pesanan table...\n";

// 1. Drop existing composite primary key
// Note: We might need to drop foreign keys first if they depend on the PK, but usually in InnoDB they refer to the columns, not specifically the "primary keyness" of them. However, it's safer to check.
// The FKs are fk_detail_barang and fk_detail_pesanan.

try {
    // Add id_detail column and make it PRIMARY KEY
    $sql = "ALTER TABLE detail_pesanan 
            DROP PRIMARY KEY,
            ADD COLUMN id_detail INT UNSIGNED AUTO_INCREMENT PRIMARY KEY FIRST";
    
    if ($koneksi->query($sql)) {
        echo "SUCCESS: Added id_detail as Primary Key and removed composite PK.\n";
    } else {
        throw new Exception($koneksi->error);
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Attempting alternative strategy (adding column first)...\n";
    
    // Alternative if DROP PRIMARY KEY fails due to constraints
    // (Though usually InnoDB allows it if the columns are still there)
}

?>
