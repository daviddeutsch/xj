<?php
class xJLanguageHandler extends xJLanguageHandlerCommon
{
	static function loadList( $list )
	{
		if ( empty( $list ) ) {
			return;
		}

		$lang =& JFactory::getLanguage();

		foreach ( $list as $name => $path ) {
			$lang->load( $name, $path, 'en-GB', true );
			$lang->load( $name, $path, $lang->get('tag'), true );
		}

		foreach ( $lang->_strings as $k => $v ) {
			$lang->_strings[$k]= str_replace( '"_QQ_"', '"', $v );
		}

		return;
	}

	static function getSystemLanguages()
	{
		$fdir = JPATH_SITE . '/language';

		$list = xJUtility::getFileArray( $fdir, null, true, true );

		$adir = JPATH_SITE . '/administrator/language';

		$list = array_merge( $list, xJUtility::getFileArray( $adir, null, true, true ) );

		$languages = array();
		foreach ( $list as $li ) {
			if ( ( strpos( $li, '-' ) !== false ) && !in_array( $li, $languages ) ) {
				$languages[] = $li;
			}
		}

		return $languages;
	}
}

class xJACLhandler extends xJACLhandlerCommon
{
	static function getSuperAdmins()
	{
		$db = &JFactory::getDBO();

		$query = 'SELECT `id`, `name`, `email`, `sendEmail`'
				. ' FROM #__users'
				. ' WHERE LOWER( usertype ) = \'superadministrator\''
				. ' OR LOWER( usertype ) = \'super administrator\''
				;
		$db->setQuery( $query );
		return $db->loadObjectList();
	}

	static function setGID( $userid, $gid, $gid_name )
	{
		$db = &JFactory::getDBO();

		$query = 'UPDATE #__users'
				. ' SET `gid` = \'' .  (int) $gid . '\', `usertype` = \'' . $gid_name . '\''
				. ' WHERE `id` = \''  . (int) $userid . '\''
				;
		$db->setQuery( $query );
		$db->query() or die( $db->stderr() );
	}

	static function setGIDsTakeNames( $userid, $gid )
	{
		$db = &JFactory::getDBO();

		$acl = &JFactory::getACL();

		// Get ARO ID for user
		$query = 'SELECT `id`'
				. ' FROM #__core_acl_aro'
				. ' WHERE `value` = \'' . (int) $userid . '\''
				;
		$db->setQuery( $query );
		$aro_id = $db->loadResult();

		// If we have no aro id, something went wrong and we need to create it
		if ( empty( $aro_id ) ) {
			$metaUser = new metaUser( $userid );

			$query2 = 'INSERT INTO #__core_acl_aro'
					. ' (`section_value`, `value`, `order_value`, `name`, `hidden` )'
					. ' VALUES ( \'users\', \'' . $userid . '\', \'0\', \'' . $metaUser->cmsUser->name . '\', \'0\' )'
					;
			$db->setQuery( $query2 );
			$db->query();

			$db->setQuery( $query );
			$aro_id = $db->loadResult();
		}

		// Carry out ARO ID -> ACL group mapping
		$query = 'UPDATE #__core_acl_groups_aro_map'
				. ' SET `group_id` = \'' . (int) $gid . '\''
				. ' WHERE `aro_id` = \'' . $aro_id . '\''
				;
		$db->setQuery( $query );
		$db->query() or die( $db->stderr() );

		$gid_name = $acl->get_group_name( $gid, 'ARO' );

		return $gid_name;
	}

	static function adminBlock( $admin, $manager )
	{
		$user = &JFactory::getUser();

		$acl = &JFactory::getACL();

		$block = false;

		$acl->addACL( 'administration', 'config', 'users', 'super administrator' );

		$acpermission = $acl->acl_check( 'administration', 'config', 'users', $user->usertype );

		if ( !$acpermission ) {
			if (
				!( ( strcmp( $user->usertype, 'Administrator' ) === 0 ) && $admin )
				&& !( ( strcmp( $user->usertype, 'Manager' ) === 0 ) && $manager )
			 ) {
				$block = true;
			}
		}

		if ( $block ) {
			$app = JFactory::getApplication();

			$app->redirect( 'index.php', _NOT_AUTH );
		}
	}

	static function userDelete( $userid, $msg )
	{
		$user = &JFactory::getUser();

		if ( $userid == $user->id ) {
			return JText::_('You cannot delete yourself');
		}

		$acl = &JFactory::getACL();

		$user = &JFactory::getUser();

		$groups		= $acl->get_object_groups( 'users', $userid, 'ARO' );

		$this_group	= strtolower( $acl->get_group_name( $groups[0], 'ARO' ) );

		$deletor_admin = $user->gid == 24;

		if( $this_group == 'super administrator' ) {
			return JText::_('You cannot delete a Superadmin');
		}

		$is_admin		= $this_group == 'administrator';

		if ( $is_admin && $deletor_admin ) {
			return JText::_('Only a Superadmin can do that');
		} else {
			$db = &JFactory::getDBO();

			$obj = new cmsUser();

			if ( !$obj->delete( $userid ) ) {
				return $obj->getError();
			}
		}

		return null;
	}

	static function getGroupTree( $ex=array() )
	{
		$acl = &JFactory::getACL();

		$user = &JFactory::getUser();

		$ex_groups = array();

		$gtree = $acl->get_group_children_tree( null, 'USERS', true );

		$my_groups = $acl->get_object_groups( 'users', $user->id, 'ARO' );

		if ( is_array( $my_groups ) && count( $my_groups ) > 0) {
			$ex_groups = $acl->get_group_children( $my_groups[0], 'ARO', 'RECURSE' );
		} else {
			$ex_groups = array();
		}

		$ex_groups = array_merge( $ex_groups, $ex );

		// remove groups 'above' current user
		$i = 0;
		while ( $i < count( $gtree ) ) {
			if ( in_array( $gtree[$i]->value, $ex_groups ) ) {
				array_splice( $gtree, $i, 1 );
			} else {
				$i++;
			}
		}

		return $gtree;
	}

	static function countAdmins()
	{
		$db = &JFactory::getDBO();

		$query = 'SELECT count(*)'
				. ' FROM #__core_acl_groups_aro_map'
				. ' WHERE `group_id` IN (\'25\',\'24\')'
				;
		$db->setQuery( $query );
		return $db->loadResult();
	}

	static function aclList()
	{
		$list = array();

		$acl =& JFactory::getACL();

		$acllist = $acl->get_group_children( 28, 'ARO', 'RECURSE' );

		foreach ( $acllist as $aclli ) {
			$acldata = $acl->get_group_data( $aclli );

			$list[$aclli] = new stdClass();

			$list[$aclli]->group_id	= $acldata[0];
			$list[$aclli]->name		= $acldata[3];
		}

		return $list;
	}

	static function getLowerACLGroups( $group_id )
	{
		$db = &JFactory::getDBO();

		$query = 'SELECT g2.id'
				. ' FROM #__core_acl_aro_groups AS g1'
				. ' INNER JOIN #__core_acl_aro_groups AS g2 ON g1.lft > g2.lft AND g1.rgt < g2.rgt'
				. ' WHERE g1.id = ' . $group_id
				. ' GROUP BY g2.id'
				. ' ORDER BY g2.lft'
				;
		$db->setQuery( $query );

		return xJ::getDBArray( $db );
	}

	static function getHigherACLGroups( $group_id )
	{
		$db = &JFactory::getDBO();

		$query = 'SELECT g2.id'
				. ' FROM #__core_acl_aro_groups AS g1'
				. ' INNER JOIN #__core_acl_aro_groups AS g2 ON g1.lft < g2.lft AND g1.rgt > g2.rgt'
				. ' WHERE g1.id = ' . $group_id
				. ' GROUP BY g2.id'
				. ' ORDER BY g2.lft'
				;
		$db->setQuery( $query );

		return xJ::getDBArray( $db );
	}
}

class xJSessionHandler extends xJSessionHandlerCommon
{
	function instantGIDchange( $userid, $gid, $removegid=array(), $sessionextra=null )
	{
		$user = &JFactory::getUser();

		if ( !is_array( $gid ) && !empty( $gid ) ) {
			$gid = array( $gid );
		} elseif ( empty( $gid ) ) {
			$gid = array();
		}

		if ( !is_array( $removegid ) && !empty( $removegid ) ) {
			$removegid = array( $removegid );
		}

		if ( !empty( $removegid ) ) {
			xJACLhandler::removeGIDs( (int) $userid, $removegid );
		}

		// Set GID and usertype
		if ( !empty( $gid ) ) {
			$info = xJACLhandler::setGIDs( (int) $userid, $gid );
		}

		$session = $this->getSession( $userid );

		if ( empty( $session ) ) {
			return true;
		}

		if ( !empty( $sessionextra ) ) {
			if ( is_array( $sessionextra ) ) {
				foreach ( $sessionextra as $sk => $sv ) {
					$session['user']->$sk = $sv;

					if ( $userid == $user->id ) {
						$user->$sk	= $sv;
					}
				}
			}
		}

		if ( isset( $session['user'] ) ) {
				if ( !empty( $gid[0] ) ) {
				$session['user']->gid		= $gid[0];
				$session['user']->usertype	= $info[$gid[0]];

				if ( $userid == $user->id ) {
					$user->gid		= $gid[0];
					$user->usertype	= $info[$gid[0]];
				}
			}
		}

		return $this->putSession( $userid, $session, $gid[0], $info[$gid[0]] );
	}

	function putSession( $userid, $data, $gid=null, $gid_name=null )
	{
		$db = &JFactory::getDBO();

		$sdata = $this->joomserializesession( array( $this->sessionkey => $data) );

		if ( isset( $data['user'] ) ) {
			if ( empty( $gid ) ) {
				$query = 'UPDATE #__session'
						. ' SET `data` = \'' . xJ::escape( $db, $sdata ) . '\''
						. ' WHERE `userid` = \'' . (int) $userid . '\''
						;
			} else {
				$query = 'UPDATE #__session'
						. ' SET `gid` = \'' .  (int) $gid . '\', `usertype` = \'' . $gid_name . '\', `data` = \'' . xJ::escape( $db, $sdata ) . '\''
						. ' WHERE `userid` = \'' . (int) $userid . '\''
						;
			}
		}

		$db->setQuery( $query );
		return $db->query() or die( $db->stderr() );
	}

}


?>
