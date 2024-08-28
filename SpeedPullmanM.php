<?php
set_time_limit(1200);
$pasw = "123";
$user="Pullman";
$dia=$_GET['dia'];

include "./conexion.php";

$consulta = "SELECT hash FROM masgps.hash WHERE user='$user' AND pasw='$pasw'";
$resutaldo = mysqli_query($mysqli, $consulta);
$data = mysqli_fetch_array($resutaldo);
$hash = $data['hash'];

date_default_timezone_set("America/Santiago");
$hoy = date("Y-m-d");
$ayer = date('Y-m-d', strtotime("-$dia days"));

include "listadoPullman.php";

$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => 'http://www.trackermasgps.com/api-v2/report/tracker/generate',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => 'hash=' . $hash . '&title=Informe%20de%20violaci%C3%B3n%20de%20velocidad&trackers=' . $ids . '&from=' . $ayer . '%2000%3A00%3A00&to=' . $ayer . '%2023%3A59%3A59&time_filter=%7B%22from%22%3A%2200%3A00%22%2C%22to%22%3A%2223%3A59%22%2C%22weekdays%22%3A%5B1%2C2%2C3%2C4%2C5%2C6%2C7%5D%7D&plugin=%7B%22hide_empty_tabs%22%3Atrue%2C%22plugin_id%22%3A27%2C%22show_seconds%22%3Afalse%2C%22min_duration_minutes%22%3A1%2C%22max_speed%22%3A100%2C%22group_by_driver%22%3Afalse%2C%22filter%22%3Atrue%7D',
    CURLOPT_HTTPHEADER => array(
        'Accept: */*',
        'Accept-Language: es-419,es;q=0.9,en;q=0.8',
        'Connection: keep-alive',
        'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        'Cookie: _ga=GA1.2.728367267.1665672802; session_key=313bcf73d4cab8b8934bae1556b273e2; _gid=GA1.2.1549217858.1690386194; locale=es; check_audit=313bcf73d4cab8b8934bae1556b273e2; _ga_XXFQ02HEZ2=GS1.2.1690401308.12.1.1690401317.0.0.0',
        'Origin: http://www.trackermasgps.com',
        'Referer: http://www.trackermasgps.com/pro/applications/reports/index.html?newuiwrap=1',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36'
    ),
));

$response = curl_exec($curl);
curl_close($curl);
echo $response;
$arreglo = json_decode($response);
$reporte = $arreglo->id;

traerDatos:

Loop:

sleep(10);

$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => 'http://www.trackermasgps.com/api-v2/report/tracker/retrieve',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => 'hash=' . $hash . '&report_id=' . $reporte,
    CURLOPT_HTTPHEADER => array(
        'Accept: */*',
        'Accept-Language: es-419,es;q=0.9,en;q=0.8',
        'Connection: keep-alive',
        'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        'Cookie: _ga=GA1.2.728367267.1665672802; _gid=GA1.2.183718605.1679328823; locale=es; session_key=cf290712c61924284913e1af01cfaded; check_audit=cf290712c61924284913e1af01cfaded; date_format=m-d-Y; date_format_moment=MM-DD-YYYY',
        'Origin: http://www.trackermasgps.com',
        'Referer: http://www.trackermasgps.com/pro/applications/reports/index.html?newuiwrap=1',
        'User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Mobile Safari/537.36'
    ),
));

$response = curl_exec($curl);
curl_close($curl);

$datos = json_decode($response);

if (isset($datos->report->sheets)) {
    $vehiculos = $datos->report->sheets;
    $insertBatch = [];

    foreach ($vehiculos as $tracker) {
        $pat = $tracker->header;
        $id_tracker = $tracker->entity_ids[0];

        $eventos = $tracker->sections[1]->data[0]->rows;

        foreach ($eventos as $evento) {
            $start_time = $evento->start_time->v;
            $duration = $evento->duration->v;
            $max_speed = $evento->max_speed->v;
            $lat = $evento->max_speed_address->location->lat;
            $lng = $evento->max_speed_address->location->lng;

            $insertBatch[] = "('Pullman', '$id_tracker', '$pat', '$ayer', '$start_time', '$duration', '$max_speed', '$lat', '$lng')";

            if (count($insertBatch) == 50) {
                $Qry = "INSERT INTO `masgps`.`speedPullman` (`cuenta`, `id_tracker`, `patente`, `fecha`, `start_time`, `duration`, `max_speed`, `lat`, `lng`) VALUES " . implode(", ", $insertBatch) . ";";
                $ejecutar = mysqli_query($mysqli, $Qry);
                $insertBatch = [];
            }
        }
    }

    // Insert any remaining records that didn't make a full batch of 50
    if (count($insertBatch) > 0) {
        $Qry = "INSERT INTO `masgps`.`speedPullman` (`cuenta`, `id_tracker`, `patente`, `fecha`, `start_time`, `duration`, `max_speed`, `lat`, `lng`) VALUES " . implode(", ", $insertBatch) . ";";
        $ejecutar = mysqli_query($mysqli, $Qry);
    }
} else {
    echo $response;
    goto Loop;
}
