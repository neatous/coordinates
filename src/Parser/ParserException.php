<?php

declare(strict_types=1);

namespace Neatous\Coordinates\Parser;

use Neatous\Coordinates\Exception;

/** The input is not coordinates the parser reads */
final class ParserException extends \Exception implements Exception
{
}
