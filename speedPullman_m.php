<?php
set_time_limit(1200); // Tiempo máximo de ejecución
$pasw = "123";
$user = "Pullman";
$dia = $_GET['dia'] ?? 1; // Valor predeterminado si no se proporciona 'dia'

include "./conexion.php"; // Incluye la conexión a la base de datos

// Obtener el hash de autenticación
$consulta = "SELECT hash FROM masgps.hash WHERE user='$user' AND pasw='$pasw'";
$resultado = mysqli_query($mysqli, $consulta);
if (!$resultado || mysqli_num_rows($resultado) === 0) {
    die("Error al obtener el hash o usuario no encontrado.");
}
$data = mysqli_fetch_assoc($resultado);
$hash = $data['hash'];

// Definir zona horaria y fechas
date_default_timezone_set("America/Santiago");
$ayer = date('Y-m-d', strtotime("-$dia days"));

// Incluye la lista de identificadores de trackers
include "listadoPullman.php";

// Función para ejecutar cURL
function executeCurl($url, $postFields, $headers) {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HTTPHEADER => $headers,
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}

// Generar el informe de violación de velocidad
$generateReportUrl = 'http://www.trackermasgps.com/api-v2/report/tracker/generate';
$generatePostFields = 'hash=' . $hash . '&title=Informe%20de%20violaci%C3%B3n%20de%20velocidad&trackers=' . $ids . '&from=' . $ayer . '%2000%3A00%3A00&to=' . $ayer . '%2023%3A59%3A59&time_filter=%7B%22from%22%3A%2200%3A00%22%2C%22to%22%3A%2223%3A59%22%2C%22weekdays%22%3A%5B1%2C2%2C3%2C4%2C5%2C6%2C7%5D%7D&plugin=%7B%22hide_empty_tabs%22%3Atrue%2C%22plugin_id%22%3A27%2C%22show_seconds%22%3Afalse%2C%22min_duration_minutes%22%3A1%2C%22max_speed%22%3A100%2C%22group_by_driver%22%3Afalse%2C%22filter%22%3Atrue%7D';
$headers = array(
    'Accept: */*',
    'Accept-Language: es-419,es;q=0.9,en;q=0.8',
    'Connection: keep-alive',
    'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
    'Origin: http://www.trackermasgps.com',
    'Referer: http://www.trackermasgps.com/pro/applications/reports/index.html?newuiwrap=1',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36'
);

$response = executeCurl($generateReportUrl, $generatePostFields, $headers);
$arreglo = json_decode($response);
$reporte = $arreglo->id ?? null;

if (!$reporte) {
    die("Error al generar el informe.");
}

$retrieveReportUrl = 'http://www.trackermasgps.com/api-v2/report/tracker/retrieve';
$retrievePostFields = 'hash=' . $hash . '&report_id=' . $reporte;

// Intentar obtener los datos del informe
do {
    sleep(10); // Esperar antes de intentar recuperar los datos

    $response = executeCurl($retrieveReportUrl, $retrievePostFields, $headers);
    $datos = json_decode($response);

    if (isset($datos->report->sheets)) {
        $vehiculos = $datos->report->sheets;
        $insertBatch = [];

        foreach ($vehiculos as $tracker) {
            $pat = $tracker->header ?? '';
            $id_tracker = $tracker->entity_ids[0] ?? '';

            $eventos = $tracker->sections[1]->data[0]->rows ?? [];

            foreach ($eventos as $evento) {
                $start_time = $evento->start_time->v ?? '';
                $duration = $evento->duration->v ?? '';
                $max_speed = $evento->max_speed->v ?? '';
                $lat = $evento->max_speed_address->location->lat ?? '';
                $lng = $evento->max_speed_address->location->lng ?? '';

                $insertBatch[] = "('$user', '$id_tracker', '$pat', '$ayer', '$start_time', '$duration', '$max_speed', '$lat', '$lng')";

                if (count($insertBatch) == 50) {
                    $Qry = "INSERT INTO `masgps`.`max_speed` (`cuenta`, `id_tracker`, `patente`, `fecha`, `start_time`, `duration`, `max_speed`, `lat`, `lng`) VALUES " . implode(", ", $insertBatch) . ";";
                    mysqli_query($mysqli, $Qry);
                    $insertBatch = [];
                }
            }
        }

        // Insertar cualquier registro restante que no haya alcanzado un lote completo de 50
        if (count($insertBatch) > 0) {
            $Qry = "INSERT INTO `masgps`.`max_speed` (`cuenta`, `id_tracker`, `patente`, `fecha`, `start_time`, `duration`, `max_speed`, `lat`, `lng`) VALUES " . implode(", ", $insertBatch) . ";";
            mysqli_query($mysqli, $Qry);
        }
        echo 'Ciclo terminado';
        break; // Salir del bucle si los datos han sido procesados correctamente
    }

} while (true); // Continuar el bucle hasta que se obtengan los datos

?>
