<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Authentication', weight: 2)]
class EmailVerificationController extends Controller
{
    /**
     * Verify an email address via the signed link from the email.
     *
     * Подпись ссылки и есть доказательство контроля над ящиком (capability), поэтому роут публичный —
     * `signed` middleware уже проверил подпись и срок. Идемпотентно: повторный переход не падает.
     */
    public function verify(string $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        // Хеш привязан к текущему email: если адрес сменился после выдачи ссылки — ссылка больше не валидна.
        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403, 'Invalid verification link.');
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return response()->json(['message' => 'Email verified.']);
    }

    /**
     * Resend the email verification link to the authenticated user.
     */
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification link sent.']);
    }
}
