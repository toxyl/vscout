<?php
    class ReflectionUtils
    {
        static public function get_param_type($route, $param)
        {
            $params = Routes::__route($route)["params"];
            while (count($params) > 0) 
            {   
                $p = array_shift($params);
                if ($p["name"] == $param)
                    return $p["type"]; 
            }
            return null;
        }
        static public function get_documentation($class, $route, $only_meta = false)
        {
            if ($route == '')
                return '';

            $doc = (new ReflectionMethod($class, str_replace(DELIMITER_ROUTES, '_', $route)))->getDocComment();
            if ($only_meta) 
            {
                preg_match_all('/[^\n]*\*\s*@([a-zA-Z]+[a-zA-Z0-9_]*) {1,}([^\n]*)\n/', $doc, $matches, PREG_SET_ORDER);
                $doc = [];
                foreach ($matches as $match) 
                {
                    $doc[$match[1]] = $match[2]; 
                }
            }   
            else
            {
                $doc = preg_replace('/[^\n]*\*\s*@([a-zA-Z]+[a-zA-Z0-9_]*) {1,}[^\n]*\n/', '', $doc);
                $doc = " " . preg_replace('/\n/', "\n ", preg_replace('/\s*\/\*{2,}\s*\n([\s\S]+)\s*\*{0,1}\/\s*/', '$1', preg_replace('/\n\s*\*\s*?/', "\n", $doc)));
            }           
            return $doc;
        }
        static public function get_meta_info($class, $route, $prop, $default, $value_delimiter = '/\s+/')
        {
            $meta = self::get_documentation($class, $route, true);

            if (!isset($meta[$prop]))
                return $default;
            return preg_split($value_delimiter, $meta[$prop]);
        }
    }
