<h1 align="center">
	<img src="docs/img/neatous.png" alt="Logo" height="120" />
	<br/>
	neatous/coordinates
</h1>

Parser and formatter of geographic coordinates for PHP 8.4+.

The parser is a PHP port of the JavaScript library
[geo-coordinates-parser](https://github.com/ianengelbrecht/geo-coordinates-parser). It reads coordinates in decimal
degrees, degrees and minutes, or degrees, minutes and seconds, and returns decimal degrees. The formatter writes
coordinates in a form the parser reads back to the same values.

## Installation

```bash
composer require neatous/coordinates
```

## Parser

```php
$coordinates = (new Neatous\Coordinates\Parser\Parser())->parse('50°4\'30.5"N 14°25\'12"E');

$coordinates->getDecimalLatitude();  // '50.0751388889'
$coordinates->getDecimalLongitude(); // '14.4200000000'
$coordinates->getVerbatimLatitude(); // '50°4\'30.5"N'
```

The second argument of `parse()` sets the maximum number of decimal places of the result, 0 to 10 (10 by default, see
[Default precision](#default-precision)), and the result is rounded to it. Both coordinates get the same number of
decimal places: the longer of the two after trailing zeros are trimmed, but at least 3 (or the given number, if it is
lower), as the parser reads one or two decimal places as minutes. Two whole numbers stay whole. So `49.930, 14.881307`
gives `'49.930000'` and `'14.881307'`, and more decimal places, such as `49.936752169181595, 14.88130750552173` from
Google Maps, give `'49.9367521692'` and `'14.8813075055'`. Invalid input throws
`Neatous\Coordinates\Parser\ParserException`, decimal places out of the range throw
`Neatous\Coordinates\InvalidArgumentException`. Coordinates that round to a latitude of 90 or a longitude of 180 are
rejected as out of range.

The result is not always valid input: a coordinate rounded to a whole number is rejected, both padded with zeros
(such as `50.000000`) and without them (`50` and `14`), and a result with one or two decimal places is read as
degrees and minutes. To show the coordinates or to prefill a form, use the formatter.

Examples of accepted input:

| Input | Latitude, longitude |
|---|---|
| `40.123, -74.123` | 40.123, -74.123 |
| `40.123° N 74.123° W` | 40.123, -74.123 |
| `40° 7.38' N, 74° 7.38' W` | 40.123, -74.123 |
| `40° 7' 22.8" N 74° 7' 22.8" W` | 40.123, -74.123 |
| `N 50.08765, E 14.42123` | 50.08765, 14.42123 |
| `49.930, 14.881307` | 49.930000, 14.881307 |
| `48N, 14E` | 48, 14 |

Read the input as the original library does:

- A value with one or two decimal places after the degrees, such as `50.08`, is degrees and minutes (50°08'), not
  decimal degrees. Write decimal degrees with at least three decimal places, or use the formatter.
- Each coordinate has at most one direction, before or after the number, and both coordinates have one or none.
- Text around the coordinates, minutes or seconds above 59 and numbers split between degrees, minutes and seconds
  are rejected.

## Formatter

```php
use Neatous\Coordinates\Formatter\Formatter;
use Neatous\Coordinates\Formatter\Notation;

$formatter = new Formatter();

$formatter->format(50.08765, 14.42123);   // 'N 50.08765, E 14.42123'
$formatter->format(-33.45, -70.66667);    // 'S 33.45000, W 70.66667'
$formatter->format(50.08765, -14.42123, Notation::SignedDecimal);         // '50.08765, -14.42123'
$formatter->format(50.08765, 14.42123, Notation::DegreesMinutesSeconds);    // 'N 50° 5\' 15.54", E 14° 25\' 16.43"'
$formatter->format(50.08765, 14.42123, Notation::DegreesMinutesSeconds, 0); // 'N 50° 5\' 16", E 14° 25\' 16"'
$formatter->format(50.08765, 14.42123, Notation::HemisphereDecimal, 3);    // 'N 50.088, E 14.421'
```

The formatter writes coordinates in a form the parser reads back to the same values, rounded to the given decimal
places. Use it to prefill a form field, or anywhere a user may copy the coordinates into a form.

Parse the output with at least as many decimal places as it has (7 or more for seconds), otherwise a value within about
half a metre of a pole or the 180th meridian rounds to 90 or 180 and is rejected.

| Notation | Example | Decimal places |
|---|---|---|
| `HemisphereDecimal` (default) | `N 50.08765, E 14.42123` | of the degrees, 3 to 10 (5 by default) |
| `SignedDecimal` | `50.08765, -14.42123` | of the degrees, 3 to 10 (5 by default) |
| `DegreesMinutesSeconds` | `N 50° 5' 15.54", E 14° 25' 16.43"` | of the seconds, 0 to 3 (2 by default) |

The defaults can be changed in the constructor, see [Default precision](#default-precision). More than 10 decimal
places of the degrees would show the error of the float, such as `55.03400000000001`, and the
parser reads at most three decimal places of the seconds.

Decimal degrees get at least three decimal places, so the parser does not read them as degrees and minutes. The parser
also rejects a whole number written with decimal places, such as `50.00000`, so whole degrees are written differently:

```php
$formatter->format(50.0, 14.0);                          // 'N 50, E 14'
$formatter->format(50.0, 14.4);                          // 'N 50° 0.0000\', E 14° 24.0000\''
$formatter->format(50.0, -14.0, Notation::SignedDecimal); // '50° 0.0000\', -14° 0.0000\''
$formatter->format(49.999999, 14.4);                     // 'N 50° 0.0000\', E 14° 24.0000\''
$formatter->format(-0.000001, 14.5);                     // 'N 0° 0.0000\', E 14° 30.0000\''
```

- Both coordinates whole: whole degrees with the hemisphere letters. The parser does not read whole degrees without
  them, so `SignedDecimal` uses degrees and minutes as below.
- One coordinate whole: both coordinates in degrees and decimal minutes, with one decimal place less than the degrees,
  which keeps the value exact.
- Whether a coordinate is whole, and its hemisphere, is decided after rounding: `49.999999` is written as `50`, and
  `-0.000001` as zero, which is northern or eastern (`N`, `E`) and has no minus sign.

Seconds rounded up to 60 carry over into the minutes, and minutes into the degrees. A latitude of 90 or more, or
a longitude of 180 or more (in absolute value, after rounding), and a number that is not finite throw
`Neatous\Coordinates\Formatter\FormatterException`. Decimal places out of the range throw
`Neatous\Coordinates\InvalidArgumentException`, which `FormatterException` does not extend: the first is a wrong
argument of the calling code, the second is data the formatter cannot write.

## Default precision

The constructors of the parser and the formatter take the default number of decimal places, so an application sets
it once, for example in the configuration of its dependency injection container, and calls `parse($value)` and
`format($latitude, $longitude)` without it. A change of the defaults of the library then does not change the results
of the application. A number given to `parse()` or `format()` still overrides the default, both fewer and more decimal
places.

```php
use Neatous\Coordinates\Formatter\Formatter;
use Neatous\Coordinates\Formatter\Notation;
use Neatous\Coordinates\Parser\Parser;

$parser = new Parser(decimalPlaces: 5);
$formatter = new Formatter(decimalPlaces: 5, secondsDecimalPlaces: 0);

$parser->parse('49.936752169181595, 14.88130750552173')->getDecimalLatitude();     // '49.93675'
$parser->parse('49.936752169181595, 14.88130750552173', 10)->getDecimalLatitude(); // '49.9367521692'
$formatter->format(49.936752169181595, 14.88130750552173);                         // 'N 49.93675, E 14.88131'
$formatter->format(50.08765, 14.42123, Notation::DegreesMinutesSeconds);           // 'N 50° 5\' 16", E 14° 25\' 16"'
$formatter->format(50.08765, 14.42123, Notation::HemisphereDecimal, 3);            // 'N 50.088, E 14.421'
```

| Constructor argument | Default of | Range | Default |
|---|---|---|---|
| `Parser` `decimalPlaces` | `parse()` | 0 to 10 | 10 |
| `Formatter` `decimalPlaces` | `format()` in `HemisphereDecimal` and `SignedDecimal` | 3 to 10 | 5 |
| `Formatter` `secondsDecimalPlaces` | `format()` in `DegreesMinutesSeconds` | 0 to 3 | 2 |

A number out of the range throws `Neatous\Coordinates\InvalidArgumentException` when the object is created.

With [Nette DI](https://doc.nette.org/en/dependency-injection), register both services in the configuration:

```neon
services:
	- Neatous\Coordinates\Parser\Parser(decimalPlaces: 5)
	- Neatous\Coordinates\Formatter\Formatter(decimalPlaces: 5)
```

Set the parser and the formatter to the same number of decimal places. The parser then stores coordinates with the
precision the formatter shows, so a form prefilled with stored coordinates and saved without a change keeps them.
This holds for the decimal notations; degrees, minutes and seconds keep five decimal places of the degrees only
with at least 2 decimal places of the seconds (the default).

## Exceptions

All exceptions of the library implement `Neatous\Coordinates\Exception`:

| Exception | Thrown when | Extends |
|---|---|---|
| `Neatous\Coordinates\Parser\ParserException` | the parser does not read the input | `\Exception` |
| `Neatous\Coordinates\Formatter\FormatterException` | the formatter cannot write the coordinates | `\RuntimeException` |
| `Neatous\Coordinates\InvalidArgumentException` | the number of decimal places is out of the range | `\InvalidArgumentException` |

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/phpcs
```

## License

MIT, see [LICENSE](LICENSE). The parser is derived from geo-coordinates-parser by Ian Engelbrecht, also MIT.
