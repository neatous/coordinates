<?php

declare(strict_types=1);

namespace Neatous\Coordinates\Formatter;

use Neatous\Coordinates\InvalidArgumentException;

/**
 * Writes coordinates in a form the parser reads back to the same values. The parser reads a decimal number with one
 * or two decimal places as degrees and minutes and rejects a whole number written with decimal places, so decimal
 * degrees get at least three decimal places, and whole degrees are written as degrees and minutes when the other
 * coordinate is not whole.
 */
final class Formatter
{
	private const int MIN_DECIMAL_DEGREES_PLACES = 3;

	// more decimal places show the error of the float, such as 55.03400000000001
	private const int MAX_DECIMAL_DEGREES_PLACES = 10;

	private const int DEFAULT_DECIMAL_DEGREES_PLACES = 5;

	// the parser reads at most three decimal places of seconds
	private const int MAX_SECONDS_PLACES = 3;

	private const int DEFAULT_SECONDS_PLACES = 2;

	private const float LATITUDE_LIMIT = 90.0;

	private const float LONGITUDE_LIMIT = 180.0;

	/**
	 * @param int $decimalPlaces default decimal places of the degrees, 3 to 10
	 * @param int $secondsDecimalPlaces default decimal places of the seconds in degrees, minutes and seconds, 0 to 3
	 */
	public function __construct(
		private readonly int $decimalPlaces = self::DEFAULT_DECIMAL_DEGREES_PLACES,
		private readonly int $secondsDecimalPlaces = self::DEFAULT_SECONDS_PLACES,
	) {
		$this->checkDecimalDegreesPlaces($decimalPlaces);
		$this->checkSecondsPlaces($secondsDecimalPlaces);
	}

	/**
	 * @param int|null $decimalPlaces decimal places of the degrees (3 to 10), or of the seconds in degrees, minutes
	 * and seconds (0 to 3), null for the default of the constructor
	 */
	public function format(
		float $latitude,
		float $longitude,
		Notation $notation = Notation::HemisphereDecimal,
		?int $decimalPlaces = null,
	): string {
		return match ($notation) {
			Notation::HemisphereDecimal => $this->formatDecimal(
				$latitude,
				$longitude,
				false,
				$decimalPlaces ?? $this->decimalPlaces,
			),
			Notation::SignedDecimal => $this->formatDecimal(
				$latitude,
				$longitude,
				true,
				$decimalPlaces ?? $this->decimalPlaces,
			),
			Notation::DegreesMinutesSeconds => $this->formatDegreesMinutesSeconds(
				$latitude,
				$longitude,
				$decimalPlaces ?? $this->secondsDecimalPlaces,
			),
		};
	}

	private function formatDecimal(float $latitude, float $longitude, bool $signed, int $decimalPlaces): string
	{
		$this->checkDecimalDegreesPlaces($decimalPlaces);

		$latitude = $this->roundDegrees($latitude, $decimalPlaces, self::LATITUDE_LIMIT, 'latitude');
		$longitude = $this->roundDegrees($longitude, $decimalPlaces, self::LONGITUDE_LIMIT, 'longitude');
		$latitudeWhole = floor($latitude) === $latitude;
		$longitudeWhole = floor($longitude) === $longitude;

		return match (true) {
			!$latitudeWhole && !$longitudeWhole => sprintf(
				'%s, %s',
				$this->formatCoordinate($latitude, $signed, 'N', 'S', $this->formatDegrees(...), $decimalPlaces),
				$this->formatCoordinate($longitude, $signed, 'E', 'W', $this->formatDegrees(...), $decimalPlaces),
			),
			// the parser reads whole degrees without decimal places only with the hemisphere letters
			$latitudeWhole && $longitudeWhole && !$signed => sprintf(
				'%s %d, %s %d',
				$this->getHemisphere($latitude, 'N', 'S'),
				abs($latitude),
				$this->getHemisphere($longitude, 'E', 'W'),
				abs($longitude),
			),
			// minutes with one decimal place less are exact, a degree has 60 of them
			default => sprintf(
				'%s, %s',
				$this->formatCoordinate(
					$latitude,
					$signed,
					'N',
					'S',
					$this->formatDegreesMinutes(...),
					$decimalPlaces - 1,
				),
				$this->formatCoordinate(
					$longitude,
					$signed,
					'E',
					'W',
					$this->formatDegreesMinutes(...),
					$decimalPlaces - 1,
				),
			),
		};
	}

	private function formatDegreesMinutesSeconds(float $latitude, float $longitude, int $decimalPlaces): string
	{
		$this->checkSecondsPlaces($decimalPlaces);

		return sprintf(
			'%s, %s',
			$this->formatSeconds($latitude, $decimalPlaces, self::LATITUDE_LIMIT, 'latitude', 'N', 'S'),
			$this->formatSeconds($longitude, $decimalPlaces, self::LONGITUDE_LIMIT, 'longitude', 'E', 'W'),
		);
	}

	private function checkDecimalDegreesPlaces(int $decimalPlaces): void
	{
		if ($decimalPlaces < self::MIN_DECIMAL_DEGREES_PLACES || $decimalPlaces > self::MAX_DECIMAL_DEGREES_PLACES) {
			throw new InvalidArgumentException(sprintf(
				'Decimal degrees need %d to %d decimal places, %d given.',
				self::MIN_DECIMAL_DEGREES_PLACES,
				self::MAX_DECIMAL_DEGREES_PLACES,
				$decimalPlaces,
			));
		}
	}

	private function checkSecondsPlaces(int $decimalPlaces): void
	{
		if ($decimalPlaces < 0 || $decimalPlaces > self::MAX_SECONDS_PLACES) {
			throw new InvalidArgumentException(sprintf(
				'Seconds need 0 to %d decimal places, %d given.',
				self::MAX_SECONDS_PLACES,
				$decimalPlaces,
			));
		}
	}

	/** @param callable(float, int): string $formatAbsolute */
	private function formatCoordinate(
		float $degrees,
		bool $signed,
		string $positiveHemisphere,
		string $negativeHemisphere,
		callable $formatAbsolute,
		int $decimalPlaces,
	): string {
		$absolute = $formatAbsolute(abs($degrees), $decimalPlaces);

		return match (true) {
			$signed && $degrees < 0 => '-' . $absolute,
			$signed => $absolute,
			default => $this->getHemisphere($degrees, $positiveHemisphere, $negativeHemisphere) . ' ' . $absolute,
		};
	}

	private function formatDegrees(float $degrees, int $decimalPlaces): string
	{
		return number_format($degrees, $decimalPlaces, '.', '');
	}

	private function formatDegreesMinutes(float $degrees, int $decimalPlaces): string
	{
		$wholeDegrees = floor($degrees);

		return sprintf(
			"%d° %s'",
			$wholeDegrees,
			number_format(($degrees - $wholeDegrees) * 60, $decimalPlaces, '.', ''),
		);
	}

	/**
	 * Rounds the seconds in whole units of the last decimal place, so 59.995 seconds carry over into the minutes
	 * and the minutes into the degrees
	 */
	private function formatSeconds(
		float $degrees,
		int $decimalPlaces,
		float $limit,
		string $name,
		string $positiveHemisphere,
		string $negativeHemisphere,
	): string {
		$this->checkFinite($degrees, $name);
		$unitsPerSecond = 10 ** $decimalPlaces;
		$units = round(abs($degrees) * 3600 * $unitsPerSecond);
		$this->checkLimit($units / 3600 / $unitsPerSecond, $degrees, $limit, $name);
		$units = (int) $units;

		return sprintf(
			"%s %d° %d' %s\"",
			$this->getHemisphere($units === 0 ? 0.0 : $degrees, $positiveHemisphere, $negativeHemisphere),
			intdiv($units, 3600 * $unitsPerSecond),
			intdiv($units, 60 * $unitsPerSecond) % 60,
			number_format($units % (60 * $unitsPerSecond) / $unitsPerSecond, $decimalPlaces, '.', ''),
		);
	}

	private function roundDegrees(float $degrees, int $decimalPlaces, float $limit, string $name): float
	{
		$this->checkFinite($degrees, $name);
		$rounded = round($degrees, $decimalPlaces);
		$this->checkLimit($rounded, $degrees, $limit, $name);

		return $rounded;
	}

	private function checkFinite(float $degrees, string $name): void
	{
		if (!is_finite($degrees)) {
			throw new FormatterException(sprintf('The %s is not a finite number.', $name));
		}
	}

	private function checkLimit(float $rounded, float $degrees, float $limit, string $name): void
	{
		if (abs($rounded) >= $limit) {
			throw new FormatterException(sprintf(
				'The %s must be greater than -%d and less than %d after rounding, %s given.',
				$name,
				$limit,
				$limit,
				$degrees,
			));
		}
	}

	private function getHemisphere(float $degrees, string $positiveHemisphere, string $negativeHemisphere): string
	{
		return $degrees < 0 ? $negativeHemisphere : $positiveHemisphere;
	}
}
