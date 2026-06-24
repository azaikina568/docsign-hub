<?php

namespace App\Domain\Users\Actions;

use App\Models\User;
use Illuminate\Auth\Events\Registered;

class RegisterUserAction
{
    /**
     * Создать нового пользователя. Пароль хешируется каст-ом `hashed` на модели.
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function execute(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        // Registered → SendEmailVerificationNotification (фреймворк): письмо с подтверждением email.
        event(new Registered($user));

        return $user;
    }
}
