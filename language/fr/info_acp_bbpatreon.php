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
	'ACP_BBPATREON_TITLE'	=> 'Intégration Patreon',
	'ACP_BBPATREON'			=> 'Paramètres',

	'ACP_BBPATREON_SETTING_SAVED'	=> 'Les paramètres Patreon ont été enregistrés avec succès !',
	'ACP_BBPATREON_HELP'			=> 'Comment ça marche ?',

	'ACP_BBPATREON_OVERVIEW_TITLE'		=> 'Ce que fait cette extension',
	'ACP_BBPATREON_OVERVIEW'			=> 'Cette extension connecte votre page créateur Patreon à votre forum. Les membres peuvent lier leur compte Patreon depuis leur Panneau de l\'utilisateur. Une fois liés, ils sont automatiquement assignés à des groupes phpBB en fonction de leur palier Patreon. Lorsqu\'un patron change de palier ou annule, l\'appartenance au groupe est mise à jour en conséquence.',

	'ACP_BBPATREON_HOW_IT_WORKS_TITLE'	=> 'Étapes de configuration',
	'ACP_BBPATREON_STEP_CREDENTIALS'	=> 'Entrez vos identifiants API Patreon ci-dessous. Vous les obtenez en créant un client OAuth sur <a href="https://www.patreon.com/portal/registration/register-clients" target="_blank">patreon.com/portal/registration/register-clients</a>. Définissez l\'URI de redirection sur : <strong>https://votreforum.fr/patreon/callback</strong> (remplacez par l\'URL de votre forum).',
	'ACP_BBPATREON_STEP_TIERS'			=> 'Associez chaque palier Patreon à un groupe phpBB dans la section Correspondance des paliers. Cliquez sur "Récupérer les paliers" pour les charger automatiquement.',
	'ACP_BBPATREON_STEP_WEBHOOK'		=> 'Enregistrez un webhook pour que Patreon notifie votre forum en temps réel des changements de pledges. Optionnel mais recommandé.',
	'ACP_BBPATREON_STEP_USERS'			=> 'Dites à vos membres de visiter leur Panneau de l\'utilisateur et de cliquer sur "Lier votre compte Patreon".',

	'ACP_BBPATREON_SYNC_TITLE'			=> 'Comment fonctionne la synchronisation',
	'ACP_BBPATREON_SYNC_EXPLAIN'		=> 'L\'appartenance aux groupes est maintenue à jour via trois mécanismes : <strong>Liaison OAuth</strong> (les groupes sont assignés immédiatement lors de la liaison), <strong>Webhooks</strong> (Patreon envoie des notifications en temps réel), et une <strong>Tâche cron nocturne</strong> (réconciliation complète toutes les 24 heures).',

	'ACP_BBPATREON_STATUSES_TITLE'		=> 'Statuts de pledge',
	'ACP_BBPATREON_STATUS_ACTIVE'		=> 'Pledgeant activement et paiement à jour. L\'utilisateur est assigné au groupe de son palier.',
	'ACP_BBPATREON_STATUS_DECLINED'		=> 'Paiement échoué mais le pledge n\'a pas encore été annulé. Pendant la période de grâce, l\'utilisateur garde son groupe.',
	'ACP_BBPATREON_STATUS_FORMER'		=> 'Pledge annulé. L\'utilisateur sera retiré de son groupe patron immédiatement (ou après la période de grâce).',
	'ACP_BBPATREON_STATUS_PENDING'		=> 'Compte Patreon lié au forum mais l\'utilisateur n\'est pas actuellement un patron de votre campagne.',

	'ACP_BBPATREON_API_CREDENTIALS'			=> 'Identifiants API',
	'ACP_BBPATREON_API_CREDENTIALS_EXPLAIN'	=> 'Entrez les identifiants de votre client API Patreon. Le Client ID et le Secret sont utilisés pour le flux OAuth. Les jetons Creator sont utilisés pour les appels API serveur à serveur. Toutes les valeurs proviennent de <a href="https://www.patreon.com/portal/registration/register-clients" target="_blank">votre page client API Patreon</a>.',
	'ACP_BBPATREON_CLIENT_ID'				=> 'Client ID',
	'ACP_BBPATREON_CLIENT_ID_EXPLAIN'		=> 'L\'OAuth Client ID de votre portail développeur Patreon.',
	'ACP_BBPATREON_CLIENT_SECRET'			=> 'Client Secret',
	'ACP_BBPATREON_CREATOR_TOKEN'			=> 'Jeton d\'accès créateur',
	'ACP_BBPATREON_CREATOR_TOKEN_EXPLAIN'	=> 'Utilisé pour les appels API serveur à serveur. Ce jeton est automatiquement renouvelé lorsqu\'il expire.',
	'ACP_BBPATREON_CREATOR_REFRESH'			=> 'Jeton de renouvellement créateur',
	'ACP_BBPATREON_CAMPAIGN_ID'				=> 'ID de campagne',
	'ACP_BBPATREON_CAMPAIGN_ID_EXPLAIN'		=> 'L\'ID numérique de votre campagne Patreon. Sauvegardez d\'abord vos jetons, puis cliquez sur "Récupérer".',
	'ACP_BBPATREON_FETCH_CAMPAIGN'			=> 'Récupérer',
	'ACP_BBPATREON_CAMPAIGN_FETCHED'		=> 'ID de campagne récupéré avec succès : %s',
	'ACP_BBPATREON_CAMPAIGN_FETCH_ERROR'	=> 'Impossible de récupérer l\'ID de campagne. Vérifiez que le jeton d\'accès créateur est enregistré et valide.',

	'ACP_BBPATREON_WEBHOOK'						=> 'Webhook',
	'ACP_BBPATREON_WEBHOOK_EXPLAIN'				=> 'Les webhooks permettent la synchronisation en temps réel.<br><br><strong>Option A (recommandée) :</strong> Allez sur <a href="https://www.patreon.com/portal/registration/register-webhooks" target="_blank">patreon.com/portal/registration/register-webhooks</a>, créez un webhook avec l\'URL ci-dessous et collez le secret.<br><strong>Option B :</strong> Cliquez sur "Enregistrer via API".',
	'ACP_BBPATREON_WEBHOOK_URL'					=> 'Votre URL de webhook',
	'ACP_BBPATREON_WEBHOOK_URL_EXPLAIN'			=> 'Copiez cette URL dans le portail webhook Patreon. Cliquez sur le champ pour le sélectionner.',
	'ACP_BBPATREON_WEBHOOK_SECRET'				=> 'Secret du webhook',
	'ACP_BBPATREON_WEBHOOK_SECRET_EXPLAIN'		=> 'Utilisé pour vérifier la signature HMAC-MD5 des webhooks entrants. Collez le secret affiché par Patreon.',
	'ACP_BBPATREON_REGISTER_WEBHOOK'			=> 'Enregistrer via API',
	'ACP_BBPATREON_REGISTER_WEBHOOK_EXPLAIN'	=> 'Enregistre un webhook par programmation. Nécessite le scope <code>w:campaigns.webhook</code>.',
	'ACP_BBPATREON_WEBHOOK_REGISTERED'			=> 'Webhook enregistré avec succès chez Patreon.',
	'ACP_BBPATREON_CHECK_WEBHOOK'				=> 'Vérifier le statut',
	'ACP_BBPATREON_CHECK_WEBHOOK_EXPLAIN'		=> 'Interroger l\'API Patreon pour les webhooks enregistrés, ou envoyer un ping de test.',
	'ACP_BBPATREON_TEST_WEBHOOK'				=> 'Ping de test',
	'ACP_BBPATREON_WEBHOOK_CHECK_ERROR'			=> 'Impossible de récupérer le statut du webhook depuis Patreon.',
	'ACP_BBPATREON_WEBHOOK_NONE_REGISTERED'		=> 'Aucun webhook enregistré chez Patreon pour ce client API.',
	'ACP_BBPATREON_WEBHOOK_STATUS_HEADER'		=> '<strong>Webhooks enregistrés :</strong>',
	'ACP_BBPATREON_WEBHOOK_STATUS_ROW'			=> '<strong>URL :</strong> %1$s<br><strong>En pause :</strong> %2$s<br><strong>Déclencheurs :</strong> %3$s<br><strong>Dernière tentative :</strong> %4$s<br><strong>Échecs consécutifs :</strong> %5$s<br><strong>Correspond à ce forum :</strong> %6$s',
	'ACP_BBPATREON_WEBHOOK_TEST_NO_SECRET'		=> 'Test impossible : aucun secret de webhook configuré.',
	'ACP_BBPATREON_WEBHOOK_TEST_OK'				=> 'Test du webhook réussi ! Votre endpoint sur <strong>%s</strong> a répondu correctement.',
	'ACP_BBPATREON_WEBHOOK_TEST_FAIL'			=> 'Test du webhook échoué. Statut HTTP : %1$s. Réponse : %2$s',
	'ACP_BBPATREON_WEBHOOK_TEST_CURL_ERROR'		=> 'Test du webhook échoué : endpoint inaccessible. Erreur : %s',

	'ACP_BBPATREON_TIER_MAPPING'			=> 'Correspondance palier-groupe',
	'ACP_BBPATREON_TIER_MAPPING_EXPLAIN'	=> 'Associez chaque palier Patreon à un groupe phpBB. Cliquez sur "Récupérer les paliers" pour charger vos paliers automatiquement.',
	'ACP_BBPATREON_FETCH_TIERS'				=> 'Récupérer les paliers',
	'ACP_BBPATREON_FETCH_TIERS_EXPLAIN'		=> 'Chargez vos paliers Patreon via l\'API. L\'ID de campagne doit d\'abord être enregistré.',
	'ACP_BBPATREON_FETCH_TIERS_NO_CAMPAIGN'	=> 'Impossible de récupérer les paliers : aucun ID de campagne configuré.',
	'ACP_BBPATREON_FETCH_TIERS_EMPTY'		=> 'Aucun palier trouvé pour cette campagne.',
	'ACP_BBPATREON_FETCH_TIERS_DONE'		=> '%d palier(s) récupéré(s) de Patreon. Sélectionnez un groupe pour chaque palier et cliquez sur Soumettre.',
	'ACP_BBPATREON_TIER_ID'					=> 'ID du palier Patreon',
	'ACP_BBPATREON_PHPBB_GROUP'				=> 'Groupe phpBB',
	'ACP_BBPATREON_SELECT_GROUP'			=> '-- Sélectionner un groupe --',
	'ACP_BBPATREON_ADD_TIER_MAP'			=> 'Ajouter une correspondance',
	'ACP_BBPATREON_GRACE_PERIOD'			=> 'Période de grâce',
	'ACP_BBPATREON_GRACE_PERIOD_EXPLAIN'	=> 'Nombre de jours d\'attente avant de retirer un utilisateur de son groupe patron. Mettre à 0 pour un retrait immédiat.',

	'ACP_BBPATREON_LINKED_USERS'			=> 'Utilisateurs liés',
	'ACP_BBPATREON_LINKED_USERS_EXPLAIN'	=> 'Utilisateurs du forum ayant lié leur compte Patreon via le Panneau de l\'utilisateur.',
	'ACP_BBPATREON_NO_LINKED_USERS'			=> 'Aucun utilisateur n\'a encore lié son compte Patreon.',
	'ACP_BBPATREON_PATREON_ID'				=> 'ID Patreon',
	'ACP_BBPATREON_TIER'					=> 'Palier',
	'ACP_BBPATREON_STATUS'					=> 'Statut',
	'ACP_BBPATREON_PLEDGE'					=> 'Pledge',
	'ACP_BBPATREON_PATRONS'					=> 'Mécènes',
	'ACP_BBPATREON_UNPUBLISHED'				=> 'non publié',
	'ACP_BBPATREON_LAST_WEBHOOK'			=> 'Dernier webhook',
	'ACP_BBPATREON_LAST_SYNCED'				=> 'Dernière synchro',
	'ACP_BBPATREON_LAST_SYNC'				=> 'Dernière synchro cron',

	'ACP_BBPATREON_MANUAL_SYNC'		=> 'Synchroniser maintenant',
	'ACP_BBPATREON_SYNC_DONE'		=> 'Synchronisation terminée : %1$d membres récupérés, %2$d utilisateurs liés synchronisés.',
	'ACP_BBPATREON_SYNC_ERROR'		=> 'Synchronisation échouée : %s',

	'LOG_ACP_BBPATREON_SETTINGS'	=> '<strong>Paramètres d\'intégration Patreon mis à jour</strong>',
]);
