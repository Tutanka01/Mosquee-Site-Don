<?php
// File: /src/update_adherent.php
header('Content-Type: application/json');

include 'db.php';

$id = (int)($_POST['id'] ?? 0);
$nom = $_POST['nom'] ?? '';
$prenom = $_POST['prenom'] ?? '';
$email = $_POST['email'] ?? '';
$telephone = $_POST['telephone'] ?? '';
$new_monthly_fee = (float)($_POST['monthly_fee'] ?? 0);
$new_start_date = $_POST['start_date'] ?? null;
$new_end_date = $_POST['end_date'] ?? null;

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

try {
    // 1) Mise à jour de l'adhérent
    $stmt = $db->prepare("
        UPDATE Adherents
        SET nom=?, prenom=?, email=?, telephone=?,
            monthly_fee=?, start_date=?, end_date=?
        WHERE id=?
    ");
    $stmt->execute([$nom, $prenom, $email, $telephone,
                    $new_monthly_fee, $new_start_date, $new_end_date,
                    $id]);

    // 2) Recharger l'adhérent avec ses nouvelles infos (pour la répartition)
    $st = $db->prepare("SELECT monthly_fee, start_date, end_date FROM Adherents WHERE id=?");
    $st->execute([$id]);
    $ad = $st->fetch(PDO::FETCH_ASSOC);

    if (!$ad) {
        echo json_encode(['success' => false, 'message' => 'Adhérent non retrouvé en BDD après mise à jour.']);
        exit;
    }

    $monthly_fee = (float)$ad['monthly_fee'];
    $start_date  = $ad['start_date'] ? new DateTime($ad['start_date']) : null;
    $end_date    = $ad['end_date']   ? new DateTime($ad['end_date'])   : null;

    // 3) Si monthly_fee > 0 && start_date n'est pas null => recalcul
    //    On décide de tout réinitialiser (effacer Cotisation_Months)
    if ($monthly_fee > 0 && $start_date) {
        // Effacer les lignes cotisation existantes pour cet adhérent
        $del = $db->prepare("DELETE FROM Cotisation_Months WHERE id_adherent=?");
        $del->execute([$id]);

        // 4) Relire toutes les contributions "cotisation" de cet adhérent
        //    et réexécuter la logique pour repartir le montant
        $cots = $db->prepare("
            SELECT id, montant, jour_paiement
            FROM Contributions
            WHERE id_adherent=? AND type_contribution='cotisation'
            ORDER BY jour_paiement ASC, id ASC
        ");
        $cots->execute([$id]);
        $allContrib = $cots->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allContrib as $contrib) {
            $montant = (float)$contrib['montant'];
            // Répartition de $montant sur les mois
            $reste = $montant;
            $current = clone $start_date;

            while ($reste > 0) {
                // Si end_date et qu'on a dépassé => break
                if ($end_date && $current > $end_date) {
                    break;
                }
                // Sécurité boucle infinie
                if ((int)$current->format('Y') > 9999) {
                    break;
                }

                $y = (int)$current->format('Y');
                $m = (int)$current->format('m');

                // On cherche s'il existe déjà une ligne (peut arriver si on
                // a plusieurs contributions => cumul)
                $chk = $db->prepare("
                    SELECT id, paid_amount
                    FROM Cotisation_Months
                    WHERE id_adherent=? AND year=? AND month=?
                ");
                $chk->execute([$id, $y, $m]);
                $line = $chk->fetch(PDO::FETCH_ASSOC);

                if ($line) {
                    $alreadyPaid = (float)$line['paid_amount'];
                    if ($alreadyPaid < $monthly_fee) {
                        $need = $monthly_fee - $alreadyPaid;
                        if ($reste >= $need) {
                            $new_paid = $alreadyPaid + $need;
                            $reste -= $need;
                        } else {
                            $new_paid = $alreadyPaid + $reste;
                            $reste = 0;
                        }
                        $upd = $db->prepare("UPDATE Cotisation_Months SET paid_amount=? WHERE id=?");
                        $upd->execute([$new_paid, $line['id']]);
                    }
                } else {
                    // Pas de ligne => on crée
                    $paid = 0;
                    $need = $monthly_fee - $paid;
                    $toPay = 0;
                    if ($reste >= $need) {
                        $toPay = $need;
                        $reste -= $need;
                    } else {
                        $toPay = $reste;
                        $reste = 0;
                    }
                    $ins = $db->prepare("
                        INSERT INTO Cotisation_Months (id_adherent, year, month, paid_amount)
                        VALUES (?, ?, ?, ?)
                    ");
                    $ins->execute([$id, $y, $m, $toPay]);
                }

                $current->modify('+1 month');
            }
        }
    }

    echo json_encode(['success' => true, 'message' => 'Adhérent mis à jour et Cotisation_Months recalculé.']);
} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour de l\'adhérent.']);
}
?>
