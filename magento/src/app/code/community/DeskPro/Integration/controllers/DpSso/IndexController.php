<?php

class DeskPro_Integration_DpSso_IndexController extends Mage_Core_Controller_Front_Action
{
	public function indexAction()
	{
		/** @var $customer Mage_Customer_Model_Customer */
		$customer = Mage::getSingleton('customer/session')->getCustomer();
		$customerId = intval($customer->getId());

		if ($customerId) {
			$loginKey = Mage::getModel('deskpro_integration/loginkey')->load($customerId, 'customer_id');

			if (!$loginKey->getId()) {
				$loginKey->setCustomerId($customerId);
			}
			if (!$loginKey->getLoginkey() || !$loginKey->stillValid()) {
				$loginKey->setLoginkey(md5(uniqid(microtime(), true)));
				$loginKey->save();
			}

			$id = $loginKey->getId();
			$key = $loginKey->getLoginkey();

			$code = <<<HEREDOC
(function() {
	var cookies = {};

	var parts = document.cookie.split(';'), pair;
	for (var i = 0; i < parts.length; i++) {
		pair = parts[i].split('=');
		cookies[unescape(pair[0])] = unescape(pair[1]);
	}

	var isMagentoDpLogin = cookies.dpmagento;

	if (window.dpMagentoLogin) {
		var login = function(url) {
			document.cookie = "dpmagento=1;path=/";
			cookies.dpmagento = 1;
			url += (url.indexOf('?') == -1 ? '?' : '&') + 'id=$id&key=$key';
			window.location = url;
		};
		window.dpMagentoLogin(login);
	}
})();
HEREDOC;
		} else {
			$code = '';
		}

		$response = $this->getResponse();

		$cache_expire = 0;

		$response->setHeader('Content-type', 'text/javascript');
		$response->setHeader('Cache-control', 'max-age=' . $cache_expire);
		$response->setHeader('Expires', gmdate('D, d M Y H:i:s', time()+$cache_expire) . ' GMT');
		$response->setBody($code);
	}
}