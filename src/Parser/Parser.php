<?php

declare(strict_types=1);

namespace Neatous\Coordinates\Parser;

use Neatous\Coordinates\InvalidArgumentException;

/**
 * PHP port of the Geo Coordinates Parser javascript library
 * https://github.com/ianengelbrecht/geo-coordinates-parser/
 */
final class Parser
{
	// a float carries about 15 significant digits and the whole part has up to 3 of them, so 14 or more decimal
	// places show the error of the float, such as 50.08765000000001; the limit is 10, the same as in the formatter
	private const int MAX_DECIMAL_PLACES = 10;

	/** @param int $decimalPlaces default maximum number of decimal places of the result, 0 to 10 */
	public function __construct(
		private readonly int $decimalPlaces = self::MAX_DECIMAL_PLACES,
	) {
		$this->checkDecimalPlaces($decimalPlaces);
	}

	/** @param int|null $decimalPlaces maximum number of decimal places of the result, null for the default one */
	public function parse(string $input, ?int $decimalPlaces = null): Coordinates
	{
		$decimalPlaces ??= $this->decimalPlaces;
		$this->checkDecimalPlaces($decimalPlaces);

		$coordsString = trim(preg_replace('/\s+/u', ' ', $input) ?? '');

		$ddLat = null;
		$ddLng = null;
		$latdir = null;
		$lngdir = null;
		$matches = [];
		$matchSuccess = false;

		//minutes above 59 would be read as other formats
		if (preg_match(Format::DM_INVALID->getRegex(), $coordsString)) {
			throw new ParserException('invalid coordinate value');
		}

		if (preg_match(Format::DM_NUMBERS->getRegex(), $coordsString, $matches, PREG_UNMATCHED_AS_NULL)) {
			$filteredMatches = array_filter($matches, static fn ($value) => $value !== null && $value !== '');
			$matchSuccess = $this->checkMatch($matches);

			if (!$matchSuccess) {
				throw new ParserException("invalid coordinates format");
			}

			$ddLat = abs((int) $filteredMatches[1]) + floatval($filteredMatches[2]) / 60;

			if (str_starts_with($filteredMatches[1], '-')) {
				$ddLat = -1 * $ddLat;
			}

			$ddLng = abs((int) $filteredMatches[3]) + floatval($filteredMatches[4]) / 60;

			if (str_starts_with($filteredMatches[3], '-')) {
				$ddLng = -1 * $ddLng;
			}
		} elseif (preg_match(Format::DD_RE->getRegex(), $coordsString, $matches, PREG_UNMATCHED_AS_NULL)) {
			$filteredMatches = array_filter($matches, static fn ($value) => $value !== null && $value !== '');
			$matchSuccess = $this->checkMatch($matches);
			preg_match(
				Format::DD_RE->getRegex(),
				$coordsString,
				$offsets,
				PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL,
			);

			//the longitude is a number of its own
			if ($this->isNumberSplit($offsets, 2, 6)) {
				throw new ParserException('invalid coordinates format');
			}

			if (!$matchSuccess) {
				throw new ParserException("invalid decimal coordinate format");
			}

			$ddLat = str_replace(',', '.', $filteredMatches[2]);
			$ddLng = str_replace(',', '.', $filteredMatches[6]);

			//validation, we don't want things like 23.00000

			//some more validation: no zero coords or degrees only
			if (is_numeric($ddLat) && (($ddLat <=> (int) $ddLat) === 0)) {
				throw new ParserException('integer only coordinate provided');
			}

			if (is_numeric($ddLng) && (($ddLng <=> (int) $ddLng) === 0)) {
				throw new ParserException('integer only coordinate provided');
			}

			//a coordinate has one direction, either before or after the number
			if (isset($filteredMatches[1], $filteredMatches[4])) {
				throw new ParserException('invalid coordinates format');
			}

			//get directions
			if (isset($filteredMatches[1])) {
				$latdir = $filteredMatches[1];
				$lngdir = $filteredMatches[5] ?? null;
			} elseif (isset($filteredMatches[4])) {
				$latdir = $filteredMatches[4];
				$lngdir = $filteredMatches[8] ?? null;
			}
		} elseif (preg_match(Format::DMS_PERIODS->getRegex(), $coordsString, $matches, PREG_UNMATCHED_AS_NULL)) {
			$filteredMatches = array_filter($matches, static fn ($value) => $value !== null && $value !== '');
			$matchSuccess = $this->checkMatch($matches);
			preg_match(
				Format::DMS_PERIODS->getRegex(),
				$coordsString,
				$offsets,
				PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL,
			);

			//the longitude is a number of its own
			if ($this->isNumberSplit($offsets, 6, 9)) {
				throw new ParserException('invalid coordinates format');
			}

			if (!$matchSuccess) {
				throw new ParserException("invalid DMS coordinates format");
			}

			$ddLat = abs((int) $filteredMatches[2]);

			if (isset($filteredMatches[4])) {
				$ddLat += $this->parseDecimal($filteredMatches[4]) / 60;
			}

			if (isset($filteredMatches[6])) {
				$ddLat += $this->parseDecimal($filteredMatches[6]) / 3600;
			}

			if (str_starts_with($filteredMatches[2], '-')) {
				$ddLat = -1 * $ddLat;
			}

			$ddLng = abs((int) $filteredMatches[9]);

			if (isset($filteredMatches[11])) {
				$ddLng += $this->parseDecimal($filteredMatches[11]) / 60;
			}

			if (isset($filteredMatches[13])) {
				$ddLng += $this->parseDecimal($filteredMatches[13]) / 3600;
			}

			if (str_starts_with($filteredMatches[9], '-')) {
				$ddLng = -1 * $ddLng;
			}

			//a coordinate has one direction, either before or after the number
			if (isset($filteredMatches[1], $filteredMatches[7])) {
				throw new ParserException('invalid coordinates format');
			}

			//the compass directions
			if (isset($filteredMatches[1])) {
				$latdir = $filteredMatches[1];
				$lngdir = $filteredMatches[8] ?? null;
			} elseif (isset($filteredMatches[7])) {
				$latdir = $filteredMatches[7];
				$lngdir = $filteredMatches[14] ?? null;
			}
		} elseif (preg_match(Format::DMS_ABBR->getRegex(), $coordsString, $matches, PREG_UNMATCHED_AS_NULL)) {
			$filteredMatches = array_filter($matches, static fn ($value) => $value !== null && $value !== '');
			$matchSuccess = $this->checkMatch($matches);
			preg_match(
				Format::DMS_ABBR->getRegex(),
				$coordsString,
				$offsets,
				PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL,
			);

			//the longitude is a number of its own
			if ($this->isNumberSplit($offsets, 6, 10)) {
				throw new ParserException('invalid coordinates format');
			}

			if (!$matchSuccess) {
				throw new ParserException("invalid DMS coordinates format");
			}

			$ddLat = abs((int) $filteredMatches[2]);

			if (isset($filteredMatches[4])) {
				$ddLat += $this->parseDecimal($filteredMatches[4]) / 60;
			}

			if (isset($filteredMatches[6])) {
				$ddLat += $this->parseDecimal($filteredMatches[6]) / 3600;
			}

			if (str_starts_with($filteredMatches[2], '-')) {
				$ddLat = -1 * $ddLat;
			}

			$ddLng = abs((int) $filteredMatches[10]);

			if (isset($filteredMatches[12])) {
				$ddLng += $this->parseDecimal($filteredMatches[12]) / 60;
			}

			if (isset($filteredMatches[14])) {
				$ddLng += $this->parseDecimal($filteredMatches[14]) / 3600;
			}

			if (str_starts_with($filteredMatches[10], '-')) {
				$ddLng = -1 * $ddLng;
			}

			//a coordinate has one direction, either before or after the number
			if (isset($filteredMatches[1], $filteredMatches[8])) {
				throw new ParserException('invalid coordinates format');
			}

			if (isset($filteredMatches[1])) {
				$latdir = $filteredMatches[1];
				$lngdir = $filteredMatches[9] ?? null;
			} elseif (isset($filteredMatches[8])) {
				$latdir = $filteredMatches[8];
				$lngdir = $filteredMatches[16] ?? null;
			}
		} elseif (preg_match(Format::OTHER->getRegex(), $coordsString, $matches, PREG_UNMATCHED_AS_NULL)) {
			$filteredMatches = array_filter($matches, static fn ($value) => $value !== null && $value !== '');
			$matchSuccess = $this->checkMatch($matches);
			preg_match(
				Format::OTHER->getRegex(),
				$coordsString,
				$offsets,
				PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL,
			);

			//the longitude is a number of its own
			foreach ([2, 4, 6] as $latitudeGroup) {
				if ($this->isNumberSplit($offsets, $latitudeGroup, 10)) {
					throw new ParserException('invalid coordinates format');
				}
			}

			//seconds follow minutes without a symbol only when minutes follow degrees the same way, such as "400722.8N"
			foreach ([[2, 4, 6], [10, 12, 14]] as [$degrees, $minutes, $seconds]) {
				$minutesSplit = $this->isNumberSplit($offsets, $degrees, $minutes);
				$secondsSplit = $this->isNumberSplit($offsets, $minutes, $seconds);

				if (isset($filteredMatches[$seconds]) && $minutesSplit !== $secondsSplit) {
					throw new ParserException('invalid coordinates format');
				}
			}

			//minutes follow degrees without a symbol either in both coordinates, such as "4007.38N7407.38W", or in none
			if ($this->isNumberSplit($offsets, 2, 4) !== $this->isNumberSplit($offsets, 10, 12)) {
				throw new ParserException('invalid coordinates format');
			}

			//degrees and minutes without any symbol or direction, such as "-254.4602", are parts of a longer number
			if (count($filteredMatches) <= 5 && !isset($filteredMatches[1]) && !isset($filteredMatches[8])) {
				throw new ParserException("invalid coordinates format");
			}

			if (!$matchSuccess) {
				throw new ParserException("invalid coordinates format");
			}

			$ddLat = abs((int) $filteredMatches[2]);

			if (isset($filteredMatches[4])) {
				$ddLat += $this->parseDecimal($filteredMatches[4]) / 60;
			}

			if (isset($filteredMatches[6])) {
				$ddLat += $this->parseDecimal($filteredMatches[6]) / 3600;
			}

			if (str_starts_with($filteredMatches[2], '-')) {
				$ddLat = -1 * $ddLat;
			}

			$ddLng = abs((int) $filteredMatches[10]);

			if (isset($filteredMatches[12])) {
				$ddLng += $this->parseDecimal($filteredMatches[12]) / 60;
			}

			if (isset($filteredMatches[14])) {
				$ddLng += $this->parseDecimal($filteredMatches[14]) / 3600;
			}

			if (str_starts_with($filteredMatches[10], '-')) {
				$ddLng = -1 * $ddLng;
			}

			//a coordinate has one direction, either before or after the number
			if (isset($filteredMatches[1], $filteredMatches[8])) {
				throw new ParserException('invalid coordinates format');
			}

			if (isset($filteredMatches[1])) {
				$latdir = $filteredMatches[1];
				$lngdir = $filteredMatches[9] ?? null;
			} elseif (isset($filteredMatches[8])) {
				$latdir = $filteredMatches[8];
				$lngdir = $filteredMatches[16] ?? null;
			}
		}

		if (!$matchSuccess) {
			throw new ParserException("coordinates pattern match failed");
		}

		//more validation....
		$ddLat = floatval($ddLat);

		$ddLng = floatval($ddLng);

		//check longitude value - it can be wrong!
		//the range is checked after rounding, so a result is never 90 or 180
		if (abs(round($ddLng, $decimalPlaces)) >= 180) {
			throw new ParserException("invalid longitude value");
		}

		//just to be safe check latitude also...
		if (abs(round($ddLat, $decimalPlaces)) >= 90) {
			throw new ParserException("invalid latitude value");
		}

		//if we have one direction we must have the other
		// checkMatch() rejects this already, kept as a safety net from the original library
		// @phpstan-ignore notIdentical.alwaysFalse, identical.alwaysFalse
		if (($latdir !== null || $lngdir !== null) && ($latdir === null || $lngdir === null)) {
			throw new ParserException("invalid coordinates format");
		}

		//the directions can't be the same
		if ($latdir !== null && $latdir === $lngdir) {
			throw new ParserException("invalid coordinates format");
		}

		//make sure the signs and cardinal directions match
		$patt = '/S|SOUTH/i';

		if (preg_match($patt, $latdir ?? '')) {
			if ($ddLat > 0) {
				$ddLat = -1 * floatval($ddLat);
			}
		}

		$patt = '/W|WEST/i';

		if (preg_match($patt, $lngdir ?? '')) {
			if ($ddLng > 0) {
				$ddLng = -1 * floatval($ddLng);
			}
		}

		//we need to get the verbatim coords from the string
		//we can't split down the middle because if there are decimals they may have different numbers on each side
		//so we need to find the separating character, or if none, use the match values to split down the middle
		$verbatimCoordinates = trim($filteredMatches[0] ?? '');

		$verbatimLat = null;
		$verbatimLng = null;

		//comma, forward slash and spacebar
		$sepChars = '#[,/; ]#';
		preg_match_all($sepChars, $verbatimCoordinates, $seps, PREG_SET_ORDER, 0);
		$seps = array_column($seps, 0);

		if (!$seps) {
			//split down the middle
			$middle = (int) floor(mb_strlen($coordsString) / 2);
			$verbatimLat = trim(mb_substr($verbatimCoordinates, 0, $middle));
			$verbatimLng = trim(mb_substr($verbatimCoordinates, $middle));
		} else {
			//if length is odd then find the index of the middle value
			//get the middle index

			//easy for odd numbers
			if (count($seps) % 2 === 1) {
				$middle = (int) floor(count($seps) / 2);
			} else {
				$middle = (count($seps) / 2) - 1;
			}

			//walk through seps until we get to the middle
			$splitIndex = 0;

			//it might be only one value
			if ($middle === 0) {
				$splitIndex = mb_strpos($verbatimCoordinates, $seps[0]);

				if ($splitIndex === false) {
					throw new ParserException('Invalid coordinates.');
				}

				$verbatimLat = trim(mb_substr($verbatimCoordinates, 0, $splitIndex));
				$verbatimLng = trim(mb_substr($verbatimCoordinates, $splitIndex + 1));
			} else {
				$currSepIndex = 0;
				$startSearchIndex = 0;
				while ($currSepIndex <= $middle) {
					$splitIndex = mb_strpos($verbatimCoordinates, $seps[$currSepIndex], $startSearchIndex);

					if ($splitIndex === false) {
						throw new ParserException('Invalid coordinates.');
					}

					$startSearchIndex = $splitIndex + 1;
					$currSepIndex++;
				}

				$verbatimLat = trim(mb_substr($verbatimCoordinates, 0, $splitIndex));
				$verbatimLng = trim(mb_substr($verbatimCoordinates, $splitIndex + 1));
			}
		}

		//validation again...

		//we only allow zeros after the period if its DM: "24.0" is whole degrees written as a decimal number,
		//zeros after minutes or seconds, such as 31.0", are their fraction
		foreach ([$verbatimLat, $verbatimLng] as $verbatimValue) {
			if (
				substr_count($verbatimValue, '.') === 1
				&& preg_match('/^\D*\d+\.(0+)(?!\d)/', $verbatimValue, $zeros)
				&& strlen($zeros[1]) !== 2
			) {
				throw new ParserException('invalid coordinates format');
			}
		}

		//no integer coords allowed
		//validation -- no integer coords
		if (preg_match('/^\d+$/', $verbatimLat) || preg_match('/^\d+$/', $verbatimLng)) {
			throw new ParserException('degree only coordinate/s provided');
		}

		//all done!!
		//round to the decimal places of the result, see resultDecimalPlaces()
		$places = $this->resultDecimalPlaces($ddLat, $ddLng, $decimalPlaces);

		return new Coordinates(
			$verbatimLat,
			$verbatimLng,
			number_format($ddLat, $places, '.', ''),
			number_format($ddLng, $places, '.', ''),
		);
	}

	private function checkDecimalPlaces(int $decimalPlaces): void
	{
		if ($decimalPlaces < 0 || $decimalPlaces > self::MAX_DECIMAL_PLACES) {
			throw new InvalidArgumentException(sprintf(
				'Decimal places must be 0 to %d, %d given.',
				self::MAX_DECIMAL_PLACES,
				$decimalPlaces,
			));
		}
	}

	/**
	 * Both coordinates get the same number of decimal places: the longer of the two with trailing zeros trimmed, and
	 * at least 3, as the parser reads one or two decimal places as minutes; two whole numbers stay whole
	 */
	private function resultDecimalPlaces(float $latitude, float $longitude, int $decimalPlaces): int
	{
		$places = max(
			$this->trimmedDecimalPlaces($latitude, $decimalPlaces),
			$this->trimmedDecimalPlaces($longitude, $decimalPlaces),
		);

		return match (true) {
			$places === 0 => 0,
			default => max($places, min(3, $decimalPlaces)),
		};
	}

	private function trimmedDecimalPlaces(float $value, int $decimalPlaces): int
	{
		$formatted = number_format($value, $decimalPlaces, '.', '');

		// the integer part is left alone, so only the part after the decimal point is trimmed
		return match (strpos($formatted, '.')) {
			false => 0,
			default => strlen(rtrim(substr($formatted, (int) strpos($formatted, '.') + 1), '0')),
		};
	}

	/**
	 * Tells whether one number was divided between two groups, such as "7492" read as 7 minutes, 49 seconds
	 * and 2 degrees
	 *
	 * @param array<array{string|null, int}> $offsets
	 */
	private function isNumberSplit(array $offsets, int $leftGroup, int $rightGroup): bool
	{
		[$left, $leftOffset] = $offsets[$leftGroup];
		[$right, $rightOffset] = $offsets[$rightGroup];

		return $left !== null
			&& $right !== null
			&& $leftOffset + strlen($left) === $rightOffset
			&& preg_match('/^\d/', $right) === 1;
	}

	private function parseDecimal(string $value): float
	{
		return floatval(str_replace(',', '.', $value));
	}

	/** @param array<string|null> $matches all groups at their positions, null for an unmatched group */
	private function checkMatch(array $matches): bool
	{
		//test if the matched groups arrays are 'balanced'. match is the resulting array
		//we've matched a number, not what we want....
		if (is_numeric($matches[0])) {
			return false;
		}

		//we need to shift the array because it contains the whole coordinates string in the first item
		array_shift($matches);

		//check the array length is an even number else exit
		if (count($matches) % 2 > 0) {
			return false;
		}

		//regex for testing corresponding values match
		//for testing numeric values
		$numerictest = '#^[-+]?\d+([\.,]\d+)?$#';
		//for testing string values (north, south, etc)
		$stringtest = '#[eastsouthnorthwest]+#iu';

		$halflen = intdiv(count($matches), 2);

		for ($i = 0; $i < $halflen; $i++) {
			$leftside = $matches[$i];
			$rightside = $matches[$i + $halflen];

			if ($leftside === null && $rightside === null) {
				continue;
			}

			if ($leftside === null || $rightside === null) {
				return false;
			}

			$bothAreNumbers = preg_match($numerictest, $leftside) && preg_match($numerictest, $rightside);
			$bothAreStrings = preg_match($stringtest, $leftside) && preg_match($stringtest, $rightside);

			$valuesAreEqual = $leftside === $rightside;

			if ($bothAreNumbers || $bothAreStrings || $valuesAreEqual) {
				continue;
			}

			return false;
		}

		return true;
	}
}
