<?php
class xJ
{
	static function getDBArray( $db )
	{
		return $db->loadResultArray();
	}

	static function escape( $db, $value )
	{
		return $db->getEscaped( $value );
	}

	static function token()
	{
		return JUtility::getToken();
	}

	static function getHash()
	{
		return JUtility::getHash(JUserHelper::genRandomPassword());
	}

	static function sendMail( $sender, $sender_name, $recipient, $subject, $message, $html=null, $cc=null, $bcc=null, $attach=null )
	{
		JUTility::sendMail( $sender, $sender_name, $recipient, $subject, $message, $html, $cc, $bcc, $attach );
	}
}

?>
