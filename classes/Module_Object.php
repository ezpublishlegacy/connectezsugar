<?php

class Module_Object {

	private $module_name = '';
	private $remote_id;
	private $schema;
	private $cli;
	private $ez_object_id;
	private $ez_object;
	private $connector;
	private $num_item;
	private $simulation;
	
	const EXCEPTION_OBJECT_INTROUVABLE = 999;
	
	public $logs = array();
	
	/**
	 * Constructor
	 */
	public function __construct( $module_name, $remote_id, $schema, $cli, $simulation, $num_item ) {
		$this->module_name     = $module_name;
		$this->remote_id       = $remote_id;
		$this->schema          = $schema;
		$this->cli             = $cli;
		$this->simulation	   = $simulation;
		$this->num_item		   = $num_item;
		$this->connector       = new SugarConnector();
	}

	public function __destruct() {
		unset( $this->connector, $this->schema, $this->ez_object );
		eZContentObject::clearCache();
	}
	
	public function delete( ) {
		$this->charge_ez_object( );
		
		$this->warning( '[' . $this->num_item . '] Suppression de ' . $this->schema->ez_class_identifier . ' #' . $this->ez_object->ID . ' - ' . $this->ez_object->Name . ' [memory=' . memory_get_usage_hr() . ']' . ( $this->simulation ? ' [SIMULATION]' : '' ) );
		if ( !$this->simulation ) {
			//$this->ez_object->removeThis( ); // add to trash
			$this->ez_object->purge(); // remove definitively
		}
	}
	
	private function charge_ez_object() {
		if (!$this->ez_object_id) {
			$this->ez_object_id = $this->get_ez_object_id( $this->schema->ez_class_identifier, $this->remote_id );
			$this->ez_object    = eZContentObject::fetch( $this->ez_object_id );
			
			//$this->notice( 'charge_ez_object ' . $this->ez_object->Name . ' [memory=' . memory_get_usage_hr() . ']' );
		}
	}

	public function create_ez_object( $attributes ) {
		$ini 			= eZINI::instance('owobjectsmaster.ini.append.php');
		$remote_id		= $this->schema->ez_class_identifier . '_' . $this->remote_id;
		$parent_node_id = $ini->variable( 'Tree', 'DefaultParentNodeID' );
		
		if ( $ini->hasVariable( 'Tree', 'ClassParentNodeID' ) ) {
			$node_ids = $ini->variable( 'Tree', 'ClassParentNodeID' );
			if ( isset( $node_ids[ $this->schema->ez_class_identifier ] ) ) {
				$parent_node_id = $node_ids[ $this->schema->ez_class_identifier ];
			}
		}
		
		$params = array(
			'creator_id'       => $ini->variable('Users', 'AdminID'),
			'section_id'       => $ini->variable('Tree', 'DefaultSectionID'),
			'class_identifier' => $this->schema->ez_class_identifier,
			'parent_node_id'   => $parent_node_id,
			'remote_id'		   => $remote_id,
			'attributes'	   => $this->get_ez_attribute_value( $attributes ),
		);
		if ( !$this->simulation ) {
			$contentObject = eZContentFunctions::createAndPublishObject( $params );
			if ($contentObject) {
				$this->ez_object    = $contentObject;
				$this->ez_object_id = $contentObject->ID;
				$this->warning( '[' . $this->num_item . '] Ajout de ' . $this->schema->ez_class_identifier . ' #' . $this->ez_object_id . ' - ' . $this->ez_object->Name . ' [memory=' . memory_get_usage_hr() . ']' . ( $this->simulation ? ' [SIMULATION]' : '' ) );
				unset( $contentObject );
			} else {
				throw new Exception( '[' . $this->num_item . '] Ajout de ' . $this->schema->ez_class_identifier . ' impossible pour remote_id=' . $remote_id);
			}
		} else {
			$this->warning( '[' . $this->num_item . '] Ajout de ' . $this->schema->ez_class_identifier . ' remote_id=' . $remote_id . ' [memory=' . memory_get_usage_hr() . ']' . ( $this->simulation ? ' [SIMULATION]' : '' ) );
		}
	}


	/**
	 * Public
	 */
	
	/* Import de toutes les relations (common / attribute) via le script de synchro */
	public function import_relations() {
		foreach ( $this->schema->get_relations( ) as $relation ) {
			$this->import_relation( $relation );
		}
	}
	/* Import des relationships uniquement via le script de synchro manuelle d'une fiche */
	private function import_relations_common() {
		foreach ( $this->schema->get_relations( ) as $relation ) {
			if ( $relation[ 'type' ] == 'relation' ) {
				$this->import_relation( $relation );
			}
		}
	}
	
	/* Import manuel d'une fiche */
	public function import( $datas ) {
		// Récupération des relations sur attributs
		$attributes_relation = $this->set_relations_attribute( );

		// On merge avec les champs
		foreach ( $attributes_relation as $k => $v ) {
			$datas[ 'data' ][ ] = array(
				'name' => $k,
				'value' => $v,
			);
		}
		/*foreach ( $datas[ 'data' ] as $k => $v ) {
			$this->warning( 'datas ' . $k . ' => ' . implode(', ', array_values($v)) );
		}*/
		// On enregistre les données dans eZ avec création d'une nouvelle version
		$this->update( $datas );
		
		// Enfin, import des relationships 
		$this->import_relations_common( );
	}

	public function update ( $datas ) {
		if ( isset( $datas[ 'data' ] ) ) {
			$remote_id    = $this->schema->ez_class_identifier . '_' . $this->remote_id;
			$object_found = eZContentObject::fetchByRemoteID( $remote_id );
			
			$attributes_list = eZContentClass::fetchAttributes($this->schema->ez_class_id);
			$data_types = array();
			foreach ( $attributes_list as $attribute ) {
				$data_types[ $attribute->Identifier ] = $attribute->DataTypeString;
			}
			unset( $attributes_list );
			
			$attributes = array( );
			foreach ( $datas [ 'data' ] as $data ) {
				if ( isset($data_types[ $data[ 'name' ] ]) ) {
					$value = $this->get_selected_value_remote( $data, $data_types[ $data[ 'name' ] ] );
					$attributes[ $data[ 'name' ] ] = $value;
					if ( $this->simulation ) {
						// En mode Simulation, on affiche plus de logs
						if ( in_array( $data_types[ $data[ 'name' ] ], array( 'ezstring', 'ezselection' ) ) ) {
							// Pour logguer des datatypes en particulier
							$this->notice( '- ' . $data_types[ $data[ 'name' ] ] . ' / ' . $data[ 'name' ] . ' => ' . $value );
						}
					}
				}
			}
			
			if ( !$object_found ) {
				// Création de l'objet
				if (count ( $attributes ) ) {
					$this->create_ez_object( $attributes );
				}
			} else {
				// Modification de l'objet
				$this->charge_ez_object( );
				
				if (count ( $attributes ) ) {
					$this->update_ez_attribute_value( $this->get_ez_attribute_value( $attributes ) );
				}
				unset( $object_found );
			}
		} else {
			throw new Exception( 'Pas de data envoyées pour ID=' . $this->remote_id );
		}
	}

	/**
	 * Private Import
	 */
	private function get_ez_object_id($ez_class_identifier, $remote_id) {
		$remote_id    = $ez_class_identifier . "_" . $remote_id;
		$ez_object_id = owObjectsMaster::objectIDByRemoteID($remote_id);
		
		if ($ez_object_id === false) {
			throw new Exception( '[' . $this->num_item . '] object_id introuvable pour remote_id=' . $remote_id, self::EXCEPTION_OBJECT_INTROUVABLE);
		}
		return $ez_object_id;
	}

	public function import_relation( $relation ) {
		
		$this->charge_ez_object( );
		
		if ( $relation[ 'type' ] == 'relation' ) {
			$this->import_relation_common( $relation );
		} else if ( $relation[ 'type' ] == 'attribute' ) {
			$this->import_relation_attribute( $relation );
		}
	}

	private function import_relation_common($relation) {
		$diff_related_ids = $this->diff_relations_common( $relation );
		//$this->notice( '[' . $this->num_item . '] Relations common : à ajouter : ' . count( $diff_related_ids[ 'to_add' ] ) . ' - à supprimer : ' . count( $diff_related_ids[ 'to_remove' ] ) );
		$i = 1;
		foreach ( $diff_related_ids[ 'to_add' ] as $related_ez_object_id ) {
			if ( !$this->simulation ) {
				if ( $this->ez_object->addContentObjectRelation( $related_ez_object_id ) === FALSE ) {
					throw new Exception( 'Erreur de eZ Publish, impossible d\'ajouter une relation entre ' . $this->schema->ez_class_identifier . ' #' . $this->ez_object_id .' et ' . $relation[ 'related_class_identifier' ] . '#' . $related_ez_object_id );
				}
			}
			$this->notice( '[' . $this->num_item . '-' . $i++ . '] Add relation between ' . $this->module_name . ' #'. $this->ez_object_id . ' ' . $this->ez_object->Name . ' and ' . $relation[ 'related_module_name' ] . ' #' . $related_ez_object_id . ( $this->simulation ? ' [SIMULATION]' : '' ) );
		}
		$i = 1;
		foreach ( $diff_related_ids[ 'to_remove' ] as $related_ez_object_id ) {
			if ( !$this->simulation ) {
				if ( $this->ez_object->removeContentObjectRelation( $related_ez_object_id ) === FALSE ) {
					throw new Exception( 'Erreur de eZ Publish, impossible de supprimer une relation entre ' . $this->schema->ez_class_identifier . ' #' . $this->ez_object_id .' et ' . $relation[ 'related_class_identifier' ] . '#' . $related_ez_object_id );
				}
			}
			$this->notice( '[' . $this->num_item . '-' . $i++ . '] Remove relation between ' . $this->module_name . ' #'. $this->ez_object_id . ' ' . $this->ez_object->Name . ' and ' . $relation[ 'related_module_name' ] . ' #' . $related_ez_object_id . ( $this->simulation ? ' [SIMULATION]' : '' ) );
		}
	}

	private function diff_relations_common( $relation ) {
		$new_related_ez_object_ids     = $this->get_related_ez_object_ids_by_crm( $relation );
		$current_related_ez_object_ids = $this->get_related_ez_object_ids_by_ez( $relation );
		return array(
			'to_add'    => array_diff( $new_related_ez_object_ids    , $current_related_ez_object_ids ),
			'to_remove' => array_diff( $current_related_ez_object_ids, $new_related_ez_object_ids     ),
		);
	}

	private function get_related_ez_object_ids_by_crm( $relation ) {
		$related_ez_object_ids = array( );
		try {
			$values = $this->connector->get_relationships($this->module_name, $this->remote_id, $relation[ 'related_module_name' ]);
		} catch ( Exception $e ) {
			throw new Exception( 'Exception du connecteur sur la liste des relations entre ' . $this->module_name . ' #' . $this->remote_id .' et ' . $relation[ 'related_module_name' ] );
		}
		if ( ! is_array( $values ) || ( isset($values['error'] ) && $values['error']['number'] !== '0' ) ) {
			throw new Exception( 'Erreur du connecteur sur la liste des relations entre ' . $this->module_name . ' #' . $this->remote_id .' et ' . $relation[ 'related_module_name' ] );
		}
		foreach ( $values[ 'data' ] as $value ) {
			$related_ez_object_ids[ ] = $this->get_ez_object_id( $relation['related_class_identifier'], $value['id'] );
		}
		return $related_ez_object_ids;
	}

	private function get_related_ez_object_ids_by_ez( $relation ) {
		$related_ez_object_ids = array( );
		$params = array(
			'object_id'     => $this->ez_object_id,
		    'load_data_map' => false,
		);
		if ( isset( $relation[ 'attribute_identifier' ] ) ) {
			$params[ 'attribute_identifier' ] = $relation[ 'attribute_identifier' ];
		}
		$related_objects = eZFunctionHandler::execute('content', 'related_objects', $params);
		foreach ( $related_objects as $related_object ) {
			if ( $related_object->ClassIdentifier == $relation['related_class_identifier'] ) {
				$related_ez_object_ids[ ] = $related_object->ID;
			}
		}
		unset( $related_objects );
		return $related_ez_object_ids;
	}

	private function import_relation_attribute( $relation ) {
		if ( $relation[ 'attribute_type' ] == 'list' ) {
			$this->import_relation_attribute_list( $relation );
		} else {
			$this->import_relation_attribute_one( $relation );
		}
	}
	
	/* On renvoie la liste des relations de type attribut */
	private function set_relations_attribute( ) {
		$attributes = array();
		foreach ( $this->schema->get_relations( ) as $relation ) {
			if ( $relation[ 'type' ] == 'attribute' ) {
				$attributes_relation = $this->set_relation_attribute( $relation );
				$attributes 		 = array_merge($attributes_relation, $attributes);
			}
		}
		return $attributes;
	}
	
	private function set_relation_attribute( $relation ) {
		if ( $relation[ 'attribute_type' ] == 'list' ) {
			return $this->set_relation_attribute_list( $relation );
		} else {
			return $this->set_relation_attribute_one( $relation );
		}
	}
	
	
	/* On renvoie l'attribut dane le cas d'une relation Liste d'objets */
	private function set_relation_attribute_list( $relation ) {
		$related_ez_object_ids = $this->get_related_ez_object_ids_by_crm( $relation );
		$attributes 		   = array(
			$relation[ 'attribute_name' ] => implode( '-', $related_ez_object_ids ),
		);
		return $this->get_ez_attribute_value( $attributes );
	}
	
	/* On renvoie l'attribut dans le cas d'une relation d'objet simple */
	private function set_relation_attribute_one( $relation ) {
		$related_ez_object_ids = $this->get_related_ez_object_ids_by_crm( $relation );
		if ( count( $related_ez_object_ids ) > 1 ) {
			$this->warning( 'Warning on va perdre des données de relation, many-many to one-many' );
		}
		if ( count( $related_ez_object_ids ) == 1 ) {
			$attribute_value = $related_ez_object_ids[ 0 ];
		} else {
			// @TODO: Ne réinitialise pas la relation, trouver comment faire
			$attribute_value = '';
		}
		$attributes = array(
			$relation[ 'attribute_name' ] => $attribute_value,
		);
		return $this->get_ez_attribute_value( $attributes );
	}

	/* Lancement de la requête de màj d'eZ */
	private function import_relation_attribute_list( $relation ) {
		$this->update_ez_attribute_value( $this->set_relation_attribute_list( $relation ), true );
	}

	/* Lancement de la requête de màj d'eZ */
	private function import_relation_attribute_one( $relation ) {
		$this->update_ez_attribute_value( $this->set_relation_attribute_one( $relation ), true );
	}
	
	private function get_ez_attribute_value( $attributes ) {
		if ( isset( $this->ez_object )) {
			$dataMap      = $this->ez_object->fetchDataMap( FALSE );
		}
		$paramsUpdate = array( );
		
		foreach ($attributes as $attribute_name => $attribute_value) {
			$current_attribute_value = null;
			if ( isset( $dataMap ) && isset( $dataMap[ $attribute_name ] ) && $dataMap[ $attribute_name ]->DataTypeString == 'ezrelation' ) {
				$current_attribute_value = $dataMap[ $attribute_name ]->toString( );
			}
			if ( ! isset( $current_attribute_value ) || $current_attribute_value != $attribute_value ) {
				$paramsUpdate[ $attribute_name ] = $attribute_value;
			}
		}
		return $paramsUpdate;
	}

	private function update_ez_attribute_value( $attributes, $check_modifications = false ) {
		
		if ( count ( $attributes ) > 0) {
			
			if ( is_null( $this->cli ) ) {
				// On log la liste exhaustive des modifs uniquement lors d'une synchro manuelle d'une fiche côté CRM
				foreach ( $attributes as $k => $v ) {
					$this->notice( 'màj ' . $k . ' => ' . $v );
				}
			}
			
			// $check_modifications : avant de vouloir mettre à jour l'objet, on vérifie que la valeur a bel et bien changé (uniquement pour les relations attributes)
			// Sinon, on met à jour
			$is_modified = $check_modifications ? $this->is_object_modified( $attributes ) : true;
			
			if ( $is_modified ) {
				if ( !$this->simulation ) {
					$return = eZContentFunctions::updateAndPublishObject( $this->ez_object, array( 'attributes' => $attributes ) );
					if ( ! $return ) {
						throw new Exception( 'Erreur de eZ Publish, impossible de mettre à jour ' . $this->schema->ez_class_identifier . '#' . $this->ez_object_id );
					}
					unset($return);
				}
				$this->notice( '[' . $this->num_item . '] Mise à jour des attributs de ' . $this->schema->ez_class_identifier . ' #' . $this->ez_object_id . ' - ' . $this->ez_object->Name . ' [memory=' . memory_get_usage_hr() . ']' . ( $this->simulation ? ' [SIMULATION]' : '' ) );
			} else {
				//$this->notice( '[' . $this->num_item . '] Attributs inchangés pour ' . $this->schema->ez_class_identifier . ' #' . $this->ez_object_id . ' - ' . $this->ez_object->Name . ' [memory=' . memory_get_usage_hr() . ']' . ( $this->simulation ? ' [SIMULATION]' : '' ) );
			}
		} else {
			//$this->notice( '[' . $this->num_item . '] Pas de modification de ' . $this->schema->ez_class_identifier . '#' . $this->ez_object_id . ' - ' . $this->ez_object->Name );
		}
	}
	
	private function is_object_modified( $attributes ) {
		// Check les modifs
		
		$return = false;
		$dataMap = $this->ez_object->dataMap( );
		foreach ( $attributes as $field_name => $new_value ) {
			$old_value = is_object( $dataMap[ $field_name ] ) ? $dataMap[ $field_name ]->toString() : null;
			if ( $new_value != $old_value ) {
				//$this->notice( 'UPDATE : ' . $field_name . ' - ' . $old_value . ' => ' . $new_value );
				$return = true;
			}
		}
		
		return $return;
	}

	// Renvoie la valeur de l'élément sélectionné dans le CRM pour être récupérée dans eZ
	private function get_selected_value_remote( $data, $datatype ) {
			
		$value = $data[ 'value' ];
			
		switch ( $datatype ) {

			case 'ezselection' :
				$new_value      = str_replace ( "^" , "" , $value );
				$new_value      = str_replace ( "," , "|" , $new_value );
				$selected_ids   = eZStringUtils::explodeStr( $new_value, '|' );
				$selected_names = array();
				foreach ( $selected_ids as $selected_id ) {
					$selected_names[ ] = owObjectsMaster::getSelectionNameById( $selected_id, $this->schema->ez_class_identifier, $data[ 'name' ] );
				}
				$new_value = eZStringUtils::implodeStr( $selected_names, '|' );
				if ($new_value == '') {
					$new_value = '|';
				}
				return $new_value;

			case 'ezboolean' :
			case 'ezinteger' :
			case 'ezfloat' :
				if ( is_null( $value ) || empty( $value ) ) {
					return '0';
				} else {
					return $value;
				}
					
			case 'ezprice' :
				return $value . '|0|1';
					
			case 'ezdate' :
				if ( is_null($value) || empty($value) ) {
					return 0;
				}
				// dans le cas d'une date on calcule le timestamp
				$date_array = explode( '-', $value );
				$ezdate     = eZDate::create( $date_array[1], $date_array[2], $date_array[0] );
				return (string)$ezdate->timeStamp();

			case 'ezdatetime' :
				// dans le cas d'un datetime on calcule le timestamp avec le temps (h:m:s)
				// si la valeur est NULL on transforme en 0
				if ( is_null($value) || empty($value) ) {
					return 0;
				}
				$datetime_array = explode( ' ', $value );
				$date_array     = explode( '-', $datetime_array[0] );
				$time_array     = explode( ':', $datetime_array[1] );
				$ezdatetime     = eZDateTime::create( $time_array[0], $time_array[1], $time_array[2], $date_array[1], $date_array[2], $date_array[0] );
				return (string)$ezdatetime->timeStamp();

			case 'ezobjectrelation' :
				$object = ezContentObject::fetchByRemoteID($value);
				if ( $object ) {
					return $object->ID;
				}
				break;
				
			default :
				return str_replace('&#039;', '\'', html_entity_decode( $value ) ); // Remplacement de l'apostrophe de Word
		}
	}

	/**
	 * EXPORT CRM
	 */

	/**
	 *
	 * Synchro Ez Publish vers CRM
	 */
	public function export() {
		$this->export_data();
	}

	private function export_data() {
		$this->charge_ez_object( );
		
		if ($this->ez_object_id) {
			$entry   = array( );
			$dataMap = $this->ez_object->fetchDataMap( FALSE );
			$this->notice( '[' . $this->num_item . '] Export de ' . $this->schema->ez_class_identifier . ' #' . $this->ez_object->ID . ' - ' . $this->ez_object->Name . ' [memory=' . memory_get_usage_hr() . ']' . ( $this->simulation ? ' [SIMULATION]' : '' ) );
			foreach ( $this->schema->editable_attributes as $field ) {
				if ( isset( $dataMap[ $field ] ) ) {
					$value = self::get_selected_value_ez($dataMap[ $field ]);
					$entry[ $field ] = utf8_encode( $value ); // Pour la prise en compte des accents côté CRM
					if ( $this->simulation ) {
						// En mode Simulation, on affiche la liste des champs envoyés au CRM 
						//$this->notice( '- ' . $field . ' -> ' . $value );
					}
				}
			}
			if ( !$this->simulation ) {
				$this->connector->set_entry( $this->module_name, $this->remote_id, $entry );
			}
		} else {
			throw new Exception( 'ez_object_id vide pour ID=' . $this->remote_id );
		}
	}

	// Renvoie la valeur de l'élément selon son datatype
	private static function get_selected_value_ez($datamap) {
		$value = $datamap->value( );

		switch ($datamap->DataTypeString) {
			case 'ezselection' :
				return (is_array($value) && isset($value[0]) ? $value[0] : false);

			case 'ezstring' :
			case 'eztext' :
			case 'ezboolean' :
			case 'ezinteger' :
			case 'ezfloat' :
				return $value;
					
			default :
				//@TODO : Si d'autres types de champs éditables, les gérer dans un case
				throw new Exception ('get_selected_value : ' . $datamap->DataTypeString . ' non traité !');
			return false;
		}
	}
	
	protected function notice( $str ) {
		$this->logs[ ] = 'NOTICE : ' . $str;
		if ( !is_null( $this->cli ) ) {
			$this->cli->notice( $str );
		}
	}
	
	protected function warning( $str ) {
		$this->logs[ ] = 'WARNING : ' . $str;
		if ( !is_null( $this->cli ) ) {
			$this->cli->warning( $str );
		}
	}
	
	protected function error( $str ) {
		$this->logs[ ] = 'ERROR : ' . $str;
		if ( !is_null( $this->cli ) ) {
			$this->cli->error( $str );
		}
	}
	
	public function check_relation( $relation ) {
	
		$this->charge_ez_object( );
	
		return $this->check_relation_common( $relation );
	}
	
	private function check_relation_common($relation) {
		$diff_related_ids = $this->diff_relations_common( $relation );
		$count_to_add = count( $diff_related_ids[ 'to_add' ] );
		$count_to_remove = count( $diff_related_ids[ 'to_remove' ] );
		$return_array = array(
			'to_add' => array(),
			'to_remove' => array(),
		);
		if ($count_to_add + $count_to_remove > 0) {
			foreach ($diff_related_ids['to_add'] as $related_ez_object_id) {
				$related_object = eZContentObject::fetch( $related_ez_object_id );
				$item = array(
					'related_module_name' => $relation[ 'related_module_name' ],
					'related_object_id' => $related_ez_object_id,
					'related_object_name' => $related_object->Name,
					'module_name' => $this->module_name,
					'object_id' => $this->ez_object->ID,
					'object_name' => $this->ez_object->Name,
					'object_remote_id' => $this->remote_id,
				);
				$return_array['to_add'][] = $item;
				$this->notice('#' . $item['related_object_id'] . ' ' . $item['related_object_name'] . ' (' . $item['related_module_name'] . ') <=> ' . '#' . $item['object_id'] . ' ' . $item['object_name'] . ' (' . $item['module_name'] . ') - ' . $item['object_remote_id']);
			}
			foreach ($diff_related_ids['to_remove'] as $ez_object_id) {
				$related_object = eZContentObject::fetch( $related_ez_object_id );
				$item = array(
					'related_module_name' => $relation[ 'related_module_name' ],
					'related_object_id' => $related_ez_object_id,
					'related_object_name' => $related_object->Name,
					'module_name' => $this->module_name,
					'object_id' => $this->ez_object->ID,
					'object_name' => $this->ez_object->Name,
					'object_remote_id' => $this->remote_id,
				);
				$return_array['to_remove'][] = $item;
				$this->notice('#' . $item['related_object_id'] . ' ' . $item['related_object_name'] . ' (' . $item['related_module_name'] . ') <=> ' . '#' . $item['object_id'] . ' ' . $item['object_name'] . ' (' . $item['module_name'] . ') - ' . $item['object_remote_id']);
			}
		}
		return $return_array;
	}
	
}
?>