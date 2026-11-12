<?php

declare(strict_types=1);

namespace Neatous\Coordinates\Formatter;

use Neatous\Coordinates\Exception;

/** The coordinates cannot be written: a number is not finite or it is out of range after rounding */
final class FormatterException extends \RuntimeException implements Exception
{
}
