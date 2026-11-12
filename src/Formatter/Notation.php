<?php

declare(strict_types=1);

namespace Neatous\Coordinates\Formatter;

enum Notation
{
	/** Decimal degrees with the hemisphere letters, such as "N 50.08765, E 14.42123" */
	case HemisphereDecimal;

	/** Decimal degrees with a minus sign for the southern and western hemisphere, such as "50.08765, -14.42123" */
	case SignedDecimal;

	/** Degrees, minutes and seconds with the hemisphere letters, such as "N 50° 5' 15.54", E 14° 25' 16.43"" */
	case DegreesMinutesSeconds;
}
