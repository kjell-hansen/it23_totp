<?php

namespace App\Storage\Implementations;

use App\Models\RefreshToken;
use App\Models\User;
use App\Storage\UserRepository;
use Carbon\Carbon;
use DateTimeInterface;

class DbUserRepository implements UserRepository {

    public function __construct() {
        $this->deleteExpiredTokens();
    }
    public function add(User $user) {
        $user->save();
    }

    public function getUserByEmail(string $email):?User {
        return User::where('email', $email)->first();
    }

    public function saveRefreshToken(string $user_id, string $refreshToken, string $expiresAt):void {
        $hash=hash('sha256', $refreshToken);

        RefreshToken::create([
                                 'user_id' => $user_id,
                                 'token_hash' => $hash,
                                 'expires' => $expiresAt
                             ]);
    }

    public function getUserByRefreshToken(string $refreshtoken):?User {
        $hash=hash('sha256', $refreshtoken);

        $record=RefreshToken::where('token_hash', $hash)
            ->where(function($q) {
                $q->whereNull('expires')
                    ->orWhere('expires', '>', date("Y-m-d H:i:s"));
            })
            ->first();

        if(!$record) {
            return null;
        }

        return User::find($record->user_id);
    }

    private function deleteExpiredTokens():void {
        RefreshToken::where('expires','<', date('Y-m-d H:i:s'))->delete();
    }

    public function deleteRefreshToken(string $refreshToken) {
        $hash=hash('sha256', $refreshToken);

        RefreshToken::where('token_hash', $hash)->delete();
    }

    public function deleteAllRefreshTokens(User $user) {
        RefreshToken::where('user_id', $user->id)->delete();
    }
}
