<?php
    class Data
    {
        static private $db = null;

        static public function close()
        {
            if (self::$db != null)
            {
                try 
                {
                    if (Router::is_cli())
                    {
                        DataExecBuffer::flush();
                        self::$db->exec('PRAGMA optimize;');                        
                    }
                    self::$db->close();
                }
                catch (\Throwable $e)
                {
                    // do nothing
                }
            }
        }

        static public function exec(string $query)
        {
            if (self::$db == null)
            {
                self::$db = new Database();
            }
            self::$db->execute($query);
        }

        static public function exec_buffered(string $query)
        {
            DataExecBuffer::add($query);
        }

        static public function query(string $query)
        {
            if (self::$db == null)
            {
                self::$db = new Database();
            }
            return self::$db->get($query);
        }

        static public function sql(string $file, string $db_file = null)
        {
            $db_file = $db_file ?? DataFile::path('data.db');
            `( echo .read '$file' ) | sqlite3 '$db_file'`;
        }

        static private function map_fields($fields)
        {
            return array_map(
                function($f) {
                    return '`' . $f . '`';
                }, 
                is_array($fields) ? $fields : preg_split('/\s*,\s*/', $fields)
            );
        }
        
        static private function map_values($values)
        {
            return array_map(
                function($v) {
                    if ($v == null)
                        $v = 'NULL';

                    $v = trim($v);

                    if (!is_numeric($v))
                        $v = "'" . implode("''", preg_split('/\'/', $v)) . "'";

                    return $v;
                }, 
                is_array($values) ? $values : preg_split('/\s*,\s*/', $values)
            );
        }

        static private function map_pairs($pairs)
        {
            $res = [
                "keys" => [],
                "values" => [],
                "pairs" => [],
            ];

            foreach ($pairs as $k => $v) 
            {
                $k = '`' . $k . '`';
                if ($v === null)
                    $v = 'NULL';

                $v = trim($v);

                if (!is_numeric($v))
                    $v = "'" . implode("''", preg_split('/\'/', $v)) . "'";

                $res['keys'][]   = $k; 
                $res['values'][] = $v; 
                $res['pairs'][]  = $k . '=' . $v; 
            }

            return $res;
        }
        
        /**
         * Inserts a record into the $table if it doesn't exist.  
         * If the $pk parameter is set (i.e. not empty or null)
         * any record matching the $pk parameter will be updated
         * with the given values.
         * $data has to be key-value formatted (e.g. [ 'mykey' => 'myvalue' ]).
         */
        static public function upsert(string $table, ?string $pk, array $data, string $db_file = null)
        {
            $data = self::map_pairs($data);

            self::exec_buffered("INSERT OR IGNORE INTO $table (".implode(',', $data['keys']).") VALUES (".implode(',', $data['values']).");", $db_file);

            if ($pk != null && trim($pk) != '')
            {
                $set = implode(', ', $data['pairs']);
                $where = [];
                $i = 0;
                $l = min(count($data['keys']), count($data['pairs']));
                while ($i < $l)
                {
                    if ($data['keys'][$i] == '`' . $pk . '`')
                    {
                        $where[] = $data['pairs'][$i];
                        break;
                    } 
                    $i++;
                }
                self::exec_buffered("UPDATE $table SET $set WHERE " . implode(' AND ', $where) . ";", $db_file);
            }
        }

        /**
         * Checks if a record exists where ALL key-value pairs of the $conditions array match.
         * If $conditions is null or an empty array the method will check if the table has records.
         */
        static public function has_record(string $table, ?array $conditions = null)
        {
            return self::count($table, $conditions) > 0;
        }

        /**
         * Counts how many records match ALL key-value pairs of the $conditions array.
         */
        static public function count(string $table, ?array $conditions = null)
        {
            $where = '';

            if ($conditions != null && count($conditions) > 0)
            {
                $data = self::map_pairs($conditions);
                $where = 'WHERE ' . implode(' AND ', $data['pairs']);
            }

            $res = self::query("SELECT COUNT(*) FROM $table $where;");
            
            return count($res) > 0 && count($res[0]) > 0 ? $res[0][0] : 0;
        }

        /**
         * Selects one or more records that match ALL conditions.
         * Returns null if nothing was selected,
         * a one-dimensional array if $limit is 1 and 
         * otherwise a multidimensional array.
         */
        static public function select(string $table, $fields, ?array $conditions = null, int $limit = 1)
        {
            $where = '';
            $fields = self::map_fields($fields);

            if ($conditions != null && count($conditions) > 0)
            {
                $data = self::map_pairs($conditions);
                $where = 'WHERE ' . implode(' AND ', $data['pairs']);
            }

            if ($limit > 0)
                $limit = "LIMIT $limit";
            else
                $limit = "";

            $query = "SELECT ".implode(',', $fields)." FROM $table $where $limit;";
            $data = self::query($query);

            if ($data == null || $data == '' || count($data ?? []) == 0)
                return [];

            foreach ($data as &$row) 
            {
                foreach ($row as &$col) 
                {
                    if (strtoupper($col) == 'NULL')
                        $col = null;

                    if ($col != null && is_numeric($col))
                        $col = floatval($col);
                }
            }

            return $limit == 1 ? $data[0] : $data;
        }

        /**
         * Deletes records that match the filter.  
         *           
         * The filter must be structured like this:
         * [
         *   'field_1' => [ value, is_string ],
         *   ..
         *   'field_n' => [ value, is_string ]
         * ] 
         *
         * where
         *
         * is_string      determines if the value will be 
         *                wrapped in single quotes
         */
        static public function delete(string $table, array $data, string $db_file = null)
        {
            $where = [];
            foreach ($data as $key => $key_data) 
            {
                $value = array_shift($key_data);
                $string = count($key_data) >= 1 && array_shift($key_data) == true; # this is a string field
                $value = $string ? "'" . $value . "'" : $value;
                $key = '`' . $key . '`';
                $where[] = $key . '=' . $value;
            }
            $where = implode(' AND ', $where);
            self::exec("DELETE FROM $table WHERE $where;", $db_file);
        }

        static public function avg_clicks()
        {
            if (self::$db == null)
                self::$db = new Database();
            $ravg = self::$db->query('SELECT AVG(avg_s) AS avg_s, AVG(avg_m) AS avg_m, AVG(avg_h) AS avg_h, AVG(avg_d) AS avg_d FROM stats_history');
            return @$ravg->fetchArray(SQLITE3_ASSOC) ?? null;
        }

        static public function total_clicks()
        {
            if (self::$db == null)
                self::$db = new Database();
            $rsum = self::$db->query('SELECT SUM(total) AS total, SUM(total_1xx) AS total_1xx, SUM(total_2xx) AS total_2xx, SUM(total_3xx) AS total_3xx, SUM(total_4xx) AS total_4xx, SUM(total_5xx) AS total_5xx FROM stats_history');
            return @$rsum->fetchArray(SQLITE3_ASSOC) ?? null;
        }

        static public function stats(string $fields = '*')
        {
            if (self::$db == null)
                self::$db = new Database();

            return self::$db->get_stats($fields);
        }

        static public function stats_update()
        {
            if (self::$db == null)
                self::$db = new Database();

            self::$db->update_stats();
        }

        static public function results_by_status(int $min = 100, int $max = 599, int $max_results = 100)
        {
            if (self::$db == null)
                self::$db = new Database(); 

            return self::$db->get_results_by_status_code($min, $max, $max_results);
        }

    }
