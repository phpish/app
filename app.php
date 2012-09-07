<?php

	namespace phpish\app;
	use phpish\request;


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
		if (empty($args)) $args = array(request\msg());
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
					if ($path_matched = path_match($path, $req['path'], $matches)) break;
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



	function respond()
	{
		next_handler(request\msg());
	}


	function path_macro($paths, $func)
	{
		handler_macro('*', $paths, array(), $func);
	}

	function macro($method, $paths, $conds, $func)
	{
		$req = request\msg();
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

?>