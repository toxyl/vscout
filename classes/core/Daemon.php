<?php 
class Daemon
{
	static private $is_master = false;

	public static function parent_pid(): int 
	{
	    return self::$is_master ? getmypid() : posix_getppid(); // (int) (explode (" ", file_get_contents("/proc/" . posix_getppid() . "/stat"))[3]);
	}

	static public function is_master()
	{
		return self::$is_master;
	}

	static public function active()
	{
		return count(BackgroundProcess::active_pids(WORKER_CMD)); 
	}

	static public function stop()
	{
		if (!self::$is_master)
			return;

		Log::ln("Stopping workers...");
		BackgroundProcess::stop_all();
		Log::ln('Stopped all workers.');

		Log::ln("Stopping services...");
        CommandIO::exec("service nginx stop");

        if (TOR_MODE)
        {
            CommandIO::exec("service ipchanger stop");               
            CommandIO::exec("service tor stop");
        }
	}

	static private function remove_worker()
	{
		if (!self::$is_master)
			return;

		BackgroundProcess::stop_first(function(){ posix_kill(self::parent_pid(), SIGCHLD); });
	}

	static private function add_worker()
	{
		if (!self::$is_master)
			return;

		BackgroundProcess::start(WORKER_CMD);
	}

	static public function spawn_worker() 
	{
        while (true)
        {
            Scout::click(RandomDataGenerator::generate_random_url());
            usleep(10); 
        }
	}

	static public function start()
	{
		self::$is_master = true;

		Log::ln("Starting services...");
        CommandIO::exec("service nginx start");

        if (TOR_MODE)
        {
            CommandIO::exec("service tor start");
            CommandIO::exec("service ipchanger start");               
        }

		Log::ln("Starting daemon...");
		$i = 0;
        while ($i < WORKERS_MIN) 
        {
        	self::add_worker();
        	sleep(2);
            $i++;
        }

		Log::ln("Daemon started with " . WORKERS_MIN . " workers.");

        $t_stats = $t = time();

        while (true) 
        {
        	if (time() - $t_stats > STATS_UPDATE)
        	{
				$tu = time();
           	 	Data::stats_update(); 
           	 	$as = Stats::average_speed();
				Log::ln("Stats updated in " . (time() - $tu) . "s. Average speed was " . sprintf('%.2f / minute (%d / day).', $as, $as * 60 * 24));
           	 	$t_stats = time();
           	 	continue;
        	}
        	
        	if (time() - $t > LOAD_CHECK_TIME) # check if we need to start or stop a worker to optimize load utilization
        	{
        		BackgroundProcess::gc();
	        	$load = Stats::load_averages()['5m'];
	        	$c = BackgroundProcess::count();
	        	if ($load > LOAD_THRES_MAX && $c > WORKERS_MIN) 
        		{
        			self::remove_worker();
					Log::ln('Removed a worker, ' . BackgroundProcess::count() . ' are still active. Load average 5m: ' . $load);
        		}
	        	else if ($load < LOAD_THRES_MIN && $c < WORKERS_MAX) 
        		{
        			self::add_worker();
					Log::ln('Added a worker, ' . BackgroundProcess::count() . ' are now active. Load average 5m: ' . $load);
        		}
	        	$t = time();
	        	continue;
        	}

           	sleep(10); # avoid checking too often as it will use resources unnecessarily 
     	}
	}

}
