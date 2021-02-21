<?php
class Installer
{
	static private function cprecursive($src, $dst) 
	{
	    $dir = opendir($src);
	    @mkdir($dst);

	    while (false !== ($file = readdir($dir))) 
	    {
	        if (in_array($file, ['.','..']))
	        	continue; 

            if (is_dir($src . '/' . $file))  cprecursive($src . '/' . $file, $dst . '/' . $file);
            else                             copy($src . '/' . $file, $dst . '/' . $file);
	    }
	    closedir($dir);
	}

	static public function install()
	{
		$phpv = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

        if (TOR_MODE)
        {
		    ProcessControl::timeout(60,
		    	function() use ($phpv) { return CommandIO::exec("service tor top ; service ipchanger stop"); }, null,
		    	function() { return "Failed to stop tor/ipchanger services!"; }, null
		    );        	
        }

        CommandIO::exec("service " . NAME . " stop ; service nginx stop ; service php{$phpv}-fpm stop");

		Response::auto("Installing required software...\n");
		Response::auto(CommandIO::exec("apt-get purge apache2 -y ; apt-get install sqlite3 nginx php{$phpv}-fpm php{$phpv}-sqlite3 php{$phpv}-readline network-manager proxychains tor python3.9-dev python3-pip cython3 gcc iptables whois -y --no-install-recommends") . "\n");

		Response::auto("Installing NginX configuration...\n");
		$whitelist = explode(',', SERVER_WHITELIST);
		foreach ($whitelist as &$ip) 
		{
			$ip = "allow $ip;";
		}
        file_put_contents(
        	NGINX_DIR   . '/' . NAME . '.conf', 
        	TemplateParser::parse(
        		'nginx', 
        		[ 
        			"phpversion" => $phpv, 
        			"whitelist" => trim(implode("\n\t", $whitelist)) 
        		], 
        		'configs', 
        		'conf'
        	)
        );
        CommandIO::exec('rm ' . NGINX_DIR . '/default'); # get rid off the default nginx site
        CommandIO::exec('rm ' . WEB_DIR . '/index.*');
        @symlink(BIN_DIR . '/' . NAME, WEB_DIR . '/index.php');
        CommandIO::exec('chown www-data:www-data ' . WEB_DIR . '/index.php'); # make accessible for nginx
        CommandIO::exec("service nginx start");
        CommandIO::exec("service php{$phpv}-fpm start");

		Response::auto("Installing " . NAME . " service...\n");
        file_put_contents(
        	SERVICE_DIR . '/' . NAME . '.service', 
        	TemplateParser::parse('vscout', [], 'configs', 'service')
        );

		Response::auto("Retrieving public IP...\n");
        $ip = CommandIO::exec('curl --silent ' . IP_CHECK_URL);
        Response::ansi('Is %f3>' . $ip . '%rst> your public IP? [y|N] %ln>');
        $answer = readline();
        $is_correct = substr(strtolower($answer), 0, 1);

    	while ($is_correct != 'y')
    	{
	        Response::ansi('%ln>What is your public IP then? %ln>');
	        $ip = readline();
	        Response::ansi('Is %f3>' . $ip . '%rst> really your public IP? [y|N] %ln>');
	        $answer = readline();
	        $is_correct = substr(strtolower($answer), 0, 1);        		
    	}

        file_put_contents(DataFile::path(FILE_IP_BLACKLIST), $ip);

		Response::auto("Installing ipchanger...\n");
		self::cprecursive(DATA_DIR . '/ipchanger/', DATA_DIR . '/../ipchanger/');
		CommandIO::exec('chmod +x ' . DATA_DIR . "/../ipchanger/install.sh");
		Response::auto(CommandIO::exec("cd " . DATA_DIR . "/../ipchanger ; ./install.sh") . "\n");
		CommandIO::exec('rm -rf ' . DATA_DIR . "/../ipchanger");

        file_put_contents('/etc/tor/ipchanger_realip', $ip);
		Response::auto("Installing ipchanger service...\n");
        file_put_contents(
        	SERVICE_DIR . '/ipchanger.service', 
	        TemplateParser::parse('ipchanger', [], 'configs', 'service')
	    );

        if (TOR_MODE)
        {
		    ProcessControl::timeout(60,
		    	function() { return CommandIO::exec("service tor start && service ipchanger start"); }, null,
		    	function() { return "Failed to start Tor & IPChanger!"; }, null
		    );        	
        }

        Response::ansi("%ln>%f2>Done!%rst>%ln>");
	}
}
