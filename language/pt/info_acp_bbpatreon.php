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
	'ACP_BBPATREON_TITLE'	=> 'Integração Patreon',
	'ACP_BBPATREON'			=> 'Configurações',

	'ACP_BBPATREON_SETTING_SAVED'	=> 'As configurações do Patreon foram guardadas com sucesso!',
	'ACP_BBPATREON_HELP'			=> 'Como é que isto funciona?',

	'ACP_BBPATREON_OVERVIEW_TITLE'		=> 'O que esta extensão faz',
	'ACP_BBPATREON_OVERVIEW'			=> 'Esta extensão liga a sua página de criador do Patreon ao seu fórum. Os membros podem vincular a sua conta Patreon a partir do Painel de Controlo do Utilizador. Uma vez vinculados, são automaticamente atribuídos a grupos phpBB com base no seu nível de Patreon. Quando um patrono muda de nível ou cancela, a associação ao grupo é atualizada automaticamente.',

	'ACP_BBPATREON_HOW_IT_WORKS_TITLE'	=> 'Passos de configuração',
	'ACP_BBPATREON_STEP_CREDENTIALS'	=> 'Introduza as suas credenciais da API do Patreon abaixo. Obtém-nas criando um cliente OAuth em <a href="https://www.patreon.com/portal/registration/register-clients" target="_blank">patreon.com/portal/registration/register-clients</a>. Defina o URI de redirecionamento para: <strong>https://seuforum.pt/patreon/callback</strong> (substitua pelo URL real do seu fórum).',
	'ACP_BBPATREON_STEP_TIERS'			=> 'Associe cada nível do Patreon a um grupo phpBB na secção de Mapeamento de Níveis. Clique em "Obter níveis" para carregá-los automaticamente.',
	'ACP_BBPATREON_STEP_WEBHOOK'		=> 'Registe um webhook para que o Patreon notifique o seu fórum em tempo real quando os pledges mudam. Opcional mas recomendado.',
	'ACP_BBPATREON_STEP_USERS'			=> 'Diga aos seus membros para visitarem o Painel de Controlo do Utilizador e clicarem em "Vincular a sua conta Patreon".',

	'ACP_BBPATREON_SYNC_TITLE'			=> 'Como funciona a sincronização',
	'ACP_BBPATREON_SYNC_EXPLAIN'		=> 'A associação a grupos é mantida atualizada através de três mecanismos: <strong>Vinculação OAuth</strong> (grupos são atribuídos imediatamente ao vincular), <strong>Webhooks</strong> (Patreon envia notificações em tempo real), e uma <strong>Tarefa cron noturna</strong> (reconciliação completa a cada 24 horas).',

	'ACP_BBPATREON_STATUSES_TITLE'		=> 'Estados de pledge',
	'ACP_BBPATREON_STATUS_ACTIVE'		=> 'A pledgear ativamente e pagamento em dia. O utilizador está atribuído ao grupo do seu nível.',
	'ACP_BBPATREON_STATUS_DECLINED'		=> 'Pagamento falhou mas o pledge ainda não foi cancelado. Durante o período de graça o utilizador mantém o seu grupo.',
	'ACP_BBPATREON_STATUS_FORMER'		=> 'Pledge cancelado. O utilizador será removido do seu grupo de patrono imediatamente (ou após o período de graça).',
	'ACP_BBPATREON_STATUS_PENDING'		=> 'Conta Patreon vinculada ao fórum mas o utilizador não é atualmente um patrono da sua campanha.',

	'ACP_BBPATREON_API_CREDENTIALS'			=> 'Credenciais da API',
	'ACP_BBPATREON_API_CREDENTIALS_EXPLAIN'	=> 'Introduza as credenciais do seu cliente API do Patreon. O Client ID e Secret são usados para o fluxo OAuth. Os tokens de Creator são usados para chamadas API servidor a servidor. Todos os valores vêm da <a href="https://www.patreon.com/portal/registration/register-clients" target="_blank">sua página de cliente API do Patreon</a>.',
	'ACP_BBPATREON_CLIENT_ID'				=> 'Client ID',
	'ACP_BBPATREON_CLIENT_ID_EXPLAIN'		=> 'O OAuth Client ID do seu Portal de Desenvolvedor do Patreon.',
	'ACP_BBPATREON_CLIENT_SECRET'			=> 'Client Secret',
	'ACP_BBPATREON_CREATOR_TOKEN'			=> 'Token de acesso do criador',
	'ACP_BBPATREON_CREATOR_TOKEN_EXPLAIN'	=> 'Usado para chamadas API servidor a servidor. Este token é automaticamente renovado quando expira.',
	'ACP_BBPATREON_CREATOR_REFRESH'			=> 'Token de renovação do criador',
	'ACP_BBPATREON_CAMPAIGN_ID'				=> 'ID da campanha',
	'ACP_BBPATREON_CAMPAIGN_ID_EXPLAIN'		=> 'O ID numérico da sua campanha Patreon. Guarde primeiro os seus tokens e depois clique em "Obter".',
	'ACP_BBPATREON_FETCH_CAMPAIGN'			=> 'Obter',
	'ACP_BBPATREON_CAMPAIGN_FETCHED'		=> 'ID da campanha obtido com sucesso: %s',
	'ACP_BBPATREON_CAMPAIGN_FETCH_ERROR'	=> 'Não foi possível obter o ID da campanha. Verifique que o token de acesso do criador está guardado e é válido.',

	'ACP_BBPATREON_WEBHOOK'						=> 'Webhook',
	'ACP_BBPATREON_WEBHOOK_EXPLAIN'				=> 'Os webhooks permitem sincronização em tempo real.<br><br><strong>Opção A (recomendada):</strong> Vá a <a href="https://www.patreon.com/portal/registration/register-webhooks" target="_blank">patreon.com/portal/registration/register-webhooks</a>, crie um webhook com o URL abaixo e cole o segredo.<br><strong>Opção B:</strong> Clique em "Registar via API".',
	'ACP_BBPATREON_WEBHOOK_URL'					=> 'O seu URL de webhook',
	'ACP_BBPATREON_WEBHOOK_URL_EXPLAIN'			=> 'Copie este URL para o portal de webhooks do Patreon. Clique no campo para o selecionar.',
	'ACP_BBPATREON_WEBHOOK_SECRET'				=> 'Segredo do webhook',
	'ACP_BBPATREON_WEBHOOK_SECRET_EXPLAIN'		=> 'Usado para verificar a assinatura HMAC-MD5 dos webhooks recebidos. Cole o segredo mostrado pelo Patreon.',
	'ACP_BBPATREON_REGISTER_WEBHOOK'			=> 'Registar via API',
	'ACP_BBPATREON_REGISTER_WEBHOOK_EXPLAIN'	=> 'Regista um webhook programaticamente. Requer o scope <code>w:campaigns.webhook</code>.',
	'ACP_BBPATREON_WEBHOOK_REGISTERED'			=> 'Webhook registado com sucesso no Patreon.',
	'ACP_BBPATREON_CHECK_WEBHOOK'				=> 'Verificar estado',
	'ACP_BBPATREON_CHECK_WEBHOOK_EXPLAIN'		=> 'Consultar a API do Patreon para webhooks registados, ou enviar um ping de teste.',
	'ACP_BBPATREON_TEST_WEBHOOK'				=> 'Ping de teste',
	'ACP_BBPATREON_WEBHOOK_CHECK_ERROR'			=> 'Não foi possível obter o estado do webhook do Patreon.',
	'ACP_BBPATREON_WEBHOOK_NONE_REGISTERED'		=> 'Nenhum webhook registado no Patreon para este cliente API.',
	'ACP_BBPATREON_WEBHOOK_STATUS_HEADER'		=> '<strong>Webhooks registados:</strong>',
	'ACP_BBPATREON_WEBHOOK_STATUS_ROW'			=> '<strong>URL:</strong> %1$s<br><strong>Em pausa:</strong> %2$s<br><strong>Gatilhos:</strong> %3$s<br><strong>Última tentativa:</strong> %4$s<br><strong>Falhas consecutivas:</strong> %5$s<br><strong>Corresponde a este fórum:</strong> %6$s',
	'ACP_BBPATREON_WEBHOOK_TEST_NO_SECRET'		=> 'Não é possível testar: nenhum segredo de webhook configurado.',
	'ACP_BBPATREON_WEBHOOK_TEST_OK'				=> 'Teste de webhook bem-sucedido! O seu endpoint em <strong>%s</strong> respondeu corretamente.',
	'ACP_BBPATREON_WEBHOOK_TEST_FAIL'			=> 'Teste de webhook falhou. Estado HTTP: %1$s. Resposta: %2$s',
	'ACP_BBPATREON_WEBHOOK_TEST_CURL_ERROR'		=> 'Teste de webhook falhou: endpoint inacessível. Erro: %s',

	'ACP_BBPATREON_TIER_MAPPING'			=> 'Mapeamento de nível para grupo',
	'ACP_BBPATREON_TIER_MAPPING_EXPLAIN'	=> 'Associe cada nível do Patreon a um grupo phpBB. Clique em "Obter níveis" para carregar automaticamente.',
	'ACP_BBPATREON_FETCH_TIERS'				=> 'Obter níveis',
	'ACP_BBPATREON_FETCH_TIERS_EXPLAIN'		=> 'Carregue os seus níveis do Patreon via API. O ID da campanha deve estar guardado primeiro.',
	'ACP_BBPATREON_FETCH_TIERS_NO_CAMPAIGN'	=> 'Não é possível obter níveis: nenhum ID de campanha configurado.',
	'ACP_BBPATREON_FETCH_TIERS_EMPTY'		=> 'Nenhum nível encontrado para esta campanha.',
	'ACP_BBPATREON_FETCH_TIERS_DONE'		=> '%d nível(eis) obtido(s) do Patreon. Selecione um grupo para cada nível e clique em Submeter.',
	'ACP_BBPATREON_TIER_ID'					=> 'ID do nível Patreon',
	'ACP_BBPATREON_PHPBB_GROUP'				=> 'Grupo phpBB',
	'ACP_BBPATREON_SELECT_GROUP'			=> '-- Selecionar grupo --',
	'ACP_BBPATREON_ADD_TIER_MAP'			=> 'Adicionar mapeamento de nível',
	'ACP_BBPATREON_GRACE_PERIOD'			=> 'Período de graça',
	'ACP_BBPATREON_GRACE_PERIOD_EXPLAIN'	=> 'Dias de espera antes de remover um utilizador do seu grupo de patrono. Defina como 0 para remoção imediata.',

	'ACP_BBPATREON_LINKED_USERS'			=> 'Utilizadores vinculados',
	'ACP_BBPATREON_LINKED_USERS_EXPLAIN'	=> 'Utilizadores do fórum que vincularam a sua conta Patreon via Painel de Controlo do Utilizador.',
	'ACP_BBPATREON_NO_LINKED_USERS'			=> 'Nenhum utilizador vinculou a sua conta Patreon ainda.',
	'ACP_BBPATREON_PATREON_ID'				=> 'ID Patreon',
	'ACP_BBPATREON_TIER'					=> 'Nível',
	'ACP_BBPATREON_STATUS'					=> 'Estado',
	'ACP_BBPATREON_PLEDGE'					=> 'Pledge',
	'ACP_BBPATREON_LAST_WEBHOOK'			=> 'Último webhook',
	'ACP_BBPATREON_LAST_SYNCED'				=> 'Última sincronização',
	'ACP_BBPATREON_LAST_SYNC'				=> 'Última sincronização cron',

	'ACP_BBPATREON_MANUAL_SYNC'		=> 'Sincronizar agora',
	'ACP_BBPATREON_SYNC_DONE'		=> 'Sincronização concluída: %1$d membros obtidos, %2$d utilizadores vinculados sincronizados.',
	'ACP_BBPATREON_SYNC_ERROR'		=> 'Sincronização falhou: %s',

	'LOG_ACP_BBPATREON_SETTINGS'	=> '<strong>Configurações de integração Patreon atualizadas</strong>',
]);
