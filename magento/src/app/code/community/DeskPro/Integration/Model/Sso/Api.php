<?php

class DeskPro_Integration_Model_Sso_Api
{
	public function validate(array $params = array())
	{
		$id = isset($params['id']) ? intval($params['id']) : false;
		$key = isset($params['key']) ? $params['key'] : false;

		if ($id && $key) {
			$loginKey = Mage::getModel('deskpro_integration/loginkey')->load($id);
			if ($loginKey->getLoginkey() && $loginKey->getLoginkey() === $key && $loginKey->stillValid()) {
				$customerId = $loginKey->getCustomerId();
				$loginKey->delete();

				/** @var $model Mage_Customer_Model_Customer */
				$customer = Mage::getModel('customer/customer')->setWebsiteId(Mage::app()->getStore()->getWebsiteId());
				$customer->load($customerId);

				if ($customer->getId()) {
					$api = new Mage_Customer_Model_Customer_Api();
					return $api->info($customerId);
				}
			}
		}

		return null;
	}
}