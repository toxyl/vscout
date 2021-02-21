<?php
class TimeoutException extends Exception {};

class ProcessControl
{
	static public function timeout(int $s, callable $f, ?array $fargs, callable $faborted, ?array $fabortedargs = null)
	{
		if (function_exists('pcntl_signal'))
		{
			pcntl_signal(SIGALRM, function($signal) { throw new TimeoutException(); }, true);
			pcntl_alarm($s);
			try 
			{
				$data = $fargs != null ? $f(...$fargs) : $f();
			} 
			catch (TimeoutException $e) 
			{
			    $data = $fabortedargs != null ? $faborted(...$fabortedargs) : $faborted();
			}
			pcntl_alarm(0);

			return $data;			
		}
		else
		{
			return $fargs != null ? $f(...$fargs) : $f();
		}
	}
}
