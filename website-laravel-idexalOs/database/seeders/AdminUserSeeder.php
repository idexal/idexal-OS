<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('idexal.admin.email', 'admin@idexal.com');
        $configuredPassword = (string) config('idexal.admin.password', '');
        $password = $configuredPassword !== '' ? $configuredPassword : Str::password(20);
        $user = User::query()->where('email', $email)->first();
        $created = $user === null;

        if ($user === null) {
            $user = User::query()->create([
                'name' => (string) config('idexal.admin.name', 'Platform Admin'),
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);
        }

        $user->forceFill([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        if ($user->memberships()->where('status', 'active')->doesntExist()) {
            app(OnboardingService::class)->createPersonalOrganization($user);
        }

        if ($created) {
            $this->command?->warn('Admin account created.');
            $this->command?->line('Email: '.$email);
            $this->command?->line('Password: '.$password);
            $this->command?->warn('Change this password immediately after your first login.');
        } else {
            $this->command?->info('Admin account already exists: '.$email);
        }
    }
}
