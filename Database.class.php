<?php
/**
 * @author Ibrahim Tounkara (PHP/Symfony Backend Developer)
 */
 
namespace app\xanfaberAPI\src\catalog\xanfaber\model;
use app\xanfaberAPI\kernel\interfaceDatabase;
use \PDO;
use \PDOException;
use lib\Log\Logger;

require_once $_SERVER["DOCUMENT_ROOT"] .'xandrieAPIs/lib/library.php';
require_once $_SERVER["DOCUMENT_ROOT"] .'xandrieAPIs/lib/autoload.php';



class Database implements interfaceDatabase{
	
	
	private $pdo;
	
	
	public function getPdo() {
		return $this->pdo;
	}
		
	
	//Methods
	
	public function __construct() {
		return $this->connect();
	}
	
		
	/** 
	 * @see \ProductRepository\API\Kernel\interfaceDatabase::disconnect()
	 */
	public function disconnect() {
		$this->pdo = null;
	}

	
	/**
	 * @see interfaceDatabase::getParameters()
	 */
	public function getDatabaseParameters($name){
		$configFile = $_SERVER["DOCUMENT_ROOT"] .$_SERVER['CONFIG_FILE'];

		try {
			if (file_exists($configFile)){
				$content = parse_ini_file($configFile, true);
				if (isset($content['DATABASE'])){
					$content = parse_ini_file($_SERVER["DOCUMENT_ROOT"] .'/'.$_SERVER['API_ROOT'].'/config/'.$content['DATABASE'][$name], true);
					return $content['PARAMETERS'];
				}
			}
		} 
		catch (Exception $e) {
			$response['code_error']    = $e->getCode();
			$response['message_error'] = $e->getMessage();
			$response['line']          = $e->getLine();
		}
	}


	
	
	
	
	
	//CHECK THIS WEBSITE http://whateverthing.com/blog/2014/08/28/the-heart-of-darkness/
	//SEE THE PART "Play It Again, MyISAM"
	
	/**
	 * @see \ProductRepository\API\Kernel\interfaceDatabase::connect()
	 */
	public function connect(){
		$response = array();
		
		try {
			$parameters = $this->getDatabaseParameters('xanfaber');
			$this->pdo = new \PDO(
							'mysql:host='.$parameters['HOST'].';
						     port='.$parameters['PORT'].';
					         dbname='.$parameters['BDD'].'',
					         $parameters['USER'],$parameters['PASSWORD'],
							array(\PDO::ATTR_PERSISTENT   	 => true,
								  \PDO::ATTR_ERRMODE 	 	 => \PDO::ERRMODE_EXCEPTION,
								  \PDO::ATTR_EMULATE_PREPARES => false
							));
			
			$this->pdo->exec("SET CHARACTER SET utf8");
			$response = $this->pdo;
		} 
		catch (PDOException $e) {
			
			$e = new PDOException($e->getMessage() , 301, null);
			$response['code'] 		= $e->getCode();
			$response['msgserver']  = $e->getMessage();
			$response['line']		= $e->getLine();
			$response['filename']	= $e->getFile();
			
			$now     = getdate();
			$current = $now['weekday'].' '.$now['month'].' '.$now['mday'].' '.$now['year'].' '.$now['hours'].'h'.$now['minutes'].':'.$now['seconds'].'s';
			
			$log = new Logger($_SERVER["DOCUMENT_ROOT"].$_SERVER["API_ROOT"].'/log/error_code.log');
			$log->error($current.' '.$e->getCode().' '.$e->getMessage().' '.$e->getPrevious());
			
		}
		finally{
			return $response;
		}
	}

	
	
	
	
	/**
	 * Retrieve rows depending of the SQL request given
	 * @param array $query (contains request and binding parameters)
	 * @return array $response
	 */
	public function selectSQL($query){
		$response = array();
		
		try {
			$req = $this->pdo->prepare((string)$query['request']);
			
			if(array_key_exists('bindparams', $query) == true){
				foreach($query['bindparams'] as $params){
					$reference = $params->getValue();
					$req->bindValue($params->getPlaceholders(),$reference,$params->getType());
				}
			}
			
			$req->execute();
			
			while($line = $req->fetch(\PDO::FETCH_ASSOC)){
				$response['items'][] = $line;
			}
			$req->closeCursor();
			
			if(empty($response['items']))
				$response['code'] = 2;
			else
				$response['code'] = 0;
		} 
		catch (Exception $e) {
			
			$e = new \PDOException($e->getMessage() , 102, null);
			$response['code'] 		= $e->getCode();
			$response['msgserver']  = $e->getMessage();
			$response['line']		= $e->getLine();
			$response['filename']	= $e->getFile();
			
			$now     = getdate();
			$current = $now['weekday'].' '.$now['month'].' '.$now['mday'].' '.$now['year'].' '.$now['hours'].'h'.$now['minutes'].':'.$now['seconds'].'s';

			$log = new Logger($_SERVER["DOCUMENT_ROOT"].$_SERVER["API_ROOT"].'/log/error_code.log');
			$log->error($current.' '.$e->getCode().' '.$e->getMessage().' '.$e->getPrevious());
		}
		finally {
			return $response;
		}

	}

	
	
		
	
	/**
	 * Remove rows from the database 
	 * @param array $query (contains request and binding parameters)
	 * @return array $response
	 */
	public function deleteSQL($query){
		
		$response = array();
		$response['item'] = array();
		
		try {
			
			$req = $this->pdo->prepare($query['request']);
			
			foreach($query['bindparams'] as $params){
				$reference = $params->getValue();
				$req->bindParam($params->getPlaceholders(),$reference,$params->getType());
				array_push($response['item'], $params->getValue());
			}
			
			$req->execute();
			$response['code'] = 0;
			
		} 
		catch (PDOException $e) {
			
			$e = new \PDOException($e->getMessage(), 102, null);
			
			$response['code'] 		= $e->getCode();
			$response['msgserver']  = $e->getMessage();
			$response['line']		= $e->getLine();
			$response['filename']	= $e->getFile();
			//$response['item']  		= $reference;
			
			$now     = getdate();
			$current = $now['weekday'].' '.$now['month'].' '.$now['mday'].' '.$now['year'].' '.$now['hours'].'h'.$now['minutes'].':'.$now['seconds'].'s';

			$log = new Logger($_SERVER["DOCUMENT_ROOT"].$_SERVER["API_ROOT"].'/log/error_code.log');
			$log->error($current.' '.$e->getCode().' '.$e->getMessage().' '.$e->getPrevious());
		}
		finally {
			return $response;
		}
		
	}
	
	
	
	
	/**
	 * Update one row of the database
	 * @param array $query (contains request and binding parameters)
	 * @return array $response
	 */
	
	public function updateSQL($query){
		$response = array();
		try {
				
			$req = $this->pdo->prepare($query['request']);
			
			foreach($query['bindparams'] as $params){
				$reference = $params->getValue();
				$req->bindValue($params->getPlaceholders(),$reference,$params->getType());

				if($params->getPlaceholders() == 'place_sku')
					$response['sku'] = $params->getValue();
			}
			$req->execute();
			$response['code'] = 0;

		}
		catch (PDOException $e) {
			
			$e = new \PDOException($e->getMessage(), 102, null);
			
			$response['code'] 		= $e->getCode();
			$response['msgserver']  = $e->getMessage();
			$response['line']		= $e->getLine();
			$response['filename']	= $e->getFile();

			$now     = getdate();
			$current = $now['weekday'].' '.$now['month'].' '.$now['mday'].' '.$now['year'].' '.$now['hours'].'h'.$now['minutes'].':'.$now['seconds'].'s';

			$log = new Logger($_SERVER["DOCUMENT_ROOT"].$_SERVER["API_ROOT"].'/log/error_code.log');
			$log->error($current.' '.$e->getCode().' '.$e->getMessage().' '.$e->getPrevious());
		}
		finally {
			return $response;
		}
		
		
	}
	
	
	
	
	
	/**
	 * Create a new item in the database
	 * @param array $query (contains SQL query and needed binding parameters) 
	 * @return array $response
	 */
	public function insertSQL($query){
		$response = array();
		
		try {
			$req = $this->pdo->prepare($query['request']);
			
			foreach($query['bindparams'] as $params){
				$reference = $params->getValue();
				$req->bindValue($params->getPlaceholders(),$reference,$params->getType());
			}

			$req->execute();
			
			$response['code'] = 0;
		} 
		catch (PDOException $e) {
	
			$e = new PDOException($e->getMessage(), 102, null);
			$response['code'] 		= $e->getCode();
			$response['msgserver']  = $e->getMessage();
			$response['line']		= $e->getLine();
			$response['filename']	= $e->getFile();
			
			$now     = getdate();
			$current = $now['weekday'].' '.$now['month'].' '.$now['mday'].' '.$now['year'].' '.$now['hours'].'h'.$now['minutes'].':'.$now['seconds'].'s';

			$log = new Logger($_SERVER["DOCUMENT_ROOT"].$_SERVER["API_ROOT"].'/log/error_core.log');
			$log->error($current.' '.$e->getCode().' '.$e->getMessage().' '.$e->getPrevious());
		}
		finally {
			return $response;
		}
		
	}
	
	
	
	
	
	
	/**
	 * Check whether a resource is already existing in the database
	 * @param array $query (contents SQL query and needed parameters for binding) 
	 * @return boolean $response
	 */
	
	public function _exist($query){
		
		$response = false;
		
		try {
			$req = $this->pdo->prepare($query['request']);
				
			foreach($query['bindparams'] as $params){
				$reference = $params->getValue();
				$req->bindValue($params->getPlaceholders(),$reference,$params->getType());
			}
			$req->execute();
	
			$nb = $req->rowCount();
			
			$req->closeCursor();
			
			if($nb == 0)
				$response;
			else if($nb > 0)
				$response = true;
		}
		catch (PDOException $e) {
				
			$e = new \PDOException($e->getMessage(), 102, null);
			$response['code'] 		= $e->getCode();
			$response['msgserver']  = $e->getMessage();
			$response['line']		= $e->getLine();
			$response['filename']	= $e->getFile();
			
			$now     = getdate();
			$current = $now['weekday'].' '.$now['month'].' '.$now['mday'].' '.$now['year'].' '.$now['hours'].'h'.$now['minutes'].':'.$now['seconds'].'s';
			
			$log = new Logger($_SERVER["DOCUMENT_ROOT"].$_SERVER["API_ROOT"].'/log/error_code.log');
			$log->error($current.' '.$e->getCode().' '.$e->getMessage().' '.$e->getPrevious());
		}
		finally {
			return $response;
		}
		
	}

	
	
	
}
