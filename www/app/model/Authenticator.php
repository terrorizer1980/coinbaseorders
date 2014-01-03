<?php

use Nette\Security,
	Nette\Utils\Strings;


/**
 * Users authenticator.
 */
class Authenticator extends Nette\Object implements Security\IAuthenticator
{
	const
		TABLE_NAME = 'users',
		COLUMN_ID = 'user_id',
		COLUMN_EMAIL = 'email',
		COLUMN_PASSWORD = 'password',
		COLUMN_ROLE = 'role';

	/** @var Nette\Database\Connection */
	private $database;

	private $context;
	private $currentUser;	

	public function __construct(Nette\Database\Connection $database)
	{
		$this->database = $database;
		$this->currentUser = \Nette\Environment::getApplication()->presenter->user;
		$this->context = \Nette\Environment::getApplication()->presenter->context;
	}


	/**
	 * Performs an authentication.
	 * @return Nette\Security\Identity
	 * @throws Nette\Security\AuthenticationException
	 */
	public function authenticate(array $credentials)
	{
		list($email, $password) = $credentials;
		$row = $this->database->table(self::TABLE_NAME)->where(self::COLUMN_EMAIL, $email)->fetch();

		if (!$row) {
			throw new Security\AuthenticationException('The username is incorrect.', self::IDENTITY_NOT_FOUND);
		}

		if ($row[self::COLUMN_PASSWORD] !== $this->context->salted->hash($password)) {
			throw new Security\AuthenticationException('The password is incorrect.', self::INVALID_CREDENTIAL);
		}

		$arr = $row->toArray();
		unset($arr[self::COLUMN_PASSWORD]);
		return new Nette\Security\Identity($row[self::COLUMN_ID], $row[self::COLUMN_ROLE], $arr);
	}
	
	public function register( $email, $password ){
		if ($this->database->table(self::TABLE_NAME)->where(self::COLUMN_EMAIL, $email)->fetch()) {
			return False;
		}		
		
		$this->database->table(self::TABLE_NAME)->insert(Array(
			'email' => $email,
			'password' => $this->context->salted->hash($password),
			'role' => 'USER',
			'email_confirmation' => \Nette\Utils\Strings::random(4, '0-9A-Z'),
		));
		
		return True;
	}
	
	public function verifyEmail($userId, $emailCode){
		$userRow = $this->getUser($userId);
		if($userRow->email_confirmation == $emailCode){
			$this->update($userId, Array('email_confirmation' => 'confirmed'));
			return True;
		}
		return False;
	}

	public function update( $userId, array $data)
	{	
		//encrypt sensitive info
		foreach(Array('coinbase_access_token', 'coinbase_refresh_token') as $key){
			if(isset($data[$key])){
				$data[$key] = $this->context->salted->encrypt($data[$key]);
			}
		}

		//update in DB
		$this->database->table(self::TABLE_NAME)->get($userId)->update($data);		
		
		//update in session
		if($this->currentUser->id == $userId){
			foreach ($data as $attribute => $value) {
				$this->currentUser->identity->$attribute = $value;
			}
		}
	}
	
	public function getUser($userId){
		return $this->database->table(self::TABLE_NAME)->get($userId);
	}
}