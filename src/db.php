<?php
$dsn = 'sqlite:sqlite.sqlite'; // Assurez-vous du chemin correct vers votre base.
try {
    $db = new PDO($dsn);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>
