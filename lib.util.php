<?php
/* Copyright (C) 2011 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * 09/17/2012: Jason Hughes <wizkid057@gmail.com>
 *             - Code modified, trimmed, and adapted to work with wizstats
 */


date_default_timezone_set('UTC');

function satoshiToBTC($satoshi) {
	return bcmul($satoshi, '0.00000001', 8).' BTC';
}

/**
 * Convert a money amount from Satoshis to TBC.
 * @param string|integer $satoshi amount of satoshis
 * @return string specified amount in TBC
 */
function satoshiToTBC($satoshi) {
	$tonalDigits = array('9' => '&#59865;', 'a' => '&#59866;', 'b' => '&#59867;', 'c' => '&#59868;', 'd' => '&#59869;', 'e' => '&#59870;', 'f' => '&#59871;');
	$hex = base_convert(bcdiv($satoshi, 1), 10, 16);
	$xbc = preg_replace("/(?=.{4}$)(.*?)0*$/", ".\$1", "0000$hex");
	$xbc = rtrim(preg_replace("/^0+([^.]+\.)/", "\$1", $xbc), '.');
	$result = strtr($xbc, $tonalDigits);
	// TODO: commas every 4 digits

	return '<span class="tfix">'.$result.'</span> TBC';
}

/**
 * What unit should we use ?
 * @return string TBC or BTC, according to settings.
 */
function getPrefferedMonetaryUnit() {
	if ( 
		(isset($_COOKIE['a2_tbc']) && $_COOKIE['a2_tbc']) 
		|| (isset($_GET["tonal"]) && $_GET["tonal"])
	) {
		return 'TBC';
	} else return 'BTC';
}

/**
 * Format an amount of satoshis in the currently preffered unit.
 * @param string|integer $satoshis amount of satoshis
 * @return string formatted amount
 */
function prettySatoshis($satoshis) {
	if(getPrefferedMonetaryUnit() == 'TBC') {
		return satoshiToTBC($satoshis);
	} else return satoshiToBTC($satoshis);
}

/**
 * Format a BTC amount in BTC.
 * @param int|float|string $btc amount of BTC
 * @return string formatted amount
 */
function prettyBTC($btc) {
	return prettySatoshis(bcadd(0, bcdiv($btc, '0.00000001', 8), 0));
}

/**
 * Convert from satoshis to BTC, but output the raw result as a floating number (with no " BTC" suffix).
 * @param string|int|float $s amount of satoshis
 * @return float raw formatted amount (no suffix)
 */
function rawSatoshiToBTC($s) {
	return bcmul($s, '0.00000001', 8);
}

/**
 * Format a fraction in the currently preferred way.
 * @param float $a proportion
 * @return string formatted proportion
 */
function prettyProportion($a) {
	// TODO: Tonal permill
	return sprintf("%.2f%%", $a * 100);
}

/**
 * Get the preffered BlockExplorer-like URI.
 */
function getBE() {
	$site = isset($_COOKIE['a2_bclink']) ? $_COOKIE['a2_bclink'] : 'bc.info';
	switch ($site) {
	case 'be':
		return 'blockexplorer.com';
	case 'pident':
	case 'piuk':
	case 'bc.info':
		return 'blockchain.info';
	}
	return $site;
}

/**
 * Format a duration in a human-readable way.
 * @param int|float $duration the time, in seconds, to format
 * @param bool $align whether we should align the components.
 * @return string a human-readable version of the same duration
 */
function prettyDuration($duration, $align = false, $precision = 4) {
	if($duration < 60) return "a few seconds";
	else if($duration < 300) return "a few minutes";

	$units = array("month" => 30.5 * 86400, "week" => 7*86400, "day" => 86400, "hour" => 3600, "minute" => 60);

	$r = array();
	foreach($units as $u => $d) {
		$num = floor($duration / $d);
		if($num >= 1) {
			$plural = ($num > 1 ? 's' : ($align ? '&nbsp;' : ''));
			if($align && count($r) > 0) {
				$num = str_pad($num, 2, '_', STR_PAD_LEFT);
				$num = str_replace('_', '&nbsp;', $num);
			}
			$r[] = $num.' '.$u.$plural;
			$duration %= $d;
		}
	}

	$prefix = '';
	while(count($r) > $precision) {
		#$prefix = 'about ';
		array_pop($r);
	}

	if(count($r) > 1) {
		$ret = array_pop($r);
		$ret = implode(', ', $r).' and '.$ret;
		return $prefix.$ret;
	} else return $prefix.$r[0];
}

function prettyDurationshort($duration, $align = false, $precision = 4) {
	if($duration < 60) return "a few seconds";
	else if($duration < 300) return "a few minutes";

	$units = array("mon" => 30.5 * 86400, "wk" => 7*86400, "day" => 86400, "hr" => 3600, "min" => 60);

	$r = array();
	foreach($units as $u => $d) {
		$num = floor($duration / $d);
		if($num >= 1) {
			$plural = ($num > 1 ? 's' : ($align ? '&nbsp;' : ''));
			if($align && count($r) > 0) {
				$num = str_pad($num, 2, '_', STR_PAD_LEFT);
				$num = str_replace('_', '&nbsp;', $num);
			}
			$r[] = $num.' '.$u.$plural;
			$duration %= $d;
		}
	}

	$prefix = '';
	while(count($r) > $precision) {
		$prefix = 'about ';
		array_pop($r);
	}

	if(count($r) > 1) {
		$ret = array_pop($r);
		$ret = implode(', ', $r).' and '.$ret;
		return $prefix.$ret;
	} else return $prefix.$r[0];
}

/**
 * Format a hashrate in a human-readable fashion.
 * @param int|float|string $hps the number of hashes per second
 * @return string a formatted rate
 */
function prettyHashrate($hps) {
	if($hps < 10000000) {
		return number_format($hps / 1000, 2).' kh/s';
	} else if($hps < 10000000000) {
		return number_format($hps / 1000000, 2).' Mh/s';
	} else if ($hps < 1e13) {
		return number_format($hps / 1e9, 2).' Gh/s';
	} else
		return number_format($hps / 1e12, 2).' Th/s';
}

/**
 * Extract a not-too-dark, not-too-light color from anything.
 * @param mixed $seed the seed to extract the color from.
 * @return string a color in the rgb($r, $g, $b) format.
 */
function extractColor($seed) {
	global $COLOR_OVERRIDES;

	if(isset($COLOR_OVERRIDES[$seed])) {
		return $COLOR_OVERRIDES[$seed];
	}

	static $threshold = 100;

	$d = sha1($seed);

	$r = hexdec(substr($d, 0, 2));
	$g = hexdec(substr($d, 2, 2));
	$b = hexdec(substr($d, 4, 2));

	if($r + $g + $b < $threshold || $r + $g + $b > (3*255 - $threshold)) return extractColor($d);
	else return "rgb($r, $g, $b)";
}

/**
 * Neatly formats a (large) integer.
 * @param integer $i integer to format
 * @return string the formatted integer
 */
function prettyInt($i) {
	return number_format($i, 0, '.', ',');
}

/**
 * Get the formatted number of seconds, minutes and hours from a duration.
 * @param integer $d the duration (number of seconds)
 * @return array array($seconds, $minutes, $hours)
 */
function extractTime($d) {
	$seconds = $d % 60;
	$minutes = (($d - $seconds) / 60) % 60;
	$hours = ($d - 60 * $minutes - $seconds) / 3600;
	if($seconds) {
		$seconds .= 's';
	} else $seconds = '';
	if($minutes) {
		$minutes .= 'm';
	} else $minutes = '';
	if($hours) {
		$hours .= 'h';
	} else $hours = '';
	if($hours && $minutes == '') {
		$minutes = '0m';
	}
	if(($hours || $minutes) && $seconds == '') {
		$seconds = '0s';
	}

	return array($seconds, $minutes, $hours);
}

/**
 * Format the name of an invalid share.
 * @param string $reason the reason why the share was invalid.
 * @return string formatted name
 */
function prettyInvalidReason($reason) {
	$reason = str_replace('-', ' ', $reason);
	switch ($reason) {
	case 'bad cb flag':
		$desc = 'Your miner tried to add coinbase flags that require pool-side support, so it could not be accepted.';
		break;
	case 'bad cb length':
		$desc = 'Your miner sent a coinbase longer than the valid allowable length of 95 bytes.';
		break;
	case 'bad cb prefix':
		$desc = 'Your miner changed the coinbase prefix. This is not allowed.';
		break;
	case 'bad diffbits':
		$desc = 'The block "bits" was wrong. Your miner software is probably corrupting it.';
		break;
	case 'bad txnmrklroot':
		$desc = 'Your miner sent a block with the wrong merkle root in the header.';
		break;
	case 'bad txns':
		$desc = 'Your miner sent a block with invalid or not allowed transactions. Eligius does not currently support modifying transactions included in blocks.';
	case 'bad version':
		$desc = 'The block version was wrong. Your miner software must be corrupting it.';
		break;
	case 'duplicate':
		$desc = 'The same share was submitted twice. This can indicate doing the same work twice, but is most often caused by a retried submission after a network error.';
		break;
	case 'H not zero':
		$desc = 'The solution did not meet the required share difficulty. Usually this means the miner has a hardware problem.';
		break;
	case 'prevhash stale':
	case 'stale prevblk':
		$desc = 'The solution was based on the past previous-block-hash. This will happen often if a miner doesn\'t support longpolling, and can happen for a very brief period of time during longpolls if it does.';
		break;
	case 'prevhash wrong':
	case 'bad prevblk':
		$desc = 'The solution contained a wrong previous-block-hash. This could indicate a very old prevhash, or a mining software bug.';
		break;
	case 'stale':
		$desc = 'The solution did not have the current previous-block-hash. This will happen often if a miner doesn\'t support longpolling, and can happen for a very brief period of time during longpolls if it does.';
		break;
	case 'stale work':
		$desc = 'The solution solved work issued over 2 minutes ago, with obsolete payout generation information.';
		break;
	case 'time invalid':
		$desc = 'The miner modified the work\'s time header, when it was not supposed to. This means the mining software does not comply with the rollntime specification correctly, and is attempting to roll it when not allowed to.';
		break;
	case 'time too new':
		$desc = 'The miner modified the work\'s time header too far into the future. This probably means the system clock or timezone is set wrong, but can also indicate mining software bugs.';
		break;
	case 'time too old':
		$desc = 'The miner modified the work\'s time header too far into the past. This probably means the system clock or timezone is set wrong, but can also indicate mining software bugs.';
		break;
	case 'unknown user':
		$desc = 'The pool had no record of giving this address any work at the time the share was submitted. Usually this only happens right after the pool server crashes or is restarted.';
		break;
	case 'unknown work':
		$desc = 'The pool had no record of the work submitted. Possible causes include a miner corrupting the work, or holding on to it for over 2 minutes. This also indicates a stale-prevblk work when using stratum.';
		break;
	case 'high hash':
		$desc = 'The miner submitted a share with a hash higher than the target provided by the pool.  This suggests that the miner software does not properly handle work with variable difficulty (Not pdiff 1).';
		break;
	}
	if (isset($desc))
		return "<span title=\"$desc\">$reason</span>";

	return "$reason";
}

/**
 * Format the status of a block.
 * @param mixed $s the status of the block.
 * @param int $when when was the block found?
 * @return string formatted block status.
 */
function prettyBlockStatus($s, $when = null) {
	if($s === null) {
		return '<td class="unconfirmed conf9" title="There is no bitcoin node available at the moment to check the status of this block."><span>???</span></td>';	
	} else if($when !== null && (time() - $when) < FRESH_BLOCK_THRESHOLD) {
		return '<td class="unconfirmed conf9" title="It is too soon to try to determine the status of this block."><span>???</span></td>';
	} else if($s === true) {
		return '<td>Confirmed</td>';
	} else if(is_numeric($s)) {
		$opacity = (int)floor(10 * $s / NUM_CONFIRMATIONS);
		if($opacity > 9) $opacity = 9;
		return '<td class="unconfirmed conf'.$opacity.'" title="'.$s.' confirmations left"><span>'.$s.' left</span></td>';
	} else if($s === false) {
		return '<td>Invalid</td>';
	} else {
		return '<td class="unconfirmed conf9" title="Unknown status"><span>Unknown</span></td>';
	}
}



const SHARE_DIFF = 0.999984741210937500000000000000000000000000000000000000000000000000037091495526931787187794383295520714646689355129378026328393541675746568232157348440782664046922186356528849561891415710665458082566072220046505;
function getCDF($shares, $difficulty) {
	return 1.0 - exp(- SHARE_DIFF * $shares / $difficulty);
}

function reverseCDF($cdf, $difficulty) { return ($difficulty * log(-(1/($cdf-1)))) / SHARE_DIFF; }


function currentPPSsatoshi($difficulty) {
	return (((25*SHARE_DIFF)*100000000)/$difficulty);
}

