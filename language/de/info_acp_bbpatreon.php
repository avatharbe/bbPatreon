<?php
/**
 *
 * Patreon-Integration für phpBB.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'ACP_BBPATREON_TITLE'	=> 'Patreon-Integration',
	'ACP_BBPATREON'			=> 'Einstellungen',

	'ACP_BBPATREON_SETTING_SAVED'	=> 'Patreon-Einstellungen wurden erfolgreich gespeichert!',
	'ACP_BBPATREON_HELP'			=> 'Wie funktioniert das?',

	'ACP_BBPATREON_OVERVIEW_TITLE'		=> 'Was diese Erweiterung macht',
	'ACP_BBPATREON_OVERVIEW'			=> 'Diese Erweiterung verbindet deine Patreon-Creatorseite mit deinem Forum. Forenmitglieder können ihr Patreon-Konto über ihr Benutzerkontrollzentrum verknüpfen. Nach der Verknüpfung werden sie automatisch phpBB-Benutzergruppen zugewiesen, basierend auf der Patreon-Stufe, auf der sie pledgen. Wenn ein Patron upgradet, downgradet oder kündigt, wird die Gruppenmitgliedschaft entsprechend aktualisiert.',

	'ACP_BBPATREON_HOW_IT_WORKS_TITLE'	=> 'Einrichtungsschritte',
	'ACP_BBPATREON_STEP_CREDENTIALS'	=> 'Gib unten deine Patreon API-Zugangsdaten ein. Du erhältst diese durch Erstellen eines OAuth-Clients auf <a href="https://www.patreon.com/portal/registration/register-clients" target="_blank">patreon.com/portal/registration/register-clients</a>. Setze die Redirect-URI auf: <strong>https://deinforum.de/patreon/callback</strong> (ersetze durch deine tatsächliche Forum-URL).',
	'ACP_BBPATREON_STEP_TIERS'			=> 'Ordne jede Patreon-Stufe einer phpBB-Benutzergruppe im Abschnitt Stufen-Zuordnung zu. Klicke auf "Stufen abrufen" um sie automatisch zu laden. Stelle dann die Forenberechtigungen für diese Gruppen ein.',
	'ACP_BBPATREON_STEP_WEBHOOK'		=> 'Registriere einen Webhook, damit Patreon dein Forum in Echtzeit benachrichtigt, wenn sich Pledges ändern. Dies ist optional aber empfohlen &mdash; ohne Webhooks werden Änderungen nur durch die nächtliche Synchronisation erfasst.',
	'ACP_BBPATREON_STEP_USERS'			=> 'Informiere deine Mitglieder, dass sie ihr Benutzerkontrollzentrum besuchen und auf "Patreon-Konto verknüpfen" klicken sollen. Nach der Verknüpfung werden Stufe und Gruppe automatisch synchronisiert.',

	'ACP_BBPATREON_SYNC_TITLE'			=> 'Wie die Synchronisation funktioniert',
	'ACP_BBPATREON_SYNC_EXPLAIN'		=> 'Die Gruppenmitgliedschaft wird über drei Mechanismen aktuell gehalten: <strong>OAuth-Verknüpfung</strong> (Gruppen werden sofort zugewiesen, wenn ein Benutzer sein Konto verknüpft), <strong>Webhooks</strong> (Patreon sendet Echtzeit-Benachrichtigungen bei Pledge-Änderungen), und eine <strong>Nächtliche Cron-Aufgabe</strong> (ein vollständiger Abgleich findet alle 24 Stunden als Sicherheitsnetz statt).',

	'ACP_BBPATREON_STATUSES_TITLE'		=> 'Pledge-Status',
	'ACP_BBPATREON_STATUS_ACTIVE'		=> 'Aktiv pledgend und Zahlung ist aktuell. Benutzer ist der Gruppe seiner Stufe zugewiesen.',
	'ACP_BBPATREON_STATUS_DECLINED'		=> 'Zahlung fehlgeschlagen, aber der Pledge wurde noch nicht gekündigt. Während der Gnadenfrist behält der Benutzer seine Gruppe.',
	'ACP_BBPATREON_STATUS_FORMER'		=> 'Pledge wurde gekündigt. Der Benutzer wird sofort aus seiner Patrongruppe entfernt (oder nach der Gnadenfrist).',
	'ACP_BBPATREON_STATUS_PENDING'		=> 'Patreon-Konto ist mit dem Forum verknüpft, aber der Benutzer ist derzeit kein Patron deiner Kampagne.',

	'ACP_BBPATREON_API_CREDENTIALS'			=> 'API-Zugangsdaten',
	'ACP_BBPATREON_API_CREDENTIALS_EXPLAIN'	=> 'Gib die Zugangsdaten deines Patreon API-Clients ein. Die Client ID und das Secret werden für den OAuth-Anmeldevorgang verwendet. Die Creator-Tokens werden für Server-zu-Server API-Aufrufe verwendet. Alle vier Werte stammen von <a href="https://www.patreon.com/portal/registration/register-clients" target="_blank">deiner Patreon API-Client-Seite</a>.',
	'ACP_BBPATREON_CLIENT_ID'				=> 'Client ID',
	'ACP_BBPATREON_CLIENT_ID_EXPLAIN'		=> 'Die OAuth Client ID aus deinem Patreon Developer Portal.',
	'ACP_BBPATREON_CLIENT_SECRET'			=> 'Client Secret',
	'ACP_BBPATREON_CREATOR_TOKEN'			=> 'Creator Access Token',
	'ACP_BBPATREON_CREATOR_TOKEN_EXPLAIN'	=> 'Wird für Server-zu-Server API-Aufrufe verwendet. Dieses Token wird automatisch erneuert, wenn es abläuft.',
	'ACP_BBPATREON_CREATOR_REFRESH'			=> 'Creator Refresh Token',
	'ACP_BBPATREON_CAMPAIGN_ID'				=> 'Kampagnen-ID',
	'ACP_BBPATREON_CAMPAIGN_ID_EXPLAIN'		=> 'Die numerische ID deiner Patreon-Kampagne. Speichere zuerst deine Creator-Tokens und klicke dann auf "Abrufen".',
	'ACP_BBPATREON_FETCH_CAMPAIGN'			=> 'Abrufen',
	'ACP_BBPATREON_CAMPAIGN_FETCHED'		=> 'Kampagnen-ID erfolgreich abgerufen: %s',
	'ACP_BBPATREON_CAMPAIGN_FETCH_ERROR'	=> 'Kampagnen-ID konnte nicht abgerufen werden. Überprüfe, ob das Creator Access Token gespeichert und gültig ist.',

	'ACP_BBPATREON_WEBHOOK'						=> 'Webhook',
	'ACP_BBPATREON_WEBHOOK_EXPLAIN'				=> 'Webhooks ermöglichen Echtzeit-Synchronisation. Wenn ein Patron einen Pledge erstellt, ändert oder kündigt, wird eine signierte HTTP-Anfrage an dein Forum gesendet.<br><br><strong>Option A (empfohlen):</strong> Gehe zu <a href="https://www.patreon.com/portal/registration/register-webhooks" target="_blank">patreon.com/portal/registration/register-webhooks</a>, erstelle einen Webhook mit der unten angezeigten URL und füge das Geheimnis ein.<br><strong>Option B:</strong> Klicke auf "Über API registrieren".',
	'ACP_BBPATREON_WEBHOOK_URL'					=> 'Deine Webhook-URL',
	'ACP_BBPATREON_WEBHOOK_URL_EXPLAIN'			=> 'Kopiere diese URL in das Patreon Webhook-Portal. Klicke auf das Feld zum Auswählen.',
	'ACP_BBPATREON_WEBHOOK_SECRET'				=> 'Webhook-Geheimnis',
	'ACP_BBPATREON_WEBHOOK_SECRET_EXPLAIN'		=> 'Wird zur Überprüfung der HMAC-MD5-Signatur eingehender Webhooks verwendet. Füge das von Patreon angezeigte Geheimnis ein.',
	'ACP_BBPATREON_REGISTER_WEBHOOK'			=> 'Über API registrieren',
	'ACP_BBPATREON_REGISTER_WEBHOOK_EXPLAIN'	=> 'Registriert einen Webhook programmatisch. Erfordert den <code>w:campaigns.webhook</code>-Scope.',
	'ACP_BBPATREON_WEBHOOK_REGISTERED'			=> 'Webhook erfolgreich bei Patreon registriert.',
	'ACP_BBPATREON_CHECK_WEBHOOK'				=> 'Status prüfen',
	'ACP_BBPATREON_CHECK_WEBHOOK_EXPLAIN'		=> 'Patreon API nach registrierten Webhooks und deren Status abfragen, oder einen Testping senden.',
	'ACP_BBPATREON_TEST_WEBHOOK'				=> 'Testping',
	'ACP_BBPATREON_WEBHOOK_CHECK_ERROR'			=> 'Webhook-Status konnte nicht von Patreon abgerufen werden.',
	'ACP_BBPATREON_WEBHOOK_NONE_REGISTERED'		=> 'Keine Webhooks bei Patreon für diesen API-Client registriert.',
	'ACP_BBPATREON_WEBHOOK_STATUS_HEADER'		=> '<strong>Registrierte Webhooks:</strong>',
	'ACP_BBPATREON_WEBHOOK_STATUS_ROW'			=> '<strong>URL:</strong> %1$s<br><strong>Pausiert:</strong> %2$s<br><strong>Trigger:</strong> %3$s<br><strong>Zuletzt versucht:</strong> %4$s<br><strong>Aufeinanderfolgende Fehler:</strong> %5$s<br><strong>Stimmt mit diesem Forum überein:</strong> %6$s',
	'ACP_BBPATREON_WEBHOOK_TEST_NO_SECRET'		=> 'Test nicht möglich: kein Webhook-Geheimnis konfiguriert.',
	'ACP_BBPATREON_WEBHOOK_TEST_OK'				=> 'Webhook-Test erfolgreich! Dein Endpoint auf <strong>%s</strong> hat korrekt geantwortet.',
	'ACP_BBPATREON_WEBHOOK_TEST_FAIL'			=> 'Webhook-Test fehlgeschlagen. HTTP-Status: %1$s. Antwort: %2$s',
	'ACP_BBPATREON_WEBHOOK_TEST_CURL_ERROR'		=> 'Webhook-Test fehlgeschlagen: Endpoint nicht erreichbar. Fehler: %s',

	'ACP_BBPATREON_TIER_MAPPING'			=> 'Stufen-zu-Gruppen-Zuordnung',
	'ACP_BBPATREON_TIER_MAPPING_EXPLAIN'	=> 'Ordne jede Patreon-Stufe einer phpBB-Benutzergruppe zu. Klicke auf "Stufen abrufen" um deine Stufen automatisch zu laden.',
	'ACP_BBPATREON_FETCH_TIERS'				=> 'Stufen abrufen',
	'ACP_BBPATREON_FETCH_TIERS_EXPLAIN'		=> 'Lade deine Patreon-Stufen über die API. Kampagnen-ID muss zuerst gespeichert sein.',
	'ACP_BBPATREON_FETCH_TIERS_NO_CAMPAIGN'	=> 'Stufen können nicht abgerufen werden: keine Kampagnen-ID konfiguriert.',
	'ACP_BBPATREON_FETCH_TIERS_EMPTY'		=> 'Keine Stufen für diese Kampagne gefunden.',
	'ACP_BBPATREON_FETCH_TIERS_DONE'		=> '%d Stufe(n) von Patreon abgerufen. Wähle eine Gruppe für jede Stufe und klicke auf Absenden.',
	'ACP_BBPATREON_TIER_ID'					=> 'Patreon Stufen-ID',
	'ACP_BBPATREON_PHPBB_GROUP'				=> 'phpBB-Gruppe',
	'ACP_BBPATREON_SELECT_GROUP'			=> '-- Gruppe auswählen --',
	'ACP_BBPATREON_ADD_TIER_MAP'			=> 'Stufen-Zuordnung hinzufügen',
	'ACP_BBPATREON_GRACE_PERIOD'			=> 'Gnadenfrist',
	'ACP_BBPATREON_GRACE_PERIOD_EXPLAIN'	=> 'Anzahl der Tage, die gewartet wird, bevor ein Benutzer aus seiner Patrongruppe entfernt wird. Auf 0 setzen für sofortige Entfernung.',
	'ACP_BBPATREON_SUPPORTERS_PAGE'			=> 'Öffentliche Unterstützerseite',
	'ACP_BBPATREON_SUPPORTERS_PAGE_EXPLAIN'	=> 'Aktiviert eine öffentliche Seite mit Benutzern, die sich als Unterstützer zeigen möchten.',
	'ACP_BBPATREON_SUPPORTERS_SHOW_AMOUNTS'			=> 'Pledge-Beträge auf Unterstützerseite erlauben',
	'ACP_BBPATREON_SUPPORTERS_SHOW_AMOUNTS_EXPLAIN'	=> 'Wenn aktiviert, können Unterstützer ihren Pledge-Betrag auf der öffentlichen Seite anzeigen. Jeder Benutzer muss dies individuell im UCP aktivieren.',

	'ACP_BBPATREON_LINKED_USERS'			=> 'Verknüpfte Benutzer',
	'ACP_BBPATREON_LINKED_USERS_EXPLAIN'	=> 'Forenbenutzer, die ihr Patreon-Konto über das Benutzerkontrollzentrum verknüpft haben.',
	'ACP_BBPATREON_NO_LINKED_USERS'			=> 'Noch keine Benutzer haben ihr Patreon-Konto verknüpft.',
	'ACP_BBPATREON_PATREON_ID'				=> 'Patreon-ID',
	'ACP_BBPATREON_TIER'					=> 'Stufe',
	'ACP_BBPATREON_STATUS'					=> 'Status',
	'ACP_BBPATREON_PLEDGE'					=> 'Pledge',
	'ACP_BBPATREON_PATRONS'					=> 'Unterstützer',
	'ACP_BBPATREON_UNPUBLISHED'				=> 'unveröffentlicht',
	'ACP_BBPATREON_LAST_WEBHOOK'			=> 'Letzter Webhook',
	'ACP_BBPATREON_LAST_SYNCED'				=> 'Zuletzt synchronisiert',
	'ACP_BBPATREON_LAST_SYNC'				=> 'Letzte Cron-Synchronisation',

	'ACP_BBPATREON_MANUAL_SYNC'		=> 'Jetzt synchronisieren',
	'ACP_BBPATREON_SYNC_DONE'		=> 'Synchronisation abgeschlossen: %1$d Mitglieder abgerufen, %2$d verknüpfte Benutzer synchronisiert.',
	'ACP_BBPATREON_SYNC_ERROR'		=> 'Synchronisation fehlgeschlagen: %s',

	'LOG_ACP_BBPATREON_SETTINGS'	=> '<strong>Patreon-Integrationseinstellungen aktualisiert</strong>',
]);
