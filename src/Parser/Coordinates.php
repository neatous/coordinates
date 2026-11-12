<?php

declare(strict_types=1);

namespace Neatous\Coordinates\Parser;

final class Coordinates
{
	private string $verbatimLatitude;

	private string $verbatimLongitude;

	private string $decimalLatitude;

	private string $decimalLongitude;

	public function __construct(
		string $verbatimLatitude,
		string $verbatimLongitude,
		string $decimalLatitude,
		string $decimalLongitude,
	) {
		$this->verbatimLatitude = $verbatimLatitude;
		$this->verbatimLongitude = $verbatimLongitude;
		$this->decimalLatitude = $decimalLatitude;
		$this->decimalLongitude = $decimalLongitude;
	}

	public function getVerbatimLatitude(): string
	{
		return $this->verbatimLatitude;
	}

	public function getVerbatimLongitude(): string
	{
		return $this->verbatimLongitude;
	}

	public function getDecimalLatitude(): string
	{
		return $this->decimalLatitude;
	}

	public function getDecimalLongitude(): string
	{
		return $this->decimalLongitude;
	}
}
