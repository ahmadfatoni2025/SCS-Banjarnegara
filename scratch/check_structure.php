<?php
include 'koneksi.php';
function check($table) {
    global $koneksi;
    echo "\nStructure of $table:\n";
    $res = $koneksi->query("DESCRIBE $table");
    while($row = $res->fetch_assoc()) {
        echo "{$row['Field']} - {$row['Type']}\n";
    }
}
check('data_suplier');
check('gudang');
?>
