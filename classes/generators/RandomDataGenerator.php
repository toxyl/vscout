<?php
    class RandomDataGenerator 
    {
        static private $max_retries       = 50;
        
        static private $usernames         = null;
        static private $useragents        = null;
        static private $domains           = null;
        static private $urls              = null;
        static private $wordlists         = null;

        static private $vulnerabilities   = null;

        static private function init() 
        {
            if (self::$useragents == null)
                self::$useragents = new RandomData(FILE_USER_AGENTS); 

            if (self::$usernames == null)
                self::$usernames = new RandomData(FILE_USERNAMES); 

            if (self::$domains == null)
                self::$domains = new RandomData(FILE_DOMAINS); 

            if (self::$urls == null)
                self::$urls = new RandomData(FILE_URLS); 

            if (self::$wordlists == null)
                self::$wordlists = new RandomData(FILE_WORDLISTS); 

            if (self::$vulnerabilities == null)
            {

                $files = glob(DataFile::path('vuln') . '/*.txt');
                foreach ($files as $file)
                {
                    if (is_file($file))
                    {
                        $file = preg_replace('/' . preg_quote(DATA_DIR, '/') . '\/(.*)/', '$1', $file);
                        $title = preg_replace('/.+?\/(.+?).txt/', '$1', $file);
                        self::$vulnerabilities[$title] = new RandomData($file);
                    }   
                }
            }
        }

        static public function random_user_agent()          
        { 
            self::init(); 

            $res = '';
            $i = 0;
            while ($res == '' && $i < self::$max_retries) {
                $res = trim(self::$useragents->get());
                $i++;
            }
            return $res; 
        }

        static private function random_user_name()           
        { 
            self::init();

            $res = '';
            $i = 0;
            while ($res == '' && $i < self::$max_retries) {
                $res = trim(self::$usernames->get());
                $i++;
            }
            return $res; 
        }

        static private function random_domain()              
        { 
            self::init();

            $res = '';
            $i = 0;
            while ($res == '' && $i < self::$max_retries) {
                $res = trim(self::$domains->get());
                $i++;
            }
            return $res; 
        }

        static private function random_url()                 
        { 
            self::init();
            
            $res = '';
            $i = 0;
            while ($res == '' && $i < self::$max_retries) {
                $res = trim(self::$urls->get());
                $i++;
            }
            return $res; 
        }

        static private function random_vuln($name)                 
        { 
            self::init();
            
            $res = '';
            $i = 0;
            while ($res == '' && $i < self::$max_retries) {
                $res = trim(self::$vulnerabilities[$name]->get());
                $i++;         
            }
            return $res; 
        }

        static private function random_word()                 
        { 
            self::init();
            
            $res = '';
            $i = 0;
            while ($res == '' && $i < self::$max_retries) {
                $res = trim(self::$wordlists->get());
                $i++;         
            }
            return $res; 
        }

        static private function str_replace_first(&$str, $search, $replace)
        {
            $pos = strpos($str, $search);
            if ($pos !== false) {
                $str = substr_replace($str, $replace, $pos, strlen($search));
                return true;
            }
            return false;
        }

        static private function gen_fake_hash($length) 
        {
            $options = ['a','b','c','d','e','f','0','1','2','3','4','5','6','7','8','9'];
            $str = '';
            while ($length > 0) 
            {
                $str .= $options[rand(0,15)];
                $length--;
            }
            return $str;
        }

        static private function parse_set($re, &$url, $fnMatch, $trimSpaces = true) 
        {
            preg_match_all($re, $url, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) 
            {
                while (self::str_replace_first($url, $match[0], trim($trimSpaces ? preg_replace('/\s+/', '', $fnMatch($url, $match)) : $fnMatch($url, $match)))) {}
            }
        }

        static private function parse_hash(&$url)
        {
            self::parse_set('/\[#(\d+)\]/', $url, function($url, $m) {
                return self::gen_fake_hash(rand(2, $m[1]));
            });
        }

        static private function parse_uuid(&$url)
        {
            self::parse_set('/\[#(UUID)\]/', $url, function($url, $m) {
                return  self::gen_fake_hash(8) . '-' . 
                        self::gen_fake_hash(4) . '-' . 
                        self::gen_fake_hash(4) . '-' . 
                        self::gen_fake_hash(4) . '-' . 
                        self::gen_fake_hash(12);
            });
        }

        static private function parse_vulnerabilities(&$url)
        {
            self::init();

            $names = array_keys(self::$vulnerabilities);
            $url = preg_replace('/\[VULN\]/', '[' . implode("|", $names) . ']', $url);

            self::parse_or_list($url);

            foreach ($names as $name) 
            {
                self::parse_set('/\[' . $name . '\]/', $url, function($url, $m) use ($name) {
                    return substr(escapeshellarg(self::random_vuln($name)), 1, -1);
                }, false);
            }
        }
        
        static private function parse_list(&$url)
        {
            self::parse_set('/\[(.*?,.*?)\]/', $url, function($url, $m) {
                $options = explode(",", $m[1]);
                $m[1] = $options[rand(0, count($options)-1)];
                return $m[1];
            }, false);
        }

        static private function parse_or_list(&$url)
        {
            self::parse_set('/\[(.*?\|.*?)\]/', $url, function($url, $m) {
                $options = explode("|", $m[1]);
                $m[1] = $options[rand(0, count($options)-1)];
                return '[' . $m[1] . ']';
            }, false);
        }

        static private function parse_int_range(&$url)
        {
            self::parse_set('/\[(\d+)\-(\d+)\]/', $url, function($url, $m) {
                return rand($m[1], $m[2]);
            });
        }

        static private function parse_string(&$url)
        {
            self::parse_set('/\[(str|int|mix|s|i|m)(U|R|L|):(\d+\-\d+|\d+)\]/', $url, function($url, $m) {
                switch ($m[1]) {
                    case 's':
                    case 'str':
                        $chars = 'abcdefghijklmnopqrstuvxyz';
                        break;

                    case 'i':
                    case 'int':
                        $chars = '1234567890';
                        break;
                    
                    default:
                        $chars = 'abcdefghijklmnopqrstuvxyz1234567890';
                        break;
                }

                $chars = str_split($chars);

                $len = explode('-', $m[3]);
                $len = count($len) == 2 ? $len[round(rand(0,1))] : $len[0];

                $out = [];
                while ($len > 0)
                {
                    $out[] = $chars[rand(0, count($chars) - 1)];
                    $len--;
                }

                switch ($m[2]) {
                    case 'U':
                        $out = strtoupper(implode('', $out));
                        break;
                    
                    case 'R':
                        $rl = round(count($out) / 3);
                        while ($rl > 0)
                        {
                            $idx = rand(0, count($out) - 1);
                            $out[$idx] = strtoupper($out[$idx]); 
                            $rl--;
                        }
                        $out = implode('', $out);
                        break;
                    
                    default:
                        $out = strtolower(implode('', $out));
                        break;
                }
                return $out;
            });
        }

        static private function parse_email(&$url)
        {
            self::parse_set('/\[@\]/', $url, function($url, $m) {
                return self::random_user_name() . '@' . self::random_domain();
            });
        }

        static private function parse_user(&$url)
        {
            self::parse_set('/\[USER\]/', $url, function($url, $m) {
                return self::random_user_name();
            });
        }

        static private function parse_domain(&$url)
        {
            self::parse_set('/\[DOMAIN\]/', $url, function($url, $m) {
                return self::random_domain();
            });
        }

        static private function parse_user_agent(&$url)
        {
            self::parse_set('/\[USERAGENT\]/', $url, function($url, $m) {
                return self::random_user_agent();
            }, false);
        }

        static private function parse_word(&$url)
        {
            self::parse_set('/\[WORD\]/', $url, function($url, $m) {
                return self::random_word();
            }, false);
        }

        static private function parse_url(&$url)
        {
            self::parse_set('/\[URL\]/', $url, function($url, $m) {
                $ru = preg_replace('/\s/', '', self::random_url());
                return preg_match('/(https|http):\/\/\/.*/', $ru) ? preg_replace('/(https|http):\/\/\/(.*)/', '$1://[DOMAIN]/$2', $ru)  : $ru;
            }, false);
        }

        static private function parse_variable(&$url)
        {
            self::parse_set('/\[\$([a-zA-Z0-9_]+)\]/', $url, function($url, $m) {
                if ($m == null)
                    return '';

                try 
                {
                    $val = self::$useragents->getVar($m[1]);
                    if ($val == null) 
                        $val = self::$domains->getVar($m[1]);
                    if ($val == null) 
                        $val = self::$usernames->getVar($m[1]);
                    if ($val == null) 
                        $val = self::$urls->getVar($m[1]);
                    if ($val == null) 
                        $val = self::$wordlists->getVar($m[1]);
                }
                catch (\Throwable $e)
                {
                    $val = null;
                }

                return $val == null ? '' : (substr_count($val, ',') > 0 ? '[' . $val . ']' : $val);
            }, false);
        }

        static public function remove_domain($name)
        {
            self::$domains->remove($name);
        }

        /**
        * Available Placeholders                                                                     
        * ========================================================================================
        * 
        * Variable blocks
        * ========================================================================================
        * [DOMAINS:3]
        * {                  
        *    ...             
        * }
        * 
        * List of domains that are made available through the $DOMAINS3 variable 
        * defined in domains.txt in block 3. Each will be checked for usability.
        * Only active domains that are not firewalled will be added to the system.
        * 
        * [BACKEND:https:7]                                                                          
        * {                                                                                          
        *     hello/world[int:3]                                                                     
        *     ?some-random-var=[int:3]                                                               
        * }                                                                                          
        *                                                                                            
        * Use the above to create blocks of URL patterns using one of the $DOMAINS* variables.       
        * The second parameter can be http or https and defines the protocol to use.                 
        * The third parameter defines from which domain set to read the domain name.                 
        * The example would parse to:                                                                
        *                                                                                            
        * https://$DOMAINS7/hello/world[int:3]                                                       
        * https://$DOMAINS7/?some-random-var=[int:3]                                                 
        * [BACKEND:http:3] = generates URL patterns for an HTTP backend using domains 
        * {                  defined in domains.txt in block 3,
        *    ...             each line is used as suffix for protocol + domain
        * }
        * ----------------------------------------------------------------------------------------
        * 
        * Misc. variable shorthands
        * ========================================================================================
        * [$DOMAINS:3]     = random domain from the domains defined in domains.txt in block 3 
        * [PTA|int:6|a,b]  = random placeholder (separate with pipes)
        * ----------------------------------------------------------------------------------------
        * 
        * Random data from datasets
        * ========================================================================================
        * [USERAGENT]    = random user agent (from useragents.txt)
        * [DOMAIN]       = random spammer domain (from domains.txt)
        * [USER]         = random user name (from usernames.txt)
        * [@]            = random fake email ([USER]@[DOMAIN])
        * [WORD]         = random word (from wordlists.txt)
        * ----------------------------------------------------------------------------------------
        * 
        * Generated random data
        * ========================================================================================
        * [URL]          = random URL
        * [#UUID]        = random UUID (xxxxxxxx-xxxx-xxxx-xxxxxxxxxxxx)
        * [#56]          = random 56-characters hash 
        * [int:6]        = random 6-characters integer (zero-padded)
        * [str:6]        = random 6-characters lowercase string (a-z) 
        * [strU:6]       = random 6-characters uppercase string (A-Z) 
        * [strR:6]       = random 6-characters mixed-case string (a-z, A-Z) 
        * [mix:6]        = random 6-characters lowercase alphanumeric string (a-z, 0-9) 
        * [mixU:6]       = random 6-characters uppercase alphanumeric string (A-Z, 0-9) 
        * [mixR:6]       = random 6-characters mixed-case alphanumeric string (a-z, A-Z, 0-9) 
        * [10-500]       = random value between 10 and 500 (inclusive)
        * [a,b,c]        = random value from the list 
        * ----------------------------------------------------------------------------------------
        * 
        * Vulnerabilities
        * ========================================================================================
        * [VULN]         = random vulnerability
        * [PTA:10]       = path traversal attack with random depth (given value is the maximum)
        * [PTA]          = random path traversal attack
        * [SQLi]         = random SQLi attack
        * [XSS]          = random XSS attack
        * ----------------------------------------------------------------------------------------
        */
        static public function parse(&$str) {
            self::parse_url($str);
            self::parse_or_list($str);
            self::parse_variable($str);
            self::parse_word($str);
            self::parse_user_agent($str);
            self::parse_domain($str);
            self::parse_email($str);
            self::parse_user($str);
            self::parse_uuid($str);
            self::parse_hash($str);
            self::parse_string($str);
            self::parse_int_range($str);
            self::parse_list($str);
            self::parse_vulnerabilities($str);
            return $str;
        }

        static public function generate_random_url()
        {
            $url = null;
            while ($url == '' || $url == null || preg_match('/(https|http):\/\/\/.*/', $url))
            {
                $url = preg_replace('/\s/', '', self::random_url());
                self::parse($url);

                if (preg_match('/(https|http):\/\/\/.*/', $url))
                {
                    $url = preg_replace('/(https|http):\/\/\/(.*)/', '$1://[DOMAIN]/$2', $url);
                    self::parse($url);
                } 
            }
            return $url;
        }
    }
