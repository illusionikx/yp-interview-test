<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Http\Middleware\EnsureUserHasRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureUserHasRoleTest extends TestCase
{
    private function nextMiddleware(): \Closure
    {
        return fn (Request $request) => new Response('ok');
    }

    public function test_it_allows_a_user_whose_role_matches(): void
    {
        $middleware = new EnsureUserHasRole;
        $user = new User(['role' => Role::Lecturer]);

        $request = Request::create('/lecturer');
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, $this->nextMiddleware(), 'lecturer');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    public function test_it_aborts_403_for_a_user_whose_role_does_not_match(): void
    {
        $middleware = new EnsureUserHasRole;
        $user = new User(['role' => Role::Student]);

        $request = Request::create('/lecturer');
        $request->setUserResolver(fn () => $user);

        $this->expectException(HttpException::class);

        try {
            $middleware->handle($request, $this->nextMiddleware(), 'lecturer');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());

            throw $e;
        }
    }

    public function test_it_aborts_403_for_a_guest(): void
    {
        $middleware = new EnsureUserHasRole;

        $request = Request::create('/lecturer');
        $request->setUserResolver(fn () => null);

        $this->expectException(HttpException::class);

        try {
            $middleware->handle($request, $this->nextMiddleware(), 'lecturer');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());

            throw $e;
        }
    }
}
