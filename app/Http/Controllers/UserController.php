<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TotpQrService;
use App\Storage\UserRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class UserController extends Controller {
    public function __construct(private UserRepository $repo) {}

    public function showRegister() {
        return View::make('register');
    }

    public function register(Request $request) {
        try {
            $user = User::factory()->make($request->request->all());

            $this->repo->add($user);

            $qr = TotpQrService::generateQrCode($user);
            $content = View::make('mail', ['qr' => $qr]);
/*
 // Behöver tillgång till en mailserver för att kunna skicka mailet :(
            mail(
                $user->email,
                'Grattis till registreringen!',
                $content,
                'MIME-version:1.0; Content-Type:text/html; charset=UTF-8'
            );
*/
            return $content;
        } catch (UniqueConstraintViolationException $e) {
            return View::make('register', ['message' => 'User email exists']);
        }
    }
}
