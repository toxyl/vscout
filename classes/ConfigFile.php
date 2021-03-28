<?php 
class Config
{
	static private $data = null;

	static public function init($reload = false)
	{
		if (self::$data == null || $reload)
		{
			$cfg = file_get_contents(DATA_DIR . '/config.json');
			$cfg_stripped = preg_replace('/^\s*\/\/.*\s*$/m', '', $cfg);

			self::$data = json_decode($cfg_stripped, true); # load file, strip comments, decode as assoc array

			define('SERVER_INTERFACE',     	self::$data["server"]["interface"]);
			define('SERVER_IP',     	  	self::$data["server"]["ip"]);
			define('SERVER_PORT',     		self::$data["server"]["port"]);
			define('SERVER_WHITELIST',    	implode(", ", self::$data["server"]["whitelist"]));
			define('TOR_MODE',              self::$data["tor_mode"]);
			define('CHECK_FIREWALLS',       self::$data["check_firewalls"]);
			define('IP_CHECK_URL',          self::$data["ip_check_url"]);
			define('WORKERS_MIN',           self::$data["workers"]["min"]);
			define('WORKERS_MAX',           self::$data["workers"]["max"]);
			define('LOAD_THRES_MIN',        self::$data["workers"]["spawn_threshold"]);
			define('LOAD_THRES_MAX',        self::$data["workers"]["destroy_threshold"]);
			define('LOAD_CHECK_TIME',       self::$data["workers"]["update_time"]);
			define('LOG_RESPONSES',         self::$data["log"]["responses"]);
			define('LOG_TEMPLATE_PARSER',   self::$data["log"]["parser"]);
			define('EXEC_BUFFER_SIZE',      self::$data["database"]["insert_buffer_size"]);
			define('EXEC_BUFFER_TIME',      self::$data["database"]["insert_buffer_flush_time"]);
			define('STATS_UPDATE',          self::$data["database"]["stats_update_time"]);
			define('DATA_RETENTION',        self::$data["database"]["data_retention"]);
			define('FILE_IP_BLACKLIST',     self::$data["files"]["ip_blacklist"]);
			define('FILE_USER_AGENTS',      self::$data["files"]["user_agents"]);
			define('FILE_USERNAMES',        self::$data["files"]["user_names"]);
			define('FILE_DOMAINS',          self::$data["files"]["domains"]);
			define('FILE_URLS',             self::$data["files"]["urls"]);
			define('FILE_WORDLISTS',        self::$data["files"]["wordlists"]);
			define('LOG_FILE_INFO',         LOG_DIR . '/' . NAME . '-info.log');
			define('LOG_FILE_ERROR',        LOG_DIR . '/' . NAME . '-error.log');
			define('WORKER_CMD',            NAME . ' worker');
		} 
	}
}
