<?php
/**
 *
 * Intégration Patreon pour phpBB.
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
	'AUTH_PROVIDER_OAUTH_SERVICE_PATREON'	=> 'Patreon',

	'PATREON_NEVER'	=> 'Jamais',

	'PATREON_SUPPORTERS_TITLE'		=> 'Nos supporters',
	'PATREON_SUPPORTERS_EXPLAIN'	=> 'Ces membres de la communauté nous soutiennent via Patreon.',
	'PATREON_SUPPORTERS_TIER'		=> 'Palier',
	'PATREON_SUPPORTERS_PLEDGE'		=> 'Pledge',
	'PATREON_SUPPORTERS_NONE'		=> 'Aucun supporter à afficher pour le moment.',

	'NOTIFICATION_TYPE_PATREON_LINKED'			=> 'Quelqu\'un lie son compte Patreon',
	'NOTIFICATION_PATREON_LINKED'				=> '<strong>%s</strong> a lié son compte Patreon',
	'NOTIFICATION_PATREON_LINKED_REFERENCE'		=> 'Palier : %s',

	'LOG_PATREON_API_ERROR'				=> '<strong>Erreur API Patreon :</strong> %s',
	'LOG_PATREON_TOKEN_REFRESH_FAILED'	=> '<strong>Échec du renouvellement du jeton Patreon</strong> (HTTP %s)',
	'LOG_PATREON_WEBHOOK_NO_SIGNATURE'	=> '<strong>Webhook Patreon reçu sans signature</strong>',
	'LOG_PATREON_WEBHOOK_BAD_SIGNATURE'	=> '<strong>Échec de la validation de la signature du webhook Patreon</strong>',
	'LOG_PATREON_CRON_SYNC'				=> '<strong>Synchronisation cron Patreon terminée :</strong> %1$s membres récupérés, %2$s synchronisés',
	'LOG_PATREON_MANUAL_SYNC'			=> '<strong>Synchronisation manuelle Patreon :</strong> %1$s membres récupérés, %2$s synchronisés',
	'LOG_PATREON_LINKED'				=> '<strong>Compte Patreon lié :</strong> utilisateur %1$s a lié le Patreon ID %2$s (palier : %3$s, statut : %4$s)',
	'LOG_PATREON_UNLINKED'				=> '<strong>Compte Patreon délié :</strong> utilisateur %1$s a délié le Patreon ID %2$s',
	'LOG_PATREON_WEBHOOK_EVENT'			=> '<strong>Webhook Patreon reçu :</strong> %1$s (statut : %2$s, palier : %3$s)',
	'LOG_PATREON_GROUP_ADD'				=> '<strong>Promotion de groupe Patreon :</strong> utilisateur ID %1$s ajouté au groupe ID %2$s',
	'LOG_PATREON_GROUP_REMOVE'			=> '<strong>Rétrogradation de groupe Patreon :</strong> utilisateur ID %1$s retiré du groupe ID %2$s',
]);
