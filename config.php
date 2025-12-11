<?php
$dbh = new PDO('mysql:host=medsmysql04.m2group.com.au; dbname=callcentre_intranet; charset=utf8', 'cccjoomla', 's2wrd1budsfua');
$dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

//TIMEZONE
date_default_timezone_set('Australia/Melbourne');


//CONSTANTS
// ROOT DIRECTORY - linked with header.php
$RD = $_SERVER['DOCUMENT_ROOT'] . "/sandbox/trainingportal/";
$RDh = "/sandbox/trainingportal/";
$external = "/sandbox/n-external/";
$reloginProtocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https://' : 'http://';
$relogin = $reloginProtocol . $_SERVER['SERVER_NAME'];

/*CREATE TABLE `tp_trainingClass` (
  `tp_id` int(11) NOT NULL AUTO_INCREMENT,
  `tp_code` varchar(100) NOT NULL,
  `tp_status` varchar(100) NOT NULL,
  `tp_className` varchar(200) NOT NULL,
  `tp_startDate` varchar(100) NOT NULL,
  `tp_startDateFormatted` varchar(100) NOT NULL,
  `tp_endDate` varchar(100) NOT NULL,
  `tp_endDateFormatted` varchar(100) NOT NULL,
  `tp_trainer` int(11) NOT NULL,
  `tp_notes` text NOT NULL,
  `tp_isArchived` tinyint(4) NOT NULL DEFAULT '0',
  `tp_lastupdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tp_id`),
  KEY `tp_code` (`tp_code`),
  KEY `tp_trainer` (`tp_trainer`)
) ENGINE=InnoDB AUTO_INCREMENT=1042 DEFAULT CHARSET=latin1
*/
?>



