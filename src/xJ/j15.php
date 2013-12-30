<?php
class xJLanguageHandler extends xJLanguageHandlerCommon
{
	static function loadList( $list )
	{
		if ( empty($list) ) {
			return;
		}

		$lang =& JFactory::getLanguage();

		foreach ( $list as $name => $path ) {
			$lang->load($name, $path, 'en-GB', true);
			$lang->load($name, $path, $lang->get('tag'), true);
		}

		foreach ( $lang->_strings as $k => $v ) {
			$lang->_strings[$k]= str_replace('"_QQ_"', '"', $v);
		}

		return;
	}

	static function getSystemLanguages()
	{
		$fdir = JPATH_SITE.'/language';

		$list = xJUtility::getFileArray($fdir, null, true, true);

		$adir = JPATH_SITE.'/administrator/language';

		$list = array_merge($list, xJUtility::getFileArray($adir, null, true, true));

		$languages = array();
		foreach ( $list as $li ) {
			if ( (strpos($li, '-') !== false) && !in_array($li, $languages) ) {
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
		$db = JFactory::getDBO();

		$db->setQuery(
			'SELECT `id`, `name`, `email`, `sendEmail`'
			. ' FROM #__users'
			. ' WHERE LOWER(usertype) = \'superadministrator\''
			. ' OR LOWER(usertype) = \'super administrator\''
		);

		return $db->loadObjectList();
	}

	static function setGID( $userid, $gid, $gid_name )
	{
		$db = JFactory::getDBO();

		$db->setQuery(
			'UPDATE #__users'
			. ' SET `gid` = \''.((int) $gid).'\','
				. ' `usertype` = \''.$gid_name.'\''
			. ' WHERE `id` = \'' .((int) $userid).'\''
		);

		$db->query() or die($db->stderr());
	}

	static function setGIDsTakeNames( $userid, $gid )
	{
		$db = JFactory::getDBO();

		$acl = JFactory::getACL();

		// Get ARO ID for user
		$query = 'SELECT `id`'
				. ' FROM #__core_acl_aro'
				. ' WHERE `value` = \''.(int) $userid.'\''
				;

		$db->setQuery($query);

		$aro_id = $db->loadResult();

		// If we have no aro id, something went wrong and we need to create it
		if  (empty($aro_id) ) {
			$user = new JTableUser($userid);

			$db->setQuery(
				'INSERT INTO #__core_acl_aro'
				. ' (`section_value`, `value`, `order_value`, `name`, `hidden`)'
				. ' VALUES (\'users\', \''.$userid.'\', \'0\', \''.$user->name.'\', \'0\')'
			);

			$db->query();

			$db->setQuery($query);

			$aro_id = $db->loadResult();
		}

		// Carry out ARO ID -> ACL group mapping
		$db->setQuery(
			'UPDATE #__core_acl_groups_aro_map'
			. ' SET `group_id` = \''.(int) $gid.'\''
			. ' WHERE `aro_id` = \''.$aro_id.'\''
		);

		$db->query() or die($db->stderr());

		return $acl->get_group_name($gid, 'ARO');
	}

	static function adminBlock( $admin, $manager )
	{
		$user = JFactory::getUser();

		$acl = JFactory::getACL();

		$block = false;

		$acl->addACL('administration', 'config', 'users', 'super administrator');

		$permission = $acl->acl_check('administration', 'config', 'users', $user->usertype);

		if ( !$permission ) {
			if (
				!((strcmp($user->usertype, 'Administrator') === 0) && $admin)
				&& !((strcmp($user->usertype, 'Manager') === 0) && $manager)
			) {
				$block = true;
			}
		}

		if ( $block ) {
			$app = JFactory::getApplication();

			$app->redirect('index.php', 'Not Authorized');
		}
	}

	static function userDelete( $userid, $msg )
	{
		$user = JFactory::getUser();

		if ( $userid == $user->id ) {
			return JText::_('You cannot delete yourself');
		}

		$acl = JFactory::getACL();

		$groups = $acl->get_object_groups('users', $userid, 'ARO');

		$this_group	= strtolower ($acl->get_group_name($groups[0], 'ARO') );

		if ( $this_group == 'super administrator' ) {
			return JText::_('You cannot delete a Superadmin');
		}

		if ( ($this_group == 'administrator') && ($user->gid == 24) ) {
			return JText::_('Only a Superadmin can do that');
		} else {
			$db = JFactory::getDBO();

			$obj = new JTableUser($db);

			if ( !$obj->delete($userid) ) {
				return $obj->getError();
			}
		}

		return null;
	}

	static function getGroupTree( $ex=array() )
	{
		$acl = JFactory::getACL();

		$user = JFactory::getUser();

		$gtree = $acl->get_group_children_tree(null, 'USERS', true);

		$my_groups = $acl->get_object_groups('users', $user->id, 'ARO');

		if ( is_array($my_groups) && count($my_groups) > 0 ) {
			$ex_groups = $acl->get_group_children($my_groups[0], 'ARO', 'RECURSE');
		} else {
			$ex_groups = array();
		}

		$ex_groups = array_merge($ex_groups, $ex);

		// remove groups 'above' current user
		$i = 0;
		while ( $i < count($gtree) ) {
			if ( in_array($gtree[$i]->value, $ex_groups) ) {
				array_splice($gtree, $i, 1);
			} else {
				$i++;
			}
		}

		return $gtree;
	}

	static function countAdmins()
	{
		$db = JFactory::getDBO();

		$db->setQuery(
			'SELECT count(*)'
			. ' FROM #__core_acl_groups_aro_map'
			. ' WHERE `group_id` IN (\'25\',\'24\')'
		);

		return $db->loadResult();
	}

	static function aclList()
	{
		$list = array();

		$acl =& JFactory::getACL();

		$acllist = $acl->get_group_children(28, 'ARO', 'RECURSE');

		foreach ( $acllist as $aclli ) {
			$acldata = $acl->get_group_data($aclli);

			$list[$aclli] = new stdClass();

			$list[$aclli]->group_id = $acldata[0];
			$list[$aclli]->name     = $acldata[3];
		}

		return $list;
	}

	static function getLowerACLGroups( $group_id )
	{
		$db = JFactory::getDBO();

		$db->setQuery(
			'SELECT g2.id'
			. ' FROM #__core_acl_aro_groups AS g1'
			. ' INNER JOIN #__core_acl_aro_groups AS g2'
				. ' ON g1.lft > g2.lft AND g1.rgt < g2.rgt'
			. ' WHERE g1.id = '.$group_id
			. ' GROUP BY g2.id'
			. ' ORDER BY g2.lft'
		);

		return xJ::getDBArray($db);
	}

	static function getHigherACLGroups( $group_id )
	{
		$db = JFactory::getDBO();

		$db->setQuery(
			'SELECT g2.id'
			. ' FROM #__core_acl_aro_groups AS g1'
			. ' INNER JOIN #__core_acl_aro_groups AS g2'
				. ' ON g1.lft < g2.lft AND g1.rgt > g2.rgt'
			. ' WHERE g1.id = '.$group_id
			. ' GROUP BY g2.id'
			. ' ORDER BY g2.lft'
		);

		return xJ::getDBArray($db);
	}
}

class xJSessionHandler extends xJSessionHandlerCommon
{
	function instantGIDchange( $userid, $gid, $removegid=array(), $sessionextra=null )
	{
		$user = JFactory::getUser();

		if ( !is_array($gid) && !empty($gid) ) {
			$gid = array($gid);
		} elseif ( empty($gid) ) {
			$gid = array();
		}

		if ( !is_array($removegid) && !empty($removegid) ) {
			$removegid = array($removegid);
		}

		if ( !empty($removegid )) {
			xJACLhandler::removeGIDs((int) $userid, $removegid);
		}

		$info = array();

		// Set GID and usertype
		if ( !empty($gid) ) {
			$info = xJACLhandler::setGIDs((int) $userid, $gid);
		}

		$session = $this->getSession($userid);

		if ( empty($session) ) {
			return true;
		}

		if ( !empty($sessionextra) && is_array($sessionextra) ) {
			foreach ($sessionextra as $sk => $sv) {
				$session['user']->$sk = $sv;

				if ($userid == $user->id) {
					$user->$sk = $sv;
				}
			}
		}

		if ( isset($session['user']) && !empty($gid[0]) ) {
			$session['user']->gid      = $gid[0];
			$session['user']->usertype = $info[$gid[0]];

			if ($userid == $user->id) {
				$user->gid      = $gid[0];
				$user->usertype = $info[$gid[0]];
			}
		}

		return $this->putSession($userid, $session, $gid[0], $info[$gid[0]]);
	}

	function putSession( $userid, $data, $gid=null, $gid_name=null )
	{
		$db = JFactory::getDBO();

		$sdata = $this->joomserializesession( array($this->sessionkey => $data) );

		if ( isset($data['user']) ) {
			if ( empty($gid) ) {
				$db->setQuery(
					'UPDATE #__session'
					. ' SET `data` = \''.xJ::escape($db, $sdata).'\''
					. ' WHERE `userid` = \''.(int) $userid.'\''
				);
			} else {
				$db->setQuery(
					'UPDATE #__session'
					. ' SET `gid` = \''.(int) $gid.'\','
						. ' `usertype` = \''.$gid_name.'\','
						. ' `data` = \''.xJ::escape($db, $sdata).'\''
					. ' WHERE `userid` = \''.(int) $userid.'\''
				);
			}
		}

		return $db->query() or die($db->stderr());
	}

}


?>
