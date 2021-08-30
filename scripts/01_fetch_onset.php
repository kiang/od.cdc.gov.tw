<?php
$basePath = dirname(__DIR__);
$rawPath = $basePath . '/raw/od';
if (!file_exists($rawPath)) {
    mkdir($rawPath, 0777, true);
}
$dataPath = $basePath . '/data/od';

$dailyFile = $rawPath . '/Age_County_Gender_day_19Cov.csv';
file_put_contents($dailyFile, file_get_contents('https://od.cdc.gov.tw/eic/Age_County_Gender_day_19Cov.csv'));

$fh = fopen($dailyFile, 'r');
$head = fgetcsv($fh, 2048);
$pathOnset = $dataPath . '/onset';
if (!file_exists($pathOnset)) {
    mkdir($pathOnset, 0777, true);
}
$pathTown = $dataPath . '/onset/town';
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

$onset = [];
$now = date('Y-m-d H:i:s', $timeEnd);
$towns = [];
$rateBase = [];
$avg7Pool = [];
$latestDay = 0;
while ($line = fgetcsv($fh, 2048)) {
    $data = array_combine($head, $line);
    if ($data['是否為境外移入'] !== '是') {
        $data['發病日'] = str_replace('/', '', $data['發病日']);
        if($latestDay < $data['發病日']) {
            $latestDay = $data['發病日'];
        }
        $y = substr($data['發病日'], 0, 4);
        if (!isset($onset[$y])) {
            $onset[$y] = [
                'meta' => [
                    'day' => $data['發病日'],
                    'total' => 0,
                    'modified' => $now,
                ],
                'data' => [],
                'rate' => [],
                'increase' => [],
                'avg7' => [],
            ];
            if ($y != date('Y')) {
                $onset[$y]['meta']['day'] = date('Ymd', strtotime($y . '-12-31'));
            }
        }
        if($data['發病日'] > $onset[$y]['meta']['day']) {
            $onset[$y]['meta']['day'] = $data['發病日'];
        }
        $onset[$y]['meta']['total'] += $data['確定病例數'];
        if (!isset($onset[$y]['data'][$data['縣市']])) {
            $onset[$y]['data'][$data['縣市']] = [];
            $onset[$y]['rate'][$data['縣市']] = [];
            $onset[$y]['increase'][$data['縣市']] = [];
            $onset[$y]['avg7'][$data['縣市']] = [];
        }
        if (!isset($onset[$y]['data'][$data['縣市']][$data['鄉鎮']])) {
            $onset[$y]['data'][$data['縣市']][$data['鄉鎮']] = 0;
            $onset[$y]['rate'][$data['縣市']][$data['鄉鎮']] = 0.0;
            $onset[$y]['increase'][$data['縣市']][$data['鄉鎮']] = 0;
            $onset[$y]['avg7'][$data['縣市']][$data['鄉鎮']] = 0;
        }
        $onset[$y]['data'][$data['縣市']][$data['鄉鎮']] += $data['確定病例數'];

        $townKey = $data['縣市'] . $data['鄉鎮'];
        if (!isset($towns[$townKey])) {
            $towns[$townKey] = $townTemplate;
            $towns[$townKey]['city'] = $data['縣市'];
            $towns[$townKey]['area'] = $data['鄉鎮'];
        }
        if (isset($towns[$townKey]['days'][$data['發病日']])) {
            $towns[$townKey]['days'][$data['發病日']] += $data['確定病例數'];
            if ($data['性別'] === 'F') {
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
foreach ($onset as $y => $data1) {
    $rateBaseDay = date('Ymd', strtotime('-1 day', strtotime($data1['meta']['day'])));
    $rateBaseFile = $pathOnset . '/' . $rateBaseDay . '.json';
    if (file_exists($rateBaseFile)) {
        $rateBase[$y] = json_decode(file_get_contents($pathOnset . '/' . $rateBaseDay . '.json'), true);
        $avg7Pool[$rateBaseDay] = $rateBase[$y];
    } else {
        $rateBase[$y] = [];
    }

    ksort($onset[$y]['data']);
    ksort($onset[$y]['rate']);
    ksort($onset[$y]['increase']);
    ksort($onset[$y]['avg7']);
    foreach ($onset[$y]['data'] as $city => $data2) {
        ksort($onset[$y]['data'][$city]);
        ksort($onset[$y]['rate'][$city]);
        ksort($onset[$y]['increase'][$city]);
        ksort($onset[$y]['avg7'][$city]);

        foreach ($data2 as $town => $data3) {
            if (isset($rateBase[$y]['data'][$city][$town])) {
                $onset[$y]['increase'][$city][$town] = $onset[$y]['data'][$city][$town] - $rateBase[$y]['data'][$city][$town];
                $onset[$y]['rate'][$city][$town] = round($onset[$y]['increase'][$city][$town] / $rateBase[$y]['data'][$city][$town], 2);
            } else {
                $onset[$y]['increase'][$city][$town] = $onset[$y]['data'][$city][$town];
                $onset[$y]['rate'][$city][$town] = 1.0;
            }

            $daySum7 = 0;
            $daySumDay = strtotime($onset[$y]['meta']['day']);
            for ($j = 0; $j < 7; $j++) {
                $dayKey = date('Ymd', $daySumDay);
                if ($dayKey == $onset[$y]['meta']['day']) {
                    if(isset($onset[$y]['increase'][$city][$town])) {
                        $daySum7 += $onset[$y]['increase'][$city][$town];
                    }
                } else {
                    if(!isset($avg7Pool[$dayKey])) {
                        $rateBaseFile = $pathOnset . '/' . $dayKey . '.json';
                        if (file_exists($rateBaseFile)) {
                            $avg7Pool[$dayKey] = json_decode(file_get_contents($rateBaseFile), true);
                        }
                    }
                    if(isset($avg7Pool[$dayKey]['increase'][$city][$town])) {
                        $daySum7 += $avg7Pool[$dayKey]['increase'][$city][$town];
                    }
                }
                $daySumDay -= 86400;
            }
            $onset[$y]['avg7'][$city][$town] = round($daySum7 / 7, 2);
        }
    }

    $targetFile = $pathOnset . '/' . $y . '.json';
    $fileToWrite = true;
    if (file_exists($targetFile)) {
        $json = json_decode(file_get_contents($targetFile), true);
        if ($json['data'] === $onset[$y]['data']) {
            $fileToWrite = false;
        }
    }
    if ($fileToWrite) {
        file_put_contents($targetFile, json_encode($onset[$y], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        file_put_contents($pathOnset . '/' . $onset[$y]['meta']['day'] . '.json', json_encode($onset[$y], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

foreach ($towns as $k => $data) {
    foreach($data['days'] AS $d => $v) {
        if($d > $latestDay) {
            unset($data['days'][$d]);
        }
    }
    file_put_contents($pathTown . '/' . $k . '.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
