<?php
include 'db.php';

$nom = $_POST['nom'] ?? '';
$prenom = $_POST['prenom'] ?? '';
$email = $_POST['email'] ?? '';
$telephone = $_POST['telephone'] ?? '';
$monthly_fee = (float)($_POST['monthly_fee'] ?? 0);
$start_date = $_POST['start_date'] ?? null;
$end_date = $_POST['end_date'] ?? null;

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
    $stmt = $db->prepare("INSERT INTO Adherents (nom, prenom, email, telephone, anonyme, donateur_temporaire, monthly_fee, start_date, end_date) VALUES (?, ?, ?, ?, 0, 0, ?, ?, ?)");
    $stmt->execute([$nom, $prenom, $email, $telephone, $monthly_fee, $start_date, $end_date]);
    $id = $db->lastInsertId();

    // Générer les mois de cotisation pour l'année en cours si monthly_fee > 0
    if ($monthly_fee > 0) {
        // $start = new DateTime($start_date);
        // $y = (int)$start->format('Y');
        // On se limite à l'année en cours dans cet exemple, on pourrait aller plus loin
        // for ($m=1;$m<=12;$m++){
        //     $current = new DateTime("$y-$m-01");
        //     if ($current >= $start && (!$end_date || $current <= new DateTime($end_date))) {
        //         $ins = $db->prepare("INSERT INTO Cotisation_Months (id_adherent, year, month, paid_amount) VALUES (?, ?, ?, 0)");
        //         $ins->execute([$id, $y, $m]);
        //     }
        // }
    }

    echo json_encode(['success' => true, 'id' => $id, 'nom' => $nom, 'prenom' => $prenom, 'email' => $email, 'telephone' => $telephone]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
