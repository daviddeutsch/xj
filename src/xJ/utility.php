<?php
class xJUtility
{
	static function getFileArray( $dir, $extension=false, $listDirectories=false, $keepDots=false )
	{
		$dirArray	= array();
		$handle		= dir( $dir );

		while ( ( $file = $handle->read() ) !== false ) {
			if ( ( $file != '.' && $file != '..' ) || $keepDots ) {
				if ( !$listDirectories ) {
					if ( is_dir( $dir.'/'.$file ) ) {
						continue;
					}
				}
				if ( !empty( $extension ) ) {
					if ( !is_dir( $dir.'/'.$file ) ) {
						if ( strpos( basename( $file ), $extension ) === false ) {
							continue;
						}
					}
				}

				array_push( $dirArray, basename( $file ) );
			}
		}
		$handle->close();
		return $dirArray;
	}

	static function versionSort( $array )
	{
		// Bastardized Quicksort
		if ( !isset( $array[2] ) ) {
			return $array;
		}

		$piv = $array[0];
		$x = $y = array();
		$len = count( $array );
		$i = 1;

		while ( $i < $len ) {
			if ( version_compare( xJUtility::normVersionName( $array[$i] ), xJUtility::normVersionName( $piv ), '<' ) ) {
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
		$str = str_replace( "RC", "_", $name );

		$lastchar = substr( $str, -1, 1 );

		if ( !is_numeric( $lastchar ) ) {
			$str = substr( $str, 0, strlen( $str )-1 ) . "_" . ord( $lastchar );
		}

		return $str;
	}

}
?>
