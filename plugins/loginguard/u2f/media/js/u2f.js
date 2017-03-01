/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Namespace
var akeeba = akeeba || {};

akeeba.LoginGuard = akeeba.LoginGuard || {};

akeeba.LoginGuard.u2f = akeeba.LoginGuard.u2f || {
	regData: null,
	authData: null,
	handledError: false
};

/**
 * Register a new U2F security key.
 */
akeeba.LoginGuard.u2f.setUp = function ()
{
	var u2fRequest       = akeeba.LoginGuard.u2f.regData[0];
	var u2fAuthorization = akeeba.LoginGuard.u2f.regData[1];

	u2f.register([u2fRequest], u2fAuthorization, function (data)
	{
		try
		{
			console.debug(data);
		}
		catch (e)
		{
		}

		if ((data.errorCode === undefined) || (data.errorCode === 0))
		{
			// Store the U2F reply
			document.getElementById('loginguard-method-code').value = JSON.stringify(data);

			// Submit the form
			document.forms['loginguard-method-edit'].submit();

			return;
		}

		/**
		 * Firefox sends two responses with error codes 4 and 1 when the device is already registered. Using this trick
		 * we only display the relevant error message (4), discarding the secondary generic error.
		 */
		if (akeeba.LoginGuard.u2f.handledError)
		{
			return;
		}

		akeeba.LoginGuard.u2f.handledError = true;

		switch (data.errorCode)
		{
			case 1:
			default:
				alert(Joomla.JText._('PLG_LOGINGUARD_U2F_ERR_JS_OTHER'));
				break;

			case 2:
				alert(Joomla.JText._('PLG_LOGINGUARD_U2F_ERR_JS_CANNOTPROCESS'));
				break;

			case 3:
				alert(Joomla.JText._('PLG_LOGINGUARD_U2F_ERR_JS_CLIENTCONFIGNOTSUPPORTED'));
				break;

			case 4:
				alert(Joomla.JText._('PLG_LOGINGUARD_U2F_ERR_JS_INELIGIBLE'));
				break;

			case 5:
				alert(Joomla.JText._('PLG_LOGINGUARD_U2F_ERR_JS_TIMEOUT'));
				break;
		}

		akeeba.LoginGuard.u2f.handledError = false;
	});
};

/**
 * Ask the U2F key to sign a challenge (authenticate)
 */
akeeba.LoginGuard.u2f.validate = function ()
{
	u2f.sign(akeeba.LoginGuard.u2f.authData, function (response)
	{
		document.getElementById('loginGuardCode').value = JSON.stringify(response);
		document.forms['loginguard-captive-form'].submit();
	})
};