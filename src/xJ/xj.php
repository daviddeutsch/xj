<?php

$self = dirname(__FILE__);

include_once($self.'/utility.php');
include_once($self.'/common.php');

if ( !defined('JPATH_MANIFESTS') ) {
	include_once($self.'/ltj30.php');
	include_once($self.'/j15.php');
} else {
	include_once($self.'/gtj25.php');

	$v = new JVersion();

	if ( $v->isCompatible('3.0') ) {
		include_once($self.'/j30.php');
	} else {
		include_once($self.'/ltj30.php');
	}
}
