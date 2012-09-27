<?php

class DeskPro_Integration_Model_Resource_Loginkey extends Mage_Core_Model_Resource_Db_Abstract
{
	protected function _construct()
	{
		$this->_init('deskpro_integration/loginkey', 'loginkey_id');
	}
}