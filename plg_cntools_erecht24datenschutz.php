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
	//------------------------------------------------------------------------
	function plgContentPlg_CNTools_ERecht24Datenschutz( &$subject, $config ){
		parent::__construct( $subject, $config );
	}
	//------------------------------------------------------------------------
	function onContentPrepare($context, &$article, &$params, $page = 0){
		$regex = "#{ERecht24Datenschutz\b(.*?)\}(.*?){/ERecht24Datenschutz}#s";

		$article->text = preg_replace_callback( $regex, array('plgContentPlg_CNTools_ERecht24Datenschutz', 'render'), $article->text, -1, $count );
	}
	//------------------------------------------------------------------------
	function render(&$matches){
		if ($this->params->get('plg_cntools_e24d_acknowledge', '0') != '1')
		{
			$lResult = "<p><strong>Bitte bestätigen Sie zuerst in den Einstellungen des Plug-In's, dass Sie die Hinweise zum Datenschutzgenerator gelesen und akzeptiert haben.</strong></p>";
		} else {
			$lValue = htmlspecialchars_decode($matches[0]);
			$lValue = substr($lValue, 21);
			$lValue = substr($lValue, 0, strlen($lValue)-22);
			$lResult = $this->addContent($lValue);
		}

		return $lResult;
	}
	//------------------------------------------------------------------------
	private function GetRueckfallText($phrase, $txt){
		$param = 'plg_cntools_e24d_fallback_' + $txt;
		$txt .= '=1';
		$lResult = '';
		
		$pos = strpos($phrase, $txt);
		if ($pos <> false) {
			$lText = trim($this->params->get($param));
			if ($lText<>'') {
				$lResult .= '<p>'.$lText.'</p>';
			}
		}
		
		return $lResult;
	}
	//------------------------------------------------------------------------
	function addContent($phrase) {
		$lResult = '';
		if ($phrase!=''){
			$http = JHttpFactory::getHttp(); 
			try
			{
				$response = $http->get('http://www.e-recht24.de/plugins/content/disclaimermaker/assets/dmaker.php?acknowledge='.$this->params->get('plg_cntools_e24d_acknowledge', '0').$phrase, null, 6);
				
				$stringJSONFull = $response->body; 
			}
			catch (Exception $e)
			{
				$stringJSONFull = null;
			} 			

			if ((!isset($stringJSONFull)) or ($stringJSONFull[0]!='{')){
				$lResult = trim($this->params->get('plg_cntools_e24d_fallback'));
				if ($lResult<>''){
					$lResult = '<p>'.$lResult.'</p>';
				}
				
				$lResult .= $this->GetRueckfallText($phrase, 'standard');
				$lResult .= $this->GetRueckfallText($phrase, 'privacy');
				$lResult .= $this->GetRueckfallText($phrase, 'cookies');
				$lResult .= $this->GetRueckfallText($phrase, 'serverlogfiles');
				$lResult .= $this->GetRueckfallText($phrase, 'contactform');
				$lResult .= $this->GetRueckfallText($phrase, 'newsletter');
				$lResult .= $this->GetRueckfallText($phrase, 'analytics');
				$lResult .= $this->GetRueckfallText($phrase, 'etracker');
				$lResult .= $this->GetRueckfallText($phrase, 'piwik');
				$lResult .= $this->GetRueckfallText($phrase, 'facebook');
				$lResult .= $this->GetRueckfallText($phrase, 'twitter');
				$lResult .= $this->GetRueckfallText($phrase, 'plusone');
				$lResult .= $this->GetRueckfallText($phrase, 'instagram');
				$lResult .= $this->GetRueckfallText($phrase, 'linkedin');
				$lResult .= $this->GetRueckfallText($phrase, 'pinterest');
				$lResult .= $this->GetRueckfallText($phrase, 'xing');
				$lResult .= $this->GetRueckfallText($phrase, 'tumblr');
				$lResult .= $this->GetRueckfallText($phrase, 'youtube');
				//$lResult .= $this->GetRueckfallText($phrase, 'adsense');
				//$lResult .= $this->GetRueckfallText($phrase, 'remarketing');
				//$lResult .= $this->GetRueckfallText($phrase, 'amazon');
				//$lResult .= $this->GetRueckfallText($phrase, 'registration');
				//$lResult .= $this->GetRueckfallText($phrase, 'dataprocessing');
				//$lResult .= $this->GetRueckfallText($phrase, 'shops');
				//$lResult .= $this->GetRueckfallText($phrase, 'onlinecontracts');
				$lResult .= $this->GetRueckfallText($phrase, 'infodelete');
				$lResult .= $this->GetRueckfallText($phrase, 'advertemails');
				//$lResult .= $this->GetRueckfallText($phrase, 'translation_en');

				$lTxt = trim($this->params->get('plg_cntools_e24d_fallback_bottom'));
				if ($lTxt<>''){
					$lResult .= '<p>'.$lTxt.'</p>';
				}
				
				$lResult = trim($lResult);
				if ($lResult == '') {
					$lResult = '<p>Der Haftungsausschluss und/oder die Datenschutzerklärung von <a target="_blank" href="http://www.e-recht24.de">www.e-recht24.de</a> steht derzeit nicht zur Verfügung!<br />Sollte dieses Problem länger bestehen, kontaktieren Sie bitte den Betreiber dieser Homepage!</p>';
				}

				$lErrorEmail = $this->params->get('plg_cntools_e24d_erroremail');
				if ($lErrorEmail != ''){
					$config = JFactory::getConfig();
					JFactory::getMailer()->sendMail($config->get('mailfrom'), 
													$config->get('fromname'), 
													$lErrorEmail, 
													$this->params->get('plg_cntools_e24d_erroremail_subject').' ('.$config->get('sitename').')', 
													$this->params->get('plg_cntools_e24d_erroremail_body')
													);
				}
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
