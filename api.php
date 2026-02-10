<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Configuration MySQL - À ADAPTER selon votre serveur
$host = 'localhost';
$dbname = 'maintenance_agro';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Connexion échouée: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

switch($action) {
    case 'test':
        // Test de connexion
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM pannes");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'count' => $result['count']]);
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
        
    case 'getAll':
        // Récupérer toutes les pannes
        try {
            $stmt = $pdo->query("SELECT * FROM pannes ORDER BY date_created DESC");
            $pannes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'pannes' => $pannes]);
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
        
    case 'getMachines':
        // Récupérer la liste des machines avec compteur
        try {
            $stmt = $pdo->query("
                SELECT machine_name as name, COUNT(*) as count 
                FROM pannes 
                GROUP BY machine_name 
                ORDER BY machine_name
            ");
            $machines = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'machines' => $machines]);
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
        
    case 'getByMachine':
        // Récupérer les pannes d'une machine spécifique
        $machine = $_GET['machine'] ?? '';
        try {
            $stmt = $pdo->prepare("SELECT * FROM pannes WHERE machine_name = ? ORDER BY date_created DESC");
            $stmt->execute([$machine]);
            $pannes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'pannes' => $pannes]);
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
        
    case 'add':
        // Ajouter une nouvelle panne
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $stmt = $pdo->prepare("
                INSERT INTO pannes (machine_name, problem, cause, resolution) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['machine_name'],
                $data['problem'],
                $data['cause'],
                $data['resolution']
            ]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
        
    case 'query':
        // Traiter une requête de l'assistant IA
        $data = json_decode(file_get_contents('php://input'), true);
        $query = strtolower($data['query']);
        
        try {
            // Détecter le type de requête
            if (preg_match('/historique.*?(\w+)/i', $query, $matches)) {
                // Recherche par machine
                $machine = $matches[1];
                $stmt = $pdo->prepare("SELECT * FROM pannes WHERE machine_name LIKE ? ORDER BY date_created DESC");
                $stmt->execute(["%$machine%"]);
                $pannes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $response = generateMachineReport($pannes, $machine);
                
            } elseif (preg_match('/température|thermique/i', $query)) {
                // Recherche par mot-clé
                $stmt = $pdo->query("SELECT * FROM pannes WHERE problem LIKE '%température%' OR cause LIKE '%température%' ORDER BY date_created DESC");
                $pannes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $response = generateKeywordReport($pannes, 'température');
                
            } elseif (preg_match('/fréquent|statistique/i', $query)) {
                // Statistiques
                $stmt = $pdo->query("SELECT machine_name, COUNT(*) as count FROM pannes GROUP BY machine_name ORDER BY count DESC");
                $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $response = generateStatsReport($stats);
                
            } else {
                // Réponse générale
                $response = generateGeneralHelp();
            }
            
            echo json_encode(['success' => true, 'response' => $response]);
            
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Action non reconnue']);
}

// Fonctions de génération de réponses
function generateMachineReport($pannes, $machine) {
    if (empty($pannes)) {
        return "Aucune panne trouvée pour la machine contenant '<strong>$machine</strong>'.";
    }
    
    $response = "<div class='analysis-section'>";
    $response .= "<h4>📊 Analyse : Machine '$machine'</h4>";
    $response .= "<p><strong>Total de pannes :</strong> " . count($pannes) . "</p>";
    $response .= "</div>";
    
    $response .= "<h4>🔍 Historique des Pannes :</h4>";
    
    foreach($pannes as $index => $panne) {
        $date = date('d/m/Y', strtotime($panne['date_created']));
        $response .= "<div class='panne-item'>";
        $response .= "<strong>Panne #{$panne['id']}</strong> - $date<br>";
        $response .= "<strong>Machine :</strong> {$panne['machine_name']}<br>";
        $response .= "<strong>Problème :</strong> {$panne['problem']}<br>";
        $response .= "<strong>Cause :</strong> {$panne['cause']}<br>";
        $response .= "<strong>✅ Résolution :</strong> {$panne['resolution']}";
        $response .= "</div>";
    }
    
    return $response;
}

function generateKeywordReport($pannes, $keyword) {
    if (empty($pannes)) {
        return "Aucune panne trouvée concernant '<strong>$keyword</strong>'.";
    }
    
    $response = "<div class='analysis-section'>";
    $response .= "<h4>🔍 Recherche : '$keyword'</h4>";
    $response .= "<p>" . count($pannes) . " résultat(s) trouvé(s)</p>";
    $response .= "</div>";
    
    foreach($pannes as $panne) {
        $date = date('d/m/Y', strtotime($panne['date_created']));
        $response .= "<div class='panne-item'>";
        $response .= "<strong>{$panne['machine_name']}</strong> - $date<br>";
        $response .= "<strong>Problème :</strong> {$panne['problem']}<br>";
        $response .= "<strong>Cause :</strong> {$panne['cause']}<br>";
        $response .= "<strong>✅ Résolution :</strong> {$panne['resolution']}";
        $response .= "</div>";
    }
    
    return $response;
}

function generateStatsReport($stats) {
    $response = "<div class='analysis-section'>";
    $response .= "<h4>📈 Statistiques des Pannes</h4>";
    $response .= "</div>";
    
    $response .= "<h4>🏆 Machines avec le plus de pannes :</h4>";
    
    foreach($stats as $index => $stat) {
        $response .= "<div class='panne-item'>";
        $response .= "<strong>#" . ($index + 1) . " - {$stat['machine_name']}</strong><br>";
        $response .= "{$stat['count']} panne(s) enregistrée(s)";
        $response .= "</div>";
    }
    
    return $response;
}

function generateGeneralHelp() {
    return "<div class='analysis-section'>
        <h4>👋 Comment puis-je vous aider ?</h4>
    </div>
    <h4>💬 Exemples de questions :</h4>
    <div class='panne-item'>
        • 'Historique du Pasteurisateur'<br>
        <small>Voir toutes les pannes d'une machine</small>
    </div>
    <div class='panne-item'>
        • 'Problèmes de température'<br>
        <small>Rechercher par mot-clé</small>
    </div>
    <div class='panne-item'>
        • 'Pannes fréquentes'<br>
        <small>Voir les statistiques</small>
    </div>";
}
?>