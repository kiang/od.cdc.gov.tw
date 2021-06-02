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

$fh = fopen($specimenFile, 'r');
$head = fgetcsv($fh, 2048);
$specimen = [];
while ($line = fgetcsv($fh, 2048)) {
    $data = array_combine($head, $line);
    $time = strtotime($data['通報日']);
    $y = date('Y', $time);
    if (!isset($specimen[$y])) {
        $specimen[$y] = [];
    }
    array_shift($data);
    foreach ($data as $k => $v) {
        $data[$k] = intval($v);
    }
    $specimen[$y][date('md', $time)] = array_values($data);
}
$specimenPath = $dataPath . '/specimen';
if (!file_exists($specimenPath)) {
    mkdir($specimenPath, 0777);
}
foreach ($specimen as $y => $data1) {
    ksort($data1);
    file_put_contents($specimenPath . '/' . $y . '.json', json_encode($data1, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$fh = fopen($statsFile, 'r');
$head = fgetcsv($fh, 2048);
$line = fgetcsv($fh, 2048);
file_put_contents($dataPath . '/meta.json', json_encode(array_combine($head, $line), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$fh = fopen($dailyFile, 'r');
$head = fgetcsv($fh, 2048);
$pathConfirmed = $dataPath . '/confirmed';
if (!file_exists($pathConfirmed)) {
    mkdir($pathConfirmed, 0777, true);
}
$pathTown = $dataPath . '/town';
if (!file_exists($pathTown)) {
    mkdir($pathTown, 0777, true);
}
$timeBegin = strtotime('-60 days');
$timeEnd = time();
$townTemplate = [
    'days' => [],
    'gender' => [
        'm' => 0,
        'f' => 0,
    ],
    'age' => [],
];
for ($i = $timeBegin; $i < $timeEnd; $i += 86400) {
    $townTemplate['days'][date('Ymd', $i)] = 0;
}

$confirmed = [];
$now = date('Y-m-d H:i:s', $timeEnd);
$towns = [];
$rateBase = [];
while ($line = fgetcsv($fh, 2048)) {
    $data = array_combine($head, $line);
    if ($data['是否為境外移入'] === '否') {
        $y = substr($data['個案研判日'], 0, 4);
        if (!isset($confirmed[$y])) {
            if ($y == date('Y')) {
                $dayTime = strtotime('-1 day');
            } else {
                $dayTime = strtotime($y . '-12-31');
            }
            $day = date('Ymd', $dayTime);
            $rateBaseDay = date('Ymd', strtotime('-1 day', $dayTime));
            $rateBaseFile = $pathConfirmed . '/' . $rateBaseDay . '.json';
            if(file_exists($rateBaseFile)) {
                $rateBase[$y] = json_decode(file_get_contents($pathConfirmed . '/' . $rateBaseDay . '.json'), true);
            } else {
                $rateBase[$y] = [];
            }
            $confirmed[$y] = [
                'meta' => [
                    'day' => $day,
                    'total' => 0,
                    'modified' => $now,
                ],
                'data' => [],
                'rate' => [],
                'increase' => [],
            ];
        }
        $confirmed[$y]['meta']['total'] += $data['確定病例數'];
        if (!isset($confirmed[$y]['data'][$data['縣市']])) {
            $confirmed[$y]['data'][$data['縣市']] = [];
            $confirmed[$y]['rate'][$data['縣市']] = [];
            $confirmed[$y]['increase'][$data['縣市']] = [];
        }
        if (!isset($confirmed[$y]['data'][$data['縣市']][$data['鄉鎮']])) {
            $confirmed[$y]['data'][$data['縣市']][$data['鄉鎮']] = 0;
            $confirmed[$y]['rate'][$data['縣市']][$data['鄉鎮']] = 0.0;
            $confirmed[$y]['increase'][$data['縣市']][$data['鄉鎮']] = 0;
        }
        $confirmed[$y]['data'][$data['縣市']][$data['鄉鎮']] += $data['確定病例數'];
        if(isset($rateBase[$y]['data'][$data['縣市']][$data['鄉鎮']])) {
            $confirmed[$y]['increase'][$data['縣市']][$data['鄉鎮']] = $confirmed[$y]['data'][$data['縣市']][$data['鄉鎮']] - $rateBase[$y]['data'][$data['縣市']][$data['鄉鎮']];
            $confirmed[$y]['rate'][$data['縣市']][$data['鄉鎮']] = round(
                $confirmed[$y]['increase'][$data['縣市']][$data['鄉鎮']] / $rateBase[$y]['data'][$data['縣市']][$data['鄉鎮']],
                1
            );
        } else {
            $confirmed[$y]['increase'][$data['縣市']][$data['鄉鎮']] = $confirmed[$y]['data'][$data['縣市']][$data['鄉鎮']];
            $confirmed[$y]['rate'][$data['縣市']][$data['鄉鎮']] = 1.0;
        }
    
        $townKey = $data['縣市'] . $data['鄉鎮'];
        if (!isset($towns[$townKey])) {
            $towns[$townKey] = $townTemplate;
        }
        if (isset($towns[$townKey]['days'][$data['個案研判日']])) {
            $towns[$townKey]['days'][$data['個案研判日']] += $data['確定病例數'];
            if ($data['性別'] === '女') {
                $towns[$townKey]['gender']['f'] += $data['確定病例數'];
            } else {
                $towns[$townKey]['gender']['m'] += $data['確定病例數'];
            }
            if (!isset($towns[$townKey]['age'][$data['年齡層']])) {
                $towns[$townKey]['age'][$data['年齡層']] = 0;
            }
            $towns[$townKey]['age'][$data['年齡層']] += $data['確定病例數'];
        }
    }
}
foreach ($confirmed as $y => $data1) {
    ksort($confirmed[$y]['data']);
    foreach ($confirmed[$y]['data'] as $city => $data2) {
        ksort($confirmed[$y]['data'][$city]);
    }
    $targetFile = $pathConfirmed . '/' . $y . '.json';
    $fileToWrite = true;
    if (file_exists($targetFile)) {
        $json = json_decode(file_get_contents($targetFile), true);
        if ($json['data'] === $confirmed[$y]['data']) {
            $fileToWrite = false;
        }
    }
    if ($fileToWrite) {
        file_put_contents($targetFile, json_encode($confirmed[$y], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        file_put_contents($pathConfirmed . '/' . $confirmed[$y]['meta']['day'] . '.json', json_encode($confirmed[$y], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

function cmp($a, $b)
{
    $a = intval($a);
    $b = intval($b);
    return ($a > $b);
}

foreach ($towns as $k => $data) {
    uksort($data['age'], 'cmp');
    file_put_contents($pathTown . '/' . $k . '.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
