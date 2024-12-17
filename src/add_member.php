<?php
include 'db.php';

$nom = $_POST['nom'];
$prenom = $_POST['prenom'];
$email = $_POST['email'];
$telephone = $_POST['telephone'];

try {
    $stmt = $db->prepare("INSERT INTO Adherents (nom, prenom, email, telephone) VALUES (?, ?, ?, ?)");
    $stmt->execute([$nom, $prenom, $email, $telephone]);
    $id = $db->lastInsertId();

    echo json_encode(['success' => true, 'id' => $id, 'nom' => $nom, 'prenom' => $prenom]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
