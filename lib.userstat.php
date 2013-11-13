<?php
/* Copyright (C) 2013 Linus Unnebäck <linus@folkdatorn.se>
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
 */

namespace wizstats\userstat;

function getAllBalances() {

  if($balanacesjsondec = apc_fetch('balance')) {
  } else {
    $balance = file_get_contents("/var/lib/eligius/$serverid/balances.json");
    $balanacesjsondec = json_decode($balance, true);
    // Store Cache for 10 minutes
    apc_store('balance', $balanacesjsondec, 600);
  }

  return $balanacesjsondec;
}

function getAllBalancesSM() {

  if($balanacesjsondecSM = apc_fetch('balance_smpps')) {
  } else {
    $balanacesjsonSM = file_get_contents("/var/lib/eligius/$serverid/smpps_lastblock.json");
    $balanacesjsondecSM = json_decode($balanacesjsonSM,true);
    // Store Cache forever (10 days)
    apc_store('balance_smpps', $balanacesjsondecSM, 864000);
  }

  return $balanacesjsondecSM;
}

function getBalanceForUser($link, $user_id, $givenuser) {
  global $psqlschema, $serverid;

  $return = array();
  $balanacesjsondec = getAllBalances();
  $mybal = $balanacesjsondec[$givenuser];

  if ($mybal) {

    $return['bal']       = (isset($mybal["balance"] )) ? $mybal["balance"]  : 0;
    $return['ec']        = (isset($mybal["credit"]  )) ? $mybal["credit"]   : 0;
    $return['everpaid']  = (isset($mybal["everpaid"])) ? $mybal["everpaid"] : 0;

    $return['lbal']      = (isset($mybal["included_balance_estimate"])) ? ($return['bal'] - $mybal["included_balance_estimate"]) : $return['bal'];
    $return['lec']       = (isset($mybal["included_credit_estimate"] )) ? ($return['ec']  - $mybal["included_credit_estimate"] ) : $return['ec'];

    $return['datadate']  = $mybal["newest"];
    $return['balupdate'] = $mybal["last_balance_update"];

  } else {

    # fall back to sql
    $sql = "select * from $psqlschema.stats_balances where server=$serverid and user_id=$user_id order by time desc limit 1";
    $result = pg_exec($link, $sql);
    $numrows = pg_numrows($result);

    if (!$numrows) {

      $return['bal'] = "N/A";
      $return['ec'] = "N/A";

      $return['lbal'] = "N/A";

      $return['datadate'] = "N/A";

    } else {
      $row = pg_fetch_array($result, 0);

      $return['bal'] = $row["balance"];
      $return['ec'] = $row["credit"];

      $return['lbal'] = "N/A";

      $return['datadate'] = $row["time"];
      $return['everpaid'] = $row["everpaid"];

    }

  }

  return $return;
}

function getBalanceForUserSM($link, $user_id, $givenuser) {

  $balanacesjsondecSM = getAllBalancesSM();
  $mybalSM = $balanacesjsondecSM[$givenuser];

  if ($mybalSM) {
    # SMPPS credit needed to be halved for the pool to be statistically viable
    $smppsec = $mybalSM["credit"];
    $smppshalf = $mybalSM["credit"]/2;
    $smppsec -= $smppshalf;
  } else {
    $smppsec = 0;
  }

  return array('smppsec' => $smppsec);
}
