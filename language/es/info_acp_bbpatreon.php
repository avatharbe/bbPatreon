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
	'ACP_BBPATREON_TITLE'	=> 'Integración de Patreon',
	'ACP_BBPATREON'			=> 'Configuración',

	'ACP_BBPATREON_SETTING_SAVED'	=> '¡La configuración de Patreon se ha guardado correctamente!',
	'ACP_BBPATREON_HELP'			=> '¿Cómo funciona esto?',

	'ACP_BBPATREON_OVERVIEW_TITLE'		=> 'Qué hace esta extensión',
	'ACP_BBPATREON_OVERVIEW'			=> 'Esta extensión conecta tu página de creador de Patreon con tu foro. Los miembros pueden vincular su cuenta de Patreon desde su Panel de Control de Usuario. Una vez vinculados, se les asigna automáticamente a grupos de phpBB según su nivel de Patreon. Cuando un mecenas cambia de nivel o cancela, la membresía del grupo se actualiza automáticamente.',

	'ACP_BBPATREON_HOW_IT_WORKS_TITLE'	=> 'Pasos de configuración',
	'ACP_BBPATREON_STEP_CREDENTIALS'	=> 'Introduce tus credenciales de API de Patreon abajo. Las obtienes creando un cliente OAuth en <a href="https://www.patreon.com/portal/registration/register-clients" target="_blank">patreon.com/portal/registration/register-clients</a>. Configura la URI de redirección a: <strong>https://tuforo.com/patreon/callback</strong> (reemplaza con la URL real de tu foro).',
	'ACP_BBPATREON_STEP_TIERS'			=> 'Asocia cada nivel de Patreon a un grupo de phpBB en la sección de Mapeo de Niveles. Haz clic en "Obtener niveles" para cargarlos automáticamente.',
	'ACP_BBPATREON_STEP_WEBHOOK'		=> 'Registra un webhook para que Patreon notifique a tu foro en tiempo real cuando cambien los pledges. Opcional pero recomendado.',
	'ACP_BBPATREON_STEP_USERS'			=> 'Indica a tus miembros que visiten su Panel de Control de Usuario y hagan clic en "Vincular tu cuenta de Patreon".',

	'ACP_BBPATREON_SYNC_TITLE'			=> 'Cómo funciona la sincronización',
	'ACP_BBPATREON_SYNC_EXPLAIN'		=> 'La membresía de grupos se mantiene actualizada mediante tres mecanismos: <strong>Vinculación OAuth</strong> (los grupos se asignan inmediatamente al vincular), <strong>Webhooks</strong> (Patreon envía notificaciones en tiempo real), y una <strong>Tarea cron nocturna</strong> (reconciliación completa cada 24 horas).',

	'ACP_BBPATREON_STATUSES_TITLE'		=> 'Estados de pledge',
	'ACP_BBPATREON_STATUS_ACTIVE'		=> 'Pledgeando activamente y pago al día. El usuario está asignado al grupo de su nivel.',
	'ACP_BBPATREON_STATUS_DECLINED'		=> 'Pago fallido pero el pledge no ha sido cancelado aún. Durante el período de gracia el usuario mantiene su grupo.',
	'ACP_BBPATREON_STATUS_FORMER'		=> 'Pledge cancelado. El usuario será eliminado de su grupo de mecenas inmediatamente (o tras el período de gracia).',
	'ACP_BBPATREON_STATUS_PENDING'		=> 'Cuenta de Patreon vinculada al foro pero el usuario no es actualmente un mecenas de tu campaña.',

	'ACP_BBPATREON_API_CREDENTIALS'			=> 'Credenciales de API',
	'ACP_BBPATREON_API_CREDENTIALS_EXPLAIN'	=> 'Introduce las credenciales de tu cliente API de Patreon. El Client ID y Secret se usan para el flujo OAuth. Los tokens de Creator se usan para llamadas API servidor a servidor. Todos los valores provienen de <a href="https://www.patreon.com/portal/registration/register-clients" target="_blank">tu página de cliente API de Patreon</a>.',
	'ACP_BBPATREON_CLIENT_ID'				=> 'Client ID',
	'ACP_BBPATREON_CLIENT_ID_EXPLAIN'		=> 'El OAuth Client ID de tu Portal de Desarrollador de Patreon.',
	'ACP_BBPATREON_CLIENT_SECRET'			=> 'Client Secret',
	'ACP_BBPATREON_CREATOR_TOKEN'			=> 'Token de acceso del creador',
	'ACP_BBPATREON_CREATOR_TOKEN_EXPLAIN'	=> 'Usado para llamadas API servidor a servidor. Este token se renueva automáticamente al expirar.',
	'ACP_BBPATREON_CREATOR_REFRESH'			=> 'Token de renovación del creador',
	'ACP_BBPATREON_CAMPAIGN_ID'				=> 'ID de campaña',
	'ACP_BBPATREON_CAMPAIGN_ID_EXPLAIN'		=> 'El ID numérico de tu campaña de Patreon. Guarda primero tus tokens y haz clic en "Obtener".',
	'ACP_BBPATREON_FETCH_CAMPAIGN'			=> 'Obtener',
	'ACP_BBPATREON_CAMPAIGN_FETCHED'		=> 'ID de campaña obtenido correctamente: %s',
	'ACP_BBPATREON_CAMPAIGN_FETCH_ERROR'	=> 'No se pudo obtener el ID de campaña. Verifica que el token de acceso del creador esté guardado y sea válido.',

	'ACP_BBPATREON_WEBHOOK'						=> 'Webhook',
	'ACP_BBPATREON_WEBHOOK_EXPLAIN'				=> 'Los webhooks permiten la sincronización en tiempo real.<br><br><strong>Opción A (recomendada):</strong> Ve a <a href="https://www.patreon.com/portal/registration/register-webhooks" target="_blank">patreon.com/portal/registration/register-webhooks</a>, crea un webhook con la URL de abajo y pega el secreto.<br><strong>Opción B:</strong> Haz clic en "Registrar vía API".',
	'ACP_BBPATREON_WEBHOOK_URL'					=> 'Tu URL de webhook',
	'ACP_BBPATREON_WEBHOOK_URL_EXPLAIN'			=> 'Copia esta URL en el portal de webhooks de Patreon. Haz clic en el campo para seleccionarlo.',
	'ACP_BBPATREON_WEBHOOK_SECRET'				=> 'Secreto del webhook',
	'ACP_BBPATREON_WEBHOOK_SECRET_EXPLAIN'		=> 'Usado para verificar la firma HMAC-MD5 de los webhooks entrantes. Pega el secreto mostrado por Patreon.',
	'ACP_BBPATREON_REGISTER_WEBHOOK'			=> 'Registrar vía API',
	'ACP_BBPATREON_REGISTER_WEBHOOK_EXPLAIN'	=> 'Registra un webhook programáticamente. Requiere el scope <code>w:campaigns.webhook</code>.',
	'ACP_BBPATREON_WEBHOOK_REGISTERED'			=> 'Webhook registrado correctamente en Patreon.',
	'ACP_BBPATREON_CHECK_WEBHOOK'				=> 'Verificar estado',
	'ACP_BBPATREON_CHECK_WEBHOOK_EXPLAIN'		=> 'Consultar la API de Patreon para webhooks registrados, o enviar un ping de prueba.',
	'ACP_BBPATREON_TEST_WEBHOOK'				=> 'Ping de prueba',
	'ACP_BBPATREON_WEBHOOK_CHECK_ERROR'			=> 'No se pudo obtener el estado del webhook de Patreon.',
	'ACP_BBPATREON_WEBHOOK_NONE_REGISTERED'		=> 'No hay webhooks registrados en Patreon para este cliente API.',
	'ACP_BBPATREON_WEBHOOK_STATUS_HEADER'		=> '<strong>Webhooks registrados:</strong>',
	'ACP_BBPATREON_WEBHOOK_STATUS_ROW'			=> '<strong>URL:</strong> %1$s<br><strong>En pausa:</strong> %2$s<br><strong>Disparadores:</strong> %3$s<br><strong>Último intento:</strong> %4$s<br><strong>Fallos consecutivos:</strong> %5$s<br><strong>Coincide con este foro:</strong> %6$s',
	'ACP_BBPATREON_WEBHOOK_TEST_NO_SECRET'		=> 'No se puede probar: no hay secreto de webhook configurado.',
	'ACP_BBPATREON_WEBHOOK_TEST_OK'				=> '¡Prueba de webhook exitosa! Tu endpoint en <strong>%s</strong> respondió correctamente.',
	'ACP_BBPATREON_WEBHOOK_TEST_FAIL'			=> 'Prueba de webhook fallida. Estado HTTP: %1$s. Respuesta: %2$s',
	'ACP_BBPATREON_WEBHOOK_TEST_CURL_ERROR'		=> 'Prueba de webhook fallida: endpoint no accesible. Error: %s',

	'ACP_BBPATREON_TIER_MAPPING'			=> 'Mapeo de nivel a grupo',
	'ACP_BBPATREON_TIER_MAPPING_EXPLAIN'	=> 'Asocia cada nivel de Patreon a un grupo de phpBB. Haz clic en "Obtener niveles" para cargarlos automáticamente.',
	'ACP_BBPATREON_FETCH_TIERS'				=> 'Obtener niveles',
	'ACP_BBPATREON_FETCH_TIERS_EXPLAIN'		=> 'Carga tus niveles de Patreon desde la API. El ID de campaña debe estar guardado primero.',
	'ACP_BBPATREON_FETCH_TIERS_NO_CAMPAIGN'	=> 'No se pueden obtener niveles: no hay ID de campaña configurado.',
	'ACP_BBPATREON_FETCH_TIERS_EMPTY'		=> 'No se encontraron niveles para esta campaña.',
	'ACP_BBPATREON_FETCH_TIERS_DONE'		=> '%d nivel(es) obtenido(s) de Patreon. Selecciona un grupo para cada nivel y haz clic en Enviar.',
	'ACP_BBPATREON_TIER_ID'					=> 'ID de nivel de Patreon',
	'ACP_BBPATREON_PHPBB_GROUP'				=> 'Grupo phpBB',
	'ACP_BBPATREON_SELECT_GROUP'			=> '-- Seleccionar grupo --',
	'ACP_BBPATREON_ADD_TIER_MAP'			=> 'Añadir mapeo de nivel',
	'ACP_BBPATREON_GRACE_PERIOD'			=> 'Período de gracia',
	'ACP_BBPATREON_GRACE_PERIOD_EXPLAIN'	=> 'Días de espera antes de eliminar a un usuario de su grupo de mecenas. Pon 0 para eliminación inmediata.',

	'ACP_BBPATREON_LINKED_USERS'			=> 'Usuarios vinculados',
	'ACP_BBPATREON_LINKED_USERS_EXPLAIN'	=> 'Usuarios del foro que han vinculado su cuenta de Patreon desde el Panel de Control de Usuario.',
	'ACP_BBPATREON_NO_LINKED_USERS'			=> 'Ningún usuario ha vinculado su cuenta de Patreon todavía.',
	'ACP_BBPATREON_PATREON_ID'				=> 'ID de Patreon',
	'ACP_BBPATREON_TIER'					=> 'Nivel',
	'ACP_BBPATREON_STATUS'					=> 'Estado',
	'ACP_BBPATREON_PLEDGE'					=> 'Pledge',
	'ACP_BBPATREON_LAST_WEBHOOK'			=> 'Último webhook',
	'ACP_BBPATREON_LAST_SYNCED'				=> 'Última sincronización',
	'ACP_BBPATREON_LAST_SYNC'				=> 'Última sincronización cron',

	'ACP_BBPATREON_MANUAL_SYNC'		=> 'Sincronizar ahora',
	'ACP_BBPATREON_SYNC_DONE'		=> 'Sincronización completada: %1$d miembros obtenidos, %2$d usuarios vinculados sincronizados.',
	'ACP_BBPATREON_SYNC_ERROR'		=> 'Sincronización fallida: %s',

	'LOG_ACP_BBPATREON_SETTINGS'	=> '<strong>Configuración de integración de Patreon actualizada</strong>',
]);
