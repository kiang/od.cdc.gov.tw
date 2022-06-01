<?php
$basePath = dirname(__DIR__);
$dailyFile = $basePath . '/raw/od/Day_Confirmation_Age_County_Gender_19CoV.csv';

$pathConfirmed = $basePath . '/data/od/confirmed';
$timeBegin = strtotime('2021-03-31');
$timeEnd = strtotime('-1 days');
$dayBegin = date('Ymd', $timeBegin);
$now = date('Y-m-d H:i:s');

$confirmed = [
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
        $y = substr($data['個案研判日'], 0, 4);
        $data['個案研判日'] = str_replace('/', '', $data['個案研判日']);
        if ($y != 2021 || $data['個案研判日'] > $dayBegin) {
            continue;
        }
        $confirmed['meta']['total'] += $data['確定病例數'];
        if (!isset($confirmed['data'][$data['縣市']])) {
            $confirmed['data'][$data['縣市']] = [];
            $confirmed['rate'][$data['縣市']] = [];
            $confirmed['increase'][$data['縣市']] = [];
            $confirmed['avg7'][$data['縣市']] = [];
        }
        if (!isset($confirmed['data'][$data['縣市']][$data['鄉鎮']])) {
            $confirmed['data'][$data['縣市']][$data['鄉鎮']] = 0;
            $confirmed['rate'][$data['縣市']][$data['鄉鎮']] = 0.0;
            $confirmed['increase'][$data['縣市']][$data['鄉鎮']] = 0;
            $confirmed['avg7'][$data['縣市']][$data['鄉鎮']] = 0.0;
        }
        $confirmed['data'][$data['縣市']][$data['鄉鎮']] += $data['確定病例數'];
    }
}

$pool = [];
foreach (glob($basePath . '/data/od/town/*.json') as $jsonFile) {
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
    $confirmed['meta']['day'] = $day;
    if ($i !== $timeBegin) {
        foreach ($pool[$day] as $city => $data1) {
            foreach ($data1 as $town => $count) {
                if (isset($confirmed['increase'][$city][$town])) {
                    $confirmed['increase'][$city][$town] = 0;
                    $confirmed['avg7'][$city][$town] = 0.0;
                }

                if ($count > 0) {
                    if (!isset($confirmed['data'][$city])) {
                        $confirmed['data'][$city] = [];
                        $confirmed['rate'][$city] = [];
                        $confirmed['increase'][$city] = [];
                    }
                    if (!isset($confirmed['data'][$city][$town])) {
                        $confirmed['data'][$city][$town] = 0;
                        $confirmed['rate'][$city][$town] = 0.0;
                        $confirmed['increase'][$city][$town] = 0;
                    }
                    $confirmed['increase'][$city][$town] = $count;

                    if ($confirmed['data'][$city][$town] > 0) {
                        $confirmed['rate'][$city][$town] = round($count / $confirmed['data'][$city][$town], 1);
                    } else {
                        $confirmed['rate'][$city][$town] = 1.0;
                    }
                    $confirmed['data'][$city][$town] += $count;
                    $confirmed['meta']['total'] += $count;

                    $daySum7 = 0;
                    $daySumDay = $i;
                    for ($j = 0; $j < 7; $j++) {
                        $dayKey = date('Ymd', $daySumDay);
                        if (isset($pool[$dayKey][$city][$town])) {
                            $daySum7 += $pool[$dayKey][$city][$town];
                        }
                        $daySumDay -= 86400;
                    }
                    $confirmed['avg7'][$city][$town] = round($daySum7 / 7, 1);
                } else {
                    if (isset($confirmed['rate'][$city][$town])) {
                        $confirmed['rate'][$city][$town] = 0.0;
                    }
                }
            }
        }
    }
    ksort($confirmed['data']);
    ksort($confirmed['rate']);
    ksort($confirmed['increase']);
    ksort($confirmed['avg7']);
    foreach ($confirmed['data'] as $city => $data2) {
        ksort($confirmed['data'][$city]);
        ksort($confirmed['rate'][$city]);
        ksort($confirmed['increase'][$city]);
        ksort($confirmed['avg7'][$city]);
    }

    file_put_contents($pathConfirmed . '/' . date('Ymd', $i) . '.json', json_encode($confirmed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
