<?php
class BackgroundProcess
{
	static public $procs = [];

    static public function start(
	    $command,
	    $cwd = null,
	    $env = null,
	    $other_options = null
	) {
	    $descriptorspec = array(
	        1 => array('file', LOG_FILE_INFO, 'w'),
	        2 => array('file', LOG_FILE_ERROR, 'w'),
	    );

	    $proc = @proc_open("exec " . $command, $descriptorspec, $pipes, $cwd, $env, $other_options);

	    if (!is_resource($proc)) {
	        throw new \Exception("Failed to start background process for $command");
	    }
	    self::$procs[] = $proc;
	    return $proc;
	}

	static public function count() 
	{
		return count(self::$procs);
	}

	static public function active_pids($command = null)
	{
		$pids = [];
		
		if (Daemon::is_master())
		{
			foreach (self::$procs as $proc) 
			{
				$pinfo = proc_get_status($proc);

				// processes do not have a pid at all times,
				// probably they are still booting,
				// so we check every second for the next 30 seconds
				// if the process has a pid.  
				$i = 0;
				while ($i < 30)
				{
					if (isset($pinfo['pid']))
					{
						$pids[] = $pinfo['pid'];
						break;
					}
					sleep(1);
					$pinfo = proc_get_status($proc);
					$i++;
				}
			}
		}
		else
		{
			// A worker doesn't know what the Daemon status is, 
			// so it has to rely on ps aux to figure out 
			// how many active workers there are.
			$pids = explode("\n", CommandIO::exec('ps aux | grep "' . $command . '" | grep -v grep | awk \'{ print $2 }\''));
		}

		return $pids;
	}

	static public function stop_first(callable $on_success = null)
	{
		foreach (self::$procs as &$proc) 
		{
			$pinfo = proc_get_status($proc);
			if ($pinfo['running'])
			{
				proc_terminate($proc, 15);
				$proc = false;
				if ($on_success != null)
					$on_success();
				break;
			}
		}
		self::$procs = array_filter(self::$procs);
	}

	/**
	 * Stops all started processes if $pid == null,
	 * otherwise stops the process with the given ID.
	 */
	static public function stop_all(?int $pid)
	{
		foreach (self::$procs as &$proc) 
		{
			$pinfo = proc_get_status($proc);
			if ($pid == null || $pinfo['pid'] == $pid)
			{
				if ($pid == null || $pinfo['running'])
					proc_terminate($proc, 15);
	
				$proc = false;

				if ($pid != null)
					break;
			}
		}
		self::$procs = array_filter(self::$procs);
	}

	static public function gc()
	{
		foreach (self::$procs as &$proc) 
		{
			$pinfo = proc_get_status($proc);

			if (!$pinfo['running'])
				$proc = false;
		}
		self::$procs = array_filter(self::$procs);
	}
}
