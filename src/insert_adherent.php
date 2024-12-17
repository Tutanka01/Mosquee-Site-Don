<?php
include 'db.php';

$nom = $_POST['nom'] ?? '';
$prenom = $_POST['prenom'] ?? '';
$email = $_POST['email'] ?? '';
$telephone = $_POST['telephone'] ?? '';

// Vérification email unique
$check = $db->prepare("SELECT COUNT(*) FROM Adherents WHERE email = ?");
$check->execute([$email]);
if ($check->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'Cet email est déjà utilisé.']);
    exit;
}

try {
    $stmt = $db->prepare("INSERT INTO Adherents (nom, prenom, email, telephone, anonyme, donateur_temporaire) VALUES (?, ?, ?, ?, 0, 0)");
    $stmt->execute([$nom, $prenom, $email, $telephone]);
    $id = $db->lastInsertId();

    echo json_encode(['success' => true, 'id' => $id, 'nom' => $nom, 'prenom' => $prenom, 'email' => $email, 'telephone' => $telephone]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
