<?php

declare(strict_types=1);

namespace Gcego\Forms\Contracts;

use Gcego\Forms\Options\OptionCollection;
use Gcego\Forms\State\FormState;

/**
 * Trusted, server-side options of a choice field. The same options render the
 * control and validate the submission, so a value that was never offered is rejected.
 */
interface OptionsProvider
{
    public function options(FormState $state, ?string $model = null): OptionCollection;

    /**
     * Whether every submitted value is a permitted option (relationship providers query only these values).
     *
     * @param  list<string|int>  $values
     */
    public function allows(array $values, FormState $state, ?string $model = null): bool;

    /**
     * State paths whose values change the options (dependent selects).
     *
     * @return list<string>
     */
    public function dependencies(): array;
}
