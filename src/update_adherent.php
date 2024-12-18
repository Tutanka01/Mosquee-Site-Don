<?php
include 'db.php';

$id = (int)($_POST['id'] ?? 0);
$nom = $_POST['nom'] ?? '';
$prenom = $_POST['prenom'] ?? '';
$email = $_POST['email'] ?? '';
$telephone = $_POST['telephone'] ?? '';
$monthly_fee = (float)($_POST['monthly_fee'] ?? 0);
$start_date = $_POST['start_date'] ?? null;
$end_date = $_POST['end_date'] ?? null;

if ($id <= 0 || $nom === '' || $prenom === '' || $email === '' || $telephone === '') {
    echo json_encode(['success' => false, 'message' => 'Champs obligatoires manquants.']);
    exit;
}

// Vérifier si l'email n'est pas déjà pris par un autre adhérent
$check = $db->prepare("SELECT COUNT(*) FROM Adherents WHERE email=? AND id<>?");
$check->execute([$email, $id]);
if ($check->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'Cet email est déjà utilisé par un autre adhérent.']);
    exit;
}

// Mise à jour
$stmt = $db->prepare("UPDATE Adherents SET nom=?, prenom=?, email=?, telephone=?, monthly_fee=?, start_date=?, end_date=? WHERE id=?");
$stmt->execute([$nom, $prenom, $email, $telephone, $monthly_fee, $start_date, $end_date, $id]);

echo json_encode(['success' => true]);
