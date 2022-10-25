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
$dailyGzFile = $rawPath . '/Day_Confirmation_Age_County_Gender_19CoV.csv.gz';
file_put_contents($statsFile, file_get_contents('https://od.cdc.gov.tw/eic/covid19/covid19_tw_stats.csv'));
file_put_contents($specimenFile, file_get_contents('https://od.cdc.gov.tw/eic/covid19/covid19_tw_specimen.csv'));
$c = file_get_contents('https://od.cdc.gov.tw/eic/Day_Confirmation_Age_County_Gender_19CoV.csv');
file_put_contents($dailyFile, $c);
$fp = gzopen($dailyGzFile, 'w9');
gzwrite($fp, $c);
gzclose($fp);

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
    'city' => '',
    'area' => '',
    'days' => [],
    'gender' => [
        'm' => 0,
        'f' => 0,
    ],
    'age' => [
        '0' => 0,
        '1' => 0,
        '2' => 0,
        '3' => 0,
        '4' => 0,
        '5-9' => 0,
        '10-14' => 0,
        '15-19' => 0,
        '20-24' => 0,
        '25-29' => 0,
        '30-34' => 0,
        '35-39' => 0,
        '40-44' => 0,
        '45-49' => 0,
        '50-54' => 0,
        '55-59' => 0,
        '60-64' => 0,
        '65-69' => 0,
        '70+' => 0,
    ],
];
for ($i = $timeBegin; $i < $timeEnd; $i += 86400) {
    $townTemplate['days'][date('Ymd', $i)] = 0;
}

$confirmed = [];
$now = date('Y-m-d H:i:s', $timeEnd);
$towns = [];
$rateBase = [];
$avg7Pool = [];
$latestDay = 0;
while ($line = fgetcsv($fh, 2048)) {
    $data = array_combine($head, $line);
    $data['鄉鎮'] = str_replace(['　', ' '], '', $data['鄉鎮']);
    if ($data['是否為境外移入'] !== '是') {
        $data['個案研判日'] = str_replace('/', '', $data['個案研判日']);
        if ($latestDay < $data['個案研判日']) {
            $latestDay = $data['個案研判日'];
        }
        $y = substr($data['個案研判日'], 0, 4);
        if (!isset($confirmed[$y])) {
            $confirmed[$y] = [
                'meta' => [
                    'day' => $data['個案研判日'],
                    'total' => 0,
                    'modified' => $now,
                ],
                'data' => [],
                'rate' => [],
                'increase' => [],
                'avg7' => [],
            ];
            if ($y != date('Y')) {
                $confirmed[$y]['meta']['day'] = date('Ymd', strtotime($y . '-12-31'));
            }
        }
        if ($data['個案研判日'] > $confirmed[$y]['meta']['day']) {
            $confirmed[$y]['meta']['day'] = $data['個案研判日'];
        }
        $confirmed[$y]['meta']['total'] += $data['確定病例數'];
        if (!isset($confirmed[$y]['data'][$data['縣市']])) {
            $confirmed[$y]['data'][$data['縣市']] = [];
            $confirmed[$y]['rate'][$data['縣市']] = [];
            $confirmed[$y]['increase'][$data['縣市']] = [];
            $confirmed[$y]['avg7'][$data['縣市']] = [];
        }
        if (!isset($confirmed[$y]['data'][$data['縣市']][$data['鄉鎮']])) {
            $confirmed[$y]['data'][$data['縣市']][$data['鄉鎮']] = 0;
            $confirmed[$y]['rate'][$data['縣市']][$data['鄉鎮']] = 0.0;
            $confirmed[$y]['increase'][$data['縣市']][$data['鄉鎮']] = 0;
            $confirmed[$y]['avg7'][$data['縣市']][$data['鄉鎮']] = 0;
        }
        $confirmed[$y]['data'][$data['縣市']][$data['鄉鎮']] += $data['確定病例數'];

        $townKey = $data['縣市'] . $data['鄉鎮'];
        if (!isset($towns[$townKey])) {
            $towns[$townKey] = $townTemplate;
            $towns[$townKey]['city'] = $data['縣市'];
            $towns[$townKey]['area'] = $data['鄉鎮'];
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
    $rateBaseDay = date('Ymd', strtotime('-1 day', strtotime($data1['meta']['day'])));
    $rateBaseFile = $pathConfirmed . '/' . $rateBaseDay . '.json';
    if (file_exists($rateBaseFile)) {
        $rateBase[$y] = json_decode(file_get_contents($pathConfirmed . '/' . $rateBaseDay . '.json'), true);
        $avg7Pool[$rateBaseDay] = $rateBase[$y];
    } else {
        $rateBase[$y] = [];
    }

    ksort($confirmed[$y]['data']);
    ksort($confirmed[$y]['rate']);
    ksort($confirmed[$y]['increase']);
    ksort($confirmed[$y]['avg7']);
    foreach ($confirmed[$y]['data'] as $city => $data2) {
        ksort($confirmed[$y]['data'][$city]);
        ksort($confirmed[$y]['rate'][$city]);
        ksort($confirmed[$y]['increase'][$city]);
        ksort($confirmed[$y]['avg7'][$city]);

        foreach ($data2 as $town => $data3) {
            if (isset($rateBase[$y]['data'][$city][$town])) {
                $confirmed[$y]['increase'][$city][$town] = $confirmed[$y]['data'][$city][$town] - $rateBase[$y]['data'][$city][$town];
                $confirmed[$y]['rate'][$city][$town] = round($confirmed[$y]['increase'][$city][$town] / $rateBase[$y]['data'][$city][$town], 2);
            } else {
                $confirmed[$y]['increase'][$city][$town] = $confirmed[$y]['data'][$city][$town];
                $confirmed[$y]['rate'][$city][$town] = 1.0;
            }

            $daySum7 = 0;
            $daySumDay = strtotime($confirmed[$y]['meta']['day']);
            for ($j = 0; $j < 7; $j++) {
                $dayKey = date('Ymd', $daySumDay);
                if ($dayKey == $confirmed[$y]['meta']['day']) {
                    if (isset($confirmed[$y]['increase'][$city][$town])) {
                        $daySum7 += $confirmed[$y]['increase'][$city][$town];
                    }
                } else {
                    if (!isset($avg7Pool[$dayKey])) {
                        $rateBaseFile = $pathConfirmed . '/' . $dayKey . '.json';
                        if (file_exists($rateBaseFile)) {
                            $avg7Pool[$dayKey] = json_decode(file_get_contents($rateBaseFile), true);
                        }
                    }
                    if (isset($avg7Pool[$dayKey]['increase'][$city][$town])) {
                        $daySum7 += $avg7Pool[$dayKey]['increase'][$city][$town];
                    }
                }
                $daySumDay -= 86400;
            }
            $confirmed[$y]['avg7'][$city][$town] = round($daySum7 / 7, 2);
        }
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

foreach ($towns as $k => $data) {
    foreach ($data['days'] as $d => $v) {
        if ($d > $latestDay) {
            unset($data['days'][$d]);
        }
    }
    file_put_contents($pathTown . '/' . $k . '.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
