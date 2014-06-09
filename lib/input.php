<?php

class Input
{

	public static function get($str, $default = null)
	{
		return isset($_GET[$str]) ? $_GET[$str] : $default;
	}

	public static function post($str, $default = null)
	{
		return isset($_POST[$str]) ? $_POST[$str] : $default;
	}

	public static function param($str, $default = null)
	{
		return isset($_REQUEST[$str]) ? $_REQUEST[$str] : $default;
	}

	public static function method()
	{
		return $_SERVER['REQUEST_METHOD'];
	}

	public static function agent()
	{
		return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
	}

}
