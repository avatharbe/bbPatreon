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
	'UCP_BBPATREON'			=> 'Patreon',
	'UCP_BBPATREON_TITLE'	=> 'Patreon',

	'UCP_BBPATREON_LINKED_STATUS'	=> 'Tu cuenta de Patreon',
	'UCP_BBPATREON_PATREON_ID'		=> 'ID de Patreon',
	'UCP_BBPATREON_TIER'			=> 'Nivel actual',
	'UCP_BBPATREON_STATUS'			=> 'Estado del pledge',
	'UCP_BBPATREON_PLEDGE'			=> 'Monto del pledge',

	'UCP_BBPATREON_NOT_LINKED'		=> 'Tu cuenta de Patreon no está vinculada. Vincúlala para recibir tus beneficios de mecenas en este foro.',
	'UCP_BBPATREON_LINK_ACCOUNT'	=> 'Vincular tu cuenta de Patreon',
	'UCP_BBPATREON_UNLINK'			=> 'Desvincular cuenta de Patreon',
	'UCP_BBPATREON_UNLINK_CONFIRM'	=> '¿Estás seguro de que deseas desvincular tu cuenta de Patreon? Perderás tu membresía en el grupo de mecenas.',
	'UCP_BBPATREON_UNLINKED'		=> 'Tu cuenta de Patreon ha sido desvinculada correctamente.',
	'UCP_BBPATREON_LINKED'			=> '¡Tu cuenta de Patreon ha sido vinculada correctamente!',
	'UCP_BBPATREON_OAUTH_ERROR'		=> 'No se pudo conectar con Patreon.',
	'UCP_BBPATREON_ALREADY_LINKED'	=> 'Esta cuenta de Patreon ya está vinculada a otro usuario del foro.',
]);
