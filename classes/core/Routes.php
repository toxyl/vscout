<?php
    class Routes
    {
        static public $info;
        static public $class = 'Main';
        static public $route;

        static public function __process()
        {
            self::$info = self::__export();
            self::$route = Router::routes(self::$info);
            if (self::$route == null || self::$route == '?' || self::$route == 'help')
            {
                if (Router::is_cli())
                    Response::auto(Documentation::routes());
                else
                    Response::html_file('index');
            }
            else
            {
                $params = [];
                $keys = array_keys(self::$info[self::$route]);
                if ((Router::$input["?"] ?? false) == '?' || (Router::$input[$keys[1] ?? false] ?? false) == '?')
                {
                    Response::auto(Documentation::route(self::$route, self::$info[self::$route]));
                }
                else
                {
                    AccessControl::access_granted();
                    $i = 0;
                    foreach ($keys as $key) 
                    {
                        if ($key == '__desc')
                            continue;
                    
                        $param = Router::get_argument_value($key);
                        $type = ReflectionUtils::get_param_type(self::$route, $key);
                        switch ($type) 
                        {
                            case 'integer':
                            case 'int':     $param = (int) $param; break;
                            case 'double':
                            case 'real':
                            case 'float':   $param = (float) $param; break;
                            case 'boolean':
                            case 'bool':    $param = (boolean) ($param == 'true' || $param == 1 ? true : false); break;
                            case 'string':  $param = (string) $param; break;
                            case 'object':  $param = json_decode($param); break;                                        // syntax: { "prop1": "something", "prob2": "else" }
                            case 'array':   $param = json_decode('{ "data": ' . $param . ' }', true)["data"]; break;    // syntax: [ "entry1", "entry2", "entryN" ]
                            default:        echo "Why are we here? Did you forget to declare the type of " . preg_replace('/[^a-zA-Z0-9\_]/', '', $param) . "?\n"; exit;
                        }
                        $params[] = $param;
                        $i++;
                    }
                    call_user_func_array(self::$class . '::' . str_replace(DELIMITER_ROUTES, '_', self::$route), $params);                  
                }
            }
        }

        static private function __routes()
        {
            $endpoints = [];
            $m = (new ReflectionClass(self::$class))->getMethods();
            foreach ($m as $method) 
            {
                if (!$method->isPublic() || !$method->isStatic() || substr($method->name, 0, 2) == "__")
                    continue;
                $endpoints[] = str_replace("_", DELIMITER_ROUTES, $method->name);
            }
            return $endpoints;
        }

        static public function __route($route = null, $endpoints = null)
        {
            $endpoint = [];
            if ($endpoints == null)
                $endpoints = self::__routes();
            if ($route == null)
                $route = self::$route;
            if (in_array($route, $endpoints))
            {
                $method = (new ReflectionClass(self::$class))->getMethod(str_replace(DELIMITER_ROUTES, '_', $route));
                $endpoint["name"] = $route;
                $endpoint["params"] = [];
                $endpoint["desc"] = ANSI::string(ReflectionUtils::get_documentation(self::$class, $route));
                $params = $method->getParameters();
                foreach ($params as $param) 
                {
                    $pname = $param->name;
                    $pdefault = $param->isOptional() ? $param->getDefaultValue() : null;
                    $ptype = $param->getType();
                    if ($ptype != NULL) $ptype = $ptype->getName();
                    $ppos = $param->getPosition();
                    $endpoint["params"][$ppos] = [
                        "name" => $pname,
                        "default" => $pdefault,
                        "type" => $ptype
                    ];
                }
            }
            return $endpoint;
        }

        static public function __export()
        {
            $endpoints = self::__routes();
            $data = [ ];
            $column_widths = [];
            $column_widths_defaults = [];
            foreach ($endpoints as $ep) 
            {
                $ep = self::__route($ep, $endpoints);
                $epn = $ep["name"];
                $epp = $ep["params"];
                $epd = $ep["desc"];
                $data[$epn] = [];
                $data[$epn]['__desc'] = $epd;
                $column_widths[0] = $column_widths_defaults[0] = max($column_widths[0] ?? 0, strlen($epn));
                $i = 1;
                foreach ($epp as $p) 
                {
                    $pn = $p["name"];
                    $pd = $p["default"];
                    $column_widths[$i]          = max($column_widths[$i] ?? 0, strlen($pn));
                    $column_widths_defaults[$i] = max($column_widths_defaults[$i] ?? 0, strlen($pd), $column_widths[$i]);
                    $data[$epn][$pn] = $pd;
                    $i++;
                }
            }
            $data['__widths'] = $column_widths;
            $data['__widths_defaults'] = $column_widths_defaults;

            return $data;
        }
    }
