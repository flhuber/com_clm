<?php
/**
 * @ Chess League Manager (CLM) Component 
 * @Copyright (C) 2008-2023 CLM Team.  All rights reserved
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link http://www.chessleaguemanager.de
 * @author Thomas Schwietert
 * @email fishpoke@fishpoke.de
 * @author Andreas Dorn
 * @email webmaster@sbbl.org
*/
defined('clm') or die('Restricted access');

$mainframe	= JFactory::getApplication();
// Variablen holen
$sid 	= clm_core::$load->request_int('saison','1');
$lid 	= clm_core::$load->request_int('liga');
$zps 	= clm_core::$load->request_string('zps');
$gid 	= clm_core::$load->request_int('gid');
$count 	= clm_core::$load->request_int('count');

	$option = clm_core::$load->request_string('option' );
	$db	= JFactory::getDBO();

$user 		=JFactory::getUser();
$meldung 	= $user->get('id');

// Prüfen ob Datensatz schon vorhanden ist
	$query	= "SELECT * "
		." FROM #__clm_rangliste_id "
		." WHERE sid = $sid AND zps = '$zps' AND gid = $gid ";
	$db->setQuery( $query );
	$abgabe=$db->loadObjectList();
	if (isset($abgabe[0])) $sg_zps = $abgabe[0]->sg_zps; else $sg_zps = '0';

	$today = date("Y-m-d");
	// Datum der Erstellung
	$date =JFactory::getDate();
	$now = $date->toSQL();
/* if (count($abgabe) > 0) {

	$link = 'index.php?option=com_clm&view=info';
	$msg = JText::_( '<h2>Diese Rangliste wurde bereits abgegeben ! </h2>Bitte schauen Sie in die entsprechende Mannschaftsübersicht' );
	$mainframe->redirect( $link, $msg);
 			}
*/
	// evtl. vorhandene Daten in der Tabelle löschen
	$query	=" DELETE FROM #__clm_rangliste_id "
		." WHERE gid = ".$gid
		." AND sid = ".$sid
		." AND zps = '$zps'"
		;
	$db->setQuery($query);
	clm_core::$db->query($query);

	$query	=" DELETE FROM #__clm_rangliste_spieler "
		." WHERE Gruppe = ".$gid
		." AND sid = ".$sid
		." AND ZPS = '$zps'"
		;
	$db->setQuery($query);
	clm_core::$db->query($query);

	$query	=" DELETE FROM #__clm_meldeliste_spieler "
		." WHERE status = ".$gid
		." AND sid = ".$sid
		." AND (ZPS = '$zps' OR ZPS = '$sg_zps') "
		;
	$db->setQuery($query);
	clm_core::$db->query($query);

	// Liganummer ermitteln
	$query	=" SELECT liga FROM #__clm_mannschaften "
		." WHERE zps = '$zps'"
		." GROUP BY man_nr"
		;
	$db->setQuery($query);
	$lid_rang	= $db->loadObjectList();

	// Datum und Uhrzeit für Meldung
	$date =JFactory::getDate();
	$now = $date->toSQL();

	// Datensätze schreiben
	$liga_count	= 0;
	$liga		= $lid_rang[0]->liga;
	$change		= clm_core::$load->request_string('MA0');

	for ($y=0; $y < $count; $y++) {
		$ZPSmgl	= trim(clm_core::$load->request_string('ZPSM'.$y));
		$mgl	= clm_core::$load->request_string('MGL'.$y);
		$pkz	= clm_core::$load->request_string('PKZ'.$y);
		$mnr	= clm_core::$load->request_string('MA'.$y);
		$rang	= clm_core::$load->request_string('RA'.$y);

		if ($y !="0" AND $mnr > $change) {
			$liga_count++;
			if (isset($lid_rang[$liga_count])) $liga = $lid_rang[$liga_count]->liga;
			else $liga = -1;
		}
		$change	= $mnr;
		if (is_null($pkz) OR $pkz == '' OR $pkz == ' ') $pkz = '0';
		if ($mnr !=="99" AND $mnr !=="0" AND $mnr !=="") {
			$query = " INSERT INTO #__clm_rangliste_spieler "
				." (`Gruppe`, `ZPS`, `ZPSmgl`, `Mgl_Nr`, `PKZ`, `Rang`, `man_nr`, `sid`) "
				." VALUES ('$gid','$zps','$ZPSmgl','$mgl','$pkz','$rang','$mnr','$sid') "
			;					   
			clm_core::$db->query($query);
			if ($liga > -1) {
				$query = " INSERT INTO #__clm_meldeliste_spieler "
					." (`sid`, `lid`, `mnr`, `snr`, `mgl_nr`, `zps`,`status`) "
					." VALUES ('$sid','$liga','$mnr','$rang','$mgl','$ZPSmgl','$gid') "
				;					   
				clm_core::$db->query($query);
			}
		}
	}

	$query = " INSERT INTO #__clm_rangliste_id "
		." (`gid`, `sid`, `zps`, `sg_zps`, `rang`, `published`, `bemerkungen`, `bem_int`) "
		." VALUES ('$gid','$sid','$zps','$sg_zps','0','0','','') "
		;
	$db->setQuery($query);
	clm_core::$db->query($query);

	// Log schreiben
	$jid_aktion =  ($user->get('id'));
	$aktion = "Die Rangliste wurde im FE gespeichert!";
	$callid = uniqid ( "", false );
	$userid = clm_core::$access->getId ();	
	$parray = array('sid' => $sid, 'gid' => $gid, 'zps' => $zps);
	$query	= "INSERT INTO #__clm_logging "
		." ( `callid`, `userid`, `timestamp` , `type` ,`name`, `content`) "
		." VALUES ('".$callid."','".$userid."',".time().",5,'".$aktion."','".json_encode($parray)."') "
		;					   
	clm_core::$db->query($query);


	$msg = '<h5>'.JText::_( 'Die Rangliste wurde gespeichert!' ).'</h5>';
	$mainframe->enqueueMessage( $msg, 'message' );
	
	
// Mails verschicken ?
	// Konfigurationsparameter auslesen
	$config = clm_core::$db->config();
	// Zur Abwärtskompatibilität mit CLM <= 1.0.3 werden alte Daten aus Language-Datei als Default eingelesen
	$from = $config->email_from;
	$fromname = $config->email_fromname;
	$bcc	= $config->email_bcc;
	$bcc_mail	= $config->bcc;
	$sl_mail	= $config->sl_mail;
	$countryversion = $config->countryversion;
	
	if (!clm_core::$load->is_email($bcc)) $bcc = NULL;
	$send = 1;
	if (!clm_core::$load->is_email($from)) $send = 0;
	elseif ($fromname == '') $send = 0;

if ( $send == 1 ) {
	
// nur wegen sehr leistungsschwachen Providern
	$query	= " SET SQL_BIG_SELECTS=1";
	$db->setQuery($query);
	clm_core::$db->query($query);
	
// Daten für Email sammeln
// Melder
	$query	= "SELECT a.* FROM #__clm_user as a "
		." WHERE a.sid =".$sid
		."   AND a.jid =".$jid_aktion
		;
	$db->setQuery($query);
	$melder = $db->loadObjectList();
// Saison
	$query	= "SELECT a.* FROM #__clm_saison as a "
		." WHERE a.id =".$sid
		;
	$db->setQuery($query);
	$saison = $db->loadObjectList();
// Ranglisten
	$query	= "SELECT r.*, g.Gruppe, g.Meldeschluss FROM #__clm_rangliste_id as r "
		." LEFT JOIN #__clm_rangliste_name as g ON g.id = r.gid "  
		." WHERE r.sid = ".$sid
		." AND r.gid = ".$gid
		." AND r.zps = '".$zps."'"
		;
	$db->setQuery($query);
	$rangliste_id = $db->loadObjectList();
// Ligen mit Daten SL
	$query	= "SELECT l.*, u.email as sl_email, u.name as sl_name FROM #__clm_liga as l "
		." LEFT JOIN #__clm_rangliste_name as g ON g.id = l.rang "  
		." LEFT JOIN #__clm_user as u ON u.jid = l.sl AND u.sid = l.sid "  
		." WHERE l.rang =".$gid
		." AND l.published = 1 "
		;
	$db->setQuery($query);
	$ligen = $db->loadObjectList();
	$str_ligen = '';
	$a_ligen = array();
	foreach ($ligen as $liga1) {
		$str_ligen .= $liga1->id.",";
		$a_ligen[$liga1->id] = new stdClass();
		$a_ligen[$liga1->id]->sl_email = $liga1->sl_email;
		$a_ligen[$liga1->id]->sl_name = $liga1->sl_name;;
		$a_ligen[$liga1->id]->name = $liga1->name;;
	}
	if ($str_ligen != '') $str_ligen = substr($str_ligen,0,-1);
// Mannschaften mit Daten ML
	$query	= "SELECT a.*, u.email as mf_email, u.name as mf_name, Vereinname FROM #__clm_mannschaften as a "
		." LEFT JOIN #__clm_user as u ON u.jid = a.mf AND u.sid = a.sid "  
		." LEFT JOIN #__clm_dwz_vereine as v ON (v.sid = a.sid AND v.ZPS = a.zps) "
		." WHERE a.sid =".$sid
		." AND (FIND_IN_SET ( a.liga, '".$str_ligen."' ) != 0) "
		." AND a.zps = '".$zps."'"
		." ORDER BY a.liga, a.man_nr "
		;
	$db->setQuery($query);
	$mannschaft = $db->loadObjectList();
// Spielerrangliste
	$query	= "SELECT a.*, p.DWZ as pDWZ, Spielername, Vereinname FROM #__clm_rangliste_spieler as a";
	if ($countryversion =="de") {
		$query .= " LEFT JOIN #__clm_dwz_spieler as p ON (p.sid = a.sid AND p.ZPS = a.ZPSmgl AND p.Mgl_Nr = a.mgl_nr) ";
	} else{
		$query .= " LEFT JOIN #__clm_dwz_spieler as p ON (p.sid = a.sid AND p.ZPS = a.ZPSmgl AND p.PKZ = a.PKZ) ";
	}
	$query .= " LEFT JOIN #__clm_dwz_vereine as v ON (v.sid = a.sid AND v.ZPS = a.ZPSmgl) "
		." WHERE a.sid = ".$sid
		." AND a.Gruppe = ".$gid
		." AND a.ZPS = '".$zps."'"
		." ORDER BY a.man_nr, a.Rang ASC "
		;
	$db->setQuery($query);
	$rangliste=$db->loadObjectList();

// Mailbody HTML Header
	$body_html_header = '
			<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
			<html>
			<head>
			<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
			<title>'.JText::_( 'CLUB_LIST_MAIL_HEADLINE' ).'</title>
			</head>
			<body>';
	$body_html_footer = '
			</body>
			</html>';	
// Mailbody HTML Ranglisteneingabe oder -änderung
	$body_html =	'
		<table width="700" border="0" cellspacing="0" cellpadding="3" style="font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 11px;">
		<tr>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td bgcolor="#F2F2F2" style="border-bottom: solid 1px #000000; border-top: solid 1px #000000; padding: 3px;" colspan="6"><div align="center" style="font-size: 12px;"><strong>'.JText::_( 'CLUB_RANG_MAIL_HEADLINE' ).' '.JText::_( 'OF_DAY' ).JHTML::_('date', date("Y-m-d"), JText::_('DATE_FORMAT_CLM_F')). '</strong></div></td>
		</tr>
		<tr>
			<td bgcolor="#F2F2F2" style="border-bottom: solid 1px #000000; border-top: solid 1px #000000; padding: 3px;" colspan="6"><div align="center" style="font-size: 12px;"><strong>'.JText::_( 'CLUB_RANG_MAIL_HEADLINE' ).' '.JText::_( 'OF_DAY' ).JHTML::_('date', $now, JText::_('DATE_FORMAT_CLM_PDF')). '</strong></div></td>
		</tr>
		<tr>
			<td width="120">&nbsp;</td>
			<td>&nbsp;</td>
			<td width="5">&nbsp;</td>
			<td width="5">&nbsp;</td>
			<td width="80">&nbsp;</td>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td width="120" style="border-bottom: solid 1px #999999;"><strong>'.JText::_( 'CLUB_RANG_MAIL_RANG' ).'</strong></td>
			<td style="border-bottom: solid 1px #999999;">' .$rangliste_id[0]->Gruppe. '&nbsp;</td>
			<td width="5" style="border-bottom: solid 1px #999999;">&nbsp;</td>
			<td width="5" style="border-bottom: solid 1px #999999;">&nbsp;</td>
			<td width="80" style="border-bottom: solid 1px #999999;"><strong>'.JText::_( 'CLUB_LIST_MAIL_SEASON' ).'</strong></td>
			<td style="border-bottom: solid 1px #999999;">' .$saison[0]->name. '&nbsp;</td>
		</tr>
		<tr>
			<td width="120" style="border-bottom: solid 1px #999999;"><strong>'.JText::_( 'CLUB_LIST_MAIL_CLUB' ).'</strong></td>
			<td style="border-bottom: solid 1px #999999;">' .$mannschaft[0]->Vereinname. '&nbsp;</td>
			<td width="5" style="border-bottom: solid 1px #999999;">&nbsp;</td>
			<td width="5" style="border-bottom: solid 1px #999999;">&nbsp;</td>
			<td width="80" style="border-bottom: solid 1px #999999;"><strong>'.JText::_( '' ).'</strong></td>
			<td style="border-bottom: solid 1px #999999;">' .''. '&nbsp;</td>
		</tr>';
	
	$body_html .=	' 
		<tr>
			<td width="120">&nbsp;</td>
			<td>&nbsp;</td>
			<td width="5">&nbsp;</td>
			<td width="5">&nbsp;</td>
			<td width="80">&nbsp;</td>
			<td>&nbsp;</td>
		</tr>
		</table>';
	$body_html .=	'
		<table width="700" border="0" cellspacing="0" cellpadding="3" style="font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 11px;">
		<tr>
			<td width="50" bgcolor="#F2F2F2" style="border-bottom: solid 1px #000000; border-top: solid 1px #000000; padding: 3px;"><div align="center" style="font-size: 12px;"><strong>'.JText::_( 'CLUB_RANG_MAIL_MNR' ).'</strong></div></td>
			<td width="75" bgcolor="#F2F2F2" style="border-bottom: solid 1px #000000; border-top: solid 1px #000000; padding: 3px;"><div align="center" style="font-size: 12px;"><strong>'.JText::_( 'CLUB_RANG_MAIL_RNR' ).'</strong></div></td>
			<td width="210" bgcolor="#F2F2F2" style="border-bottom: solid 1px #000000; border-top: solid 1px #000000; padding: 3px;"><div align="center" style="font-size: 12px;"><strong>'.JText::_( 'CLUB_LIST_MAIL_NAME' ).'</strong></div></td>
			<td width="75" bgcolor="#F2F2F2" style="border-bottom: solid 1px #000000; border-top: solid 1px #000000; padding: 3px;"><div align="center" style="font-size: 12px;"><strong>'.JText::_( 'CLUB_LIST_MAIL_RATING' ).'</strong></div></td>
			<td width="75" bgcolor="#F2F2F2" style="border-bottom: solid 1px #000000; border-top: solid 1px #000000; padding: 3px;"><div align="center" style="font-size: 12px;"><strong>'.JText::_( 'CLUB_LIST_MAIL_NUMBER' ).'</strong></div></td>
			<td width="210" bgcolor="#F2F2F2" style="border-bottom: solid 1px #000000; border-top: solid 1px #000000; padding: 3px;"><div align="center" style="font-size: 12px;"><strong>'.JText::_( 'CLUB_LIST_MAIL_CLUBL' ).'</strong></div></td>
		</tr>
	';
	foreach ($rangliste as $rangliste1) {
			if ($rangliste1->Mgl_Nr > 0) {
				$body_html .=   '
					<tr>
					<td width="50" style="border-bottom: solid 1px #999999;"><div align="center"><strong>'.$rangliste1->man_nr.'</strong></div></td>
					<td width="75" style="border-bottom: solid 1px #999999;"><div align="center">' .$rangliste1->Rang. '&nbsp;</div></td>
					<td width="210" style="border-bottom: solid 1px #999999;"><div align="center">' .$rangliste1->Spielername. '&nbsp;</div></td>
					<td width="75" style="border-bottom: solid 1px #999999;"><div align="center">' .$rangliste1->pDWZ. '&nbsp;</div></td>
					<td width="75" style="border-bottom: solid 1px #999999;"><div align="center">' .str_pad($rangliste1->Mgl_Nr,3,"0",STR_PAD_LEFT). '&nbsp;</div></td>
					<td width="210" style="border-bottom: solid 1px #999999;"><div align="center">' .$rangliste1->Vereinname. '&nbsp;</div></td>
					</tr>
				';
			} 
	}
	$body_html .= 	  '
		<tr>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
		</tr>	
		</table>
		<table width="700" border="0" cellspacing="0" cellpadding="3" style="font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 11px;">
		<tr>
			<td width="120" style="border-bottom: solid 1px #999999;"><strong>'.JText::_( 'CLUB_LIST_MAIL_SENDER' ).'</strong></td>
			<td style="border-bottom: solid 1px #999999;">' .$melder[0]->name. '&nbsp;</td>
			<td width="5" style="border-bottom: solid 1px #999999;">&nbsp;</td>
			<td width="5" style="border-bottom: solid 1px #999999;">&nbsp;</td>
			<td width="80" style="border-bottom: solid 1px #999999;"><strong>'.JText::_( '' ).'</strong></td>
			<td style="border-bottom: solid 1px #999999;">' .''. '&nbsp;</td>
		</tr>';
	foreach ($mannschaft as $mannschaft1) { 
	  $z_liga = $mannschaft1->liga;
	  $body_html .=	'
		<tr>
			<td width="120" style="border-bottom: solid 1px #999999;"><strong>'.JText::_( 'CLUB_LIST_MAIL_LEAGUE' ).'</strong></td>
			<td style="border-bottom: solid 1px #999999;">' .$a_ligen[$z_liga]->name. '&nbsp;</td>
			<td width="5" style="border-bottom: solid 1px #999999;">&nbsp;</td>
			<td width="5" style="border-bottom: solid 1px #999999;">&nbsp;</td>
			<td width="80" style="border-bottom: solid 1px #999999;"><strong>'.JText::_( 'CLUB_LIST_MAIL_CONTROLLER' ).'</strong></td>
			<td style="border-bottom: solid 1px #999999;">' .$a_ligen[$z_liga]->sl_name. '&nbsp;</td>
		</tr>
		<tr>
			<td width="120" style="border-bottom: solid 1px #999999;"><strong>'.JText::_( 'CLUB_LIST_MAIL_TEAM' ).'</strong></td>
			<td style="border-bottom: solid 1px #999999;">' .$mannschaft1->name. '&nbsp;</td>
			<td width="5" style="border-bottom: solid 1px #999999;">&nbsp;</td>
			<td width="5" style="border-bottom: solid 1px #999999;">&nbsp;</td>
			<td width="80" style="border-bottom: solid 1px #999999;"><strong>'.JText::_( 'CLUB_LIST_MAIL_CAPTAIN' ).'</strong></td>
			<td style="border-bottom: solid 1px #999999;">' .$mannschaft1->mf_name. '&nbsp;</td>
		</tr>
		';
	}
	  $body_html .=	'  </table>';
	$subject = $fromname.': '.JTEXT::_('CLUB_RANG_SUBJECT').' '.$mannschaft[0]->Vereinname.'  '.$rangliste_id[0]->Gruppe.'   '.$saison[0]->name;

	$body_name = JText::_('RESULT_NAME').$melder[0]->name.",";
	$countmail = 0;

	// Textparameter setzen
	if (!is_null($abgabe) ) $erstmeldung = 0;	// Erstmeldung nein
	else $erstmeldung = 1;  						// Erstmeldung ja
	if ($rangliste_id[0]->Meldeschluss < $today) { $korr_moeglich = 0; 	// Korrektur möglich im FE nein
														$deadline_roster = ''; }
	else { $korr_moeglich = 1; 			// Korrektur möglich im FE ja
		$deadline_roster = JHTML::_('date', $rangliste_id[0]->Meldeschluss, JText::_('DATE_FORMAT_CLM_F')); }
	
	// Mail Melder
	if (isset($melder[0]->email) AND clm_core::$load->is_email($melder[0]->email)) {
		$recipient = $melder[0]->email;
		$body_html_md = '
		<table width="700" border="0" cellspacing="0" cellpadding="3" style="font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 12px;">
		<tr>
		  <td>'.JText::_('CLUB_RANG_MAIL_MD1').'</td>
  		</tr>
		<tr>
		  <td>';
		if ($erstmeldung == 1)  $body_html_md .= JText::_('CLUB_LIST_MAIL_MD2'); 
		else $body_html_md .= JText::_('CLUB_LIST_MAIL_MD2A');
		$body_html_md .= '</td>
		</tr>
		<tr>';
		if ($korr_moeglich == 1) { 
			if ($erstmeldung == 1)  $body_html_md .= '<td>'.JText::_('CLUB_LIST_MAIL_MD3').$deadline_roster.'</td></tr><tr>'; 
							  else $body_html_md .= '<td>'.JText::_('CLUB_LIST_MAIL_MD3A').$deadline_roster.'</td></tr><tr>'; }
		$body_html_md .= '</tr>
		</table>
		';
		$body_name = JText::_('RESULT_NAME').$melder[0]->name.",";
		$body = $body_html_header.$body_name.$body_html_md.$body_html.$body_html_footer;
		jimport( 'joomla.mail.mail' );
		$mail = JFactory::getMailer();
		$mail->sendMail($from,$fromname,$recipient,$subject,$body,1,null,$bcc);
		$countmail++;
	}
	
		// Mail Mannschaftsleiter
	foreach ($mannschaft as $mannschaft1) { 
	  if (isset($mannschaft1->mf_email) AND clm_core::$load->is_email($mannschaft1->mf_email)) {
		$recipient = $mannschaft1->mf_email;
		$body_html_mf = '
		<table width="700" border="0" cellspacing="0" cellpadding="3" style="font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 12px;">
		<tr>
		  <td>';
		if ($erstmeldung == 1)  $body_html_mf .= JText::_('CLUB_RANG_MAIL_MF1'); 
		else $body_html_mf .= JText::_('CLUB_RANG_MAIL_MF1A');
		$body_html_mf .= '</td>
  		</tr>
		<tr>
		  <td>'.JText::_('CLUB_LIST_MAIL_MF2').'</td>
		</tr>
		<tr>';
		if ($korr_moeglich == 1) { 
			if ($erstmeldung == 1)  $body_html_mf .= '<td>'.JText::_('CLUB_LIST_MAIL_MF3').$deadline_roster.'</td></tr><tr>'; 
							  else $body_html_mf .= '<td>'.JText::_('CLUB_LIST_MAIL_MF3A').$deadline_roster.'</td></tr><tr>'; }
		$body_html_mf .= '</tr>
		</table>
		';
		$body_name = JText::_('RESULT_NAME').$mannschaft1->mf_name.",";
		$body = $body_html_header.$body_name.$body_html_mf.$body_html.$body_html_footer;
		jimport( 'joomla.mail.mail' );
		$mail = JFactory::getMailer();
		$mail->sendMail($from,$fromname,$recipient,$subject,$body,1,null,$bcc);
		$countmail++;
	  }
	}

	// Mail Staffelleiter
	foreach ($mannschaft as $mannschaft1) { 
	  $z_liga = $mannschaft1->liga;
	  if ($sl_mail == 1 AND isset($a_ligen[$z_liga]->sl_email) AND clm_core::$load->is_email($a_ligen[$z_liga]->sl_email)) {
		$recipient = $a_ligen[$z_liga]->sl_email;
		$body_html_sl = '
		<table width="700" border="0" cellspacing="0" cellpadding="3" style="font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 12px;">
		<tr>';
			if ($erstmeldung == 1)  $body_html_sl .= '<td>'.JText::_('CLUB_RANG_MAIL_SL1').'</td>'; 
							  else $body_html_sl .= '<td>'.JText::_('CLUB_RANG_MAIL_SL1A').'</td>'; 
		$body_html_sl .= '</tr>
		<tr>
		  <td>'.JText::_('CLUB_LIST_MAIL_SL2').'</td>
		</tr>
		<tr>
		  <td>'.JText::_('CLUB_LIST_MAIL_SL3').'</td>
		</tr>
		<tr> </tr>
		</table>
		';
		$body_name = JText::_('RESULT_NAME').$a_ligen[$z_liga]->sl_name.",";
		$body = $body_html_header.$body_name.$body_html_sl.$body_html.$body_html_footer;
		jimport( 'joomla.mail.mail' );
		$mail = JFactory::getMailer();
		$mail->sendMail($from,$fromname,$recipient,$subject,$body,1,null,$bcc);
		$countmail++;
	  }
	}

	// Mail Admin 
	if (clm_core::$load->is_email($bcc_mail)) {
		$recipient = $bcc_mail;
		$body_html_ad = '
		<table width="700" border="0" cellspacing="0" cellpadding="3" style="font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 12px;">
		<tr>';
			if ($erstmeldung == 1)  $body_html_ad .= '<td>'.JText::_('CLUB_RANG_MAIL_AD1').'</td>'; 
							  else $body_html_ad .= '<td>'.JText::_('CLUB_RANG_MAIL_AD1A').'</td>'; 
		$body_html_ad .= '</tr>
		<tr>
		  <td>'.JText::_('CLUB_LIST_MAIL_AD2').'</td>
		</tr>
		<tr> </tr>
		</table>
		';
		$body_name = JText::_('RESULT_NAME').$bcc_name.",";
		$body = $body_html_header.$body_name.$body_html_ad.$body_html.$body_html_footer;
		jimport( 'joomla.mail.mail' );
		$mail = JFactory::getMailer();
		$mail->sendMail($from,$fromname,$recipient,$subject,$body,1);
		$countmail++;
	}
	$msg = "<h5>".$countmail++. ' '.JText::_( 'Mail wurden gesendet' )."</h5>";
	$mainframe->enqueueMessage( $msg, 'message' );

}	
	$mainframe->redirect( 'index.php' );
?>