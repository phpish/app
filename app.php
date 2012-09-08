<?php

	namespace phpish\app;


	register_shutdown_function(function ()
	{
		if (!connection_aborted()) respond();
	});


	function any($path)
	{
		handler('*', $path, array(), array_slice(func_get_args(), 1));
	}

	function head($path)
	{
		handler('HEAD', $path, array(), array_slice(func_get_args(), 1));
	}

	function get($path)
	{
		handler('GET', $path, array(), array_slice(func_get_args(), 1));
	}

	function query($path)
	{
		handler('GET', $path, array('query'=>true), array_slice(func_get_args(), 1));
	}

	function post($path)
	{
		handler('POST', $path, array(), array_slice(func_get_args(), 1));
	}

	function post_action($path, $action)
	{
		handler('POST', $path, array('action'=>$action), array_slice(func_get_args(), 2));
	}

	function put($path)
	{
		handler('PUT', $path, array(), array_slice(func_get_args(), 1));
	}

	function delete($path)
	{
		handler('DELETE', $path, array(), array_slice(func_get_args(), 1));
	}

	function handler($method, $paths, $conds, $funcs)
	{
		if (!is_array($paths)) $paths = array($paths);
		foreach ($paths as $key=>$val) if (!is_int($key)) _named_paths($key, $val);
		foreach($funcs as $func) _handlers(_handler_hash($method, $paths, $conds, $func));
	}

		function _named_paths($name=NULL, $path=NULL, $reset=false)
		{
			static $named_paths = array();
			if ($reset) return $named_paths = array();
			if (!is_null($name) and is_null($path)) return isset($named_paths[$name]) ? $named_paths[$name] : false;
			$named_paths[$name] = $path;
			return $named_paths;
		}

		function _handlers($handler=NULL, $reset=false)
		{
			static $handlers = array();
			if ($reset) return $handlers = array();
			if (is_null($handler)) return $handlers;
			$handlers[] = $handler;
			return $handlers;
		}

		function _handler_hash($method, $paths, $conds, $func)
		{
			return compact('method', 'paths', 'conds', 'func');
		}



	function next()
	{
		$args = func_get_args();
		if (empty($args)) $args = array(request());
		$matches = array();

		$handler = _next_handler_match($args[0], $matches);

		if (!is_null($handler))
		{
			if (is_callable($handler['func']))
			{
				$response = call_user_func_array($handler['func'], array_merge(array($args, $matches)));
			}
			// TODO: else trigger error?

			return $response;
		}

		// TODO return 404 response. That way user can specify handler that intercepts this and return custom 404 response.
	}

		function _next_handler_match($req, &$matches)
		{
			static $handlers; if (!isset($handlers)) $handlers = _handlers();

			while ($handler = array_shift($handlers) and ($matched_handler = _handler_match($handler, $req, $matches)))
			{
				return $matched_handler;
			}
		}

			function _handler_match($handler, $req, &$matches=NULL)
			{
				$method_matched = (($req['method'] === $handler['method']) or ('*' === $handler['method']));

				foreach ($handler['paths'] as $path)
				{
					if ($path_matched = _path_match($path, $req['path'], $matches)) break;
				}

				$action_cond_failed = (isset($handler['conds']['action'])
				                      and  (!isset($req['form']['action'])
										or (strtolower(_underscorize($req['form']['action'])) === $handler['conds']['action'])));

				$query_cond_failed = (isset($handler['conds']['query']) and
				                      is_equal(true, $handler['conds']['query']) and
									  empty($req['query']));

				if ($method_matched and $path_matched and !$action_cond_failed and !$query_cond_failed)
				{
					return	$handler;
				}
			}

				function _underscorize($str)
				{
					$str = preg_replace('/^[^a-zA-Z0-9]+/', '', trim($str));
					return preg_replace('/[^a-zA-Z0-9]/', '_', trim($str));
				}


				function _path_match($path_pattern, $path, &$matches=array())
				{
					$regex_pattern = _path_pattern_to_regex_pattern($path_pattern);

					if (1 === preg_match($regex_pattern, $path, $matches))
					{
						foreach ($matches as $key=>$val) { if (is_int($key)) { unset($matches[$key]); }}
						return true;
					}
					return false;
				}

					//TODO: convert all \{ and \} to \x00<curllystart>, \x00<curllyend>
					function _path_pattern_to_regex_pattern($pattern)
					{
						$pattern = _path_pattern_optional_parts_to_regex($pattern);
						$pattern = _path_pattern_named_parts_to_regex($pattern);
						$pattern = strtr($pattern, array('/' => '\/'));
						return "/^$pattern\$/";
					}

						function _path_pattern_optional_parts_to_regex($pattern)
						{
							$optional_parts_pattern = '/\[([^\]\[]*)\]/';
							$replacement = '(\1)?';

							while (true)
							{
								$regex_pattern = preg_replace($optional_parts_pattern, $replacement, $pattern);
								if ($regex_pattern === $pattern) break;
								$pattern = $regex_pattern;
							}

							return $pattern;
						}

						function _path_pattern_named_parts_to_regex($pattern)
						{
							$named_parts = '/{([^}]*)}/';
							$pattern = preg_replace_callback
							(
								$named_parts,
								function ($matches)
								{
									return _path_pattern_named_part_filters_to_regex($matches, _path_pattern_named_part_filters());
								},
								$pattern
							);
							return $pattern;
						}


								function _path_pattern_named_part_filters_to_regex($matches, $filters)
								{
									if (strpos($matches[1], ':') !== false)
									{
										list($subpattern_name, $pattern) = explode(':', $matches[1], 2);
										$pattern = isset($filters[$pattern]) ? $filters[$pattern] : $pattern;
										return "(?P<$subpattern_name>$pattern)";
									}
									else
									{
										return "(?P<{$matches[1]}>{$filters['segment']})";
									}
								}

								function _path_pattern_named_part_filters()
								{
									return array
									(
										'word'    => '\w+',
										'alpha'   => '[a-zA-Z]+',
										'digits'  => '\d+',
										'number'  => '\d*.?\d+',
										'segment' => '[^/]+',
										'any'     => '.+'
									);
								}




	function respond()
	{
		next(request());
	}


	function path_macro($paths, $func)
	{
		handler_macro('*', $paths, array(), $func);
	}

	function macro($method, $paths, $conds, $func)
	{
		$req = request();
		$handler = _handler_hash($method, $paths, $conds, $func);
		if (_handler_match($handler, $req, $matches))
		{
			if (is_callable($handler['func']))
			{
				call_user_func_array($handler['func'], array_merge(array($req, $matches)));
			}
			// TODO: else trigger error?
		}
	}


	function request($override=array())
	{
		static $request;

		if (!isset($request))
		{
			$body = file_get_contents('php://input');
			$request = array
			(
				'method'=> strtoupper($_SERVER['REQUEST_METHOD']),
				'path'=> rawurldecode('/'.ltrim(_request_path(), '/')),
				'query'=> $_GET,
				'form'=> $_POST,
				'server_vars'=> $_SERVER,
				'headers'=> _request_headers(),
				'body'=> (false === $body) ? NULL : $body
			);
		}

		$request = $override + $request;
		return $request;
	}


		function _request_path()
		{
			$path_to_executing_script = dirname($_SERVER['PHP_SELF']);
			if ((1 === strlen($path_to_executing_script)) and (DIRECTORY_SEPARATOR === $path_to_executing_script))
			{
				$path_to_executing_script = '';
			}

			$path = substr($_SERVER['REQUEST_URI'], strlen($path_to_executing_script));
			list($path, ) = (strpos($path, '?') !== false) ? explode('?', $path, 2) : array($path, '');
			return $path;
		}


		function _request_headers()
		{
			if (function_exists('apache_request_headers')) return apache_request_headers();

			$headers = array();
			foreach ($_SERVER as $key=>$value)
			{
				if (preg_match('/^HTTP_(.*)/', $key, $matches))
				{
					$header = strtolower(strtr($matches[1], '_', '-'));
					$headers[$header] = $value;
				}
			}

			return $headers;
		}

?>