<?php

class DeskPro_Integration_Block_Customer_Tab extends Mage_Adminhtml_Block_Template
{
	public function __construct()
	{
		parent::__construct();
		$this->setTemplate('deskpro_integration/customer.phtml');
	}
}