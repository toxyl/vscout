<?php
	class CommandIO
	{
	    static public function exec($cmd)   
	    { 
	        $f = trim(`mktemp`);
	        passthru('(' . $cmd . ") > $f 2>&1"); 
	        return trim(`cat $f` . `rm -rf $f`);
	    } 
	}
