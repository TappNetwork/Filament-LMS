<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\PersonalAccessToken;

class LmsMcpTokenCommand extends Command
{
    protected $signature = 'lms:mcp-token
        {email : LMS admin email to mint the token for}
        {--rotate : Delete existing lms-mcp tokens for this user before creating a new one}
        {--name=lms-mcp : Sanctum token name}
        {--server-key=filament-lms : Claude Desktop mcpServers key}';

    protected $description = 'Mint a Sanctum token for LMS MCP and print Claude Desktop JSON';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $userModel = config('filament-lms.user_model');

        if (! is_string($userModel) || ! class_exists($userModel)) {
            $this->error('filament-lms.user_model must be a valid user class.');

            return self::FAILURE;
        }

        /** @var Model|null $user */
        $user = $userModel::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No user found for [{$email}].");

            return self::FAILURE;
        }

        if (! method_exists($user, 'isLmsAdmin') || ! $user->isLmsAdmin()) {
            $this->error("[{$email}] is not an LMS admin.");

            return self::FAILURE;
        }

        if (! method_exists($user, 'createToken')) {
            $this->error('The user model must use Laravel\\Sanctum\\HasApiTokens.');

            return self::FAILURE;
        }

        $tokenName = (string) $this->option('name');

        if ($this->option('rotate')) {
            $deleted = PersonalAccessToken::query()
                ->where('tokenable_type', $user->getMorphClass())
                ->where('tokenable_id', $user->getKey())
                ->where('name', $tokenName)
                ->delete();

            $this->info("Deleted {$deleted} existing [{$tokenName}] token(s).");
        }

        $plainTextToken = $user->createToken($tokenName)->plainTextToken;
        $mcpUrl = rtrim((string) config('app.url'), '/').'/mcp/lms';
        $serverKey = (string) $this->option('server-key');

        $config = [
            'mcpServers' => [
                $serverKey => [
                    'url' => $mcpUrl,
                    'headers' => [
                        'Authorization' => 'Bearer '.$plainTextToken,
                    ],
                ],
            ],
        ];

        $this->newLine();
        $this->info("Token minted for {$email} (id {$user->getKey()}). Copy into Claude Desktop MCP settings:");
        $this->newLine();
        $this->line(json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->newLine();
        $this->warn('The plaintext token is shown once. Use --rotate to replace it later.');

        return self::SUCCESS;
    }
}
