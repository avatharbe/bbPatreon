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

	'UCP_BBPATREON_GROUP'			=> 'Groupe du forum',
	'UCP_BBPATREON_LAST_SYNCED'		=> 'Dernière mise à jour',
	'UCP_BBPATREON_RESYNC'			=> 'Actualiser mon statut',
	'UCP_BBPATREON_RESYNCED'		=> 'Votre statut Patreon a été actualisé avec succès.',
	'UCP_BBPATREON_RESYNC_TOO_SOON'	=> 'Vous ne pouvez actualiser votre statut qu\'une fois toutes les 5 minutes. Veuillez réessayer plus tard.',

	'UCP_BBPATREON_PREFERENCES'				=> 'Préférences',
	'UCP_BBPATREON_SHOW_PUBLIC'				=> 'M\'afficher comme supporter',
	'UCP_BBPATREON_SHOW_PUBLIC_EXPLAIN'		=> 'Si activé, votre nom d\'utilisateur et votre palier apparaîtront sur la page publique des supporters. Les montants des pledges ne sont jamais affichés. Réservé aux patrons payants.',
	'UCP_BBPATREON_SHOW_PLEDGE'				=> 'Afficher mon montant de pledge',
	'UCP_BBPATREON_SHOW_PLEDGE_EXPLAIN'		=> 'Si activé, votre montant de pledge sera visible sur la page des supporters. Nécessite que « M\'afficher comme supporter » soit activé.',
	'UCP_BBPATREON_PREFERENCES_SAVED'		=> 'Vos préférences ont été enregistrées.',

	'UCP_BBPATREON_STATUS_ACTIVE_PATRON'	=> 'Patron actif',
	'UCP_BBPATREON_STATUS_DECLINED_PATRON'	=> 'Paiement refusé',
	'UCP_BBPATREON_STATUS_FORMER_PATRON'	=> 'Ancien patron',
	'UCP_BBPATREON_STATUS_PENDING_LINK'		=> 'En attente',
]);
