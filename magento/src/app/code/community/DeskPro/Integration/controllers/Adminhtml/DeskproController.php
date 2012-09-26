<?php

class DeskPro_Integration_Adminhtml_DeskproController extends Mage_Adminhtml_Controller_Action
{
	public function infoAction()
	{
		$customerId = (int) $this->getRequest()->getParam('id');
		$customer = Mage::getModel('customer/customer');

		if ($customerId) {
			$customer->load($customerId);
		}

		Mage::register('current_customer', $customer);

		$this->loadLayout();
		$this->renderLayout();
	}
}