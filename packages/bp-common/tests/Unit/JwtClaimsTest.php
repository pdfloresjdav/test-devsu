<?php

namespace BP\Common\Tests\Unit;

use BP\Common\Auth\JwtClaims;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class JwtClaimsTest extends TestCase
{
    public function test_actor_returns_the_sub_claim_if_present(): void
    {
        $request = Request::create('/');
        $request->attributes->set('jwt_claims', ['sub' => 'user-123']);

        $this->assertSame('user-123', JwtClaims::actor($request));
    }

    public function test_actor_returns_the_default_if_there_are_no_claims(): void
    {
        $request = Request::create('/');

        $this->assertSame('system', JwtClaims::actor($request));
    }

    public function test_bearer_token_extracts_the_token_from_the_authorization_header(): void
    {
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer abc.def.ghi');

        $this->assertSame('abc.def.ghi', JwtClaims::bearerToken($request));
    }

    public function test_bearer_token_returns_null_if_there_is_no_header(): void
    {
        $request = Request::create('/');

        $this->assertNull(JwtClaims::bearerToken($request));
    }
}
