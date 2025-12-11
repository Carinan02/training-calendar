<?php

/////////////////// GENERAL NOTES ///////////////////////////
// generic functions [INTRANET Jfactory]
// CLASS SESSION [checkLoginState, createRecord, createSession, createString]
// CLASS COOKIE [createFilterCookie, deleteFilterCookie, createFormCookie, deleteFormCookie, createSessionCookie, deleteSessionCookie]
// CLASS EMAIL [composeEmail]
// CLASS PROFILE [contact]
// CLASS DATECHECKER [getCalendarDate]
/////////////////////////////////////////////////////////////

/////////////////////////////////////////////////////////////
//////////////////////////////////// START OF INTRANET Jfactory//
/////////////////////////////////////////////////////////////
function getuserdetails()
{
	
	define( '_JEXEC', 1 );
	define( 'DS', DIRECTORY_SEPARATOR );
	define( 'JPATH_BASE', $_SERVER[ 'DOCUMENT_ROOT' ] );

	require_once( JPATH_BASE . DS . 'includes' . DS . 'defines.php' );
	require_once( JPATH_BASE . DS . 'includes' . DS . 'framework.php' );
	require_once( JPATH_BASE . DS . 'libraries' . DS . 'joomla' . DS . 'factory.php' );
	$mainframe =& JFactory::getApplication('site');

	$user =& JFactory::getUser();

	if(!empty($user->id)){
		
		return $user;
		
	}else{
		return false;
	}

}

function camelCase($str, array $noStrip = [])
{
        // non-alpha and non-numeric characters become spaces
        $str = preg_replace('/[^a-z0-9' . implode("", $noStrip) . ']+/i', ' ', $str);
        $str = trim($str);
        // uppercase the first character of each word
        $str = ucwords($str);
        $str = str_replace(" ", "", $str);
        $str = lcfirst($str);

        return $str;
}

function getUserInfo($dbh, $id, $field)
{
	$finderQ = "SELECT $field FROM aa_vocusdirectory LEFT JOIN aa_vocusdemographics on aa_vocusdirectory.vd_employeeid = aa_vocusdemographics.demog_employeeid WHERE vd_employeeid = :empid AND vd_access != 0";
	$finderS = $dbh->prepare($finderQ);
	$finderS->execute(array(':empid' => $id));
	$finder = $finderS->fetch(PDO::FETCH_ASSOC);
	
	return $finder[$field];
}



/////////////////////////////////////////////////////////////
//////////////////////////////////// START OF CLASS SESSION//
/////////////////////////////////////////////////////////////

class session
{
	public static function checkLoginState($dbh)
	{
		if (!isset($_SESSION))
		{
			session_start();
		}
		if (isset($_SESSION) && isset($_COOKIE['userid']) && isset($_COOKIE['token']) && isset($_COOKIE['serial']))
		{
			$query = "SELECT * FROM cmdr_sessions WHERE session_userid = :userid AND session_token = :token AND session_serial = :serial;";
			$userid = $_COOKIE['userid'];
			$token = $_COOKIE['token'];
			$serial = $_COOKIE['serial'];

			$stmt = $dbh->prepare($query);
			$stmt->execute(array(':userid' => $userid,
					 ':token' => $token,
					 ':serial' => $serial));

			$row = $stmt->fetch(PDO::FETCH_ASSOC);

			if ($row['session_userid'] > 0 && isset($_SESSION['userid']))
			{
				if ($row['session_userid'] == $_COOKIE['userid'] && $row['session_token']  == $_COOKIE['token']  && $row['session_serial'] == $_COOKIE['serial'])
				{
					if ($row['session_userid'] == $_SESSION['userid'] && $row['session_token']  == $_SESSION['token']  && $row['session_serial'] == $_SESSION['serial'])
					{
						return true;
					}
					else
					{
						session::createSession($_COOKIE['username'], $_COOKIE['userid'], $_COOKIE['token'], $_COOKIE['serial']);
						return true;
					}
				}
			}
		}
	}

	public static function createRecord($dbh, $user_username, $user_empid)
	{
		$query = "INSERT INTO cmdr_sessions (session_userid, session_token, session_serial) VALUES (:user_empid, :token, :serial);";
		$dbh->prepare("DELETE FROM cmdr_sessions WHERE session_userid= :session_userid;")->execute(array(':session_userid' => $user_empid));

		$token = session::createString(30);
		$serial = session::createString(30);

		cookie::createSessionCookie($user_username, $user_empid, $token, $serial);
		session::createSession($user_username, $user_empid, $token, $serial);

		$stmt = $dbh->prepare($query);
		$stmt->execute(array(':user_empid' => $user_empid,
				 ':token' => $token,
				 ':serial' => $serial));		
	}
		
	public static function createSession($user_username, $user_empid, $token, $serial)
	{
		if (!isset($_SESSION))
		{
			session_start();
		}
		$_SESSION['userid'] = $user_empid;
		$_SESSION['token'] = $token;
		$_SESSION['serial'] = $serial;
		$_SESSION['username'] = $user_username;
	}

	public static function createString($len)
	{
		$string = bin2hex(openssl_random_pseudo_bytes(30));
		
		return substr(str_shuffle($string), 0, 30);
	}	
}


/////////////////////////////////////////////////////////////
//////////////////////////////////// START OF CLASS COOKIE//
/////////////////////////////////////////////////////////////

class cookie 
{
	public static function createFilterCookie($cookieName, $cookieValue)
	{
		setcookie($cookieName, $cookieValue, time() + (86400) * 30, "/");
	}
	
	public static function deleteFilterCookie($cookieName)
	{
		setcookie($cookieName, '', time() - 1, "/");
	}
		
	public static function createFormCookie($cookieName, $cookieValue)
	{
		setcookie($cookieName, json_encode($cookieValue), time() + (86400) * 30, "/");
	}
	
	public static function deleteFormCookie($cookieName)
	{
		setcookie($cookieName, '', time() - 1, "/");
	}

	public static function createSessionCookie($user_username, $user_empid, $token, $serial)
	{
		setcookie('userid', $user_empid, time() + (86400) * 30, "/");
		setcookie('username', $user_username, time() + (86400) * 30, "/");
		setcookie('token', $token, time() + (86400) * 30, "/");
		setcookie('serial', $serial, time() + (86400) * 30, "/");
	}
	
	public static function deleteSessionCookie()
	{
		setcookie('userid', '', time() - 1, "/");
		setcookie('username', '', time() - 1, "/");
		setcookie('token', '', time() - 1, "/");
		setcookie('serial', '', time() - 1, "/");
		session_destroy();
	}
}


/////////////////////////////////////////////////////////////
//////////////////////////////////// START OF CLASS EMAIL ///
/////////////////////////////////////////////////////////////

class email 
{
	public static function composeEmail($random_hash, $email_to, $specific_subject, $target_path, $target_name, $message_content)
	{	
			
		$content = "<div class=WordSection1>\n";
		$content .= "<p class=MsoNormal>&nbsp;</p>\n";
		$content .= "<div align=center>\n";
		$content .= "<table class=MsoNormalTable border=0 cellspacing=0 cellpadding=0 width=689 style='width:516.9pt;border-collapse:collapse'>\n";
		$content .= "<tr style='height:59.2pt'>\n";
		$content .= "<td width=689 style='width:516.9pt;border:solid #A6E6FF 1.0pt;border-bottom: none;background:#A6E6FF;padding:0cm 5.4pt 0cm 5.4pt;height:59.2pt'>\n";
		$content .= "<p class=MsoNormal align=center style='text-align:center;line-height:106%'><b>\n";
		$content .= "<span style='font-size:26.0pt;line-height:106%;font-family:\"Arial\",sans-serif; color:#57585A;letter-spacing:5.5pt'>VOCUS PORTAL</span></b>\n";
		$content .= "<span style='font-family:\"Arial\",sans-serif;color:#57585A'><u1:p></u1:p></span></p>\n";
		$content .= "</td></tr>\n";
		$content .= "<tr style='height:33.85pt'>\n";
		$content .= "<td width=689 style='width:516.9pt;background:#114B95;padding:0cm 5.4pt 0cm 5.4pt; height:33.85pt;border-image: initial'>\n";
		$content .= "<p class=MsoNormal align=center style='text-align:center;line-height:106%'><b>\n";
		$content .= "<span style='font-size:16.0pt;line-height:106%;font-family:\"Arial\",sans-serif; color:white'>\n";
		
		$content .= $specific_subject;
		
		$content .= "</span></b></p>\n";
		$content .= "</td></tr>\n";
		$content .= "<tr style='height:1.0cm'>\n";
		$content .= "<td width=689 style='width:516.9pt;border:solid #F4F4F4 1.0pt;border-top: none;background:#F4F4F4;padding:0cm 5.4pt 0cm 5.4pt;height:1.0cm;border-image: initial'>\n";
		$content .= "<span style='font-size:12.0pt;line-height:106%;font-family:\"Arial\",sans-serif; color:black'><u1:p></span>\n";
		$content .= "<p style='margin-right:20.75pt;line-height:106%'>&nbsp;</p>\n";
		$content .= "</td></tr>\n";
		$content .= "<tr style='height:44.5pt'>\n";
		$content .= "<td width=689 style='width:516.9pt;border:solid #F4F4F4 1.0pt;border-top: none;background:#F4F4F4;padding:0cm 5.4pt 0cm 5.4pt;height:44.5pt;border-image: initial'>\n";
		$content .= "<h2 style='margin-top:5.0pt;margin-right:20.75pt;margin-bottom:5.0pt; margin-left:15.95pt;text-align:justify;line-height:106%'>\n";
		$content .= "<span style='font-size:9.0pt;line-height:106%;font-family:\"Arial\",sans-serif; color:#232323'>\n";
		
		$content .= $message_content;
		
		$content .= "</span></h2>\n";
		$content .= "</td></tr>\n";
		$content .= "<tr style='height:1.0cm'>\n";
		$content .= "<td width=689 style='width:516.9pt;border:solid #F4F4F4 1.0pt;border-top: none;background:#F4F4F4;padding:0cm 5.4pt 0cm 5.4pt;height:1.0cm'>\n";
		$content .= "<span style='font-size:12.0pt;line-height:106%;font-family:\"Arial\",sans-serif; color:black'><u1:p></span>\n";
		$content .= "<p style='margin-right:20.75pt;line-height:106%'>&nbsp;</p>\n";
		$content .= "</td></tr>\n";
		$content .= "<tr style='height:34.75pt'>\n";
		$content .= "<td width=689 style='width:516.9pt;border:solid #114B95 1.0pt;border-top: none;background:#114B95;padding:0cm 5.4pt 0cm 5.4pt;height:34.75pt; border-image: initial'>\n";
		$content .= "<p class=MsoNormal align=center style='text-align:center;line-height:106%'><strong>\n";
		$content .= "<span style='font-size:5.5pt;line-height:106%;font-family:\"Arial\",sans-serif; color:white'>NOTICE -</span></strong>\n";
		$content .= "<span style='font-size:5.5pt;line-height: 106%;font-family:\"Arial\",sans-serif;color:white'>&nbsp;\n";
		$content .= "This message contains information intended only for the use of the addressee named above. \n";
		$content .= "It may also be confidential and/or privileged. If you are not the intended recipient\n";
		$content .= "of this message or have received this message in error, you are hereby notified that \n";
		$content .= "you must not disseminate, copy or take any action in reliance on it.\n";
		$content .= "Please do not reply to this message. Replies to this message are routed to an unmonitored mailbox. If you have questions please contact your immediate support or create a JIRA ticket (ELEARNING - Intranet Update).\n";
		$content .= "</span></p></td></tr></table></div>\n";		
		$content .= "<p align=center style='margin:0cm;margin-bottom:.0001pt;text-align:center;\n";
		$content .= "background:white;font-variant-ligatures: normal;font-variant-caps: normal;\n";
		$content .= "orphans: 2;widows: 2;-webkit-text-stroke-width: 0px;text-decoration-style: initial;\n";
		$content .= "text-decoration-color: initial;word-spacing:0px'><strong>\n";
		$content .= "<span style='font-size:8.5pt;font-family:\"Arial\",sans-serif;color:#203864'>P</span></strong>\n";
		$content .= "<span style='font-size:8.5pt;font-family:\"Arial\",sans-serif;color:#A0A2A4'>:&nbsp;</span>\n";
		$content .= "<span style='font-size:8.5pt;font-family:\"Arial\",sans-serif;color:black'>\n";
		$content .= "<a href='tel:2%208667%206800'><span style='color:#666666;background:white'>+63 2 8667 6800&nbsp;</span></a></span>\n";
		$content .= "<strong><span style='font-size:8.5pt; font-family:\"Arial\",sans-serif;color:#203864'>W</span></strong>\n";
		$content .= "<span style='font-size:8.5pt;font-family:\"Arial\",sans-serif;color:#A0A2A4'>:&nbsp;</span>\n";
		$content .= "<span style='font-size:8.5pt;font-family:\"Arial\",sans-serif;color:gray'>\n";
		$content .= "<a href='http://callcentreintranet.apps.m2group.ext'>\n";
		$content .= "<span style='color:gray'>http://callcentreintranet.apps.m2group.ext</span></a><br></span>\n";
		$content .= "<strong><span style='font-size:8.5pt;font-family:\"Arial\",sans-serif; color:#203864'>A</span></strong>\n";
		$content .= "<span style='font-size:8.5pt;font-family:\"Arial\",sans-serif; color:#A0A2A4'>:</span>\n";
		$content .= "<span style='font-size:8.5pt;font-family:\"Helvetica\",sans-serif; color:#A0A2A4'>&nbsp;</span>\n";
		$content .= "<span style='font-size:8.5pt;font-family:\"Arial\",sans-serif;color:gray'>\n";
		$content .= "Unit A Level 31 Eastwood Cyber &amp; Fashion Mall Building, 10\n";
		$content .= "Eastwood City, Bagumbayan, Quezon City, 1110 Metro Manila</span></p>\n";
		$content .= "<p class=MsoNormal>&nbsp;</p></div>\n";
			
		// REQUIRED STATIC VARIABLES
		$eol = PHP_EOL;	
		$to = $email_to;
		$subject = 'Vocus Portal | ' . $specific_subject;
				
		// email headers
		$headers = "From: noreply@portal.com".$eol;
		//$headers .= "Bcc: angelika.rockwell@team.acquirebpo.com".$eol;
		//$headers .= "Bcc: murge20163319@team.acquirebpo.com".$eol;
		$headers .= "MIME-Version: 1.0".$eol;
		$headers .= "Content-Type: text/html; charset=utf-8".$eol;
			
		// html message
		$body = "--".$random_hash.$eol;
		$body .= "Content-Type: text/html; charset=\"UTF-8\"".$eol;
		$body .= "Content-Transfer-Encoding: 8bit".$eol.$eol;
		$body .= $content.$eol;
			
		// FOR EMAILS WITH ATTACHMENTS 
		if ($target_name != '') {
			$headers .= "Content-Type: multipart/mixed; boundary=\"".$random_hash."\"";
			$headers .= "This is a multi-part message in MIME format. If you can read this, something went wrong or your mail client doesn't understand MIME messages.".$eol;

			// MULTIPLE ATTACHMENTS
			if (is_array($target_name)) {
				foreach ($target_name as $target_names) {
					$target_type = strtolower(pathinfo($target_names,PATHINFO_EXTENSION));			//attachment complete file type
					$target_file = $target_path . $target_names;						//attachment complete name
					$attachment = chunk_split(base64_encode(file_get_contents($target_file))); 		//and split it into smaller chunks
					
					if ($target_type == 'png' || $target_type == 'gif' ) {
						$contenttype = 'image/'.$target_type;
					} else if ($target_type == 'jpg' || $target_type == 'jpeg' ) {
						$contenttype = 'image/jpeg';
					} else if ($target_type == 'pdf') {
						$contenttype = 'application/pdf';
					} else if ($target_type == 'xls' || $target_type == 'xls') {
						$contenttype = 'application/vnd.ms-excel';
					} else if ($target_type == 'csv') {
						$contenttype = 'text/csv';
					} else if ($target_type == 'doc') {
						$contenttype = 'application/msword';
					} else if ($target_type == 'docx') {
						$contenttype = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
					} else {
						$contenttype = 'application/octet-stream';
					}
									
					$body .= "--".$random_hash.$eol;
					$body .= "Content-Type: ".$contenttype."; name=\"".$target_file."\"".$eol; 
					$body .= "Content-ID: <".$target_file.">".$eol;
					$body .= "Content-Transfer-Encoding: base64".$eol;
					$body .= "Content-Disposition: inline; filename=\"".$target_names."\"".$eol.$eol;
					$body .= $attachment.$eol;
				}
				$body .= "--".$random_hash."--";
			}
			else {
				$target_type = strtolower(pathinfo($target_name,PATHINFO_EXTENSION));			//attachment complete file type
				$target_file = $target_path . $target_name;						//attachment complete name
				$attachment = chunk_split(base64_encode(file_get_contents($target_file))); 		//and split it into smaller chunks	
				
				if ($target_type == 'png' || $target_type == 'gif' ) {
					$contenttype = 'image/'.$target_type;
				} else if ($target_type == 'jpg' || $target_type == 'jpeg' ) {
					$contenttype = 'image/jpeg';
				} else if ($target_type == 'pdf') {
					$contenttype = 'application/pdf';
				} else if ($target_type == 'xls' || $target_type == 'xls') {
					$contenttype = 'application/vnd.ms-excel';
				} else if ($target_type == 'csv') {
					$contenttype = 'text/csv';
				} else if ($target_type == 'doc') {
					$contenttype = 'application/msword';
				} else if ($target_type == 'docx') {
					$contenttype = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
				} else {
					$contenttype = 'application/octet-stream';
				}
				
				$body .= "--".$random_hash.$eol;
				$body .= "Content-Type: ".$contenttype."; name=\"".$target_file."\"".$eol; 
				$body .= "Content-ID: <".$target_file.">".$eol;
				$body .= "Content-Transfer-Encoding: base64".$eol;
				$body .= "Content-Disposition: attachment; filename=\"".$target_name."\"".$eol.$eol;
				$body .= $attachment.$eol;
				$body .= "--".$random_hash."--";
			}
			mail( $to, $subject, $body, $headers ); 
		}
		
		// FOR EMAILS WITH NO ATTACHMENTS 
		else {
			mail( $to, $subject, $content, $headers ); 
		}	
	}
}


/////////////////////////////////////////////////////////////
//////////////////////////////////// START OF CLASS PROFILE//
/////////////////////////////////////////////////////////////

class profile
{
	public static function contact($dbh,$user,$contact)
	{
		$checkContactQ = "SELECT * FROM cmdr_profile WHERE profile_user = :contact AND profile_contact = :user;";
		$checkContactS = $dbh->prepare($checkContactQ);
		$checkContactS->execute(array(':user' => $user,
									':contact' => $contact));
		$checkContact = $checkContactS->fetch(PDO::FETCH_ASSOC);
		
		if ($checkContact['profile_status'] == "blocked") {
			return "blocked";
		}
		else if ($checkContact['profile_status'] == "pending") {
			return "confirm";
		}

		else {
			$checkContactQ2 = "SELECT * FROM cmdr_profile WHERE profile_user = :user AND profile_contact = :contact;";
			$checkContactS2 = $dbh->prepare($checkContactQ2);
			$checkContactS2->execute(array(':user' => $user,
										':contact' => $contact));
			$checkContact2 = $checkContactS2->fetch(PDO::FETCH_ASSOC);
			
			switch ($checkContact2['profile_status']) {
				case 'blocked':
						return "unblock";
					break;
				
				case 'pending':
						return "pending";
					break;

				case 'connected':
						return "connected";
					break;

				default:
					return "request";
					break;
			}
		}
	}
	
	public static function staff($dbh, $id, $field)
	{
		$finderQ = "SELECT * FROM cmdr_stafflist LEFT JOIN cmdr_cleansing on cmdr_stafflist.staffid = cmdr_cleansing.cleansing_empid LEFT JOIN cmdr_demographics ON cmdr_demographics.empid = cmdr_stafflist.staffid WHERE staffid = :empid";
		$finderS = $dbh->prepare($finderQ);
		$finderS->execute(array(':empid' => $id));
		$finder = $finderS->fetch(PDO::FETCH_ASSOC);
		
		return $finder[$field];
	}
}


/////////////////////////////////////////////////////////////
///////////////////////////// START OF CLASS dateChecker ////
/////////////////////////////////////////////////////////////

class dateChecker
{
	public static function getCalendarDate($week){
	
		if(date('N', strtotime($week)) == 7){
			$dates_array = date("Y-m-d", strtotime('Monday last week', strtotime($week)));   
		}else{
			$dates_array = date("Y-m-d", strtotime('Monday this week', strtotime($week)));     
		}
		
		return $dates_array;
	}
}


/////////////////////////////////////////////////////////////
//////////////////////////////////// START OF CLASS MNU /////
/////////////////////////////////////////////////////////////
class mnu
{
	public static function addCookie($name, $value)
	{
		setcookie($name, $value, time() + (86400) * 30, "/");
	}

	public static function deleteCookie($name)
	{
		setcookie($name, '', time() - 1, "/");
	}
}

?>
