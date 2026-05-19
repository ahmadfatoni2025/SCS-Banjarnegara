<?php
$mysqli = new mysqli('localhost', 'root', '', 'scsbanja_banjarnegara');
$result = $mysqli->query("SHOW COLUMNS FROM user");
while($row = $result->fetch_assoc()){
    echo $row['Field'] . "\n";
}
