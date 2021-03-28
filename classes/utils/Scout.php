<?php
class Scout
{
    static public function blacklisted()
    {
        if (!file_exists(DataFile::path(FILE_IP_BLACKLIST)))
            DataFile::write(FILE_IP_BLACKLIST, '');

        // wait till we have an IP because sometimes an empty string is returned
        while (($ip = CommandIO::exec('wget -qO - ' . IP_CHECK_URL)) == '')
        {
            usleep(500000);
        }

        if ($ip == '') // unlikely but we don't want to risk it, right?
            return true;

        return in_array($ip, explode("\n", DataFile::read(FILE_IP_BLACKLIST)));
    }

    static public function exec(string $cmd, bool $use_proychains)
    {
        if (!TOR_MODE) // alright, we'll just run the command out in the open *sigh*
            return CommandIO::exec($cmd);

        if (self::blacklisted())
        {
            CommandIO::exec("ipchanger -r");
            return 'Refusing operation, not connected to TOR. Requested a new exit node.';
        }

        if ($use_proychains)
            $cmd = 'proxychains ' . $cmd . " 2>&1 | grep -v 'ProxyChains-' | grep -v 'S-chain' | grep -v 'DNS-request' | grep -v 'DNS-response'";
        
        return CommandIO::exec($cmd);
    }

    static public function ip(string $url) 
    {
        if (preg_match('/^(https:\/\/|http:\/\/|)(localhost)(\/.*|)$/', $url))
            return '127.0.0.1';

        $domain = preg_replace('/^(https:\/\/|http:\/\/|)(.+?)(\/.*|)$/', '$2', $url);
        
        if (preg_match('/[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}/', $domain))
            return $domain;

        if (Data::has_record("domain", [ "name" => $domain ]))
        {
            $res = Data::select('domain', ['name', 'ip'], ['name' => $domain]);
            $ip = $res != null && count($res) > 0 ? $res[0][1] : null;
        }
        else
        {
            if (TOR_MODE)
                $ip = trim(self::exec("tor-resolve $domain 2>/dev/null", false));
            else
                $ip = trim(self::exec("dig $domain +short", false));

            Data::upsert('domain', 'name', ['name' => $domain, 'ip' => $ip]);            
        }

        return $ip && preg_match('/[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}/', $ip) ? $ip : null;
    }

    static private function register(string $url, $status)
    {
        if ($status == null || !is_numeric($status) || trim($status) == '')
            $status = 0;

        $prot   = preg_replace('/(http|https):\/\/(.*?)(\/.*|$)/', '$1', $url);
        $domain = preg_replace('/(http|https):\/\/(.*?)(\/.*|$)/', '$2', $url);
        $path   = preg_replace('/(http|https):\/\/(.*?)(\/.*|$)/', '$3', $url);

        Data::upsert('clicks', null, [ 'prot' => $prot, 'domain' => $domain, 'path' => $path, 'status' => $status, 'ts' => date('Y-m-d H:i:s') ]);
    }

    static private function is_already_clicked(string $url)
    {
        $prot   = preg_replace('/(http|https):\/\/(.*?)(\/.*|$)/', '$1', $url);
        $domain = preg_replace('/(http|https):\/\/(.*?)(\/.*|$)/', '$2', $url);
        $path   = preg_replace('/(http|https):\/\/(.*?)(\/.*|$)/', '$3', $url);

        return Data::has_record("clicks", [ 'prot' => $prot, 'domain' => $domain, 'path' => $path ]);
    }

    static private function process(string $url) 
    {
        $domain = preg_replace('/^(https:\/\/|http:\/\/|)(.+?)(\/.*|)$/', '$2', $url);
        if (Data::has_record("domain", [ "name" => $domain, 'firewalled' => 1 ]) || 
            Data::has_record("domain", [ "name" => $domain, 'dead' => 1 ]))
            return null;

        $ua = RandomDataGenerator::random_user_agent();

        if (($ip = self::ip($url)) == null)
            return [
                "ip" => null,
                "ua" => $ua,
                "status" => -2
            ];

        if (self::is_already_clicked($url))
            return [
                "ip" => $ip,
                "ua" => $ua,
                "status" => -1
            ];

        $lines    = explode("\n", trim(self::exec(sprintf("curl -i -v -m 10 -L -q -s -A '%s' --compressed -o /dev/null '%s'", $ua, $url), true)) ?? '');
        $status   = null;
        $reSta    = '/^.*(HTTP)\/(\d+\.\d+|\d+)\s+(\d+)\s*(.*)\s*$/i';

        foreach ($lines as $line) 
        {
            if (trim($line) == '') 
                continue;

            if ($status == null && preg_match($reSta, $line) === 1)
            {
                $status = preg_replace($reSta, '$3', $line);
            }
        }

        return [
            "ip" => $ip,
            "ua" => $ua,
            "url" => $url,
            "status" => (int) $status,
            "result" => $lines
        ];
    }
        
    static public function click($url) 
    {
        if (preg_match('/(http|https):\/\/([a-zA-Z0-9_\-\.]+?\.[a-zA-Z]{2,6}|localhost|\d+\.\d+\.\d+\.\d+)\/.*/', $url))
        {
            $res = self::process($url);
            
            if ($res == null)
                return null;

            Log::click($url, $res['status'], $res['ip']); 

            if ($res['status'] > 0)
            {
                self::register($url, $res['status']);
            }

            return json_encode($res, JSON_PRETTY_PRINT);
        }
    }
}
