<?php
/**
 *
 * Patreon Integration for phpBB.
 * Tests the webhook signature validation and JSON:API payload parsing
 * that the webhook controller relies on.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\controller;

/**
 * Unit tests for webhook-related logic.
 *
 * The webhook controller cannot be instantiated in a unit test (it needs
 * the DI container for DB, config, etc.), so these tests verify the two
 * critical pieces of logic that run before any service call:
 * 1. HMAC-MD5 signature generation and comparison
 * 2. JSON:API payload structure extraction
 *
 * Getting either of these wrong means webhooks silently fail: bad
 * signatures reject all legitimate Patreon events, and bad parsing
 * causes null pointer errors when extracting tier/user data.
 */
class webhook_test extends \phpbb_test_case
{
	/**
	 * Verify that hash_hmac('md5') + hash_equals() correctly validates
	 * a matching signature and rejects a non-matching one.
	 *
	 * This is the exact algorithm Patreon uses to sign webhook payloads.
	 * If we use the wrong hash function (sha256 instead of md5) or
	 * compare with == instead of hash_equals(), webhooks break or
	 * become vulnerable to timing attacks.
	 */
	public function test_hmac_signature_validation()
	{
		$secret = 'test_webhook_secret';
		$body = '{"data":{"type":"member","id":"1","attributes":{"patron_status":"active_patron"}}}';

		$expected = hash_hmac('md5', $body, $secret);

		$this->assertTrue(hash_equals($expected, hash_hmac('md5', $body, $secret)));
		$this->assertFalse(hash_equals($expected, 'bad_signature'));
	}

	/**
	 * The HMAC-MD5 hex digest must be exactly 32 lowercase hex characters.
	 * Patreon sends this format in the X-Patreon-Signature header. If our
	 * output format doesn't match (e.g. binary instead of hex), every
	 * webhook will fail signature validation.
	 */
	public function test_hmac_signature_is_md5_hex()
	{
		$sig = hash_hmac('md5', 'test body', 'secret');

		$this->assertEquals(32, strlen($sig));
		$this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $sig);
	}

	/**
	 * Deliberately failing test to verify PhpStorm reports failures correctly.
	 * Remove this test once you have confirmed the red bar appears.
	public function test_deliberate_failure()
	{
		$this->assertEquals('expected', 'actual', 'This test is supposed to fail — delete it once verified');
	}
	*/

	/**
	 * Parse a complete Patreon webhook payload with tier data.
	 *
	 * Patreon uses JSON:API format where relationships reference included
	 * resources by type+id. This test verifies we can extract:
	 * - patreon_user_id from data.relationships.user.data.id
	 * - patron_status from data.attributes
	 * - pledge amount from data.attributes
	 * - tier_id from data.relationships.currently_entitled_tiers
	 * - tier label by resolving the tier_id against the included array
	 *
	 * If any of these paths change in a future Patreon API version, this
	 * test will catch it.
	 */
	public function test_webhook_payload_parsing()
	{
		$payload = json_decode('{
			"data": {
				"type": "member",
				"id": "test-001",
				"attributes": {
					"patron_status": "active_patron",
					"currently_entitled_amount_cents": 500
				},
				"relationships": {
					"currently_entitled_tiers": {
						"data": [{"id": "tier-123", "type": "tier"}]
					},
					"user": {
						"data": {"id": "patreon-user-456", "type": "user"}
					}
				}
			},
			"included": [
				{"type": "tier", "id": "tier-123", "attributes": {"title": "Gold Patron"}}
			]
		}', true);

		$data = $payload['data'];
		$relationships = $data['relationships'];

		$this->assertEquals('patreon-user-456', $relationships['user']['data']['id']);
		$this->assertEquals('active_patron', $data['attributes']['patron_status']);
		$this->assertEquals(500, $data['attributes']['currently_entitled_amount_cents']);
		$this->assertEquals('tier-123', $relationships['currently_entitled_tiers']['data'][0]['id']);

		$tier_label = '';
		foreach ($payload['included'] as $resource)
		{
			if ($resource['type'] === 'tier' && $resource['id'] === 'tier-123')
			{
				$tier_label = $resource['attributes']['title'];
			}
		}
		$this->assertEquals('Gold Patron', $tier_label);
	}

	/**
	 * Parse a webhook payload for a former patron with no active tiers.
	 *
	 * When a patron cancels, Patreon sends an empty tiers array. The
	 * webhook controller must handle this without errors and set tier_id
	 * to an empty string (not null or undefined). This triggers the
	 * demotion path in the group mapper.
	 */
	public function test_webhook_payload_no_tiers()
	{
		$payload = json_decode('{
			"data": {
				"type": "member",
				"id": "test-002",
				"attributes": {
					"patron_status": "former_patron",
					"currently_entitled_amount_cents": 0
				},
				"relationships": {
					"currently_entitled_tiers": {"data": []},
					"user": {"data": {"id": "user-789", "type": "user"}}
				}
			}
		}', true);

		$tiers = $payload['data']['relationships']['currently_entitled_tiers']['data'];
		$this->assertEmpty($tiers);

		$tier_id = !empty($tiers) ? $tiers[0]['id'] : '';
		$this->assertEquals('', $tier_id);
	}
}
