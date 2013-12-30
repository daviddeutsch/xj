<?php
class xJ
{
	static function getDBArray( $db )
	{
		$list = $db->loadObjectList();
	
		$return = array();
		if ( count( $list ) ) {
			$obj = array_keys( get_object_vars( $list[0] ) );
	
			$k = $obj[0];
	
			foreach ( $list as $li ) {
				$return[] = $li->$k;
			}
		}
	
		return $return;
	}

	static function escape( $db, $value )
	{
		return $db->escape( $value );
	}

	static function token()
	{
		return JSession::getFormToken();
	}

	static function getHash()
	{
		return JApplication::getHash(JUserHelper::genRandomPassword());
	}

	static function sendMail( $sender, $sender_name, $recipient, $subject, $message, $html=null, $cc=null, $bcc=null, $attach=null )
	{
		$mailer = JFactory::getMailer();

		$mailer->sendMail( $sender, $sender_name, $recipient, $subject, $message, $html, $cc, $bcc, $attach );
	}
}
?>
