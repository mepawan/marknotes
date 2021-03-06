<?php
/**
 * Search engine, search for keywords in notes and return the md5
 * of the filename. Then, the treeview (jstree) will filter on that
 * list and only show items with the same md5
 */
namespace MarkNotes\Plugins\Task\Search;

defined('_MARKNOTES') or die('No direct access allowed');

class Search
{
	/**
	 * Get the list of notes, relies on the listFiles task plugin for this
	 * in order to, among other things, be sure that only files that the
	 * user can access are retrieved and not confidential ones
	 */
	private static function getFiles() : array
	{
		$arrFiles = array();

		// Call the listfiles.get event and initialize $arrFiles
		$aeEvents = \MarkNotes\Events::getInstance();
		$args=array(&$arrFiles);
		$aeEvents->loadPlugins('task.listfiles.get');
		$aeEvents->trigger('task.listfiles.get::run', $args);

		return $args[0];
	}

	/**
	 * Used when no keyword has been mentionned on the url
	 * (f.i. http://localhost/notes/search.php?str=)
	 */
	private static function noParam() : boolean
	{
		$aeSettings = \MarkNotes\Settings::getInstance();
		$aeDebug = \MarkNotes\Debug::getInstance();

		/*<!-- build:debug -->*/
		if ($aeSettings->getDebugMode()) {
			$aeDebug->log('No pattern has been specified. The str=keyword parameter was missing', 'debug');
		}
		/*<!-- endbuild -->*/

		// Nothing should be returned, the list of files can be immediatly displayed
		header('Content-Type: application/json');
		die('[]');

		return false;
	}

	/**
	 * Get the content of the cache/search file if present
	 * @return string
	 */
	private static function getFromCache(string $fname) : array
	{
		$return = array();

		if (is_file($fname)) {
			// If we can use the session, do it ! This will really improve speed
			$json = file_get_contents($fname);

			if ($json!=='') {
				try {
					$return = json_decode($json, true);

					if ($return!=array()) {
						$aeSettings = \MarkNotes\Settings::getInstance();

						// Indicate it's coming from the cache
						$return['message'] = $aeSettings->getText('search_from_cache');

						/*<!-- build:debug -->*/
						if ($aeSettings->getDebugMode()) {
							$aeDebug = \MarkNotes\Debug::getInstance();
							$aeDebug->log("Get search's results from the cached ".
								"file ".$fname, "info");
						}
						/*<!-- endbuild -->*/
					}
				} catch (Exception $e) {
				} // try
			} // if ($json!=='')
		} // if (is_file($fname))

		return $return;
	}

	/**

	* $params['encryption'] = 0 : encrypted data should be displayed unencrypted
	*                         1 : encrypted infos should stay encrypted
	 */
	public static function run(&$params = null)
	{
		$aeFunctions = \MarkNotes\Functions::getInstance();

		// String to search (can be something like
		// 'invoices,2017,internet') i.e. multiple keywords
		$pattern = trim($aeFunctions->getParam('str', 'string', '', false, SEARCH_MAX_LENGTH));

		if ($pattern==='') {
			self::noParam();
		}

		// search will be case insensitive
		$pattern = strtolower($pattern);

		// $keywords can contains multiple terms like 'php,avonture,marknotes'.
		// Search for these three keywords (AND)
		$keywords = explode(',', rtrim($pattern, ','));

		// Speed : be sure to have the same keyword only once
		$keywords = $aeFunctions->array_iunique($keywords);

		// Sort keywords so the pattern will always be sorted
		// If $pattern was 'php,avonture,marknotes', thanks the sort, it will be
		// avonture,php,marknotes. Whatever the order, as from here, the $pattern
		// will always be sorted (=> optimization)
		sort($keywords);
		$pattern = implode($keywords, ',');

		$aeDebug = \MarkNotes\Debug::getInstance();
		$aeSession = \MarkNotes\Session::getInstance();
		$aeSettings = \MarkNotes\Settings::getInstance();
		$aeMarkdown = \MarkNotes\FileType\Markdown::getInstance();

		/*<!-- build:debug -->*/
		if ($aeSettings->getDebugMode()) {
			$aeDebug->log('Searching for ['.$pattern.']', 'debug');
		}
		/*<!-- endbuild -->*/

		$arrSettings = $aeSettings->getPlugins(JSON_OPTIONS_OPTIMIZE);
		$bOptimize = $arrSettings['cache_search_results'] ?? false;

		// Can we use the cache system ? Default is true
		$useCache = $aeFunctions->getParam('cache', 'bool', true);

		$arr = array();

		if ($bOptimize) {
			// Get the cache/search folder and if not present, create it
			$folder = $aeSettings->getFolderCache().'search';

			if (!is_dir($folder)) {
				mkdir($folder, CHMOD_FOLDER);
			}

			$fname = $folder.DS.md5($pattern).'.json';

			if ($useCache) {
				$arr = self::getFromCache($fname);
			}
		} // if ($bOptimize)

		if ($arr === array()) {
			// Retrieve the list of files
			$arrFiles = self::getFiles();

			// docs should be relative so $aeSettings->getFolderDocs(false) and not $aeSettings->getFolderDocs(true)
			$docs = str_replace('/', DS, $aeSettings->getFolderDocs(false));

			// This one will be absolute
			$docFolder = $aeSettings->getFolderDocs(true);

			$bDebug=false;
			/*<!-- build:debug -->*/
			if ($aeSettings->getDebugMode()) {
				$bDebug=true;
			}
			/*<!-- endbuild -->*/

			$return = array();

			foreach ($arrFiles as $file) {
				// Just keep relative filenames, relative from the /docs folder
				$file = str_replace($docFolder, '', $file);

				// If the keyword can be found in the document title,
				// yeah, it's the fatest solution,
				// return that filename
				foreach ($keywords as $keyword) {
					$bFound = true;
					if (stripos($file, $keyword) === false) {
						// at least one term is not present in the filename, stop
						$bFound = false;
						break;
					}
				} // foreach ($keywords as $keyword)

				if ($bFound) {
					// Found in the filename => stop process of this file

					/*<!-- build:debug -->*/
					if ($bDebug) {
						$aeDebug->log("   FOUND IN [".$docs.$file."]", "debug");
					}
					/*<!-- endbuild -->*/

					$return[] = $docs.$file;
				} else { // if ($bFound)
					// Open the file and check against its content
					// (plain and encrypted)
					$fullname = $docFolder.$file;

					// Read the note content
					// The read() method will fires any plugin linked
					// to the markdown.read event
					// so encrypted notes will be automatically unencrypted

					$params['filename']=$fullname;
					$params['encryption'] = 0;
					$content = $aeMarkdown->read($fullname, $params);

					$bFound = true;

					foreach ($keywords as $keyword) {
						/**
						* Add "$file" which is the filename in the
						* content, just for the search.
						* Because when f.i. search for two words;
						* one can be in the filename and one in the content
						* By searching only in the content; that file won't
						* appear while it should be the Collapse
						* so "fake" and add the filename in the content,
						* just for the search_no_result
						*/
						if (stripos($file.'#@#§§@'.$content, $keyword) === false) {
							// at least one term is not present in the content, stop
							$bFound = false;
							break;
						}
					} // foreach($keywords as $keyword)

					if ($bFound) {
						/*<!-- build:debug -->*/
						if ($bDebug) {
							$aeDebug->log("   FOUND IN [".$docs.$file."]", "debug");
						}
						/*<!-- endbuild -->*/

						// Found in the filename => stop process of this file
						$return[] = $docs.$file;
					}  // if ($bFound)
				} // if ($bFound) {
			} // foreach ($arrFiles as $file)

			$arr = array();
			$arr['pattern'] = $pattern;
			$arr['files'] = json_encode(array_map("md5", $return));

			if ($bOptimize) {
				$aeFiles = \MarkNotes\Files::getInstance();
				$aeFiles->fwriteANSI($fname, json_encode($arr));
			}
		}

		// Nothing should be returned, the list of files
		// can be immediatly displayed
		header('Content-Type: application/json');
		// Don't return filenames but the md5() of these names
		echo json_encode($arr);

		return true;
	}

	/**
	 * Attach the function and responds to events
	 */
	public function bind(string $task)
	{
		$aeEvents = \MarkNotes\Events::getInstance();
		$aeEvents->bind('run', __CLASS__.'::run', $task);
		return true;
	}
}
