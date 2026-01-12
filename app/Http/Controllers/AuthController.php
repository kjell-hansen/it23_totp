<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\JwtService;
use App\Services\TotpService;
use App\Storage\UserRepository;
use Carbon\Carbon;
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
            return response()->json(['error' => "Invalid credentials $email"], 401);
        }

        // Verifiera code
        $totpValid = $this->totpService->verify($user->secret, $code);
        if (!$totpValid) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $accessToken = $this->jwtService->createAccessToken($user->id);
        $refreshToken = $this->jwtService->createRefreshToken();
        $expiresAt = Carbon::now()->addDays(30)->format("Y-m-d H:i:s");

        // Spara refreshtoken i databasen
        $this->repo->saveRefreshToken($user->id, $refreshToken, $expiresAt);

        // Sätt cookies
        $cookie = Cookie::create(
            'refresh_token',
            $refreshToken,
            $expiresAt,
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

    public function refresh(Request $request) {
        // Läs refreshtoken från cookie
        $refreshToken = $request->cookie('refresh_token');

        if (!$refreshToken) {
            return response()->json(['error' => "Missing refreshtoken"], 401);
        }

        // Kontrollera att angivet token finns i databasen
        $user = $this->repo->getUserByRefreshToken($refreshToken);
        if (!$user) {
            return response()->json(['error' => 'Invalid refreshtoken (no user)']);
        }

        // Skapa nya tokens för användaren
        $accessToken = $this->jwtService->createAccessToken($user->id);
        $newRefreshToken = $this->jwtService->createRefreshToken();
        $expiresAt = Carbon::now()->addDays(30)->format("Y-m-d H:i:s");
        $this->repo->saveRefreshToken($user->id, $newRefreshToken, $expiresAt);

        // Sätt cookies
        $cookie = Cookie::create(
            'refresh_token',
            $newRefreshToken,
            $expiresAt,
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

    public function logout(Request $request) {
        $refreshToken=$request->cookie('refresh_token');

        if($refreshToken) {
            $this->repo->deleteRefreshToken($refreshToken);
        }

        $cookie = Cookie::create(
            'refresh_token',
            null,
            -1,
            'refresh',
            null,
            true,
            true,
            false,
            'lax'
        );

        // Returnera ett ogiltigt accesstoken och radera refresh_token-cookien
        return response()->json([
            'access_token'=>null,
            'expires_in'=>-1
        ], 204)->withoutCookie($cookie);
    }

    public function logoutAll(Request $request) {
        $refreshToken=$request->cookie('refresh_token');

        if(!$refreshToken) {
            return response()->json(['error'=>'Missing refreshtoken'],401);
        }

        $user=$this->repo->getUserByRefreshToken($refreshToken);
        if(!$user) {
            return response()->json(['error'=>'Missing refreshtoken'],401);
        }

        $this->repo->deleteAllRefreshTokens($user);
        $cookie = Cookie::create(
            'refresh_token',
            null,
            -1,
            'refresh',
            null,
            true,
            true,
            false,
            'lax'
        );

        // Returnera ett ogiltigt accesstoken och radera refresh_token-cookien
        return response()->json([
                                    'access_token'=>null,
                                    'expires_in'=>-1
                                ], 204)->withoutCookie($cookie);
    }
}
