<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

if (version_compare(PHP_VERSION, '5.4.0', 'lt'))
{
	(include_once JPATH_COMPONENT_ADMINISTRATOR . '/View/wrongphp.php') or die('Your PHP version is too old for this component.');

	return;
}

// Why, oh why, are you people using eAccelerator? Seriously, what's wrong with you, people?!
if (function_exists('eaccelerator_info'))
{
	$isBrokenCachingEnabled = true;

	if (function_exists('ini_get') && !ini_get('eaccelerator.enable'))
	{
		$isBrokenCachingEnabled = false;
	}

	if ($isBrokenCachingEnabled)
	{
		(include_once JPATH_COMPONENT_ADMINISTRATOR . '/View/eaccelerator.php') or die('eAccelerator is broken and abandoned since 2012. Ask your host to disable it before using this component.');

		return;
	}
}

// HHVM made sense in 2013, now PHP 7 is a way better solution than an hybrid PHP interpreter
if (defined('HHVM_VERSION'))
{
	(include_once JPATH_COMPONENT_ADMINISTRATOR . '/View/hhvm.php') or die('We have detected that you are running HHVM instead of PHP. This software WILL NOT WORK properly on HHVM. Please switch to PHP 7 instead.');

	return;
}


/**
 * The following code is a neat trick to help us collect the maximum amount of relevant information when a user
 * encounters an unexpected exception (PHP 5.4+) or a PHP fatal error (PHP 7+). In both cases we capture the generated
 * exception and render an error page, making sure that the HTTP response code is set to an appropriate value (4xx or
 * 5xx).
 *
 * Why the two functions? In PHP 5 the base exception class is Exception. In PHP 7 there is a base interface called
 * Throwable which the two base classes Exception (user-defined exception) and Error (PHP fatal error) implement.
 * However, Throwable does not exist in PHP 5 so we can't have a try-catch expecting Throwable. At the same time, in
 * PHP 7 neither catching Exception will handle PHP fatal errors nor can you manually implement Throwable to create a
 * base class for use in try-catch. Therefore the only solution is to have two functions for the try and catch part,
 * a conditional for the PHP version and a slightly different catch block in each case.
 *
 * Now you know what we did and why we did it. Feel free to include this idea in your GPL projects :)
 */

function mainLoopAkeebaLoginGuard()
{
	if (!defined('FOF30_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof30/include.php'))
	{
		throw new RuntimeException('FOF 3.0 is not installed', 500);
	}

	FOF30\Container\Container::getInstance('com_loginguard')->dispatcher->dispatch();
};

function errorHandlerAkeebaLoginGuard($e)
{
	$title = 'Akeeba LoginGuard';
	$isPro = false;

	if (!(include_once __DIR__ . '/View/errorhandler.php'))
	{
		throw $e;
	}
}

if (version_compare(PHP_VERSION, '7.0.0', 'lt'))
{
	// PHP 5.4, 5.5 and 5.6. Only user exceptions can be caught.
	try
	{
		mainLoopAkeebaLoginGuard();
	}
	catch (Exception $e)
	{
		errorHandlerAkeebaLoginGuard($e);
	}
}
else
{
	// PHP 7.0 or later; we can catch PHP Fatal Errors as well
	try
	{
		mainLoopAkeebaLoginGuard();
	}
	catch (Throwable $e)
	{
		errorHandlerAkeebaLoginGuard($e);
	}
}
