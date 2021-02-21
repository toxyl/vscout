<?php
    class RandomData
    {
        private $vars = null;
        private $data = null;

        public function __construct($file)
        {
            Log::template_parser("Loading $file...");
            $res = [];
            $this->vars = [];
            $this->data = [];
            $data = DataFile::read($file);
            $this->parse_domain_blocks($data);
            $this->parse_backend_blocks($data);
            $this->parse_variable_blocks($data);
            $this->data = array_merge($this->data, explode("\n", $data));
           
            foreach ($this->data as $v) 
            {
                $v = trim($v);
                if ($v != '')
                {
                    if (preg_match('/^\s*\$([a-zA-Z0-9_]+)\s*=\s*(.*)$/', $v)) 
                    {
                        $name = preg_replace('/^\s*\$([a-zA-Z0-9_]+)\s*=\s*(.*)$/', '$1', $v);
                        $val = preg_replace('/^\s*\$([a-zA-Z0-9_]+)\s*=\s*(.*)$/', '$2', $v);
                        $this->vars[$name] = $val;
                    } 
                    else if (!preg_match('/^\s*#.*$/', $v))
                    {
                        $res[] = $v;
                    } 
                }
            }
            $this->data = $res;
        }

        public function getVar($name)
        {
            return $this->vars[$name] ?? null;
        }

        public function get()
        {
            $val = null;
            if ($this->data && count($this->data) > 0)
            {
                $val = $this->data[rand(0, count($this->data) - 1)];
                RandomDataGenerator::parse($val);                
            }
            return $val;
        }

        public function remove($name)
        {
            if ($this->data && count($this->data) > 0)
            {
                foreach ($this->vars as $k => $v) {
                    $list = explode(",", $v);
                    $res = [];
                    foreach ($list as $v2) {
                        if ($v2 != $name)
                            $res[] = $v2;
                    }
                    $this->vars[$k] = implode(',', $res);
                }
                if (isset($this->data[$name]))
                    unset($this->data[$name]);
            }
        }

        public function count()
        {
            return count($this->vars ?? []);
        }

        public function parse_domain_blocks(&$data)
        {
            preg_match_all('/\[(DOMAINS):(\d+)\]\s*\{(.*?)\}\s*/s', $data, $matches, PREG_SET_ORDER);

            $firewalled_domains = DomainScanner::get_firewalled();
            $dead_domains       = DomainScanner::get_dead();
            $active_domains     = DomainScanner::get_active();

            foreach ($matches as $m) 
            {
                if ($m == null)
                    continue;

                $id = $m[2];
                $name = '$' . $m[1] . $m[2];
                $vs = explode("\n", trim($m[3]));
                $res = [];

                Log::template_parser("Parsing domain block $id ...");

                foreach ($vs as $val) 
                {
                    $val = trim($val);
                    if ( 
                        ( $dead_domains && in_array($val, $dead_domains) ) || 
                        ( $firewalled_domains && in_array($val, $firewalled_domains) ) ||
                        (
                            !( $active_domains && in_array($val, $active_domains) ) &&
                            (
                                DomainScanner::is_dead($val) || 
                                DomainScanner::is_firewalled($val)
                            )
                        )
                    )
                    {   
                        continue;
                    }

                    $this->data[] = $val;
                    $res[] = $val;
                }

                $data = str_replace($m[0], $name . " = " . implode(",", $res) . "\n", $data);
            }
        }

        public function parse_backend_blocks(&$data)
        {
            preg_match_all('/\[(BACKEND):(http|https):(\d+)\]\s*\{(.*?)\}\s*/s', $data, $matches, PREG_SET_ORDER);

            foreach ($matches as $m) {
                if ($m == null)
                    continue;

                $id = $m[3];
                $prot = $m[2];
                $name = '$' . $m[1] . $m[3];
                $lines = explode("\n", trim($m[4]));
                $res = [];

                Log::template_parser("Parsing backend block $id ($prot) ...");
    
                foreach ($lines as $line) 
                {
                    $line = $m[2] . '://[$DOMAINS' . $m[3]  . ']/' . trim($line);
                    $this->data[] = $line;
                    $res[] = $line;
                }

                $data = str_replace($m[0], implode("\n", $res) . "\n", $data);
            }
        }

        public function parse_variable_blocks(&$data)
        {
            preg_match_all('/\[([a-zA-Z0-9_]+):(\d+)\]\s*\{(.*?)\}\s*/s', $data, $matches, PREG_SET_ORDER);

            foreach ($matches as $m) {
                if ($m == null)
                    continue;

                $title = $m[1];
                $id = $m[2];
                $name = '$' . $m[1] . $m[2];
                $vs = explode("\n", trim($m[3]));
                $res = [];

                Log::template_parser("Parsing variable block $title $id ...");

                foreach ($vs as $val) 
                {
                    $val = trim($val);
                    $res[] = $val;
                }
                
                $data = str_replace($m[0], $name . " = " . implode(",", $res) . "\n", $data);
            }
        }

    }
