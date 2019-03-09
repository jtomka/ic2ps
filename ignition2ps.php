<?php
ini_set('date.timezone', 'UTC');
$DEBUG = true;

function err($format, ...$args) {
    fputs(STDERR, vsprintf($format, $args));
    fputs(STDERR, "\n");
}

function debug($format, ...$args) {
    global $DEBUG;
    if ($DEBUG)
        return call_user_func_array('err', func_get_args());
}

const STATE_INIT = 'init';
const STATE_SEATS = 'seats';
const STATE_CARDS = 'cards';
const STATE_FLOP = 'flop';
const STATE_TURN = 'turn';
const STATE_RIVER = 'river';
const STATE_SUMMARY = 'summary';

function check_state_change($file)
{
    $state_str = array(
        STATE_INIT => 'Ignition Hand ',
        STATE_SEATS => 'Seat ',
        STATE_CARDS => '*** HOLE CARDS ***',
        STATE_FLOP => '*** FLOP ***',
        STATE_TURN => '*** TURN ***',
        STATE_RIVER => '*** RIVER ***',
        STATE_SUMMARY => '*** SUMMARY ***',
    );

    $state_tree = array(
        STATE_INIT => array(
            STATE_SEATS,
        ),
        STATE_SEATS => array(
            STATE_CARDS,
            STATE_SUMMARY,
        ),
        STATE_CARDS => array(
            STATE_FLOP,
            STATE_SUMMARY
        ),
        STATE_FLOP => array(
            STATE_TURN,
            STATE_SUMMARY,
        ),
        STATE_TURN => array(
            STATE_RIVER,
            STATE_SUMMARY,
        ),
        STATE_RIVER => array(
            STATE_SUMMARY,
        ),
        STATE_SUMMARY => array(
            STATE_INIT,
        ),
    );

    if (! array_key_exists($file->state, $state_tree))
        return false;

    foreach ($state_tree[$file->state] as $state) {
        if (strpos($file->line, $state_str[$state]) === 0) {
            $file->previous_state = $file->state;
            $file->state = $state;
            if (strlen($file->line) > strlen($state_str[$state]))
                $file->rerun = true; // Additional info on this line.
            return true;
        }
    }
    return false;
}

/*
 * Small Blind  [ME] : Folds
 * UTG : Checks 
 * Big Blind : Calls $0.45 
 * Dealer : Raises $0.50 to $0.50
 * UTG+2 : All-in(raise) $0.62 to $0.62
 * UTG+1  [ME] : Return uncalled portion of bet $0.10
 */
function parse_action($hand, $file)
{
    $action_regexs = array(
        'REGEX_ACTION' => '/^(?<player>.*?)(?<me>  \[ME\])? : (?<action>Folds|Checks|Calls|Bets|Raises|All-in|All-in\(raise\)|Hand result(-Side pot)?)( \((auth|timeout|disconnect)\))?( \$(?<chips>[0-9.]+)( to \$(?<to_chips>[0-9.]+))?)?$/',
        'REGEX_RETURN' => '/^(?<player>.*?)(?<me>  \[ME\])? : (?<action>Return) uncalled portion of bet \$(?<chips>[0-9.]+)$/',
        'REGEX_NOSHOW' => '/^(?<player>.*?)(?<me>  \[ME\])? : (?<action>Does not show|Mucks|Folds)( & shows)? \[(?<card1>[2-9TJQKA][cdhs]) (?<card2>[2-9TJQKA][cdhs])\]( Show1 \[(?<show1>[2-9TJQKA][cdhs])\])?( \((?<ranking>.*)\))?$/',
        'REGEX_SHOWDOWN' => '/^(?<player>.*?)(?<me>  \[ME\])? : (?<action>Showdown) \[(?<card1>[2-9TJQKA][cdhs]) (?<card2>[2-9TJQKA][cdhs]) (?<card3>[2-9TJQKA][cdhs]) (?<card4>[2-9TJQKA][cdhs]) (?<card5>[2-9TJQKA][cdhs])\] \((?<ranking>.*)\)$/',
        // ignore, not an actual showdown
        'REGEX_SHOWDOWN_NOT' => '/^(?<player>.*?)(?<me>  \[ME\])? : (?<action>Showdown\(High Card\))$/',
    );

    $state_property = array(
        STATE_CARDS => 'preflop',
        STATE_FLOP => 'flop',
        STATE_TURN => 'turn',
        STATE_RIVER => 'river',
    );
    $prop_name = $state_property[$file->state];
    if (! isset($hand->$prop_name))
        $hand->$prop_name = array();
    $prop = &$hand->$prop_name;

    foreach ($action_regexs as $regex) {
        if (preg_match($regex, $file->line, $prop['action'][])) {
            return true;
        }
    }

    $file->error = 'Expecting Card dealt, Folds, Checks, Calls, Raises, All-in, Return, Hand result, Does not show line';
    return false;
}

function me($player)
{
    global $hand;
    global $file;

    $str_id_prefix = 'Ignition';

    if (empty($hand->me)) {
        foreach ($hand->seats as $seat) {
            if (! empty($seat['me'])) {
                $hand->me = $seat['player'];
                break;
            }
        }
    }

    if ($player == $hand->me)
        $player = $str_id_prefix . $file->account['id'];

    return $player;
}

function process_actions($actions)
{
    $out = '';

    $tr_action = array(
        'Folds' => 'folds',
        'Checks' => 'checks',
        'Calls' => 'calls',
        'Bets' => 'bets',
        'Raises' => 'raises',
        'All-in\(bet\)' => 'bets',
        'All-in\(call\)' => 'calls',
        'All-in\(raise\)' => 'raises',
        'Return uncalled portion of bet' => '',
    );

    $last_bet_size = 0;
    foreach ($actions as $action) {
        if (empty($action['action'])) {
            // skip everything else

        } elseif (in_array($action['action'], array('Folds', 'Checks'))) {
            $out .= sprintf("%s: %s\n", me($action['player']), $tr_action[$action['action']]);

        } elseif (in_array($action['action'], array('Bets', 'Calls'))) {
            $out .= sprintf("%s: %s $%.2f\n", me($action['player']), $tr_action[$action['action']], $action['chips']);

        } elseif (in_array($action['action'], array('Raises', 'All-in\(raise\)'))) {
            $out .= sprintf("%s: %s $%.2f to $%.2f%s\n", me($action['player']), $tr_action[$action['action']], $action['chips'], $action['to_chips'], ($action['action'] == 'Raises' ? '' : ' and is all-in'));

        } elseif (in_array($action['action'], array('All-in'))) {
            $out .= sprintf("%s: %s $%.2f and is all-in\n", me($action['player']), ($action['chips'] < $last_bet_size ? 'calls' : 'bets'), $action['chips']);

        } elseif ($action['action'] == 'Return') {
            $out .= sprintf("Uncalled bet ($%.02f) returned to %s\n", $action['chips'], me($action['player']));
        }

        if (! empty($action['action']['to_chips']))
            $last_bet_size = $action['action']['to_chips'];
        elseif (! empty($action['action']['chips']))
            $last_bet_size = $action['action']['chips'];
    }

    return $out;
}

function process_hand($hand, $file)
{
    debug('file %s', $file->file['full_path']);
    debug('line %d', $file->lineno);
    debug('hand %s', $hand->info['id']);

    $out = '';

    $str_casino = 'PokerStars';
    $str_handid_prefix = '99999';
    $str_table_size = '6-max'; // table size missing in ignition
    $str_table_prefix = 'Ignition';

    $tr_game = array(
        'HOLDEM' => "Hold'em",
    );

    $tr_limit = array(
        'No Limit' => "No Limit",
    );

    $tr_blind = array(
        'Small Blind' => 'small blind',
        'Big blind' => 'big blind',
    );

    $tr_summary_position = array(
        'Dealer' => 'button',
        'Big Blind' => 'big blind',
        'Small Blind' => 'small blind',
    );

    $tr_summary_action = array(
        'Folded before the FLOP' => 'folded before Flop',
        'Folded on the FLOP' => 'folded on the Flop',
        'Folded on the TURN' => 'folded on the Turn',
        'Folded on the RIVER' => 'folded on the River',
    );

    $timestamp = mktime($hand->info['hour'], $hand->info['minute'], $hand->info['second'], $hand->info['month'], $hand->info['day'], $hand->info['year']);
    $time_format = '%Y/%m/%d %H:%M:%S';
    $aest_time = strftime($time_format, $timestamp);
    $et_time = strftime($time_format, $timestamp - (15 * 60 * 60));

    $out .= sprintf("%s Hand #%d%d:  %s %s ($%.02f/$%.02f USD) - %s AEST [%s ET]\n", $str_casino, $str_handid_prefix, $hand->info['id'], $tr_game[$hand->info['game']], $tr_limit[$hand->info['limit']], $file->history['sb'], $file->history['bb'], $aest_time, $et_time);
    
    if (empty($hand->dealer['seatno'])) {
        // Find first empty seat, that'll be the dealer.
        $i = 1;
        foreach ($hand->seats as $seat) {
            if (empty($seat))
                continue;

            if ($seat['seatno'] != $i) {
                $hand->dealer['seatno'] = $i;
                break;
            }

            $i++;
        }
    }

    $out .= sprintf("Table '%s%s' %s Seat #%d is the button\n", $str_table_prefix, $hand->info['table'], $str_table_size, $hand->dealer['seatno']);

    foreach ($hand->seats as $seat) {
        if (empty($seat))
            continue;
        $out .= sprintf("Seat %d: %s ($%.02f in chips)\n", $seat['seatno'], me($seat['player']), $seat['chips']);
    }

    if (! empty($hand->posts['sb']))
        $out .= sprintf("%s: posts small blind $%.02f\n", me($hand->posts['sb']['player']), $hand->posts['sb']['chips']);

    $out .= sprintf("%s: posts big blind $%.02f\n", me($hand->posts['bb']['player']), $hand->posts['bb']['chips']);

    $out .= sprintf("*** HOLE CARDS ***\n");

    foreach ($hand->preflop['hole_cards'] as $player) {
        if (! empty($player['me'])) {
            $me = $player;
            break;
        }
    }
    $out .= sprintf("Dealt to %s [%s %s]\n", me($me['player']), $me['card1'], $me['card2']);

    $out .= process_actions($hand->preflop['action']);

    if (! empty($hand->flop['cards'])) {
        $out .= sprintf("*** FLOP *** [%s %s %s]\n", $hand->flop['cards'][1], $hand->flop['cards'][2], $hand->flop['cards'][3]);

        if (! empty($hand->flop['action']))
            $out .= process_actions($hand->flop['action']);
    }

    if (! empty($hand->turn['cards'])) {
        $out .= sprintf("*** TURN *** [%s %s %s] [%s]\n", $hand->flop['cards'][1], $hand->flop['cards'][2], $hand->flop['cards'][3], $hand->turn['cards'][1]);

        if (! empty($hand->turn['action']))
            $out .= process_actions($hand->turn['action']);
    }

    if (! empty($hand->river['cards'])) {
        $out .= sprintf("*** RIVER *** [%s %s %s %s] [%s]\n", $hand->flop['cards'][1], $hand->flop['cards'][2], $hand->flop['cards'][3], $hand->turn['cards'][1], $hand->river['cards'][1]);

        if (! empty($hand->river['action']))
            $out .= process_actions($hand->river['action']);
    }

    $all_action = array_merge($hand->preflop['action'], empty($hand->flop['action']) ? array() : $hand->flop['action'], empty($hand->turn['action']) ? array() : $hand->turn['action'], empty($hand->river['action']) ? array() : $hand->river['action']);

    $has_showdown = false;
    $showdown = array();
    foreach ($all_action as $action) {
        if (empty($action))
            continue;

        if (in_array($action['action'], array('Showdown', 'Mucks', 'Hand result'))) {
            if (! $has_showdown && $action['action'] == 'Showdown')
                $has_showdown = true;
            $showdown[] = $action;
        }
    }

    if ($has_showdown) {

        $out .= "*** SHOW DOWN ***\n";

        foreach ($showdown as $action) {
            if ($action['action'] == 'Showdown') {
                foreach ($hand->preflop['hole_cards'] as $seat) {
                    if ($seat['player'] == $action['player'])
                        break;
                }

                $out .= sprintf("%s: shows [%s %s] (%s)\n", me($action['player']), $seat['card1'], $seat['card2'], $action['ranking']);

            } elseif ($action['action'] == 'Mucks') {
                $out .= sprintf("%s: mucks hand\n", me($action['player']));

            } elseif ($action['action'] == 'Hand result') {
                $out .= sprintf("%s collected $%.02f from pot\n", me($action['player']), $action['chips']);
            }
        }
    }

    $out .= "*** SUMMARY ***\n";

    $result_pot = 0;
    $total_pot = $hand->summary['pot']['chips'];
    foreach ($all_action as $action) {
        if (empty($action))
            continue;

        if (strpos($action['action'], 'Hand result') === 0) {
            $result_pot += $action['chips'];
        } elseif ($action['action'] == 'Return') {
            $result_pot -= $action['chips'];
            $total_pot -= $action['chips'];
        }
    }
    $rake = $total_pot - $result_pot;
    $out .= sprintf("Total pot $%.02f | Rake $%.02f\n", $hand->summary['pot']['chips'], $rake);

    if (! empty($hand->summary['board']))
        $out .= sprintf("Board [%s]\n", implode(' ', array_slice($hand->summary['board'], 1, 5)));

    foreach ($hand->summary['seats'] as $action) {
        if (empty($action))
            continue;

        $str_position = '';
        if (isset($tr_summary_position[$action['player']]))
            $str_position = ' (' . $tr_summary_position[$action['player']] . ')';

        if (empty($action['action'])) {
            foreach ($hand->preflop['hole_cards'] as $seat) {
                if ($seat['player'] == $action['player'])
                    break;
            }

            $won_with = '';
            if (! empty($action['ranking']))
                $won_with = sprintf(' with %s', $action['ranking']);

            if (! $won_with) // make up something
                $won_with = ' with a pair of Deuces';

            $result = sprintf('showed [%s %s] and won ($%.02f)%s', $seat['card1'], $seat['card2'], $action['chips'], $won_with);
        } elseif ($action['action'] == 'Folded') {
            $result = $tr_summary_action[$action['action_full']];
        } elseif ($action['action'] == 'Mucked') {
            $result = sprintf('mucked [%s %s]', $action['card1'], $action['card2']);
        }

        $out .= sprintf("Seat %d: %s%s %s\n", $action['seatno'], me($action['player']), $str_position, $result);
    }

    echo "\n" . $out . "\n";

    return true;
}

function process_file($ignition_hh_dir, $account_dir, $hh_file) {
    global $file;
    global $hand;

    debug('file %s', $hh_file);

    $file = new StdClass();

    $file->file = array(
        'hh_dir' => $ignition_hh_dir,
        'account_dir' => $account_dir,
        'hh_file' => $hh_file,
        'full_path' => $ignition_hh_dir . '/' . $account_dir . '/' . $hh_file,
    );

    $file->account['id'] = $account_dir;

    $REGEX_FILENAME = '/^HH(?<year>\d{4})(?<month>\d{2})(?<day>\d{2})-(?<hour>\d{2})(?<minute>\d{2})(?<second>\d{2}) - (?<id>\d+) - (?<format>[A-Z]+) - \$(?<sb>[0-9.]+)-\$(?<bb>[0-9.]+) - (?<game>[A-Z]+) - (?<limit>[A-Z]+) - TBL No.(?<table>\d+)\.txt$/';
    if (! preg_match($REGEX_FILENAME, $hh_file, $file->history))
        return; // ignore file

    if (($file->fh = @fopen($file->file['full_path'], 'r')) === FALSE)
        die('failed to open file ' . $file->file['full_path']);

    $file->lineno = 0;
    $file->handno = 0;
    $file->state = STATE_INIT;
    $file->rerun = false;
    $file->eof = false;

    while ($file->rerun || ($file->line = fgets($file->fh)) || ($file->eof = feof($file->fh))) {
        if ($file->eof && $file->state != STATE_SUMMARY) {
            $file->error = 'Unexpected end of file';
            break;
        }

        if ($file->eof || $file->rerun) { // Processing the same line after a state change.
            $file->rerun = false;
        } else {
            $file->line = trim($file->line);
            $file->lineno++;

            if (check_state_change($file))
                continue;
        }

        /* Ignored lines */

        if (! $file->eof) {
            if ($file->line == '')
                continue;

            $REGEX_IGNORE = array(
                '/^((?<player>.*) : )?Table enter user$/',
                '/^((?<player>.*) : )?Table leave user$/',
                '/^((?<player>.*) : )?Seat sit down$/',
                '/^((?<player>.*) : )?Seat sit out$/',
                '/^((?<player>.*) : )?Seat stand$/',
                '/^((?<player>.*) : )?Seat re-join$/',
                '/^((?<player>.*) : )?Table deposit (?<chips>\$[0-9.]+)$/',
            );

            $ignore = false;
            foreach ($REGEX_IGNORE as $regex) {
                if (preg_match($regex, $file->line)) {
                    $ignore = true;
                    break;
                }
            }
            if ($ignore)
                continue;
        }

        switch ($file->state) {
        case STATE_INIT:
            if (! empty($file->previous_state)) {
                /* Process previous hand */
                if (! process_hand($hand, $file)) {
                    $file->error = 'Failed to process hand #' . $file->handno;
                    break;
                }
            }

            if ($file->eof)
                break;

            $hand = new StdClass();

            $REGEX_INIT = '/^(?<casino>Ignition) Hand #(?<id>\d+) TBL#(?<table>\d+) (?<game>[A-Z]+) (?<limit>[^-]+) - (?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2}) (?<hour>\d{2}):(?<minute>\d{2}):(?<second>\d{2})$/';   
            if (! preg_match($REGEX_INIT, $file->line, $hand->info)) {
                $file->error = 'Expecting init line';
                break;
            }

            $file->handno++;
            break;

        case STATE_SEATS:
            $REGEX_SEAT = '/^Seat (?<seatno>\d+): (?<player>.*?)(?<me> \[ME\])? \(\$(?<chips>[0-9.]+) in chips\)$/';
            if (preg_match($REGEX_SEAT, $file->line, $hand->seats[]))
                break;

            $REGEX_DEALER = '/^((?<player>.*?)(?<me>  \[ME\])? : )?Set dealer( \[(?<seatno>\d+)\])?$/';
            if (empty($hand->dealer) && preg_match($REGEX_DEALER, $file->line, $hand->dealer))
                break;

            $REGEX_SB = '/^(?<player>.*?)(?<me>  \[ME\])? : (?<blind>Small Blind) \$(?<chips>[0-9.]+)$/';
            if (empty($hand->posts['sb']) && preg_match($REGEX_SB, $file->line, $hand->posts['sb']))
                break;

            $REGEX_BB = '/^(?<player>.*?)(?<me>  \[ME\])? : (?<blind>Big blind) \$(?<chips>[0-9.]+)$/';
            if (empty($hand->posts['bb']) && preg_match($REGEX_BB, $file->line, $hand->posts['bb']))
                break;

            $REGEX_SITOUT = '/^(?<player>.*?)(?<me>  \[ME\])? : Sitout \(wait for bb\)$/';
            if (preg_match($REGEX_SITOUT, $file->line, $hand->posts['sitout'][]))
                break;

            $REGEX_RETURN_PRE = '/^(?<player>.*?)(?<me>  \[ME\])? : Return uncalled blind \$(?<chips>[0-9.]+)$/';
            if (preg_match($REGEX_RETURN_PRE, $file->line, $hand->posts['return'][]))
                break;

            $REGEX_POST = '/^(?<player>.*?)(?<me>  \[ME\])? : Posts( (?<dead>dead))? chip \$(?<chips>[0-9.]+)$/';
            if (preg_match($REGEX_POST, $file->line, $hand->posts['other'][]))
                break;

            $file->error = 'Expecting one Seat, Dealer, Small Blind or Big Blind line, and multiple Posts lines';
            break;

        case STATE_CARDS: // PREFLOP

            // Small Blind  [ME] : Card dealt to a spot [4c 4d] 
            $REGEX_DEALT = '/^(?<player>.*?)(?<me>  \[ME\])? : Card dealt to a spot \[(?<card1>[2-9TJQKA][cdhs]) (?<card2>[2-9TJQKA][cdhs])\]$/';
            if (preg_match($REGEX_DEALT, $file->line, $hand->preflop['hole_cards'][]))
                break;

            if (! parse_action($hand, $file))
                break;
         
            break;

        case STATE_FLOP:
            $REGEX_FLOP = '/^\*\*\* FLOP \*\*\* \[([2-9TJQKA][cdhs]) ([2-9TJQKA][cdhs]) ([2-9TJQKA][cdhs])\]$/';
            if (empty($hand->flop['cards'])) {
                if (! preg_match($REGEX_FLOP, $file->line, $hand->flop['cards']))
                    $file->error = 'Expecting FLOP line';
                break;
            }

            if (! parse_action($hand, $file))
                break;
         
            break;

        case STATE_TURN:
            $REGEX_TURN = '/^\*\*\* TURN \*\*\* \[[2-9TJQKA][cdhs] [2-9TJQKA][cdhs] [2-9TJQKA][cdhs]\] \[([2-9TJQKA][cdhs])\]$/';
            if (empty($hand->turn['cards'])) {
               if (! preg_match($REGEX_TURN, $file->line, $hand->turn['cards'])) 
                    $file->error = 'Expecting TURN line';
                break;
            }

            if (! parse_action($hand, $file))
                break;
         
            break;

        case STATE_RIVER:
            $REGEX_RIVER = '/^\*\*\* RIVER \*\*\* \[[2-9TJQKA][cdhs] [2-9TJQKA][cdhs] [2-9TJQKA][cdhs] [2-9TJQKA][cdhs]\] \[([2-9TJQKA][cdhs])\]$/';
            if (empty($hand->river['cards'])) {
               if (! preg_match($REGEX_RIVER, $file->line, $hand->river['cards']))
                    $file->error = 'Expecting RIVER line';
                break;
            }

            if (! parse_action($hand, $file))
                break;
         
            break;

        // Total Pot($0.90)
        // Board [8d 4h Ah Th 6s]
        // Seat+1: Dealer Folded before the FLOP
        // Seat+4: Small Blind Folded on the RIVER
        // Seat+5: Big Blind $0.86 [Does not show]  
        case STATE_SUMMARY:
            if ($file->previous_state == STATE_SEATS) {
                // abort mission, this hand ended before cards were dealt
                $file->previous_state = null;
                $file->state = STATE_INIT;
            }

            $REGEX_TOTAL = '/^Total Pot\(\$(?<chips>[0-9.]+)\)$/';
            if (empty($hand->summary['pot']) && preg_match($REGEX_TOTAL, $file->line, $hand->summary['pot']))
                break;

            $REGEX_BOARD = '/^Board \[([2-9TJQKA][cdhs]) ([2-9TJQKA][cdhs]) ([2-9TJQKA][cdhs]) ([2-9TJQKA][cdhs])? ([2-9TJQKA][cdhs])?\]$/';
            if (empty($hand->summary['board']) && preg_match($REGEX_BOARD, $file->line, $hand->summary['board']))
                break;

            $REGEX_FOLDED = '/^Seat\+(?<seatno>\d+): (?<player>.*?) (?<action_full>(?<action>Folded) (?<street>(on|before) the (FLOP|TURN|RIVER)))$/';
            if (preg_match($REGEX_FOLDED, $file->line, $hand->summary['seats'][]))
                break;

            $REGEX_MUCKED = '/^Seat\+(?<seatno>\d+): (?<player>.*?) \[(?<action>Mucked)\] \[(?<card1>[2-9TJQKA][cdhs]) (?<card2>[2-9TJQKA][cdhs])\]$/';
            if (preg_match($REGEX_MUCKED, $file->line, $hand->summary['seats'][]))
                break;

            $REGEX_WON = '/^Seat\+(?<seatno>\d+): (?<player>.*?) \$(?<chips>[0-9.]+) (\[Does not show\]| with (?<ranking>.*) \[(?<hand>[^[]*)\])$/';
            if (preg_match($REGEX_WON, $file->line, $hand->summary['seats'][]))
                break;

            break;
        }

        if ($file->eof || ! empty($file->error))
            break;
    };

    fclose($file->fh);

    if (! empty($file->error)) {
        err("<<< %s\nERR %s\nFile %s (Line %d)", $file->line, $file->error, $file->file['full_path'], $file->lineno);
        exit(1);
    }
}

function process_ignition_hh_dir($ignition_hh_dir)
{
    debug('ignition_hh_dir %s', $ignition_hh_dir);

    if (! is_dir($ignition_hh_dir) || ! is_readable($ignition_hh_dir)) {
        err("%s does not exist or is not a directory", $ignition_hh_dir);
        exit(1);
    }

    if ($dh = opendir($ignition_hh_dir)) {
        while (($account_dir = readdir($dh)) !== false) {
            if (! is_dir($ignition_hh_dir . '/' . $account_dir))
                continue;

            if (! preg_match('/^\d+$/', $account_dir))
                continue;

            process_account_dir($ignition_hh_dir, $account_dir);
        }

        closedir($dh);
    }
}

// e.g. HH20190304-161444 - 6484717 - RING - $0.02-$0.05 - HOLDEM - NL - TBL No.17948084.txt

function process_account_dir($ignition_hh_dir, $account_dir)
{
    global $file;

    debug('account_dir %s/%s', $ignition_hh_dir, $account_dir);

    if ($dh = opendir($ignition_hh_dir . '/' . $account_dir)) {
        while (($hh_file = readdir($dh)) !== false) {
            $full_path = $ignition_hh_dir . '/' . $account_dir . '/' . $hh_file;

            if (! is_file($full_path) || ! is_readable($full_path))
                continue;

            if (! preg_match('/^\d+$/', $account_dir))
                continue;

            process_file($ignition_hh_dir, $account_dir, $hh_file);
        }

        closedir($dh);
    }
}

$file = new StdClass();
$hand = new StdClass();

$ignition_hh_dir = getenv("HOME") . '/Ignition Casino Poker/Hand History';

process_ignition_hh_dir($ignition_hh_dir);

