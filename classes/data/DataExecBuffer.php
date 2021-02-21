<?php
    class DataExecBuffer
    {
        static private $transaction = null;
        static private $creating = false;
        static private $last_flush = null;

        static public function new()
        {
            if (self::$creating)
                return;

            self::$transaction = [];
            self::$creating = true;
        } 

        static public function flush()
        {
            if (!self::$creating)
                return;

            if (count(self::$transaction) > 0)
                Data::exec("BEGIN EXCLUSIVE;\n" . implode("\n", self::$transaction) . "\nCOMMIT;\n");                

            self::$last_flush = time();

            self::$creating = false;
        } 

        static public function add($sql)
        {
            if (!self::$creating)
                self::new();

            self::$transaction[] = $sql;
            if (time() - self::$last_flush > EXEC_BUFFER_TIME || count(self::$transaction) >= EXEC_BUFFER_SIZE)
                self::flush();
        }
    }
