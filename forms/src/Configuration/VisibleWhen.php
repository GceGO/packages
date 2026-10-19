<?php

declare(strict_types=1);

namespace Gcego\Forms\Configuration;

/**
 * The field (or layout) is shown, validated and saved only while the condition holds; otherwise its submitted value is ignored.
 */
final readonly class VisibleWhen extends FieldCondition {}
