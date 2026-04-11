<?php
/**
 *
 * Patreon-integratie voor phpBB.
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
	'ACP_BBPATREON_TITLE'	=> 'Patreon-integratie',
	'ACP_BBPATREON'			=> 'Instellingen',

	'ACP_BBPATREON_SETTING_SAVED'	=> 'Patreon-instellingen zijn succesvol opgeslagen!',
	'ACP_BBPATREON_HELP'			=> 'Hoe werkt dit?',

	// Overview panel
	'ACP_BBPATREON_OVERVIEW_TITLE'		=> 'Wat doet deze extensie',
	'ACP_BBPATREON_OVERVIEW'			=> 'Deze extensie koppelt je Patreon-creatorspage aan je forum. Forumleden kunnen hun Patreon-account koppelen vanuit hun Gebruikerspaneel. Na koppeling worden ze automatisch toegewezen aan phpBB-gebruikersgroepen op basis van hun Patreon-tier. Wanneer een patron upgradet, downgradet of opzegt, wordt het groepslidmaatschap automatisch bijgewerkt. Zo kun je phpBB\'s ingebouwde groepsrechten gebruiken om toegang tot patron-only forums, speciale rangen of andere functies te regelen.',

	'ACP_BBPATREON_HOW_IT_WORKS_TITLE'	=> 'Installatiestappen',
	'ACP_BBPATREON_STEP_CREDENTIALS'	=> 'Voer hieronder je Patreon API-gegevens in. Je krijgt deze door een OAuth-client aan te maken op <a href="https://www.patreon.com/portal/registration/register-clients" target="_blank">patreon.com/portal/registration/register-clients</a>. Stel de redirect-URI in op: <strong>https://jouwforum.nl/patreon/callback</strong> (vervang door je werkelijke forum-URL).',
	'ACP_BBPATREON_STEP_TIERS'			=> 'Koppel elke Patreon-tier aan een phpBB-gebruikersgroep in de sectie Tier Mapping. Klik op "Tiers ophalen" om ze automatisch te laden. Stel daarna de forumrechten voor die groepen in.',
	'ACP_BBPATREON_STEP_WEBHOOK'		=> 'Registreer een webhook zodat Patreon je forum in realtime op de hoogte stelt wanneer pledges veranderen. Dit is optioneel maar aanbevolen &mdash; zonder webhooks worden wijzigingen alleen opgepikt door de nachtelijke synchronisatie.',
	'ACP_BBPATREON_STEP_USERS'			=> 'Vertel je leden dat ze hun Gebruikerspaneel moeten bezoeken en op "Koppel je Patreon-account" moeten klikken. Na koppeling worden tier en groep automatisch gesynchroniseerd.',

	'ACP_BBPATREON_SYNC_TITLE'			=> 'Hoe synchronisatie werkt',
	'ACP_BBPATREON_SYNC_EXPLAIN'		=> 'Groepslidmaatschap wordt up-to-date gehouden via drie mechanismen: <strong>OAuth-koppeling</strong> (groepen worden direct toegewezen wanneer een gebruiker zijn account koppelt), <strong>Webhooks</strong> (Patreon stuurt realtime meldingen wanneer een pledge wordt aangemaakt, gewijzigd of geannuleerd), en een <strong>Nachtelijke cron-taak</strong> (een volledige afstemming vindt elke 24 uur plaats als vangnet). Je kunt ook een handmatige synchronisatie starten met de knop "Nu synchroniseren".',

	'ACP_BBPATREON_STATUSES_TITLE'		=> 'Pledge-statussen',
	'ACP_BBPATREON_STATUS_ACTIVE'		=> 'Actief pledgend en betaling is actueel. Gebruiker is toegewezen aan de groep van zijn tier.',
	'ACP_BBPATREON_STATUS_DECLINED'		=> 'Betaling mislukt maar de pledge is nog niet geannuleerd. Tijdens de gratieperiode behoudt de gebruiker zijn groep. Als de betaling niet wordt opgelost, wordt hij gedemoteerd wanneer de gratieperiode verloopt.',
	'ACP_BBPATREON_STATUS_FORMER'		=> 'Pledge is geannuleerd. De gebruiker wordt onmiddellijk uit zijn patrongroep verwijderd (of na de gratieperiode, indien geconfigureerd).',
	'ACP_BBPATREON_STATUS_PENDING'		=> 'Patreon-account is gekoppeld aan het forum maar de gebruiker is momenteel geen patron van je campagne.',

	// API Credentials
	'ACP_BBPATREON_API_CREDENTIALS'			=> 'API-gegevens',
	'ACP_BBPATREON_API_CREDENTIALS_EXPLAIN'	=> 'Voer de gegevens van je Patreon API-client in. De Client ID en Secret worden gebruikt voor de OAuth-aanmeldingsstroom. De Creator-tokens worden gebruikt voor server-naar-server API-aanroepen (leden ophalen, webhooks registreren). Alle vier de waarden komen van <a href="https://www.patreon.com/portal/registration/register-clients" target="_blank">je Patreon API-clientpagina</a>.',
	'ACP_BBPATREON_CLIENT_ID'				=> 'Client ID',
	'ACP_BBPATREON_CLIENT_ID_EXPLAIN'		=> 'De OAuth Client ID van je Patreon Developer Portal.',
	'ACP_BBPATREON_CLIENT_SECRET'			=> 'Client Secret',
	'ACP_BBPATREON_CREATOR_TOKEN'			=> 'Creator Access Token',
	'ACP_BBPATREON_CREATOR_TOKEN_EXPLAIN'	=> 'Gebruikt voor server-naar-server API-aanroepen (leden ophalen, webhooks registreren). Dit token wordt automatisch vernieuwd wanneer het verloopt, zolang het refresh-token geldig is.',
	'ACP_BBPATREON_CREATOR_REFRESH'			=> 'Creator Refresh Token',
	'ACP_BBPATREON_CAMPAIGN_ID'				=> 'Campagne-ID',
	'ACP_BBPATREON_CAMPAIGN_ID_EXPLAIN'		=> 'Het numerieke ID van je Patreon-campagne. Sla eerst je creator-tokens op en klik dan op "Ophalen" om het automatisch te detecteren.',
	'ACP_BBPATREON_FETCH_CAMPAIGN'			=> 'Ophalen',
	'ACP_BBPATREON_CAMPAIGN_FETCHED'		=> 'Campagne-ID succesvol opgehaald: %s',
	'ACP_BBPATREON_CAMPAIGN_FETCH_ERROR'	=> 'Kan campagne-ID niet ophalen. Controleer of het Creator Access Token is opgeslagen en geldig is.',

	// Webhook
	'ACP_BBPATREON_WEBHOOK'						=> 'Webhook',
	'ACP_BBPATREON_WEBHOOK_EXPLAIN'				=> 'Webhooks maken realtime synchronisatie mogelijk. Wanneer een patron een pledge aanmaakt, wijzigt of annuleert op Patreon, wordt een ondertekend HTTP-verzoek naar je forum gestuurd dat onmiddellijk het groepslidmaatschap bijwerkt. Zonder webhooks worden wijzigingen alleen opgepikt door de nachtelijke cron-taak. Je forum moet bereikbaar zijn via HTTPS.<br><br>Er zijn twee manieren om een webhook te registreren:<br><strong>Optie A (aanbevolen):</strong> Ga naar <a href="https://www.patreon.com/portal/registration/register-webhooks" target="_blank">patreon.com/portal/registration/register-webhooks</a>, maak een webhook aan met de URL hieronder, selecteer de pledge-triggers en plak het geheim dat Patreon toont in het veld hieronder.<br><strong>Optie B:</strong> Klik op de knop "Registreer via API". Dit vereist het <code>w:campaigns.webhook</code>-bereik op je creator access token.',
	'ACP_BBPATREON_WEBHOOK_URL'					=> 'Je Webhook-URL',
	'ACP_BBPATREON_WEBHOOK_URL_EXPLAIN'			=> 'Kopieer deze URL naar het Patreon webhook-portaal. Klik op het veld om het te selecteren.',
	'ACP_BBPATREON_WEBHOOK_SECRET'				=> 'Webhook-geheim',
	'ACP_BBPATREON_WEBHOOK_SECRET_EXPLAIN'		=> 'Wordt gebruikt om de HMAC-MD5-handtekening op inkomende webhooks te verifiëren. Plak het geheim dat Patreon toont na registratie van je webhook in het portaal.',
	'ACP_BBPATREON_REGISTER_WEBHOOK'			=> 'Registreer via API',
	'ACP_BBPATREON_REGISTER_WEBHOOK_EXPLAIN'	=> 'Registreert een webhook programmatisch. Vereist het <code>w:campaigns.webhook</code>-bereik op je creator-token. Als dit mislukt, gebruik dan het Patreon-portaal (zie hulp hierboven).',
	'ACP_BBPATREON_WEBHOOK_REGISTERED'			=> 'Webhook succesvol geregistreerd bij Patreon.',
	'ACP_BBPATREON_CHECK_WEBHOOK'				=> 'Status controleren',
	'ACP_BBPATREON_CHECK_WEBHOOK_EXPLAIN'		=> 'Bevraag de Patreon API voor geregistreerde webhooks en hun status, of stuur een testpingback naar je eigen endpoint.',
	'ACP_BBPATREON_TEST_WEBHOOK'				=> 'Testping',
	'ACP_BBPATREON_WEBHOOK_CHECK_ERROR'			=> 'Kan webhookstatus niet ophalen van Patreon. Controleer of het Creator Access Token geldig is.',
	'ACP_BBPATREON_WEBHOOK_NONE_REGISTERED'		=> 'Er zijn geen webhooks geregistreerd bij Patreon voor deze API-client.',
	'ACP_BBPATREON_WEBHOOK_STATUS_HEADER'		=> '<strong>Geregistreerde webhooks:</strong>',
	'ACP_BBPATREON_WEBHOOK_STATUS_ROW'			=> '<strong>URL:</strong> %1$s<br><strong>Gepauzeerd:</strong> %2$s<br><strong>Triggers:</strong> %3$s<br><strong>Laatst geprobeerd:</strong> %4$s<br><strong>Opeenvolgende fouten:</strong> %5$s<br><strong>Komt overeen met dit forum:</strong> %6$s',
	'ACP_BBPATREON_WEBHOOK_TEST_NO_SECRET'		=> 'Kan niet testen: geen webhook-geheim geconfigureerd. Registreer eerst een webhook of voer een geheim in.',
	'ACP_BBPATREON_WEBHOOK_TEST_OK'				=> 'Webhooktest geslaagd! Je endpoint op <strong>%s</strong> heeft correct gereageerd met een geldige handtekeningmatch.',
	'ACP_BBPATREON_WEBHOOK_TEST_FAIL'			=> 'Webhooktest mislukt. HTTP-status: %1$s. Antwoord: %2$s',
	'ACP_BBPATREON_WEBHOOK_TEST_CURL_ERROR'		=> 'Webhooktest mislukt: endpoint niet bereikbaar. Fout: %s',

	// Tier mapping
	'ACP_BBPATREON_TIER_MAPPING'			=> 'Tier-naar-groep-koppeling',
	'ACP_BBPATREON_TIER_MAPPING_EXPLAIN'	=> 'Koppel elke Patreon-tier aan een phpBB-gebruikersgroep. Wanneer een patron op een bepaalde tier pledgt, wordt hij aan de bijbehorende groep toegevoegd. Bij wisseling van tier wordt hij naar de nieuwe groep verplaatst. Bij annulering wordt hij uit alle patrongroepen verwijderd (afhankelijk van de gratieperiode). Klik op "Tiers ophalen" om je tiers automatisch te laden.',
	'ACP_BBPATREON_FETCH_TIERS'				=> 'Tiers ophalen',
	'ACP_BBPATREON_FETCH_TIERS_EXPLAIN'		=> 'Laad je Patreon-tiers via de API. Campagne-ID moet eerst opgeslagen zijn.',
	'ACP_BBPATREON_FETCH_TIERS_NO_CAMPAIGN'	=> 'Kan tiers niet ophalen: geen campagne-ID geconfigureerd. Sla deze eerst op.',
	'ACP_BBPATREON_FETCH_TIERS_EMPTY'		=> 'Geen tiers gevonden voor deze campagne.',
	'ACP_BBPATREON_FETCH_TIERS_DONE'		=> '%d tier(s) opgehaald van Patreon. Selecteer een groep voor elke tier en klik op Verzenden.',
	'ACP_BBPATREON_TIER_ID'					=> 'Patreon Tier-ID',
	'ACP_BBPATREON_PHPBB_GROUP'				=> 'phpBB-groep',
	'ACP_BBPATREON_SELECT_GROUP'			=> '-- Selecteer groep --',
	'ACP_BBPATREON_ADD_TIER_MAP'			=> 'Tier-koppeling toevoegen',
	'ACP_BBPATREON_GRACE_PERIOD'			=> 'Gratieperiode',
	'ACP_BBPATREON_GRACE_PERIOD_EXPLAIN'	=> 'Aantal dagen wachten voordat een gebruiker uit zijn patrongroep wordt verwijderd nadat hij opzegt of zijn betaling mislukt. Tijdens deze periode behoudt de gebruiker zijn groepstoegang. Stel in op 0 voor onmiddellijke verwijdering. De nachtelijke cron-taak handhaaft het verlopen van de gratieperiode.',

	// Linked users
	'ACP_BBPATREON_LINKED_USERS'			=> 'Gekoppelde gebruikers',
	'ACP_BBPATREON_LINKED_USERS_EXPLAIN'	=> 'Forumgebruikers die hun Patreon-account hebben gekoppeld via het Gebruikerspaneel. De status- en tierkolommen geven de laatst bekende gegevens van Patreon weer (via webhook of synchronisatie). Als een gebruiker hier verschijnt maar geen tier heeft, heeft hij zijn Patreon-account gekoppeld maar is hij momenteel geen patron.',
	'ACP_BBPATREON_NO_LINKED_USERS'			=> 'Er hebben nog geen gebruikers hun Patreon-account gekoppeld.',
	'ACP_BBPATREON_PATREON_ID'				=> 'Patreon-ID',
	'ACP_BBPATREON_TIER'					=> 'Tier',
	'ACP_BBPATREON_STATUS'					=> 'Status',
	'ACP_BBPATREON_PLEDGE'					=> 'Pledge',
	'ACP_BBPATREON_PATRONS'					=> 'Patreons',
	'ACP_BBPATREON_UNPUBLISHED'				=> 'niet gepubliceerd',
	'ACP_BBPATREON_LAST_WEBHOOK'			=> 'Laatste webhook',
	'ACP_BBPATREON_LAST_SYNCED'				=> 'Laatst gesynchroniseerd',
	'ACP_BBPATREON_LAST_SYNC'				=> 'Laatste cron-synchronisatie',

	// Actions
	'ACP_BBPATREON_MANUAL_SYNC'		=> 'Nu synchroniseren',
	'ACP_BBPATREON_SYNC_DONE'		=> 'Synchronisatie voltooid: %1$d leden opgehaald, %2$d gekoppelde gebruikers gesynchroniseerd.',
	'ACP_BBPATREON_SYNC_ERROR'		=> 'Synchronisatie mislukt: %s',

	// Admin log
	'LOG_ACP_BBPATREON_SETTINGS'	=> '<strong>Patreon-integratie-instellingen bijgewerkt</strong>',
]);
