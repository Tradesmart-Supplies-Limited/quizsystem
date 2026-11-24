<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

Artisan::command('quiz:create-admin {--name=} {--email=} {--password=} {--facilitator : Create a facilitator instead of an administrator}', function () {
    $name = $this->option('name') ?: $this->ask('Full name', 'Quiz Administrator');
    $email = $this->option('email') ?: $this->ask('Email address');
    $password = $this->option('password') ?: $this->secret('Password (min 8 characters)');

    $v = Validator::make(compact('name', 'email', 'password'), [
        'name' => ['required', 'max:100'],
        'email' => ['required', 'email'],
        'password' => ['required', 'min:8'],
    ]);
    if ($v->fails()) {
        foreach ($v->errors()->all() as $e) {
            $this->error($e);
        }

        return 1;
    }

    $user = User::updateOrCreate(['email' => $email], [
        'name' => $name,
        'password' => $password,
        'role' => $this->option('facilitator') ? User::ROLE_FACILITATOR : User::ROLE_ADMIN,
        'is_active' => true,
    ]);
    $this->info("{$user->role} account ready: {$user->email}");

    return 0;
})->purpose('Create (or reset) an administrator / facilitator login for the Quiz Arena');
