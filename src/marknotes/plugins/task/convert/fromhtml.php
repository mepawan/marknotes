<?php
/**
 * Convert HTML to markdown
 *
 * @Link https://github.com/thephpleague/html-to-markdown
 */
namespace MarkNotes\Plugins\Task\Convert;

defined('_MARKNOTES') or die('No direct access allowed');

use League\HTMLToMarkdown\HtmlConverter;

class FromHTML extends \MarkNotes\Plugins\Task\Plugin
{
	protected static $me = __CLASS__;
	protected static $json_settings = 'plugins.task.convert';
	protected static $json_options = 'plugins.options.task.convert';

	/**
	 * Call the html-to-markdown library and make the conversion
	 * https://github.com/thephpleague/html-to-markdown
	 */
	private static function convert(string $HTML) : string
	{

		$libs = str_replace('/', DS, __DIR__.'/libs/html2md/');

		require_once($libs.'ConfigurationAwareInterface.php');
		require_once($libs.'Converter/ConverterInterface.php');

		$arr = glob($libs.'Converter/*.php');
		foreach ($arr as $file) {
			require_once($file);
		}

		$arr = array('Environment', 'ElementInterface',
			'Element', 'Configuration', 'HtmlConverter');

		foreach ($arr as $file) {
			require_once($libs.$file.'.php');
		}

		$converter = new HtmlConverter();

		$converter->getConfig()->setOption('header_style', 'atx');
		$converter->getConfig()->setOption('italic_style', '*');
		$converter->getConfig()->setOption('bold_style', '**');
		$converter->getConfig()->setOption('strip_tags', false);

		$markdown = trim($converter->convert($HTML));

		return $markdown;
	}

	public static function run(&$params = null) : bool
	{
		$aeFunctions = \MarkNotes\Functions::getInstance();

		if (is_file($filename = __DIR__.DS.'test.html')) {
			$sHTML = utf8_encode(file_get_contents($filename));
		} else {
			$sHTML = trim($aeFunctions->getParam('param', 'unsafe', '', false));
		}

		// Call html-to-markdown and make the conversion to MD
		$sMD = self::convert($sHTML);

		// Remove tags not proessed by html-to-markdown
		// The returned markdown string should no more contains
		// html tags
		$sMD = strip_tags($sMD);

		// Return the string
		header('Content-Type: text/plain; charset=utf-8');
		header('Content-Transfer-Encoding: ascii');
		echo $sMD;

		return true;
	}
}