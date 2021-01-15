<?php
ini_set('memory_limit', '1024M'); // or you could use 1G

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
echo "Salut à toi ! Quelle est ton adresse mail chez WDG : \n";
$dev_email = get_input_dev('helene@wedogood.co'); // Read up to 80 characters or a newline
echo "\n";
echo 'Parfait, nous allons anonymiser les BDD en utilisant cette adresse : ' . $dev_email . "\n\n";
echo "Quelle est l'hostname (par défaut localhost): \n";
$mysql_host = get_input_dev('localhost'); // Read up to 80 characters or a newline
echo "Nickel, quel est ton nom d'utilisateur (par défaut root) : \n";
$mysql_username = get_input_dev('root'); // Read up to 80 characters or a newline
echo "Et maintenant le mot de passe (par défaut vide) : \n";
$mysql_password = get_input_dev(''); // Read up to 80 characters or a newline
echo "Quelle est le nom de la base pour l'API (par défaut test_api) : \n";
$mysql_database_api = get_input_dev('test_api'); // Read up to 80 characters or a newline
echo "Cool, maintenant celle pour le site (par défaut test_site) : \n";
$mysql_database_site = get_input_dev('test_site'); // Read up to 80 characters or a newline
echo $mysql_host.','.$mysql_username.','.$mysql_password.','.$mysql_database_api.','.$mysql_database_site."\n";

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

// TODO : demander si on sauvegarde la BDD actuelle ? (pour plus tard) éventuellement créer une BDD s'il n'en existe pas ?
// https://www.ionos.fr/assistance/hebergement/utiliser-la-base-de-donnees-mysql-pour-un-projet-web/sauvegarder-et-restaurer-une-base-de-donnees-mysql-a-laide-de-php/
// https://phpsources.net/code/php/mysql/612_dump-sauvegarde-avec-php-d-une-base-de-donnee-mysql

// TODO vérifier si les BDD locales sont vides, sinon les vider (à faire au moment de la sauvegarde préalable ?)


// TODO : aller chercher les sauvegardes des bases sur le drive ? pour l'instant c'est à côté du scropt
// echo "Nous allons récupérer les dernières sauvegardes de BDD de la prod \n\n";



// en code, augmenter la variable max_allowed_packet à 524288000 
// to get the max_allowed_packet
$maxp = $mysqli_api->query( 'SELECT @@global.max_allowed_packet' )->fetch_array();
echo "max_allowed_packet = ".$maxp[ 0 ]."\n";
// to set the max_allowed_packet to 500MB
$mysqli_api->query( 'SET @@global.max_allowed_packet =524288000 ');
$maxp = $mysqli_api->query( 'SELECT @@global.max_allowed_packet' )->fetch_array();
echo "Augmentation à ".$maxp[ 0 ]."\n";

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
// import_db($mysql_host, $mysql_username, $mysql_password, $mysql_database_site, $filename_site);
// import_db($mysql_host, $mysql_username, $mysql_password, $mysql_database_api, $filename_api);


// ANONYMISATION ET NETTOYAGE DES BASES

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

/*TODO Champs JSON à anonymiser plus tard :
sur l'API
champ options de wdgrestapi1524_entity_bill , 
champ data dans wdgrestapi1524_entity_investment_draft
champ metadata dans wdgrestapi1524_entity_project_draft
sur le site
edd_payment_meta dans wpwdg_postmeta 
*/
    
/*TODO A verifier à l'utilisation :
 faut-il aussi les changer les noms des utilisateurs dans wpwdg_bp_xprofile_data et wpwdg_edd_customers?
changement de l'admin_email et du mailserver_login dans wpwdg_options ?
changement de l'admin_email dans wdgrestapi1524_options ? et dans wdgrestapi1524_users et wdgrestapi1524_usermeta ?
*/



$mysqli_api->close();
$mysqli_site->close();

// solution regex sur les dumps avant import abandonnée : 
// on n'a pas du tout assez de mémoire pour faire ça même si la regex marche bien et que je suis fière de moi
// $contenu_test = file_get_contents('apirest_prod.sql');
// $re = '/\b[a-zA-Z]\w*@[\da-z]+\.[a-z]+\b/';
// $result = preg_replace($re, $dev_email, $contenu_test);
// on réécrie un fichier avec le contenu anonymisé
// file_put_contents('apirest_prod_anonymous.sql', $result);

// TODO : faire le lien avec les wallets de tests LW ?

// TODO : copier tous les fichiers du WP (zip du drive ? dans dossier ?)

// TODO : checkout git pour api, plugins et theme ?


// TODO : maj wp-config.php, htaccess

