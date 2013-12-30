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
	function getSuperAdmins()
	{
		$groups = xJACLhandler::getAdminGroups();

		$users = xJACLhandler::getUsersByGroup( $groups );

		return xJACLhandler::getUserObjects( $users );
	}

	function setGID( $userid, $gid, $gid_name )
	{
		$db = &JFactory::getDBO();

		// Make sure the user does not have this group assigned yet
		$query = 'SELECT `user_id`'
				. ' FROM #__user_usergroup_map'
				. ' WHERE `user_id` = \'' . $userid . '\''
				. ' AND `group_id` = \'' . $gid . '\''
				;
		$db->setQuery( $query );
		$id = $db->loadResult();

		if ( empty( $id ) ) {
			$query = 'INSERT INTO `#__user_usergroup_map` (`user_id`, `group_id`)'
					. ' VALUES ('.$userid.', '.$gid.')'
					;
			$db->setQuery( $query );
			$db->query() or die( $db->stderr() );
		}
	}

	function setGIDsTakeNames( $userid, $gid )
	{
		$db = &JFactory::getDBO();

		$query = 'SELECT `title`'
				. ' FROM #__usergroups'
				. ' WHERE `id` = \'' . $gid . '\''
				;
		$db->setQuery( $query );
		$gid_name = $db->loadResult();

		return $gid_name;
	}

	function adminBlock( $admin, $manager )
	{
		$user = &JFactory::getUser();

		$acl = &JFactory::getACL();

		$block = false;

		$allowed_groups = xJACLhandler::getAdminGroups( $admin );

		if ( $manager ) {
			$allowed_groups = array_merge( $allowed_groups, xJACLhandler::getManagerGroups() );
		}

		$usergroups = $acl->getGroupsByUser( $user->id );

		if ( !count( array_intersect( $allowed_groups, $usergroups ) ) ) {
			$block = true;
		}

		if ( $block ) {
			$app = JFactory::getApplication();

			$app->redirect( 'index.php', "Not Authorized" );
		}
	}

	function userDelete( $userid, $msg )
	{
		$user = &JFactory::getUser();

		if ( $userid == $user->id ) {
			return JText::_('You cannot delete yourself');
		}

		$acl = &JFactory::getACL();

		$user = &JFactory::getUser();

		$superadmins = xJACLhandler::getAdminGroups( false );

		$alladmins = xJACLhandler::getAdminGroups();

		$groups = $acl->getGroupsByUser( $userid );

		if ( count( array_intersect( $groups, $superadmins ) ) ) {
			return JText::_('You cannot delete a Super User');
		}

		$is_admin = false;
		if ( count( array_intersect( $groups, $alladmins ) ) ) {
			$is_admin = true;
		}

		$usergroups = $acl->getGroupsByUser( $user->id );

		$deletor_admin = true;
		if ( count( array_intersect( $usergroups, $superadmins ) ) ) {
			$deletor_admin = false;
		}

		if ( $is_admin && $deletor_admin ) {
			return JText::_('Only Super Users can do that');
		} else {
			$db = &JFactory::getDBO();

			$obj = new cmsUser();

			if ( !$obj->delete( $userid ) ) {
				return $obj->getError();
			}
		}
	}

	function getGroupTree( $ex=array() )
	{
		$acl = &JFactory::getACL();

		$user = &JFactory::getUser();

		$ex_groups = array();

		$db = JFactory::getDbo();

		$db->setQuery(
			'SELECT a.id AS value, a.title AS text, COUNT(DISTINCT b.id) AS level'
			. ' FROM #__usergroups AS a'
			. ' LEFT JOIN `#__usergroups` AS b ON a.lft > b.lft AND a.rgt < b.rgt'
			. ' WHERE a.parent_id != 0'
			. ' GROUP BY a.id'
			. ' ORDER BY a.lft ASC'
		);

		$gtree = $db->loadObjectList();

		foreach ( $gtree as &$option ) {
			$option->text = str_repeat('- ',$option->level).$option->text;
		}

		$usergroups = $acl->getGroupsByUser( $user->id );

		$superadmins = xJACLhandler::getAdminGroups( false );

		$alladmins = xJACLhandler::getAdminGroups();

		if ( !count( array_intersect( $usergroups, $superadmins ) ) ) {
			$ex_groups = array_merge( $ex_groups, $superadmins );
		} else {
			$ex_groups = array_merge( $ex_groups, $alladmins );
		}

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

	function countAdmins()
	{
		return count( xJACLhandler::getUsersByGroup( xJACLhandler::getAdminGroups() ) );
	}

	function aclList()
	{
		$db = &JFactory::getDBO();

		$list = array();

		$query = 'SELECT `id`, `title`'
				. ' FROM #__usergroups'
				;
		$db->setQuery( $query );

		$acllist = $db->loadObjectList();

		foreach ( $acllist as $aclli ) {
			$acll = new stdClass();

			$acll->group_id	= $aclli->id;
			$acll->name		= $aclli->title;

			$list[] = $acll;
		}

		return $list;
	}

	function getLowerACLGroups( $group_id )
	{
		$db = &JFactory::getDBO();

		$query = 'SELECT g2.id'
				. ' FROM #__usergroups AS g1'
				. ' INNER JOIN #__usergroups AS g2 ON g1.lft > g2.lft AND g1.rgt < g2.rgt'
				. ' WHERE g1.id = ' . $group_id
				. ' GROUP BY g2.id'
				. ' ORDER BY g2.lft'
				;
		$db->setQuery( $query );

		return xJ::getDBArray( $db );
	}

	function getHigherACLGroups( $group_id )
	{
		$db = &JFactory::getDBO();

		$query = 'SELECT g2.id'
				. ' FROM #__usergroups AS g1'
				. ' INNER JOIN #__usergroups AS g2 ON g1.lft < g2.lft AND g1.rgt > g2.rgt'
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
			$user = &JFactory::getUser();

			$sgsids = JAccess::getGroupsByUser( $userid );

			if ( !empty( $gid ) ) {
				foreach ( $gid as $g ) {
					if ( !in_array( $g, $sgsids ) ) {
						$sgsids[] = $g;
					}
				}
			}

			if ( !empty( $removegid ) ) {
				foreach ( $sgsids as $k => $g ) {
					if ( in_array( $g, $removegid ) ) {
						unset( $sgsids[$k] );
					}
				}
			}

			$db = &JFactory::getDBO();

			$query = 'SELECT `title`, `id`'
					. ' FROM #__usergroups'
					. ' WHERE `id` IN (' . implode( ',', $sgsids ) . ')'
					;
			$db->setQuery( $query );
			$sgslist = $db->loadObjectList();

			$sgs = array();

			foreach ( $sgslist as $gidgroup ) {
				if ( !in_array( $gidgroup->id, $removegid ) ) {
					$sgs[$gidgroup->title] = $gidgroup->id;
				}
			}

			if ( $userid == $user->id ) {
				$user->set( 'groups', $sgs );

				$user->set( '_authLevels', xJSessionHandler::getAuthorisedViewLevels($userid) );
				$user->set( '_authGroups', xJSessionHandler::getGroupsByUser($userid) );
			}

			$session['user']->set( 'groups', $sgs );

			$session['user']->set( '_authLevels', xJSessionHandler::getAuthorisedViewLevels($userid) );
			$session['user']->set( '_authGroups', xJSessionHandler::getGroupsByUser($userid) );
		}

		$this->putSession( $userid, $session, $gid[0], $info[$gid[0]] );
	}

	function putSession( $userid, $data, $gid=null, $gid_name=null )
	{
		$db = &JFactory::getDBO();

		$sdata = $this->joomserializesession( array( $this->sessionkey => $data) );

		if ( defined( 'JPATH_MANIFESTS' ) ) {
			$query = 'UPDATE #__session'
					. ' SET `data` = \'' . xJ::escape( $db, $sdata ) . '\''
					. ' WHERE `userid` = \'' . (int) $userid . '\''
					;
		}

		$db->setQuery( $query );
		$db->query() or die( $db->stderr() );
	}

}

?>
