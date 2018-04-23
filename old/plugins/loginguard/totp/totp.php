<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
use FOF30\Encrypt\Totp;
use Joomla\CMS\Document\HtmlDocument;

defined('_JEXEC') or die;

// Minimum PHP version check
if (!version_compare(PHP_VERSION, '5.4.0', '>='))
{
	return;
}

/**
 * Work around the very broken and completely defunct eAccelerator on PHP 5.4 (or, worse, later versions).
 */
if (function_exists('eaccelerator_info'))
{
	$isBrokenCachingEnabled = true;

	if (function_exists('ini_get') && !ini_get('eaccelerator.enable'))
	{
		$isBrokenCachingEnabled = false;
	}

	if ($isBrokenCachingEnabled)
	{
		/**
		 * I know that this define seems pointless since I am returning. This means that we are exiting the file and
		 * the plugin class isn't defined, so Joomla cannot possibly use it.
		 *
		 * Well, that is how PHP works. Unfortunately, eAccelerator has some "novel" ideas about how to go about it.
		 * For very broken values of "novel". What does it do? It ignores the return and parses the plugin class below.
		 *
		 * You read that right. It ignores ALL THE CODE between here and the class declaration and parses the
		 * class declaration. Therefore the only way to actually NOT load the  plugin when you are using it on a
		 * server where an irresponsible sysadmin has installed and enabled eAccelerator (IT'S END OF LIFE AND BROKEN
		 * PER ITS CREATORS FOR CRYING OUT LOUD) is to define a constant and use it to return from the constructor
		 * method, therefore forcing PHP to return null instead of an object. This prompts Joomla to not do anything
		 * with the plugin.
		 */
		if (!defined('AKEEBA_EACCELERATOR_IS_SO_BORKED_IT_DOES_NOT_EVEN_RETURN'))
		{
			define('AKEEBA_EACCELERATOR_IS_SO_BORKED_IT_DOES_NOT_EVEN_RETURN', 3245);
		}

		return;
	}
}

// Make sure Akeeba LoginGuard is installed
if (!file_exists(JPATH_ADMINISTRATOR . '/components/com_loginguard'))
{
	return;
}

// Load FOF
if (!defined('FOF30_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof30/include.php'))
{
	return;
}

/**
 * Akeeba LoginGuard Plugin for Two Step Verification method "Time-based One Time Password"
 *
 * Requires a 6-digit code generated by Google Authenticator or any compatible application. These codes change
 * automatically every 30 seconds.
 */
class PlgLoginguardTotp extends JPlugin
{
	/**
	 * The TFA method name handled by this plugin
	 *
	 * @var   string
	 */
	private $tfaMethodName = 'totp';

	/**
	 * Constructor. Loads the language files as well.
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 */
	public function __construct($subject, array $config = array())
	{
		/**
		 * Required to work around eAccelerator on PHP 5.4 and later.
		 *
		 * PUBLIC SERVICE ANNOUNCEMENT: eAccelerator IS DEFUNCT AND INCOMPATIBLE WITH PHP 5.4 AND ANY LATER VERSION. If
		 * you have it enabled on your server go ahead and uninstall it NOW. It's officially dead since 2012. Thanks.
		 */
		if (defined('AKEEBA_EACCELERATOR_IS_SO_BORKED_IT_DOES_NOT_EVEN_RETURN'))
		{
			return;
		}

		parent::__construct($subject, $config);

		$this->loadLanguage();
	}

	/**
	 * Gets the identity of this TFA method
	 *
	 * @return  array
	 */
	public function onLoginGuardTfaGetMethod()
	{
		$helpURL = $this->params->get('helpurl', 'https://github.com/akeeba/loginguard/wiki/Authenticator-App');

		return array(
			// Internal code of this TFA method
			'name'          => $this->tfaMethodName,
			// User-facing name for this TFA method
			'display'       => JText::_('PLG_LOGINGUARD_TOTP_LBL_DISPLAYEDAS'),
			// Short description of this TFA method displayed to the user
			'shortinfo'     => JText::_('PLG_LOGINGUARD_TOTP_LBL_SHORTINFO'),
			// URL to the logo image for this method
			'image'         => 'media/plg_loginguard_totp/images/totp.svg',
			// Are we allowed to disable it?
			'canDisable'    => true,
			// Are we allowed to have multiple instances of it per user?
			'allowMultiple' => false,
			// URL for help content
			'help_url' => $helpURL,
		);
	}

	/**
	 * Returns the information which allows LoginGuard to render the captive TFA page. This is the page which appears
	 * right after you log in and asks you to validate your login with TFA.
	 *
	 * @param   stdClass  $record  The #__loginguard_tfa record currently selected by the user.
	 *
	 * @return  array
	 */
	public function onLoginGuardTfaCaptive($record)
	{
		// Make sure we are actually meant to handle this method
		if ($record->method != $this->tfaMethodName)
		{
			return array();
		}

		$helpURL = $this->params->get('helpurl', 'https://github.com/akeeba/loginguard/wiki/Authenticator-App');

		return array(
			// Custom HTML to display above the TFA form
			'pre_message'  => '',
			// How to render the TFA code field. "input" (HTML input element) or "custom" (custom HTML)
			'field_type'   => 'input',
			// The type attribute for the HTML input box. Typically "text" or "password". Use any HTML5 input type.
			'input_type'   => 'text',
			// Placeholder text for the HTML input box. Leave empty if you don't need it.
			'placeholder'  => '',
			// Label to show above the HTML input box. Leave empty if you don't need it.
			'label'        => JText::_('PLG_LOGINGUARD_TOTP_LBL_LABEL'),
			// Custom HTML. Only used when field_type = custom.
			'html'         => '',
			// Custom HTML to display below the TFA form
			'post_message' => '',
			// URL for help content
			'help_url'     => $helpURL,
		);
	}

	/**
	 * Returns the information which allows LoginGuard to render the TFA setup page. This is the page which allows the
	 * user to add or modify a TFA method for their user account. If the record does not correspond to your plugin
	 * return an empty array.
	 *
	 * @param   stdClass  $record  The #__loginguard_tfa record currently selected by the user.
	 *
	 * @return  array
	 */
	public function onLoginGuardTfaGetSetup($record)
	{
		// Make sure we are actually meant to handle this method
		if ($record->method != $this->tfaMethodName)
		{
			return array();
		}

		$totp = new Totp();

		// Load the options from the record (if any)
		$options = $this->_decodeRecordOptions($record);
		$key     = isset($options['key']) ? $options['key'] : '';

		// If there's a key in the session use that instead.
		$session    = JFactory::getSession();
		$sessionKey = $session->get('totp.key', null, 'com_loginguard');

		if (!empty($sessionKey))
		{
			$key = $sessionKey;
		}

		// If there's still no key in the options, generate one and save it in the session
		if (empty($key))
		{
			$key = $totp->generateSecret();
			$session->set('totp.key', $key, 'com_loginguard');
		}

		// Generate a QR code for the key
		$user     = JFactory::getUser($record->user_id);
		$hostname = JUri::getInstance()->toString(array('host'));
		$qr       = sprintf("otpauth://totp/%s@%s?secret=%s", $user->username, $hostname, $key);
		$helpURL  = $this->params->get('helpurl', 'https://github.com/akeeba/loginguard/wiki/Authenticator-App');

		$this->loadJavascript($qr);

		return array(
			// Default title if you are setting up this TFA method for the first time
			'default_title'  => JText::_('PLG_LOGINGUARD_TOTP_LBL_DISPLAYEDAS'),
			// Custom HTML to display above the TFA setup form
			'pre_message'    => JText::_('PLG_LOGINGUARD_TOTP_LBL_SETUP_INSTRUCTIONS'),
			// Heading for displayed tabular data. Typically used to display a list of fixed TFA codes, TOTP setup parameters etc
			'table_heading'  => JText::_('PLG_LOGINGUARD_TOTP_LBL_SETUP_TABLE_HEADING'),
			// Any tabular data to display (label => custom HTML). See above
			'tabular_data'   => array(
				JText::_('PLG_LOGINGUARD_TOTP_LBL_SETUP_TABLE_KEY') => $key,
				JText::_('PLG_LOGINGUARD_TOTP_LBL_SETUP_TABLE_QR')  => "<span id=\"loginGuardQRImage\" />",
			),
			// Hidden fields to include in the form (name => value)
			'hidden_data'    => array(
				'key' => $key,
			),
			// How to render the TFA setup code field. "input" (HTML input element) or "custom" (custom HTML)
			'field_type'     => 'input',
			// The type attribute for the HTML input box. Typically "text" or "password". Use any HTML5 input type.
			'input_type'     => 'text',
			// Pre-filled value for the HTML input box. Typically used for fixed codes, the fixed YubiKey ID etc.
			'input_value'    => '',
			// Placeholder text for the HTML input box. Leave empty if you don't need it.
			'placeholder'    => JText::_('PLG_LOGINGUARD_TOTP_LBL_SETUP_PLACEHOLDER'),
			// Label to show above the HTML input box. Leave empty if you don't need it.
			'label'          => JText::_('PLG_LOGINGUARD_TOTP_LBL_SETUP_LABEL'),
			// Custom HTML. Only used when field_type = custom.
			'html'           => '',
			// Should I show the submit button (apply the TFA setup)? Only applies in the Add page.
			'show_submit'    => true,
			// onclick handler for the submit button (apply the TFA setup)?
			'submit_onclick' => '',
			// Custom HTML to display below the TFA setup form
			'post_message'   => '',
			// URL for help content
			'help_url' => $helpURL,
		);
	}

	/**
	 * Parse the input from the TFA setup page and return the configuration information to be saved to the database. If
	 * the information is invalid throw a RuntimeException to signal the need to display the editor page again. The
	 * message of the exception will be displayed to the user. If the record does not correspond to your plugin return
	 * an empty array.
	 *
	 * @param   stdClass  $record  The #__loginguard_tfa record currently selected by the user.
	 * @param   JInput    $input   The user input you are going to take into account.
	 *
	 * @return  array  The configuration data to save to the database
	 *
	 * @throws  RuntimeException  In case the validation fails
	 */
	public function onLoginGuardTfaSaveSetup($record, JInput $input)
	{
		// Make sure we are actually meant to handle this method
		if ($record->method != $this->tfaMethodName)
		{
			return array();
		}

		$session = JFactory::getSession();

		// Load the options from the record (if any)
		$options    = $this->_decodeRecordOptions($record);
		$optionsKey = isset($options['key']) ? $options['key'] : '';
		$key        = $optionsKey;

		// If there is no key in the options fetch one from the session
		if (empty($key))
		{
			$key = $session->get('totp.key', null, 'com_loginguard');
		}

		// If there is still no key in the options throw an error
		if (empty($key))
		{
			throw new RuntimeException(JText::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		/**
		 * If the code is empty but the key already existed in $options someone is simply changing the title / default
		 * method status. We can allow this and stop checking anything else now.
		 */
		$code = $input->getInt('code');

		if (empty($code) && !empty($optionsKey))
		{
			return $options;
		}

		// In any other case validate the submitted code
		$totp = new Totp();
		$isValid = $totp->checkCode($key, $code);

		if (!$isValid)
		{
			throw new RuntimeException(JText::_('PLG_LOGINGUARD_TOTP_ERR_INVALID_CODE'), 500);
		}

		// The code is valid. Unset the key from the session.
		$session->set('totp.key', null, 'com_loginguard');

		// Return the configuration to be serialized
		return array(
			'key' => $key
		);
	}

	/**
	 * Validates the Two Factor Authentication code submitted by the user in the captive Two Step Verification page. If
	 * the record does not correspond to your plugin return FALSE.
	 *
	 * @param   stdClass  $record  The TFA method's record you're validatng against
	 * @param   JUser     $user    The user record
	 * @param   string    $code    The submitted code
	 *
	 * @return  bool
	 */
	public function onLoginGuardTfaValidate($record, JUser $user, $code)
	{
		// Make sure we are actually meant to handle this method
		if ($record->method != $this->tfaMethodName)
		{
			return false;
		}

		// Double check the TFA method is for the correct user
		if ($user->id != $record->user_id)
		{
			return false;
		}

		// Load the options from the record (if any)
		$options = $this->_decodeRecordOptions($record);
		$key = isset($options['key']) ? $options['key'] : '';

		// If there is no key in the options throw an error
		if (empty($key))
		{
			return false;
		}

		// Check the TFA code for validity
		$totp = new Totp();
		return $totp->checkCode($key, $code);
	}

	/**
	 * Decodes the options from a #__loginguard_tfa record into an options object.
	 *
	 * @param   stdClass  $record
	 *
	 * @return  array
	 */
	private function _decodeRecordOptions($record)
	{
		$options = array(
			'key' => ''
		);

		if (!empty($record->options))
		{
			$recordOptions = $record->options;

			if (is_string($recordOptions))
			{
				$recordOptions = json_decode($recordOptions, true);
			}

			$options = array_merge($options, $recordOptions);
		}

		return $options;
	}

	protected function loadJavascript($QRContent)
	{
		if (defined('AKEEBA_LOGINGUARD_TOTP_JAVASCRIPT_INCLUDED'))
		{
			return;
		}

		define('AKEEBA_LOGINGUARD_TOTP_JAVASCRIPT_INCLUDED', 1);

		JHtml::_('jquery.framework');

		if (version_compare(JVERSION, '3.6.999', 'le'))
		{
			// Load Javascript
			JHtml::_('script', 'plg_loginguard_totp/qrcode.min.js', array(
				'version'     => 'auto',
				'relative'    => true,
				'detectDebug' => true,
			), true, false, false, true);
		}
		// Joomla! 3.7 is broken. We have to use the new method AND MAKE SURE $attribs IS NOT EMPTY BECAUSE JOOMLA IS HORRIBLY BROKEN.
		else
		{
			// Load Javascript
			JHtml::_('script', 'plg_loginguard_totp/qrcode.min.js', array(
				'version'       => 'auto',
				'relative'      => true,
				'detectDebug'   => true,
				'framework'     => true,
				'pathOnly'      => false,
				'detectBrowser' => true,
			), array(
				'defer' => false,
				'async' => false,
			));
		}

		$js        = /** @lang JavaScript */
			<<< JS
;; // Defense against broken scripts
window.jQuery(document).ready(function ($){
    $("#loginGuardQRImage").qrcode({
    	render: 'image',
    	ecLevel: 'Q',
    	size: 300,
    	quiet: 2,
    	text: '$QRContent'
    });
});

JS;

		JFactory::getApplication()->getDocument()->addScriptDeclaration($js);

	}
}