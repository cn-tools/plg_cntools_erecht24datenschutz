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
	var $_doAddHinweisMessage;
	var $_doPiwikRework;
	var $_doAddPiwikMessage;
	var $_doReworkTarget;
	//-------------------------------------------------------------------------
	function plgContentPlg_CNTools_ERecht24Datenschutz( &$subject, $config ){
		parent::__construct( $subject, $config );
		$this->_doAddHinweisMessage = true;
		$this->_doPiwikRework = true;
		$this->_doAddPiwikMessage = true;
		$this->_doReworkTarget = true;
	}
	//-------------------------------------------------------------------------
	function onContentPrepare($context, &$article, &$params, $page = 0){
		$regex = "#{ERecht24Datenschutz\b(.*?)\}(.*?){/ERecht24Datenschutz}#s";
		
		if (is_object($params))
		{
			if  (property_exists($params, 'plg_cntools_e24d_dopiwikrework'))
			{
				$this->_doPiwikRework = ($params->plg_cntools_e24d_dopiwikrework != '0');
			}
			if  (property_exists($params, 'plg_cntools_e24d_target'))
			{
				$this->_doReworkTarget = ($params->plg_cntools_e24d_target != '0');
			}
		}

		if (is_object($article) and property_exists($article, 'text'))
		{
			$article->text = preg_replace_callback($regex, array('plgContentPlg_CNTools_ERecht24Datenschutz', 'render'), $article->text, -1, $count );
		} else {
			$article = preg_replace_callback($regex, array('plgContentPlg_CNTools_ERecht24Datenschutz', 'render'), $article, -1, $count );
		}
	}
	/*-------------------------- onContentAfterSave -------------------------*/
	public function onExtensionBeforeSave($context, $table, $isNew)
	{
		$lResult = true;
		if (($table->enabled == 1) and ($table->element == 'plg_cntools_erecht24datenschutz'))
		{
			$params = json_decode($table->params);
			if ($params->plg_cntools_e24d_acknowledge != '1')
			{
				$table->_errors[] = 'Damit das Plug-In ordnungsgemäss funktioniert, kontollieren Sie bitte die Einstellungen im Reiter \'Basiseinstellungen\' zum Punkt \'Bestätigung\'!';
				$lResult = false;
			} elseif (($params->plg_cntools_e24d_erroremail_dosend == '1') and ($params->plg_cntools_e24d_erroremail == ''))
			{
				$table->_errors[] = 'Bei aktiviertem Errorreporting muss eine gültige Emailadresse hinterlegt werden!';
				$lResult = false;
			}
		}
		
		return $lResult;
	}
	//------------------------------------------------------------------------
	function render(&$matches){
		if ($this->params->get('plg_cntools_e24d_acknowledge', '0') != '1')
		{
			if ($this->_doAddHinweisMessage)
			{
				JFactory::getApplication()->enqueueMessage('Bitte bestätigen Sie zuerst in den Einstellungen des Plug-In\'s, dass Sie die Hinweise zum Datenschutzgenerator gelesen haben und diese akzeptieren!', 'error');
				$this->_doAddHinweisMessage = false;
			}
			$lResult = '';
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
			$lReworkPiwik = false;
			if ($this->_doPiwikRework and strpos($phrase, 'piwik=1') !== false)
			{
				if ($this->params->get('plg_cntools_e24d_piwik_opt_out_link', '') == '')
				{
					if ($this->_doAddPiwikMessage)
					{
						JFactory::getApplication()->enqueueMessage('Piwik ist aktiv, jedoch sind die Einstellungen im Plug-In für die automatische Generierung der Datenschutzerklärung mit Hilfe von eRecht24.de nicht vollständig!<br />Bitte hinterlegen Sie im Reiter \'Piwik\' die notwendige URL für Opt-Out!', 'warning');
						$this->_doAddPiwikMessage = false;
					}
				} else {
					$lReworkPiwik = true;
				}
			}

			unset($stringJSONFull);
			unset($response);
			$http = JHttpFactory::getHttp(); 
			try
			{
				$lURL = $this->params->get('plg_cntools_e24d_protokoll', 'https') . '://www.e-recht24.de/plugins/content/disclaimermaker/assets/dmaker.php?acknowledge='.$this->params->get('plg_cntools_e24d_acknowledge', '0').$phrase;
				$response = $http->get($lURL, null, $this->params->get('plg_cntools_e24d_timeout', 6));
				if (isset($response) and ($response->code == 200))
				{
					$stringJSONFull = $response->body;
				}
			}
			catch (Exception $e)
			{
				unset($stringJSONFull);
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

				if ($this->params->get('plg_cntools_e24d_erroremail_dosend', '1') == '1')
				{
					$lErrorEmail = $this->params->get('plg_cntools_e24d_erroremail', '');
					if ($lErrorEmail != ''){
						$config = JFactory::getConfig();
						JFactory::getMailer()->sendMail($config->get('mailfrom'), 
														$config->get('fromname'), 
														$lErrorEmail, 
														$this->params->get('plg_cntools_e24d_erroremail_subject').' ('.$config->get('sitename').')', 
														$this->params->get('plg_cntools_e24d_erroremail_body')
														);
					}
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
			
			//-- link target rework -------------------------------------------
			if ($this->_doReworkTarget and $this->params->get('plg_cntools_e24d_target', '1') == '1')
			{
				$regex = "#(<a)(.*?)(<\\/a>)#is";
				$lResult = preg_replace_callback($regex, array('plgContentPlg_CNTools_ERecht24Datenschutz', 'renderTarget'), $lResult);
			}
			
			//-- PIWIK-IFRAME rework ------------------------------------------
			/* 
			<p><em><strong><a style="color:#F00;" href="http://piwik.org/docs/privacy/" rel="nofollow" target="_blank">[Hier PIWIK iframe-Code einfügen] (Klick für die Anleitung)</a></strong></em></p>
			*/
			if ($lReworkPiwik and (strpos($lResult, 'piwik.org') !== false))
			{
				$lPiwikUrl = $this->params->get('plg_cntools_e24d_piwik_opt_out_link');
				$lTag = $this->params->get('plg_cntools_e24d_piwik_tag', 'p');

				if (!class_exists ('simple_html_dom', false/*$autoload*/))
				{
					include_once('plugins/content/plg_cntools_erecht24datenschutz/assets/simple_html_dom.php');
				}

				if ($this->params->get('plg_cntools_e24d_piwik_language', '1') == '1')
				{
					$lang = JFactory::getLanguage();
					$langCode = '&language=' . mb_strtolower(substr($lang->getTag(), 0, 2));
					if (strpos($lPiwikUrl, $langCode) === false)
					{
						$lPiwikUrl .= $langCode;
					}
				}

				// load erecht24 data
				$lWorkDoc = new simple_html_dom();
				$lWorkDoc->load($lResult);

				// Find all p-tags
				$lPiwikCount = 0;
				foreach ($lWorkDoc->find('p') as $pelem)
				{
					// find a-tag with href to piwik
					$piwiklink = $pelem->find('a[href*=piwik.org]');
					if (isset($piwiklink) and (count($piwiklink)>>0))
					{
						$lPiwikCount = $lPiwikCount + 1;
						$lBorder = ' frameborder="' . $this->params->get('plg_cntools_e24d_piwik_border', '0') . '"';
						
						if ($this->params->get('plg_cntools_e24d_piwik_ver_type', 'auto') != 'auto')
						{
							$lHeight = ' height="' . $this->params->get('plg_cntools_e24d_piwik_ver_size', '200') . $this->params->get('plg_cntools_e24d_piwik_ver_type', 'px') . '"';
						} else {
							$document = JFactory::getDocument();
							$document->addScriptDeclaration('function plg_cntool_piwik_resizeIframe(obj){obj.style.height = obj.contentWindow.document.body.scrollHeight + \'px\';};');
							$lHeight = ' height="' . $this->params->get('plg_cntools_e24d_piwik_ver_size', '200') . 'px" onload="plg_cntool_piwik_resizeIframe(this);"';
						}

						$lText = '<' . $lTag . ' id=plg_cntools_e24d_piwik' . $lPiwikCount . '" class="plg_cntools_e24d_piwik"><iframe' . $lBorder . ' width="' . $this->params->get('plg_cntools_e24d_piwik_hor_size', '90') . $this->params->get('plg_cntools_e24d_piwik_hor_type', '%') . '"' . $lHeight . ' src="' . $lPiwikUrl . '" ></iframe></' . $lTag . '>';
						$pelem->outertext = $lText;
					}
				}
				$lResult = $lWorkDoc->outertext;
				$lWorkDoc->clear(); 
				unset($lWorkDoc);
			}
		}
		return $lResult;
	}
	
	//-- renderTarget ---------------------------------------------------------
	function renderTarget(&$matches)
	{
		$lResult = $matches[0];
		if (!strpos($lResult, ' target='))
		{
			if (strpos($lResult, ' href="'))
			{
				$lResult = str_ireplace(' href="', ' target="_blank" href="', $lResult);
			} 
			else if (strpos($lResult, " href='")) 
			{
				$lResult = str_ireplace(" href='", " target='_blank'  href='", $lResult);
			}
		}
		return $lResult;
	}
}
?>
