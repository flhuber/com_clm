<?php
/**
 * @ Chess League Manager (CLM) Component 
 * @Copyright (C) 2008-2021 CLM Team.  All rights reserved
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link http://www.chessleaguemanager.de
 * @author Thomas Schwietert
 * @email fishpoke@fishpoke.de
 * @author Andreas Dorn
 * @email webmaster@sbbl.org
*/

defined('_JEXEC') or die();
jimport('joomla.application.component.model');

class CLMModelMitglieder_Details extends JModelLegacy
{
	function _getCLMSpieler ( &$options )
	{
	
	$sid 		= clm_core::$load->request_int('saison');
	$zps 		= clm_escape(clm_core::$load->request_string('zps'));
	$mgl		= clm_core::$load->request_int('mglnr');

	$db	= JFactory::getDBO();
	$id	= @$options['id'];
 
	$query = "SELECT a.*, s.name AS saison, v.name AS verein " 
			." FROM #__clm_dwz_spieler AS a"
			." LEFT JOIN #__clm_saison as s ON s.id = a.sid "
			." LEFT JOIN #__clm_vereine as v ON v.ZPS = a.ZPS "
			." WHERE a.ZPS = '$zps'"
			." AND a.sid = '$sid'"
			." AND a.Mgl_Nr = '$mgl'"
			;
		
	return $query;
	}
	function getCLMSpieler ( $options=array() )
	{
		$query	= $this->_getCLMSpieler ( $options );
		$result = $this->_getList( $query );
		return @$result;
	}
	
	function _getCLMVerein( &$options )
	{
	$zps = clm_escape(clm_core::$load->request_string('zps'));
	$db	= JFactory::getDBO();
	$id	= @$options['id'];
 
		$query = "SELECT name " 
			." FROM #__clm_vereine "
			." WHERE zps = '$zps'  "
			;
	return $query;
	}
	function getCLMVerein( $options=array() )
	{
		$query	= $this->_getCLMVerein( $options );
		$result = $this->_getList( $query );
		return @$result;
	}

	// Prüfen ob User berechtigt ist zu melden
	function _getCLMClmuser ( &$options )
	{
	$user	= JFactory::getUser();
	$jid	= $user->get('id');
	$sid	= clm_core::$load->request_int('saison','1');

		$db	= JFactory::getDBO();
		$id	= @$options['id'];

	$query	= "SELECT zps,published "
		." FROM #__clm_user "
		." WHERE jid = $jid "
		." AND sid = $sid "
		;
	return $query;
	}

	function getCLMClmuser ( $options=array() )
	{
		$query	= $this->_getCLMClmuser( $options );
		$result = $this->_getList( $query );
		return @$result;
	}

}
?>
