<?php

namespace App\Competition\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class CreateDemoAdmin extends Command
{
    protected $signature = 'competition:create-demo-admin {--allow-real-data : Continue only after acknowledging that the judge can access real student data}';

    protected $description = 'Create or update the configured Digifest judge admin account.';

    public function handle(): int
    {
        $email = trim((string) config('competition.judge_email'));
        $password = (string) config('competition.judge_password');

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Set COMPETITION_JUDGE_EMAIL to a valid email address before running this command.');

            return self::FAILURE;
        }

        if ($password === '' || mb_strlen($password) < 12) {
            $this->error('Set COMPETITION_JUDGE_PASSWORD to a password with at least 12 characters.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('siswa')) {
            $this->error('The siswa table is unavailable; refusing to create the judge account.');

            return self::FAILURE;
        }

        $containsRealStudentData = DB::table('siswa')
            ->where(function ($query): void {
                $query->whereNull('nisn')
                    ->orWhere('nisn', 'not like', '9999%');
            })
            ->exists();

        if ($containsRealStudentData && ! $this->option('allow-real-data')) {
            $this->error('Refusing to create a judge account because siswa contains a NISN outside the synthetic 9999 prefix. Use --allow-real-data only after explicit approval.');

            return self::FAILURE;
        }

        if ($containsRealStudentData) {
            $this->warn('WARNING: allowing this judge account on a database with real student data means the judge can view that data.');
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Juri DigiFest 2026',
                'password' => Hash::make($password),
            ]
        );

        $this->info('Judge admin account created or updated. Password was not displayed.');

        return self::SUCCESS;
    }
}
