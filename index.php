<?php

    /**
     * Configuration
     */
    $apiUrl = 'http://api.nbp.pl/api/cenyzlota/%s/%s/';

    $toInvest = array_key_exists('invest', $_GET) && is_numeric($_GET['invest']) ? $_GET['invest'] : 600000;

    /**
     * Collect gold price data from API
     */
    $stocks = [];

    $dates['from'] = strtotime('-5 years');
    $dates['to'] = strtotime('-1 day', strtotime('+1 year', $dates['from']));

    $minMax = [NULL, NULL, $dates['from'], $dates['from']];

    for($i = 0; $i < 5; ++$i) {
        $response = file_get_contents(sprintf($apiUrl, date('Y-m-d', $dates['from']), date('Y-m-d', $dates['to'])));
        if($response === FALSE) {
            die('api connection error');
        }
        $response = json_decode($response, true);

        foreach($response as $e) {
            /**
             * structure of array element:
             * 0 - price
             * 1 - price delta
             */
            $stocks[$e['data']] = [$e['cena'], count($stocks) ? ($e['cena'] - end($stocks)[0]) : 0];

            if($minMax[0] === NULL || $minMax[0] < $e['cena']) {
                $minMax[0] = $e['cena'];
                $minMax[2] = strtotime($e['data']); 
            }
            if($minMax[1] === NULL || $minMax[1] > $e['cena']) {
                $minMax[1] = $e['cena'];
                $minMax[3] = strtotime($e['data']);
            }
        }

        foreach($dates as &$date) {
            $date = strtotime('+1 year', $date);
        }
    }

    /**
     * Quickest solution to found
     * If global min is earlier than global max price of gold
     */
    if($minMax[3] > $minMax[2]) {
        $bought = $toInvest / $minMax[0];
        $delta = $minMax[1] - $minMax[0];
        $earn = $bought * $delta;
        die(sprintf("buy %s %.2f gold (%.2f PLN) and sell %s it (%.2f PLN): %.2f PLN delta; you earn %.2f PLN net by investing %.2f PLN", date('Y-m-d,', $minMax[2]), $bought, $minMax[0], date('Y-m-d,', $minMax[3]), $minMax[1], $delta, $earn, $toInvest).'<br/>');
    }


    $stocks = array_reverse($stocks);

    reset($stocks);

    /**
     * Get monotonicities of gold price array
     * Monotonicity structure:
     * 0 - signum
     * 1 - delta
     * 2 - min
     * 3 - max
     * 4 - date from
     * 5 - date to
     */
    $monotonicities = [];
    $tmp = array_key_first($stocks);
    $monotonicity = [0, 0, 0, 0, $tmp, $tmp];

    foreach($stocks as $date=>$stock) {
        if($monotonicity[0] != ($stock[1] <=> 0)) {
            /**
             * End and register old monotonicity; start new monotonicity
             */
            if($monotonicity[0] != 0) {
                $monotonicities[] = $monotonicity;
            }
            $monotonicity = [$stock[1] <=> 0, $stock[1], $stock[0], $stock[0], strtotime($date), strtotime($date)];
        }
        else {
            /**
             * Append current monotonicity
             */
            $monotonicity[1] += $stock[1];
            if($stock[0] < $monotonicity[2]) {
                $monotonicity[2] = $stock[0];
            }
            else if($stock[0] > $monotonicity[3]) {
                $monotonicity[3] = $stock[0];
            }
            $monotonicity[5] = strtotime($date);
        }
    }

    /**
     * Split arrays of monotonicities to positive and negative array
     */
    $negativeMonotonicities = array_filter($monotonicities, function($monotonicity) { return ($monotonicity[0] == -1); });
    $positiveMonotonicities = array_filter($monotonicities, function($monotonicity) { return ($monotonicity[0] == 1); });

    $deltas = [];

    /**
     * Get available investments in gold
     */
    foreach($negativeMonotonicities as $nMonotonicity) {
        $min = $nMonotonicity[2];
        $max = $nMonotonicity[3];
        $from = $nMonotonicity[4];
        foreach($positiveMonotonicities as $pMonotonicity) {
            if($pMonotonicity[4] < $nMonotonicity[5]) {
                continue;
            }
            if($pMonotonicity[3] > $max) {
                $max = $pMonotonicity[3];
                $to = $pMonotonicity[5];
            }
        }
        $deltas[] = [$min, $max, $max - $min, $from, $to];
    }

    /**
     * Sort investments in gold DESC
     */
    usort($deltas, function($a, $b) { return $b[2] - $a[2]; });

    /**
     * Get first (best) investment i ngold
     */
    $delta = $deltas[0];

    $bought = $toInvest / $delta[0];
    $earn = $bought * $delta[2];
    die(sprintf("buy %s %.2f gold (%.2f PLN) and sell %s it (%.2f PLN): %.2f PLN delta; you earn %.2f PLN net by investing %.2f PLN", date('Y-m-d,', $delta[3]), $bought, $delta[0], date('Y-m-d,', $delta[4]), $delta[1], $delta[2], $earn, $toInvest).'<br/>');

?>