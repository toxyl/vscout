<?php
    class Router
    {
        const CLI = 'CLI';
        const POST = 'POST';
        const GET = 'GET';
        const MIXED = 'MIXED';
        static public $mode = self::GET;
        static public $route = null;
        static public $input = null;
        static public $routes = null;
       
        static public function is_get()   { return self::$mode == self::GET; }
        static public function is_post()  { return self::$mode == self::POST; }
        static public function is_mixed() { return self::$mode == self::MIXED; }
        static public function is_cli()   { return self::$mode == self::CLI; }

        static private function get_mode()
        {
            if      (isset($_SERVER['argv']))                                                                                            self::$mode = self::CLI;
            else if (isset($_GET) && count($_GET) == 1 && $_GET[array_keys($_GET)[0]] == null && isset($_POST) && count($_POST) > 0)     self::$mode = self::MIXED;
            else if (isset($_POST) && count($_POST) > 0)                                                                                 self::$mode = self::POST;
            else if (isset($_GET) && count($_GET) > 0)                                                                                   self::$mode = self::GET;
            
            return self::$mode;
        }
    
        static public function get_argument_value($name, $default = null)                               
        { 
            return isset(self::$input["$name"]) && !empty(self::$input["$name"]) ? self::$input["$name"] : $default; 
        }
        
        static private function get_route_selection()
        {
            $v = null;
            switch (self::$mode) 
            {
                case self::CLI:
                    if (isset($_SERVER['argv'][1]))
                        $v = $_SERVER['argv'][1];
                    break;
                
                case self::POST:
                    if (isset(array_keys($_POST)[0]))
                        $v = str_replace('_', DELIMITER_ROUTES, array_keys($_POST)[0]);
                    break;
                
                case self::MIXED:
                case self::GET:
                    if (isset(array_keys($_GET)[0]))
                        $v = str_replace('_', DELIMITER_ROUTES, array_keys($_GET)[0]);
                    break;
                
                default:
                    # hmmm, this ain't right.
                    # if this happens, then most likely 
                    # someone is trying to exploit something, 
                    # we better stop NOW!
                    exit(1);
            }
            return $v;
        }
        
        static private function match_route($name, $data)
        {
            if ( 
                 self::$mode == null || 
                (self::$mode == self::CLI && count($_SERVER['argv']) < 2) || 
                (self::$mode == self::GET && count($_GET) < 1) || 
                (self::$mode == self::POST && count($_POST) < 1) || 
                (self::$mode == self::MIXED && count($_GET) < 1)
            )
                return null;

            if ($name == self::get_route_selection())
            {
                $d = [ "$name" => $data ];
                self::$route = $name;
            }
            else
            {
                $d = null;
                self::$route = null;
            }
            return $d;
        }
    
        static public function routes($routes)                  
        { 
            self::get_mode();
            self::$input = [];
            self::$routes = $routes;

            uksort(self::$routes, 
                function ($a, $b) 
                {
                    if ($a[0] == '.' && $b[0] != '.')
                    {
                        return 1;
                    }
                    else if ($a[0] != '.' && $b[0] == '.')
                    {
                        return -1;
                    }
                    return strcmp($a, $b);
                }
            );

            foreach ($routes as $name => $data) 
            {
                $route = self::match_route($name, $data);
                if ($route != null)
                    break; // we've got a match here!
            }

            if ($route == null)
            {
                // no matching route found
                return null;
            }

            $name = array_keys($route)[0]; // route name
            $d = array_values($route)[0]; // route defaults

            switch (self::$mode)
            {
                case self::CLI:
                    $a = $_SERVER['argv'];
                    array_shift($a);
                    break;
                    
                case self::POST:
                    $name_param = str_replace(DELIMITER_ROUTES, "_", $name);
                    $a = explode(DELIMITER_POST, $_POST[$name_param]);
                    if (strlen($a[0]) == 0) $a = [];
                    array_unshift($a, $name);
                    break;
                    
                case self::GET:     
                    $name_param = str_replace(DELIMITER_ROUTES, "_", $name);        
                    $a = explode(DELIMITER_GET, $_GET[$name_param]);
                    if (strlen($a[0]) == 0) $a = [];
                    array_unshift($a, $name);
                    break;
                    
                case self::MIXED:       
                    $name_param = str_replace(DELIMITER_ROUTES, "_", $name);        
                    $a = explode(DELIMITER_POST, array_keys($_POST)[0]);
                    
                    if (strlen($a[0]) == 0) $a = [];
                    array_unshift($a, $name);
                    break;
            }

            foreach ($d as $k => $def) 
            {
                $v = count($a) > 0 ? array_shift($a) : $def;
                self::$input["$k"] = $v;
            }

            if (count($a) > 0 && $a[0] == '?')
                self::$input["?"] = array_shift($a);
            
            return $name;
        }
    }
