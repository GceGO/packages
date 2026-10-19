<?php

declare(strict_types=1);

namespace Gcego\Settings\Integration\Actions;

use Gcego\Actions\Contracts\ActionHandler;
use Gcego\Actions\Execution\ActionContext;
use Gcego\Settings\Exceptions\InvalidSettingException;
use Gcego\Settings\Exceptions\SettingAuthorizationException;
use Gcego\Settings\Scopes\ScopeContext;
use Gcego\Settings\Scopes\ScopeResolver;
use Gcego\Settings\Scopes\SettingScope;
use Gcego\Settings\Security\SettingsActor;
use Gcego\Settings\Writing\SettingsWriter;
use Gcego\Settings\Writing\SettingsWriteResult;

/**
 * A gcego/actions handler saving validated action input as settings, as
 * the acting user, at a scope resolved on the server (the action's own
 * authorization runs first; the settings' runs again in SettingsWriter):
 *
 *     final class SaveMailSettings extends SaveSettings
 *     {
 *         protected function keys(): array { return ['mail_enabled', 'mail_from_address']; }
 *     }
 *
 *     new Action('save-mail', 'Save', new Handler(SaveMailSettings::class), new Input(...))
 *
 * Only the keys() named here are written; other input is ignored.
 * Settings errors become validation errors of their keys.
 */
abstract class SaveSettings implements ActionHandler
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
        $user = $context->user() ?? throw SettingAuthorizationException::for('change');
        $values = array_intersect_key($context->input->values, array_flip($this->keys()));

        try {
            return app(SettingsWriter::class)->write($this->context(), $values, SettingsActor::user($user));
        } catch (InvalidSettingException $exception) {
            throw $exception->toValidationException();
        }
    }

    protected function context(): ScopeContext
    {
        return app(ScopeResolver::class)->forScreen($this->scope());
    }
}
