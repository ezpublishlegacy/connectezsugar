<?php /*

[connexion]
#ServerUrl=nimportequoi.openwidesi-vm-recettephp.accelance.net
ServerUrl=otcp_crm.openwidesi-vm-recettephp.accelance.net
ServerPath=/soap.php
ServerNamespace=http://www.sugarcrm.com/sugarcrm
#ServerNamespace=http://www.sugarcrm.com/sugarcrm
login=admin
password=password


[Language]
defaultLanguage=fr


[Names]
# si il y a un prefix à enlever du nom de module SUGAR pour nommer une class EZ en absence de mapping
prefixRemove=false
# le prefix eventuel du nom de module SUGAR
# pas pris en compte si 'prefixRemove' est à false
prefixString=test_


# mapping des correspondances des tables SUGAR avec les objets EZ
[Mapping]
# mapping des correspondances des noms de modules SUGAR avec les noms des classes EZ
# ex.: mapping_names[nom_module_sugar]=class_name_ez
mapping_names[]
#mapping_names[test_Hotel]=TestHotel
#mapping_names[otcp_room]=Hotel
mapping_names[otcp_accommodation]=Hebergement

# mapping des correspondances des noms de modules SUGAR avec les identifiers des classes EZ
# ex.: mapping_identifiers[nom_module_sugar]=class_identifier_ez
mapping_identifiers[]
#mapping_identifiers[test_Hotel]=testhotel
#mapping_identifiers[otcp_room]=hotel
mapping_identifiers[otcp_accommodation]=accommodation

# champs des tables SUGAR (field name) à ignorer pour les objets EZ
# generique pour tous les modules
exclude_fields[]
exclude_fields[]=id
exclude_fields[]=date_entered
exclude_fields[]=date_modified
exclude_fields[]=modified_user_id
exclude_fields[]=modified_by_name
exclude_fields[]=created_by
exclude_fields[]=created_by_name
exclude_fields[]=team_id
exclude_fields[]=team_set_id
exclude_fields[]=team_count
exclude_fields[]=team_name
exclude_fields[]=assigned_user_id
exclude_fields[]=assigned_user_name


# @TODO : rajouter dans mappingezsugar.ini un tableau de correspondences specifiques à un champ particulier le cas echeant
# par exemple mapping_types[nom_du_champ]=ezdatatype
# @TODO : la liste des datatype est à completer !!!!!!!!!
# mapping des correspondances des types de champs SUGAR avec les datatype EZ
# ex.: mapping_types[sugar_field_type]=ez_datatype
mapping_types[]
# strings
mapping_types[id]=ezstring
mapping_types[name]=ezstring
mapping_types[char]=ezstring
mapping_types[varchar]=ezstring
mapping_types[text]=eztext
mapping_types[tinytext]=eztext
mapping_types[mediumtext]=eztext
mapping_types[longtext]=eztext
# booleans
mapping_types[bool]=ezboolean
# numbers
mapping_types[int]=ezinteger
mapping_types[tinyint]=ezinteger
mapping_types[smallint]=ezinteger
mapping_types[mediumint]=ezinteger
mapping_types[bigint]=ezinteger
mapping_types[decimal]=ezfloat
mapping_types[double]=ezfloat
mapping_types[float]=ezfloat
# date & time
mapping_types[date]=ezdate
mapping_types[datetime]=ezdatetime
mapping_types[time]=eztime
mapping_types[timestamp]=ezdatetime
mapping_types[year]=ezinteger
# listes
mapping_types[enum]=ezselection

[Synchro]
# liste des modules SUGAR qui sont concerné par la synchronisation
# @IMPORTANT! : le chronjob de synchronisation viens lire cette liste !!!
modulesListToSynchro[]
modulesListToSynchro[]=otcp_room


# @IMPORTANT! : pour l'instant [RemoteIdModel] n'est pas utilisé !
# le modele de RemoteId des objets EZ synchronisés avec SUGAR est "eZClassIdentifier_SugarObjectId"
[RemoteIdModel]
# liste de variables pour le model de remote_id
var_list[]
var_list[]=ez_class_identifier
var_list[]=ez_class_name
var_list[]=sugar_object_id
var_list[]=sugar_module_name
var_list[]=sugar_module_libelle

# modele de remote_id EZ en fonction de données SUGAR
# le remote_id est construit avec la concatenation consecutive des elements du tableau "remote_id_model[]"
# les valeurs possibles doivent faire partie de "var_list[]"
remote_id_model[]
remote_id_model[]=ez_class_identifier
remote_id_model[]=_
remote_id_model[]=sugar_object_id

*/ ?>
