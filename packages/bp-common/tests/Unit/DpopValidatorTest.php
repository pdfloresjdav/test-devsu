<?php

namespace BP\Common\Tests\Unit;

use BP\Common\Auth\DpopValidationException;
use BP\Common\Auth\DpopValidator;
use BP\Common\Auth\InMemoryDpopReplayStore;
use BP\Common\Tests\Support\RsaKeyPair;
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

    public function test_valida_un_dpop_proof_correcto(): void
    {
        $keyPair = new RsaKeyPair();
        $validator = new DpopValidator(new InMemoryDpopReplayStore());

        $proof = $this->buildProof($keyPair);

        $validator->validate($proof, 'POST', 'http://localhost/transfers');

        $this->addToAssertionCount(1);
    }

    public function test_rechaza_si_el_metodo_http_no_coincide(): void
    {
        $keyPair = new RsaKeyPair();
        $validator = new DpopValidator(new InMemoryDpopReplayStore());
        $proof = $this->buildProof($keyPair, ['htm' => 'GET']);

        $this->expectException(DpopValidationException::class);
        $validator->validate($proof, 'POST', 'http://localhost/transfers');
    }

    public function test_rechaza_si_la_url_no_coincide(): void
    {
        $keyPair = new RsaKeyPair();
        $validator = new DpopValidator(new InMemoryDpopReplayStore());
        $proof = $this->buildProof($keyPair, ['htu' => 'http://localhost/otra-cosa']);

        $this->expectException(DpopValidationException::class);
        $validator->validate($proof, 'POST', 'http://localhost/transfers');
    }

    public function test_rechaza_un_proof_reutilizado_replay(): void
    {
        $keyPair = new RsaKeyPair();
        $validator = new DpopValidator(new InMemoryDpopReplayStore());
        $proof = $this->buildProof($keyPair, [], jti: 'jti-fijo');

        $validator->validate($proof, 'POST', 'http://localhost/transfers');

        $this->expectException(DpopValidationException::class);
        $this->expectExceptionMessage('replay');
        $validator->validate($proof, 'POST', 'http://localhost/transfers');
    }

    public function test_rechaza_un_iat_fuera_de_tolerancia(): void
    {
        $keyPair = new RsaKeyPair();
        $validator = new DpopValidator(new InMemoryDpopReplayStore(), iatLeewaySeconds: 30);
        $proof = $this->buildProof($keyPair, ['iat' => time() - 3600]);

        $this->expectException(DpopValidationException::class);
        $validator->validate($proof, 'POST', 'http://localhost/transfers');
    }

    public function test_valida_el_amarre_al_cnf_jkt_del_access_token(): void
    {
        $keyPair = new RsaKeyPair();
        $validator = new DpopValidator(new InMemoryDpopReplayStore());
        $proof = $this->buildProof($keyPair);

        $publicJwk = $keyPair->toJwks()['keys'][0];
        unset($publicJwk['kid'], $publicJwk['use']);
        $expectedThumbprint = $validator->jwkThumbprint($publicJwk);

        $validator->validate($proof, 'POST', 'http://localhost/transfers', $expectedThumbprint);

        $this->addToAssertionCount(1);
    }

    public function test_rechaza_si_el_cnf_jkt_no_coincide(): void
    {
        $keyPair = new RsaKeyPair();
        $otraLlave = new RsaKeyPair('otra');
        $validator = new DpopValidator(new InMemoryDpopReplayStore());
        $proof = $this->buildProof($keyPair);

        $otraJwk = $otraLlave->toJwks()['keys'][0];
        unset($otraJwk['kid'], $otraJwk['use']);
        $thumbprintDeOtraLlave = $validator->jwkThumbprint($otraJwk);

        $this->expectException(DpopValidationException::class);
        $validator->validate($proof, 'POST', 'http://localhost/transfers', $thumbprintDeOtraLlave);
    }
}
