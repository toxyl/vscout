<?php
class Log 
{
	static public function ln($msg, $to_syslog = true)
    {
        if ($to_syslog)
        {
            syslog(LOG_INFO, '[VS ' . (Daemon::is_master() ? 'Daemon' : 'Worker') . ' ' . (Daemon::is_master() ? getmypid() : Daemon::parent_pid() . '-' . getmypid()) . '] ' . $msg);
        }
        else
        {
            Response::ansi('%f8>[VS ' . (Daemon::is_master() ? 'Daemon' : 'Worker') . ' ' . (Daemon::is_master() ? getmypid() : Daemon::parent_pid() . '-' . getmypid()) . ']%rst> ' . $msg . '%rst>%ln>');            
        }
    }

    static public function template_parser($msg)
	{
		if (LOG_TEMPLATE_PARSER)
			self::ln("    $msg");
	}

    static public function click($url, $status, $ip)
    {
        if (!LOG_RESPONSES)
            return;
        
        if ($ip == null || $ip == '')
            $ip = '---.---.---.---';

        $ip = explode(".", trim($ip));
        foreach ($ip as &$seg) 
        {
            $spaces = max(0, 4 - strlen($seg));
            if ($spaces > 0) 
                $seg = str_repeat(' ', $spaces) . $seg;
        }
        $ip = implode('.', $ip);

        $fmt = '';

        switch ($status) {
            case 200:
                $mark   = 'OK';
                break;

            case 500:
            case 501:
            case 502:
            case 503:
                $mark   = 'E!';
                break;

            case 403:
            case 429:
                $mark   = 'FW';
                break;
            
            case 404:
                $mark   = '  ';
                $status = "---";
                break;

            case 0:
                $mark   = ' ?';
                $status = "···";
                break;

            case -1:
                return; // don't log ignored 
                $mark   = ' I';
                $status = "   ";
                break;

            case -2:
                $mark   = 'D!';
                $status = "   ";
                break;

            default:
                $mark   = ' ?';
                break;
        }

        self::ln("$mark $status [$ip] $url");
    }
}
