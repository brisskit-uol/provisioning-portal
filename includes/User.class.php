<?php

class User{

	// Private ORM instance
	private $orm;

	/**
	 * Find a user by a token string. Only valid tokens are taken into
	 * consideration. A token is valid for 10 minutes after it has been generated.
	 * @param string $token The token to search for
	 * @return User
	 */

	public static function findByToken($token){
		
		// find it in the database and make sure the timestamp is correct

		$result = ORM::for_table('reg_users')
						->where('token', $token)
						->where_raw('token_validity > NOW()')
						->find_one();

		if(!$result){
			return false;
		}

		return new User($result);
	}

	/**
	 * Either login or register a user.
	 * @param string $email The user's email address
	 * @return User
	 */

	public static function loginOrRegister($email){

		// If such a user already exists, return it

		if(User::exists($email)){
			return new User($email);
		}
		
		// Otherwise, create it and return it

		return User::create($email);
	}

	/**
	 * Create a new user and save it to the database
	 * @param string $email The user's email address
	 * @return User
	 */

	private static function create($email){

		// Write a new user to the database and return it
		
		// Generate unique customer ID
		$mdate = explode(' ', microtime());
		
		// Get remote IP address of request.
		$ip_addr = (isset($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
		$ip_addr = explode('.', $ip_addr);
		$ip_addr = array_map(function($a) { return str_pad($a, 3, '0', STR_PAD_LEFT); }, $ip_addr);
		$ip_addr = implode('_', $ip_addr);
		
		$date = date("Ymd_His", $mdate[1]);
		$msecs = str_pad(intval(floatval($mdate[0]) * 1000), 3, '0', STR_PAD_LEFT);
		$rand = mt_rand(10000, 99999);
		
		$custid = implode('-', array($ip_addr, $date . '_' . $msecs, $rand));
		
		$result = ORM::for_table('reg_users')->create();
		$result->email = $email;
		$result->custid = $custid;
		$result->save();

		return new User($result);
	}

	/**
	 * Check whether such a user exists in the database and return a boolean.
	 * @param string $email The user's email address
	 * @return boolean
	 */

	public static function exists($email){

		// Does the user exist in the database?
		$result = ORM::for_table('reg_users')
					->where('email', $email)
					->count();

		return $result == 1;
	}

	/**
	 * Create a new user object
	 * @param $param ORM instance, id, email or null
	 * @return User
	 */

	public function __construct($param = null){

		if($param instanceof ORM){

			// An ORM instance was passed
			$this->orm = $param;
		}
		else if(is_string($param)){

			// An email was passed
			$this->orm = ORM::for_table('reg_users')
							->where('email', $param)
							->find_one();
		}
		else{

			$id = 0;

			if(is_numeric($param)){
				// A user id was passed as a parameter
				$id = $param;
			}
			else if(isset($_SESSION['loginid'])){

				// No user ID was passed, look into the sesion
				$id = $_SESSION['loginid'];
			}

			$this->orm = ORM::for_table('reg_users')
							->where('id', $id)
							->find_one();
		}

	}

	/**
	 * Generates a new SHA256 login token, writes it to the database and returns it.
	 * @return string
	 */

	public function generateToken(){
		// generate a token for the logged in user. Save it to the database.

		$token = hash('sha256', $this->email.time().rand(0, 1000000));

		// Save the token to the database, 
		// and mark it as valid for the next 10 minutes only

		$this->orm->set('token', $token);
		$this->orm->set_expr('token_validity', "ADDTIME(NOW(),'0:10')");
		$this->orm->save();

		return $token;
	}
	
	public function storeURL($url){
		// generate a token for the logged in user. Save it to the database.
		// Save the token to the database, 
		// and mark it as valid for the next 10 minutes only

		$this->orm->set('instance_url', $url);
		$this->orm->save();

		return $url;
	}

	/**
	 * Login this user
	 * @return void
	 */

	public function login(){
		
		// Mark the user as logged in
		$_SESSION['loginid'] = $this->orm->id;

		// Update the last_login db field
		$this->orm->set_expr('last_login', 'NOW()');
		$this->orm->save();
	}

	/**
	 * Destroy the session and logout the user.
	 * @return void
	 */

	public function logout(){
		$_SESSION = array();
		unset($_SESSION);
	}

	/**
	 * Check whether the user is logged in.
	 * @return boolean
	 */

	public function loggedIn(){
		return isset($this->orm->id) && $_SESSION['loginid'] == $this->orm->id;
	}

	/**
	 * Check whether the user is an administrator
	 * @return boolean
	 */

	public function isAdmin(){
		return $this->rank() == 'administrator';
	}

	/**
	 * Find the type of user. It can be either admin or regular.
	 * @return string
	 */

	public function rank(){
		if($this->orm->rank == 1){
			return 'administrator';
		}

		return 'regular';
	}

	/**
	 * Magic method for accessing the elements of the private
	 * $orm instance as properties of the user object
	 * @param string $key The accessed property's name 
	 * @return mixed
	 */

	public function __get($key){
		if(isset($this->orm->$key)){
			return $this->orm->$key;
		}

		return null;
	}
}