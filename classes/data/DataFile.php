<?php
    class DataFile
    {
    	static public function path(string $file)
    	{
    		return DATA_DIR . '/' . $file;
    	}
    	
        static public function read(string $file)
    	{
            $p = self::path($file);
            
            if (!file_exists($p))
                throw new Exception("File $p does not exist!", 1);
                
    		return file_get_contents(self::path($file));
    	}
    	
        static public function write(string $file, $data)
    	{
    		file_put_contents(self::path($file), $data);
    	}
    }
