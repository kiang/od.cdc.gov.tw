<?php
$basePath = dirname(__DIR__);
$dailyFile = $basePath . '/raw/od2024/Age_County_Gender_day_19Cov.csv';

$pathOnset = $basePath . '/data/od2024/onset';
$timeBegin = strtotime('2021-03-31');
$timeEnd = strtotime('-1 days');
$dayBegin = date('Ymd', $timeBegin);
$now = date('Y-m-d H:i:s');

$onset = [
    'meta' => [
        'day' => $dayBegin,
        'total' => 0,
        'modified' => $now,
    ],
    'data' => [],
    'rate' => [],
    'increase' => [],
    'avg7' => [],
];

$fh = fopen($dailyFile, 'r');
$head = fgetcsv($fh, 2048);
while ($line = fgetcsv($fh, 2048)) {
    $data = array_combine($head, $line);
    $data['鄉鎮'] = str_replace(['　', ' '], '', $data['鄉鎮']);
    if ($data['是否為境外移入'] !== '是') {
        $y = substr($data['發病日'], 0, 4);
        $data['發病日'] = str_replace('/', '', $data['發病日']);
        if ($y != 2021 || $data['發病日'] > $dayBegin) {
            continue;
        }
        $onset['meta']['total'] += $data['確定病例數'];
        if (!isset($onset['data'][$data['縣市']])) {
            $onset['data'][$data['縣市']] = [];
            $onset['rate'][$data['縣市']] = [];
            $onset['increase'][$data['縣市']] = [];
            $onset['avg7'][$data['縣市']] = [];
        }
        if (!isset($onset['data'][$data['縣市']][$data['鄉鎮']])) {
            $onset['data'][$data['縣市']][$data['鄉鎮']] = 0;
            $onset['rate'][$data['縣市']][$data['鄉鎮']] = 0.0;
            $onset['increase'][$data['縣市']][$data['鄉鎮']] = 0;
            $onset['avg7'][$data['縣市']][$data['鄉鎮']] = 0.0;
        }
        $onset['data'][$data['縣市']][$data['鄉鎮']] += $data['確定病例數'];
    }
}

$pool = [];
foreach (glob($basePath . '/data/od2024/onset/town/*.json') as $jsonFile) {
    $p = pathinfo($jsonFile);
    $city = mb_substr($p['filename'], 0, 3, 'utf-8');
    $town = mb_substr($p['filename'], 3, null, 'utf-8');
    $json = json_decode(file_get_contents($jsonFile), true);
    foreach ($json['days'] as $day => $count) {
        if (!isset($pool[$day])) {
            $pool[$day] = [];
        }
        if (!isset($pool[$day][$city])) {
            $pool[$day][$city] = [];
        }
        if (!isset($pool[$day][$city][$town])) {
            $pool[$day][$city][$town] = 0;
        }
        $pool[$day][$city][$town] += $count;
    }
}

for ($i = $timeBegin; $i <= $timeEnd; $i += 86400) {
    $day = date('Ymd', $i);
    if (!isset($pool[$day])) {
        continue;
    }
    $onset['meta']['day'] = $day;
    if ($i !== $timeBegin) {
        foreach ($pool[$day] as $city => $data1) {
            foreach ($data1 as $town => $count) {
                if (isset($onset['increase'][$city][$town])) {
                    $onset['increase'][$city][$town] = 0;
                    $onset['avg7'][$city][$town] = 0.0;
                }

                if ($count > 0) {
                    if (!isset($onset['data'][$city])) {
                        $onset['data'][$city] = [];
                        $onset['rate'][$city] = [];
                        $onset['increase'][$city] = [];
                    }
                    if (!isset($onset['data'][$city][$town])) {
                        $onset['data'][$city][$town] = 0;
                        $onset['rate'][$city][$town] = 0.0;
                        $onset['increase'][$city][$town] = 0;
                    }
                    $onset['increase'][$city][$town] = $count;

                    if ($onset['data'][$city][$town] > 0) {
                        $onset['rate'][$city][$town] = round($count / $onset['data'][$city][$town], 1);
                    } else {
                        $onset['rate'][$city][$town] = 1.0;
                    }
                    $onset['data'][$city][$town] += $count;
                    $onset['meta']['total'] += $count;

                    $daySum7 = 0;
                    $daySumDay = $i;
                    for ($j = 0; $j < 7; $j++) {
                        $dayKey = date('Ymd', $daySumDay);
                        if (isset($pool[$dayKey][$city][$town])) {
                            $daySum7 += $pool[$dayKey][$city][$town];
                        }
                        $daySumDay -= 86400;
                    }
                    $onset['avg7'][$city][$town] = round($daySum7 / 7, 1);
                } else {
                    if (isset($onset['rate'][$city][$town])) {
                        $onset['rate'][$city][$town] = 0.0;
                    }
                }
            }
        }
    }
    ksort($onset['data']);
    ksort($onset['rate']);
    ksort($onset['increase']);
    ksort($onset['avg7']);
    foreach ($onset['data'] as $city => $data2) {
        ksort($onset['data'][$city]);
        ksort($onset['rate'][$city]);
        ksort($onset['increase'][$city]);
        ksort($onset['avg7'][$city]);
    }

    file_put_contents($pathOnset . '/' . date('Ymd', $i) . '.json', json_encode($onset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
