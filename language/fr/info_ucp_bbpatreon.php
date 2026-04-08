<?php
/**
 *
 * Intégration Patreon pour phpBB.
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

	'UCP_BBPATREON_LINKED_STATUS'	=> 'Votre compte Patreon',
	'UCP_BBPATREON_PATREON_ID'		=> 'ID Patreon',
	'UCP_BBPATREON_TIER'			=> 'Palier actuel',
	'UCP_BBPATREON_STATUS'			=> 'Statut du pledge',
	'UCP_BBPATREON_PLEDGE'			=> 'Montant du pledge',

	'UCP_BBPATREON_NOT_LINKED'		=> 'Votre compte Patreon n\'est pas lié. Liez-le pour recevoir vos avantages patron sur ce forum.',
	'UCP_BBPATREON_LINK_ACCOUNT'	=> 'Lier votre compte Patreon',
	'UCP_BBPATREON_UNLINK'			=> 'Délier le compte Patreon',
	'UCP_BBPATREON_UNLINK_CONFIRM'	=> 'Êtes-vous sûr de vouloir délier votre compte Patreon ? Vous perdrez votre appartenance au groupe patron.',
	'UCP_BBPATREON_UNLINKED'		=> 'Votre compte Patreon a été délié avec succès.',
	'UCP_BBPATREON_LINKED'			=> 'Votre compte Patreon a été lié avec succès !',
	'UCP_BBPATREON_OAUTH_ERROR'		=> 'Impossible de se connecter à Patreon.',
	'UCP_BBPATREON_ALREADY_LINKED'	=> 'Ce compte Patreon est déjà lié à un autre utilisateur du forum.',
]);
