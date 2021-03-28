<?php
    class DomainScanner
    {
        static public function is_firewalled(string $url) 
        {
            if (!CHECK_FIREWALLS)
                return false;

            $response = explode("\n", trim(`proxychains curl -v -i --head -q -s --compressed '$url' 2>/dev/null`));
            foreach ($response as $line) 
            {
                $line = trim($line);
                if (preg_match('/^HTTP\/.*?\s+403\s+Forbidden$/', $line) || preg_match('/^.+: Connection refused$/', $line))
                {
                    Data::upsert('domain', 'name', ['name' => $url, 'firewalled' => 1, 'dead' => 0]);
                    return true;
                }
            }
            return false;
        }

        static public function is_dead(string $url) 
        {
            if (Scout::ip($url) == null)
            {
                Data::upsert('domain', 'name', ['name' => $url, 'dead' => 1, 'firewalled' => 0]);
                return true;
            }

            return false;
        }

        static public function get_firewalled()
        {
            if (!CHECK_FIREWALLS)
                return [];

            return array_map(function ($r) {
                return $r[0];
            }, Data::select('domain', ['name'], [ 'firewalled' => 1, 'dead' => 0 ], 0));
        }

        static public function get_dead()
        {
            return array_map(function ($r) {
                return $r[0];
            }, Data::select('domain', ['name'], [ 'firewalled' => 0, 'dead' => 1 ], 0));
        }

        static public function get_active()
        {
            return array_map(function ($r) {
                return $r[0];
            }, Data::select('domain', ['name'], [ 'firewalled' => 0, 'dead' => 0 ], 0));
        }

        static public function update()
        {   
            // force loading domains from domains.txt,
            // so domains from it that are not yet in the database 
            // will be rescanned as well
            $fd = new RandomData(FILE_DOMAINS);

            $domains = array_map(function ($r) {
                return $r[0];
            }, Data::select('domain', ['name'], [ ], 0));

            Response::ansi('Found %f3>' . count($domains) . '%rst> domains.%ln>');
            foreach ($domains as $domain) 
            {
                if (self::is_dead($domain))
                {
                    Response::ansi('    [%f1>  DEAD%rst>] ' . $domain . '%ln>');
                }
                else if (self::is_firewalled($domain))
                {
                    Response::ansi('    [%f3>    FW%rst>] ' . $domain . '%ln>');
                }
                else
                {
                    Response::ansi('    [%f2>ACTIVE%rst>] ' . $domain . '%ln>');
                }
            }
        }
    }
