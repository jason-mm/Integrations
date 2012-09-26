<?php

/**
 * This is a class to make accessing the REST API exposed by DeskPRO easier.
 * It wraps various API calls in a simple to use PHP API.
 *
 * To use, simply create the object with the URL to your DeskPRO root and API key:
 *  $api = new DpApi('http://example.com/deskpro', '1:APIKEYHERE');
 *
 * Then call whatever API method you want:
 *  $results = $api->findTickets();
 *
 * API methods will return false on failure. getLastErrors() can be called to get
 * more specific error messages.
 *
 * For more information on the return values and available parameters in the DeskPRO
 * API, see here: https://support.deskpro.com/kb/17-deskpro-api
 *
 * @version 0.1.0
 */
class DpApi
{
	/**
	 * URL to DeskPRO root (eg, http://example.com/deskpro)
	 *
	 * @var string
	 */
	protected $_root;

	/**
	 * API key (id:secret format)
	 *
	 * @var string
	 */
	protected $_api_key;

	/**
	 * List of errors from last API call. False if no errors occurred.
	 *
	 * @var bool|array
	 */
	protected $_errors = false;

	/**
	 * Raw results object from the last API call, or null if there were no previous calls.
	 *
	 * @var DpApiResult|null
	 */
	protected $_last;

	/**
	 * @param string $dp_root
	 * @param string $api_key
	 */
	public function __construct($dp_root, $api_key)
	{
		$this->setRoot($dp_root);
		$this->setApiKey($api_key);
	}

	/**
	 * @param string $root
	 */
	public function setRoot($root)
	{
		if (substr($root, -1) == '/') {
			$root = substr($root, 0, -1);
		}

		$this->_root = $root;
	}

	/**
	 * @return string
	 */
	public function getRoot()
	{
		return $this->_root;
	}

	/**
	 * @param string $api_key
	 */
	public function setApiKey($api_key)
	{
		$this->_api_key = $api_key;
	}

	/**
	 * Calls an API method
	 *
	 * @param string $method Request method (GET, POST, PUT, DELETE)
	 * @param string $end URL to the end point (eg, /tickets), relative to DP root
	 * @param array $params List of parameters to pass to method
	 *
	 * @return DpApiResult
	 */
	public function call($method, $end, array $params = array())
	{
		if (substr($end, 0, 1) == '/') {
			$end = substr($end, 1);
		}
		if (substr($end, -1) == '/') {
			$end = substr($end, 0, -1);
		}

		$url = $this->_root . '/api/' . $end;

		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			'X-DeskPRO-API-Key: ' . $this->_api_key
		));

		switch (strtoupper($method)) {
			case 'POST':
			case 'PUT':
			case 'DELETE':
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
				curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
				break;

			case 'GET':
				curl_setopt($curl, CURLOPT_HTTPGET, true);
				$url .= '?' . http_build_query($params);
				curl_setopt($curl, CURLOPT_URL, $url);
		}

		$response = curl_exec($curl);
		curl_close($curl);

		$header_end = strpos($response, "\r\n\r\n");
		if ($header_end === false) {
			$headers = $response;
			$body = '';
		} else {
			$headers = substr($response, 0, $header_end);
			$body = substr($response, $header_end + 4);
		}

		$results = new DpApiResult($headers, $body);

		$this->_errors = false;
		$this->_last = $results;

		return $results;
	}

	/**
	 * @return DpApiResult|null
	 */
	public function getLastResults()
	{
		return $this->_last;
	}

	/**
	 * @return array|bool
	 */
	public function getLastErrors()
	{
		return $this->_errors;
	}

	/**
	 * Gets the response from a results object, if it did not error.
	 * Throws exceptions if authentication data is incorrect or the API did not return JSON
	 * (usually an indication of an incorrect URL).
	 *
	 * @param DpApiResult $results
	 *
	 * @return array|bool False on error
	 *
	 * @throws DpApiResponseException
	 * @throws DpApiAuthException
	 */
	protected function _getResponse(DpApiResult $results)
	{
		if ($results->getResponseCode() == 401) {
			throw new DpApiAuthException('Invalid API authentication');
		}

		$json = $results->getJson();

		if ($json === false) {
			throw new DpApiResponseException('API did not return valid JSON');
		}

		if (!empty($json['error_code'])) {
			if ($json['error_code'] == 'multiple') {
				$this->_errors = $json['errors'];
			} else {
				$this->_errors = array(array($json['error_code'], $json['error_message']));
			}

			return false;
		}

		return $json;
	}

	/**
	 * Gets the result when expecting a success response.
	 *
	 * @param DpApiResult $results
	 *
	 * @return bool
	 */
	protected function _getSuccessResponse(DpApiResult $results)
	{
		$json = $this->_getResponse($results);
		return ($json && !empty($json['success']));
	}

	/**
	 * Gets the result when expecting an exists response
	 *
	 * @param DpApiResult $results
	 *
	 * @return bool
	 */
	protected function _getExistsResponse(DpApiResult $results)
	{
		$json = $this->_getResponse($results);
		return ($json && !empty($json['exists']));
	}

	// ################### ORGANIZATION ACTIONS ####################

	/**
	 * Finds organizations matching the criteria
	 *
	 * @param array $criteria
	 * @param integer $page Page number of results to retrieve - deafults to 1
	 * @param null|string $order Order of results (if not specified, uses API default)
	 * @param null|integer $cache Number of seconds to cache (if not specified, uses API default)
	 *
	 * @return array|bool
	 */
	public function findOrganizations(array $criteria, $page = 1, $order = null, $cache = null)
	{
		$criteria['page'] = $page;
		if ($order !== null) {
			$criteria['order'] = $order;
		}
		if ($cache !== null) {
			$criteria['cache'] = $cache;
		}

		$results = $this->call('GET', '/organizations', $criteria);
		return $this->_getResponse($results);
	}

	/**
	 * Creates an organization with the given data.
	 *
	 * @param array $info
	 *
	 * @return array|bool
	 */
	public function createOrganization(array $info)
	{
		$results = $this->call('POST', '/organizations', $info);
		return $this->_getResponse($results);
	}

	/**
	 * Gets information about the given organization.
	 *
	 * @param integer $id
	 *
	 * @return array|bool
	 */
	public function getOrganization($id)
	{
		$results = $this->call('GET', '/organizations/' . intval($id));
		return $this->_getResponse($results);
	}

	/**
	 * Updates information about the given organization.
	 *
	 * @param integer $id
	 * @param array $info
	 *
	 * @return bool
	 */
	public function updateOrganization($id, array $info)
	{
		$results = $this->call('POST', '/organizations/' . intval($id), $info);
		return $this->_getSuccessResponse($results);
	}

	/**
	 * Deletes the given organization.
	 *
	 * @param integer $id
	 *
	 * @return bool
	 */
	public function deleteOrganization($id)
	{
		$results = $this->call('DELETE', '/organizations/' . intval($id));
		return $this->_getSuccessResponse($results);
	}

	/**
	 * Gets members of an organization.
	 *
	 * @param integer $id
	 * @param integer $page Page number of results to retrieve - deafults to 1
	 * @param null|string $order Order of results (if not specified, uses API default)
	 * @param null|integer $cache Number of seconds to cache (if not specified, uses API default)
	 *
	 * @return array|bool
	 */
	public function getOrganizationMembers($id, $page = 1, $order = null, $cache = null)
	{
		$params = array();
		$params['page'] = $page;
		if ($order !== null) {
			$params['order'] = $order;
		}
		if ($cache !== null) {
			$params['cache'] = $cache;
		}

		$results = $this->call('GET', '/organizations/' . intval($id) . '/tickets', $params);
		return $this->_getResponse($results);
	}

	/**
	 * Gets tickets for an organization.
	 *
	 * @param integer $id
	 * @param integer $page Page number of results to retrieve - deafults to 1
	 * @param null|string $order Order of results (if not specified, uses API default)
	 * @param null|integer $cache Number of seconds to cache (if not specified, uses API default)
	 *
	 * @return array|bool
	 */
	public function getOrganizationTickets($id, $page = 1, $order = null, $cache = null)
	{
		$params = array();
		$params['page'] = $page;
		if ($order !== null) {
			$params['order'] = $order;
		}
		if ($cache !== null) {
			$params['cache'] = $cache;
		}

		$results = $this->call('GET', '/organizations/' . intval($id) . '/tickets', $params);
		return $this->_getResponse($results);
	}

	/**
	 * Gets all contact details for an organization.
	 *
	 * @param integer $id
	 *
	 * @return array|bool
	 */
	public function getOrganizationContactDetails($id)
	{
		$results = $this->call('GET', '/organizations/' . intval($id) . '/contact-details');
		return $this->_getResponse($results);
	}

	/**
	 * Determines if a particular contact ID exists for an organization.
	 *
	 * @param integer $organization_id
	 * @param integer $contact_id
	 *
	 * @return bool
	 */
	public function getOrganizationContactDetail($organization_id, $contact_id)
	{
		$results = $this->call('GET', '/organizations/' . intval($organization_id) . '/contact-details/' . intval($contact_id));
		return $this->_getExistsResponse($results);
	}

	/**
	 * Removes a particular contact detail from an organization.
	 *
	 * @param integer $organization_id
	 * @param integer $contact_id
	 *
	 * @return bool
	 */
	public function removeOrganizationContactDetail($organization_id, $contact_id)
	{
		$results = $this->call('DELETE', '/organizations/' . intval($organization_id) . '/contact-details/' . intval($contact_id));
		return $this->_getSuccessResponse($results);
	}

	/**
	 * Gets the list of groups that the organization belongs to.
	 *
	 * @param integer $id
	 *
	 * @return array|bool
	 */
	public function getOrganizationGroups($id)
	{
		$results = $this->call('GET', '/organizations/' . intval($id) . '/groups');
		return $this->_getResponse($results);
	}

	/**
	 * Adds an organization to a group
	 *
	 * @param integer $organization_id
	 * @param integer $group_id
	 *
	 * @return array|bool
	 */
	public function addOrganizationGroup($organization_id, $group_id)
	{
		$results = $this->call('POST', '/organizations/' . intval($organization_id) . '/groups', array('id' => $group_id));
		return $this->_getResponse($results);
	}

	/**
	 * Determines if the an organization is a member of a particular group.
	 *
	 * @param integer $organization_id
	 * @param integer $group_id
	 *
	 * @return bool
	 */
	public function getOrganizationGroup($organization_id, $group_id)
	{
		$results = $this->call('GET', '/organizations/' . intval($organization_id) . '/groups/' . intval($group_id));
		return $this->_getExistsResponse($results);
	}

	/**
	 * Removes an organization from a group.
	 *
	 * @param integer $organization_id
	 * @param integer $group_id
	 *
	 * @return bool
	 */
	public function removeOrganizationGroup($organization_id, $group_id)
	{
		$results = $this->call('DELETE', '/organizations/' . intval($organization_id) . '/groups/' . intval($group_id));
		return $this->_getSuccessResponse($results);
	}

	/**
	 * Gets all labels associated with an organization.
	 *
	 * @param integer $id
	 *
	 * @return array|bool
	 */
	public function getOrganizationLabels($id)
	{
		$results = $this->call('GET', '/organizations/' . intval($id) . '/labels');
		return $this->_getResponse($results);
	}

	/**
	 * Adds a label to an organization.
	 *
	 * @param integer $id
	 * @param string $label
	 *
	 * @return array|bool
	 */
	public function addOrganizationLabel($id, $label)
	{
		$results = $this->call('POST', '/organizations/' . intval($id) . '/labels', array('label' => $label));
		return $this->_getResponse($results);
	}

	/**
	 * Determines if an organization has a specific label.
	 *
	 * @param integer $id
	 * @param string $label
	 *
	 * @return bool
	 */
	public function getOrganizationLabel($id, $label)
	{
		$results = $this->call('GET', '/organizations/' . intval($id) . '/labels/' . $label);
		return $this->_getExistsResponse($results);
	}

	/**
	 * Removes a label from an organization.
	 *
	 * @param integer $id
	 * @param string $label
	 *
	 * @return bool
	 */
	public function removeOrganizationLabel($id, $label)
	{
		$results = $this->call('DELETE', '/organizations/' . intval($id) . '/label/' . $label);
		return $this->_getSuccessResponse($results);
	}

	/**
	 * Gets a list of custom organizations fields.
	 *
	 * @return array|bool
	 */
	public function getOrganizationsFields()
	{
		$results = $this->call('GET', '/organizations/fields');
		return $this->_getResponse($results);
	}

	/**
	 * Gets a list of available user groups.
	 *
	 * @return array|bool
	 */
	public function getOrganizationsGroups()
	{
		$results = $this->call('GET', '/organizations/groups');
		return $this->_getResponse($results);
	}

	// ################### PEOPLE ACTIONS ####################

	/**
	 * Finds people matching the criteria
	 *
	 * @param array $criteria
	 * @param integer $page Page number of results to retrieve - deafults to 1
	 * @param null|string $order Order of results (if not specified, uses API default)
	 * @param null|integer $cache Number of seconds to cache (if not specified, uses API default)
	 *
	 * @return array|bool
	 */
	public function findPeople(array $criteria, $page = 1, $order = null, $cache = null)
	{
		$criteria['page'] = $page;
		if ($order !== null) {
			$criteria['order'] = $order;
		}
		if ($cache !== null) {
			$criteria['cache'] = $cache;
		}

		$results = $this->call('GET', '/people', $criteria);
		return $this->_getResponse($results);
	}

	/**
	 * Creates a person with the given data.
	 *
	 * @param array $info
	 *
	 * @return array|bool
	 */
	public function createPerson(array $info)
	{
		$results = $this->call('POST', '/people', $info);
		return $this->_getResponse($results);
	}

	/**
	 * Gets information about the given person.
	 *
	 * @param integer $id
	 *
	 * @return array|bool
	 */
	public function getPerson($id)
	{
		$results = $this->call('GET', '/people/' . intval($id));
		return $this->_getResponse($results);
	}

	/**
	 * Updates information about the given person.
	 *
	 * @param integer $id
	 * @param array $info
	 *
	 * @return bool
	 */
	public function updatePerson($id, array $info)
	{
		$results = $this->call('POST', '/people/' . intval($id), $info);
		return $this->_getSuccessResponse($results);
	}

	/**
	 * Deletes the given person.
	 *
	 * @param integer $id
	 *
	 * @return bool
	 */
	public function deletePerson($id)
	{
		$results = $this->call('DELETE', '/people/' . intval($id));
		return $this->_getSuccessResponse($results);
	}

	/**
	 * Resets a person's password.
	 *
	 * @param integer $id
	 * @param string $password
	 * @param bool $send_email
	 *
	 * @return bool
	 */
	public function resetPersonPassword($id, $password, $send_email = true)
	{
		$params = array(
			'password' => $password,
			'send_email' => $send_email ? 1 : 0
		);

		$results = $this->call('POST', '/people/' . intval($id) . '/reset-password', $params);
		return $this->_getSuccessResponse($results);
	}

	/**
	 * Gets tickets for a person.
	 *
	 * @param integer $id
	 * @param integer $page Page number of results to retrieve - deafults to 1
	 * @param null|string $order Order of results (if not specified, uses API default)
	 * @param null|integer $cache Number of seconds to cache (if not specified, uses API default)
	 *
	 * @return array|bool
	 */
	public function getPersonTickets($id, $page = 1, $order = null, $cache = null)
	{
		$params = array();
		$params['page'] = $page;
		if ($order !== null) {
			$params['order'] = $order;
		}
		if ($cache !== null) {
			$params['cache'] = $cache;
		}

		$results = $this->call('GET', '/people/' . intval($id) . '/tickets', $params);
		return $this->_getResponse($results);
	}

	/**
	 * Gets notes for a person.
	 *
	 * @param integer $id
	 *
	 * @return array|bool
	 */
	public function getPersonNotes($id)
	{
		$results = $this->call('GET', '/people/' . intval($id) . '/notes');
		return $this->_getResponse($results);
	}

	/**
	 * Creates a note for a person.
	 *
	 * @param integer $id
	 * @param string $note
	 *
	 * @return array|bool
	 */
	public function createPersonNote($id, $note)
	{
		$results = $this->call('POST', '/people/' . intval($id) . '/notes', array('note' => $note));
		return $this->_getResponse($results);
	}

	/**
	 * Gets all contact details for a person.
	 *
	 * @param integer $id
	 *
	 * @return array|bool
	 */
	public function getPersonContactDetails($id)
	{
		$results = $this->call('GET', '/people/' . intval($id) . '/contact-details');
		return $this->_getResponse($results);
	}

	/**
	 * Determines if a particular contact ID exists for a person.
	 *
	 * @param integer $person_id
	 * @param integer $contact_id
	 *
	 * @return bool
	 */
	public function getPersonContactDetail($person_id, $contact_id)
	{
		$results = $this->call('GET', '/people/' . intval($person_id) . '/contact-details/' . intval($contact_id));
		return $this->_getExistsResponse($results);
	}

	/**
	 * Removes a particular contact detail from a person.
	 *
	 * @param integer $person_id
	 * @param integer $contact_id
	 *
	 * @return bool
	 */
	public function removePersonContactDetail($person_id, $contact_id)
	{
		$results = $this->call('DELETE', '/people/' . intval($person_id) . '/contact-details/' . intval($contact_id));
		return $this->_getSuccessResponse($results);
	}

	/**
	 * Gets the list of groups that the person belongs to.
	 *
	 * @param integer $id
	 *
	 * @return array|bool
	 */
	public function getPersonGroups($id)
	{
		$results = $this->call('GET', '/people/' . intval($id) . '/groups');
		return $this->_getResponse($results);
	}

	/**
	 * Adds a person to a group
	 *
	 * @param integer $person_id
	 * @param integer $group_id
	 *
	 * @return array|bool
	 */
	public function addPersonGroup($person_id, $group_id)
	{
		$results = $this->call('POST', '/people/' . intval($person_id) . '/groups', array('id' => $group_id));
		return $this->_getResponse($results);
	}

	/**
	 * Determines if the a person is a member of a particular group.
	 *
	 * @param integer $person_id
	 * @param integer $group_id
	 *
	 * @return bool
	 */
	public function getPersonGroup($person_id, $group_id)
	{
		$results = $this->call('GET', '/people/' . intval($person_id) . '/groups/' . intval($group_id));
		return $this->_getExistsResponse($results);
	}

	/**
	 * Removes a person from a group.
	 *
	 * @param integer $person_id
	 * @param integer $group_id
	 *
	 * @return bool
	 */
	public function removePersonGroup($person_id, $group_id)
	{
		$results = $this->call('DELETE', '/people/' . intval($person_id) . '/groups/' . intval($group_id));
		return $this->_getSuccessResponse($results);
	}

	/**
	 * Gets all labels associated with a person.
	 *
	 * @param integer $id
	 *
	 * @return array|bool
	 */
	public function getPersonLabels($id)
	{
		$results = $this->call('GET', '/people/' . intval($id) . '/labels');
		return $this->_getResponse($results);
	}

	/**
	 * Adds a label to a person.
	 *
	 * @param integer $id
	 * @param string $label
	 *
	 * @return array|bool
	 */
	public function addPersonLabel($id, $label)
	{
		$results = $this->call('POST', '/people/' . intval($id) . '/labels', array('label' => $label));
		return $this->_getResponse($results);
	}

	/**
	 * Determines if a person has a specific label.
	 *
	 * @param integer $id
	 * @param string $label
	 *
	 * @return bool
	 */
	public function getPersonLabel($id, $label)
	{
		$results = $this->call('GET', '/people/' . intval($id) . '/labels/' . $label);
		return $this->_getExistsResponse($results);
	}

	/**
	 * Removes a label from a person.
	 *
	 * @param integer $id
	 * @param string $label
	 *
	 * @return bool
	 */
	public function removePersonLabel($id, $label)
	{
		$results = $this->call('DELETE', '/people/' . intval($id) . '/label/' . $label);
		return $this->_getSuccessResponse($results);
	}

	/**
	 * Gets a list of custom people fields.
	 *
	 * @return array|bool
	 */
	public function getPeopleFields()
	{
		$results = $this->call('GET', '/people/fields');
		return $this->_getResponse($results);
	}

	/**
	 * Gets a list of available user groups.
	 *
	 * @return array|bool
	 */
	public function getPeopleGroups()
	{
		$results = $this->call('GET', '/people/groups');
		return $this->_getResponse($results);
	}

	// ################### TICKET ACTIONS ####################

	/**
	 * Finds tickets matching the criteria
	 *
	 * @param array $criteria
	 * @param int $page Page number of results to retrieve - deafults to 1
	 * @param null|string $order Order of results (if not specified, uses API default)
	 * @param null|integer $cache Number of seconds to cache (if not specified, uses API default)
	 *
	 * @return array|bool
	 */
	public function findTickets(array $criteria, $page = 1, $order = null, $cache = null)
	{
		$criteria['page'] = $page;
		if ($order !== null) {
			$criteria['order'] = $order;
		}
		if ($cache !== null) {
			$criteria['cache'] = $cache;
		}

		$results = $this->call('GET', '/tickets', $criteria);
		return $this->_getResponse($results);
	}

	/**
	 * Creates a ticket
	 *
	 * @param array $info
	 *
	 * @return array|bool
	 */
	public function createTicket(array $info)
	{
		$results = $this->call('POST', '/tickets', $info);
		return $this->_getResponse($results);
	}

	/**
	 * Gets information about a ticket
	 *
	 * @param integer $id
	 *
	 * @return array|bool
	 */
	public function getTicket($id)
	{
		$results = $this->call('GET', '/tickets/' . intval($id));
		return $this->_getResponse($results);
	}

	/**
	 * Updates information for a ticket
	 *
	 * @param integer $id
	 * @param array $info
	 *
	 * @return bool
	 */
	public function updateTicket($id, array $info)
	{
		$results = $this->call('POST', '/tickets/' . intval($id), $info);
		return $this->_getSuccessResponse($results);
	}

	/**
	 * Deletes a ticket
	 *
	 * @param integer $id
	 * @param bool $ban If true, bans the emails used by the person creating the ticket
	 *
	 * @return bool
	 */
	public function deleteTicket($id, $ban = false)
	{
		$results = $this->call('DELETE', '/tickets/' . intval($id), array('ban' => ($ban ? 1 : 0)));
		return $this->_getSuccessResponse($results);
	}

	/**
	 * Undeletes a ticket
	 *
	 * @param integer $id
	 *
	 * @return bool
	 */
	public function undeleteTicket($id)
	{
		$results = $this->call('POST', '/tickets/' . intval($id) . '/undelete');
		return $this->_getSuccessResponse($results);
	}

	/**
	 * Marks a ticket as spam.
	 *
	 * @param integer $id
	 * @param bool $ban
	 *
	 * @return bool
	 */
	public function markTicketAsSpam($id, $ban = false)
	{
		$results = $this->call('POST', '/tickets/' . intval($id) . '/spam', array('ban' => ($ban ? 1 : 0)));
		return $this->_getSuccessResponse($results);
	}

	/**
	 * Unmarks a ticket as spam.
	 *
	 * @param integer $id
	 *
	 * @return bool
	 */
	public function unmarkTicketAsSpam($id)
	{
		$results = $this->call('POST', '/tickets/' . intval($id) . '/unspam');
		return $this->_getSuccessResponse($results);
	}

	/**
	 * Assigns a ticket to the user the API key is associated with.
	 *
	 * @param integer $id
	 *
	 * @return bool
	 */
	public function claimTicket($id)
	{
		$results = $this->call('POST', '/tickets/' . intval($id) . '/claim');
		return $this->_getSuccessResponse($results);
	}

	/**
	 * Locks a ticket.
	 *
	 * @param integer $id
	 *
	 * @return bool
	 */
	public function lockTicket($id)
	{
		$results = $this->call('POST', '/tickets/' . intval($id) . '/lock');
		return $this->_getSuccessResponse($results);
	}

	/**
	 * Unlocks a ticket.
	 *
	 * @param integer $id
	 *
	 * @return bool
	 */
	public function unlockTicket($id)
	{
		$results = $this->call('POST', '/tickets/' . intval($id) . '/unlock');
		return $this->_getSuccessResponse($results);
	}

	/**
	 * Merges two tickets.
	 *
	 * @param integer $target The ticket that the other will be merged into
	 * @param integer $from This ticket will be removed on a successful merge
	 *
	 * @return bool
	 */
	public function mergeTickets($target, $from)
	{
		$results = $this->call('POST', '/tickets/' . intval($target) . '/merge/' . intval($from));
		return $this->_getSuccessResponse($results);
	}

	/**
	 * Gets all messages in a ticket.
	 *
	 * @param integer $ticket_id
	 *
	 * @return array|bool
	 */
	public function getTicketMessages($ticket_id)
	{
		$results = $this->call('GET', '/tickets/' . intval($ticket_id) . '/messages');
		return $this->_getResponse($results);
	}

	/**
	 * Creates a new ticket message in a ticket.
	 *
	 * @param integer $ticket_id
	 * @param string $message
	 * @param array $extra
	 *
	 * @return array|bool
	 */
	public function createTicketMessage($ticket_id, $message, array $extra = array())
	{
		$params['message'] = $message;

		$results = $this->call('POST', '/tickets/' . intval($ticket_id) . '/messages', $extra);
		return $this->_getResponse($results);
	}

	/**
	 * Gets a ticket message.
	 *
	 * @param integer $ticket_id
	 * @param integer $message_id
	 *
	 * @return array|bool
	 */
	public function getTicketMessage($ticket_id, $message_id)
	{
		$results = $this->call('GET', '/tickets/' . intval($ticket_id) . '/messages/' . intval($message_id));
		return $this->_getResponse($results);
	}

	/**
	 * Gets all participants (CC'd users) in a ticket.
	 *
	 * @param integer $ticket_id
	 *
	 * @return array|bool
	 */
	public function getTicketParticipants($ticket_id)
	{
		$results = $this->call('GET', '/tickets/' . intval($ticket_id) . '/participants');
		return $this->_getResponse($results);
	}

	/**
	 * Adds a ticket participant.
	 *
	 * @param integer $ticket_id
	 * @param integer|null $person_id If non-null, the ID of the person to add
	 * @param string|null $email If non-null (and person_id is null), adds the specified email as a participant. A person will be created if needed.
	 *
	 * @return array|bool
	 */
	public function addTicketParticipant($ticket_id, $person_id = null, $email = null)
	{
		$params = array();
		if ($person_id) {
			$params['person_id'] = $person_id;
		}
		if ($email) {
			$params['email'] = $email;
		}

		$results = $this->call('POST', '/tickets/' . intval($ticket_id) . '/participants', $params);
		return $this->_getResponse($results);
	}

	/**
	 * Returns whether a person is a participant (CC user) in a ticket.
	 *
	 * @param integer $ticket_id
	 * @param integer $person_id
	 *
	 * @return bool
	 */
	public function getTicketParticipant($ticket_id, $person_id)
	{
		$results = $this->call('GET', '/tickets/' . intval($ticket_id) . '/participants/' . intval($person_id));
		return $this->_getExistsResponse($results);
	}

	/**
	 * Removes a participant from a ticket.
	 *
	 * @param integer $ticket_id
	 * @param integer $person_id
	 *
	 * @return bool
	 */
	public function removeTicketParticipant($ticket_id, $person_id)
	{
		$results = $this->call('DELETE', '/tickets/' . intval($ticket_id) . '/participants/' . intval($person_id));
		return $this->_getSuccessResponse($results);
	}

	/**
	 * Gets all labels associated with a ticket.
	 *
	 * @param integer $ticket_id
	 *
	 * @return array|bool
	 */
	public function getTicketLabels($ticket_id)
	{
		$results = $this->call('GET', '/tickets/' . intval($ticket_id) . '/labels');
		return $this->_getResponse($results);
	}

	/**
	 * Adds a label to a ticket.
	 *
	 * @param integer $ticket_id
	 * @param string $label
	 *
	 * @return array|bool
	 */
	public function addTicketLabel($ticket_id, $label)
	{
		$results = $this->call('POST', '/tickets/' . intval($ticket_id) . '/labels', array('label' => $label));
		return $this->_getResponse($results);
	}

	/**
	 * Determines if a ticket has a specific label.
	 *
	 * @param integer $ticket_id
	 * @param string $label
	 *
	 * @return bool
	 */
	public function getTicketLabel($ticket_id, $label)
	{
		$results = $this->call('GET', '/tickets/' . intval($ticket_id) . '/labels/' . $label);
		return $this->_getExistsResponse($results);
	}

	/**
	 * Removes a label from a ticket.
	 *
	 * @param integer $ticket_id
	 * @param string $label
	 *
	 * @return bool
	 */
	public function removeTicketLabel($ticket_id, $label)
	{
		$results = $this->call('DELETE', '/tickets/' . intval($ticket_id) . '/label/' . $label);
		return $this->_getSuccessResponse($results);
	}

	/**
	 * Gets a list of custom ticket fields.
	 *
	 * @return array|bool
	 */
	public function getTicketsFields()
	{
		$results = $this->call('GET', '/tickets/fields');
		return $this->_getResponse($results);
	}

	/**
	 * Gets a list of ticket departments.
	 *
	 * @return array|bool
	 */
	public function getTickestDepartments()
	{
		$results = $this->call('GET', '/tickets/departments');
		return $this->_getResponse($results);
	}

	/**
	 * Gets a list of products.
	 *
	 * @return array|bool
	 */
	public function getTicketsProducts()
	{
		$results = $this->call('GET', '/tickets/products');
		return $this->_getResponse($results);
	}

	/**
	 * Gets a list of ticket categories.
	 *
	 * @return array|bool
	 */
	public function getTicketsCategories()
	{
		$results = $this->call('GET', '/tickets/categories');
		return $this->_getResponse($results);
	}

	/**
	 * Gets a list of ticket priorities.
	 *
	 * @return array|bool
	 */
	public function getTicketsPriorities()
	{
		$results = $this->call('GET', '/tickets/priorities');
		return $this->_getResponse($results);
	}

	/**
	 * Gets a list of ticket workflows.
	 *
	 * @return array|bool
	 */
	public function getTicketsWorkflows()
	{
		$results = $this->call('GET', '/tickets/workflows');
		return $this->_getResponse($results);
	}

	/**
	 * Gets a list of ticket filters.
	 *
	 * @return array|bool
	 */
	public function getTicketsFilters()
	{
		$results = $this->call('GET', '/tickets/filters');
		return $this->_getResponse($results);
	}

	/**
	 * Runs the specified ticket filter and returns the results.
	 *
	 * @param integer $filter_id
	 * @param integer $page Page number to retrieve results from
	 *
	 * @return array|bool
	 */
	public function runTicketFilter($filter_id, $page = 1)
	{
		$params = array('page' => $page);
		$results = $this->call('GET', '/tickets/filters/' . intval($filter_id), $params);
		return $this->_getResponse($results);
	}
}

/**
 * Represents the HTTP call results from a DeskPRO API call.
 */
class DpApiResult
{
	/**
	 * HTTP response code
	 *
	 * @var int
	 */
	protected $_code = 200;

	/**
	 * List of headers. As headers may be repeated, in form array(array([name], [value]),...).
	 *
	 * @var array
	 */
	protected $_headers = array();

	/**
	 * Result body
	 *
	 * @var string
	 */
	protected $_body;

	/**
	 * JSON version of the body (if a conversion is possible)
	 *
	 * @var mixed
	 */
	protected $_json = null;

	/**
	 * @param string $headers Raw HTTP headers
	 * @param string $body
	 */
	public function __construct($headers, $body)
	{
		$this->_parseHeaders($headers);
		$this->_body = $body;
	}

	/**
	 * Parses the headers and HTTP response code (assumed to be first line).
	 *
	 * @param string $headers
	 */
	protected function _parseHeaders($headers)
	{
		$lines = explode("\r\n", $headers);
		$first = array_shift($lines);

		if (preg_match('/^HTTP\/1\.\d (\d{3})/', $first, $match)) {
			$this->_code = intval($match[1]);
		}

		foreach ($lines AS $line) {
			$parts = explode(':', $line, 2);
			if (isset($parts[1])) {
				$this->_headers[] = array(trim(strtolower($parts[0])), trim($parts[1]));
			}
		}
	}

	/**
	 * Gets the JSON body results. Returns false if the JSON could not be decoded.
	 *
	 * @return mixed
	 */
	public function getJson()
	{
		if ($this->_json === null) {
			$this->_json = json_decode($this->_body, true);
			if ($this->_json === null) {
				$this->_json = false;
			}
		}

		return $this->_json;
	}

	/**
	 * Gets the raw string body.
	 *
	 * @return string
	 */
	public function getBody()
	{
		return $this->_body;
	}

	/**
	 * Sets the body results.
	 *
	 * @param string $body
	 */
	public function setBody($body)
	{
		$this->_body = $body;
		$this->_json = null;
	}

	/**
	 * Gets the HTTP response code.
	 *
	 * @return int
	 */
	public function getResponseCode()
	{
		return $this->_code;
	}

	/**
	 * Gets the headers. If no name is specified, returns all headers.
	 * If a name is given, gets the values for each header with that name.
	 *
	 * @param string|null $name
	 *
	 * @return array
	 */
	public function getHeaders($name = null)
	{
		if ($name === null) {
			return $this->_headers;
		}

		$output = array();
		$name = strtolower($name);

		foreach ($this->_headers AS $header) {
			if ($header[0] == $name) {
				$output[] = $header[1];
			}
		}

		return $output;
	}
}

/**
 * General DP API exception type.
 */
class DpApiException extends Exception {}

/**
 * An exception that represents a failed authentication with the API.
 */
class DpApiAuthException extends DpApiException {}

/**
 * An exception that represents the API not returning a JSON value.
 */
class DpApiResponseException extends DpApiException {}