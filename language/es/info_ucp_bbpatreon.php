<?php
/**
 *
 * Integración de Patreon para phpBB.
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

	'UCP_BBPATREON_GROUP'			=> 'Grupo del foro',
	'UCP_BBPATREON_LAST_SYNCED'		=> 'Última actualización',
	'UCP_BBPATREON_RESYNC'			=> 'Actualizar mi estado',
	'UCP_BBPATREON_RESYNCED'		=> 'Tu estado de Patreon se ha actualizado correctamente.',
	'UCP_BBPATREON_RESYNC_TOO_SOON'	=> 'Solo puedes actualizar tu estado una vez cada 5 minutos. Inténtalo de nuevo más tarde.',

	'UCP_BBPATREON_PREFERENCES'				=> 'Preferencias',
	'UCP_BBPATREON_SHOW_PUBLIC'				=> 'Mostrarme como mecenas',
	'UCP_BBPATREON_SHOW_PUBLIC_EXPLAIN'		=> 'Si está activado, tu nombre de usuario y nivel aparecerán en la página pública de mecenas. Los importes nunca se muestran.',
	'UCP_BBPATREON_SHOW_PLEDGE'				=> 'Mostrar mi importe de pledge',
	'UCP_BBPATREON_SHOW_PLEDGE_EXPLAIN'		=> 'Si está activado, tu importe de pledge será visible en la página de mecenas. Requiere que «Mostrarme como mecenas» esté activado.',
	'UCP_BBPATREON_PREFERENCES_SAVED'		=> 'Tus preferencias se han guardado.',

	'UCP_BBPATREON_STATUS_ACTIVE_PATRON'	=> 'Mecenas activo',
	'UCP_BBPATREON_STATUS_DECLINED_PATRON'	=> 'Pago rechazado',
	'UCP_BBPATREON_STATUS_FORMER_PATRON'	=> 'Antiguo mecenas',
	'UCP_BBPATREON_STATUS_PENDING_LINK'		=> 'Pendiente',
]);
