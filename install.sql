-- Création de la base de données
CREATE DATABASE IF NOT EXISTS maintenance_agro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE maintenance_agro;

-- Création de la table pannes
CREATE TABLE IF NOT EXISTS pannes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_name VARCHAR(255) NOT NULL,
    problem TEXT NOT NULL,
    cause TEXT NOT NULL,
    resolution TEXT NOT NULL,
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_machine (machine_name),
    INDEX idx_date (date_created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion de données de test
INSERT INTO pannes (machine_name, problem, cause, resolution) VALUES
('Pasteurisateur', 'Température de pasteurisation instable', 'Sonde de température PT100 défectueuse', 'Remplacement de la sonde PT100 et recalibration du système'),
('Ligne de Remplissage', 'Arrêt intempestif de la ligne', 'Capteur de niveau encrassé', 'Nettoyage du capteur ultrason et vérification du câblage'),
('Convoyeur Central', 'Bruit anormal et vibrations', 'Roulements usés et courroie détendue', 'Remplacement des roulements et tension de la courroie'),
('Système CIP', 'Perte de pression dans le circuit', 'Fuite au niveau des joints de pompe', 'Remplacement des joints toriques et test d\'étanchéité'),
('Pasteurisateur', 'Alarme haute température', 'Vanne de régulation bloquée en position ouverte', 'Démontage et nettoyage de la vanne, remplacement du joint'),
('Ligne de Remplissage', 'Dosage imprécis', 'Pompe doseuse avec membrane usée', 'Remplacement du kit membrane et recalibration'),
('Tunnel de Refroidissement', 'Température de sortie trop élevée', 'Ventilateurs obstrués par des résidus', 'Nettoyage complet des ventilateurs et vérification du circuit'),
('Système CIP', 'Concentration de soude incorrecte', 'Pompe doseuse déréglée', 'Recalibration de la pompe doseuse et vérification des paramètres');

-- Requêtes utiles pour l'administration
-- Historique par machine:
-- SELECT * FROM pannes WHERE machine_name = 'Pasteurisateur' ORDER BY date_created DESC;

-- Statistiques:
-- SELECT machine_name, COUNT(*) as total_pannes FROM pannes GROUP BY machine_name ORDER BY total_pannes DESC;

-- Recherche par mot-clé:
-- SELECT * FROM pannes WHERE problem LIKE '%température%' OR cause LIKE '%température%';