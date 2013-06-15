<?php


class Mailman_Service_Mailman
{
	/**
	 * URL to the Mailman "Admin Links" page (no trailing slash)
	 * @var string|Zend_Uri
	 */
	protected $_uri = null;
	
	/**
	 * Mailing list name
	 * @var string
	 */
	protected $_list;
	
	/**
	 * Admin password for the list.
	 * @var string
	 */
	protected $_password;
	

	/**
	 * Constructor.
	 * 
	 * @param string|Zend_Uri $uri
	 * @param string|null $list
	 * @param string|null $password
	 */
	public function __construct($uri, $list = null, $password = null)
	{
		if ($uri instanceof Zend_Uri) {
			$uri = $uri->__toString();
		}

		if (!Zend_Uri::check($uri)) {
			require_once 'Mailman/Service/Mailman/Exception.php';
			throw new Mailman_Service_Mailman_Exception('Invalid URI');
		}
		
		$this->_uri = $uri;
		
		if (!empty($list)) {
			$this->setList($list, $password);
		} elseif (!empty($password)) {
			$this->setPassword($password);
		}
	}
	
	/**
	 * Sets the current mailing-list.
	 * 
	 * @param string $name
	 * @param string $password
	 * @throws Mailman_Service_Mailman_Exception
	 * @return Mailman_Service_Mailman
	 */
	public function setList($name, $password)
	{
		if (!is_string($name)) {
			require_once 'Mailman/Service/Mailman/Exception.php';
			throw new Mailman_Service_Mailman_Exception('The mailing-list name should be a string but ' . gettype($name) . ' given');
		} else {
			$name = trim($name);
			if ( empty($name) ) {
				require_once 'Mailman/Service/Mailman/Exception.php';
				throw new Mailman_Service_Mailman_Exception('The mailing-list name can not be empty');
			}
		}
		
		$this->_list = $name;
		$this->setPassword($password);
		return $this;
	}
	
	/**
	 * Gets the mailing-list name.
	 * 
	 * @return string|null Mailing-list name.
	 */
	public function getList()
	{
		return $this->_list;
	}
	
	/**
	 * Get an URI to our Mailman.
	 * 
	 * @param string|null $path
	 * @param array $query
	 * @throws Mailman_Service_Mailman_Exception
	 * @return string
	 */
	public function getUri($path = null, array $query = array())
	{
		if (empty($this->_list)) {
			require_once 'Mailman/Service/Mailman/Exception.php';
			throw new Mailman_Service_Mailman_Exception('No mailing-list has been defined');
		}
		
		if ((!is_string($path)) && (!empty($path))) {
			require_once 'Mailman/Service/Mailman/Exception.php';
			throw new Mailman_Service_Mailman_Exception('The path should be a string but ' . gettype($name) . ' given');
		}
		
		$uri = $this->_uri . Zend_Controller_Router_Abstract::URI_DELIMITER . $this->_list;
		
		if (!empty($path)) {
			$uri .= Zend_Controller_Router_Abstract::URI_DELIMITER . trim($path, Zend_Controller_Router_Abstract::URI_DELIMITER);
		}
		
		if (!empty($query)) {
			$uri .= '?' . http_build_query($query, '', '&');
		}
		
		return $uri;
	}
	
	/**
	 * Set the administrative password for the current mailing-list.
	 * 
	 * @param string $password
	 * @throws Mailman_Service_Mailman_Exception
	 * @return Mailman_Service_Mailman
	 */
	public function setPassword( $password )
	{
		if (!is_string($password)) {
			require_once 'Mailman/Service/Mailman/Exception.php';
			throw new Mailman_Service_Mailman_Exception('The mailing-list password should be a string but ' . gettype($password) . ' given');
		} elseif( empty($password) ) {
			require_once 'Mailman/Service/Mailman/Exception.php';
			throw new Mailman_Service_Mailman_Exception('The mailing-list password can not be empty');
		}
		
		$this->_password = $password;
		return $this;
	}
	
	/**
	 * Submit a query to Mailman.
	 * 
	 * @param string|Zend_Uri $uri
	 * @throws Mailman_Service_Mailman_Exception
	 */
	protected function _submit($uri)
	{
		if (empty($this->_list)) {
			require_once 'Mailman/Service/Mailman/Exception.php';
			throw new Mailman_Service_Mailman_Exception('No available mailing-list');
		} elseif (empty($this->_password)) {
			require_once 'Mailman/Service/Mailman/Exception.php';
			throw new Mailman_Service_Mailman_Exception('Missing administrative password for ' . $this->_list);
		}
		
		$client = new Zend_Http_Client($uri);
		
		$response = $client->request( Zend_Http_Client::GET );
		
		if ($response->getStatus() !== 200) {
			// Query the list to see if it is there
			$client = new Zend_Http_Client($this->_uri);
			$response = $client->request( Zend_Http_Client::GET );
			if ($response->getStatus() !== 200) {
				require_once 'Mailman/Service/Mailman/Exception.php';
				throw new Mailman_Service_Mailman_Exception('Mailman service not available');
			}
			
			// The service response is ok.
			// Does the mailing-list respond?
			$client = new Zend_Http_Client($this->_uri . Zend_Controller_Router_Abstract::URI_DELIMITER . $this->_list);
			$response = $client->request( Zend_Http_Client::GET );
			if ($response->getStatus() !== 200) {
				require_once 'Mailman/Service/Mailman/Exception.php';
				throw new Mailman_Service_Mailman_Exception('The mailing-list ' . $this->_list . ' does not exist or is unreachable');
			}
			
			// Generic service error
			require_once 'Mailman/Service/Mailman/Exception.php';
			throw new Mailman_Service_Mailman_Exception('Service not available');
		}
		
		return $response->getBody();
	}

	/**
	 * Checks if a mail address is valid.
	 * 
	 * @param string $email
	 */
	protected function _isValidEmailAddress( $email )
	{
		if (is_string($email)) {
			$validator = new Zend_Validate_EmailAddress();		
			return $validator->isValid($email);
		}
		return false;
	}
	
	/**
	 * Subscribe an email address to the current mailing-list
	 * 
	 * @param string $email
	 * @param boolean $invite
	 * @throws Mailman_Service_Mailman_Exception
	 */
	public function subscribe($email, $invite = false)
	{
		// Validate email
		if (!$this->_isValidEmailAddress($email)) {
			require_once 'Mailman/Service/Mailman/Exception.php';
			throw new Mailman_Service_Mailman_Exception('Invalid e-mail address');
		}
		
		// forge URI
		$uri = $this->getUri('members/add', array(
			'send_welcome_msg_to_this_batch' => 0,
			'send_notifications_to_list_owner' => 0,
			'subscribees' => $email,
			'adminpw' => $this->_password
		));
		
		// Fetch content
		$html = $this->_submit($uri);
		
		if (function_exists('libxml_use_internal_errors')) {
			libxml_use_internal_errors(true);
		}
		
		// Create a DOM document with retrieved content
		$dom = new DOMDocument();
		$dom->preserveWhiteSpace = false;
		$dom->loadHTML($html);
		
		// XPath for navigation
		$xpath = new DOMXPath($dom);
		
		$h5 = $xpath->query('/html/body/h5');
		if (function_exists('libxml_clear_errors')) {
			libxml_clear_errors();
		}
		
		if ($h5) {
			if ($h5->length > 0) {
				$h5 = trim((string)$h5->item(0)->nodeValue);
				if (strcasecmp($h5, 'Successfully subscribed:') === 0) {
					return true;
				} else {
					$h5 = trim(rtrim($h5, ':'));
					if (empty($h5)) $h5 = 'Unknown error';
					require_once 'Mailman/Service/Mailman/Exception.php';
					throw new Mailman_Service_Mailman_Exception($h5);
				}
			}
		}
		
		require_once 'Mailman/Service/Mailman/Exception.php';
		throw new Mailman_Service_Mailman_Exception("Failed to parse response");
	}
	

	/**
	 * Unsubscribe from the current mailing-list
	 * 
	 * @param string $email
	 * @throws Mailman_Service_Mailman_Exception
	 */
	public function unsubscribe($email)
	{
		// Validate email
		if (!$this->_isValidEmailAddress($email)) {
			require_once 'Mailman/Service/Mailman/Exception.php';
			throw new Mailman_Service_Mailman_Exception('Invalid e-mail address');
		}
		
		// forge URI
		$uri = $this->getUri('members/remove', array(
			'send_unsub_ack_to_this_batch' => 0,
			'send_unsub_notifications_to_list_owner' => 0,
			'unsubscribees' => $email,
			'adminpw' => $this->_password
		));
		
		// Fetch content
		$html = $this->_submit($uri);
		
		if (function_exists('libxml_use_internal_errors')) {
			libxml_use_internal_errors(true);
		}
		
		// Create a DOM document with retrieved content
		$dom = new DOMDocument();
		$dom->preserveWhiteSpace = false;
		$dom->loadHTML($html);
		
		// XPath for navigation
		$xpath = new DOMXPath($dom);
		
		$h5 = $xpath->query('/html/body/h5');
		$h3 = $xpath->query('/html/body/h3');
		
		if (function_exists('libxml_clear_errors')) {
			libxml_clear_errors();
		}
		
		if ($h5) {
			if ($h5->length > 0) {
				$h5 = trim((string)$h5->item(0)->nodeValue);
				if (strcasecmp($h5, 'Successfully Unsubscribed:') === 0) {
					return true;
				} else {
					$h5 = trim(rtrim($h5, ':'));
					if (empty($h5)) $h5 = 'Unknown error';
					require_once 'Mailman/Service/Mailman/Exception.php';
					throw new Mailman_Service_Mailman_Exception($h5);
				}
			}
		}
		
		// Some errors are displayed in h3
		if ($h3) {
			if ($h3->length > 0) {
				$h3 = trim(rtrim($h3, ':'));
				if (empty($h3)) $h3 = 'Unknown error';
				require_once 'Mailman/Service/Mailman/Exception.php';
				throw new Mailman_Service_Mailman_Exception($h3);
			}
		}
		
		require_once 'Mailman/Service/Mailman/Exception.php';
		throw new Mailman_Service_Mailman_Exception("Failed to parse response");
	}
	
}