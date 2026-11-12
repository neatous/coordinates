<?php

declare(strict_types=1);

namespace Neatous\Coordinates\Tests\Formatter;

use Neatous\Coordinates\Formatter\Formatter;
use Neatous\Coordinates\Formatter\FormatterException;
use Neatous\Coordinates\Formatter\Notation;
use Neatous\Coordinates\InvalidArgumentException;
use Neatous\Coordinates\Parser\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FormatterTest extends TestCase
{
	private const int RANDOM_SEED = 20261112;

	private const int RANDOM_COUNT = 3000;

	#[DataProvider('getDataForFormat')]
	public function testFormat(
		float $latitude,
		float $longitude,
		Notation $notation,
		int $decimalPlaces,
		string $expected,
	): void {
		$this->assertSame($expected, (new Formatter())->format($latitude, $longitude, $notation, $decimalPlaces));
	}

	/** @return mixed[][] */
	public static function getDataForFormat(): array
	{
		$hd = Notation::HemisphereDecimal;
		$sd = Notation::SignedDecimal;
		$dms = Notation::DegreesMinutesSeconds;

		return [
			'hemisphere, two decimal places' => [50.08, 14.4, $hd, 5, 'N 50.08000, E 14.40000'],
			'hemisphere, five decimal places' => [50.08765, 14.42123, $hd, 5, 'N 50.08765, E 14.42123'],
			'hemisphere, more decimal places are rounded' => [50.087654, 14.421236, $hd, 5, 'N 50.08765, E 14.42124'],
			'hemisphere, southern and western' => [-33.45, -70.66667, $hd, 5, 'S 33.45000, W 70.66667'],
			'hemisphere, three decimal places' => [50.08765, 14.42123, $hd, 3, 'N 50.088, E 14.421'],
			'hemisphere, seven decimal places' => [50.0876543, 14.4212345, $hd, 7, 'N 50.0876543, E 14.4212345'],
			'hemisphere, ten decimal places' => [55.034, 14.4, $hd, 10, 'N 55.0340000000, E 14.4000000000'],
			'hemisphere, ten decimal places, rounded' => [
				50.08765432109,
				-14.42123456789,
				$hd,
				10,
				'N 50.0876543211, W 14.4212345679',
			],
			'hemisphere, whole latitude, ten decimal places' => [
				50.0,
				14.4,
				$hd,
				10,
				'N 50° 0.000000000\', E 14° 24.000000000\'',
			],
			'hemisphere, whole degrees' => [50.0, 14.0, $hd, 5, 'N 50, E 14'],
			'hemisphere, whole southern and western degrees' => [-50.0, -14.0, $hd, 5, 'S 50, W 14'],
			'hemisphere, whole latitude only' => [50.0, 14.4, $hd, 5, 'N 50° 0.0000\', E 14° 24.0000\''],
			'hemisphere, whole longitude only' => [-50.5, -14.0, $hd, 5, 'S 50° 30.0000\', W 14° 0.0000\''],
			'hemisphere, whole latitude, three decimal places' => [50.0, 14.421, $hd, 3, 'N 50° 0.00\', E 14° 25.26\''],
			'hemisphere, whole after rounding' => [49.999999, 13.999999, $hd, 5, 'N 50, E 14'],
			'hemisphere, one whole after rounding' => [49.999999, 14.4, $hd, 5, 'N 50° 0.0000\', E 14° 24.0000\''],
			'hemisphere, minus zero after rounding' => [-0.000001, 14.4, $hd, 5, 'N 0° 0.0000\', E 14° 24.0000\''],
			'hemisphere, minus zeros after rounding' => [-0.000001, -0.000001, $hd, 5, 'N 0, E 0'],
			'hemisphere, minus zero' => [-0.0, -0.0, $hd, 5, 'N 0, E 0'],
			'hemisphere, below one degree' => [-0.5, -0.00001, $hd, 5, 'S 0.50000, W 0.00001'],
			'hemisphere, close to the limits' => [-89.999994, 179.999994, $hd, 5, 'S 89.99999, E 179.99999'],
			'signed, five decimal places' => [50.08765, 14.42123, $sd, 5, '50.08765, 14.42123'],
			'signed, southern and western' => [-33.45, -70.66667, $sd, 5, '-33.45000, -70.66667'],
			'signed, four decimal places' => [50.08765, -14.42123, $sd, 4, '50.0877, -14.4212'],
			'signed, ten decimal places' => [-55.034, 14.4, $sd, 10, '-55.0340000000, 14.4000000000'],
			'signed, whole degrees' => [50.0, -14.0, $sd, 5, '50° 0.0000\', -14° 0.0000\''],
			'signed, whole latitude only' => [-50.0, 14.4, $sd, 5, '-50° 0.0000\', 14° 24.0000\''],
			'signed, whole longitude below one degree' => [-0.5, 14.0, $sd, 5, '-0° 30.0000\', 14° 0.0000\''],
			'signed, minus zero after rounding' => [-0.000001, -0.5, $sd, 5, '0° 0.0000\', -0° 30.0000\''],
			'signed, below one degree' => [-0.5, -0.00001, $sd, 5, '-0.50000, -0.00001'],
			'signed, close to the limits' => [89.999994, -179.999994, $sd, 5, '89.99999, -179.99999'],
			'seconds, two decimal places' => [50.08765, 14.42123, $dms, 2, 'N 50° 5\' 15.54", E 14° 25\' 16.43"'],
			'seconds, southern and western' => [-33.45, -70.66667, $dms, 2, 'S 33° 27\' 0.00", W 70° 40\' 0.01"'],
			'seconds, no decimal places' => [50.08765, 14.42123, $dms, 0, 'N 50° 5\' 16", E 14° 25\' 16"'],
			'seconds, three decimal places' => [50.08765, 14.42123, $dms, 3, 'N 50° 5\' 15.540", E 14° 25\' 16.428"'],
			'seconds, whole degrees' => [50.0, 14.0, $dms, 2, 'N 50° 0\' 0.00", E 14° 0\' 0.00"'],
			'seconds carry over into minutes' => [50.0833332, 14.0, $dms, 2, 'N 50° 5\' 0.00", E 14° 0\' 0.00"'],
			'seconds carry over into degrees' => [50.9999999, -14.9999999, $dms, 2, 'N 51° 0\' 0.00", W 15° 0\' 0.00"'],
			'seconds, minus zero after rounding' => [-0.000001, -0.000001, $dms, 2, 'N 0° 0\' 0.00", E 0° 0\' 0.00"'],
			'seconds, below one degree' => [-0.5, -0.00001, $dms, 2, 'S 0° 30\' 0.00", W 0° 0\' 0.04"'],
			'seconds, near the limits' => [89.999997, -179.999997, $dms, 2, 'N 89° 59\' 59.99", W 179° 59\' 59.99"'],
		];
	}

	public function testFormatDefaults(): void
	{
		$formatter = new Formatter();

		$this->assertSame('N 50.08765, E 14.42123', $formatter->format(50.087654, 14.421234));
		$this->assertSame(
			'N 50.08765, E 14.42123',
			$formatter->format(50.087654, 14.421234, Notation::HemisphereDecimal),
		);
		$this->assertSame('50.08765, -14.42123', $formatter->format(50.087654, -14.421234, Notation::SignedDecimal));
		$this->assertSame(
			'N 50° 5\' 15.55", E 14° 25\' 16.44"',
			$formatter->format(50.087654, 14.421234, Notation::DegreesMinutesSeconds),
		);
	}

	#[DataProvider('getDataForParserReadsFormattedDecimalDegrees')]
	public function testParserReadsFormattedDecimalDegrees(
		float $latitude,
		float $longitude,
		Notation $notation,
		int $decimalPlaces,
	): void {
		$this->assertParserReadsDecimalDegrees($latitude, $longitude, $notation, $decimalPlaces);
	}

	/** @return mixed[][] */
	public static function getDataForParserReadsFormattedDecimalDegrees(): array
	{
		$data = [];

		foreach ([Notation::HemisphereDecimal, Notation::SignedDecimal] as $notation) {
			foreach ([3, 5, 8, 10] as $decimalPlaces) {
				foreach (self::getEdgeCoordinates() as $name => [$latitude, $longitude]) {
					$data[sprintf('%s, %d places, %s', $notation->name, $decimalPlaces, $name)] = [
						$latitude,
						$longitude,
						$notation,
						$decimalPlaces,
					];
				}
			}
		}

		return $data;
	}

	#[DataProvider('getDataForParserReadsFormattedSeconds')]
	public function testParserReadsFormattedSeconds(float $latitude, float $longitude, int $decimalPlaces): void
	{
		$this->assertParserReadsSeconds($latitude, $longitude, $decimalPlaces);
	}

	/** @return mixed[][] */
	public static function getDataForParserReadsFormattedSeconds(): array
	{
		$data = [];

		foreach ([0, 1, 2, 3] as $decimalPlaces) {
			foreach (self::getEdgeCoordinates() as $name => [$latitude, $longitude]) {
				$data[sprintf('%d places, %s', $decimalPlaces, $name)] = [$latitude, $longitude, $decimalPlaces];
			}
		}

		return $data;
	}

	public function testParserReadsRandomFormattedCoordinates(): void
	{
		mt_srand(self::RANDOM_SEED);

		for ($i = 0; $i < self::RANDOM_COUNT; $i++) {
			$latitude = $this->getRandomDegrees(90);
			$longitude = $this->getRandomDegrees(180);

			$this->assertParserReadsDecimalDegrees($latitude, $longitude, Notation::HemisphereDecimal, mt_rand(3, 10));
			$this->assertParserReadsDecimalDegrees($latitude, $longitude, Notation::SignedDecimal, mt_rand(3, 10));
			$this->assertParserReadsSeconds($latitude, $longitude, mt_rand(0, 3));
		}
	}

	#[DataProvider('getDataForFormatRejectsInvalidInput')]
	public function testFormatRejectsInvalidInput(
		float $latitude,
		float $longitude,
		Notation $notation,
		?int $decimalPlaces,
	): void {
		$this->expectException(FormatterException::class);
		(new Formatter())->format($latitude, $longitude, $notation, $decimalPlaces);
	}

	/** @return mixed[][] */
	public static function getDataForFormatRejectsInvalidInput(): array
	{
		$hemisphere = Notation::HemisphereDecimal;
		$signed = Notation::SignedDecimal;
		$seconds = Notation::DegreesMinutesSeconds;

		return [
			'hemisphere, latitude 90' => [90.0, 14.0, $hemisphere, 5],
			'hemisphere, latitude -90' => [-90.0, 14.0, $hemisphere, 5],
			'hemisphere, latitude above 90' => [91.5, 14.4, $hemisphere, 5],
			'hemisphere, longitude 180' => [50.0, 180.0, $hemisphere, 5],
			'hemisphere, longitude above 180' => [50.5, 200.5, $hemisphere, 5],
			'hemisphere, latitude 90 after rounding' => [89.999995, 14.4, $hemisphere, 5],
			'signed, longitude -180 after rounding' => [50.5, -179.9999995, $signed, 6],
			'seconds, latitude 90 after rounding' => [89.9999999, 14.4, $seconds, 2],
			'seconds, longitude 180 after rounding' => [50.5, 179.9999999, $seconds, 2],
			'hemisphere, huge latitude' => [1e30, 14.4, $hemisphere, 5],
			'seconds, huge longitude' => [50.5, -1e30, $seconds, 2],
			'hemisphere, latitude not a number' => [NAN, 14.4, $hemisphere, 5],
			'signed, infinite longitude' => [50.5, INF, $signed, 5],
			'seconds, infinite latitude' => [-INF, 14.4, $seconds, 2],
			'seconds, latitude not a number' => [NAN, 14.4, $seconds, 2],
			'seconds, longitude not a number' => [50.5, NAN, $seconds, 2],
			'seconds, infinite longitude' => [50.5, INF, $seconds, 0],
			'seconds by default, latitude not a number' => [NAN, 14.4, $seconds, null],
		];
	}

	public function testFormatRejectsOutOfRangeWithTheGivenValue(): void
	{
		$this->expectException(FormatterException::class);
		$this->expectExceptionMessage(
			'The latitude must be greater than -90 and less than 90 after rounding, 89.999995 given.',
		);
		(new Formatter())->format(89.999995, 14.4);
	}

	#[DataProvider('getDataForFormatRejectsDecimalPlaces')]
	public function testFormatRejectsDecimalPlaces(Notation $notation, int $decimalPlaces): void
	{
		try {
			(new Formatter())->format(50.08765, 14.42123, $notation, $decimalPlaces);
			$this->fail('The formatter accepted the decimal places.');
		} catch (InvalidArgumentException $exception) {
			// a wrong argument of the calling code, not coordinates out of range
			$this->assertSame(InvalidArgumentException::class, $exception::class);
		}
	}

	/** @return array<string, array{Notation, int}> */
	public static function getDataForFormatRejectsDecimalPlaces(): array
	{
		return [
			'hemisphere, two decimal places' => [Notation::HemisphereDecimal, 2],
			'hemisphere, eleven decimal places' => [Notation::HemisphereDecimal, 11],
			'signed, no decimal places' => [Notation::SignedDecimal, 0],
			'signed, negative decimal places' => [Notation::SignedDecimal, -1],
			'signed, eleven decimal places' => [Notation::SignedDecimal, 11],
			'signed, fourteen decimal places' => [Notation::SignedDecimal, 14],
			'seconds, negative decimal places' => [Notation::DegreesMinutesSeconds, -1],
			'seconds, four decimal places' => [Notation::DegreesMinutesSeconds, 4],
		];
	}

	public function testExceptionsImplementTheLibraryInterface(): void
	{
		$this->assertInstanceOf(\Neatous\Coordinates\Exception::class, new FormatterException());
		$this->assertInstanceOf(\RuntimeException::class, new FormatterException());
		$this->assertNotInstanceOf(\InvalidArgumentException::class, new FormatterException());
		$this->assertInstanceOf(\Neatous\Coordinates\Exception::class, new InvalidArgumentException());
		$this->assertInstanceOf(\InvalidArgumentException::class, new InvalidArgumentException());
	}

	/** @return float[][] */
	private static function getEdgeCoordinates(): array
	{
		return [
			'decimal degrees' => [50.08765, 14.42123],
			'southern and western hemisphere' => [-33.45, -70.66667],
			'one decimal place' => [50.1, 14.4],
			'whole degrees' => [50.0, 14.0],
			'whole southern and western degrees' => [-50.0, -14.0],
			'whole latitude only' => [50.0, -14.4],
			'whole longitude only' => [-50.4, 14.0],
			'zero' => [0.0, 0.0],
			'zero latitude only' => [0.0, 14.4],
			'just below whole degrees' => [49.9999999, -13.9999999],
			'just above whole degrees' => [50.0000001, -14.0000001],
			'just below one whole degree' => [49.9999999, 14.4],
			'minus zero after rounding' => [-0.0000001, -0.0000001],
			'one minus zero after rounding' => [-0.0000001, -14.4],
			'below one degree' => [-0.5, -0.25],
			'close to the limits' => [89.9994, -179.9994],
			'close to the negative limits' => [-89.9994, 179.9994],
		];
	}

	private function getRandomDegrees(int $limit): float
	{
		$degrees = mt_rand(1 - $limit, $limit - 1);

		// whole degrees, numbers close to them and decimal degrees of various precision
		return match (mt_rand(0, 3)) {
			0 => (float) $degrees,
			1 => $degrees + mt_rand(-9, 9) / 10 ** mt_rand(6, 9),
			default => round($degrees + mt_rand(-999999999, 999999999) / 1e9, mt_rand(1, 9)),
		};
	}

	private function assertParserReadsDecimalDegrees(
		float $latitude,
		float $longitude,
		Notation $notation,
		int $decimalPlaces,
	): void {
		$formatted = (new Formatter())->format($latitude, $longitude, $notation, $decimalPlaces);
		$coordinates = (new Parser())->parse($formatted, $decimalPlaces);
		$message = sprintf('%.10F, %.10F formatted as %s', $latitude, $longitude, $formatted);

		$this->assertSame(round($latitude, $decimalPlaces), (float) $coordinates->getDecimalLatitude(), $message);
		$this->assertSame(round($longitude, $decimalPlaces), (float) $coordinates->getDecimalLongitude(), $message);
	}

	private function assertParserReadsSeconds(float $latitude, float $longitude, int $decimalPlaces): void
	{
		$formatted = (new Formatter())->format($latitude, $longitude, Notation::DegreesMinutesSeconds, $decimalPlaces);
		$coordinates = (new Parser())->parse($formatted, 9);
		$message = sprintf('%.10F, %.10F formatted as %s', $latitude, $longitude, $formatted);
		// half of the last decimal place of the seconds, the parser rounds to nine decimal places of degrees
		$delta = 0.5 / 10 ** $decimalPlaces / 3600 + 1e-9;

		$this->assertEqualsWithDelta($latitude, (float) $coordinates->getDecimalLatitude(), $delta, $message);
		$this->assertEqualsWithDelta($longitude, (float) $coordinates->getDecimalLongitude(), $delta, $message);
	}
}
