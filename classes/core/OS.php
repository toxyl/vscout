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
        
        $file_traffic = '/tmp/traffic.json';
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
        $cores = intval(CommandIO::exec("nproc"));
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
            "cores" => intval(trim(`nproc`)),
            "MHz" => floatval(trim(`cat /proc/cpuinfo | grep MHz | awk '{s+=$4;c+=1} END{print s/c}'`))
        ];
    }

    static public function workers_count()
    {
        $wcmd = WORKER_CMD;
        $name = NAME;
        return intval(trim(`pgrep -a $name | grep '$wcmd' | wc -l`));
    }
}
