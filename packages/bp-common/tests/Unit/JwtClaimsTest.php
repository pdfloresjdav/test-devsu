<?php

namespace BP\Common\Tests\Unit;

use BP\Common\Auth\JwtClaims;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class JwtClaimsTest extends TestCase
{
    public function test_actor_devuelve_el_claim_sub_si_esta_presente(): void
    {
        $request = Request::create('/');
        $request->attributes->set('jwt_claims', ['sub' => 'user-123']);

        $this->assertSame('user-123', JwtClaims::actor($request));
    }

    public function test_actor_devuelve_el_default_si_no_hay_claims(): void
    {
        $request = Request::create('/');

        $this->assertSame('system', JwtClaims::actor($request));
    }

    public function test_bearer_token_extrae_el_token_del_header_authorization(): void
    {
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer abc.def.ghi');

        $this->assertSame('abc.def.ghi', JwtClaims::bearerToken($request));
    }

    public function test_bearer_token_devuelve_null_si_no_hay_header(): void
    {
        $request = Request::create('/');

        $this->assertNull(JwtClaims::bearerToken($request));
    }
}
