<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class OperatorAccountSeeder extends Seeder
{
    public function run(): void
    {
        $username = config('managed.operator_username');
        $password = config('managed.operator_password');

        if (! is_string($username) || preg_match('/^[A-Za-z0-9._-]{3,64}$/', $username) !== 1) {
            throw new RuntimeException('OPERATOR_USERNAME must be 3-64 letters, numbers, dots, underscores, or hyphens.');
        }

        if (! is_string($password) || strlen($password) < 8) {
            throw new RuntimeException('Set OPERATOR_PASSWORD to a password with at least 8 characters in the untracked .env file.');
        }

        $sourceAdmin = User::query()
            ->where('role', 'admin')
            ->whereHas('servers', fn ($query) => $query->where('servers.is_active', true))
            ->orderBy('id')
            ->first();

        if (! $sourceAdmin instanceof User) {
            throw new RuntimeException('An admin user with an active CHR assignment is required before creating the operator account.');
        }

        $serverIds = $sourceAdmin->servers()
            ->where('servers.is_active', true)
            ->pluck('servers.id');

        if ($serverIds->isEmpty()) {
            throw new RuntimeException('The source admin has no active CHR assignments.');
        }

        $operator = User::query()->where('username', $username)->first();

        if ($operator instanceof User) {
            if ($operator->role !== 'operator') {
                throw new RuntimeException('The operator username is already assigned to a non-operator account.');
            }
        } else {
            $operator = User::query()->create([
                'name' => 'Operator',
                'email' => null,
                'username' => $username,
                'role' => 'operator',
                'password' => $password,
            ]);
        }

        $operator->servers()->sync($serverIds);

        $this->command?->info('Operator account is ready with the active CHR assignments copied from the first admin.');
    }
}
