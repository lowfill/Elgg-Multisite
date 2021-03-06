<?php
	/**
	 * Elgg multisite library.
	 *
	 * @package ElggMultisite
	 * @author Marcus Povey <marcus@marcus-povey.co.uk>
	 * @copyright Marcus Povey 2010
	 * @link http://www.marcus-povey.co.uk/
 	 */

	/**
	 * Multisite domain.
	 */
	class MultisiteDomain implements
		Iterator,	// Override foreach behaviour
		ArrayAccess // Override for array access
	{
		private $__attributes = array();
		
		private $domain = "";
		private $id;
		
		
		public function __construct($url = '') 
		{
			if ($url) {
				if ($this->load($url) === false)
					throw new Exception("Domain settings for $url could not be found");
			}
		}
		
	public function __get($name) { return (array_key_exists($name, $this->__attributes))?$this->__attributes[$name]:''; }
		public function __set($name, $value) { return $this->__attributes[$name] = $value; }
		public function __isset($name) { return isset($this->__attributes[$name]); }
		public function __unset($name) { unset($this->__attributes[$name]); }
		
		// ITERATOR INTERFACE //////////////////////////////////////////////////////////////
		private $__iterator_valid = FALSE; 
		
   		public function rewind() { $this->__iterator_valid = (FALSE !== reset($this->__attributes));  	}
   		public function current() 	{ return current($this->__attributes); 	}	
   		public function key() 	{ return key($this->__attributes); 	}	
   		public function next() { $this->__iterator_valid = (FALSE !== next($this->__attributes));  }
   		public function valid() { 	return $this->__iterator_valid;  }
   		
   		// ARRAY ACCESS INTERFACE //////////////////////////////////////////////////////////
		public function offsetSet($key, $value) { if ( array_key_exists($key, $this->__attributes) ) $this->__attributes[$key] = $value; } 
 		public function offsetGet($key) { if ( array_key_exists($key, $this->__attributes) ) return $this->__attributes[$key]; } 
 		public function offsetUnset($key) { if ( array_key_exists($key, $this->__attributes) ) $this->__attributes[$key] = ""; } 
 		public function offsetExists($offset) { return array_key_exists($offset, $this->__attributes);	} 
		
	
 		/**
 		 * Can the site be accessed? 
 		 * 
 		 * Override in order to check quotas etc.
 		 *
 		 * @return unknown
 		 */
		public function isSiteAccessible() {
			if ($this->enabled == 'no')
				return false;
				 
			return true; 
		}
		
		public function setDomain($url) { $this->domain = $url; }
		public function getDomain() {return $this->domain; }
		public function getID() {return $this->id; }
		
		public function isDbInstalled()
		{
			$link = mysql_connect($this->dbhost, $this->dbuser, $this->dbpass, true);
			mysql_select_db($this->dbname, $link);
			
			$result = mysql_query("SHOW tables like '{$this->dbprefix}%'", $link);
			if (!$result) return false;
			
			$result = mysql_fetch_object($result);
			if ($result)
				return true;
				
			return false;
		}
		
		public function getDBVersion()
		{
			$link = mysql_connect($this->dbhost, $this->dbuser, $this->dbpass, true);
			mysql_select_db($this->dbname, $link);

			$result = mysql_query("SELECT * FROM {$this->dbprefix}datalists WHERE name='version'");
			
			if (!$result) return false;
			$result = mysql_fetch_object($result);
			
			if ($result)
				(int)$result->value;
				
			return false;
		}
		
		public function disable_plugin($plugin)
		{
			return $this->toggle_plugin($plugin, false);
		}
		
		public function enable_plugin($plugin)
		{
			return $this->toggle_plugin($plugin, true);
		}
		
		protected function toggle_plugin($plugin, $enable = true, $site_id = 1)
		{
			//TODO: Handle nested sites
			
			$link = mysql_connect($this->dbhost, $this->dbuser, $this->dbpass, true);
			mysql_select_db($this->dbname, $link);
			
			$plugin = mysql_real_escape_string($plugin);
			$site_id = (int)$site_id;
			
			
			$result = mysql_query("SELECT * FROM {$this->dbprefix}metastrings WHERE string='enabled_plugins'",$link);
			if (!$result) return false;
			$string = mysql_fetch_object($result);
			if (!$string) {
				mysql_query("INSERT into {$this->dbprefix}metastrings (string) VALUES ('enabled_plugins')");
				$enabled_id = mysql_insert_id($link);	
			}
			else
			{
				
				$enabled_id = $string->id;
			}
			
			$result = mysql_query("SELECT * FROM {$this->dbprefix}metastrings WHERE string='$plugin'", $link);
			if (!$result) return false;
			$string = mysql_fetch_object($result);
			if (!$string) {
				mysql_query("INSERT into {$this->dbprefix}metastrings (string) VALUES ('$plugin')");
				$plugin_id = mysql_insert_id($link);	
			}
			else
			{
				
				$plugin_id = $string->id;	
			}
			
			// Insert metadata
			if ($enable)
			{ 
			/*	$query = "INSERT INTO {$this->dbprefix}metadata (entity_guid, name_id, value_id, value_type, owner_guid, access_id, time_created) VALUES($site_id, $enabled_id, $plugin_id, 'text', 2, 2, '".time()."')";
			
				mysql_query($query);*/
				
				// TODO : Enable
			}
			else
			{
				$query = "DELETE from {$this->dbprefix}metadata WHERE entity_guid=$site_id and name_id=$enabled_id and value_id=$plugin_id";
			
				mysql_query($query);
			}
			
			return true;
		}
 		
		/**
		 * Save object to database.
		 */
 		public function save()
 		{
 			$class = get_class($this);
 			$url = mysql_real_escape_string($this->getDomain());
 			
 			$dblink = elggmulti_db_connect();
 			if (!$this->id) {
 				$result = elggmulti_execute_query("INSERT into domains (domain, class) VALUES ('$url', '$class')");
 				$this->id = mysql_insert_id($dblink);
 			}
 			else
 				$result = elggmulti_execute_query("UPDATE domains set domain='$url', class='$class' WHERE id={$this->id}");
 		
 			if (!$result)
 				return false;
 				
 			
 			elggmulti_execute_query("DELETE from domains_metadata where domain_id='{$this->id}'");
 			
 			foreach ($this->__attributes as $key => $value)
 			{
 				// Sanitise string
				$key = mysql_real_escape_string($key);
					
				// Convert non-array to array 
				if (!is_array($value))
					$value = array($value);
					
				// Save metadata
				foreach ($value as $meta)
					elggmulti_execute_query("INSERT into domains_metadata (domain_id,name,value) VALUES ({$this->id}, '$key', '".mysql_real_escape_string($meta)."')");
				
 			}
 		}
 		
 		/**
 		 * Load database settings.
 		 *
 		 * @param string $url URL to load
 		 */
 		public function load($url)
 		{
 			$row = elggmulti_getdata_row("SELECT * from domains WHERE domain='$url' LIMIT 1");
 			if (!$row)
 				return false;
 				
 			$this->domain = $row->domain;
 			$this->id = $row->id;
 				
 			$meta = elggmulti_getdata("SELECT * from domains_metadata where domain_id = {$row->id}");
 			if ($meta)
 			{
 				foreach ($meta as $md)
 				{
 					$name = $md->name;
					$value = $md->value;
				
					// If already set, turn existing value into array
					if ($this->$name) 
					{
						$tmp = array($this->$name);
						$tmp[] = $value;
						$this->$name = $tmp;
					}	
					else
						$this->$name = $value;
 				}
 			}
 		}
 		
 		
 		/**
		 * Return available multisite domains.
		 *
		 * @return array
		 */
		public static function getDomainTypes() 
		{
			return array (
				'MultisiteDomain' => 'Elgg domain',
			);
		}
		
	}
	
	/**
	 * Helper function for constructing classes.
	 */
	function __elggmulti_db_row($row)
	{
		// Sanity check
		if (
			(!($row instanceof stdClass)) ||
			(!$row->id) ||
			(!$row->class)
		)	
			throw new Exception('Invalid handling class');  
			
		$class = $row->class;
		
		if (class_exists($class))
		{
			$object = new $class();
			
			if (!($object instanceof MultisiteDomain))
				throw new Exception('Class is invalid');
				
			$object->load($row->domain);
			
			return $object;
		}  
			
		return false;
	}

	/**
	 * Connect multisite database.
	 *
	 * @return dblink|false
	 */
	function elggmulti_db_connect()
	{
		global $CONFIG;
		
		if (!array_key_exists('elggmulti_link', $CONFIG))
		{ 
			$CONFIG->elggmulti_link = mysql_connect($CONFIG->multisite->dbhost, $CONFIG->multisite->dbuser, $CONFIG->multisite->dbpass, true);
			mysql_select_db($CONFIG->multisite->dbname, $CONFIG->elggmulti_link);
		}
		
		if ($CONFIG->elggmulti_link)
			return $CONFIG->elggmulti_link;
			
		return false;
	}
	
	/**
	 * Execute a raw query.
	 *
	 * @param string $query
	 * @return dbresult|false
	 */
	function elggmulti_execute_query($query)
	{
		global $CONFIG;
		
		$dblink = elggmulti_db_connect();
		$result = mysql_query($query, $dblink);
		
		if ($result)
			return $result;
			
		return false;
	}
	
	/**
	 * Get data from a database.
	 *
	 * @param string $query
	 * @param string $callback
	 */
	function elggmulti_getdata($query, $callback = '')
	{
		$resultarray = array();
		
		$dblink = elggmulti_db_connect();
		
		if ($result = elggmulti_execute_query($query))
		{
			while ($row = mysql_fetch_object($result)) {
				if (!empty($callback) && is_callable($callback)) {
					$row = $callback($row);
				}
				if ($row) 
					$resultarray[] = $row;
			}
		}
		
		return $resultarray;
	}

	/**
	 * Get data row.
	 *
	 * @param string $query
	 */
	function elggmulti_getdata_row($query, $callback = '')
	{
		$result = elggmulti_getdata($query, $callback);
		if ($result)
			return $result[0];
			
		return false;
	}
	
	/**
	 * User login.
	 *
	 * @param string $username Username to retrieve
	 * @param string $password Password
	 * @return bool
	 */
	function elggmulti_login($username, $password) 
	{
		$username = mysql_real_escape_string($username);
		$user = elggmulti_getdata_row("SELECT * FROM users WHERE username='$username' LIMIT 1");
	
		if ($user)
		{
			if (strcmp($user->password, md5($password.$user->salt)) == 0) {

				$_SESSION['user'] = $user;
				
				session_regenerate_id();
			
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Log the current user out.
	 *
	 */
	function elggmulti_logout()
	{
		unset ($_SESSION['user']);

		session_destroy();
		
		return true;
	}
	
	/**
	 * Create a new user item.
	 *
	 * @param string $username
	 * @param string $password
	 * @param string $password2
	 */
	function elggmulti_create_user($username, $password, $password2)
	{
		if (strcmp($password, $password2)!=0)
			return false;
			
		if (strlen($password) < 5)
			return false;
			
		if (strlen($username) < 5)
			return false;	
			
		$dblink = elggmulti_db_connect();

		$salt = substr(md5(rand()), 0, 8);
		$username = mysql_real_escape_string(strtolower($username));
		$password = md5(mysql_real_escape_string($password) . $salt);
		
		$result = elggmulti_execute_query("INSERT into users (username,password,salt) VALUES ('$username', '$password', '$salt')");	
		$userid = mysql_insert_id($dblink);		
			
		return $userid;
	}
	
	/**
	 * Set user password.
	 *
	 * @param string $username
	 * @param string $password
	 * @param string $password2
	 * @return bool
	 */
	function elggmulti_set_user_password($username, $password, $password2)
	{
		if (strcmp($password, $password2)!=0)
			return false;
			
		if (strlen($password) < 5)
			return false;
			
		$dblink = elggmulti_db_connect();

		$salt = substr(md5(rand()), 0, 8);
		$username = mysql_real_escape_string(strtolower($username));
		$password = md5(mysql_real_escape_string($password) . $salt);

		return elggmulti_execute_query("UPDATE users SET password='$password', salt='$salt' WHERE username='$username'");
	}
	
	/**
	 * Count number of users.
	 *
	 */
	function elggmulti_countusers()
	{
		$row = elggmulti_getdata_row('SELECT count(*) as count FROM users');
		
		return $row->count;
	}
	
	/**
	 * Get users
	 *
	 * @return unknown
	 */
	function elggmulti_getusers()
	{
		return elggmulti_getdata("SELECT * from users");
	}
	
	/**
	 * Retrieve db settings.
	 *
	 * Retrieve a database setting based on the current multisite domain
	 * detected.
	 * 
	 * @param $url The url who's settings you need to retrieve, detected if not provided.
	 * @return MultisiteDomain|false
	 */
	function elggmulti_get_db_settings($url = '')
	{
		global $CONFIG;
		
		$dblink = elggmulti_db_connect();
		$url = mysql_real_escape_string($url);
		
		// If no url then use the server referrer
		if (!$url) 
			$url = $_SERVER['SERVER_NAME'];

		$result = elggmulti_getdata_row("SELECT * from domains WHERE domain='$url' LIMIT 1", '__elggmulti_db_row');
		
		if ($result) {
			
			if (!$result->isSiteAccessible())
				return false;
			
			return $result;
		}
			
		
		return false;
	}
	
	function elggmulti_get_db_by_id($id)
	{
		$id = (int)$id;
		
		$result = elggmulti_getdata_row("SELECT * from domains WHERE id=$id LIMIT 1", '__elggmulti_db_row');
	
		if ($result)
			return $result;
		
		return false;
	}
	
	/**
	 * Return whether a plugin is available for a given
	 *
	 * @param string $plugin
	 */
	function elggmulti_is_plugin_available($plugin)
	{
		static $activated;
		
		if (!$activated)
			$activated = elggmulti_get_activated_plugins();
			
		return in_array($plugin, $activated);
	}
	
	/**
	 * Get plugins which have been activated for a given domain.
	 *
	 * @param int $domain_id
	 * @return array|false
	 */
	function elggmulti_get_activated_plugins($domain_id = false)
	{
		if (!$domain_id)
		{
			$result = elggmulti_get_db_settings();
			
			$domain_id = $result->getID();
		}
		
		$domain_id = (int)$domain_id;
		
		$result = elggmulti_getdata("SELECT * from domains_activated_plugins where domain_id=$domain_id");
		$resultarray = array();
		foreach ($result as $r)
			$resultarray[] = $r->plugin;
		
		return $resultarray;
	}
	
	/**
	 * Return a list of all installed plugins.
	 *
	 */
	function elggmulti_get_installed_plugins()
	{
		$plugins = array();

		$path = dirname(dirname(dirname(__FILE__))).'/mod/';
		
		if ($handle = opendir($path)) {
			
			while ($mod = readdir($handle)) {
				
				if (!in_array($mod,array('.','..','.svn','CVS')) && is_dir($path . "/" . $mod)) {
					if ($mod!='pluginmanager') // hide plugin manager
						$plugins[] = $mod;
				}
				
			}
		}

		sort($plugins);
		
		return $plugins;
	}
	
	/**
	 * Activate or deactivate a plugin. 
	 *
	 * @param unknown_type $domain_id
	 * @param unknown_type $plugin
	 * @param unknown_type $activate
	 */
	function elggmulti_toggle_plugin($domain_id, $plugin, $activate = true)
	{
		$plugin = mysql_real_escape_string($plugin);
		$domain_id = (int)$domain_id;
		
		if ($activate)
		{
			elggmulti_execute_query("INSERT into domains_activated_plugins (domain_id, plugin) VALUES ($domain_id, '$plugin')");
		}
		else
		{
			elggmulti_execute_query("DELETE FROM domains_activated_plugins where domain_id=$domain_id and plugin='$plugin'");

			$domain = elggmulti_get_db_by_id($domain_id);
			if ($domain)
				$domain->disable_plugin($plugin);
		}
	}
	
	function elggmulti_get_messages()
	{
		if ((isset($_SESSION['_EM_MESSAGES'])) && (is_array($_SESSION['_EM_MESSAGES']))) 
		{
			$messages = $_SESSION['_EM_MESSAGES'];
			$_SESSION['_EM_MESSAGES'] = null;
			
			return $messages;
		}
		
		return false;
	}
	
	function elggmulti_set_message($message)
	{
		if (!is_array($_SESSION['_EM_MESSAGES']))
			$_SESSION['_EM_MESSAGES'] = array();
			
		$_SESSION['_EM_MESSAGES'][] = $message;
		
		return true;
	}
	
?>
