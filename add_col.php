<?php
$mysqli = new mysqli('localhost', 'root', '', 'scsbanja_banjarnegara');
$mysqli->query("ALTER TABLE user ADD COLUMN foto_sampul VARCHAR(255) NULL AFTER foto");
echo "Done";
