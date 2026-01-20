<?php
// Evita che errori PHP sporchino l'output JSON
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// Pulizia buffer per sicurezza
ob_start();

$archiveFileName = 'markers.json';
$proximity_radius_meters = 10;

function haversine_distance($lat1, $lon1, $lat2, $lon2) {
    $R = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    return $R * (2 * atan2(sqrt($a), sqrt(1-$a)));
}

function loadMarkers($fn) {
    if (file_exists($fn)) {
        $content = file_get_contents($fn);
        $data = json_decode($content, true);
        return is_array($data) ? $data : array();
    }
    return array();
}

function saveMarkers($fn, $m) {
    // array_values garantisce che l'array sia salvato come lista JSON [] e non come oggetto {}
    return file_put_contents($fn, json_encode(array_values($m), JSON_PRETTY_PRINT), LOCK_EX);
}

$input = json_decode(file_get_contents('php://input'), true);
$operation = isset($input['operation']) ? $input['operation'] : '';
$markerData = isset($input['marker']) ? $input['marker'] : null;
$markers = loadMarkers($archiveFileName);

$response = array('status' => 'error', 'message' => 'Azione non valida');

switch ($operation) {
    case 'load':
        ob_clean();
        echo json_encode($markers);
        exit;

    case 'add':
        if ($markerData) {
            $tooClose = false;
            foreach ($markers as $em) {
                if (haversine_distance($markerData['latitude'], $markerData['longitude'], $em['latitude'], $em['longitude']) < $proximity_radius_meters) {
                    $tooClose = true;
                    break;
                }
            }
            
            if ($tooClose) {
                $response['message'] = "Troppo vicino ad un marker esistente!";
            } else {
                $markers[] = $markerData;
                if (saveMarkers($archiveFileName, $markers)) {
                    $response = array('status' => 'success', 'message' => 'Aggiunto');
                }
            }
        }
        break;

    case 'update':
        if ($markerData) {
            foreach ($markers as $key => $m) {
                if ($m['id'] === $markerData['id']) {
                    $markers[$key] = array_merge($m, $markerData);
                    saveMarkers($archiveFileName, $markers);
                    $response = array('status' => 'success', 'message' => 'Aggiornato');
                    break;
                }
            }
        }
        break;

    case 'delete':
        if ($markerData) {
            $initialCount = count($markers);
            // Sostituita la Arrow Function con una funzione tradizionale per compatibilità
            $newMarkers = array_filter($markers, function($m) use ($markerData) {
                return $m['id'] !== $markerData['id'];
            });

            if (count($newMarkers) < $initialCount) {
                if (saveMarkers($archiveFileName, $newMarkers)) {
                    $response = array('status' => 'success', 'message' => 'Cancellato');
                }
            } else {
                $response['message'] = "Marker non trovato";
            }
        }
        break;
}

// Invia solo il JSON pulito
ob_clean();
echo json_encode($response);
?>