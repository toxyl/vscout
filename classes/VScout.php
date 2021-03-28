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
    #[core/OS]#

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
            $traffic = OS::traffic();
            $load_avg = OS::load_average();
            $public_ip = OS::public_ip();
            $memory = OS::memory();
            $disk = OS::diskspace();
            $workers = OS::workers_count();
            $cpu = OS::cpu();

            $fmtHTML = function($v) { return str_replace("\n", '<br>', str_replace(' ', '&nbsp;', $v)); };
            $fmtRow = function ($name, $value, $unit) { return sprintf('%5s: %7.2f %s', $name, $value, $unit)  . "\n"; };

            $sCPU = $fmtHTML(sprintf('%d cores @ %d MHz', $cpu['cores'], $cpu['MHz']) . "\n");

            $sLoad = $fmtHTML(
                $fmtRow( "1m", $load_avg["1m"], "%") .
                $fmtRow( "5m", $load_avg["5m"], "%") .
                $fmtRow("15m", $load_avg["15m"], "%")
            );

            $sDisk = $fmtHTML( 
                $fmtRow("Total", $disk["total"]['value'], $disk["total"]['unit']) .
                $fmtRow( "Used", $disk["used"]['value'],  $disk["used"]['unit']) .
                $fmtRow( "Free", $disk["free"]['value'],  $disk["free"]['unit'])
            );

            $sMem = $fmtHTML( 
                $fmtRow("Total", $memory["total"]['value'], $memory["total"]['unit']) .
                $fmtRow( "Used", $memory["used"]['value'],  $memory["used"]['unit']) .
                $fmtRow( "Free", $memory["free"]['value'],  $memory["free"]['unit'])
            );

            $sTrafficAcc = $fmtHTML( 
                $fmtRow("Total", $traffic['accumulated']["total"]['value'], $traffic['accumulated']["total"]['unit']) .
                $fmtRow(   "RX", $traffic['accumulated']["rx"]['value'],    $traffic['accumulated']["rx"]['unit']) .
                $fmtRow(   "TX", $traffic['accumulated']["tx"]['value'],    $traffic['accumulated']["tx"]['unit'])
            );

            $sTrafficAvg = $fmtHTML( 
                $fmtRow("Total", $traffic['average']["total"]['value'], $traffic['average']["total"]['unit'] . "/s") .
                $fmtRow(   "RX", $traffic['average']["rx"]['value'],    $traffic['average']["rx"]['unit'] . "/s") .
                $fmtRow(   "TX", $traffic['average']["tx"]['value'],    $traffic['average']["tx"]['unit'] . "/s")
            );

            $sIP = $public_ip['ip'] == '' ? 'N/A' : ($public_ip['blacklisted'] ? '[BLACKLISTED] ' . $public_ip['ip'] : $public_ip['ip']) . ' (' . $public_ip['country'] . ')';

            Response::html_file('config', [ 
                "current_dir" => OS::pwd(),
                "os" => OS::type(),
                "user" => OS::user(),
                "traffic" => $sTrafficAcc,
                "avgtraffic" => $sTrafficAvg,
                "cpu" => $sCPU,
                "mem" => $sMem,
                "disk" => $sDisk,
                "ip" => $sIP,
                "ips" => implode('<br>', OS::ips()),
                "load" => $sLoad,
                "workers" => $workers,
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

        /**
         * @ACL CLI
         * 
         * Flushes iptables and writes default settings. 
         * Useful if ipchanger did not shutdown properly.
         */
        static public function iptables_reset(bool $open_munin_port = true) 
        { 
            CommandIO::exec("systemctl stop ipchanger");
            CommandIO::exec("iptables-legacy -P INPUT ACCEPT");
            CommandIO::exec("iptables-legacy -P FORWARD ACCEPT");
            CommandIO::exec("iptables-legacy -P OUTPUT ACCEPT");
            CommandIO::exec("iptables-legacy -t nat -F");
            CommandIO::exec("iptables-legacy -t mangle -F");
            CommandIO::exec("iptables-legacy -F");
            CommandIO::exec("iptables-legacy -X");
            if ($open_munin_port)
                CommandIO::exec("iptables-legacy -A INPUT -p tcp --dport 4949 -j ACCEPT"); 
        }

        /**
         * @ACL CLI
         * 
         * Resets resolv.conf to Google nameservers. 
         * Useful if ipchanger did not shutdown properly.
         */
        static public function resolvconf_reset() 
        { 
            file_put_contents('/etc/resolv.conf', "nameserver 8.8.8.8\nnameserver 8.8.4.4");
        }
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
