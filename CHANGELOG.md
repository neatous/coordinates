# Changelog

## 1.1.0

Added:

- `Parser` and `Formatter` take the default number of decimal places in the constructor:
  `new Parser(decimalPlaces: 5)` (0 to 10, 10 by default) and
  `new Formatter(decimalPlaces: 5, secondsDecimalPlaces: 2)` (3 to 10 and 0 to 3, the defaults of 1.0.0). An
  application configures them once, for example in a dependency injection container, and calls `parse($value)` and
  `format($latitude, $longitude)`. A number given to `parse()` or `format()` still overrides the default, and
  a number out of the range throws `Neatous\Coordinates\InvalidArgumentException` when the object is created.
- The `$decimalPlaces` argument of `Parser::parse()` accepts `null` for the default of the constructor. Its default
  value is now `null` instead of `10`, the result is the same.

## 1.0.0

First release, extracted from the Furtodo applications.

- `Parser` reads decimal degrees, degrees and minutes, and degrees, minutes and seconds, aligned with the original
  library [geo-coordinates-parser](https://github.com/ianengelbrecht/geo-coordinates-parser) at 4b871a7
  (2024-10-10), with fixes of the bugs both share: groups are compared by position, text around the coordinates is
  rejected, each coordinate has at most one direction, minutes above 59 and numbers split between groups are
  rejected, a decimal comma is read in minutes and seconds, and the sign of minus zero degrees is kept.
- `Parser` keeps the whole part of the result with no decimal places (`50`, not `5`) and accepts 0 to 10 decimal
  places (10 by default), the same limit as `Formatter`. Both coordinates of the result have the same number of
  decimal places: the longer of the two with trailing zeros trimmed, but at least 3 (or the given number, if it is
  lower), as one or two decimal places are read as minutes; two whole numbers stay whole (`49.930, 14.881307` gives
  `49.930000` and `14.881307`). Coordinates that round to a latitude of 90 or a longitude of 180 are rejected, the
  range is checked after rounding.
- `Formatter` writes coordinates the parser reads back to the same values, in decimal degrees with the hemisphere
  letters (`Notation::HemisphereDecimal`, default), signed decimal degrees (`Notation::SignedDecimal`) or degrees,
  minutes and seconds (`Notation::DegreesMinutesSeconds`), with 3 to 10 decimal places of the degrees (5 by default)
  or 0 to 3 decimal places of the seconds (2 by default). Whole degrees are written in a form the parser accepts,
  rounding comes first, zero is northern or eastern, and coordinates that are out of range (the message names the
  given value) or not finite throw `FormatterException`.
- All exceptions are final and implement `Neatous\Coordinates\Exception`: `ParserException` (an `\Exception`) and
  `FormatterException` (a `\RuntimeException`) for data the library cannot read or write. Decimal places out of
  the range throw `Neatous\Coordinates\InvalidArgumentException` in the parser and the formatter.
