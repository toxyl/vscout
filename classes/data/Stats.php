<?php
class Stats
{
    static private function retrieve()
    {
        $stats = Data::stats("`1d_1xx`, `1d_2xx`, `1d_3xx`, `1d_4xx`, `1d_5xx`, `1h_1xx`, `1h_2xx`, `1h_3xx`, `1h_4xx`, `1h_5xx`, `15m_1xx`, `15m_2xx`, `15m_3xx`, `15m_4xx`, `15m_5xx`, `5m_1xx`, `5m_2xx`, `5m_3xx`, `5m_4xx`, `5m_5xx`");
        $stats = $stats[0] ?? [];

        return [ 
            'status_codes' => [
                '1d'         => [
                    '1xx'        => $stats[0] ?? 0,
                    '2xx'        => $stats[1] ?? 0,
                    '3xx'        => $stats[2] ?? 0,
                    '4xx'        => $stats[3] ?? 0,
                    '5xx'        => $stats[4] ?? 0,
                ],
                '1h'         => [
                    '1xx'        => $stats[5] ?? 0,
                    '2xx'        => $stats[6] ?? 0,
                    '3xx'        => $stats[7] ?? 0,
                    '4xx'        => $stats[8] ?? 0,
                    '5xx'        => $stats[9] ?? 0,
                ],
                '15m'         => [
                    '1xx'        => $stats[10] ?? 0,
                    '2xx'        => $stats[11] ?? 0,
                    '3xx'        => $stats[12] ?? 0,
                    '4xx'        => $stats[13] ?? 0,
                    '5xx'        => $stats[14] ?? 0,
                ],
                '5m'         => [
                    '1xx'        => $stats[15] ?? 0,
                    '2xx'        => $stats[16] ?? 0,
                    '3xx'        => $stats[17] ?? 0,
                    '4xx'        => $stats[18] ?? 0,
                    '5xx'        => $stats[19] ?? 0,
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

    static private function format_row($data, $cell_type='td')
    {
        $fmt = [];
        $val = [];
        foreach ($data as $item) 
        {
            $fmt[] = $item[0] ?? '----'; 
            $val[] = $item[1] ?? 'null'; 
        }
        $fmt = Router::is_cli() ? implode(" ", $fmt) : "<tr><$cell_type>" . implode("</$cell_type><$cell_type>", $fmt) . "</$cell_type></tr>";
        return vsprintf($fmt, $val);
    }

    static private function header_format($data)
    {
        return Router::is_cli() ? ANSI::string("%b8>%f7> " . self::format_row($data, 'th') . " %rst>") : self::format_row($data, 'th');
    }

    static private function line_format($data)
    {
        return ' ' . self::format_row($data);
    }

    static private function header(&$out, $lbl, $lblt5m = '', $lblt15m = '', $lblt1h = '', $lblt1d = '', $lblspeed = '', $lblspeedday = '')
    {
        $out[] = self::header_format([
                    ["%12s", $lbl],
                    ["%10s", $lblt5m],
                    ["%10s", $lblt15m],
                    ["%10s", $lblt1h],
                    ["%10s", $lblt1d],
                    ["%33s", $lblspeed],
                    ["%33s", $lblspeedday],
                    ["%3s", '+/-'],
                ]);
    }

    static private function line_simple(&$out, $lbl, $v)
    {
        $out[] = self::line_format([
                    ["%12s", $lbl],
                    ["%10d", $v]
                ]);
    }

    static private function line_status_code(&$out, $lbl, $field, $t5m, $t15m, $t1h, $t1d)
    {
        $v = [ 
            (is_array($t5m)  ? $t5m[$field]  : $t5m), 
            (is_array($t15m) ? $t15m[$field] : $t15m), 
            (is_array($t1h)  ? $t1h[$field]  : $t1h), 
            (is_array($t1d)  ? $t1d[$field]  : $t1d), 
            (is_array($t1h)  ? $t1h[$field]  : $t1h) / 60, 
            (is_array($t1h)  ? $t1h[$field]  : $t1h) * 24 
        ];
        $delta = $v[3] - $v[5];
        $out[] = self::line_format([
                    ["%12s",           $lbl],  
                    ["%10s",           number_format($v[0])],
                    ["%10s",           number_format($v[1])],
                    ["%10s",           number_format($v[2])],
                    ["%10s",           number_format($v[3])],
                    ["%10.2f / min",   $v[4]],
                    ["%10d / day",     $v[5]],
                    ["%3s",             $delta > 0 ? '-' : ($delta == 0 ? ' ' : '+')]
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

        $response[] = Router::is_cli() ? "\n" : "<table>";

        self::header($response, 'Status Codes', '5m', '15m', '1h', '1d', 'average speed / min (based on 1h)', 'average speed / day (based on 1h)');

        $v1xx = self::line_status_code($response, '1xx',   '1xx', $s5m, $s15m, $s1h, $s1d);
        $v2xx = self::line_status_code($response, '2xx',   '2xx', $s5m, $s15m, $s1h, $s1d);
        $v3xx = self::line_status_code($response, '3xx',   '3xx', $s5m, $s15m, $s1h, $s1d);
        $v4xx = self::line_status_code($response, '4xx',   '4xx', $s5m, $s15m, $s1h, $s1d);
        $v5xx = self::line_status_code($response, '5xx',   '5xx', $s5m, $s15m, $s1h, $s1d);

        $vAll = [
            '5m'    =>  $v1xx[0] + $v2xx[0] + $v3xx[0] + $v4xx[0] + $v5xx[0],
            '15m'   =>  $v1xx[1] + $v2xx[1] + $v3xx[1] + $v4xx[1] + $v5xx[1],
            '1h'    =>  $v1xx[2] + $v2xx[2] + $v3xx[2] + $v4xx[2] + $v5xx[2],
            '1d'    =>  $v1xx[3] + $v2xx[3] + $v3xx[3] + $v4xx[3] + $v5xx[3],
        ];
        self::line_status_code($response, 'total', '', $vAll['5m'], $vAll['15m'], $vAll['1h'], $vAll['1d']);

        $response[] = Router::is_cli() ? "\n" : "</table>";

        return implode("\n", $response);
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

    static public function print_formatted()
    {
        return self::print(self::retrieve());
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

            if (Router::is_cli())
                Response::auto(self::print_formatted());
            else
                Response::text(self::print_formatted());

            if (!$follow)
                break;

            sleep(STATS_UPDATE);
        }
    }
}
