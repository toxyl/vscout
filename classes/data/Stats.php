<?php
class Stats
{
    static private function retrieve(string $fields = '*')
    {
        $stats = Data::stats($fields);
        $stats = $stats[0] ?? [];

        return [ 
            'domains'      => [
                'active'     => $stats[0] ?? 0,
                'firewalled' => $stats[1] ?? 0,
                'dead'       => $stats[2] ?? 0,
            ], 
            'status_codes' => [
                'all'         => [
                    '1xx'        => $stats[3] ?? 0,
                    '2xx'        => $stats[4] ?? 0,
                    '3xx'        => $stats[5] ?? 0,
                    '4xx'        => $stats[6] ?? 0,
                    '5xx'        => $stats[7] ?? 0,
                ],
                '1d'         => [
                    '1xx'        => $stats[8] ?? 0,
                    '2xx'        => $stats[9] ?? 0,
                    '3xx'        => $stats[10] ?? 0,
                    '4xx'        => $stats[11] ?? 0,
                    '5xx'        => $stats[12] ?? 0,
                ],
                '1h'         => [
                    '1xx'        => $stats[13] ?? 0,
                    '2xx'        => $stats[14] ?? 0,
                    '3xx'        => $stats[15] ?? 0,
                    '4xx'        => $stats[16] ?? 0,
                    '5xx'        => $stats[17] ?? 0,
                ],
                '15m'         => [
                    '1xx'        => $stats[18] ?? 0,
                    '2xx'        => $stats[19] ?? 0,
                    '3xx'        => $stats[20] ?? 0,
                    '4xx'        => $stats[21] ?? 0,
                    '5xx'        => $stats[22] ?? 0,
                ],
                '5m'         => [
                    '1xx'        => $stats[23] ?? 0,
                    '2xx'        => $stats[24] ?? 0,
                    '3xx'        => $stats[25] ?? 0,
                    '4xx'        => $stats[26] ?? 0,
                    '5xx'        => $stats[27] ?? 0,
                ],
            ], 
        ];
    }

    /**
     * Returns the current load averages as associative array ([ "1m" => ..., "5m" => ..., "15m" => ...]).
     * The values are adjusted for CPU cores, so on a 64-core system 1.0 would mean that all cores are 100% utilized, 
     * just as it would on a single-core system.
     */
    static public function load_averages()
    {
        # get number of cores
        $cores = intval(CommandIO::exec("nproc"));
        # get load averages
        $avgs = explode("-", CommandIO::exec('cat /proc/loadavg | awk \'{printf("%s-%s-%s",$1,$2,$3) }\''));
        return [
             "1m" => floatval($avgs[0]) / $cores,
             "5m" => floatval($avgs[1]) / $cores,
            "15m" => floatval($avgs[2]) / $cores,
        ];
    }

    static private function format_row($data)
    {
        $fmt = [];
        $val = [];
        foreach ($data as $item) 
        {
            $fmt[] = $item[0] ?? '----'; 
            $val[] = $item[1] ?? 'null'; 
        }
        $fmt = implode(" ", $fmt);
        return vsprintf($fmt, $val);
    }

    static private function header_format($data)
    {
        return ANSI::string("%b8>%f7> " . self::format_row($data) . " %rst>");
    }

    static private function line_format($data)
    {
        return ' ' . self::format_row($data);
    }

    static private function header(&$out, $lbl, $lbltall = '', $lblt5m = '', $lblt15m = '', $lblt1h = '', $lblt1d = '', $lblspeed = '')
    {
        $out[] = self::header_format([
                    ["%12s", $lbl],
                    ["%10s", $lbltall],
                    ["%10s", $lblt5m],
                    ["%10s", $lblt15m],
                    ["%10s", $lblt1h],
                    ["%10s", $lblt1d],
                    ["%35s", $lblspeed]
                ]);
    }

    static private function line_simple(&$out, $lbl, $v)
    {
        $out[] = self::line_format([
                    ["%12s", $lbl],
                    ["%10d", $v]
                ]);
    }

    static private function line_status_code(&$out, $lbl, $field, $tall, $t5m, $t15m, $t1h, $t1d)
    {
        $v = [ 
            (is_array($tall) ? $tall[$field] : $tall), 
            (is_array($t5m)  ? $t5m[$field]  : $t5m), 
            (is_array($t15m) ? $t15m[$field] : $t15m), 
            (is_array($t1h)  ? $t1h[$field]  : $t1h), 
            (is_array($t1d)  ? $t1d[$field]  : $t1d), 
            (is_array($t1h)  ? $t1h[$field]  : $t1h) / 60, 
            (is_array($t1h)  ? $t1h[$field]  : $t1h) * 24 
        ];
        $delta = $v[4] - $v[6];
        $out[] = self::line_format([
                    ["%12s",           $lbl], 
                    ["%10d",           $v[0]], 
                    ["%10d",           $v[1]],
                    ["%10d",           $v[2]], 
                    ["%10d",           $v[3]], 
                    ["%10d",           $v[4]], 
                    ["%10.2f / min",   $v[5]],
                    ["%10d / day",     $v[6]],
                    ["%s",             $delta > 0 ? '-' : ($delta == 0 ? ' ' : '+')]
                ]);
        return $v;
    }

    static private function print($s)
    {
        $response = [];

        $s5m  = $s['status_codes']['5m'];
        $s15m = $s['status_codes']['15m'];
        $s1h  = $s['status_codes']['1h'];
        $s1d  = $s['status_codes']['1d'];
        $sall = $s['status_codes']['all'];

        $response[] = "\n";

        $load = self::load_averages();

        $response[] = self::header_format([
            ['%10s',    'Workers'],
            ['%27s' ,   'Load Average:'],
            ['%6s',     '1m'],
            ['%6s',     '5m'],
            ['%6s',     '15m'],
            ['%22s',    'Domains:'],
            ['%6s',     'Active'],
            ['%6s',     'FW'],
            ['%6s',     'Dead']
        ]);

        $response[] = self::line_format([
            ['%10d',     Daemon::active()],
            ['%27s',     ''],
            ['%5d%%',    $load['1m'] * 100],
            ['%5d%%',    $load['5m'] * 100],
            ['%5d%%',    $load['15m'] * 100],
            ['%22s',     ''],
            ['%6d',      $s['domains']['active']],
            ['%6d',      $s['domains']['firewalled']],
            ['%6d',      $s['domains']['dead']]
        ]);

        $response[] = "\n";

        self::header($response, 'Status Codes', 'Total', '5m', '15m', '1h', '1d', 'average speed (based on 1h)');

        $v1xx = self::line_status_code($response, '1xx',   '1xx', $sall, $s5m, $s15m, $s1h, $s1d);
        $v2xx = self::line_status_code($response, '2xx',   '2xx', $sall, $s5m, $s15m, $s1h, $s1d);
        $v3xx = self::line_status_code($response, '3xx',   '3xx', $sall, $s5m, $s15m, $s1h, $s1d);
        $v4xx = self::line_status_code($response, '4xx',   '4xx', $sall, $s5m, $s15m, $s1h, $s1d);
        $v5xx = self::line_status_code($response, '5xx',   '5xx', $sall, $s5m, $s15m, $s1h, $s1d);

        $vAll = [
            'total' =>  $v1xx[0] + $v2xx[0] + $v3xx[0] + $v4xx[0] + $v5xx[0],
            '5m'    =>  $v1xx[1] + $v2xx[1] + $v3xx[1] + $v4xx[1] + $v5xx[1],
            '15m'   =>  $v1xx[2] + $v2xx[2] + $v3xx[2] + $v4xx[2] + $v5xx[2],
            '1h'    =>  $v1xx[3] + $v2xx[3] + $v3xx[3] + $v4xx[3] + $v5xx[3],
            '1d'    =>  $v1xx[4] + $v2xx[4] + $v3xx[4] + $v4xx[4] + $v5xx[4],
        ];
        self::line_status_code($response, 'total', '', $vAll['total'], $vAll['5m'], $vAll['15m'], $vAll['1h'], $vAll['1d']);

        $response[] = "\n";

        Response::auto(implode("\n", $response));
    }

    static public function average_speed()
    {
        $vals = Data::stats('`1h_1xx`, `1h_2xx`, `1h_3xx`, `1h_4xx`, `1h_5xx`')[0] ?? [];
        $total = 0;
        foreach ($vals as $v) 
        {
            $total += intval($v);
        }
        return $total / 60.0;
    }

    static public function show($follow = true)
    {
        while (true) 
        {
            if ($follow && Router::$mode == Router::CLI)
            {
                echo "\033[H\033[J"; // clear screen
            }
            else if ($follow && Router::$mode != Router::CLI)
            {
                header("Refresh:" . STATS_UPDATE);
                $follow = false;
            }

            self::print(self::retrieve());

            if (!$follow)
                break;

            sleep(STATS_UPDATE);
        }
    }
}
