<?php

namespace Tests\Feature\Infra;

use PHPUnit\Framework\TestCase;
use Tests\Support\RunsShell;

/** Static checks of the production infrastructure files (the real stack is validated with Docker). */
class ProductionStackConfigTest extends TestCase
{
    use RunsShell;

    private function file(string $relative): string
    {
        $this->assertFileExists($this->projectPath($relative));

        return file_get_contents($this->projectPath($relative));
    }

    public function test_the_production_compose_file_is_an_independent_project_with_one_published_port(): void
    {
        $compose = $this->file('compose.prod.yaml');

        $this->assertStringContainsString('name: foodorder-prod', $compose);
        $this->assertSame(1, preg_match_all('/^\s+ports:/m', $compose), 'Only one service may publish ports.');
        $this->assertStringContainsString('"127.0.0.1:${PROD_PORT:-8081}:8080"', $compose);
        $this->assertStringNotContainsString('0.0.0.0', $compose);
        $this->assertStringNotContainsString('network_mode', $compose);
        $this->assertStringNotContainsString('container_name', $compose);
        $this->assertStringNotContainsString('external:', $compose);
        $this->assertDoesNotMatchRegularExpression('/^\s*name:\s*foodorder\s*$/m', $compose);
    }

    public function test_the_production_stack_has_no_bind_mounts_of_the_source_code(): void
    {
        $compose = $this->file('compose.prod.yaml');

        $this->assertDoesNotMatchRegularExpression('/^\s+-\s+(\.\/|\.:|\.\.|\/home|\$\{PWD|\$PWD)/m', $compose);
        $this->assertDoesNotMatchRegularExpression('/type:\s*bind/', $compose);
        $this->assertMatchesRegularExpression('/^\s+- sqlite_data:\/data$/m', $compose);
        $this->assertMatchesRegularExpression('/^volumes:\n\s+sqlite_data:/m', $compose);
    }

    public function test_the_production_stack_is_hardened_and_uses_its_own_environment_file(): void
    {
        $compose = $this->file('compose.prod.yaml');

        $this->assertStringContainsString('env_file: .env.prod', $compose);
        $this->assertStringNotContainsString('env_file: .env'."\n", $compose);
        $this->assertStringContainsString('APP_ENV: production', $compose);
        $this->assertStringContainsString('APP_DEBUG: "false"', $compose);
        $this->assertStringContainsString('FOODORDER_ENV_PROD: ${FOODORDER_ENV_PROD:?', $compose);
        $this->assertSame(2, substr_count($compose, 'read_only: true'));
        $this->assertSame(2, substr_count($compose, 'no-new-privileges:true'));
        $this->assertSame(2, substr_count($compose, 'cap_drop:'));
        $this->assertSame(2, substr_count($compose, 'stop_grace_period: 30s'));
        $this->assertSame(2, substr_count($compose, 'max-size: "10m"'));
        $this->assertSame(2, substr_count($compose, 'healthcheck:'));
        $this->assertStringContainsString('condition: service_healthy', $compose);
        $this->assertStringContainsString('target: app', $compose);
        $this->assertStringContainsString('target: web', $compose);
        $this->assertStringContainsString('dockerfile: Dockerfile.prod', $compose);
    }

    public function test_the_production_dockerfile_builds_from_the_lockfile_without_dev_dependencies_or_secrets(): void
    {
        $dockerfile = $this->file('Dockerfile.prod');

        $this->assertStringContainsString('FROM php:8.4-fpm', $dockerfile);
        $this->assertMatchesRegularExpression('/composer install[^\n]*--no-dev/s', $dockerfile);
        $this->assertStringNotContainsString('composer update', $dockerfile);
        foreach (['AS vendor', 'AS app', 'AS web'] as $stage) {
            $this->assertStringContainsString($stage, $dockerfile);
        }
        $this->assertStringContainsString('USER app', $dockerfile);
        $this->assertDoesNotMatchRegularExpression('/COPY\s+\.\s/', $dockerfile, 'The whole build context must not be copied.');
        $this->assertDoesNotMatchRegularExpression('/COPY[^\n]*\.env/', $dockerfile);
        $this->assertDoesNotMatchRegularExpression('/APP_KEY/', $dockerfile);
        $this->assertDoesNotMatchRegularExpression('/\bnpm\b|\bnode\b|vite/i', $dockerfile);
        $this->assertStringContainsString('COPY --from=app', $dockerfile, 'The web image must take its assets from the app image build.');
    }

    public function test_the_nginx_configuration_serves_only_public_and_forwards_only_the_front_controller(): void
    {
        $nginx = $this->file('docker/prod/nginx/default.conf');

        foreach ([
            'server_tokens off', 'root /var/www/public', 'fastcgi_pass app:9000', 'client_max_body_size 1m',
            'try_files $uri /index.php?$query_string', 'SCRIPT_FILENAME /app/public/index.php', 'X-Content-Type-Options', 'nosniff', 'Content-Security-Policy',
            'frame-ancestors', 'autoindex off',
        ] as $needle) {
            $this->assertStringContainsString($needle, $nginx);
        }
        $this->assertMatchesRegularExpression('/location = \/index\.php \{[^}]*internal;/s', $nginx);
        $this->assertMatchesRegularExpression('/location ~ \\\\\.php\$ \{\s*return 404;/', $nginx);
        $this->assertMatchesRegularExpression('/location ~ \/\\\\\.[^{]*\{\s*return 404;/', $nginx);
        $this->assertStringNotContainsString('$document_root$fastcgi_script_name', $nginx);
    }

    public function test_the_production_environment_template_is_safe_and_complete(): void
    {
        $env = $this->file('.env.prod.example');

        foreach (['APP_ENV=production', 'APP_DEBUG=false', 'FOODORDER_ENV_PROD=1', 'PROD_PORT=8081', 'DB_CONNECTION=sqlite', 'DB_DATABASE=/data/database.sqlite', 'LOG_CHANNEL=stderr'] as $line) {
            $this->assertMatchesRegularExpression('/^'.preg_quote($line, '/').'$/m', $env);
        }
        $this->assertMatchesRegularExpression('/^APP_KEY=$/m', $env, 'The template must ship without a key.');
        $this->assertDoesNotMatchRegularExpression('/base64:/', $env);
    }

    public function test_secrets_and_local_files_stay_out_of_git_and_images(): void
    {
        $gitignore = $this->file('.gitignore');
        $dockerignore = $this->file('.dockerignore');

        foreach (['.env.prod', '/context/'] as $entry) {
            $this->assertStringContainsString($entry, $gitignore);
        }
        foreach (['context/', '.env.*', '!.env.prod.example', '.git/', '.agents/', '.claude/', '*.sqlite'] as $entry) {
            $this->assertStringContainsString($entry, $dockerignore);
        }
    }

    public function test_the_development_stack_keeps_its_identity(): void
    {
        $compose = $this->file('compose.yaml');

        $this->assertStringContainsString('name: foodorder'."\n", $compose);
        $this->assertStringNotContainsString('foodorder-prod', $compose);
        $this->assertStringContainsString('env_file: .env', $compose);
    }
}
