<?php

include_once( 'extension/connectezsugar/scripts/genericfunctions.php' );

class SugarSynchro
{
	/*
	 * CONSTANTES
	 */
	const LOGFILE = "var/log/SugarSynchro";
	const INIPATH = "extension/connectezsugar/settings/";
	const INIFILE = "sugarcrm.ini.append.php";
	const MAPPINGINIFILE = "mappingezsugar.ini.append.php";
	
	/*
	 * PROPRIÉTÉS
	 */
	// STATIQUES
	private static $definition = array();
	private static $properties_list;
	private static $parameters_per_function;
	private static $inidata_list;
	protected static $inidata = array();
	protected static $mappingdata_list;
	protected static $logger;
	
	// D'INSTANCE
	protected $properties = array();
	protected $sugarConnector;
	protected $sugar_session;
	protected $mappingdata = array();
	
	
	/*
	 * MÉTHODES STATIQUES
	 */
	
	/*
	 * instancie un nouveau objet de cette class
	 * @return object[SugarSynchro]
	 */
	public static function instance($properties = array())
	{
		self::initLogger();
		self::definition();
		self::getIniData();
		
		$instance = new SugarSynchro();
		
		if(count($properties) > 0)
		{
			$instance->init($properties);
		}
		
		// connexion à SUGAR
		// @TODO : user et mdp pour login sont ecrites en clais dans le fichier sugarcrm.ini
		// chercher une autre façon de stockage plus securisé ?
		$instance->sugarConnector = new SugarConnector();
		$instance->sugar_session = $instance->sugarConnector->login();
		if( $instance->sugar_session === false )
		{
			$error = "instance() : sugarConnector->login() renvoie FALSE ! regarder le log SugarConnector.log";
			self::$logger->writeTimedString($error);
			$connector_error = "SugarConnector ERROR : " . $instance->sugarConnector->lastLogContent(true);
			self::$logger->writeTimedString($connector_error);
			return false;
		}
		
		return $instance;
	}
	
	public static function definition()
	{
		// inidata_list ***
		$inidata_list = array(	'mapping_names'			=> array( 'block' => "Mapping", 'var' => "mapping_names" ),
								'mapping_identifiers'	=> array( 'block' => "Mapping", 'var' => "mapping_identifiers" ),
								'exclude_fields'		=> array( 'block' => "Mapping", 'var' => "exclude_fields" ),
								'mapping_types'			=> array( 'block' => "Mapping", 'var' => "mapping_types" ),
								'prefixRemove'			=> array( 'block' => "Names", 'var' => "prefixRemove" ),
								'prefixString'			=> array( 'block' => "Names", 'var' => "prefixString" ),
								'modulesListToSynchro'	=> array( 'block' => "Synchro", 'var' => "modulesListToSynchro" ),
								'modulesListToImport'	=> array( 'block' => "Synchro", 'var' => "modulesListToImport" ),
								'modulesListToExport'	=> array( 'block' => "Synchro", 'var' => "modulesListToExport" ),
							); 
		self::$inidata_list = $inidata_list;
		
		// $mappingdata_list ***
		$mappingdata_list = array(	'sugarez'			=> array( 'var' => "sugarez" ),
									'editable_fields'	=> array( 'var' => "editable_fields" ),
									'exclude_fields'	=> array( 'var' => "exclude_fields" ),
									'include_fields'	=> array( 'var' => "include_fields" ),
									'translate_fields'	=> array( 'var' => "translate_fields" ),
									'relations_names'	=> array( 'var' => "relations_names" ),
							); 
		self::$mappingdata_list = $mappingdata_list;
		
		// properties_list ***
		$properties_list = array(	'sugar_module',
									'sugar_id',
									'sugar_attributes',
									'sugar_attributes_values',
									'sugar_module_fields',
									'class_id',
									'class_name',
									'class_identifier',
									'class_attributes'
								);
		self::$properties_list = $properties_list;
		
		// parameters_per_function *** 
		$parameters_per_function = array(	'getSugarFields' 		=> array(	'sugar_module' 	=> true ),
											'getSugarFieldsValues' => array(	'sugar_module' 		=> true,
																				'sugar_id'			=> true,
																				'sugar_attributes' 	=> true
																		),
											'synchronizeFieldsNames' => array(	'sugar_module' 	=> true
																		),
											'verifyClassAttributes' => array(	'class_id' 			=> true,
																				'class_attributes' 	=> true,
																				'sugar_module'		=> true
																		),
											'getSugarModuleEntryList' => array(	'sugar_module' 	=> true),
											'getRelations' => array(			'sugar_module' 		=> true,
																				'sugar_id'			=> true,
																		),
											'checkForRelations' => array(	'sugar_module' 	=> true),
											'synchronizeFieldsValues' => array(	'class_attributes'	=> true,
																				'sugar_module' 		=> true,
																				'sugar_id' 			=> true,
																		),
										);
		self::$parameters_per_function = $parameters_per_function;
		
		
		// tableau complet *******
		$definition = array('properties_list' => $properties_list,
							'parameters_per_function' => $parameters_per_function,
							'inidata_list' => $inidata_list
							);
		self::$definition = $definition;
										
		return $definition;
	}
	
	
	public static function initLogger()
	{
		if( !is_object(self::$logger) )
			self::$logger = owLogger::CreateForAdd(self::LOGFILE . date("d-m-Y") . ".log");
	}
	
	
	/*
	 * va chercher les settings dans le fichiers self::INIFILE
	 * et les enregistre dans self::$inidata
	 * @param none
	 * @return void
	 */
	public static function getIniData()
	{
		// init du fichier de log
		self::initLogger();
		
		// load definition si ce n'est pas dèjà fait
		if(count(self::$definition) == 0)
			self::definition();
		
		// verifie si self::INIFILE existe dans self::INIPATH
	   	$initest = eZINI::exists(self::INIFILE, self::INIPATH);
		if($initest)
		{
			$ini = eZINI::instance(self::INIFILE, self::INIPATH);
			
			// recupere toutes les variables du fichier ini definie dans self::$inidata_list 
			foreach(self::$inidata_list as $name => $args)
			{
				if( $ini->hasVariable($args['block'], $args['var']) )
					self::$inidata[$name] = $ini->variable($args['block'], $args['var']);
				else
					self::$inidata[$name] = false;
			}
			
			// si une des variables n'existe pas on renvoie false et on ecrie dans le $log
			$err = 0;
			foreach( self::$inidata as $k => $var )
			{
				if( !$var )
				{
					$error = "la variable demandées : " . $k . ", n'existe pas !";
					self::$logger->writeTimedString("Erreur getIniData() : " . $error);
					$err++;
				}
			}
			
			//exit(var_dump(self::$inidata));
			
			if( $err > 0 )
				return false;
			
			unset($ini);
			return true;
		}
		else
		{
			$error = self::INIFILE . " IN " . self::INIPATH . " NON TROUVÉ !";
			self::$logger->writeTimedString("Erreur getIniData() : " . $error);
			unset($ini);
			return false;
		}
		
	}
	
	
	public static function getModuleListToSynchro()
	{
		if( !isset(self::$inidata) or count(self::$inidata == 0) )
			self::getIniData();
			
		return self::$inidata['modulesListToSynchro'];
	}
	
	
	public static function getModuleListToImport()
	{
		if( !isset(self::$inidata) or count(self::$inidata == 0) )
			self::getIniData();
			
		return self::$inidata['modulesListToImport'];
	}
	
	
	public static function getModuleListToExport()
	{
		if( !isset(self::$inidata) or count(self::$inidata == 0) )
			self::getIniData();
			
		return self::$inidata['modulesListToExport'];
	}
	
	
	/*
	 * retourne la valeur d'une propriété statique si elle existe
	 * @param $name string
	 * @return self::$name mixed or null
	 */
	public static function getStaticProperty($name)
	{
		if(isset(self::$$name))
			return self::$$name;
	}
	
	
	public static function lastLogContent()
	{
		self::initLogger();
		return self::$logger->getLogContentFromCurrentStartTime();
	}
	
	
	/*
	 * datetime format : 2012-05-24 15:18:03
	 */
	public static function setLastDateSynchro($datetime = false)
	{
		if( !$datetime )
			$datetime = date("Y-m-d H:i:s", time()-60);
		
		$inisynchro = eZINI::instance("synchro.ini.append.php", self::INIPATH);
		$lastSynchroDate = $inisynchro->setVariable('Synchro','lastSynchroDatetime', $datetime);
		$inisynchro->save(false,false,false,false,true,true,true);
		
		return $inisynchro->variable('Synchro','lastSynchroDatetime');
	}
	
	
	public static function verifieDateTime($datetime)
	{
		// init du fichier de log
		self::initLogger();
		
		// verifie le format
		// preg_match( '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$' , $datetime )
		if( !preg_match('`^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$`', $datetime) )
		{
			self::$logger->writeTimedString("verifieDateTime() : no valid format : $datetime");
			return false;
		}
			
		// verifie la validité du datetime
		if (date('Y-m-d H:i:s', strtotime($datetime)) == $datetime)
	        return true;
	    else
	    {
	    	self::$logger->writeTimedString("verifieDateTime() : no valid date : $datetime");
	    	return false;
	    }
	        
	}
	
	
	
	/*
	 * CONSTRUCTEUR
	*/
	
	protected function SugarSynchro()
	{
		
	}
	
	
	/*
	 * MÉTHODES D'INSTANCE
	 */
	
	protected function init($properties)
	{
		foreach( $properties as $name => $value )
		{
			if(in_array($name,self::$properties_list))
				$this->properties[$name] = $value;
			else
			{	
				$error = "Propriété " . $name . " non trouvé parmi les propriétés d'un objet " . get_class();
				self::$logger->writeTimedString($error);
			}
		}
	}
	
	
	
	public function getProperties()
	{
		return $this->properties;
	}
	
	public function setProperties($properties)
	{
		$this->init($properties);
	}
	
	
	public function getProperty($name)
	{
		if( isset($this->properties[$name]) )
			return $this->properties[$name];
		else
			return null;
	}
	
	
	
	public function setProperty($name,$value)
	{
		if(in_array($name,self::$properties_list))
		{
			$this->properties[$name] = $value;
		}
		else
		{	
			$error = "Propriété " . $name . " non trouvé parmi les propriétés d'un objet " . get_class();
			self::$logger->writeTimedString($error);
		}
	}
	
	
	
	protected function verifyArgsForFunction($function_name, $args)
	{
		// load definition si ce n'est pas dèjà fait
		if(count(self::$definition) == 0)
			self::definition();

		// parametres necessaires à la function
		$parameters = self::$parameters_per_function[$function_name];
		
		// verifie si on a passé le parametre $args à la function $function_name
		if(is_null($args)) // si non
		{
			// verifie properties
			foreach($parameters as $name => $required)
			{
				if( !isset($this->properties[$name]) and $required )
				{
					$error = $function_name . " : " . $name . " n'est pas reinsegné !";
					self::$logger->writeTimedString($error);
					return false;
				}
			}
		}
		else // si oui
		{
			// verifie args
			foreach($parameters as $name => $required)
			{
				if( !isset($args[$name]) and $required )
				{
					if(!isset($this->properties[$name]))
					{
						$error = $function_name . " : " . $name . " n'est pas reinsegné !";
						self::$logger->writeTimedString($error);
						return false;
					}
				}
				elseif( isset($args[$name]) )
				{
					// set property
					$this->properties[$name] = $args[$name]; //var_dump($this->properties);
				}
			}
		}
		
		return true;
		
	}
	
	
	
	public function defClassName($module_name)
	{
		if(isset(self::$inidata['mapping_names'][$module_name]))
		{
			$class_name = self::$inidata['mapping_names'][$module_name];
		}
		elseif(self::$inidata['prefixRemove'] == "true" and strpos($module_name, self::$inidata['prefixString']) !== false )
		{
			$prefixlen = strlen(self::$inidata['prefixString']);
			$class_name = substr($module_name,$prefixlen);
		}
		else
			$class_name = $module_name;
		
		$this->properties['class_name'] = $class_name;
		
		return $class_name;
	}
	
	public function defClassIdentifier($module_name, $setProperty = true)
	{
		if(isset(self::$inidata['mapping_identifiers'][$module_name]))
		{
			$class_identifier = self::$inidata['mapping_identifiers'][$module_name];
		}
		elseif( isset($this->properties['class_name']) && $setProperty )
		{
			$class_identifier = owObjectsMaster::normalizeIdentifier($this->properties['class_name']);
		}
		elseif(self::$inidata['prefixRemove'] == "true" and strpos($module_name, self::$inidata['prefixString']) !== false )
		{
			$prefixlen = strlen(self::$inidata['prefixString']);
			$class_identifier = substr($module_name,$prefixlen);
		}
		else
			$class_identifier = owObjectsMaster::normalizeIdentifier($module_name);
		
		if( $setProperty )
			$this->properties['class_identifier'] = $class_identifier;
		
		return $class_identifier;
	}
	
	
	
	/*
	 * get du mapping specifique au module
	 */
	public function getMappingDataForModule($args = null)
	{
		// verifie si la fonction a les parametres necessaires à son execution
		$verify = $this->verifyArgsForFunction("getSugarFields", $args);
		if(!$verify)
			return false;
		
		$module_name = $this->properties['sugar_module'];
			
		// verifie si self::MAPPINGINIFILE existe dans self::INIPATH
	   	$initest = eZINI::exists(self::MAPPINGINIFILE, self::INIPATH);
		if($initest)
		{
			$inimap = eZINI::instance(self::MAPPINGINIFILE);
			
			if( !$inimap->hasGroup($module_name) )
			{
				$warning = "Pas de block " . $module_name . " trouvé dans " . self::MAPPINGINIFILE;
				self::$logger->writeTimedString("Warning getMappingDataForModule() : " . $warning);
				$this->mappingdata = false;
				return false;	
			}
			
			// recupere toutes les variables du fichier ini definie dans self::$mappingdata_list 
			foreach(self::$mappingdata_list as $name => $args)
			{
				if( $inimap->hasVariable($module_name, $args['var']) )
					$this->mappingdata[$name] = $inimap->variable($module_name, $args['var']);
				else
					$this->mappingdata[$name] = false;
			}
			
			$wrn = 0;
			// si une des variables n'existe on ecrie dans le $log un warning
			foreach( $this->mappingdata as $k => $var )
			{
				if( !$var )
				{
					// *** @TODO : si on veut avoir dans le log les avvertissement decommenter les deux lignes suivantes ***
					//$notice = "Pour le module " . $module_name . " la variable " . $k . ", n'est pas definie !";
					//self::$logger->writeTimedString("Notice getMappingDataForModule() : " . $notice);
					$wrn++;
				}
			}
			
			if( $wrn >= count(self::$mappingdata_list) )
			{
				$this->mappingdata = false;
				return false;
			}

			unset($inimap);
			return true;
			
		}
		else
		{
			$error = self::MAPPINGINIFILE . " IN " . self::INIPATH . " NON TROUVÉ !";
			self::$logger->writeTimedString("Erreur getMappingDataForModule() : " . $error);
			$this->mappingdata = false;
			return false;
		}
	}
	
	
	
	protected function testForMapping($module_name = null)
	{
		if(is_array($this->mappingdata) and  count($this->mappingdata) == 0)
		{
			//echo("rentre ici ");
			$testmapping = $this->getMappingDataForModule($module_name);
			//var_dump($testmapping);
		}
		else
			$testmapping = $this->mappingdata;
			
		
		return $testmapping;
	}
	
	/*
	 * A) va chercher le mapping pour le module concerné;
	 * B) applique des filtres
	 * 1er filtre :
	 * - exclude les champs SUGAR qui sont listé dans le tableau exclude_fileds[] generique pour tous les modules
	 * 2eme filtre :
	 * - include seulement les champs listé dans include_fields si defini
	 * sinon
	 * - exclude les champs listé dans exclude_fields si defini;
	 * C) definie le tableau $this->properties['sugar_attributes'].
	 * 
	 */
	protected function filterSugarFields()
	{
		$testmapping = $this->testForMapping();
		//evd($this->properties['sugar_module_fields']);
		//evd(self::$inidata['exclude_fields']);
		foreach($this->properties['sugar_module_fields'] as $modulefield)
		{
			$setAttribute = false;
			// exclude les champs listé dans 'exlude_fields' dans 'sugarcrm.ini'
			// ( exclude_fields generique pour tous les modules )
			if( !in_array($modulefield['name'], self::$inidata['exclude_fields']) )
			{
				if($testmapping)
				{	// si include_fields[] et exclude_fields[] sont definie : include_fields a la priorité
					// si 'include_fields[]' est definie pour le module seulement ces champs sont inclues
					if( is_array($this->mappingdata['include_fields']) )
					{
						if( in_array($modulefield['name'], $this->mappingdata['include_fields']) )
							$setAttribute = true;
					}
					// sinon si 'exclude_fields[]' est definie pour le module ces champs sont exclues
					elseif( is_array($this->mappingdata['exclude_fields']) )
					{
						if( !in_array($modulefield['name'], $this->mappingdata['exclude_fields']) )
							$setAttribute = true;
					}
				}
				else
					$setAttribute = true;
			}
			
			if($setAttribute)
			{
				$testSetSugarAttribute = $this->setSugarAttribute($modulefield);
				// si il y a une erreur dans setSugarAttribute() return false
				if(!$testSetSugarAttribute)
					return false;
			}
				
		}
		
		return true;
	}
	
	/* 
	 * definie un element du tableau $this->properties['sugar_attributes']
	 * avec les donnée d'un champ de module SUGAR
	 * formaté pour être enregistré sous EZ
	 * 
	 * @param $modulefield array ( tableau retourné par 'get_module_fields' => 'module_fields' )
	 * @return boolean
	 */
	protected function setSugarAttribute($modulefield)
	{
		// si le type du champ SUGAR n'est pas dans la liste des datatypes (self::$inidata['mapping_types'])
		// ecrit l'erreur dans le log et return false
		if( !isset(self::$inidata['mapping_types'][$modulefield['type']]) )
		{
			$error = $modulefield['type'] . " non trouvé dans la liste mapping_types[] in " . self::INIFILE;
			self::$logger->writeTimedString("Erreur setSugarAttribute() : " . $modulefield['name'] . " : "  . $error);
			return false;
		}
		
		if( count($modulefield['options']) > 0 )
		{
			$options = array();
			foreach( $modulefield['options'] as $option )
			{
				$options[] = array( 'id'=>$option['name'], 'name'=>$option['value'] );
			}
			
			$modulefield['options'] = $options;
		}
			
		
		$this->properties['sugar_attributes'][$modulefield['name']] = array('identifier'=> $modulefield['name'],
																			'name' 		=> $modulefield['label'],
																			'datatype'	=> self::$inidata['mapping_types'][$modulefield['type']],
																			'required'	=> (int)$modulefield['required'],
																			'options'	=> $modulefield['options'],
																			);
																			
		// si c'est une type multi_options on rajoute 'multi'=1
		if( strrpos($modulefield['type'], "multi") !== false )
			$this->properties['sugar_attributes'][$modulefield['name']]['multi'] = 1;
																			
		$testmapping = $this->testForMapping();
		if( $testmapping and isset($this->mappingdata['translate_fields'][$modulefield['name']]) )
		{
			$this->properties['sugar_attributes'][$modulefield['name']]['can_translate'] = $this->mappingdata['translate_fields'][$modulefield['name']];
		}
		
		return true;
	}
	
	
	/*
	 * checkForConnectorErrors
	 */
	public function checkForConnectorErrors($response, $queryname)
	{
		if( is_array($response) && isset($response['error']) && $response['error']['number'] !== "0" )
		{
			$connector_error = "SugarConnector ERROR : $queryname : " . $this->sugarConnector->lastLogContent(true);
			self::$logger->writeTimedString($connector_error);
			return true;
		}
		
		return false;
	}
	
	/*
	 * 
	 */
	public function getSugarModuleEntryList($args = null)
	{
		// verifie si la fonction a les parametres necessaires à son execution
		$verify = $this->verifyArgsForFunction("getSugarModuleEntryList", $args);
		if(!$verify)
			return false;

		$sugardata = $this->sugarConnector->get_entry_list($this->properties['sugar_module']);
		
		if( $this->checkForConnectorErrors($sugardata, 'get_entry_list') )
			return false;
		
		return $sugardata['data'];
		
	}
	
	
	public function getSugarModuleIdList($args = null, $offset = 0, $max_results = 99999)
	{
		// verifie si la fonction a les parametres necessaires à son execution
		$verify = $this->verifyArgsForFunction("getSugarModuleEntryList", $args);
		if(!$verify)
			return false;

		$select_fields = array('id');
		//$offset = 0;
	    // @TEST
		// $max_results = 5000;
			
		$sugardata = $this->sugarConnector->get_entry_list($this->properties['sugar_module'], $select_fields, $offset, $max_results);
		
		if( $this->checkForConnectorErrors($sugardata, 'get_entry_list') )
			return false;
		
		return $sugardata['data'];
		
	}
	
	
	public function getSugarModuleIdListFromDate($args = null, $datetime)
	{
		// verifie si la fonction a les parametres necessaires à son execution
		$verify = $this->verifyArgsForFunction("getSugarModuleEntryList", $args);
		if(!$verify)
			return false;

		$select_fields = array('id');
		$offset = 0;
	    // @TEST
		$max_results = 99999;
		
		// date_entered, date_modified # 2012-05-24 15:18:03
		$verifie_datetime = self::verifieDateTime($datetime);
		if( $verifie_datetime )
			$query = $this->properties['sugar_module'] . ".date_modified>='$datetime'";
		else
			$query = "";
		
		//$select_fields = array('id', 'name', 'date_modified');
		//self::$logger->writeTimedString($query);
		$sugardata = $this->sugarConnector->get_entry_list($this->properties['sugar_module'], $select_fields, $offset, $max_results, $query);
		//self::$logger->writeTimedString($sugardata);
		//self::$logger->writeTimedString(count($sugardata['data']));
		//exit();
		
		if( $this->checkForConnectorErrors($sugardata, 'get_entry_list') )
			return false;
		
		return $sugardata['data'];
		
	}
	
	
	public function getSugarModuleIdListFromLastSynchro()
	{
		
		$inisynchro = eZINI::instance("synchro.ini.append.php", self::INIPATH);
		$lastSynchroDate = $inisynchro->variable('Synchro','lastSynchroDatetime');
		
		return $this->getSugarModuleIdListFromDate(null,$lastSynchroDate);
	}
	
	
	
	
	/*
	 * fait une requete pour obtenir les champs d'un module SUGAR,
	 * filtre les champs selon la configuration general et specifique au module,
	 * retourne le tableau filtré $this->properties['sugar_attributes']
	 * 
	 * @param $args array ( voir definition() )
	 * @return $this->properties['sugar_attributes'] array
	 */
	public function getSugarFields($args = null)
	{
		// verifie si la fonction a les parametres necessaires à son execution
		$verify = $this->verifyArgsForFunction("getSugarFields", $args);
		if(!$verify)
			return false;
		
		$sugardata = $this->sugarConnector->get_module_fields($this->properties['sugar_module']);

		if( $this->checkForConnectorErrors($sugardata, 'get_module_fields') )
			return false;
		
		$module_fields = $sugardata['data'];
		$this->properties['sugar_module_fields'] = $module_fields; //evd($module_fields);
		
		// filtre les champs selon la configuration general et specifique au module
		$testFilterSugarFields = $this->filterSugarFields();
		if(!$testFilterSugarFields)
		{
			//exit(var_dump(self::$logger->getLogContentFromCurrentStartTime()));
			return false;
		}
		
		return $this->properties['sugar_attributes'];
		
	}
	
	
	/*
	 * ex.: $attributes_values = array('attr_1' => 'test attr 1', 'attr_2' => 'test attr 2');
	 */
	public function getSugarFieldsValues($args = null)
	{
		// verifie si la fonction a les parametres necessaires à son execution
		$verify = $this->verifyArgsForFunction("getSugarFieldsValues", $args);
		if(!$verify)
			return false;
			
		$select_fields = array_keys($this->properties['sugar_attributes']);
		$sugardata = $this->sugarConnector->get_entry($this->properties['sugar_module'], $this->properties['sugar_id'], $select_fields);
		
		if( $this->checkForConnectorErrors($sugardata, 'get_entry') )
			return false;
		
		$name_value_list = $sugardata['data'];
		
		$attributes_values = array();
		foreach($name_value_list as $item)
		{
			$current_datatype = $this->properties['sugar_attributes'][$item['name']]['datatype'];
			// si la value est vide on ne reinsegne pas l'attribut 
			if( !is_null($item['value']) and !empty($item['value']) )
				$attributes_values[$item['name']] = html_entity_decode($item['value'], ENT_QUOTES, 'UTF-8');
			// sauf si c'est une date on met la valeur à 0
			elseif( $current_datatype == "ezdate" || $current_datatype == "ezdatetime" )
				$attributes_values[$item['name']] = 0;
				
		}
		
		$this->properties['sugar_attributes_values'] = $attributes_values;
		return $this->properties['sugar_attributes_values'];
		
	}
	
	/*
	 * synchronizeFieldsNames
	 * 
	 * @param $input_array array
	 * @return $output_array array OR false
	 */
	public function synchronizeFieldsNames($input_array)
	{
		// si $input_array n'est pas un tableau on ne peut pas proceder au traitement
		if(!is_array($input_array))
		{
			$error = "synchronizeFieldsNames : \$input_array n'est pas un tableau mais : " . gettype($input_array);
			self::$logger->writeTimedString($error);
			return false;
		}
		
		// il faut que la propriété 'sugar_module' soit reinsegné !
		if( !isset($this->properties['sugar_module']) )
		{
			$error = "synchronizeFieldsNames : \$this->properties['sugar_module'] n'est pas reinsegné !";
			self::$logger->writeTimedString($error);
			return false;
		}

		// init $output_array
		$output_array = array();
		
		// si un mapping de correspondences de noms d'attributes existe pour le module on le recupere et
		// construit le tableau de sortie en nommant les attributs selon le mapping
		if($this->checkMappingForModule($this->properties['sugar_module']))
		{
			foreach( $input_array as $name => $values )
			{
				if(isset($this->mappingdata['sugarez'][$name]))
					$attr_identifier = $this->mappingdata['sugarez'][$name];
				// OPTION 2 : ne change pas l'identifier de l'attribut
				else
					$attr_identifier = $name;
				// OPTION 1 : normalise les identifiers
				//else 
					//$attr_identifier = owObjectsMaster::normalizeIdentifier( $name );
				
				$output_array[$attr_identifier] = $values;
			}
		}
		else
		{
			// OPTION 1 : definie $object_attributes après avoir normalisé les identifiants
			//$output_array = owObjectsMaster::normalizeIdentifiers( $input_array );
			
			// OPTION 2 : ne change rien au tableau sauf couper la chaine à 50 caracteres parce que EZ le fait automatiquement
			$output_array = owObjectsMaster::limitIdentifiers( $input_array );
		}
		
		
		
		//exit(var_dump($output_array));
		return $output_array;
	}
	
	
	/*
	 * synchronizeFieldsValues
	 * 
	 * @param $input_array array
	 * @return $output_array array OR false
	 */
	public function synchronizeFieldsValues($input_array, $args = null)
	{
		// verifie si la fonction a les parametres necessaires à son execution
		$verify = $this->verifyArgsForFunction("synchronizeFieldsValues", $args);
		if(!$verify)
			return false;
		
		$input_array = $this->synchronizeFieldsNames($input_array);
		if( !$input_array )
			return false; 
		
		// init $output_array
		$output_array = array();
		// tableau listant les datatypes numeriques
		$numeric_datatypes = array("ezinteger", "ezfloat", "ezprice", "ezboolean");
		// tableau listant les datatypes timestamp
		$timestamp_datatypes = array( "ezdatetime", "ezdate" );
		
		// class identifier
		$class_identifier = $this->defClassIdentifier($this->properties['sugar_module']);
		
		/*
		 * @!IMPORTANT!
		 * c'est ici que on fait des traitement pour adapter/transformer
		 * la valeur donné par SUGAR en la valeur attendu par EZ
		 * selon le datatype 
		 */
		foreach( $input_array as $name => $value )
		{
			//evd( eZContentObjectAttribute::fetchByIdentifier($this->properties['class_attributes'][$name]['identifier']) );
			$datatype = $this->properties['class_attributes'][$name]['datatype'];
			
			
			// dans le cas d'une selection multiple on transforme la valeur par la valeur attendu par fromString() de ezselectiotype
			if( $datatype == "ezselection" )
			{
				$newvalue = str_replace ( "^" , "" , $value );
				$newvalue = str_replace ( "," , "|" , $newvalue );
				$selectedIDs = eZStringUtils::explodeStr( $newvalue, '|' );
				$selectedNames = array();
				foreach( $selectedIDs as $selectID )
				{
					$selectedNames[] = owObjectsMaster::getSelectionNameById( $selectID, $class_identifier, $this->properties['class_attributes'][$name]['identifier'] );	
				}
				$newvalue = eZStringUtils::implodeStr( $selectedNames, '|' );
				$output_array[$name] = $newvalue;
			}
			// dans le cas d'un prix on formate selon la valeur attendu par fromString() de ezpricetype
			elseif( $datatype == "ezprice" )
			{
				$newvalue = $value . "|0|1";
				$output_array[$name] = $newvalue;
			}
			// dans le cas d'une relation d'objet on transforme l'ID sugar en ID ez
			// SCReloaded: Code d'update de relation field
			elseif( $datatype == "ezobjectrelation" )
			{
				// @IMPORTANT! : il nous faut le nom de la class pour determiner le remote_id de l'objet !!!
				// @TODO : pour l'instant je n'ai pas trouvé d'autre mèthode pour determiner la class de l'objet en relation
				// que un explode du nom du champ sugar !!!!!!!!
				$explode_name = explode("_",$name);
				$explode_count = count($explode_name);
				$related_class_identifier = $explode_name[$explode_count-1];
				
				$select_fields = array($name."_id_c"); 
				$sugardata = $this->sugarConnector->get_entry($this->properties['sugar_module'], $this->properties['sugar_id'], $select_fields);
				$related_sugar_id = $sugardata['data'][0]['value'];
				
				$related_remoteID = $related_class_identifier . "_" . $related_sugar_id;
				//evd($name);
				//vd($related_remoteID);
				$related_object_id = owObjectsMaster::objectIDByRemoteID($related_remoteID);
				//if( $related_class_identifier != "company" ) evd($related_object_id);
				//evd($this->properties['sugar_id']);
				
				if( $related_object_id !== false )
					$output_array[$name] = $related_object_id;
				else
					$output_array[$name] = $value;
			}
			// dans le cas d'une valeur numerique à NULL on transforme en 0
			elseif( in_array($datatype, $numeric_datatypes) )
			{
				if( is_null($value) or empty($value) )
					$output_array[$name] = "0";
				else
					$output_array[$name] = $value;
			}
			// dans le cas d'un datatype date ou datetime
			elseif( in_array($datatype, $timestamp_datatypes) )
			{
				// si la valeur est NULL on transforme en 0
				if( is_null($value) or empty($value) )
					$output_array[$name] = 0;
				// dans le cas d'une date on calcule le timestamp
				elseif( $datatype == "ezdate" )
				{
					$date_array = explode("-", $value);
					$ezdate = eZDate::create($date_array[1],$date_array[2],$date_array[0]);
					$output_array[$name] = (string)$ezdate->timeStamp();
				}
				// dans le cas d'un datetime on calcule le timestamp avec le temps (h:m:s)
				elseif( $datatype == "ezdatetime" )
				{
					$datetime_array = explode(" ", $value);
					$date_array = explode("-", $datetime_array[0]);
					$time_array = explode(":", $datetime_array[1]); 
					$ezdatetime = eZDateTime::create($time_array[0], $time_array[1], $time_array[2], $date_array[1], $date_array[2], $date_array[0]);
					$output_array[$name] = (string)$ezdatetime->timeStamp();
				}
				else
					$output_array[$name] = $value;
			}
			else
				$output_array[$name] = $value;
		}

		return $output_array;
	}
	
	
	/*
	 * Verifie si un mapping de correspondences de noms d'attributes existe pour le module
	 */
	protected function checkMappingForModule($module_name)
	{
		$testmapping = $this->testForMapping($module_name);
		
		if( $testmapping && isset($this->mappingdata['sugarez']) && is_array($this->mappingdata['sugarez']) && count($this->mappingdata['sugarez']) > 0 )
			return true;
		else
			return false;
		
	}
	
	
	
	/*
	 * Verify la coherance entre le tableu $this->properties['class_attributes'] et la structure de la class EZ
	 * @param $args (voir self::definition())
	 * @return boolean -> si tout va bien ou si il y a un erreur qui empeche le deroulement de la fonction
	 * @return $changes array -> si des attributes dans le tableau en entrée $this->properties['class_attributes'] ne sont pas trouvé parmi les attributes de la class EZ
	 */
	public function verifyClassAttributes($args = null)
	{
		// verifie si la fonction a les parametres necessaires à son execution
		$verify = $this->verifyArgsForFunction("verifyClassAttributes", $args);
		if(!$verify)
			return false;
		
		if(!eZContentClass::exists($this->properties['class_id']))
			return false;
		
		$class = eZContentClass::fetch($this->properties['class_id']);
		$data_map = $class->dataMap();
		
		$changes = array();
		
		foreach($this->properties['class_attributes'] as $attr => $value)
		{
			if( is_null( $class->fetchAttributeByIdentifier($attr,false) ) )
			{
				$error[] = "ERROR verifyClassAttributes() : " . $attr . " non trouvé parmi les attributes de la class " . $class->attribute('identifier');
				$changes[$attr] = $value;
			}
		}
		
		foreach( $data_map as $identifier => $class_attr )
		{
			if( !array_key_exists($identifier, $this->properties['class_attributes']) )
			{
				$alert[] = "ALERTE verifyClassAttributes() : " . $identifier . " non trouvé parmi les attributes du module SUGAR " . $this->properties['sugar_module'];
			}
		}
		
		if(isset($alert))
			self::$logger->writeTimedString($alert);
		
		if(isset($error))
		{
			self::$logger->writeTimedString($error);
			return $changes;
		}
		
		return true;
	}
	
	
	public function checkForRelations($args = null)
	{
		// verifie si la fonction a les parametres necessaires à son execution
		$verify = $this->verifyArgsForFunction("checkForRelations", $args);
		if(!$verify)
			return false;
		
		$this->testForMapping();
		$relations_names = $this->mappingdata['relations_names'];
		
		if( $relations_names === false || ( is_array($relations_names) && count($relations_names) == 0 )  )
		{
			self::$logger->writeTimedString("Aucun tableau relations_names[] trouvé pour le module " . $this->properties['sugar_module'] );
			return false;
		}
		
		return $relations_names;
	}
	
	
	public function getRelations($args = null)
	{
		// verifie si la fonction a les parametres necessaires à son execution
		$verify = $this->verifyArgsForFunction("getRelations", $args);
		if(!$verify)
			return false;
			
		$relations_names = $this->checkForRelations();
		self::$logger->writeTimedString( "relations pour " . $this->properties['sugar_module'] . " : " . show($relations_names) );
		
		if( $relations_names === false )
		{
			return array();
		}
		
		$relations_array = array();
		
		foreach( $relations_names as $rel_module_name => $relation_name )
		{
			$sugardata = $this->sugarConnector->get_relationships($this->properties['sugar_module'],$this->properties['sugar_id'], $rel_module_name);

			$rel_class_identifier = $this->defClassIdentifier($rel_module_name, false);
			
			if( $this->checkForConnectorErrors($sugardata, 'get_relationships') )
				$relations_array[$rel_class_identifier] = false;
			else
				$relations_array[$rel_class_identifier] = $sugardata['data'];
		}

		return $relations_array;
	}
	
	
	public function relationsArrayToRemoteId($relations_array)
	{
		$sugarrelations = array();
		
		foreach($relations_array as $related_class_identifier => $related_values)
		{
			if( count($related_values) == 0 )
				$sugarrelations[$related_class_identifier] = $related_values;
			else
			{
				foreach( $related_values as $value )
				{
					$sugarrelations[$related_class_identifier][] = $related_class_identifier . "_" . $value['id'];
				}
			}
		}
		
		return $sugarrelations;
		
	}
	
}// fin de class

?>
