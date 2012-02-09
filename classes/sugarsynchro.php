<?php

class SugarSynchro
{
	/*
	 * CONSTANTES
	 */
	const LOGFILE = "var/log/SugarSynchro.log";
	const INIPATH = "extension/connectezsugar/settings/";
	const INIFILE = "sugarcrm.ini.append.php";
	
	/*
	 * PROPRIÉTÉS
	 */
	// STATIQUES
	private static $definition = array();
	private static $properties_list;
	private static $parameters_per_function;
	private static $inidata_list;
	protected static $inidata = array();
	protected static $logger;
	
	// D'INSTANCE
	protected $properties = array();
	protected $sugarConnector;
	protected $sugar_session;
	
	
	/*
	 * MÉTHODES STATIQUES
	 */
	
	/*
	 * instancie un nouveau objet de cette class
	 * @return object[owObjectsMaster]
	 */
	public static function instance($properties = array())
	{
		self::$logger = owLogger::CreateForAdd(self::LOGFILE);
		self::definition();
		self::getIniData();
		
		$instance = new SugarSynchro();
		
		if(count($properties) > 0)
		{
			$instance->init($properties);
		}
		
		// connexion à SUGAR
		// @TODO : user et mdp pour login sont en dur pour l'instant
		$instance->sugarConnector = new SugarConnector();
		$instance->sugar_session = $instance->sugarConnector->login('admin','admin');
		
		return $instance;
	}
	
	public static function definition()
	{
		// inidata_list ***
		$inidata_list = array(	'mapping_tables'	=> array( 'block' => "Mapping", 'var' => "mapping_tables" ),
								'exclude_fields'	=> array( 'block' => "Mapping", 'var' => "exclude_fields" ),
								'mapping_types'		=> array( 'block' => "Mapping", 'var' => "mapping_types" ),
								'prefixRemove'		=> array( 'block' => "Names", 'var' => "prefixRemove" ),
								'prefixString'		=> array( 'block' => "Names", 'var' => "prefixString" ),
							); 
		self::$inidata_list = $inidata_list;
		
		// properties_list ***
		$properties_list = array(	'sugar_module',
									'sugar_id',
									'sugar_attributes',
									'sugar_attributes_values',
									'class_name',
									'class_attributes'
								);
		self::$properties_list = $properties_list;
		
		// parameters_per_function *** 
		$parameters_per_function = array(	'getSynchroFields' 		=> array(	'sugar_module' 	=> true
																		),
											'getSynchroFieldsValues' => array(	'sugar_module' 		=> true,
																				'sugar_id'			=> true,
																				'sugar_attributes' 	=> true
																		)
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
	
	/*
	 * va chercher les settings dans le fichiers self::INIFILE
	 * et les enregistre dans self::$inidata
	 * @param none
	 * @return void
	 */
	public static function getIniData()
	{
		// init du fichier de log
		self::$logger = owLogger::CreateForAdd(self::LOGFILE);
		
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
				self::$inidata[$name] = $ini->variable($args['block'], $args['var']);
			}
			
			// si une des variables n'existe pas on renvoie false et on ecrie dans le $log
			foreach( self::$inidata as $k => $var )
			{
				$err = 0;
				if( !$var )
				{
					$error = "la variable demandées : " . $k . ", n'existe pas !";
					self::$logger->writeTimedString("Erreur getIniData() : " . $error);
					$err++;
				}
			}
			
			if( $err > 0 )
				return false;
			
			return true;
		}
		else
		{
			$error = self::INIFILE . " IN " . self::INIPATH . " NON TROUVÉ !";
			self::$logger->writeTimedString("Erreur getIniData() : " . $error);
			return false;
		}
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
					$error = $function_name . " : " . $name . " n'est pas reinsegné !";
					self::$logger->writeTimedString($error);
					return false;
				}
				
				// set property
				$this->properties[$name] = $args[$name];
			}
		}
		
		return true;
		
	}
	
	
	
	public function defClassName($module_name)
	{
		if(self::$inidata['prefixRemove'] == "true" and strpos($module_name, self::$inidata['prefixString']) !== false )
		{
			$prefixlen = strlen(self::$inidata['prefixString']);
			$class_name = substr($module_name,$prefixlen);
		}
		
		$this->properties['class_name'] = $class_name;
		
		return $class_name;
	}
	
	/*
	 * ex.: $sugar_attributes = array(	'attr_1' => array( 'name' => 'attr_1', 'datatype' => 'ezstring', 'required' => 1 ),
	 *									'attr_2' => array( 'name' => 'attr_2', 'datatype' => 'eztext', 'required' => 0 ) );
	 */
	public function getSynchroFields($args = null)
	{
		// verifie si la fonction a les parametres necessaires à son execution
		$verify = $this->verifyArgsForFunction("getSynchroFields", $args);
		if(!$verify)
			return false;
		
		$sugardata = $this->sugarConnector->get_module_fields($this->properties['sugar_module']);
		$module_fields = $sugardata['module_fields'];
		//exit(var_dump($module_fields));
		$sugar_attributes = array();
		foreach($module_fields as $modulefield)
		{
			if( !in_array($modulefield['name'], self::$inidata['exclude_fields']) )
			{
				// $sugar_attributes[name] = array(name=>label,datatype=>type,required=>required);
				$sugar_attributes[$modulefield['name']] = array('name' 		=> $modulefield['label'],
																'datatype'	=> self::$inidata['mapping_types'][$modulefield['type']],
																'required'	=> (int)$modulefield['required']
																);
			}
				
		}
		
		$this->properties['sugar_attributes'] = $sugar_attributes;
		
		return $sugar_attributes;
		
	}
	
	/*
	 * ex.: $attributes_values = array('attr_1' => 'test attr 1', 'attr_2' => 'test attr 2');
	 */
	public function getSynchroFieldsValues($args = null)
	{
		// verifie si la fonction a les parametres necessaires à son execution
		$verify = $this->verifyArgsForFunction("getSynchroFieldsValues", $args);
		if(!$verify)
			return false;
			
		$select_fields = array_keys($this->properties['sugar_attributes']);
		$sugardata = $this->sugarConnector->get_entry($this->properties['sugar_module'], $this->properties['sugar_id'], $select_fields);
		
		$entry_list = $sugardata['entry_list'];
		$name_value_list = $entry_list[0]['name_value_list'];
		
		$attributes_values = array();
		foreach($name_value_list as $item)
		{
			$attributes_values[$item['name']] = $item['value'];
		}
		
		$this->properties['sugar_attributes_values'] = $attributes_values;
		return $this->properties['sugar_attributes_values'];
		
	}
	
	
}// fin de class

?>