<?php

namespace Chantouch\JWTRedis\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\JWTAuth;
use Tymon\JWTAuth\Manager;
use Tymon\JWTAuth\Token;

class Refreshable extends BaseMiddleware
{
    /**
     * The JWT Authenticator.
     *
     * @var JWTAuth
     */
    protected $auth;

    /**
     * @var Manager
     */
    protected $manager;

    /**
     * @param JWTAuth $auth
     *
     * @param Manager $manager
     */
    public function __construct(JWTAuth $auth, Manager $manager)
    {
        $this->auth = $auth;
        $this->manager = $manager;
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     *@throws UnauthorizedHttpException
     *
     */
    public function handle($request, Closure $next)
    {
        $this->checkForToken($request);

        try {
            $token = $this->auth->parseToken()->refresh();

            /** Application need this assignment for using Laravel's Auth facade. */
            $request->claim = $this->manager->decode(new Token($token))->get('sub');
        } catch (TokenInvalidException | JWTException $e) {
            return $this->respondWithError($e, 401);
        }

        // Send the refreshed token back to the client.
        return $this->setAuthenticationResponse($token);
    }

    /**
     * Check the request for the presence of a token.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    protected function checkForToken(Request $request)
    {
        if (!$this->auth->parser()->setRequest($request)->hasToken()) {
            return $this->respondWithError('TokenNotProvided', 403);
        }
    }

    /**
     * Set the token response.
     *
     * @param null $token
     * @return JsonResponse
     */
    protected function setAuthenticationResponse($token = null): JsonResponse
    {
        if (config('jwt-redis.check_banned_user')) {
            if (!Auth::user()->checkUserStatus()) {
                return $this->respondWithError('AccountBlockedException', 403);
            }
        }

        $token = $token ?: $this->auth->refresh();

        return $this->respond(['token' => $token], 200);
    }
}
