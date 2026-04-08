<?php
/**
 *
 * Integração Patreon para phpBB.
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

	'NOTIFICATION_TYPE_PATREON_LINKED'			=> 'Alguém vincula a sua conta Patreon',
	'NOTIFICATION_PATREON_LINKED'				=> '<strong>%s</strong> vinculou a sua conta Patreon',
	'NOTIFICATION_PATREON_LINKED_REFERENCE'		=> 'Nível: %s',

	'LOG_PATREON_API_ERROR'				=> '<strong>Erro da API do Patreon:</strong> %s',
	'LOG_PATREON_TOKEN_REFRESH_FAILED'	=> '<strong>Falha na renovação do token do Patreon</strong> (HTTP %s)',
	'LOG_PATREON_WEBHOOK_NO_SIGNATURE'	=> '<strong>Webhook do Patreon recebido sem assinatura</strong>',
	'LOG_PATREON_WEBHOOK_BAD_SIGNATURE'	=> '<strong>Falha na validação da assinatura do webhook do Patreon</strong>',
	'LOG_PATREON_CRON_SYNC'				=> '<strong>Sincronização cron do Patreon concluída:</strong> %1$s membros obtidos, %2$s sincronizados',
	'LOG_PATREON_MANUAL_SYNC'			=> '<strong>Sincronização manual do Patreon:</strong> %1$s membros obtidos, %2$s sincronizados',
]);
