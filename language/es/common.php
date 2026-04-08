<?php
/**
 *
 * Integración de Patreon para phpBB.
 *
 * @copyright (c) 2024 Sajaki
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

	'PATREON_NEVER'	=> 'Nunca',

	'NOTIFICATION_TYPE_PATREON_LINKED'			=> 'Alguien vincula su cuenta de Patreon',
	'NOTIFICATION_PATREON_LINKED'				=> '<strong>%s</strong> ha vinculado su cuenta de Patreon',
	'NOTIFICATION_PATREON_LINKED_REFERENCE'		=> 'Nivel: %s',

	'LOG_PATREON_API_ERROR'				=> '<strong>Error de API de Patreon:</strong> %s',
	'LOG_PATREON_TOKEN_REFRESH_FAILED'	=> '<strong>Falló la renovación del token de Patreon</strong> (HTTP %s)',
	'LOG_PATREON_WEBHOOK_NO_SIGNATURE'	=> '<strong>Webhook de Patreon recibido sin firma</strong>',
	'LOG_PATREON_WEBHOOK_BAD_SIGNATURE'	=> '<strong>Falló la validación de la firma del webhook de Patreon</strong>',
	'LOG_PATREON_CRON_SYNC'				=> '<strong>Sincronización cron de Patreon completada:</strong> %1$s miembros obtenidos, %2$s sincronizados',
	'LOG_PATREON_MANUAL_SYNC'			=> '<strong>Sincronización manual de Patreon:</strong> %1$s miembros obtenidos, %2$s sincronizados',
	'LOG_PATREON_LINKED'				=> '<strong>Cuenta Patreon vinculada:</strong> usuario %1$s vinculó Patreon ID %2$s (nivel: %3$s, estado: %4$s)',
	'LOG_PATREON_UNLINKED'				=> '<strong>Cuenta Patreon desvinculada:</strong> usuario %1$s desvinculó Patreon ID %2$s',
	'LOG_PATREON_WEBHOOK_EVENT'			=> '<strong>Webhook de Patreon recibido:</strong> %1$s (estado: %2$s, nivel: %3$s)',
	'LOG_PATREON_GROUP_ADD'				=> '<strong>Promoción de grupo Patreon:</strong> usuario ID %1$s añadido al grupo ID %2$s',
	'LOG_PATREON_GROUP_REMOVE'			=> '<strong>Degradación de grupo Patreon:</strong> usuario ID %1$s eliminado del grupo ID %2$s',
]);
