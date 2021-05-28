<?php
$basePath = dirname(__DIR__);
$rawPath = $basePath . '/raw/od';
if (!file_exists($rawPath)) {
    mkdir($rawPath, 0777, true);
}
$dataPath = $basePath . '/data/od';

$statsFile = $rawPath . '/covid19_tw_stats.csv';
$specimenFile = $rawPath . '/covid19_tw_specimen.csv';
$dailyFile = $rawPath . '/Day_Confirmation_Age_County_Gender_19CoV.csv';
file_put_contents($statsFile, file_get_contents('https://od.cdc.gov.tw/eic/covid19/covid19_tw_stats.csv'));
file_put_contents($specimenFile, file_get_contents('https://od.cdc.gov.tw/eic/covid19/covid19_tw_specimen.csv'));
file_put_contents($dailyFile, file_get_contents('https://od.cdc.gov.tw/eic/Day_Confirmation_Age_County_Gender_19CoV.csv'));

$fh = fopen($dailyFile, 'r');
$head = fgetcsv($fh, 2048);
$pathConfirmed = $dataPath . '/confirmed';
if (!file_exists($pathConfirmed)) {
    mkdir($pathConfirmed, 0777, true);
}
$confirmed = [];
$now = date('Y-m-d H:i:s');
while ($line = fgetcsv($fh, 2048)) {
    $data = array_combine($head, $line);
    if ($data['是否為境外移入'] === '否') {
        $y = substr($data['個案研判日'], 0, 4);
        if (!isset($confirmed[$y])) {
            $confirmed[$y] = [
                'meta' => [
                    'total' => 0,
                    'modified' => $now,
                ],
                'data' => [],
            ];
        }
        $cityCode = $data['縣市'] . $data['鄉鎮'];
        $confirmed[$y]['meta']['total'] += $data['確定病例數'];
        if(!isset($confirmed[$y]['data'][$cityCode])) {
            $confirmed[$y]['data'][$cityCode] = 0;
        }
        $confirmed[$y]['data'][$cityCode] += $data['確定病例數'];
    }
}
foreach($confirmed AS $y => $data1) {
    ksort($confirmed[$y]['data']);
    $targetFile = $pathConfirmed . '/' . $y . '.json';
    $fileToWrite = true;
    if(file_exists($targetFile)) {
        $json = json_decode(file_get_contents($targetFile));
        if($json['data'] === $confirmed[$y]['data']) {
            $fileToWrite = false;
        }
    }
    if($fileToWrite) {
        file_put_contents($targetFile, json_encode($confirmed[$y], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}