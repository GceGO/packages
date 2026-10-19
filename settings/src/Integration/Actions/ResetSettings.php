<?php

declare(strict_types=1);

namespace Gcego\Settings\Integration\Actions;

use Gcego\Actions\Contracts\ActionHandler;
use Gcego\Actions\Execution\ActionContext;
use Gcego\Settings\Exceptions\InvalidSettingException;
use Gcego\Settings\Exceptions\SettingAuthorizationException;
use Gcego\Settings\Scopes\ScopeResolver;
use Gcego\Settings\Scopes\SettingScope;
use Gcego\Settings\Security\SettingsActor;
use Gcego\Settings\Writing\SettingsWriter;
use Gcego\Settings\Writing\SettingsWriteResult;

/**
 * A gcego/actions handler removing overrides (the settings inherit again)
 * at a scope resolved on the server, as the acting user. Pair it with a
 * Confirmation on the action:
 *
 *     final class ResetBranding extends ResetSettings
 *     {
 *         protected function keys(): array { return ['brand_color', 'brand_logo']; }
 *         protected function scope(): SettingScope { return SettingScope::Tenant; }
 *     }
 */
abstract class ResetSettings implements ActionHandler
{
    /**
     * @return list<string>
     */
    abstract protected function keys(): array;

    protected function scope(): SettingScope
    {
        return SettingScope::Global;
    }

    public function handle(ActionContext $context): SettingsWriteResult
    {
        $user = $context->user() ?? throw SettingAuthorizationException::for('reset');

        try {
            return app(SettingsWriter::class)->reset(app(ScopeResolver::class)->forScreen($this->scope()), $this->keys(), SettingsActor::user($user));
        } catch (InvalidSettingException $exception) {
            throw $exception->toValidationException();
        }
    }
}
