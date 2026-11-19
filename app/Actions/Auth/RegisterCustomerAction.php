<?php

namespace App\Actions\Auth;

use App\Enums\AccountType;
use App\Models\User;

class RegisterCustomerAction
{
    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function execute(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'account_type' => AccountType::Customer,
            'is_active' => true,
        ]);
    }
}
