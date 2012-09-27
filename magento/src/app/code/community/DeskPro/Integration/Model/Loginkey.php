<?php

class DeskPro_Integration_Model_Loginkey extends Mage_Core_Model_Abstract
{
	protected function _construct()
	{
		$this->_init('deskpro_integration/loginkey');
	}

	public function stillValid()
	{
		if (!$this->getDateCreated()) {
			return true;
		}

		$date = new DateTime($this->getDateCreated(), new DateTimeZone('GMT'));
		return (time() - $date->format('U') <= 900);
	}
}