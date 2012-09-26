<?php

class DeskPro_Integration_Model_Deskpro
{
	protected $_api;

	public function getApi()
	{
		if (!$this->_api) {
			$url = Mage::getStoreConfig('deskpro_integration_options/api/root_url');
			$key = Mage::getStoreConfig('deskpro_integration_options/api/api_key');

			if (!class_exists('DpApi')) {
				require(dirname(__FILE__) . '/../lib/DpApi.php');
			}
			$this->_api = new DpApi($url, $key);
		}

		return $this->_api;
	}

	public function getApiErrors()
	{
		return $this->getApi()->getLastErrors();
	}

	public function getDeskProUrl()
	{
		return $this->getApi()->getRoot();
	}

	public function getPeople($email)
	{
		$people = $this->getApi()->findPeople(array('email' => $email));
		return (!empty($people['people']) ? $people['people'] : array());
	}

	public function getRecentTickets($personId)
	{
		$results = $this->getApi()->findTickets(array('person_id' => $personId), 1, 'ticket.date_created:desc');
		return (!empty($results['tickets'])) ? array_slice($results['tickets'], 0, 10, true) : array();
	}
}