<?php

/**
 * @author Ibrahim Tounkara (PHP/Symfony Backend Developer)
 */

//namespace app\xanfaberAPI\src\catalog\xanfaber\model;
ini_set('memory_limit', '1024M');

use app\xanfaberAPI\kernel\AbstractAPI;
use lib\QueryBuilder\QueryBuilder;
use app\xanfaberAPI\src\catalog\xanfaber\model\Engraving;
use app\xanfaberAPI\src\catalog\xanfaber\model\Instrument;
use app\xanfaberAPI\src\catalog\xanfaber\model\Contributor;
use app\xanfaberAPI\src\catalog\xanfaber\model\TypeContributor;
use app\xanfaberAPI\src\catalog\xanfaber\model\Style;
use lib\webservice\SoapService;
use lib\Log\Logger;
use lib\Time\Timing;
use app\xanfaberAPI\kernel\Response;
use lib\Reporting\Reporting;





require_once $_SERVER["DOCUMENT_ROOT"] .'xandrieAPIs/lib/library.php';
require_once $_SERVER["DOCUMENT_ROOT"] .'xandrieAPIs/lib/autoload.php';
include_once $_SERVER["DOCUMENT_ROOT"] .'xandrieAPIs/lib/Epartner/EpartnerPublicAPI_release_candidate.php';

class EngravingAPI extends AbstractAPI{
	
	private $databaselocal;
	
	
	public function __construct(){
		$this->databaselocal = new \app\xanfaberAPI\src\catalog\xanfaber\model\Database();
	}
	
	
	
	/**
	 * @see AbstractAPI::ping()
	 */
	public function ping(){
		
		$response = new Response();
		$timing   = new Timing();
		
		$timing->start();
		
		if($this->databaselocal->getPdo() != null){
			
			$response->setResponse(date('Y-m-d H:i:s'), 'checkStatus', 'OK', array('status'=> 'Online'), $this->getStatusCode(0));
		}
		else{
						
			$response->setResponse(date('Y-m-d H:i:s'), 'checkStatus', 'KO', array('status'=> 'Offline'), $this->getStatusCode(101));
		}
		
		$timing->stop();
		$response->setTimeElapsed($timing->getFullStats());
		
		return $response->getResponse();
	}
	


	
	
	
	/**
	 * Get a collection of objects matching with the given classname
	 * @param string $classname
	 * @param array $columns (output fields)
	 * @param array $filters (filters used on the returned list - optional )
	 * @return array
	 */
	public function getCollection($classname, $columns = null, $filters = NULL){
		$collection = array();
		$table = array(
				array(
						'TABLE_NAME' 		=> $classname,
						'TABLE_ALIAS'		=> substr($classname, 0,2),
						'OUTPUT_FIELDS'		=> $columns
				)
		);
			
			
		$qb = new QueryBuilder($table);
			
		$query   = $qb->selectBuilder($filters, null);
		$results = $this->databaselocal->selectSQL($query);
		
		if(isset($results['items'])){
			foreach ($results['items'] as $items)
				array_push($collection, array_change_key_case($items,CASE_LOWER));
		}

		return $collection;
	}
	
	
	
	
	
	
	/**
	 * Edit an existing given instance.
	 * @param string $classname (name of the given class)
	 * @param array $parameters (contains columns to update and filters)
	 * @return array (associative array of a defined Response object)
	 */
	public function updateInstance($classname, $parameters){
		
		$response = new Response();
		$table = array(
				array(
						'TABLE_NAME' 		=> 'faber_catalog_'.strtolower($classname),
						'TABLE_ALIAS'		=> substr($classname, 0,2),
						'OUTPUT_FIELDS'		=> null
				)
		);
		
		$qb = new QueryBuilder($table);
		
		$dataset = $this->glueAlias(strtolower(substr($classname, 0,2)), $parameters['DATASET']);
		$filters = $this->glueAlias(strtolower(substr($classname, 0,2)), $parameters['FILTERS']);
		
		$query = $qb->updateBuilder($filters, $dataset);
		
		$result = $this->databaselocal->updateSQL($query);

		$response->setResponse(date('Y-m-d H:i:s'), 'importCatalog', 'OK', null, $this->getStatusCode($result['code']));
		
		return $response->getResponse();
	}
	
	
	
	
	
	

	
	
		
	
	
	

	/**
	 * Import a data feed into the database
	 * @return array $response (associative array of a defined Response object)
	 */
	public function importCatalog(){
		
		$timing = new Timing();
		$timing->start();
		
		$file = array();
		//$file = $this->downloadsource();
		
		$file['responseCode'] = 'OK';
		$file['result']['url']  = $_SERVER["DOCUMENT_ROOT"].'xandrieAPIs/app/xanfaberapi/web/datasources/allbrary-XMLEngravings.xml';
		
		if($file['responseCode'] != 'OK'){
			$response = $file;
		}
		else{
			$xmllist = simplexml_load_file($file['result']['url']);
			
			if($xmllist != false){
				$response = $this->loadIntoDatabase($xmllist);
			}
			else{
				$responseinstance = new Response();
				$responseinstance->setResponse(date('Y-m-d H:i:s'), 'importCatalog', 'KO', null, $this->getStatusCode(460));
				$response = $responseinstance->getResponse();
			}
		}
		
		$timing->stop();
		$response['timeElapsed'] = $timing->getFullStats();
		
		return $response;
	}
	
	
	
	
	
	
	
	/**
	 * Return a magento category reference whether
	 * an existing magento category name matches with the given style
	 * @param string $style (style of one engraving)
	 * @return string
	 */
	
	public function retrieveMagentoCategoryRef($style){
		$table = array(
				array(
						'TABLE_NAME'		=>'faber_catalog_style_map_cat_magento',
						'TABLE_ALIAS'		=>'cm',
						'OUTPUT_FIELDS'		=> array(
								'ref_magento' 		 => 'ref_magento',
						)
				)
		);
	
		$filters['cm.name'] = trim($style);
	
		$qb = new QueryBuilder($table);
	
		$query    = $qb->selectBuilder($filters,NULL);
		$response = $this->databaselocal->selectSQL($query);
		
		return $response['items'][0]['ref_magento'];
	}
	
	

	
	
	
	
	/**
	 * Call the Faber API to sell an Engraving
	 * @param integer $Id (Engraving's identifiant)
	 * @param string $unique_order_id (order_id from commercial platform)
	 * @param string $customername (Customer's identifiant) 
	 * @return array $response (associative array of a defined Response object)
	 */
	public function executeOrder($Id, $unique_order_id, $customername){
		
		$timing      		= new Timing();
		$responseInstance	= new Response();
		$soapservice 		= new SoapService(null,'faber');
		$api 		 		= new PublicAPI($soapservice->getOptions()['login'], $soapservice->getOptions()['password']); // Invoke the Faber API

		$log = new Logger($_SERVER["DOCUMENT_ROOT"].$_SERVER["API_ROOT"].'/log/xanfaber.log');
		
		$timing->start();
		
		$engraving = $this->getCollection('Engraving',null, array('en.engraving_file_ref' => $Id));
		
		$notdefined = $this->is_defined(array($Id, $unique_order_id, $customername));
		
		if($notdefined != false){
			$responseInstance->setResponse(date('Y-m-d H:i:s'), 'executeOrder', 'KO', null, $this->getStatusCode(6).': '.$notdefined);	
		}
		else{
				if($api->CheckStatus()){
					
					//Call the sale service provides by the Faber API
					$result = $api->RecordSaleWithType('France', 'eu',$customername, $Id, $engraving[0]['sale_price_euro'], $engraving[0]['sale_price_euro'], null, null, null, $unique_order_id, 'engraving');
					
					if($result['Response']['attrib']['status'] == 'ok'){
						
						$responseInstance->setResponse(date('Y-m-d H:i:s'), 'executeOrder', 'OK', array('saleID' => $result['Response']['SaleID']), $this->getStatusCode(0));
					}	
					else{
						
						$responseInstance->setResponse(date('Y-m-d H:i:s'), 'executeOrder', 'KO', null, $result['Response']['Error']['Description']);
						$log->error($responseInstance->getResponseCodeDescription().' (Product Reference :'.$unique_order_id.')',$responseInstance->getResponseCode());
					}
				}	
				else{
						
					$responseInstance->setResponse(date('Y-m-d H:i:s'), 'executeOrder', 'KO', null, $this->getStatusCode(101));
					
					$log->debug($responseInstance->getResponseCodeDescription(),101);
				}
		}
		$response = $responseInstance->getResponse();
		
		$timing->stop();
		$response['timeElapsed'] =  $timing->getFullStats();
		
		return $response;
	}
	
	
	
	
	
	/**
	 * Generate an one-time-use url access to
	 * the digital content of an engraving
	 * @param string $saleID (Sale's identifiant)
	 * @return array $response (associative array of a defined Response object)
	 */
	public function getUrlFile($saleID){
		
		$timing      		= new Timing();
		$responseInstance	= new Response();
		$soapservice 		= new SoapService(null,'faber');
		$api 		 		= new PublicAPI($soapservice->getOptions()['login'], $soapservice->getOptions()['password']);

		$log = new Logger($_SERVER["DOCUMENT_ROOT"].$_SERVER["API_ROOT"].'/log/xanfaber.log');
		
		$timing->start();
		
		$notdefined = $this->is_defined(array($saleID));
		
		if($notdefined != false){
			$responseInstance->setResponse(date('Y-m-d H:i:s'), 'getURL', 'KO', null, $this->getStatusCode(6).': '.$notdefined);
		}
		else{
			$result = $api->GetUrl($saleID);
			
			if($result['Response']['attrib']['status'] == 'ok'){
					
				$responseInstance->setResponse(date('Y-m-d H:i:s'), 'getURL', 'OK', array('Url' => $result['Response']['Url'], 'SaleID' => $saleID), $this->getStatusCode(0));	
			}
			else{
				
				$responseInstance->setResponse(date('Y-m-d H:i:s'), 'getURL', 'KO', array('SaleID' => $saleID), $result['Response']['Error']['Description']);
				$log->error($responseInstance->getResponseCodeDescription().' (Order Reference :'.$saleID.')',$responseInstance->getResponseCode());
			}
		}
		
		$timing->stop();
		
		$response = $responseInstance->getResponse();
		$response['timeElapsed'] =  $timing->getFullStats();
		
		return $response;
	}
	
	
	
	
	

	
	/**
	 * Cancel a sale process
	 * @param string $saleID (Sale's identifiant)
	 * @return array $response
	 */
	public function cancelOrder($saleID){
		
	}
	
	
	
	
	
	/**
	 * Re-launch the sale process
	 * @param string $saleID (Sale's identifiant)
	 * @return array $response
	 */
	public function reloadOrder($saleID){
		
	}
	
	
	
	
	
	
	/**
	 * Save a sale
	 * @param string $saleID
	 * @return array $response
	 */
	public function saveOrder($saleID){
		$now     = getdate();
		$current = $now['weekday'].' '.$now['month'].' '.$now['mday'].' '.$now['year'].' '.$now['hours'].'h'.$now['minutes'].':'.$now['seconds'].'s';
		error_log($current.' 		SaleID: '.$saleID.PHP_EOL,3,$_SERVER["DOCUMENT_ROOT"].$_SERVER["API_ROOT"].'/cache/Sales.txt');
	}
	
	
	
	
	
	
	/**
	 * Retrieve the complete information about an engraving
	 * @param integer (Engraving file reference)
	 */
	public function getFullEngraving($id = NULL){
		
	}
	
	
	
	
	
	
	
	
	
	/**
	 * Retrieve a data feed from a FTP Server.
	 * @return array $response (associative array of a defined Response object)
	 */
	protected function downloadsource(){
		$response = new Response();
	
		$param = getFtpParameters('faber');
	
		$connection = ftp_connect($param['host']);
		$login 		= ftp_login($connection, $param['login'], $param['password']);
		
		$log = new Logger($_SERVER["DOCUMENT_ROOT"].$_SERVER["API_ROOT"].'/log/xanfaber.log');
		
		if ((!$connection) || (!$login)) {
			
			$response->setResponse(date('Y-m-d H:i:s'), 'importCatalog', 'KO', null, $this->getStatusCode(451));
			$log->debug($response->getResponseCodeDescription(), 451);
		} 
		else {
	
			$handle = fopen($_SERVER["DOCUMENT_ROOT"].'xandrieAPIs/app/xanfaberapi/web/datasources/'. $param['datafeed'], 'w+');
	
			$upload = ftp_fget($connection, $handle, $param['datafeed'], FTP_ASCII, 0);
	
	
			// Checks loading status
			if (!$upload) {
				
				$response->setResponse(date('Y-m-d H:i:s'), 'importCatalog', 'KO', null, $this->getStatusCode(450));
				$log->debug($response->getResponseCodeDescription(), 450);
			}
			else{
				
				$response->setResponseCode('OK');
				$response->setResponseCodeDescription(null);
				$response->setResult(array('url' => $_SERVER["DOCUMENT_ROOT"].'xandrieAPIs/app/xanfaberapi/web/datasources/'. $param['datafeed']));
			}
		}
	
		ftp_close($connection);
	
		return $response->getResponse();
	}
	
	
	
	
	
	
				
	/**
	 * Insert an collection of intruments into the database
	 * @param string $string (list of intruments)
	 * @return array (contains number of inserted and missed instruments)
	 */
	protected function insertInstrument($filters, $table){
		
		$state = null;
		$qb = new QueryBuilder($table);
		
		$instrument = new Instrument();
		$instrument->setDesignation($filters['fi.designation']);
		
		$query  = $qb->insertBuilder($instrument);
		$result = $this->databaselocal->insertSQL($query);
		
		var_dump($instrument);
		if($result['code'] == 102){
			$state = 'missed';
			$log   = new Logger($_SERVER["DOCUMENT_ROOT"].$_SERVER["API_ROOT"].'/log/xanfaber.log');
			
			$log->error();
		}
		else{
			$state = 'added';
		}
		
		return $state;
	}
				

	
	
	
	
	
	/**
	 * Insert a collection of styles into the database
	 * @param array $filters
	 * @param string $table (tablename in the database)
	 * @return string $state(added or missed)
	 */
	protected function insertStyle($filters, $table){
		$state = null;
		$qb    = new QueryBuilder($table);
	
		$style = new Style();
		
		//$style->setCatMagentoRef($this->retrieveMagentoCategoryRef($item));
		$style->setDesignation($filters['fs.designation']);
		
		$query  = $qb->insertBuilder($style);
		$result = $this->databaselocal->insertSQL($query);
		
		var_dump($style);
		
		if($result['code'] == 102){
			$state = 'missed';
			$log = new Logger($_SERVER["DOCUMENT_ROOT"].$_SERVER["API_ROOT"].'/log/xanfaber.log');
			$log->error();
		}
		else{
			$state = 'added';
		}

		return $state;
	}				

	
	
	
	
	
	
	
	
	/**
	 * Insert a collection of contributors into the database
	 * @param array $filters
	 * @param string $table
	 */			
	protected function insertContributor($filters, $table){

		$state 	= null;
		$qb    	= new QueryBuilder($table);
		
		$contributor = new Contributor();
		$contributor->setFullname($filters['fc.fullname']);
		
		$query = $qb->insertBuilder($contributor);
		$result = $this->databaselocal->insertSQL($query);
		
		var_dump($contributor);
		if($result['code'] == 102){
			$state = 'missed';
			$log = new Logger($_SERVER["DOCUMENT_ROOT"].$_SERVER["API_ROOT"].'/log/xanfaber.log');
			$log->error($result['msgserver']);
		}
		else{
			$state = 'added';
		}
		
		return $state;
	}
				
				

	
	
	
	
				

	
	/**
	 * Insert an engraving into the database or update otherwise
	 * @param SimpleXMLElement $entry
	 * @return string $state ('added', 'missed' or 'updated')
	 */
	protected function insertEngraving($entry, $count){
		
		$state 	 = null;
		$filters = array();
		$table   = array(
						array(
								'TABLE_NAME'		=>'faber_catalog_engraving',
								'TABLE_ALIAS'		=>'fe',
								'OUTPUT_FIELDS'		=> null
						)
				);
		
		$qb = new QueryBuilder($table);

		
		$filters['fe.engraving_file_ref'] = trim($entry->EngravingFileId);
		$queryexist = $qb->selectBuilder($filters, null);
		
		$exist = $this->databaselocal->_exist($queryexist);
		
		if($exist == FALSE){
			$engraving = new Engraving($entry);
			
			$engraving->setSku('SHTM-FAB-'.sprintf("%05d", $count));
			
			$query  = $qb->insertBuilder($engraving);
			$result = $this->databaselocal->insertSQL($query);
			
			if($result['code'] == 0)
				$state = 'added'; //number of inserted engraving
			else
				$state = 'missed';
		}
		
		else{
			
			$engraving  = new Engraving($entry);
			$engraving->setTimestamp(date('Y-m-d H:i:s'));
			$properties = $engraving->getProperties();
			
			unset($properties['engraving_file_ref'], $properties['stamp'], $properties['status'], $properties['sku'], $properties['date_created']);
			
			$parameters = array(
							'DATASET'	=> $properties,
							'FILTERS' 	=> array('engraving_file_ref' => $filters['fe.engraving_file_ref'])
							
							);
			
			$this->updateInstance('Engraving', $parameters);
			$state = 'updated';
		}
		
		return $state;
	}
		

	

	
	
	
	
	
	/*
	 * Return a quantitative result straight from the database
	 * @see \app\xanfaberAPI\kernel\AbstractAPI::getReport()
	 */
	protected function getReport() {
		$count_engravings      = count($this->getCollection('Engraving'));
		$count_instruments     = count($this->getCollection('Instrument'));
		$count_genres 		   = count($this->getCollection('Style'));
		$count_contributor 	   = count($this->getCollection('Contributor'));
		$count_typecontributor = count($this->getCollection('TypeContributor')); 
		
		return array(
						'nb_engravings'  		=> ($count_engravings - 1),
						'nb_instruments' 		=> $count_instruments,
						'nb_genres'		 		=> $count_genres,
						'nb_contributor' 		=> $count_contributor,
						'nb_type_contributor' 	=> $count_typecontributor	
					);
	}	
	

	

	/**
	 * Bind a list of instruments to a given engraving id
	 * @param string $string (list of instruments)
	 * @param integer $id (engraving's identifiant)
	 */
	private function BindInstrumentsList($string, $id, $myinstrus){
		if($string != ''){
			
			$string = preg_replace('/\s+/','',$string);
			$list   = explode(',', $string);
	
			
			$table = array(
					array(
							'TABLE_NAME' 		=>'faber_catalog_engraving_instrument',
							'TABLE_ALIAS'		=>'ei',
							'OUTPUT_FIELDS'		=> null
					)
			);
			
			$qb = new QueryBuilder($table);
						
			foreach($list as $item){
			
				foreach ($myinstrus as $instru){
					if( $instru['designation'] == $item){
				
						$obj = new ArrayObject(
										array(
												'engraving_file_ref'	=> $id,
												'ref_instrument'		=> $instru['ref_instrument']
											)
								);
						
						
						$query  = $qb->insertBuilder($obj);
						$result = $this->databaselocal->insertSQL($query);
						
						if($result['code'] == 102){
							/*$log = new Logger($_SERVER["DOCUMENT_ROOT"].$_SERVER["API_ROOT"].'/log/xanfaber.log');
							$log->error('Binding error: Instrument n°'.$obj['ref_instrument'].' onto engraving n°'.$obj['engraving_file_ref']);*/
						}
					}	
				}
			}
		}
	}
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	/**
	 * Bind a list of genres to a given engraving id
	 * @param SimpleXMLElement $node (list of genres)
	 * @param integer $id (engraving's identifiant)
	 */
	private function BindGenresList($node, $id, $styles){
			
		if(strval($node) != ''){
			$string = str_replace(', ',',',$node);
			$list 	= explode(',',$string);
		
			$table = array(
					array(
							'TABLE_NAME' 		=>'faber_catalog_engraving_style',
							'TABLE_ALIAS'		=>'es',
							'OUTPUT_FIELDS'		=> null
					)
			);
			
			$qb = new QueryBuilder($table);
			
			foreach($list as $item){
				foreach ($styles as $style){
					if($style['designation'] == $item){
		
						$obj = new ArrayObject(
								array(
										'engraving_file_ref'	=> $id,
										'id_style'				=> $style['id_style']
									)
						);
						
						$query  = $qb->insertBuilder($obj);			
						$result = $this->databaselocal->insertSQL($query);
						
						if($result['code'] == 102){
							/*$log = new Logger($_SERVER["DOCUMENT_ROOT"].$_SERVER["API_ROOT"].'/log/xanfaber.log');
							$log->error('Binding error: Genre n°'.$obj['id_style'].' onto engraving n°'.$obj['engraving_file_ref']);*/
						}
					}
				}		
			}
		}
	}
	
	
	
	
	
	
	
	
	/**
	 * Bind a list of contributors to a given engraving id
	 * @param SimpleXMLElement $node (An engraving entry)
	 * @param int $id (Engraving's id)
	 */
	private function BindContributorsList($node, $id, $collection_typescontributors) {
		 
		//$collection_typescontributors = $this->getCollection ('TypeContributor');
		//$collection_contributors = $this->getCollection ('Contributor');
			
		foreach ($collection_typescontributors as $typecontributor){
			
			if($typecontributor['designation'] == $node->$typecontributor['designation']->getName() && $node->$typecontributor['designation'] != ''){

				$this->BindContributorToType($id, $typecontributor, $node->$typecontributor['designation']);
			}

		}
		
	}
	

	
	
	
	/**
	 * Bind a contributor to a type
	 * @param int $id (Engraving's id)
	 * @param string $string (XML node matching with contributor type)
	 */
	private function BindContributorToType($id, $typecontributor, $string){
		$contributortype = $this->getCollection('TypeContributor', null, array('tc.designation' => $string->getName()));
		
		$table = array (
					array (
						'TABLE_NAME' 	=> 'faber_catalog_engraving_contributor_type_contributor',
						'TABLE_ALIAS' 	=> 'ec',
						'OUTPUT_FIELDS' => null
					)
		);
		
		$qb = new QueryBuilder($table);
			
		if(strval($string) != ''){
			$s      = str_replace(', ',',',$string);
			$list 	= explode(',',$s);
				
			foreach($list as $name){
				$contributor = $this->getCollection('Contributor', null, array('cb.fullname' => $name));
				
				$obj = new ArrayObject(
						array(
								'engraving_file_ref'	=> $id,
								'id_contributor'		=> $contributor[0]['id_contributor'],
								'id_type_contributor'	=> $contributortype[0]['id_type_contributor']
						)
				);
				
				
				$query  = $qb->insertBuilder($obj);
				$result = $this->databaselocal->insertSQL($query);
			}
		}		
	}
	
	
	
	
	
	

	/**
	 * Return the identifiant or the reference of an entity
	 * @param string $classname
	 * @param array $filters
	 */	
	
	private function getEntityId($classname, $filters){
		$collection = array();
		$table = array(
				array(
						'TABLE_NAME' 		=> $classname,
						'TABLE_ALIAS'		=> substr($classname, 0,2),
						'OUTPUT_FIELDS'		=> $columns
				)
		);
			
			
		$qb = new QueryBuilder($table);
			
		$query    = $qb->selectBuilder($filters, null);
		
		$results  = $this->databaselocal->selectSQL($query);
		
		if(isset($results['items'])){
			foreach ($results['items'] as $items)
				array_push($collection, $items);
		}
		
		return $collection;
	}
	
	
	
	
	
	
	/**
	 * Load xml engraving entries into the database
	 * @param SimpleXMLElement $xml
	 * @return array (associative array of a defined Response object)
	 */
	private function loadIntoDatabase(&$xml){
		
		$count = 0;
		$result_instrument 	= array('missed' => 0, 'added' => 0, 'updated' => 0);
		$result_engraving 	= array('missed' => 0, 'added' => 0, 'updated' => 0);
		$result_contributor = array('missed' => 0, 'added' => 0, 'updated' => 0);
		$result_genre 		= array('missed' => 0, 'added' => 0, 'updated' => 0);
		
		

		/*$log = new Logger($_SERVER["DOCUMENT_ROOT"].$_SERVER["API_ROOT"].'/log/xanfaber.log');
		$log->info('Starting Process Import');
		$report = new Reporting();*/
		
		/*foreach ($xml as $entry){
			$count++;
			$result_instrument   = $this->checkInstrumentExisting($entry->Instruments, $result_instrument);
			$result_contributor  = $this->checkContributorExisting($entry->SongContributors, $result_contributor);
			$result_genre 	     = $this->checkGenresExisting($entry->Genres, $result_genre);
			$engravings_state    = $this->insertEngraving($entry, $count);
			
			//$result_engraving 	= $report->add($engravings_state, $result_engraving);
		}*/

		
		$collectioneng 					= $this->getCollection('Engraving'); // Get a collection of Engravings
		$collection_typescontributors 	= $this->getCollection ('TypeContributor');
		$collection_instruments  		= $this->getCollection('Instrument');
		$collection_genres      		= $this->getCollection('Style');
		//$collection_contributor			= $this->getCollection('Contributor');
		
		foreach ($xml as $entry){
			foreach ($collectioneng as $eng){
				if($eng['engraving_file_ref'] == intval($entry->EngravingFileId)){
					 	//$this->BindInstrumentsList($entry->Instruments, $eng['engraving_file_ref'], $collection_instruments);
						//$this->BindGenresList($entry->Genres, $eng['engraving_file_ref'], $collection_genres);
						$this->BindContributorsList($entry,$eng['engraving_file_ref'], $collection_typescontributors);
				}
			}
		}
			
	/*	$log->info('End Process Import');
				
		$log->info(
				"Engravings:"    .$result_engraving['added']. 	" added," .$result_engraving['missed']. 	" missed," .($result_engraving['updated'] - 1).	" updated \n".
				"Instruments:"   .$result_instrument['added']. 	" added," .$result_instrument['missed']. 	" missed \n".
				"Contributors:"  .$result_contributor['added']. " added," .$result_contributor['missed']. 	" missed \n".
				"Genres:" 		 .$result_genre['added']. 		" added," .$result_genre['missed']. 		" missed  \n"
		);*/

		$response = new Response();
		$response->setResponse(date('Y-m-d H:i:s'), 'importCatalog','OK',null, $this->getStatusCode(0));
		
		return $response->getResponse();
	}
	

	
	
	
	
	
	/**
	 * Check if a contributor already exists
	 * @param string $string (list of contributors)
	 * @param array $contriblist (contibutor list)
	 * @param array $array_status(contains number of inserted and missed inserting)
	 * @return array $array_status(contains number of inserted and missed elements)
	 */
	private function checkContributorExisting($string, $array_status){

		$report = new Reporting();
		$string = str_replace(', ',',',$string);
		$list 	= explode(',',$string);
		
		$table = array(
				array(
						'TABLE_NAME' 		=>'faber_catalog_contributor',
						'TABLE_ALIAS'		=>'fc',
						'OUTPUT_FIELDS'		=> null
				)
		);
		
		$qb = new QueryBuilder($table);
	
		foreach($list as $contributors => $contrib){
			
			$filters  = array('fc.fullname' => $contrib);
			
			$queryexist = $qb->selectBuilder($filters, null);
			$exist = $this->databaselocal->_exist($queryexist);
				
			if($exist == false){
				$state = $this->insertContributor($filters, $table);
				$array_status = $report->add($state, $array_status);
			}
		}
	
		
		return $array_status;
	}
	
	
	
	
	
	
	/**
	 * Check if an instrument already exists 
	 * @param string $table
	 * @param array $array_status
	 * @param $instrumentlist (instrument list)
	 * @return array $state(added or missed)
	 */
	private function checkInstrumentExisting($string, $array_status){
		$report = new Reporting();
		$string = preg_replace('/\s+/','',$string);
		$list 	= explode(',', $string);
		
		
		$table = array(
				array(
						'TABLE_NAME' 		=>'faber_catalog_instrument',
						'TABLE_ALIAS'		=>'fi',
						'OUTPUT_FIELDS'		=> null
				)
		);
		
		$qb  = new QueryBuilder($table);
		
		foreach($list as $instr){
			$filters = array('fi.designation' => trim($instr));
			
			$queryexist = $qb->selectBuilder($filters, null);
			$exist = $this->databaselocal->_exist($queryexist);
		
			if($exist == FALSE){
				$state = $this->insertInstrument($filters, $table);
				$array_status = $report->add($state, $array_status);
			}
		}
		return $array_status;
		
	}
	
	
	
	
	
	
	/**
	 * Check if the a genre already exists in the database
	 * @param string $string (list of styles)
	 * @return array (contains number of inserted and missed inserting)
	 */
	private function checkGenresExisting($string, $array){
		
		$report = new Reporting();
		$string = str_replace(', ',',',$string);
		$list 	= explode(',',$string);
		
		$table = array(
				array(
						'TABLE_NAME' 		=>'faber_catalog_style',
						'TABLE_ALIAS'		=>'fs',
						'OUTPUT_FIELDS'		=> null
				)
		);

		$qb = new QueryBuilder($table);
	
		foreach($list as $item){
				
			if($item != ''){
				$filters  = array('fs.designation' => trim($item));
				$queryexist = $qb->selectBuilder($filters, null);
				$exist = $this->databaselocal->_exist($queryexist);
				
				if($exist == FALSE){

					$state = $this->insertStyle($filters, $table);
					$array = $report->add($state, $array);
				}
			}
		}
			
		return $array;
	}
	
	
	
	
	
	
	
	
	/**
	 * Add a given alias to one column
	 * @param string $alias
	 * @param array $array  
	 */
	private function glueAlias($alias, $array){
		
		$fin = array();
		$keys = array();
		
		$arraycolumn = array_keys($array);
		
		foreach ($arraycolumn as $column){
			$index = $alias.'.'.$column;
		
			$fin[$index] = $array[$column];
			
			settype($index, gettype($array[$column]));
			
		}
		
		/*$arraycolumn = array_keys($array);

		foreach ($arraycolumn as $column)
			array_push($keys, $alias.'.'.$column);
		
		$fin = array_combine($keys, $array);*/

		return $fin;
				
	}
	
	
	
	/**
	 * Check if an array value is not empty, null and whether his key is defined
	 * @param unknown $list
	 * @return unknown|boolean
	 */
	protected function is_defined($list){
		foreach($list as $key=>$value){
			if(empty($list[$key]) || is_null($list[$key]) || !is_conform($value, array("\0", "\t", "\n", "\x0B", "\r", " ")))
				return $key; 
		}
		return false;	
	}
	

	
	
	
}



				
$fb = new XanfaberAPI();
//var_dump($fb->ping());
//var_dump($fb->getUrlFile('798167'));
//var_dump($fb->executeOrder(5314,'SHTM-FAB-000045'));
//var_dump($fb->downloadsource('allbrary-XMLEngravings.xml'));
var_dump($fb->importCatalog());
