<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\JwtService;
use App\Services\TotpService;
use App\Storage\UserRepository;
use \Symfony\Component\HttpFoundation\Cookie;
use Illuminate\Http\Request;

class AuthController extends Controller {
    public function __construct(private UserRepository $repo, private TotpService $totpService
        , private JwtService                           $jwtService) {}

    public function login(Request $request) {
        $email = filter_var($request->input('email'), FILTER_VALIDATE_EMAIL);
        $code = filter_var($request->input('code'), FILTER_VALIDATE_INT);

        // Hämta användare via epost
        $user = $this->repo->getUserByEmail($email);
        if (!$user) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        // Verifiera code
        $totpValid = $this->totpService->verify($user->secret, $code);
        if (!$totpValid) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $accessToken = $this->jwtService->createAccessToken($user->id);
        $refreshToken = $this->jwtService->createRefreshToken();

        // Sätt cookies
        $cookie = Cookie::create(
            'refresh_token',
            $refreshToken,
            60 * 60 * 24 * 30,
            'refresh',
            null,
            true,
            true,
            false,
            'lax'
        );

        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 900,
            '$user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email
            ]
        ])->withCookie($cookie);
    }
}
