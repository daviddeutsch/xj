<?php
class xJUtility
{
	static function getFileArray( $dir, $extension=false, $listDirectories=false, $keepDots=false )
	{
		$handle = dir($dir);

		$dirArray = array();
		while ( ($file=$handle->read()) !== false ) {
			if ( (($file == '.') || ($file == '..')) || !$keepDots ) continue;

			if ( !$listDirectories && is_dir($dir.'/'.$file) ) continue;

			if ( !empty($extension) && !is_dir($dir.'/'.$file) ) {
				if ( strpos( basename($file), $extension ) === false ) {
					continue;
				}
			}

			array_push( $dirArray, basename($file) );
		}

		$handle->close();

		return $dirArray;
	}

	/**
	 * Bastardized Quicksort
	 *
	 * @param $array
	 *
	 * @return array
	 */
	static function versionSort( $array )
	{
		if ( !isset($array[2]) ) return $array;

		$piv = $array[0];
		$x = $y = array();
		$len = count($array);
		$i = 1;

		while ( $i < $len ) {
			if (
				version_compare(
					xJUtility::normVersionName($array[$i]),
					xJUtility::normVersionName($piv),
					'<'
				)
			) {
				$x[] = $array[$i];
			} else {
				$y[] = $array[$i];
			}

			++$i;
		}

		return array_merge( xJUtility::versionSort($x), array($piv), xJUtility::versionSort($y) );
	}

	static function normVersionName( $name )
	{
		$str = str_replace("RC", "_", $name);

		$lastchar = substr($str, -1, 1);

		if ( !is_numeric($lastchar) ) {
			$str = substr ($str, 0, strlen($str)-1 )."_".ord($lastchar);
		}

		return $str;
	}

	static function deleteAdminMenuEntries( $component )
	{
		$db = JFactory::getDBO();

		if ( defined( 'JPATH_MANIFESTS' ) ) {
			$db->setQuery(
				'DELETE FROM #__extensions'
				. ' WHERE `element` LIKE "%'.$component.'%"'
				. ' OR `element`=\''.$component.'\''
			);
		} else {
			$db->setQuery(
				'DELETE FROM #__components'
				. ' WHERE `option` LIKE "%option='.$component.'%"'
				. ' OR `option`=\''.$component.'\''
			);
		}

		$db->query() or die($db->stderr());
	}

	static function createAdminMenuEntry( $component, $entry )
	{
		// Create new entry
		$return = self::AdminMenuEntry($component, $entry, 0, 0, 1);

		if ( $return === true ) {
			return $return;
		} else {
			return array($return);
		}
	}

	static function populateAdminMenuEntry( $array, $component )
	{
		$db = JFactory::getDBO();

		$details = array();
		if ( defined('JPATH_MANIFESTS') ) {
			// get id from component entry
			$db->setQuery(
				'SELECT `id`'
				. ' FROM #__extensions'
				. ' WHERE `name` = \''.$component.'\''
			);

			$details['component_id'] = $db->loadResult();

			$k = 0;
			foreach ( $array as $entry ) {
				if ( self::AdminMenuEntry($component, $entry, $details, $k) ) {
					$k++;
				}
			}
		} else {
			// get id from component entry
			$db->setQuery(
				'SELECT `id`'
				. ' FROM #__components'
				. ' WHERE `link` = \'option='.$component.'\''
			);

			$details['component_id'] = $db->loadResult();

			$k = 0;
			foreach ( $array as $entry ) {
				if ( self::AdminMenuEntry($component, $entry, $details, $k) ) {
					$k++;
				}
			}
		}
	}

	static function AdminMenuEntry( $component, $entry, $details, $ordering, $frontend=0 )
	{
		$db = JFactory::getDBO();

		if ( defined( 'JPATH_MANIFESTS' ) ) {
			$insert = array(
				'menutype'     => 'menu',
				'title'        => $entry[1],
				'alias'        => $entry[1],
				'link'         => 'index.php?option='.$component.'&task='.$entry[0],
				'type'         => 'component',
				'published'    => 1,
				'parent_id'    => '',
				'component_id' => $details['component_id'],
				'img'          => 'class:component',
				'client_id'    => 1
			);

			$table = JTable::getInstance('menu');

			if (
				!$table->setLocation($details['parent_id'], 'last-child')
				|| !$table->bind($insert)
				|| !$table->check()
				|| !$table->store()
			) {
				return false;
			}
		} else {
			$insert = array(
				'id'              => '',
				'name'            => $entry[1],
				'link'            => $frontend ? ( 'option='.$component ) : '',
				'parent'          => $details['component_id'],
				'admin_menu_link' => 'option='.$component.'&task='.$entry[0],
				'admin_menu_alt'  => $entry[1],
				'option'          => $component,
				'ordering'        => isset( $entry[3] ) ? $entry[3] : $ordering,
				'admin_menu_img'  => $entry[2]
			);

			$db->setQuery(
				'INSERT INTO #__components'
				. ' (`'.implode( '`, `', array_keys( $insert ) ).'`)'
				. ' VALUES'
				. ' (\''.implode( '\', \'', array_values( $insert ) ).'\')'
			);

			$db->query() or die($db->stderr());
		}

		return null;
	}
}
