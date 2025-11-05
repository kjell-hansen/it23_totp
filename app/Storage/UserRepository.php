<?php

namespace App\Storage;

use App\Models\User;

interface UserRepository {
    public function add(User $user);

    public function getUserByEmail(string $email):?User;
}
