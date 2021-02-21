<?php    
    class ANSI
    {
        static public function string($fmt, ...$val)
        {
            preg_match_all('/%(~|_|\*|l|r|f|b|bf|fb)(\d+|st|n)(?:,(\d+)){0,1}>/', $fmt, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) 
            {
                $fg = "";
                $bg = "";
                switch ($match[1]) 
                {
                    case 'r': # reset
                        if ($match[2] == 'st')
                            $fg = "\e[0m";
                        break;
                    case 'l':
                        if ($match[2] == 'n') # new line
                            $fg = "\n";
                        break;
                    case '_': # underline
                        $fg = "\e[4m\e[38;5;" . $match[2] . "m";
                        break;
                    case '~': # italic
                        $fg = "\e[3m\e[38;5;" . $match[2] . "m";
                        break;
                    case '*': # bold
                        $fg = "\e[1m\e[38;5;" . $match[2] . "m";
                        break;
                    case 'f': # foreground
                        $fg = "\e[38;5;" . $match[2] . "m";
                        break;
                    
                    case 'b': # background
                        $bg = "\e[48;5;" . $match[2] . "m";
                        break;
                    case 'bf': # both, background first
                        $bg = "\e[48;5;" . $match[2] . "m";
                        $fg = "\e[38;5;" . $match[3] . "m";
                        break;
                    case 'fb': # both, foreground first
                        $fg = "\e[38;5;" . $match[2] . "m";
                        $bg = "\e[48;5;" . $match[3] . "m";
                        break;
                    default: # error
                        break;
                }
                $fmt = str_replace($match[0], $bg.$fg, $fmt);
            }
            $res = vsprintf($fmt, $val);

            return $res;
        }
    }
