<?php
class TemplateParser
{
	static private function replace(string &$data, string $placeholder, $value)
	{
		$re = '/{{\s*'.preg_quote($placeholder, '/').'\s*}}/';

		if (is_array($value))
			$value = json_encode($value);

		$data = preg_replace($re, is_bool($value) ? ($value ? 'true' : 'false') : $value, $data);
	}

	static private function process(string $template, ?array $values, string $directory = 'html', string $extension = 'html') 
	{
		try 
		{
			$data = trim(DataFile::read($directory . '/' . preg_replace('/[^a-zA-Z0-9\-_]/', '', $template) . '.' . $extension));
		}
		catch (Exception $e)
		{
			return "Template $template not found!";
		}
		
		if ($values == null || !is_array($values))
			$values = [];


		$re_templates = '/{{@([a-zA-Z0-9\-_]+)}}/';
		while (preg_match($re_templates, $data))
		{
			preg_match_all($re_templates, $data, $templates, PREG_SET_ORDER);
			foreach ($templates as $tpl) 
			{
				$t = $tpl[1];
				try 
				{
					self::replace($data, '@' . $t, trim(DataFile::read($directory . '/' . preg_replace('/[^a-zA-Z0-9\-_]/', '', $t) . '.' . $extension)));
				}
				catch (Exception $e)
				{
					self::replace($data, '@' . $t,  "Template $t not found!");
				}
			}
		}

		$re_vars = '/{{\s*\$([a-zA-Z0-9_]+)\s*}}/';
		while (preg_match($re_vars, $data))
		{
			preg_match_all($re_vars, $data, $vars, PREG_SET_ORDER);
			foreach ($vars as $var) 
			{
				$v = $var[1];
				self::replace($data, '$' . $v, $values[$v] ?? '');
			}
		}

		preg_match_all('/{{\s*([A-Z0-9\_]+)\s*}}/', $data, $constants, PREG_SET_ORDER);
		foreach ($constants as $constant) 
		{
			self::replace($data, $constant[1], defined($constant[1]) ? constant($constant[1]) : '');
		}

		preg_match_all('/{{\s*`(.+?)`\s*}}/', $data, $commands, PREG_SET_ORDER);
		foreach ($commands as $command) 
		{
			self::replace($data, '`'.$command[1].'`', CommandIO::exec($command[1]));
		}

		return $data . "\n";
	}

	/**
	 * First this will replace all templates ({{@templateName}}) found in the template. 
	 * This is done recursively, so templates can contain other templates. Be sure to supply 
	 * values for all variables used within the template and its children. There is no check 
	 * for circular references, design your templates properly! However, with the $timeout 
	 * argument you define the maximum time (in seconds) allowed for rendering the template. 
	 * If the template fails to render within that time an empty string will be returned.
	 * By default the timeout is 10 seconds.
	 *  
	 * Then all variables ({{$varName}}) found in the template will be replaced
	 * with values supplied in the $values argument or an empty string if no value
	 * was supplied. After all variables have been parsed all constants ({{CONSTANT_NAME}}) 
	 * will be replaced with their value if they are currently defined or otherwise an empty string.
	 *
	 * Finally all command expressions ({{`myCLIComand`}}) will be processed, using this
	 * one can inject the output of system commands into templates.
	 * 
	 * If supplied the $values argument has to be an associative array where the key
	 * represents the variable name. If the value is an array it will be embedded 
	 * as JSON encoded string, which is useful to inject data into JavasScripts.
	 * 
	 * Variables contain contain other variables and constants, for example this
	 * would be a valid $values argument:
	 *
	 * [
	 * 	   "varA" => "hello", 
	 * 	   "varB" => "world", 
	 * 	   "varC" => "{{$varA}} {{$varB}}", 
	 * 	   "varD" => "{{$varB}} {{$varA}}", 
	 * 	   "varE" => "(TOR_MODE) ? '{{$varC}}' : '{{$varD}}'" 
	 * ]
	 *
	 * This would replace all occurrences of 
	 * - {{$varA}} with "hello",
	 * - {{$varB}} with "world",
	 * - {{$varC}} with "hello world",
	 * - {{$varD}} with "world hello" and 
	 * - {{$varE}} with "(TOR_MODE) ? 'hello world' : 'world hello'".
	 */
	static public function parse(string $template, ?array $values = null, string $directory = 'html', string $extension = 'html', int $timeout = 10)
	{
		return ProcessControl::timeout($timeout, function($t, $v, $d, $e) { return self::process($t, $v, $d, $e); }, [ $template, $values, $directory, $extension ], function() { return "Timeout while parsing template!\n"; }, null);
	}
}
