<?php
    class Database extends SQLite3 
    {
        private $initialized = false;

        function __construct() 
        {
            $this->open(DataFile::path('data.db'));
            $this->initialized = true;
        }

        public function execute($sql, $retries = 0) 
        {
            if ($retries > 60 || !$this->initialized) 
            {
                return;
            }

            $res = @$this->exec($sql);
            if (!$res) 
            {
                $e = $this->lastErrorMsg();
                if (substr_count($e, 'locked') > 0 || substr_count($e, 'busy') > 0)
                {
                    sleep(1);
                    return self::execute($sql, ++$retries);
                }
                return;
            }
        }

        public function get($sql, $retries = 0) 
        {
            if ($retries > 60 || !$this->initialized) 
            {
                return [];
            }

            $res = @$this->query($sql);
            if (!$res) 
            {
                $e = $this->lastErrorMsg();
                if (substr_count($e, 'locked') > 0 || substr_count($e, 'busy') > 0)
                {
                    sleep(1);
                    return self::get($sql, ++$retries);
                }
                return [];
            }

            $records = [];

            while ($row = @$res->fetchArray(SQLITE3_ASSOC))
            {
                $records[] = array_values($row);
            }
            return $records;
        }

        private static $last_stats_update = null;
        private static $count_stats_updates = 0;
        private static $sql_stats_update = "BEGIN EXCLUSIVE;
                DROP TABLE IF EXISTS t5xx_1d;
                DROP TABLE IF EXISTS t5xx_1h;
                DROP TABLE IF EXISTS t5xx_15m;
                DROP TABLE IF EXISTS t5xx_5m;

                DROP TABLE IF EXISTS t4xx_1d;
                DROP TABLE IF EXISTS t4xx_1h;
                DROP TABLE IF EXISTS t4xx_15m;
                DROP TABLE IF EXISTS t4xx_5m;

                DROP TABLE IF EXISTS t3xx_1d;
                DROP TABLE IF EXISTS t3xx_1h;
                DROP TABLE IF EXISTS t3xx_15m;
                DROP TABLE IF EXISTS t3xx_5m;

                DROP TABLE IF EXISTS t2xx_1d;
                DROP TABLE IF EXISTS t2xx_1h;
                DROP TABLE IF EXISTS t2xx_15m;
                DROP TABLE IF EXISTS t2xx_5m;

                DROP TABLE IF EXISTS t1xx_1d;
                DROP TABLE IF EXISTS t1xx_1h;
                DROP TABLE IF EXISTS t1xx_15m;
                DROP TABLE IF EXISTS t1xx_5m;

                DROP TABLE IF EXISTS t5m;
                DROP TABLE IF EXISTS t15m;
                DROP TABLE IF EXISTS t1h;
                DROP TABLE IF EXISTS t1d;

                DROP TABLE IF EXISTS tt;

                CREATE TEMP TABLE  tt  AS SELECT `status`, `ts` FROM  `clicks`;
                CREATE TEMP TABLE  t1d AS SELECT status, COUNT(status) AS amount FROM tt WHERE ts IS NOT NULL AND ts >= datetime('now',  '-1 day'    ) GROUP BY status ORDER BY status;
                CREATE TEMP TABLE  t1h AS SELECT status, COUNT(status) AS amount FROM tt WHERE ts IS NOT NULL AND ts >= datetime('now',  '-1 hour'   ) GROUP BY status ORDER BY status;
                CREATE TEMP TABLE t15m AS SELECT status, COUNT(status) AS amount FROM tt WHERE ts IS NOT NULL AND ts >= datetime('now', '-15 minutes') GROUP BY status ORDER BY status;
                CREATE TEMP TABLE  t5m AS SELECT status, COUNT(status) AS amount FROM tt WHERE ts IS NOT NULL AND ts >= datetime('now',  '-5 minutes') GROUP BY status ORDER BY status;

                CREATE TEMP TABLE   t1xx_1d AS SELECT SUM(amount) AS c FROM  t1d WHERE status >  99 AND status < 200;
                CREATE TEMP TABLE   t1xx_1h AS SELECT SUM(amount) AS c FROM  t1h WHERE status >  99 AND status < 200;
                CREATE TEMP TABLE  t1xx_15m AS SELECT SUM(amount) AS c FROM t15m WHERE status >  99 AND status < 200;
                CREATE TEMP TABLE   t1xx_5m AS SELECT SUM(amount) AS c FROM  t5m WHERE status >  99 AND status < 200;

                CREATE TEMP TABLE   t2xx_1d AS SELECT SUM(amount) AS c FROM  t1d WHERE status > 199 AND status < 300;
                CREATE TEMP TABLE   t2xx_1h AS SELECT SUM(amount) AS c FROM  t1h WHERE status > 199 AND status < 300;
                CREATE TEMP TABLE  t2xx_15m AS SELECT SUM(amount) AS c FROM t15m WHERE status > 199 AND status < 300;
                CREATE TEMP TABLE   t2xx_5m AS SELECT SUM(amount) AS c FROM  t5m WHERE status > 199 AND status < 300;

                CREATE TEMP TABLE   t3xx_1d AS SELECT SUM(amount) AS c FROM  t1d WHERE status > 299 AND status < 400;
                CREATE TEMP TABLE   t3xx_1h AS SELECT SUM(amount) AS c FROM  t1h WHERE status > 299 AND status < 400;
                CREATE TEMP TABLE  t3xx_15m AS SELECT SUM(amount) AS c FROM t15m WHERE status > 299 AND status < 400;
                CREATE TEMP TABLE   t3xx_5m AS SELECT SUM(amount) AS c FROM  t5m WHERE status > 299 AND status < 400;

                CREATE TEMP TABLE   t4xx_1d AS SELECT SUM(amount) AS c FROM  t1d WHERE status > 399 AND status < 500;
                CREATE TEMP TABLE   t4xx_1h AS SELECT SUM(amount) AS c FROM  t1h WHERE status > 399 AND status < 500;
                CREATE TEMP TABLE  t4xx_15m AS SELECT SUM(amount) AS c FROM t15m WHERE status > 399 AND status < 500;
                CREATE TEMP TABLE   t4xx_5m AS SELECT SUM(amount) AS c FROM  t5m WHERE status > 399 AND status < 500;

                CREATE TEMP TABLE   t5xx_1d AS SELECT SUM(amount) AS c FROM  t1d WHERE status > 499 AND status < 600;
                CREATE TEMP TABLE   t5xx_1h AS SELECT SUM(amount) AS c FROM  t1h WHERE status > 499 AND status < 600;
                CREATE TEMP TABLE  t5xx_15m AS SELECT SUM(amount) AS c FROM t15m WHERE status > 499 AND status < 600;
                CREATE TEMP TABLE   t5xx_5m AS SELECT SUM(amount) AS c FROM  t5m WHERE status > 499 AND status < 600;

                DROP TABLE IF EXISTS `stats`;
                CREATE TABLE `stats` AS
                SELECT
                    (SELECT COUNT(DISTINCT name) AS active       FROM domain WHERE firewalled=0 AND dead=0 AND ip IS NOT NULL) AS active,
                    (SELECT COUNT(DISTINCT name) AS firewalled   FROM domain WHERE firewalled=1                              ) AS firewalled,
                    (SELECT COUNT(DISTINCT name) AS dead         FROM domain WHERE                  dead=1                   ) AS dead,

                    (SELECT COUNT(*) FROM tt WHERE status >  99 AND status < 200) AS `total_1xx`,
                    (SELECT COUNT(*) FROM tt WHERE status > 199 AND status < 300) AS `total_2xx`,
                    (SELECT COUNT(*) FROM tt WHERE status > 299 AND status < 400) AS `total_3xx`,
                    (SELECT COUNT(*) FROM tt WHERE status > 399 AND status < 500) AS `total_4xx`,
                    (SELECT COUNT(*) FROM tt WHERE status > 499 AND status < 600) AS `total_5xx`,

                    t1xx_1d.`c` AS `1d_1xx`,
                    t2xx_1d.`c` AS `1d_2xx`,
                    t3xx_1d.`c` AS `1d_3xx`,
                    t4xx_1d.`c` AS `1d_4xx`,
                    t5xx_1d.`c` AS `1d_5xx`,

                    t1xx_1h.`c` AS `1h_1xx`,
                    t2xx_1h.`c` AS `1h_2xx`,
                    t3xx_1h.`c` AS `1h_3xx`,
                    t4xx_1h.`c` AS `1h_4xx`,
                    t5xx_1h.`c` AS `1h_5xx`,

                    t1xx_15m.`c` AS `15m_1xx`,
                    t2xx_15m.`c` AS `15m_2xx`,
                    t3xx_15m.`c` AS `15m_3xx`,
                    t4xx_15m.`c` AS `15m_4xx`,
                    t5xx_15m.`c` AS `15m_5xx`,

                    t1xx_5m.`c` AS `5m_1xx`,
                    t2xx_5m.`c` AS `5m_2xx`,
                    t3xx_5m.`c` AS `5m_3xx`,
                    t4xx_5m.`c` AS `5m_4xx`,
                    t5xx_5m.`c` AS `5m_5xx`
                FROM
                    t1xx_1d,
                    t2xx_1d,
                    t3xx_1d,
                    t4xx_1d,
                    t5xx_1d,
                    t1xx_1h,
                    t2xx_1h,
                    t3xx_1h,
                    t4xx_1h,
                    t5xx_1h,
                    t1xx_15m,
                    t2xx_15m,
                    t3xx_15m,
                    t4xx_15m,
                    t5xx_15m,
                    t1xx_5m,
                    t2xx_5m,
                    t3xx_5m,
                    t4xx_5m,
                    t5xx_5m
                ;

                CREATE TABLE IF NOT EXISTS stats_history (
                    ts    DATE DEFAULT CURRENT_TIMESTAMP,
                    total INTEGER DEFAULT 0,
                    total_1xx INTEGER DEFAULT 0,
                    total_2xx INTEGER DEFAULT 0,
                    total_3xx INTEGER DEFAULT 0,
                    total_4xx INTEGER DEFAULT 0,
                    total_5xx INTEGER DEFAULT 0,
                    avg_s NUMERIC DEFAULT 0,
                    avg_m NUMERIC DEFAULT 0,
                    avg_h NUMERIC DEFAULT 0,
                    avg_d NUMERIC DEFAULT 0
                );

                COMMIT;";

        public function update_stats()
        {
            $rbefore = $this->query('SELECT * FROM stats LIMIT 1');
            $rbefore = @$rbefore->fetchArray(SQLITE3_ASSOC) ?? [];

            $this->execute(self::$sql_stats_update);

            $rafter = $this->query('SELECT * FROM stats LIMIT 1');
            $rafter = @$rafter->fetchArray(SQLITE3_ASSOC) ?? [];

            $last_stats_update_delta = 0;
            if (self::$last_stats_update)
            {
                $last_stats_update_delta = time() - self::$last_stats_update;

                if (count($rbefore) == 0)
                {
                    $v = [
                        'total_1xx' => 0,
                        'total_2xx' => 0,
                        'total_3xx' => 0,
                        'total_4xx' => 0,
                        'total_5xx' => 0,
                    ];
                } 
                else
                {
                    $v = [
                        'total_1xx' => ($rafter['total_1xx'] ?? 0) - ($rbefore['total_1xx'] ?? 0),
                        'total_2xx' => ($rafter['total_2xx'] ?? 0) - ($rbefore['total_2xx'] ?? 0),
                        'total_3xx' => ($rafter['total_3xx'] ?? 0) - ($rbefore['total_3xx'] ?? 0),
                        'total_4xx' => ($rafter['total_4xx'] ?? 0) - ($rbefore['total_4xx'] ?? 0),
                        'total_5xx' => ($rafter['total_5xx'] ?? 0) - ($rbefore['total_5xx'] ?? 0),
                    ];
                }
                $v['total'] = array_sum(array_values($v));
                $v['avg_s'] = $last_stats_update_delta <= 0 ? 0 : $v['total'] / $last_stats_update_delta;
                $v['avg_m'] = $last_stats_update_delta <= 0 ? 0 : $v['avg_s'] * 60;
                $v['avg_h'] = $last_stats_update_delta <= 0 ? 0 : $v['avg_m'] * 60;
                $v['avg_d'] = $last_stats_update_delta <= 0 ? 0 : $v['avg_h'] * 24;
            
                $this->exec("INSERT INTO stats_history(total, total_1xx, total_2xx, total_3xx, total_4xx, total_5xx, avg_s, avg_m, avg_h, avg_d) VALUES(".
                    $v['total'].", ".
                    $v['total_1xx'].", ".
                    $v['total_2xx'].", ".
                    $v['total_3xx'].", ".
                    $v['total_4xx'].", ".
                    $v['total_5xx'].", ".
                    $v['avg_s'].", ".
                    $v['avg_m'].", ".
                    $v['avg_h'].", ".
                    $v['avg_d'].");"
                );
            }

            self::$last_stats_update = null;

            if (self::$count_stats_updates < 10)
            {
                self::$last_stats_update = time();
                self::$count_stats_updates++;
            }
            else
            {
                // clean DB
                $this->execute("DELETE FROM clicks WHERE ts IS NOT NULL AND ts <= datetime('now',  '-".DATA_RETENTION."');");
                $this->execute(self::$sql_stats_update);

                self::$count_stats_updates = 0;
            }
        }

        public function get_stats(string $fields = '*')
        {
            $stats = $this->get('SELECT '.$fields.' FROM stats;');
            $rsum = $this->query('SELECT SUM(total_1xx) AS total_1xx, SUM(total_2xx) AS total_2xx, SUM(total_3xx) AS total_3xx, SUM(total_4xx) AS total_4xx, SUM(total_5xx) AS total_5xx FROM stats_history');
            $rsum = @$rsum->fetchArray(SQLITE3_ASSOC) ?? null;

            if ($rsum)
            {
                $stats[0][3] = $rsum['total_1xx'];
                $stats[0][4] = $rsum['total_2xx'];
                $stats[0][5] = $rsum['total_3xx'];
                $stats[0][6] = $rsum['total_4xx'];
                $stats[0][7] = $rsum['total_5xx'];
            }

            return $stats;
        }

        public function get_results_by_status_code(int $min = 500, int $max = 500)
        {
            return $this->get('SELECT status, prot, domain, path FROM clicks WHERE status >= '.$min.' AND status <= '.$max.';');
        }
    }
