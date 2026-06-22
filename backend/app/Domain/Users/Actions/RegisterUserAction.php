<?php

namespace App\Domain\Users\Actions;

use App\Models\User;

class RegisterUserAction
{
    /**
     * Создать нового пользователя. Пароль хешируется каст-ом `hashed` на модели.
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function execute(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
    }
}
