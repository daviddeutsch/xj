<?php

class xJLanguageHandlerCommon
{
	static function getSystemLanguages()
	{
		$fdir = JPATH_SITE.'/language';

		$list = xJUtility::getFileArray($fdir, null, true, true);

		$adir = JPATH_SITE.'/administrator/language';

		$list = array_merge( $list, xJUtility::getFileArray($adir, null, true, true) );

		$languages = array();
		foreach ( $list as $li ) {
			if ( (strpos($li, '-') !== false) && !in_array($li, $languages) ) {
				$languages[] = $li;
			}
		}

		return $languages;
	}
}

class xJACLhandlerCommon
{
	static function getAdminGroups( $regular_admins=true )
	{
		$db = JFactory::getDBO();

		$db->setQuery(
			'SELECT `id`'
			. ' FROM #__usergroups'
			. ' WHERE `id` = 8'
			. ($regular_admins ? ' OR `id` = 7' : '')
		);

		return xJ::getDBArray($db);
	}

	static function getManagerGroups()
	{
		// Thank you, I hate myself /quite/ enough
		return array(6);
	}

	static function getUsersByGroup( $groups )
	{
		$acl = JFactory::getACL();

		if ( !is_array($groups) ) $groups = array($groups);

		$users = array();
		foreach ( $groups as $group_id ) {
			$users = array_merge( $users, $acl->getUsersByGroup($group_id) );
		}

		return array_unique($users);
	}

	static function getUserObjects( $users )
	{
		$db = JFactory::getDBO();

		$db->setQuery(
			'SELECT `id`, `name`, `email`, `sendEmail`'
			. ' FROM #__users'
			. ' WHERE id IN ('.implode(',', $users).')'
		);

		return $db->loadObjectList();
	}

	static function removeGIDs( $userid, $gids )
	{
		$db = JFactory::getDBO();

		foreach ( $gids as $gid ) {
			$db->setQuery(
				'DELETE'
				. ' FROM #__user_usergroup_map'
				. ' WHERE `user_id` = \''.((int)$userid).'\''
				. ' AND `group_id` = \''.((int)$gid).'\''
			);

			$db->query();
		}
	}

	static function setGIDs( $userid, $gids )
	{
		$info = array();
		foreach ( $gids as $gid ) {
			$info[$gid] = xJACLhandler::setGIDsTakeNames($userid, $gid);

			xJACLhandler::setGID($userid, $gid, $info[$gid]);
		}

		return $info;
	}
}

class xJSessionHandlerCommon
{
	// The following two functions copied from joomla to circle around their hardcoded caching

	function getGroupsByUser( $userId, $recursive=true )
	{
		$db	= JFactory::getDBO();

		// Build the database query to get the rules for the asset.
		$query = $db->getQuery(true);

		$query->select($recursive ? 'b.id' : 'a.id');
		$query->from('#__user_usergroup_map AS map');
		$query->where('map.user_id = '.(int) $userId);
		$query->leftJoin('#__usergroups AS a ON a.id = map.group_id');

		// If we want the rules cascading up to the global asset node we need a self-join.
		if ( $recursive ) {
			$query->leftJoin('#__usergroups AS b ON b.lft <= a.lft AND b.rgt >= a.rgt');
		}

		// Execute the query and load the rules from the result.
		$db->setQuery($query);
		$result	= xJ::getDBArray($db);

		// Clean up any NULL or duplicate values, just in case
		JArrayHelper::toInteger($result);

		if ( empty($result) ) {
			$result = array('1');
		} else {
			$result = array_unique($result);
		}

		return $result;
	}

	function getAuthorisedViewLevels( $userId )
	{
		// Get all groups that the user is mapped to recursively.
		$groups = self::getGroupsByUser($userId);

		$viewLevels = array();

		// Only load the view levels once.
		if ( empty($viewLevels) ) {
			// Get a database object.
			$db	= JFactory::getDBO();

			// Build the base query.
			$query	= $db->getQuery(true);
			$query->select('id, rules');
			$query->from('`#__viewlevels`');

			// Set the query for execution.
			$db->setQuery((string) $query);

			// Build the view levels array.
			foreach ($db->loadAssocList() as $level) {
				$viewLevels[$level['id']] = (array) json_decode($level['rules']);
			}
		}

		// Initialise the authorised array.
		$authorised = array(1);

		// Find the authorized levels.
		foreach ( $viewLevels as $level => $rule ) {
			foreach ( $rule as $id ) {
				// Check to see if the group is mapped to the level.
				if ( ($id < 0) && (($id * -1) == $userId) ) {
					$authorised[] = $level;
					break;
				} elseif ( ($id >= 0) && in_array($id, $groups) ) {
					$authorised[] = $level;
					break;
				}
			}
		}

		return $authorised;
	}

	function getSession( $userid )
	{
		$db = JFactory::getDBO();

		$db->setQuery(
			'SELECT data'
			. ' FROM #__session'
			. ' WHERE `userid` = \''.(int) $userid.'\''
		);

		$data = $db->loadResult();

		if ( !empty($data) ) {
			$session = $this->joomunserializesession($data);

			$key = array_pop( array_keys($session) );

			$this->sessionkey = $key;

			return $session[$key];
		} else {
			return array();
		}
	}

	function joomunserializesession( $data )
	{
		$se = explode("|", $data, 2);

		if ( isset($se[1]) ) {
			return array(
				$se[0] => unserialize($se[1])
			);
		} elseif ( isset($se[0]) ) {
			return array(
				$se[0] => array()
			);
		} else {
			return array();
		}
	}

	function joomserializesession( $data )
	{
		$key = array_pop( array_keys($data) );

		return $key."|".serialize($data[$key]);
	}
}
