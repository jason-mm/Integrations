<?php

class DeskPro_Integration_Block_Adminhtml_Customer_Edit_Tabs extends Mage_Adminhtml_Block_Customer_Edit_Tabs
{
	protected function _beforeToHtml()
	{
		$this->addTab('deskpro', array(
			'label'     => 'DeskPRO',
			'class'     => 'ajax',
			'url'       => $this->getUrl('*/deskpro/info', array('_current' => true)),
		));

		return parent::_beforeToHtml();
	}
}