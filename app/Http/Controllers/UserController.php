<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class UserController extends Controller {

    public function showRegister() {
        return View::make('register');
    }

    public function register(Request $request) {
        $user=User::factory()->make($request->request->all());

        dd($user);
    }
}
