<?php

namespace BP\Common\Tests\Unit;

use BP\Common\Auth\DpopValidationException;
use BP\Common\Auth\DpopValidator;
use BP\Common\Auth\InMemoryDpopReplayStore;
use BP\Common\Testing\RsaKeyPair;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;

class DpopValidatorTest extends TestCase
{
    private function buildProof(RsaKeyPair $keyPair, array $claimsOverride = [], ?string $jti = null): string
    {
        $publicJwk = $keyPair->toJwks()['keys'][0];
        unset($publicJwk['kid'], $publicJwk['use']);

        $claims = array_merge([
            'jti' => $jti ?? bin2hex(random_bytes(8)),
            'htm' => 'POST',
            'htu' => 'http://localhost/transfers',
            'iat' => time(),
        ], $claimsOverride);

        return JWT::encode(
            $claims,
            $keyPair->privateKeyPem,
            'RS256',
            null,
            ['typ' => 'dpop+jwt', 'jwk' => $publicJwk],
        );
    }

    public function test_validates_a_correct_dpop_proof(): void
    {
        $keyPair = new RsaKeyPair;
        $validator = new DpopValidator(new InMemoryDpopReplayStore);

        $proof = $this->buildProof($keyPair);

        $validator->validate($proof, 'POST', 'http://localhost/transfers');

        $this->addToAssertionCount(1);
    }

    public function test_rejects_if_the_http_method_does_not_match(): void
    {
        $keyPair = new RsaKeyPair;
        $validator = new DpopValidator(new InMemoryDpopReplayStore);
        $proof = $this->buildProof($keyPair, ['htm' => 'GET']);

        $this->expectException(DpopValidationException::class);
        $validator->validate($proof, 'POST', 'http://localhost/transfers');
    }

    public function test_rejects_if_the_url_does_not_match(): void
    {
        $keyPair = new RsaKeyPair;
        $validator = new DpopValidator(new InMemoryDpopReplayStore);
        $proof = $this->buildProof($keyPair, ['htu' => 'http://localhost/something-else']);

        $this->expectException(DpopValidationException::class);
        $validator->validate($proof, 'POST', 'http://localhost/transfers');
    }

    public function test_rejects_a_reused_proof_replay(): void
    {
        $keyPair = new RsaKeyPair;
        $validator = new DpopValidator(new InMemoryDpopReplayStore);
        $proof = $this->buildProof($keyPair, [], jti: 'fixed-jti');

        $validator->validate($proof, 'POST', 'http://localhost/transfers');

        $this->expectException(DpopValidationException::class);
        $this->expectExceptionMessage('replay');
        $validator->validate($proof, 'POST', 'http://localhost/transfers');
    }

    public function test_rejects_an_iat_outside_the_tolerance(): void
    {
        $keyPair = new RsaKeyPair;
        $validator = new DpopValidator(new InMemoryDpopReplayStore, iatLeewaySeconds: 30);
        $proof = $this->buildProof($keyPair, ['iat' => time() - 3600]);

        $this->expectException(DpopValidationException::class);
        $validator->validate($proof, 'POST', 'http://localhost/transfers');
    }

    public function test_validates_the_binding_to_the_access_tokens_cnf_jkt(): void
    {
        $keyPair = new RsaKeyPair;
        $validator = new DpopValidator(new InMemoryDpopReplayStore);
        $proof = $this->buildProof($keyPair);

        $publicJwk = $keyPair->toJwks()['keys'][0];
        unset($publicJwk['kid'], $publicJwk['use']);
        $expectedThumbprint = $validator->jwkThumbprint($publicJwk);

        $validator->validate($proof, 'POST', 'http://localhost/transfers', $expectedThumbprint);

        $this->addToAssertionCount(1);
    }

    public function test_rejects_if_the_cnf_jkt_does_not_match(): void
    {
        $keyPair = new RsaKeyPair;
        $otherKey = new RsaKeyPair('other');
        $validator = new DpopValidator(new InMemoryDpopReplayStore);
        $proof = $this->buildProof($keyPair);

        $otherJwk = $otherKey->toJwks()['keys'][0];
        unset($otherJwk['kid'], $otherJwk['use']);
        $otherKeyThumbprint = $validator->jwkThumbprint($otherJwk);

        $this->expectException(DpopValidationException::class);
        $validator->validate($proof, 'POST', 'http://localhost/transfers', $otherKeyThumbprint);
    }
}
