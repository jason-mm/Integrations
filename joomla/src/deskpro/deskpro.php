<?php
defined('_JEXEC') or die;

class PlgSystemDeskpro extends JPlugin
{
	/**
	 * @var string
	 */
	protected $dp_url;

	/**
	 * @var string
	 */
	protected $dp_api_key;

	/**
	 * @var string
	 */
	protected $joomla_secret;

	/**
	 * @var string
	 */
	protected $did_logged_in_user = array();

	/**
	 * @var array|null
	 */
	protected $last_login_info;

	/**
	 * @var array|null
	 */
	protected $last_login_options;

	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);

		$this->dp_url        = $this->params->get('dp_url');
		$this->dp_api_key    = $this->params->get('dp_api_key');
		$this->joomla_secret = $this->params->get('dp_joomla_api_key');

		$this->db = JFactory::getDbo();
	}

	public function onAfterInitialise()
	{
		if (isset($_GET['__dp_call'])) {
			$db = JFactory::getDbo();
			$data = isset($_REQUEST['DATA']) ? $_REQUEST['DATA'] : null;

			$m = null;
			if (!$data || !is_string($data) || !preg_match('#^(\d+)_([a-fA-F0-9]+)_(.*?)$#', $data, $m)) {
				echo json_encode(array('error' => true, 'code' => 'invalid_data.0'));
				exit;
			}

			$time   = $m[1];
			$sign   = $m[2];
			$params = @json_decode(@base64_decode($m[3]), true);

			if (!$sign || !$params || $time < (time() - 30000)) {
				echo json_encode(array('error' => true, 'code' => 'invalid_data.1'));
				exit;
			}

			$sign_check = sha1($time . $m[3] . $this->joomla_secret);
			if ($sign != $sign_check) {
				echo json_encode(array('error' => true, 'code' => 'invalid_data.2'));
				exit;
			}

			if (!isset($params['action'])) {
				echo json_encode(array('error' => true, 'code' => 'invalid_data.3'));
				exit;
			}

			switch ($params['action']) {
				case 'lookup_user_email':
					$query = $db->getQuery(true)
						->select('*')
						->from('#__users')
						->where('email=' . $db->quote($params['user_email']));

					$db->setQuery($query);
					$result = $db->loadAssoc();

					if ($result) {
						echo json_encode(array('success' => true, 'user_info' => $result));
					} else {
						echo json_encode(array('success' => true, 'user_info' => null));
					}
					break;

				case 'lookup_user_username':
					$query = $db->getQuery(true)
						->select('*')
						->from('#__users')
						->where('username=' . $db->quote($params['user_username']));

					$db->setQuery($query);
					$result = $db->loadAssoc();

					if ($result) {
						echo json_encode(array('success' => true, 'user_info' => $result));
					} else {
						echo json_encode(array('success' => true, 'user_info' => null));
					}
					break;

				case 'lookup_user_id':
					$query = $db->getQuery(true)
						->select('*')
						->from('#__users')
						->where('id=' . $db->quote($params['user_id']));

					$db->setQuery($query);
					$result = $db->loadAssoc();

					if ($result) {
						echo json_encode(array('success' => true, 'user_info' => $result));
					} else {
						echo json_encode(array('success' => true, 'user_info' => null));
					}
					break;

				case 'auth':

					$app = JFactory::getApplication();
					$result = $app->login(array(
						'username' => $params['username'],
						'password' => $params['password']
					));

					$fetch_username = null;

					if (!($result instanceof Exception) && $this->last_login_info && !empty($this->last_login_info['username'])) {
						$fetch_username = $this->last_login_info['username'];

					// was provided an email address, try finding the username to login with instead
					} else if (strpos($params['username'], '@') !== false) {
						$query = $db->getQuery(true)
							->select('*')
							->from('#__users')
							->where('email=' . $db->quote($params['username']));

						$db->setQuery($query);
						$result = $db->loadAssoc();

						if ($result) {
							$params['username'] = $result['username'];

							$result = $app->login(array(
								'username' => $params['username'],
								'password' => $params['password']
							));

							if (!($result instanceof Exception) && $this->last_login_info && !empty($this->last_login_info['username'])) {
								$fetch_username = $this->last_login_info['username'];
							}
						}
					}

					if ($fetch_username) {
						$query = $db->getQuery(true)
							->select('*')
							->from('#__users')
							->where('username=' . $db->quote($this->last_login_info['username']));

						$db->setQuery($query);
						$result = $db->loadAssoc();

						if ($result) {
							echo json_encode(array('success' => true, 'user_info' => $result));
							exit;
						}
					}

					echo json_encode(array('success' => true, 'user_info' => null));

					break;

				case 'init_session':
					$user = JFactory::getUser();
					if ($user->get('id')) {
						echo '<!-- ALREADY LOGGED IN -->';
					} else {
						try {
							$instance = JUser::getInstance();
							$instance->load($params['user_id']);

							if (!$instance || !$instance->get('id')) {
								$instance = null;
							}
						} catch (\Exception $e) {
							$instance = null;
						}

						if ($instance) {
							if ($instance->get('block') == 1) {
								return false;
							}

							$instance->set('guest', 0);
							$session = JFactory::getSession();
							$session->set('user', $instance);

							$db = JFactory::getDbo();

							$app = JFactory::getApplication();
							$app->checkSession();

							$query = $db->getQuery(true)
								->update($db->quoteName('#__session'))
								->set($db->quoteName('guest') . ' = ' . $db->quote($instance->get('guest')))
								->set($db->quoteName('username') . ' = ' . $db->quote($instance->get('username')))
								->set($db->quoteName('userid') . ' = ' . (int) $instance->get('id'))
								->where($db->quoteName('session_id') . ' = ' . $db->quote($session->getId()));
							$db->setQuery($query);
							$db->execute();

							// Hit the user last visit field
							$instance->setLastVisit();

							echo "<!-- UPDATED SESSION FOR USER {$params['user_id']} -->";
						}
					}

					exit;
					break;
			}

			exit;
		}
	}
}