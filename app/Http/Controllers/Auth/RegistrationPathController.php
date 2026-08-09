<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationPathController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $accountType = (string) $request->route('accountType', 'student');

        validator(
            ['account_type' => $accountType],
            ['account_type' => ['required', Rule::in(['student', 'creator'])]],
        )->validate();

        return Inertia::render('auth/Register', [
            'accountType' => $accountType,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'creatorAgreementVersion' => config('platform.creator_agreement.version'),
        ]);
    }
}
