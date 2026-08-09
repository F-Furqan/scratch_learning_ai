<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\BloggerStatus;
use App\Models\User;
use App\Services\Creators\CreatorAgreementService;
use App\Services\Identity\UserProfileProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        private readonly UserProfileProvisioner $profiles,
        private readonly CreatorAgreementService $agreements,
    ) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'account_type' => ['nullable', Rule::in(['student', 'creator'])],
            'phone' => ['nullable', 'string', 'max:255'],
            'expertise' => ['required_if:account_type,creator', 'nullable', 'string', 'max:255'],
            'linkedin_url' => ['required_if:account_type,creator', 'nullable', 'url', 'max:2048'],
            'website_url' => ['nullable', 'url', 'max:2048'],
            'application_reason' => ['required_if:account_type,creator', 'nullable', 'string', 'max:2000'],
            'creator_agreement_accepted' => ['exclude_unless:account_type,creator', 'accepted'],
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        if (($input['account_type'] ?? 'student') === 'creator') {
            $this->profiles->provisionBlogger($user, [
                'phone' => $input['phone'] ?? null,
                'expertise' => $input['expertise'] ?? null,
                'linkedin_url' => $input['linkedin_url'] ?? null,
                'website_url' => $input['website_url'] ?? null,
                'application_reason' => $input['application_reason'] ?? null,
                'status' => BloggerStatus::Pending,
            ]);

            $this->agreements->recordAcceptance($user, $this->request());

            return $user;
        }

        $this->profiles->provisionStudent($user);

        return $user;
    }

    private function request(): Request
    {
        /** @var Request $request */
        $request = request();

        return $request;
    }
}
