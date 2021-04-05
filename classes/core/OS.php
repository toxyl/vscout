<?php
class OS 
{
    /**
     * Takes a value in MB and returns an array with the best fitting value and the unit.
     * For example: $v = 1024 yields "1.00 GB", $v = 0.5 yields "500 KB".
     */
    static private function format_value_human_readable($v)
    {
        if ($v < 0.0009765625)
            return [
                "unit" => "B",
                "value" => round($v * 1024 * 1024, 2)
            ];

        if ($v < 1)
            return [
                "unit" => "KB",
                "value" => round($v * 1024, 2)
            ];

        if ($v >= 1073741824) 
            return [
                "unit" => "PB",
                "value" => round($v / 1024 / 1024 / 1024, 2)
            ]; 

        if ($v >= 1048576) 
            return [
                "unit" => "TB",
                "value" => round($v / 1024 / 1024, 2)
            ]; 

        if ($v >= 1024) 
            return [
                "unit" => "GB",
                "value" => round($v / 1024, 2)
            ]; 

        return [
            "unit" => "MB",
            "value" => round($v, 2)
        ];
    }

    static public function traffic()
    {
        $iface = SERVER_INTERFACE;
        $traffic = explode("\n", trim(`cat /proc/net/dev | grep $iface | awk -v OFMT='%.10f' '{ print $2/1024/1024 } END{ print $10/1024/1024 } END{ print ($2+$10)/1024/1024 }'`));
        $traffic[] = time();

        foreach ($traffic as &$t) 
        {
            $t = floatval($t);
        }
        
        $file_traffic = '/tmp/traffic_' . trim(`whoami`) . '.json';
        $old_traffic = file_exists($file_traffic) ? json_decode(file_get_contents($file_traffic)) : $traffic;
        file_put_contents($file_traffic, json_encode($traffic));

        $t_rx = $traffic[0];
        $t_tx = $traffic[1];
        $t_total = $traffic[2];

        $td_t = max(1,$traffic[3] - $old_traffic[3]); // in s
        $td_rx = ($t_rx - $old_traffic[0]) / $td_t; // mega bytes per second
        $td_tx = ($t_tx - $old_traffic[1]) / $td_t; // mega bytes per second
        $td_total = ($t_total - $old_traffic[2]) / $td_t; // mega bytes per second

        return [
            "accumulated" => [
                "total" => self::format_value_human_readable($t_total),
                "rx"    => self::format_value_human_readable($t_rx),
                "tx"    => self::format_value_human_readable($t_tx),
            ],
            "average" => [
                "total" => self::format_value_human_readable($td_total),
                "rx"    => self::format_value_human_readable($td_rx),
                "tx"    => self::format_value_human_readable($td_tx),
            ],
        ];
    }

    static public function load_average()
    {
        $la = explode("\n", trim(`cat /proc/loadavg | perl -pe 's@([^\\s]+) ([^\\s]+) ([^\\s]+) .*@\\1\n\\2\n\\3\n@g'`));
        $cores = intval(CommandIO::exec("nproc --all"));
        return [
            "1m" => round(($la[0] / $cores) * 100, 2), 
            "5m" => round(($la[1] / $cores) * 100, 2), 
            "15m" => round(($la[2] / $cores) * 100, 2), 
        ];
    }

    static public function public_ip()
    {
        $ip = CommandIO::exec("curl --silent " . IP_CHECK_URL);
        $blacklisted = in_array($ip, explode("\n", DataFile::read(FILE_IP_BLACKLIST)));
        $country = $ip == '' ? 'N/A' : substr(CommandIO::exec("whois $ip | grep country -i -m 1 | cut -d ':' -f 2 | xargs"), 0, 3);

        return [
            "ip" => $ip,
            "blacklisted" => $blacklisted,
            "country" => $country
        ];
    }

    static public function ips()
    {
        return explode(' ', trim(`hostname -I`));
    }

    static public function user()
    {
        return trim(`whoami`);
    }

    static public function type()
    {
        return trim(`uname -som`);
    }

    static public function pwd()
    {
        return trim(`pwd`);
    }

    static public function memory()
    {
        $mem = explode("\n", trim(`cat /proc/meminfo | grep Mem | awk -v OFMT='%.10f' '{print $2/1024}'`));

        return [
            'total' => self::format_value_human_readable($mem[0]),
            'free'  => self::format_value_human_readable($mem[1]), 
            'used' => self::format_value_human_readable(floatval($mem[0]) -floatval($mem[1]))
        ];
    }

    static public function diskspace()
    {
        $free = floatval(trim(`df -B1M | grep -v tmpfs | grep -v loop | grep -v udev | grep -v none | grep -v '/mnt' | awk '{s+=$4} END{print s;}'`));
        $used = floatval(trim(`df -B1M | grep -v tmpfs | grep -v loop | grep -v udev | grep -v none | grep -v '/mnt' | awk '{s+=$3} END{print s;}'`));
        $total = $free + $used;
        
        return [
            "total" => self::format_value_human_readable($total),
            "free" => self::format_value_human_readable($free),
            "used" => self::format_value_human_readable($used)
        ];
    }

    static public function cpu()
    {
        return [
            "cores" => intval(trim(`nproc --all`)),
            "MHz" => floatval(trim(`cat /proc/cpuinfo | grep MHz | awk '{s+=$4;c+=1} END{print s/c}'`))
        ];
    }

    static public function workers_count()
    {
        $wcmd = WORKER_CMD;
        $name = NAME;
        return intval(trim(`pgrep -a $name | grep '$wcmd' | wc -l`));
    }

    static public function status($as_html = true)
    {
        $traffic = self::traffic();
        $load_avg = self::load_average();
        $public_ip = self::public_ip();
        $memory = self::memory();
        $disk = self::diskspace();
        $workers = self::workers_count();
        $cpu = self::cpu();
        $click_avg = Data::avg_clicks();
        $click_total = Data::total_clicks();

        $domains = Data::stats("active, firewalled, dead");
        $domains = $domains[0] ?? [ 0, 0, 0 ];
        $domains[] = $domains[0] + $domains[1] + $domains[2];

        $fmtOut = $as_html ? function($v) { return str_replace("\n", '<br>', str_replace(' ', '&nbsp;', $v)); } : function($v) { return $v; };
        $fmtRow = function ($name, $value, $unit, $is_float = true) { return sprintf('%6s: ' . ($is_float ? '%15s' : '%12s   ' ). ' %s', $name, number_format($value, $is_float ? 2 : 0), $unit)  . "\n"; };

        $sClicks = $fmtOut(
            $fmtRow( "sec", $click_avg["avg_s"], "req/s") .
            $fmtRow( "min", $click_avg["avg_m"], "req/m") .
            $fmtRow( "hour", $click_avg["avg_h"], "req/h") .
            $fmtRow( "day", $click_avg["avg_d"], "req/d")
        );

        $sDomains = $fmtOut(
            $fmtRow( "Total",       $domains[3], "", false) .
            $fmtRow( "Active",      $domains[0], "", false) .
            $fmtRow( "FW",          $domains[1], "", false) .
            $fmtRow( "Dead",        $domains[2], "", false)
        );

        $sClicksTotal = $fmtOut(
            $fmtRow( "Total",       $click_total["total"], "", false) .
            $fmtRow( "1xx",         $click_total["total_1xx"], "", false) .
            $fmtRow( "2xx",         $click_total["total_2xx"], "", false) .
            $fmtRow( "3xx",         $click_total["total_3xx"], "", false) .
            $fmtRow( "4xx",         $click_total["total_4xx"], "", false) .
            $fmtRow( "5xx",         $click_total["total_5xx"], "", false)
        );

        $sCPU = $fmtOut(sprintf('%d cores @ %d MHz', $cpu['cores'], $cpu['MHz']) . "\n");

        $sLoad = $fmtOut(
            $fmtRow( "1m", $load_avg["1m"], "%") .
            $fmtRow( "5m", $load_avg["5m"], "%") .
            $fmtRow("15m", $load_avg["15m"], "%")
        );

        $sDisk = $fmtOut( 
            $fmtRow("Total", $disk["total"]['value'], $disk["total"]['unit']) .
            $fmtRow( "Used", $disk["used"]['value'],  $disk["used"]['unit']) .
            $fmtRow( "Free", $disk["free"]['value'],  $disk["free"]['unit'])
        );

        $sMem = $fmtOut( 
            $fmtRow("Total", $memory["total"]['value'], $memory["total"]['unit']) .
            $fmtRow( "Used", $memory["used"]['value'],  $memory["used"]['unit']) .
            $fmtRow( "Free", $memory["free"]['value'],  $memory["free"]['unit'])
        );

        $sTrafficAcc = $fmtOut( 
            $fmtRow("Total", $traffic['accumulated']["total"]['value'], $traffic['accumulated']["total"]['unit']) .
            $fmtRow(   "RX", $traffic['accumulated']["rx"]['value'],    $traffic['accumulated']["rx"]['unit']) .
            $fmtRow(   "TX", $traffic['accumulated']["tx"]['value'],    $traffic['accumulated']["tx"]['unit'])
        );

        $sTrafficAvg = $fmtOut( 
            $fmtRow("Total", $traffic['average']["total"]['value'], $traffic['average']["total"]['unit'] . "/s") .
            $fmtRow(   "RX", $traffic['average']["rx"]['value'],    $traffic['average']["rx"]['unit'] . "/s") .
            $fmtRow(   "TX", $traffic['average']["tx"]['value'],    $traffic['average']["tx"]['unit'] . "/s")
        );

        $sIP = $public_ip['ip'] == '' ? 'N/A' : ($public_ip['blacklisted'] ? '[BLACKLISTED] ' . $public_ip['ip'] : $public_ip['ip']) . ' (' . $public_ip['country'] . ')';

        if (TOR_MODE && $public_ip['ip'] == '')
        {
            $sIP = 'Waiting for new circuit...';
        }

        return [ 
            "current_dir" => self::pwd(),
            "os" => self::type(),
            "user" => self::user(),
            "traffic" => $sTrafficAcc,
            "avgtraffic" => $sTrafficAvg,
            "requests" => $sClicksTotal,
            "avgrequests" => $sClicks,
            "cpu" => $sCPU,
            "mem" => $sMem,
            "disk" => $sDisk,
            "ip" => $sIP,
            "ips" => implode($as_html ? '<br>' : "\n", self::ips()),
            "load" => $sLoad,
            "workers" => $workers,
            "whitelist" => str_replace(', ', $as_html ? "<br>" : "\n", SERVER_WHITELIST),
            "domains" => $sDomains
        ]; 
    }
}
