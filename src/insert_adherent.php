<?php
// /src/insert_adherent.php
include 'db.php';

$nom = $_POST['nom'] ?? '';
$prenom = $_POST['prenom'] ?? '';
$email = $_POST['email'] ?? '';
$telephone = $_POST['telephone'] ?? '';
$monthly_fee = (float)($_POST['monthly_fee'] ?? 0);
$start_date = $_POST['start_date'] ?? null;
$end_date = $_POST['end_date'] ?? null; // Peut être vide => adhésion "à vie"

if ($nom === '' || $prenom === '' || $email === '' || $telephone === '') {
    echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.']);
    exit;
}

// Vérification email unique
$check = $db->prepare("SELECT COUNT(*) FROM Adherents WHERE email = ?");
$check->execute([$email]);
if ($check->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'Cet email est déjà utilisé.']);
    exit;
}

try {
    $stmt = $db->prepare("
        INSERT INTO Adherents 
            (nom, prenom, email, telephone, anonyme, donateur_temporaire, monthly_fee, start_date, end_date)
        VALUES 
            (?, ?, ?, ?, 0, 0, ?, ?, ?)
    ");
    $stmt->execute([$nom, $prenom, $email, $telephone, $monthly_fee, $start_date, $end_date]);
    $id = $db->lastInsertId();

    // Aucune génération de lignes dans Cotisation_Months ici.
    echo json_encode([
        'success' => true,
        'id' => $id,
        'nom' => $nom,
        'prenom' => $prenom,
        'email' => $email,
        'telephone' => $telephone
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
