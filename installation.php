<?php
ini_set('memory_limit', '1024M'); // or you could use 1G

/*******************************************************************************
 * INITIALISATION DES DONNEES
 ******************************************************************************/
$version = 1;
// version 1 : import des BDD en local et anonymisation
// version 2 : utilisation possible sur dev.wedogood.co
// version 3 : copie des fichiers wordpress site et api, configuration htaccess, wp-config etc. mise en place des repo GIT
$correspondance_version =array(
    '1' => 'l\'import des BDD en local et anonymisation',
    '2' => 'en plus de réinitialiser la BDD sur dev.wedogood.co',
    '3' => 'enfin la copie des fichiers wordpress site et api, la configuration htaccess, wp-config etc. et la mise en place des repo GIT'
);

/* Define STDIN in case if it is not already defined by PHP for some reason */
if(!defined("STDIN")) {
    define("STDIN", fopen('php://stdin','r'));
}

function get_input_dev($default=''){
    //fread(STDIN, 80)
    $temp = fgets(STDIN);
    $length = strlen($temp);

    $buffer = trim(substr($temp, 0, $length-(PHP_OS == 'WINNT' ? 2 : 1)));
    // echo $temp.'-'.$buffer;
    if ($buffer != ''){
        return $buffer;
    } else {
        return $default;
    }    
}

// demander l'adresse mail du développeur
echo "Salut à toi ! Bienvenue sur le script d'installation \n";
echo "Ce script est en version ".$version.". Il permet ".$correspondance_version[$version]." \n";
echo "\n";
echo "Quelle est ton adresse mail chez WDG : \n";
$dev_email = get_input_dev('helene@wedogood.co'); // Read up to 80 characters or a newline
echo "\n";
echo 'Parfait, nous allons anonymiser les BDD en utilisant cette adresse : ' . $dev_email . "\n\n";


/*******************************************************************************
 * CONNEXION AUX BASES
 ******************************************************************************/

echo "Quel est l'hostname (par défaut localhost): \n";
// TODO V2 : pour utilisation sur site de dév, il faudra 2 hostname différents API et site
$mysql_host = get_input_dev('localhost'); // Read up to 80 characters or a newline
echo "Nickel, quel est ton nom d'utilisateur (par défaut root) : \n";
$mysql_username = get_input_dev('root'); // Read up to 80 characters or a newline
echo "Et maintenant le mot de passe (par défaut vide) : \n";
$mysql_password = get_input_dev(''); // Read up to 80 characters or a newline
echo "Quel est le nom de la base pour l'API (par défaut test_api) : \n";
$mysql_database_api = get_input_dev('test_api'); // Read up to 80 characters or a newline
echo "Cool, maintenant celle pour le site (par défaut test_site) : \n";
$mysql_database_site = get_input_dev('test_site'); // Read up to 80 characters or a newline
echo "Voici les infos que tu as rentrées : \n";
echo $mysql_host.','.$mysql_username.','.$mysql_password.','.$mysql_database_api.','.$mysql_database_site."\n";
echo "\n";

// connection à la base api
$mysqli_api = new mysqli($mysql_host, $mysql_username, $mysql_password, $mysql_database_api);
if (mysqli_connect_errno()) {
    echo "Connect API failed: \n".mysqli_connect_error()."\n";
    exit();
}
// connection à la base du site
$mysqli_site = new mysqli($mysql_host, $mysql_username, $mysql_password, $mysql_database_site);
/* check connection */
if (mysqli_connect_errno()) {
    echo "Connect SITE failed: \n".mysqli_connect_error()."\n";
    exit();
}

// TODO V3 : créer une BDD s'il n'en existe pas ?


/*******************************************************************************
 * OPTIONNEL : SAUVEGARDES DES BASES AVANT VIDAGE
 ******************************************************************************/

// demander si on sauvegarde la BDD actuelle
function dump_db($host, $username, $password, $database, $filename){
    $command = "mysqldump --opt -h {$host} -u {$username} -p{$password} {$database} > {$filename}";
    exec($command,$output=array(),$worked);
    switch($worked){
        case 0:
            echo 'La base de données ' .$database .' a été stockée avec succès dans le chemin suivant '.getcwd().'/' .$filename ."\n";
            break;
        case 1:
            echo 'Une erreur s est produite lors de la exportation de ' .$database .' vers'.getcwd().'/' .$filename ."\n";
            exit();
            break;
        case 2:
            echo 'Une erreur d exportation s est produite, veuillez vérifier les informations suivantes : MySQL Database Name:' .$database .' MySQL User Name:' .$username .' MySQL Password:' .$password .' MySQL Host Name:' .$host ."\n";
            exit();
            break;
    }
}

echo "Souhaites-tu faire une sauvegarde des BDD  (par défaut FALSE) : \n";
$save_database = get_input_dev(FALSE); // Read up to 80 characters or a newline

if( $save_database != FALSE ){
    //Exportation de la base de données et résultat
    echo "Dump du site... \n";
    dump_db($mysql_host, $mysql_username, $mysql_password, $mysql_database_site, 'dump_site.sql');
    echo "Dump de l'api... \n";
    dump_db($mysql_host, $mysql_username, $mysql_password, $mysql_database_api, 'dump_api.sql');

}


/*******************************************************************************
 * SUPPRESSION DES TABLES
 ******************************************************************************/

// vérifier si les BDD locales sont vides, sinon les vider (à faire au moment de la sauvegarde préalable ?)
function drop_db($mysql_connect){
    $mysql_connect->query('SET foreign_key_checks = 0');
    if ($result = $mysql_connect->query("SHOW TABLES"))
    {
        while($row = $result->fetch_array(MYSQLI_NUM))
        {
            $mysql_connect->query('DROP TABLE IF EXISTS '.$row[0]);
        }
    }

    $mysql_connect->query('SET foreign_key_checks = 1');
}
echo "Suppression de toutes les tables de la base du site... \n";
drop_db($mysqli_site);
echo "Suppression de toutes les tables de la base de l'api... \n";
drop_db($mysqli_api);


/*******************************************************************************
 * IMPORT DES BASES
 ******************************************************************************/


// TODO : indiquer à l'utilisateur où prendre les BDD et quels fichiers copier à côté de son script
// echo "Nous allons récupérer les dernières sauvegardes de BDD de la prod \n\n";
echo "Nous allons maintenant importer les tables de la prod dans ta BDD \n";
echo "Avant de continuer, as-tu bien copié les fichiers wdg_site_prod.sql et apirest_prod.sql à côté de ce script ? \n";
get_input_dev('');
// en code, augmenter la variable max_allowed_packet à 524288000 
// to get the max_allowed_packet
$maxp = $mysqli_api->query( 'SELECT @@global.max_allowed_packet' )->fetch_array();
echo "max_allowed_packet = ".$maxp[ 0 ]."\n";
// to set the max_allowed_packet to 500MB
$mysqli_api->query( 'SET @@global.max_allowed_packet =524288000 ');
$maxp = $mysqli_api->query( 'SELECT @@global.max_allowed_packet' )->fetch_array();
echo "Augmentation à ".$maxp[ 0 ]."\n";
echo "\n";

// nom des dumps
$filename_api = 'apirest_prod.sql';
$filename_site = 'wdg_site_prod.sql';


//Import des bases de données via mysql dans les BDD locales
function import_db($host, $username, $password, $database, $filename){
    $cmd = "mysql -h {$host} -u {$username} -p{$password} {$database} < {$filename}";

    $arr_out = array();
    unset($return);
    exec($cmd, $arr_out, $return);

    if($return !== 0) {
        echo "mysql for {$host} : {$database} failed with a return code of {$return}\n\n";
        print_r($arr_out);
    }
}

// à décommenter pour faire l'import, 
echo "La suite est un peu longue, il va te falloir patienter, et peut-être rentrer à nouveau ton mot de passe \n";
echo "Import des tables dans la base du site... \n";
import_db($mysql_host, $mysql_username, $mysql_password, $mysql_database_site, $filename_site);
echo "Import des tables dans la base de l'api... \n";
import_db($mysql_host, $mysql_username, $mysql_password, $mysql_database_api, $filename_api);

/*******************************************************************************
 * ANONYMISATION ET NETTOYAGE DES BASES
 ******************************************************************************/

$dev_prenom = substr($dev_email, 0, strpos($dev_email, '@'));

function execute_query_error($mysql_connect, $query, $explain, $multi = False){
    if (!$multi){            
        echo $explain.$query."\n";
        $result = $mysql_connect->query($query);

    }else{
        echo $explain."\n";
        $result = $mysql_connect->multi_query($query);
        while ($mysql_connect->next_result()) // flush multi_queries
        {
            if (!$mysql_connect->more_results()) break;
        }
    }
    if (!$result) {
        echo " Erreur : ".$mysql_connect->errno." ".$mysql_connect->error."\n";
    }
}


/*
--------------Champs à anonymiser dans l'idéal :
wdgrestapi1524_entity_user
wdgrestapi1524_entity_organization
wdgrestapi1524_entity_investment
wdgrestapi1524_entity_poll_answer
wdgrestapi1524_entity_project_draft
wpwdg_users
wpwdg_edd_customers
wpwdg_usermeta (attention il y a des mélanges de données, bien penser à faire nickname et orga_contact_email)

    email --> mail du développeur + id_site de l'user
    prénom / nom --> François-Xavier Coquen de Lecourtois avec id_site de l'user
    téléphone --> 0240000000
    iban / bic --> FR7642559000011234567890121 / CCOPFRCP
    contact_if_deceased --> Georges Abitbol 
*/

execute_query_error($mysqli_site, 'UPDATE wpwdg_users SET user_email= CONCAT(CONCAT("'.$dev_prenom.'+", ID), "@wedogood.co")', "anonymization des mails utilisateurs : ");
execute_query_error($mysqli_site, 'UPDATE wpwdg_users SET user_nicename= CONCAT("FX", ID)', "anonymization des noms d'utilisateurs : ");
execute_query_error($mysqli_site, 'UPDATE wpwdg_users SET display_name= CONCAT("FX Coquen de Lecourtois", ID)', "anonymization des noms d'utilisateurs : ");
execute_query_error($mysqli_site, 'UPDATE wpwdg_users SET user_login= CONCAT("FX_login", ID)', "anonymization des noms d' utilisateurs : ");
execute_query_error($mysqli_site, 'UPDATE wpwdg_users SET user_pass= "$P$BjTuSpuSeY4uH3FIr1HD8Nz8DX5vUS0"', "anonymization des noms d' utilisateurs : ");
execute_query_error($mysqli_site, 'UPDATE wpwdg_usermeta SET meta_value= CONCAT(CONCAT("'.$dev_prenom.'+", user_id), "@wedogood.co") WHERE meta_key = "orga_contact_email"', "anonymization des meta utilisateurs : ");
execute_query_error($mysqli_site, 'UPDATE wpwdg_usermeta SET meta_value= CONCAT("FX_login", user_id) WHERE meta_key = "nickname"', "anonymization des meta utilisateurs : ");
execute_query_error($mysqli_site, 'UPDATE wpwdg_usermeta SET meta_value= CONCAT("Coquen de Lecourtois", user_id) WHERE meta_key = "last_name"', "anonymization des meta utilisateurs : ");
execute_query_error($mysqli_site, 'UPDATE wpwdg_usermeta SET meta_value= CONCAT("François-Xavier", user_id) WHERE meta_key = "first_name"', "anonymization des meta utilisateurs : ");
execute_query_error($mysqli_site, 'UPDATE wpwdg_usermeta SET meta_value= "FR7642559000011234567890121" WHERE meta_key = "bank_iban"', "anonymization des meta utilisateurs : ");
execute_query_error($mysqli_site, 'UPDATE wpwdg_usermeta SET meta_value= "CCOPFRCP" WHERE meta_key = "bank_bic"', "anonymization des meta utilisateurs : ");
execute_query_error($mysqli_site, 'UPDATE wpwdg_usermeta SET meta_value= "0240000000" WHERE meta_key = "user_mobile_phone"', "anonymization des meta utilisateurs : ");
execute_query_error($mysqli_site, 'UPDATE wpwdg_edd_customers SET email= CONCAT(CONCAT("'.$dev_prenom.'+", id), "@wedogood.co")', "anonymization des mails customers : ");
execute_query_error($mysqli_site, 'UPDATE wpwdg_edd_customers SET name= CONCAT(CONCAT("François-Xavier", id), CONCAT(" Coquen de Lecourtois", id))', "anonymization des noms customers : ");
execute_query_error($mysqli_api, 'UPDATE wdgrestapi1524_entity_user SET email= CONCAT(CONCAT("'.$dev_prenom.'+", wpref), "@wedogood.co")', "anonymization des utlisateurs API : ");
execute_query_error($mysqli_api, 'UPDATE wdgrestapi1524_entity_user SET username= CONCAT("FX_login", wpref)', "anonymization des utilisateurs API : ");
execute_query_error($mysqli_api, 'UPDATE wdgrestapi1524_entity_user SET name= CONCAT("François-Xavier", wpref)', "anonymization des utilisateurs API : ");
execute_query_error($mysqli_api, 'UPDATE wdgrestapi1524_entity_user SET surname= CONCAT("Coquen de Lecourtois", wpref)', "anonymization des utilisateurs API : ");
execute_query_error($mysqli_api, 'UPDATE wdgrestapi1524_entity_user SET contact_if_deceased= "Georges Abitbol" WHERE contact_if_deceased  != ""', "anonymization des utilisateurs API : ");
execute_query_error($mysqli_api, 'UPDATE wdgrestapi1524_entity_user SET phone_number= "0240000000" WHERE phone_number != "" ', "anonymization des utilisateurs API : ");
execute_query_error($mysqli_api, 'UPDATE wdgrestapi1524_entity_user SET bank_iban= "FR7642559000011234567890121" WHERE bank_iban  != ""', "anonymization des utilisateurs API : ");
execute_query_error($mysqli_api, 'UPDATE wdgrestapi1524_entity_user SET bank_bic= "CCOPFRCP" WHERE bank_bic  != ""', "anonymization des utilisateurs API : ");
execute_query_error($mysqli_api, 'UPDATE wdgrestapi1524_entity_organization SET email= CONCAT(CONCAT("'.$dev_prenom.'+", wpref), "@wedogood.co")', "anonymization des orgas API : ");
execute_query_error($mysqli_api, 'UPDATE wdgrestapi1524_entity_organization SET bank_iban= "FR7642559000011234567890121" WHERE bank_iban  != ""', "anonymization des orgas API : ");
execute_query_error($mysqli_api, 'UPDATE wdgrestapi1524_entity_organization SET bank_bic= "CCOPFRCP" WHERE bank_bic  != ""', "anonymization des orgas API : ");
execute_query_error($mysqli_api, 'UPDATE wdgrestapi1524_entity_investment SET email= CONCAT(CONCAT("'.$dev_prenom.'+", user_wpref), "@wedogood.co")', "anonymization des investissements API : ");
execute_query_error($mysqli_api, 'UPDATE wdgrestapi1524_entity_investment SET firstname= CONCAT("François-Xavier", user_wpref)', "anonymization des investissements API : ");
execute_query_error($mysqli_api, 'UPDATE wdgrestapi1524_entity_investment SET lastname= CONCAT("Coquen de Lecourtois", user_wpref)', "anonymization des investissements API : ");
execute_query_error($mysqli_api, 'UPDATE wdgrestapi1524_entity_project_draft SET email= CONCAT(CONCAT("'.$dev_prenom.'+", id), "@wedogood.co")', "anonymization des mails project_draft API : ");
execute_query_error($mysqli_api, 'UPDATE wdgrestapi1524_entity_poll_answer SET user_email= CONCAT(CONCAT("'.$dev_prenom.'+", user_id), "@wedogood.co")', "anonymization des  mails poll API : ");




    
/*Tables à supprimer sur le site :
wpwdg_bp_*
*/
$query  = "DROP TABLE wpwdg_bp_activity;";
$query .= "DROP TABLE wpwdg_bp_activity_meta;";
$query .= "DROP TABLE wpwdg_bp_follow;";
$query .= "DROP TABLE wpwdg_bp_friends;";
$query .= "DROP TABLE wpwdg_bp_groups;";
$query .= "DROP TABLE wpwdg_bp_groups_groupmeta;";
$query .= "DROP TABLE wpwdg_bp_groups_members;";
$query .= "DROP TABLE wpwdg_bp_messages_messages;";
$query .= "DROP TABLE wpwdg_bp_messages_notices;";
$query .= "DROP TABLE wpwdg_bp_messages_recipients;";
$query .= "DROP TABLE wpwdg_bp_notifications;";
$query .= "DROP TABLE wpwdg_bp_user_blogs;";
$query .= "DROP TABLE wpwdg_bp_user_blogs_blogmeta;";
$query .= "DROP TABLE wpwdg_bp_xprofile_data;";
$query .= "DROP TABLE wpwdg_bp_xprofile_fields;";
$query .= "DROP TABLE wpwdg_bp_xprofile_groups;";
$query .= "DROP TABLE wpwdg_bp_xprofile_meta";
execute_query_error($mysqli_site, $query, "Suppression des tables wpwdg_bp_* ", true);

/*Tables à vider sur le site :
wp_wdg_cache
snsf_subscribe	
*/
$query  = "TRUNCATE TABLE wp_wdg_cache;";
$query .= "TRUNCATE TABLE snsf_subscribe";
execute_query_error($mysqli_site, $query, "Vidage des tables wp_wdg_cache et snsf_subscribe ", true);
  
/*Tables à vider sur l'API :
wdgrestapi1524_entity_log
wdgrestapi1524_entity_queued_action
wdgrestapi1524_entity_email (on en laisse quelques-uns pour les tests)
*/
$query  = "TRUNCATE TABLE wdgrestapi1524_entity_log;";
$query .= "TRUNCATE TABLE wdgrestapi1524_entity_queued_action";
execute_query_error($mysqli_api, $query, "Vidage des tables wdgrestapi1524_entity_log et wdgrestapi1524_entity_queued_action ", true);

$query = 'DELETE FROM wdgrestapi1524_entity_email WHERE id < 100000';
execute_query_error($mysqli_api, $query, "Vidage partiel de la table des mails envoyés : ");


/*TODO V1 bis : Champs JSON à anonymiser plus tard :
sur l'API
champ options de wdgrestapi1524_entity_bill , 
champ data dans wdgrestapi1524_entity_investment_draft
champ metadata dans wdgrestapi1524_entity_project_draft
sur le site
edd_payment_meta dans wpwdg_postmeta 
*/
    
/*TODO V1 bis : A verifier à l'utilisation :
 faut-il aussi les changer les noms des utilisateurs dans wpwdg_bp_xprofile_data et wpwdg_edd_customers?
changement de l'admin_email et du mailserver_login dans wpwdg_options ?
changement de l'admin_email dans wdgrestapi1524_options ? et dans wdgrestapi1524_users et wdgrestapi1524_usermeta ?
*/


/*******************************************************************************
 * LIAISON AVEC LES WALLETS LW
 ******************************************************************************/

// on lie des organisations et des utilisateurs avec des wallets existants sur la sandbox LW
// A FAIRE EVOLUER POUR EN AVOIR LE PLUS POSSIBLE !
$correspondance =array(
    'ORGA12W107' => '11182',
    'ORGA10W101' => '4347'
);
foreach($correspondance as $wallet=>$id){
    execute_query_error($mysqli_site, 'UPDATE wpwdg_usermeta SET meta_value= "'.$wallet.'" WHERE meta_key = "lemonway_id" AND user_id='.$id, "liaison avec des wallets de la sandbox LW : ");
    execute_query_error($mysqli_api, 'UPDATE wdgrestapi1524_entity_organization SET gateway_list=\'{"lemonway":"'.$wallet.'"}\' WHERE wpref = '.$id, "liaison avec des wallets de la sandbox LW : ");
    execute_query_error($mysqli_api, 'UPDATE wdgrestapi1524_entity_organization SET name= CONCAT(name, "_wallet") WHERE wpref = '.$id, "liaison avec des wallets de la sandbox LW : ");
}

// liaison des wallets utilisateurs
$correspondance =array(
    'USERW237' => '18198',
    'USERW217' => '18253',
    'USERW1' => '14830',
    'USERW234' => '14898',
    'USERW236' => '23579',
    'USERW244' => '68',
    'USERW245' => '12033',
    'USERW243' => '18'
);
foreach($correspondance as $wallet=>$id){
    execute_query_error($mysqli_site, 'UPDATE wpwdg_usermeta SET meta_value= "'.$wallet.'" WHERE meta_key = "lemonway_id" AND user_id='.$id, "liaison avec des wallets de la sandbox LW : ");
    execute_query_error($mysqli_api, 'UPDATE wdgrestapi1524_entity_user SET gateway_list=\'{"lemonway":"'.$wallet.'"}\' WHERE wpref = '.$id, "liaison avec des wallets de la sandbox LW : ");
    execute_query_error($mysqli_api, 'UPDATE wdgrestapi1524_entity_user SET name= CONCAT(name, "_wallet") WHERE wpref = '.$id, "liaison avec des wallets de la sandbox LW : ");
}



/*******************************************************************************
 * INSTALLATION ET CONFIGURATION WORDPRESS
 ******************************************************************************/


// TODO V3 : httpd.conf http://wiki.wedogood.co/doku.php?id=private:plateforme:dev:site:installation#fichier_de_configuration_apache_httpdconf

// TODO V3 : configuration des hosts http://wiki.wedogood.co/doku.php?id=private:plateforme:dev:site:installation#fichier_de_configuration_des_hosts

// TODO V3 : http://wiki.wedogood.co/doku.php?id=private:plateforme:dev:site:installation#edition_de_la_base_de_donnees
// TODO V3 : pour l'api aussi http://wiki.wedogood.co/doku.php?id=private:plateforme:dev:site:installation#installation_de_l_api_wordpress

// TODO V3 htaccess http://wiki.wedogood.co/doku.php?id=private:plateforme:dev:site:installation#editer_le_fichier_htaccess

// TODO V3 : maj wp-config.php http://wiki.wedogood.co/doku.php?id=private:plateforme:dev:site:installation#editer_le_fichier_wp-configphp
// pour l'api aussi http://wiki.wedogood.co/doku.php?id=private:plateforme:dev:site:installation#installation_de_l_api_wordpress

$mysqli_api->close();
$mysqli_site->close();


/*******************************************************************************
 * INSTALLATION ET CONFIGURATION repo GIT
 ******************************************************************************/

// TODO V3 : checkout git pour api, plugins et theme ? http://wiki.wedogood.co/doku.php?id=private:plateforme:dev:site:installation#mise_en_place_de_git

