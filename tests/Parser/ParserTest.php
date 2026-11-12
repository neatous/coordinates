<?php

declare(strict_types=1);

namespace Neatous\Coordinates\Tests\Parser;

use Neatous\Coordinates\InvalidArgumentException;
use Neatous\Coordinates\Parser\Parser;
use Neatous\Coordinates\Parser\ParserException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
	#[DataProvider('getDataForParse')]
	public function testParse(
		string $verbatimCoordinates,
		string $verbatimLatitude,
		string $verbatimLongitude,
		float $decimalLatitude,
		float $decimalLongitude,
	): void {
		$parser = new Parser();
		$result = $parser->parse($verbatimCoordinates, 7);
		$this->assertSame($verbatimLatitude, $result->getVerbatimLatitude());
		$this->assertSame($verbatimLongitude, $result->getVerbatimLongitude());
		// the number of decimal places is covered by testParseResultDecimalPlaces, here only the value is compared
		$this->assertSame($decimalLatitude, (float) $result->getDecimalLatitude());
		$this->assertSame($decimalLongitude, (float) $result->getDecimalLongitude());
	}

	#[DataProvider('getDataForParseInvalid')]
	public function testParseRejectsInvalidCoordinates(string $verbatimCoordinates): void
	{
		$parser = new Parser();
		$this->expectException(ParserException::class);
		$parser->parse($verbatimCoordinates, 7);
	}

	#[DataProvider('getDataForParseWithMissingDirection')]
	public function testParseRejectsMissingDirection(string $validCoordinates, string $invalidCoordinates): void
	{
		$parser = new Parser();
		$parser->parse($validCoordinates);
		$this->expectException(ParserException::class);
		$parser->parse($invalidCoordinates);
	}

	#[DataProvider('getDataForParseWithDecimalPlaces')]
	public function testParseRoundsToDecimalPlaces(
		string $verbatimCoordinates,
		int $decimalPlaces,
		string $decimalLatitude,
		string $decimalLongitude,
	): void {
		$result = (new Parser())->parse($verbatimCoordinates, $decimalPlaces);
		$this->assertSame($decimalLatitude, $result->getDecimalLatitude());
		$this->assertSame($decimalLongitude, $result->getDecimalLongitude());
	}

	/** @return array<string, array{string, int, string, string}> */
	public static function getDataForParseWithDecimalPlaces(): array
	{
		return [
			'no decimal places' => ['50.123, 10.123', 0, '50', '10'],
			'no decimal places, rounded up' => ['49.987, -109.500', 0, '50', '-110'],
			'no decimal places, below one degree' => ['-0.5 -0.5', 0, '0', '0'],
			'no decimal places, whole degrees' => ['N 50, E 100', 0, '50', '100'],
			'one decimal place, zero kept' => ['50.040, 10.123', 1, '50.0', '10.1'],
			'trailing zeros trimmed, padded to three' => ['50.10000, 10.12000', 5, '50.100', '10.120'],
			'whole degrees keep their zeros' => ['N 50, E 100', 5, '50', '100'],
			'ten decimal places' => ['N 50, E 100', 10, '50', '100'],
			'ten decimal places below one degree' => ['-0.125, 0.375', 10, '-0.125', '0.375'],
			'ten decimal places, no float error' => ['50.08765, -73.98765', 10, '50.08765', '-73.98765'],
			'ten decimal places, longitude with three digits' => ['50.08765, 179.12345', 10, '50.08765', '179.12345'],
			'ten decimal places, negative longitude with three digits' => [
				'-50.08765, -179.12345',
				10,
				'-50.08765',
				'-179.12345',
			],
		];
	}

	#[DataProvider('getDataForParseResultDecimalPlaces')]
	public function testParseResultDecimalPlaces(
		string $verbatimCoordinates,
		?int $decimalPlaces,
		string $decimalLatitude,
		string $decimalLongitude,
	): void {
		$parser = new Parser();
		$result = $decimalPlaces === null
			? $parser->parse($verbatimCoordinates)
			: $parser->parse($verbatimCoordinates, $decimalPlaces);
		$this->assertSame($decimalLatitude, $result->getDecimalLatitude());
		$this->assertSame($decimalLongitude, $result->getDecimalLongitude());
	}

	/**
	 * Both coordinates get the longer of the two decimal parts with trailing zeros trimmed, at least 3 places
	 * (the parser reads 1 or 2 places as minutes) and at most the given number; whole numbers stay whole
	 *
	 * @return array<string, array{string, ?int, string, string}>
	 */
	public static function getDataForParseResultDecimalPlaces(): array
	{
		return [
			'shorter latitude is padded' => ['49.930, 14.881307', null, '49.930000', '14.881307'],
			'longer latitude pads the longitude' => ['14.881307, 49.930', null, '14.881307', '49.930000'],
			'zeros of the input are trimmed first' => ['49.936700, 14.88130700000', null, '49.936700', '14.881307'],
			'three places each stay as they are' => ['50.123, 10.123', null, '50.123', '10.123'],
			'longitude with minimum of three places' => ['50.123, 10.100', null, '50.123', '10.100'],
			'both trimmed below three are padded to three' => ['50.12000, 10.50000', null, '50.120', '10.500'],
			'degrees and minutes, repeating decimals' => ['50.1, 14.42', null, '50.0166666667', '14.7000000000'],
			'google maps precision is rounded to ten' => [
				'49.936752169181595, 14.88130750552173',
				null,
				'49.9367521692',
				'14.8813075055',
			],
			'coordinate rounded to whole is padded' => ['50.00000000001, 14.881307', null, '50.000000', '14.881307'],
			'two whole coordinates stay whole' => ['48N, 14E', null, '48', '14'],
			'one coordinate rounded to whole is padded' => ['50.0000001, 14.123', 3, '50.000', '14.123'],
			'two decimal places' => ['50.040, 10.123', 2, '50.04', '10.12'],
			'three decimal places' => ['50.0400, 10.1234', 3, '50.040', '10.123'],
			'five decimal places pad to the longer' => ['50.040, 10.1234567', 5, '50.04000', '10.12346'],
			'ten decimal places, no padding to ten' => ['50.040, 10.1234567', 10, '50.0400000', '10.1234567'],
			'negative coordinates' => ['-50.930, -14.881307', null, '-50.930000', '-14.881307'],
			'negative longitude pads' => ['50.1234567, -14.120', null, '50.1234567', '-14.1200000'],
			'negative coordinate rounded to whole is padded' => [
				'-50.00000000001, -14.881307',
				null,
				'-50.000000',
				'-14.881307',
			],
			'minus zero loses its sign in the result' => ['-0.0001, 10.123', 3, '0.000', '10.123'],
			'rounded to one place, trailing zero kept' => ['50.500, 10.500', 2, '50.50', '10.50'],
			'both rounded to one place' => ['50.040, 10.040', 1, '50', '10'],
			'both rounded below three places' => ['50.0000001, 14.0000001', 3, '50', '14'],
			'zero before the decimal point is not trimmed' => ['0.0001, 10.123', 3, '0.000', '10.123'],
			'just below the latitude limit' => ['89.9999999999, 14.500', null, '89.9999999999', '14.5000000000'],
			'read back, padded latitude' => ['49.930000, 14.881307', null, '49.930000', '14.881307'],
			'read back, repeating decimals' => ['50.0166666667, 14.7000000000', null, '50.0166666667', '14.7000000000'],
			'degrees, minutes and seconds' => ['-0°30′15″, 14°30′0″', 7, '-0.5041667', '14.5000000'],
		];
	}

	#[DataProvider('getDataForParseRejectsDecimalPlaces')]
	public function testParseRejectsDecimalPlaces(int $decimalPlaces): void
	{
		try {
			(new Parser())->parse('50.123, 10.123', $decimalPlaces);
			$this->fail('The parser accepted the decimal places.');
		} catch (InvalidArgumentException $exception) {
			$this->assertInstanceOf(\InvalidArgumentException::class, $exception);
			$this->assertSame(
				sprintf('Decimal places must be 0 to 10, %d given.', $decimalPlaces),
				$exception->getMessage(),
			);
		}
	}

	/** @return array<string, array{int}> */
	public static function getDataForParseRejectsDecimalPlaces(): array
	{
		return [
			'negative' => [-1],
			'eleven' => [11],
			'huge' => [1000000000],
		];
	}

	#[DataProvider('getDataForParseRejectsRangeAfterRounding')]
	public function testParseRejectsRangeAfterRounding(string $input, string $message): void
	{
		$this->expectException(ParserException::class);
		$this->expectExceptionMessage($message);
		(new Parser())->parse($input, 5);
	}

	/** @return array<string, array{string, string}> */
	public static function getDataForParseRejectsRangeAfterRounding(): array
	{
		return [
			'latitude rounded up to 90' => ['89.999999, 14.500000', 'invalid latitude value'],
			'latitude rounded up to 90 with directions' => ['89.999999 N, 14.500000 E', 'invalid latitude value'],
			'longitude rounded up to 180' => ['50.500000, 179.999999', 'invalid longitude value'],
			// the longitude is checked first
			'negative latitude and longitude' => ['-89.999999, -179.999999', 'invalid longitude value'],
		];
	}

	public function testParseRejectsLatitudeRoundedUpByDefaultPlaces(): void
	{
		$this->expectException(ParserException::class);
		$this->expectExceptionMessage('invalid latitude value');
		(new Parser())->parse('89.99999999999, 14.500');
	}

	public function testParseRejectsPaddedWholeCoordinate(): void
	{
		$this->expectException(ParserException::class);
		$this->expectExceptionMessage('integer only coordinate provided');
		(new Parser())->parse('50.000000, 14.881307');
	}

	public function testParseKeepsRangeBelowRounding(): void
	{
		$result = (new Parser())->parse('89.999999, 14.500000', 6);
		$this->assertSame('89.999999', $result->getDecimalLatitude());
	}

	public function testParserExceptionImplementsTheLibraryInterface(): void
	{
		try {
			(new Parser())->parse('not coordinates');
			$this->fail('The parser accepted the input.');
		} catch (\Neatous\Coordinates\Exception $exception) {
			$this->assertInstanceOf(ParserException::class, $exception);
		}
	}

	/** @return mixed[][] */
	public static function getDataForParse(): array
	{
		return [
			[
				'verbatimCoordinates' => '40.123, -74.123',
				'verbatimLatitude' => '40.123',
				'verbatimLongitude' => '-74.123',
				'decimalLatitude' => 40.123,
				'decimalLongitude' => -74.123,
			],
			[
				'verbatimCoordinates' => '40.123° N 74.123° W',
				'verbatimLatitude' => '40.123° N',
				'verbatimLongitude' => '74.123° W',
				'decimalLatitude' => 40.123,
				'decimalLongitude' => -74.123,
			],
			[
				'verbatimCoordinates' => '40.123° N 74.123° W',
				'verbatimLatitude' => '40.123° N',
				'verbatimLongitude' => '74.123° W',
				'decimalLatitude' => 40.123,
				'decimalLongitude' => -74.123,
			],
			[
				'verbatimCoordinates' => '40° 7´ 22.8" N 74° 7´ 22.8" W',
				'verbatimLatitude' => '40° 7´ 22.8" N',
				'verbatimLongitude' => '74° 7´ 22.8" W',
				'decimalLatitude' => 40.123,
				'decimalLongitude' => -74.123,
			],
			[
				'verbatimCoordinates' => '40° 7.38’ , -74° 7.38’',
				'verbatimLatitude' => '40° 7.38’',
				'verbatimLongitude' => '-74° 7.38’',
				'decimalLatitude' => 40.123,
				'decimalLongitude' => -74.123,
			],
			[
				'verbatimCoordinates' => 'N40°7’22.8’’, W74°7’22.8’’',
				'verbatimLatitude' => 'N40°7’22.8’’',
				'verbatimLongitude' => 'W74°7’22.8’’',
				'decimalLatitude' => 40.123,
				'decimalLongitude' => -74.123,
			],
			[
				'verbatimCoordinates' => '40°7’22.8"N, 74°7’22.8"W',
				'verbatimLatitude' => '40°7’22.8"N',
				'verbatimLongitude' => '74°7’22.8"W',
				'decimalLatitude' => 40.123,
				'decimalLongitude' => -74.123,
			],
			[
				'verbatimCoordinates' => '40°7\'22.8"N, 74°7\'22.8"W',
				'verbatimLatitude' => '40°7\'22.8"N',
				'verbatimLongitude' => '74°7\'22.8"W',
				'decimalLatitude' => 40.123,
				'decimalLongitude' => -74.123,
			],
			[
				'verbatimCoordinates' => '40 7 22.8, -74 7 22.8',
				'verbatimLatitude' => '40 7 22.8',
				'verbatimLongitude' => '-74 7 22.8',
				'decimalLatitude' => 40.123,
				'decimalLongitude' => -74.123,
			],
			[
				'verbatimCoordinates' => '40.123 -74.123',
				'verbatimLatitude' => '40.123',
				'verbatimLongitude' => '-74.123',
				'decimalLatitude' => 40.123,
				'decimalLongitude' => -74.123,
			],
			[
				'verbatimCoordinates' => '40.123°,-74.123°',
				'verbatimLatitude' => '40.123°',
				'verbatimLongitude' => '-74.123°',
				'decimalLatitude' => 40.123,
				'decimalLongitude' => -74.123,
			],
			[
				'verbatimCoordinates' => '40.123N74.123W',
				'verbatimLatitude' => '40.123N',
				'verbatimLongitude' => '74.123W',
				'decimalLatitude' => 40.123,
				'decimalLongitude' => -74.123,
			],
			[
				'verbatimCoordinates' => '4007.38N7407.38W',
				'verbatimLatitude' => '4007.38N',
				'verbatimLongitude' => '7407.38W',
				'decimalLatitude' => 40.123,
				'decimalLongitude' => -74.123,
			],
			[
				'verbatimCoordinates' => '40°7’22.8"N, 74°7’22.8"W',
				'verbatimLatitude' => '40°7’22.8"N',
				'verbatimLongitude' => '74°7’22.8"W',
				'decimalLatitude' => 40.123,
				'decimalLongitude' => -74.123,
			],
			[
				'verbatimCoordinates' => '400722.8N740722.8W',
				'verbatimLatitude' => '400722.8N',
				'verbatimLongitude' => '740722.8W',
				'decimalLatitude' => 40.123,
				'decimalLongitude' => -74.123,
			],
			[
				'verbatimCoordinates' => 'N 40 7.38 W 74 7.38',
				'verbatimLatitude' => 'N 40 7.38',
				'verbatimLongitude' => 'W 74 7.38',
				'decimalLatitude' => 40.123,
				'decimalLongitude' => -74.123,
			],
			[
				'verbatimCoordinates' => '40:7:22.8N 74:7:22.8W',
				'verbatimLatitude' => '40:7:22.8N',
				'verbatimLongitude' => '74:7:22.8W',
				'decimalLatitude' => 40.123,
				'decimalLongitude' => -74.123,
			],
			[
				'verbatimCoordinates' => '40:7:23N,74:7:23W',
				'verbatimLatitude' => '40:7:23N',
				'verbatimLongitude' => '74:7:23W',
				'decimalLatitude' => 40.1230556,
				'decimalLongitude' => -74.1230556,
			],
			[
				'verbatimCoordinates' => '40°7’23"N 74°7’23"W',
				'verbatimLatitude' => '40°7’23"N',
				'verbatimLongitude' => '74°7’23"W',
				'decimalLatitude' => 40.1230556,
				'decimalLongitude' => -74.1230556,
			],
			[
				'verbatimCoordinates' => '40°7’23"S 74°7’23"E',
				'verbatimLatitude' => '40°7’23"S',
				'verbatimLongitude' => '74°7’23"E',
				'decimalLatitude' => -40.1230556,
				'decimalLongitude' => 74.1230556,
			],
			[
				'verbatimCoordinates' => '40°7’23" -74°7’23"',
				'verbatimLatitude' => '40°7’23"',
				'verbatimLongitude' => '-74°7’23"',
				'decimalLatitude' => 40.1230556,
				'decimalLongitude' => -74.1230556,
			],
			[
				'verbatimCoordinates' => '40d 7’ 23" N 74d 7’ 23" W',
				'verbatimLatitude' => '40d 7’ 23" N',
				'verbatimLongitude' => '74d 7’ 23" W',
				'decimalLatitude' => 40.1230556,
				'decimalLongitude' => -74.1230556,
			],
			[
				'verbatimCoordinates' => '40.123N 74.123W',
				'verbatimLatitude' => '40.123N',
				'verbatimLongitude' => '74.123W',
				'decimalLatitude' => 40.123,
				'decimalLongitude' => -74.123,
			],
			[
				'verbatimCoordinates' => '40° 7.38, -74° 7.38',
				'verbatimLatitude' => '40° 7.38',
				'verbatimLongitude' => '-74° 7.38',
				'decimalLatitude' => 40.123,
				'decimalLongitude' => -74.123,
			],
			[
				'verbatimCoordinates' => '40° 7.38, -74° 7.38',
				'verbatimLatitude' => '40° 7.38',
				'verbatimLongitude' => '-74° 7.38',
				'decimalLatitude' => 40.123,
				'decimalLongitude' => -74.123,
			],
			[
				'verbatimCoordinates' => '40 7 22.8; -74 7 22.8',
				'verbatimLatitude' => '40 7 22.8',
				'verbatimLongitude' => '-74 7 22.8',
				'decimalLatitude' => 40.123,
				'decimalLongitude' => -74.123,
			],
			[
				'verbatimCoordinates' => '50°4\'17.698"south, 14°24\'2.826"east',
				'verbatimLatitude' => '50°4\'17.698"south',
				'verbatimLongitude' => '14°24\'2.826"east',
				'decimalLatitude' => -50.0715828,
				'decimalLongitude' => 14.400785,
			],
			[
				'verbatimCoordinates' => '50d4m17.698S 14d24m2.826E',
				'verbatimLatitude' => '50d4m17.698S',
				'verbatimLongitude' => '14d24m2.826E',
				'decimalLatitude' => -50.0715828,
				'decimalLongitude' => 14.400785,
			],
			[
				'verbatimCoordinates' => '40:26:46N,79:56:55W',
				'verbatimLatitude' => '40:26:46N',
				'verbatimLongitude' => '79:56:55W',
				'decimalLatitude' => 40.4461111,
				'decimalLongitude' => -79.9486111,
			],
			[
				'verbatimCoordinates' => '40:26:46.302N 79:56:55.903W',
				'verbatimLatitude' => '40:26:46.302N',
				'verbatimLongitude' => '79:56:55.903W',
				'decimalLatitude' => 40.446195,
				'decimalLongitude' => -79.9488619,
			],
			[
				'verbatimCoordinates' => '40°26′47″N 79°58′36″W',
				'verbatimLatitude' => '40°26′47″N',
				'verbatimLongitude' => '79°58′36″W',
				'decimalLatitude' => 40.4463889,
				'decimalLongitude' => -79.9766667,
			],
			[
				'verbatimCoordinates' => '40d 26′ 47″ N 79d 58′ 36″ W',
				'verbatimLatitude' => '40d 26′ 47″ N',
				'verbatimLongitude' => '79d 58′ 36″ W',
				'decimalLatitude' => 40.4463889,
				'decimalLongitude' => -79.9766667,
			],
			[
				'verbatimCoordinates' => '40.446195N 79.948862W',
				'verbatimLatitude' => '40.446195N',
				'verbatimLongitude' => '79.948862W',
				'decimalLatitude' => 40.446195,
				'decimalLongitude' => -79.948862,
			],
			[
				'verbatimCoordinates' => '40,446195° 79,948862°',
				'verbatimLatitude' => '40,446195°',
				'verbatimLongitude' => '79,948862°',
				'decimalLatitude' => 40.446195,
				'decimalLongitude' => 79.948862,
			],
			[
				'verbatimCoordinates' => '40° 26.7717, -79° 56.93172',
				'verbatimLatitude' => '40° 26.7717',
				'verbatimLongitude' => '-79° 56.93172',
				'decimalLatitude' => 40.446195,
				'decimalLongitude' => -79.948862,
			],
			[
				'verbatimCoordinates' => '40.446195, -79.948862',
				'verbatimLatitude' => '40.446195',
				'verbatimLongitude' => '-79.948862',
				'decimalLatitude' => 40.446195,
				'decimalLongitude' => -79.948862,
			],
			[
				'verbatimCoordinates' => '40.123256; -74.123256',
				'verbatimLatitude' => '40.123256',
				'verbatimLongitude' => '-74.123256',
				'decimalLatitude' => 40.123256,
				'decimalLongitude' => -74.123256,
			],
			[
				'verbatimCoordinates' => '18°24S 22°45E',
				'verbatimLatitude' => '18°24S',
				'verbatimLongitude' => '22°45E',
				'decimalLatitude' => -18.4,
				'decimalLongitude' => 22.75,
			],
			[
				'verbatimCoordinates' => '18.24S 22.45E',
				'verbatimLatitude' => '18.24S',
				'verbatimLongitude' => '22.45E',
				'decimalLatitude' => -18.4,
				'decimalLongitude' => 22.75,
			],
			[
				'verbatimCoordinates' => '27deg 15min 45.2sec S 18deg 32min 53.7sec E',
				'verbatimLatitude' => '27deg 15min 45.2sec S',
				'verbatimLongitude' => '18deg 32min 53.7sec E',
				'decimalLatitude' => -27.2625556,
				'decimalLongitude' => 18.54825,
			],
			[
				'verbatimCoordinates' => '-23.3245° S / 28.2344° E',
				'verbatimLatitude' => '-23.3245° S',
				'verbatimLongitude' => '28.2344° E',
				'decimalLatitude' => -23.3245,
				'decimalLongitude' => 28.2344,
			],
			[
				'verbatimCoordinates' => '40° 26.7717 -79° 56.93172',
				'verbatimLatitude' => '40° 26.7717',
				'verbatimLongitude' => '-79° 56.93172',
				'decimalLatitude' => 40.446195,
				'decimalLongitude' => -79.948862,
			],
			[
				'verbatimCoordinates' => '27.15.45S 18.32.53E',
				'verbatimLatitude' => '27.15.45S',
				'verbatimLongitude' => '18.32.53E',
				'decimalLatitude' => -27.2625,
				'decimalLongitude' => 18.5480556,
			],
			[
				'verbatimCoordinates' => '-27.15.45 18.32.53',
				'verbatimLatitude' => '-27.15.45',
				'verbatimLongitude' => '18.32.53',
				'decimalLatitude' => -27.2625,
				'decimalLongitude' => 18.5480556,
			],
			[
				'verbatimCoordinates' => '27.15.45.2S 18.32.53.4E',
				'verbatimLatitude' => '27.15.45.2S',
				'verbatimLongitude' => '18.32.53.4E',
				'decimalLatitude' => -27.2625556,
				'decimalLongitude' => 18.5481667,
			],
			[
				'verbatimCoordinates' => '27.15.45,2S 18.32.53,4E',
				'verbatimLatitude' => '27.15.45,2S',
				'verbatimLongitude' => '18.32.53,4E',
				'decimalLatitude' => -27.2625556,
				'decimalLongitude' => 18.5481667,
			],
			[
				'verbatimCoordinates' => 'S23.43563 °  E22.45634 °',
				'verbatimLatitude' => 'S23.43563 °',
				'verbatimLongitude' => 'E22.45634 °',
				'decimalLatitude' => -23.43563,
				'decimalLongitude' => 22.45634,
			],
			[
				'verbatimCoordinates' => '27,71372° S 23,07771° E',
				'verbatimLatitude' => '27,71372° S',
				'verbatimLongitude' => '23,07771° E',
				'decimalLatitude' => -27.71372,
				'decimalLongitude' => 23.07771,
			],
			[
				'verbatimCoordinates' => '27.45.34 S 23.23.23 E',
				'verbatimLatitude' => '27.45.34 S',
				'verbatimLongitude' => '23.23.23 E',
				'decimalLatitude' => -27.7594444,
				'decimalLongitude' => 23.3897222,
			],
			[
				'verbatimCoordinates' => 'S 27.45.34 E 23.23.23',
				'verbatimLatitude' => 'S 27.45.34',
				'verbatimLongitude' => 'E 23.23.23',
				'decimalLatitude' => -27.7594444,
				'decimalLongitude' => 23.3897222,
			],
			[
				'verbatimCoordinates' => '53 16.3863,4 52.8171',
				'verbatimLatitude' => '53 16.3863',
				'verbatimLongitude' => '4 52.8171',
				'decimalLatitude' => 53.273105,
				'decimalLongitude' => 4.880285,
			],
			[
				'verbatimCoordinates' => '50 8.2914,-5 2.4447',
				'verbatimLatitude' => '50 8.2914',
				'verbatimLongitude' => '-5 2.4447',
				'decimalLatitude' => 50.13819,
				'decimalLongitude' => -5.040745,
			],
			[
				'verbatimCoordinates' => 'N 48° 30,6410\', E 18° 57,4583\'',
				'verbatimLatitude' => 'N 48° 30,6410\'',
				'verbatimLongitude' => 'E 18° 57,4583\'',
				'decimalLatitude' => 48.5106833,
				'decimalLongitude' => 18.9576383,
			],
			[
				'verbatimCoordinates' => '50d4m17,698S 14d24m2,826E',
				'verbatimLatitude' => '50d4m17,698S',
				'verbatimLongitude' => '14d24m2,826E',
				'decimalLatitude' => -50.0715828,
				'decimalLongitude' => 14.400785,
			],
			[
				'verbatimCoordinates' => '-0°30′15″, 14°30′0″',
				'verbatimLatitude' => '-0°30′15″',
				'verbatimLongitude' => '14°30′0″',
				'decimalLatitude' => -0.5041667,
				'decimalLongitude' => 14.5,
			],
			[
				'verbatimCoordinates' => '-0 30.500, -0 15.250',
				'verbatimLatitude' => '-0 30.500',
				'verbatimLongitude' => '-0 15.250',
				'decimalLatitude' => -0.5083333,
				'decimalLongitude' => -0.2541667,
			],
			[
				'verbatimCoordinates' => '10.00S 10.00E',
				'verbatimLatitude' => '10.00S',
				'verbatimLongitude' => '10.00E',
				'decimalLatitude' => -10.0,
				'decimalLongitude' => 10.0,
			],
			[
				'verbatimCoordinates' => '00.00S 01.00E',
				'verbatimLatitude' => '00.00S',
				'verbatimLongitude' => '01.00E',
				'decimalLatitude' => 0.0,
				'decimalLongitude' => 1.0,
			],
			[
				'verbatimCoordinates' => '10.432342S 10.6345345E',
				'verbatimLatitude' => '10.432342S',
				'verbatimLongitude' => '10.6345345E',
				'decimalLatitude' => -10.432342,
				'decimalLongitude' => 10.6345345,
			],
			[
				'verbatimCoordinates' => '-0.30.15 14.30.0',
				'verbatimLatitude' => '-0.30.15',
				'verbatimLongitude' => '14.30.0',
				'decimalLatitude' => -0.5041667,
				'decimalLongitude' => 14.5,
			],
			[
				'verbatimCoordinates' => '14.30.0 -0.30.15',
				'verbatimLatitude' => '14.30.0',
				'verbatimLongitude' => '-0.30.15',
				'decimalLatitude' => 14.5,
				'decimalLongitude' => -0.5041667,
			],
			[
				'verbatimCoordinates' => '-0d30m15s 14d30m0s',
				'verbatimLatitude' => '-0d30m15s',
				'verbatimLongitude' => '14d30m0s',
				'decimalLatitude' => -0.5041667,
				'decimalLongitude' => 14.5,
			],
			[
				'verbatimCoordinates' => '14d30m0s -0d30m15s',
				'verbatimLatitude' => '14d30m0s',
				'verbatimLongitude' => '-0d30m15s',
				'decimalLatitude' => 14.5,
				'decimalLongitude' => -0.5041667,
			],
			[
				'verbatimCoordinates' => '14°30′0″, -0°30′15″',
				'verbatimLatitude' => '14°30′0″',
				'verbatimLongitude' => '-0°30′15″',
				'decimalLatitude' => 14.5,
				'decimalLongitude' => -0.5041667,
			],
			[
				'verbatimCoordinates' => '27deg 15min 45,2sec S 18deg 32min 53,7sec E',
				'verbatimLatitude' => '27deg 15min 45,2sec S',
				'verbatimLongitude' => '18deg 32min 53,7sec E',
				'decimalLatitude' => -27.2625556,
				'decimalLongitude' => 18.54825,
			],
			[
				'verbatimCoordinates' => 'N 48, E 14',
				'verbatimLatitude' => 'N 48',
				'verbatimLongitude' => 'E 14',
				'decimalLatitude' => 48.0,
				'decimalLongitude' => 14.0,
			],
			[
				'verbatimCoordinates' => '50°04\'31.0"N 14°26\'16.1"E',
				'verbatimLatitude' => '50°04\'31.0"N',
				'verbatimLongitude' => '14°26\'16.1"E',
				'decimalLatitude' => 50.0752778,
				'decimalLongitude' => 14.4378056,
			],
			[
				'verbatimCoordinates' => 'N50°4\'31.0" E14°26\'16.1"',
				'verbatimLatitude' => 'N50°4\'31.0"',
				'verbatimLongitude' => 'E14°26\'16.1"',
				'decimalLatitude' => 50.0752778,
				'decimalLongitude' => 14.4378056,
			],
			[
				'verbatimCoordinates' => 'N 50°4.00000\', E 14°26.26803\'',
				'verbatimLatitude' => 'N 50°4.00000\'',
				'verbatimLongitude' => 'E 14°26.26803\'',
				'decimalLatitude' => 50.0666667,
				'decimalLongitude' => 14.4378005,
			],
			[
				'verbatimCoordinates' => '50 4 31.0, 14 26 16.1',
				'verbatimLatitude' => '50 4 31.0',
				'verbatimLongitude' => '14 26 16.1',
				'decimalLatitude' => 50.0752778,
				'decimalLongitude' => 14.4378056,
			],
			[
				'verbatimCoordinates' => '50°4\'31.000"N, 14°26\'16.082"E',
				'verbatimLatitude' => '50°4\'31.000"N',
				'verbatimLongitude' => '14°26\'16.082"E',
				'decimalLatitude' => 50.0752778,
				'decimalLongitude' => 14.4378006,
			],
			[
				'verbatimCoordinates' => '50 4.532; 14 26.268',
				'verbatimLatitude' => '50 4.532',
				'verbatimLongitude' => '14 26.268',
				'decimalLatitude' => 50.0755333,
				'decimalLongitude' => 14.4378,
			],
			[
				'verbatimCoordinates' => '50.0755381, 14.4378005',
				'verbatimLatitude' => '50.0755381',
				'verbatimLongitude' => '14.4378005',
				'decimalLatitude' => 50.0755381,
				'decimalLongitude' => 14.4378005,
			],
			[
				'verbatimCoordinates' => '50.0755381-14.4378005',
				'verbatimLatitude' => '50.0755381',
				'verbatimLongitude' => '-14.4378005',
				'decimalLatitude' => 50.0755381,
				'decimalLongitude' => -14.4378005,
			],
			[
				'verbatimCoordinates' => '40.42.46, -74.0.22',
				'verbatimLatitude' => '40.42.46',
				'verbatimLongitude' => '-74.0.22',
				'decimalLatitude' => 40.7127778,
				'decimalLongitude' => -74.0061111,
			],
			[
				'verbatimCoordinates' => '50 4.532 / 14 26.268',
				'verbatimLatitude' => '50 4.532',
				'verbatimLongitude' => '14 26.268',
				'decimalLatitude' => 50.0755333,
				'decimalLongitude' => 14.4378,
			],
			[
				'verbatimCoordinates' => '50.07554n, 14.43780e',
				'verbatimLatitude' => '50.07554n',
				'verbatimLongitude' => '14.43780e',
				'decimalLatitude' => 50.07554,
				'decimalLongitude' => 14.4378,
			],
			[
				'verbatimCoordinates' => '48N, 14E',
				'verbatimLatitude' => '48N',
				'verbatimLongitude' => '14E',
				'decimalLatitude' => 48.0000000,
				'decimalLongitude' => 14.0000000,
			],
		];
	}

	/** @return string[][] */
	public static function getDataForParseInvalid(): array
	{
		return [
			//Formats that the upstream geo-coordinates-parser must reject
			['10,10'],
			['46,8'],
			['12.12323, 123'],
			['24.0, 26.0'],
			['33.25°S 18.25°E'],
			['27.0 23.0'],
			['10.00000S 10.000000E'],
			['00.00 01.00'],
			['50°4\'17.698"south, 24.34532'],
			['90°4\'17.698"south, 23°4\'17.698"east'],
			['89°4\'17.698"south, 183°4\'17.698"east'],
			['50°4\'17.698"east, 23°4\'17.698"south'],
			['E23.34355,S25.324234'],
			['23°45\'12.2\'\'S 18.33\'56.7\'\'E'],
			['S 27.45.34 23.23.23'],
			['S 27.45.34 S 23.23.23'],
			['S 90°4\'17.698" S 23°4\'17.698"'],
			['27.45.34 S S 23.23.23'],
			['27.45.34  23.23.23 E'],
			['8°83S 35°67E '],
			['25.62S, 27.77E'],
			['-254.4602, 31.53681'],
			['1234.524234234, 1241.1541241234'],

			//A direction missing, repeated or on the wrong side
			['N 40.123 N 74.123'],
			['N 40.123 S 74.123'],
			['S 40.123 N 74.123'],
			['N 27.15.45 N 18.32.53'],
			['N 27deg 15min 45.2sec N 18deg 32min 53.7sec'],
			['50d4m17.698 N 14d24m2.826s'],
			['S 33 51 54 S 151 12 34'],
			['40.123 E 74.123 W'],
			['N 40.123 N, E 74.123 E'],
			['S 40.123 N, W 74.123 E'],
			['N 40°7’22.8" S, W 74°7’22.8" E'],
			['N 27.15.45 S, E 18.32.53 W'],
			['N 27deg 15min 45.2sec S, E 18deg 32min 53.7sec W'],
			['5004 31N 14 26 16E'],

			//Text around the coordinates
			['GPS: 40.123, -74.123'],
			['40.123, -74.123 x'],
			['foo 40.123, 74.123 bar'],
			['40°7’22.8"N, 74°7’22.8"W ok'],
			['x 27.15.45S 18.32.53E'],
			['27.15.45S 18.32.53E x'],
			['x 27deg 15min 45.2sec S 18deg 32min 53.7sec E'],
			['27deg 15min 45.2sec S 18deg 32min 53.7sec E x'],
			['x 53 16.3863,4 52.8171'],
			['53 16.3863,4 52.8171 x'],

			//Minutes above 59, a single zero after the period or a value out of range
			['8°83S 35°67E'],
			['50°70′ N, 14°80′ E'],
			['07.68 03.60'],
			['10.0S 10.0E'],
			['24.0, 26.30'],
			['24.30, 26.0'],
			['N40.7, W74.0'],
			['4007.000N7407.000W'],
			['48,000, 17,100'],
			['50,070, 14,000'],
			['N 50, E 180'],

			//Degrees and minutes split from one number
			['173,19675 -162,66030'],
			['-500123 100014'],
			['23,7492 956,801'],
			['N 48.93, E 15.5606'],
			['50°5,14°26'],
			['50°4\'32,14°26\'16'],
			['1.1235.180'],
			['50.0755381014.4378005'],
			["-500123\u{00A0}100014"],
			['50 30,000 14 15,000'],
			['50.4.32,14.26.16'],
			['N 50,08 E 14,4378005'],
			['N50,0755381 E14,44'],
		];
	}

	/** @return array<string, array{string, string}> */
	public static function getDataForParseWithMissingDirection(): array
	{
		return [
			'decimal degrees' => ['50.123N 14.456E', '50.123N 14.456'],
			'degrees, minutes and seconds separated by periods' => ['50.12.30N 14.12.30E', '50.12.30N 14.12.30'],
			'abbreviated degrees, minutes and seconds' => [
				'50 DEG 12 MIN 30 SEC N 14 DEG 12 MIN 30 SEC E',
				'50 DEG 12 MIN 30 SEC N 14 DEG 12 MIN 30 SEC',
			],
			'degrees, minutes and seconds with symbols' => [
				'50° 12\' 30" N 14° 12\' 30" E',
				'50° 12\' 30" N 14° 12\' 30"',
			],
		];
	}
}
