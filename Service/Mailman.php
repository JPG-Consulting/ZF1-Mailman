<?php
/**
 * Mailing lists service.
 * 
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * 
 * @author     Juan Pedro Gonzalez Gutierrez
 * @copyright  Copyright (c) 2012-2013 Juan Pedro Gonzalez Gutierrez (http://www.jpg-consulting.es)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * 
 *
 */

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
	 * A translator.
	 * (Use with errors for easier management)
	 * @var Zend_Translate
	 */
	//protected $_translator;
	

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
	 * Get the translator.
	 * 
	 * @return Zend_Translate|null
	 */
	/*
	public function getTranslator()
	{
		if (null === $this->_translator) {
			if (Zend_Registry::isRegistered('Zend_Translate')) {
				$translator = Zend_Registry::get('Zend_Translate');
				if ($translator instanceof Zend_Translate) {
					$this->_translator = new Zend_Translate(array(
						'adapter'        => 'gettext',
						'content'        => dirname(__FILE__) . '/Mailman/messages',
						'locale'         => (string)$translator->getLocale(),
						'scan'           => Zend_Translate::LOCALE_DIRECTORY,
						'disableNotices' => true
					));
				}
			}
		}
		return $this->_translator;
	}
	*/
	
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
					if (strcasecmp($h5, 'Error subscribing:') === 0) {
						$li = $xpath->query('/html/body/ul/li');
						if ($li) {
							if ($li->length > 0) {
								// Get and translate all "Li"s
								$li_value = trim((string)$li->item(0)->nodeValue);
								$h5 = trim((string)$li->item(0)->nodeValue);
							}			
						}
					}
					/*if (null !== $this->getTranslator()) {
						$h5 = $this->getTranslator()->translate($h5);
					}*/
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
					/*if (null !== $this->getTranslator()) {
						$h5 = $this->getTranslator()->translate($h5);
					}*/
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
				/*if (null !== $this->getTranslator()) {
					$h3 = $this->getTranslator()->translate($h3);
				}*/
				$h3 = trim(rtrim($h3, ':'));
				if (empty($h3)) $h3 = 'Unknown error';
				throw new Mailman_Service_Mailman_Exception($h3);
			}
		}
		
		require_once 'Mailman/Service/Mailman/Exception.php';
		throw new Mailman_Service_Mailman_Exception("Failed to parse response");
	}
	
	/**
	 * Find a member.
	 * 
	 * @param string $text
	 * @throws Mailman_Service_Mailman_Exception
	 */
	public function findMember( $text )
	{
		if (!is_string($text))
		{
			require_once 'Mailman/Service/Mailman/Exception.php';
			throw new Mailman_Service_Mailman_Exception('findMember() expects parameter to be string, ' . gettype($text) . ' given');
		}
		
		// forge URI
		$uri = $this->getUri('members', array(
			'findmember'        => $text, 
			'setmemberopts_btn' => null,
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
		
		
		$queries = array(
			'address'  => $xpath->query('/html/body/form/center/table/tr/td[2]/a'),
        	'realname' => $xpath->query('/html/body/form/center/table/tr/td[2]/input[type=TEXT]/@value'),
	        'mod'      => $xpath->query('/html/body/form/center/table/tr/td[3]/center/input/@value'),
	        'hide'     => $xpath->query('/html/body/form/center/table/tr/td[4]/center/input/@value'),
	        'nomail'   => $xpath->query('/html/body/form/center/table/tr/td[5]/center/input/@value'),
	        'ack'      => $xpath->query('/html/body/form/center/table/tr/td[6]/center/input/@value'),
	        'notmetoo' => $xpath->query('/html/body/form/center/table/tr/td[7]/center/input/@value'),
	        'nodupes'  => $xpath->query('/html/body/form/center/table/tr/td[8]/center/input/@value'),
	        'digest'   => $xpath->query('/html/body/form/center/table/tr/td[9]/center/input/@value'),
	        'plain'    => $xpath->query('/html/body/form/center/table/tr/td[10]/center/input/@value'),
	        'language' => $xpath->query('/html/body/form/center/table/tr/td[11]/center/select/option[@selected]/@value')
		);
		
		if (function_exists('libxml_clear_errors')) {
			libxml_clear_errors();
		}
		
		$count = $queries['address']->length;
		if (!$count) {
			require_once 'Mailman/Service/Mailman/Exception.php';
			throw new Mailman_Service_Mailman_Exception('No match' /*,Services_Mailman_Exception::NO_MATCH*/);
        }
        $a = array();
        for ($i=0;$i < $count;$i++) {
            foreach ($queries as $key => $query) {
                $a[$i][$key] = $query->item($i) ? $query->item($i)->nodeValue : '';
            }
        }
        return $a;
	}
	
	public function isSubscribedEmail( $email )
	{
		if (!$this->_isValidEmailAddress($email)) {
			require_once 'Mailman/Service/Mailman/Exception.php';
			throw new Mailman_Service_Mailman_Exception('Invalid email address');
		}
		
		$subscribers = $this->findMember( $email );
		foreach ($subscribers as $subscriber) {
			if (strcasecmp($subscriber['address'], $email) === 0) {
				return true;
			}
		}
		
		return false;
	}
}