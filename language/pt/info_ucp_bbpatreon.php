<?php
/**
 *
 * Integração Patreon para phpBB.
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
	'UCP_BBPATREON'			=> 'Patreon',
	'UCP_BBPATREON_TITLE'	=> 'Patreon',

	'UCP_BBPATREON_LINKED_STATUS'	=> 'A sua conta Patreon',
	'UCP_BBPATREON_PATREON_ID'		=> 'ID Patreon',
	'UCP_BBPATREON_TIER'			=> 'Nível atual',
	'UCP_BBPATREON_STATUS'			=> 'Estado do pledge',
	'UCP_BBPATREON_PLEDGE'			=> 'Valor do pledge',

	'UCP_BBPATREON_NOT_LINKED'		=> 'A sua conta Patreon não está vinculada. Vincule-a para receber os seus benefícios de patrono neste fórum.',
	'UCP_BBPATREON_LINK_ACCOUNT'	=> 'Vincular a sua conta Patreon',
	'UCP_BBPATREON_UNLINK'			=> 'Desvincular conta Patreon',
	'UCP_BBPATREON_UNLINK_CONFIRM'	=> 'Tem a certeza de que deseja desvincular a sua conta Patreon? Perderá a sua associação ao grupo de patrono.',
	'UCP_BBPATREON_UNLINKED'		=> 'A sua conta Patreon foi desvinculada com sucesso.',
	'UCP_BBPATREON_LINKED'			=> 'A sua conta Patreon foi vinculada com sucesso!',
	'UCP_BBPATREON_OAUTH_ERROR'		=> 'Não foi possível ligar ao Patreon.',
	'UCP_BBPATREON_ALREADY_LINKED'	=> 'Esta conta Patreon já está vinculada a outro utilizador do fórum.',

	'UCP_BBPATREON_GROUP'			=> 'Grupo do fórum',
	'UCP_BBPATREON_LAST_SYNCED'		=> 'Última atualização',
	'UCP_BBPATREON_RESYNC'			=> 'Atualizar o meu estado',
	'UCP_BBPATREON_RESYNCED'		=> 'O seu estado Patreon foi atualizado com sucesso.',
	'UCP_BBPATREON_RESYNC_TOO_SOON'	=> 'Só pode atualizar o seu estado uma vez a cada 5 minutos. Tente novamente mais tarde.',

	'UCP_BBPATREON_PREFERENCES'				=> 'Preferências',
	'UCP_BBPATREON_SHOW_PUBLIC'				=> 'Mostrar-me como apoiante',
	'UCP_BBPATREON_SHOW_PUBLIC_EXPLAIN'		=> 'Se ativado, o seu nome de utilizador e nível aparecerão na página pública de apoiantes. Os montantes nunca são mostrados. Disponível apenas para patronos pagantes.',
	'UCP_BBPATREON_SHOW_PLEDGE'				=> 'Mostrar o meu montante de pledge',
	'UCP_BBPATREON_SHOW_PLEDGE_EXPLAIN'		=> 'Se ativado, o seu montante de pledge será visível na página de apoiantes. Requer que «Mostrar-me como apoiante» esteja ativado.',
	'UCP_BBPATREON_PREFERENCES_SAVED'		=> 'As suas preferências foram guardadas.',

	'UCP_BBPATREON_STATUS_ACTIVE_PATRON'	=> 'Patrono ativo',
	'UCP_BBPATREON_STATUS_DECLINED_PATRON'	=> 'Pagamento recusado',
	'UCP_BBPATREON_STATUS_FORMER_PATRON'	=> 'Antigo patrono',
	'UCP_BBPATREON_STATUS_PENDING_LINK'		=> 'Pendente',
]);
