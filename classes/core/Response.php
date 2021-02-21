<?php
    class Response
    {
        static private function format_input($in)
        {
            if (!is_string($in)) 
                $in = print_r($in, true);

            return $in;
        }

        static public function auto($input)
        {
            echo (!in_array(Router::$mode, [ Router::CLI, Router::MIXED ]) ? self::html($input) : self::text($input));
        }

        static public function html_file($file, ?array $vars = null, int $timeout = 60)
        {
            echo self::format_input(TemplateParser::parse($file, $vars, 'html', 'html', $timeout));
        }

        static public function text($input)
        {
            echo self::format_input($input);
        }

        static public function ansi($input)
        {
            echo ANSI::string(self::format_input($input));
        }

        static public function html($input)
        {
            $input = preg_replace('/\$/', '--DOLLAR--', self::format_input($input));
            echo preg_replace('/--DOLLAR--/', '$', `echo "$input" | ansi2html`);    
        }

        static public function json($input)
        {
            echo json_encode($input, JSON_PRETTY_PRINT);    
        }
    }
