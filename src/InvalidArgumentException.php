<?php

declare(strict_types=1);

namespace Neatous\Coordinates;

/** The number of decimal places the calling code passes is out of its range, in the parser and the formatter */
final class InvalidArgumentException extends \InvalidArgumentException implements Exception
{
}
