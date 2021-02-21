<?php
    class Documentation
    {
        static public function route($route, $args, $max_len = null, $print_example = true)
        {
            $max_len = $max_len != null ? $max_len : strlen($route);
            $route_formatted = ANSI::string('%*2>%s%rst>', Router::$route);
            $definition     = " ";
            $example_mixed  = "curl http://" . SERVER_IP . ":" . SERVER_PORT . "/" . NAME . '/?' . $route_formatted . ' -d \'';
            $example_get    = "curl http://" . SERVER_IP . ":" . SERVER_PORT . "/" . NAME . '/?' . $route_formatted . '=';
            $example_post   = "curl http://" . SERVER_IP . ":" . SERVER_PORT . "/" . NAME . '/index.php -d \'' . $route_formatted . '=';
            $example_cli    = NAME . " " . $route_formatted . " ";
            $types = " ";
            if (!$print_example)
            {   
                $definition .= ANSI::string("%b0>%*7>  %-" . $max_len . "s %rst> ", $route);
                $types .= ANSI::string("%b0>%*7> %-" . $max_len . "s  %rst> ", ' ');
            }
            else
            {
                $definition .= "    ";
                $types .= "    ";
            }

            $i = 1;
            foreach ($args as $key => $default) 
            {
                if ($key == '__desc')
                    continue;
                if ($default === null)          $default = "NULL";
                else if ($default === false)    $default = "0";
                else if ($default === true)     $default = "1";
                $c = 2;
                $t = '';
                if ($route != '?' && $key != '?')
                {
                    $t = ReflectionUtils::get_param_type($route, $key);
                    switch ($t) 
                    {
                        case 'integer':
                        case 'int':     $c = 2; break;
                        case 'double':
                        case 'real':
                        case 'float':   $c = 3; break;
                        case 'boolean':
                        case 'bool':    $c = 4; break;
                        case 'string':  $c = 1; break;
                        case 'object':  $c = 5; break;
                        case 'array':   $c = 6; break;
                        default:        
                            if ($key == '?') $c = 2;
                            else echo "Why are we here? Did you forget to declare the type of $key?\n"; 
                            exit;
                    }                   
                }
                $cw = max(strlen($t), $print_example ? strlen($key) : Router::$routes['__widths'][$i] ?? 0) + 1;
                $default = str_replace("\n", '\n', $default);
                $definition .= ANSI::string("%b0>%f0>%b$c> %-" . $cw . "s%rst>%f$c>%rst> ", "$key");
                if ($print_example) 
                {
                    $example_mixed .=  ANSI::string('%*4>%s%rst>', $default) . DELIMITER_POST;
                    $example_post .=  ANSI::string('%*4>%s%rst>', $default) . DELIMITER_POST;
                    $example_get .=  ANSI::string('%*4>%s%rst>', $default) . DELIMITER_GET;
                    $example_cli .=  ANSI::string('%*4>%s%rst>', (substr_count($default, ' ') > 0 || $t == 'string' ? ANSI::string("%rst>'%*4>") . "$default" . ANSI::string("%rst>'%*4>") : $default)) . ' ';
                }
                $types   .= ANSI::string("%b0>%*$c> %-" . $cw . "s%rst>%f0>%rst> ", strlen($t) > $cw ? substr($t, 0, $cw - 3) . "..." : $t);
                $c++;
                $i++;
            }
            $example_post = (($i > 1) ? substr($example_post, 0, -strlen(DELIMITER_POST)) : substr($example_post, 0, -1)) . '\'';
            $example_mixed = (($i > 1) ? substr($example_mixed, 0, -strlen(DELIMITER_POST)) : $example_mixed) . '\'';
            $example_get = substr($example_get, 0, -strlen(DELIMITER_GET));

            $acl = AccessControl::get_acl($route);
               
            return !in_array(Router::$mode, $acl) && $route != null ? null : ($print_example 
                    ? 
                        ANSI::string(
                            '%ln>'.
                            ' %*2>%s%rst>%ln>'.
                            '    %s%rst>%ln>'.
                            '%s%rst>', 
                            $route,
                            preg_replace('/(\n|%ln>)/', "$1    ", $args['__desc']),
                            (
                                ($i > 1 ? $definition . "\n" : '') .
                                ($i > 1 ? $types . "\n\n" : '') .
                                
                                (in_array(Router::CLI, $acl)   ? ANSI::string('      %*3>%-6s%rst> %s%rst>%ln>', 'CLI', $example_cli) : '') . 
                                ($i > 1 && in_array(Router::MIXED, $acl) ? ANSI::string('      %*3>%-6s%rst> %s%rst>%ln>', 'MIXED', $example_mixed) : '') . 
                                (in_array(Router::GET, $acl)   ? ANSI::string('      %*3>%-6s%rst> %s%rst>%ln>', 'GET', $example_get) : '') . 
                                (in_array(Router::POST, $acl)  ? ANSI::string('      %*3>%-6s%rst> %s%rst>%ln>', 'POST', $example_post) : '') . "\n"
                            )
                        ) 
                    : 
                        $definition . "\n" .
                        $types . "\n"
                );
        }
    
        static public function routes()
        {
            $definitions = [];
            $help = null;
            foreach (Router::$routes as $route => $args) 
            {
                if (substr($route, 0, 2) == "__")
                    continue;

                $help = self::route($route, $args, Router::$routes['__widths'][0], false);

                if ($help)
                    $definitions[] = $help;
            }
            
            $definitions = implode("\n", $definitions);
            return ANSI::string(
                ' %ln>'.
                ' %*2>%_2>%s%rst> %*3>v%s%rst>%ln>'.
                '    %s%ln>'.
                ' %ln>'.
                ' %*2>%_2>Commands%rst>%ln>%ln>'.
                '%s%ln>'.
                ' %*2>%_2>Types%rst>%ln>'.
                '    The colors of the arguments represent their type and can be one of these:%ln>' .
                '    %*1>string%rst>, %*2>int%rst>, %*3>float%rst>, %*4>bool%rst>, %*5>object%rst>, %*6>array%rst>%ln>' .
                ' %ln>', 
                NAME, 
                VERSION,
                preg_replace('/\n/', "\n    ", ANSI::string(DESCRIPTION)),
                $definitions);
        }
    }
