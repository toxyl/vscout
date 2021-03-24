#!/usr/bin/php
<?php
    // Fix to avoid outputting the shebang in non-CLI environments as suggested by Sz. on StackOverflow:
    // https://stackoverflow.com/a/53271823/3337885
    if (ob_get_level()) { $buf = ob_get_clean(); ob_start(); echo substr($buf, 0, strpos($buf, file(__FILE__)[0])); }

    declare(ticks = 100);

    #[Config-VScout]#
    #[ConfigFile]#

    Config::init();

    #[core/AccessControl]#
    #[core/ANSI]#
    #[core/BackgroundProcess]#
    #[core/CommandIO]#
    #[core/Daemon]#
    #[core/Documentation]#
    #[core/ProcessControl]#
    #[core/ReflectionUtils]#
    #[core/Response]#
    #[core/Router]#
    #[core/Routes]#

    #[data/Data]#
    #[data/Database]#
    #[data/DataExecBuffer]#
    #[data/DataFile]#
    #[data/Stats]#
    
    #[generators/RandomDataGenerator]#
    #[generators/RandomData]#
    
    #[logging/Log]#

    #[utils/DomainScanner]#    
    #[utils/Installer]#
    #[utils/TemplateParser]#
    #[utils/Scout]#
    #[utils/URLScraper]#


	class Main extends Routes
	{
        /**
         * @ACL GET CLI
         * 
         * Returns the current config.
         */
        static public function config() 
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

            $fGetUnit = function ($v) { return $v < 1 ? "KB" : ($v >= 1024 ? "GB" : "MB"); };
            $fFormatVal = function ($v) { return sprintf('%.2f', $v < 1 ? $v * 1024 : ($v >= 1024 ? $v / 1024 : $v)); };

            $t_rx_unit       = $fGetUnit($t_rx);
            $t_tx_unit       = $fGetUnit($t_tx);  
            $t_total_unit    = $fGetUnit($t_total);  

            $t_rx            = $fFormatVal($t_rx);
            $t_tx            = $fFormatVal($t_tx);
            $t_total         = $fFormatVal($t_total);

            $td_rx_unit      = $fGetUnit($td_rx) . "/s";
            $td_tx_unit      = $fGetUnit($td_tx) . "/s";  
            $td_total_unit   = $fGetUnit($td_total) . "/s";  

            $td_rx           = $fFormatVal($td_rx);
            $td_tx           = $fFormatVal($td_tx);
            $td_total        = $fFormatVal($td_total);

            $ip = CommandIO::exec("curl --silent " . IP_CHECK_URL);
            Response::html_file('config', [ 
                "current_dir" => `pwd`,
                "os" => `uname -som`,
                "user" => `whoami`,
                "traffic" => "Total: $t_total $t_total_unit<br>&nbsp;&nbsp;&nbsp;RX: $t_rx $t_rx_unit<br>&nbsp;&nbsp;&nbsp;TX: $t_tx $t_tx_unit",
                "avgtraffic" => "Total: $td_total $td_total_unit<br>&nbsp;&nbsp;&nbsp;RX: $td_rx $td_rx_unit<br>&nbsp;&nbsp;&nbsp;TX: $td_tx $td_tx_unit",
                "ip" => (Scout::blacklisted() ? '[BLACKLISTED] ' . $ip : $ip) . ' (' . CommandIO::exec("whois $ip | grep country -i -m 1 | cut -d ':' -f 2 | xargs") . ')',
                "ips" => `hostname -I | perl -pe 's@ @<br>@g'`,
                "load" => `cat /proc/loadavg | perl -pe 's@([^\\s]+) ([^\\s]+) ([^\\s]+) .*@&nbsp;1m: \\1<br>&nbsp;5m: \\2<br>15m: \\3<br>@g'`,
                "whitelist" => implode("<br>", explode(", ", SERVER_WHITELIST))
            ], 
            10); 
        }

        /**
         * @ACL GET CLI
         * 
         * Returns the current stats.
         */
        static public function statistics() 
        { 
            Response::html_file('stats', [ "data" => Stats::print_formatted() ], 10); 
        }

        /**
         * @ACL GET CLI
         *
         * Uses curl through Tor to retrieve a URL to analyze the response of.
         * Redirects will not be followed. 
         * User agent will be a randomized string for each request.
         * Returns URL, user agent, status, location and HTML links found. 
         * 
         * The URL can use these random data placeholders:
         * 
         * %_2>Misc. variable shorthands%rst>
         * %f3>[PTA|int:6|a,b]  %rst> = random placeholder (separate with pipes)                               
         *                                                                                           
         * %_2>Random data from datasets%rst>
         * %f3>[USERAGENT]      %rst> = random user agent (from useragents.txt)                                  
         * %f3>[DOMAIN]         %rst> = random spammer domain (from domains.txt)                                 
         * %f3>[USER]           %rst> = random user name (from usernames.txt)                                    
         * %f3>[@]              %rst> = random fake email ([USER]@[DOMAIN])                                      
         * %f3>[WORD]           %rst> = random word (from wordlists.txt)                                         
         *                                                                                           
         * %_2>Generated random data%rst>
         * %f3>[#UUID]          %rst> = random UUID (xxxxxxxx-xxxx-xxxx-xxxxxxxxxxxx)                            
         * %f3>[#56]            %rst> = random 56-characters hash                                                
         * %f3>[int:6]          %rst> = random 6-characters integer (zero-padded)                                
         * %f3>[str:6]          %rst> = random 6-characters lowercase string (a-z)                               
         * %f3>[strU:6]         %rst> = random 6-characters uppercase string (A-Z)                               
         * %f3>[strR:6]         %rst> = random 6-characters mixed-case string (a-z, A-Z)                         
         * %f3>[mix:6]          %rst> = random 6-characters lowercase alphanumeric string (a-z, 0-9)             
         * %f3>[mixU:6]         %rst> = random 6-characters uppercase alphanumeric string (A-Z, 0-9)             
         * %f3>[mixR:6]         %rst> = random 6-characters mixed-case alphanumeric string (a-z, A-Z, 0-9)       
         * %f3>[10-500]         %rst> = random value between 10 and 500 (inclusive)                              
         * %f3>[a,b,c]          %rst> = random value from the list                                               
         *                                                                                           
         * %_2>Vulnerabilities%rst>
         * %f3>[VULN]           %rst> = random vulnerability                                                     
         * %f3>[PTA:10]         %rst> = path traversal attack with random depth (given value is the maximum)     
         * %f3>[PTA]            %rst> = random path traversal attack                                             
         * %f3>[SQLi]           %rst> = random SQLi attack                                                       
         * %f3>[XSS]            %rst> = random XSS attack                                                        
         */
        static public function check(string $url = 'http://' . SERVER_IP . ':' . SERVER_PORT) { URLScraper::scrape($url); } 

        /**
         * @ACL GET CLI
         * 
         * Shows stats of the system.
         */
        static public function stats(bool $follow = true) { Stats::show($follow); Data::close(); }

        /**
         * @ACL CLI
         * 
         * Shows stats of the system.
         */
        static public function results_by_status_code(int $min = 100, int $max = 599) 
        { 
            $res = Data::results_by_status($min, $max);
            foreach ($res as &$v) 
            {
                if (is_array($v))
                    $v = '%f3>' . $v[0] . '%rst>: %f4>' . $v[1] . '%rst>://%f2>' . $v[2] . '%rst>' . preg_replace('/(=)/', ' %f5>$1%rst> ', preg_replace('/(&|\?|\/)/', ' %f1>$1%rst> ', preg_replace('/(&|\?)(.*?)=/', '$1%f2>$2%rst>=', str_replace('%', '%%', $v[3]))));
            }
            Response::ansi(implode("%ln>", $res)."%ln>");
            Data::close();
        }

        /**
         * @ACL CLI
         * 
         * Rescans the status of all known domains.
         */
        static public function domains_rescan() { DomainScanner::update(); }

        /**
         * @ACL CLI
         * 
         * This starts a worker.
         */
        static public function worker() { Daemon::spawn_worker(); }

        /**
         * @ACL CLI
         * 
         * This starts a daemon that automatically scales workers
         * based on the average load of the last 5 minutes.
         */
        static public function daemon() { Daemon::start(); }


        /**
         * @ACL CLI
         * 
         * Installs all required components, sets up NginX and starts 
         * the required service. You only have to run this once.
         */
        static public function install() { Installer::install(); }
	}

    // shutdown handler to make sure we close DB connections
    if (function_exists('pcntl_signal'))
    {
        pcntl_signal(SIGCHLD, SIG_IGN); // get rid off those pesky zombies

        pcntl_signal(SIGTERM, function($signo) 
            { 
                Data::close(); 
                Log::ln('Terminated.'); 
                exit(0); 
            }
        );
        pcntl_signal(SIGINT, function($signo) 
            { 
                Data::close(); 
                Log::ln('Terminated.'); 
                exit(0); 
            }
        );        
    }

    Routes::__process();

    if (!function_exists('pcntl_signal'))
    {
        @Data::close();
    }
?>
