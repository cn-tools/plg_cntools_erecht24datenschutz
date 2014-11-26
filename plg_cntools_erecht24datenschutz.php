<?php
/**
 * ERecht24Disclaimer - Joomla Plugin
 *
 * @package    Joomla
 * @subpackage Plugin
 * @author Clemens Neubauer
 * @link https://github.com/cn-tools/
 * @license		GNU/GPL, see LICENSE.php
 * ERecht24Disclaimer is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 */

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

// Import library dependencies
jimport('joomla.plugin.plugin');

JHtml::_('jquery.framework');

class plgContentPlg_CNTools_ERecht24Datenschutz extends JPlugin{
	function plgContentPlg_CNTools_ERecht24Datenschutz( &$subject, $config ){
		parent::__construct( $subject, $config );

		// load plugin parameters
//		$this->_plugin = &JPluginHelper::getPlugin( 'content', 'plg_cntools_erecht24datenschutz' );
	}

	function onContentPrepare($context, &$article, &$params, $page = 0){
//		JPlugin::loadLanguage( 'plg_content_ERecht24Disclaimer', JPATH_ADMINISTRATOR );		//Load the plugin language file - not in contructor in case plugin called by third party components
//		$application = &JFactory::getApplication();

		$regex = "#{ERecht24Disclaimer\b(.*?)\}(.*?){/ERecht24Disclaimer}#s";

		$article->text = preg_replace_callback( $regex, array('plgContentPlg_CNTools_ERecht24Datenschutz', 'render'), $article->text, -1, $count );
	}

	function render(&$matches){
		$lValue = htmlspecialchars_decode($matches[0]);
		$lValue = substr($lValue, 20);
		$lValue = substr($lValue, 0, strlen($lValue)-21);
		$lResult = plgContentPlg_CNTools_ERecht24Datenschutz::addContent($lValue);

		return $lResult;
	}

	function addContent($phrase) {
		$lResult = '';
		if ($phrase!=''){
			$http = JHttpFactory::getHttp(); 
			try
			{
				$response = $http->get('http://www.e-recht24.de/plugins/content/disclaimermaker/dmaker.php?'.$phrase, null, 6);
				$stringJSONFull = $response->body; 
			}
			catch (Exception $e)
			{
				$stringJSONFull = null;
			} 			

			if ((!isset($stringJSONFull)) or ($stringJSONFull[0]!='{')){
				$lResult = '<p>Der Haftungsausschluss und/oder die Datenschutzerklärung von <a target="_blank" href="http://www.e-recht24.de">www.e-recht24.de</a> steht derzeit nicht zur Verfügung!</p><p>Sollte dieses Problem länger bestehen, kontaktieren Sie bitte den Betreiber dieser Homepage!</p>';
			} else {
				$stringJSONReady = json_decode($stringJSONFull);
				$lResult = $stringJSONReady->{'disclaimerpreview'};
				$lValue = $stringJSONReady->{'privacypreview'};
				if ($lValue!=''){
					if ($lResult!='') {
						$lResult .= '<p></p>';
					}
					$lResult .= $lValue;
				}
			}
		}
		return $lResult;
	}
}
?>
