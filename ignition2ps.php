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

class Ignition2Ps
{
    protected $hand;

    public function __construct($ignition_hh_dir)
    {
        $this->ignition_hh_dir = $ignition_hh_dir;
    }

    /**
     * Process entire Igntion hand history directory
     */
    public function processIgnitionHh()
    {
        if (! is_dir($this->ignition_hh_dir) || ! is_readable($this->ignition_hh_dir))
            throw new Exception($this->ignition_hh_dir . " does not exist or is not a directory");

        if ($dh = opendir($this->ignition_hh_dir)) {
            while (($account_dir = readdir($dh)) !== false) {
                if (! is_dir($this->ignition_hh_dir . '/' . $account_dir))
                    continue;

                if (! preg_match('/^\d+$/', $account_dir))
                    continue;

                $this->processAccountDir($account_dir);
            }

            closedir($dh);
        }
    }

    /**
     * Process Igntion hand history account directory
     */
    protected function processAccountDir($account_dir)
    {
        $this->account_dir = $account_dir;

        if ($dh = opendir($this->ignition_hh_dir . '/' . $account_dir)) {
            while (($hh_file = readdir($dh)) !== false) {
                $full_path = $this->ignition_hh_dir . '/' . $account_dir . '/' . $hh_file;

                if (! is_file($full_path) || ! is_readable($full_path))
                    continue;

                if (! preg_match('/^\d+$/', $account_dir))
                    continue;

                $this->processFile($hh_file);
            }

            closedir($dh);
        }
    }

    /**
     * Process individual Ignition hand history file
     */
    protected function processFile($hh_file)
    {
        debug('file %s', $hh_file);

        $this->hh_file = $hh_file;

        $file = new IgnitionHhFile($hh_file, $this);

        try {
            if (! $file->open()) // Not an Ignition HH file, ignore
                return;

            while ($out = $file->convertHand()) {
                echo $out;
            }
        } catch (IgnitionHhFileException $e) {
            throw new Exception(sprintf("%s in file %s on line %d: %s", $e->getMessage(), $file->file['full_path'], $file->lineno, $file->line));
        } catch (Ignition2PsHandException $e) {
            throw new Exception(sprintf("%s in file %s on line %d", $e->getMessage(), $file->file['full_path'], $file->handlineno));
        }
    }
}

class IgnitionHhFileException extends Exception {}

class IgnitionHhFile
{
    const STATE_INIT = 'init';
    const STATE_SEATS = 'seats';
    const STATE_CARDS = 'cards';
    const STATE_FLOP = 'flop';
    const STATE_TURN = 'turn';
    const STATE_RIVER = 'river';
    const STATE_SUMMARY = 'summary';

    protected $state_str = array(
        self::STATE_INIT => 'Ignition Hand ',
        self::STATE_SEATS => 'Seat ',
        self::STATE_CARDS => '*** HOLE CARDS ***',
        self::STATE_FLOP => '*** FLOP ***',
        self::STATE_TURN => '*** TURN ***',
        self::STATE_RIVER => '*** RIVER ***',
        self::STATE_SUMMARY => '*** SUMMARY ***',
    );

    protected $state_tree = array(
        self::STATE_INIT => array(
            self::STATE_SEATS,
        ),
        self::STATE_SEATS => array(
            self::STATE_CARDS,
            self::STATE_SUMMARY,
        ),
        self::STATE_CARDS => array(
            self::STATE_FLOP,
            self::STATE_SUMMARY
        ),
        self::STATE_FLOP => array(
            self::STATE_TURN,
            self::STATE_SUMMARY,
        ),
        self::STATE_TURN => array(
            self::STATE_RIVER,
            self::STATE_SUMMARY,
        ),
        self::STATE_RIVER => array(
            self::STATE_SUMMARY,
        ),
        self::STATE_SUMMARY => array(
            self::STATE_INIT,
        ),
    );

    public function __construct($hh_file, $ignition2ps)
    {
        $this->file = array(
            'hh_dir' => $ignition2ps->ignition_hh_dir,
            'account_dir' => $ignition2ps->account_dir,
            'hh_file' => $hh_file,
            'full_path' => $ignition2ps->ignition_hh_dir . '/' . $ignition2ps->account_dir . '/' . $hh_file,
        );

        $this->lineno = 0;
        $this->handno = 0;
        $this->state = self::STATE_INIT;
        $this->rerun = false;
        $this->eof = false;

        $this->account['id'] = $ignition2ps->account_dir;
    }

    /**
     * Get Igntion hand history file account ID
     */
    public function getAccountId()
    {
        return $this->account['id'];
    }

    /**
     * Update Igntion hand history file parser state
     *
     * Look for state change lines, check if state can be changed, change state and mark line for
     * rerun when needed.
     */
    protected function updateState()
    {
        if (! array_key_exists($this->state, $this->state_tree))
            return false;

        foreach ($this->state_tree[$this->state] as $state) {
            if (strpos($this->line, $this->state_str[$state]) === 0) {
                $this->previous_state = $this->state;
                $this->state = $state;
                if (strlen($this->line) > strlen($this->state_str[$state]))
                    $this->rerun = true; // There'll be additional info on this line
                return true;
            }
        }

        return false;
    }

    /**
     * Parse Igntion hand street action lines
     */
    function parse_action($hand)
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
            self::STATE_CARDS => 'preflop',
            self::STATE_FLOP => 'flop',
            self::STATE_TURN => 'turn',
            self::STATE_RIVER => 'river',
        );
        $prop_name = $state_property[$this->state];
        if (! isset($hand->$prop_name))
            $hand->$prop_name = array();
        $prop = &$hand->$prop_name;

        foreach ($action_regexs as $regex) {
            if (preg_match($regex, $this->line, $prop['action'][])) {
                return true;
            }
        }

        throw new IgnitionHhFileException('Unexpected action line');
    }

    public function open()
    {
        $REGEX_FILENAME = '/^HH(?<year>\d{4})(?<month>\d{2})(?<day>\d{2})-(?<hour>\d{2})(?<minute>\d{2})(?<second>\d{2}) - (?<id>\d+) - (?<format>[A-Z]+) - \$(?<sb>[0-9.]+)-\$(?<bb>[0-9.]+) - (?<game>[A-Z]+) - (?<limit>[A-Z]+) - TBL No.(?<table>\d+)\.txt$/';
        if (! preg_match($REGEX_FILENAME, $this->file['hh_file'], $this->history))
            return false; // ignore file

        if (($this->fh = @fopen($this->file['full_path'], 'r')) === FALSE)
            throw new Exception('Failed to open file ' . $this->file['full_path']);

        return true;
    }

    /**
     * Process Ignition hand history file
     */
    public function convertHand() 
    {
        while ($this->rerun || ($this->line = fgets($this->fh)) || ($this->eof = feof($this->fh))) {
            if ($this->eof && $this->state != self::STATE_SUMMARY)
                throw new IgnitionHhFileException('Unexpected end of file');

            if ($this->eof || $this->rerun) { // Processing the same line after a state change.
                $this->rerun = false;
            } else {
                $this->line = trim($this->line);
                $this->lineno++;

                if ($this->updateState())
                    continue;
            }

            /* Ignored lines */

            if (! $this->eof) {
                if ($this->line == '')
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
                    if (preg_match($regex, $this->line)) {
                        $ignore = true;
                        break;
                    }
                }
                if ($ignore)
                    continue;
            }

            switch ($this->state) {
            case self::STATE_INIT:
                if (! empty($this->previous_state)) {
                    /* Process previous hand */
                    try {
                        $out = $hand->getPsHandText();

                        $this->previous_state = null;
                        $this->rerun = true;

                        return $out;
                    } catch (Exception $e) {
                        throw new Ignition2PsHandException('%s in hand #%s' . $e->getMessage(), $hand->info['id']);
                    }
                }

                if ($this->eof)
                    break;

                $hand = new Igntion2PsHand($this);

                $REGEX_INIT = '/^(?<casino>Ignition) Hand #(?<id>\d+) TBL#(?<table>\d+) (?<game>[A-Z]+) (?<limit>[^-]+) - (?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2}) (?<hour>\d{2}):(?<minute>\d{2}):(?<second>\d{2})$/';   
                if (! preg_match($REGEX_INIT, $this->line, $hand->info))
                    throw new IgnitionHhFileException('Expecting init line');

                $this->handno++;
                $this->handlineno = $this->lineno;
                break;

            case self::STATE_SEATS:
                $REGEX_SEAT = '/^Seat (?<seatno>\d+): (?<player>.*?)(?<me> \[ME\])? \(\$(?<chips>[0-9.]+) in chips\)$/';
                if (preg_match($REGEX_SEAT, $this->line, $hand->seats[]))
                    break;

                $REGEX_DEALER = '/^((?<player>.*?)(?<me>  \[ME\])? : )?Set dealer( \[(?<seatno>\d+)\])?$/';
                if (empty($hand->dealer) && preg_match($REGEX_DEALER, $this->line, $hand->dealer))
                    break;

                $REGEX_SB = '/^(?<player>.*?)(?<me>  \[ME\])? : (?<blind>Small Blind) \$(?<chips>[0-9.]+)$/';
                if (empty($hand->posts['sb']) && preg_match($REGEX_SB, $this->line, $hand->posts['sb']))
                    break;

                $REGEX_BB = '/^(?<player>.*?)(?<me>  \[ME\])? : (?<blind>Big blind) \$(?<chips>[0-9.]+)$/';
                if (empty($hand->posts['bb']) && preg_match($REGEX_BB, $this->line, $hand->posts['bb']))
                    break;

                $REGEX_SITOUT = '/^(?<player>.*?)(?<me>  \[ME\])? : Sitout \(wait for bb\)$/';
                if (preg_match($REGEX_SITOUT, $this->line, $hand->posts['sitout'][]))
                    break;

                $REGEX_RETURN_PRE = '/^(?<player>.*?)(?<me>  \[ME\])? : Return uncalled blind \$(?<chips>[0-9.]+)$/';
                if (preg_match($REGEX_RETURN_PRE, $this->line, $hand->posts['return'][]))
                    break;

                $REGEX_POST = '/^(?<player>.*?)(?<me>  \[ME\])? : Posts( (?<dead>dead))? chip \$(?<chips>[0-9.]+)$/';
                if (preg_match($REGEX_POST, $this->line, $hand->posts['other'][]))
                    break;

                throw new IgnitionHhFileException('Unexpected init section line');

            case self::STATE_CARDS: // PREFLOP

                // Small Blind  [ME] : Card dealt to a spot [4c 4d] 
                $REGEX_DEALT = '/^(?<player>.*?)(?<me>  \[ME\])? : Card dealt to a spot \[(?<card1>[2-9TJQKA][cdhs]) (?<card2>[2-9TJQKA][cdhs])\]$/';
                if (preg_match($REGEX_DEALT, $this->line, $hand->preflop['hole_cards'][]))
                    break;

                $this->parse_action($hand);
                break;

            case self::STATE_FLOP:
                $REGEX_FLOP = '/^\*\*\* FLOP \*\*\* \[([2-9TJQKA][cdhs]) ([2-9TJQKA][cdhs]) ([2-9TJQKA][cdhs])\]$/';
                if (empty($hand->flop['cards'])) {
                    if (! preg_match($REGEX_FLOP, $this->line, $hand->flop['cards']))
                        throw new IgnitionHhFileException('Expecting FLOP line');
                    break;
                }

                $this->parse_action($hand);
                break;

            case self::STATE_TURN:
                $REGEX_TURN = '/^\*\*\* TURN \*\*\* \[[2-9TJQKA][cdhs] [2-9TJQKA][cdhs] [2-9TJQKA][cdhs]\] \[([2-9TJQKA][cdhs])\]$/';
                if (empty($hand->turn['cards'])) {
                if (! preg_match($REGEX_TURN, $this->line, $hand->turn['cards'])) 
                        throw new IgnitionHhFileException('Expecting TURN line');
                    break;
                }

                $this->parse_action($hand);
                break;

            case self::STATE_RIVER:
                $REGEX_RIVER = '/^\*\*\* RIVER \*\*\* \[[2-9TJQKA][cdhs] [2-9TJQKA][cdhs] [2-9TJQKA][cdhs] [2-9TJQKA][cdhs]\] \[([2-9TJQKA][cdhs])\]$/';
                if (empty($hand->river['cards'])) {
                    if (! preg_match($REGEX_RIVER, $this->line, $hand->river['cards']))
                        throw new IgnitionHhFileException('Expecting RIVER line');
                    break;
                }

                $this->parse_action($hand);
                break;

            // Total Pot($0.90)
            // Board [8d 4h Ah Th 6s]
            // Seat+1: Dealer Folded before the FLOP
            // Seat+4: Small Blind Folded on the RIVER
            // Seat+5: Big Blind $0.86 [Does not show]  
            case self::STATE_SUMMARY:
                if ($this->previous_state == self::STATE_SEATS) {
                    // abort mission, this hand ended before cards were dealt
                    $this->previous_state = null;
                    $this->state = self::STATE_INIT;
                }

                $REGEX_TOTAL = '/^Total Pot\(\$(?<chips>[0-9.]+)\)$/';
                if (empty($hand->summary['pot']) && preg_match($REGEX_TOTAL, $this->line, $hand->summary['pot']))
                    break;

                $REGEX_BOARD = '/^Board \[([2-9TJQKA][cdhs]) ([2-9TJQKA][cdhs]) ([2-9TJQKA][cdhs]) ([2-9TJQKA][cdhs])? ([2-9TJQKA][cdhs])?\]$/';
                if (empty($hand->summary['board']) && preg_match($REGEX_BOARD, $this->line, $hand->summary['board']))
                    break;

                $REGEX_FOLDED = '/^Seat\+(?<seatno>\d+): (?<player>.*?) (?<action_full>(?<action>Folded) (?<street>(on|before) the (FLOP|TURN|RIVER)))$/';
                if (preg_match($REGEX_FOLDED, $this->line, $hand->summary['seats'][]))
                    break;

                $REGEX_MUCKED = '/^Seat\+(?<seatno>\d+): (?<player>.*?) \[(?<action>Mucked)\] \[(?<card1>[2-9TJQKA][cdhs]) (?<card2>[2-9TJQKA][cdhs])\]$/';
                if (preg_match($REGEX_MUCKED, $this->line, $hand->summary['seats'][]))
                    break;

                $REGEX_WON = '/^Seat\+(?<seatno>\d+): (?<player>.*?) \$(?<chips>[0-9.]+) (\[Does not show\]| with (?<ranking>.*) \[(?<hand>[^[]*)\])$/';
                if (preg_match($REGEX_WON, $this->line, $hand->summary['seats'][]))
                    break;

                break;
            }

            if ($this->eof) {
                fclose($this->fh);
                break;
            }
        }
    }
}

// Exceptions specific to hand conversion process
class Ignition2PsHandException extends Exception {}

/**
 * Igntion Casino to Poker Stars conversion
 */
class Igntion2PsHand
{
    const STREET_PREFLOP = 'preflop';
    const STREET_FLOP = 'flop';
    const STREET_TURN = 'turn';
    const STREET_RIVER = 'river';
 
    // Igntion to Poker Stars hand summary translation
    private $tr_summary_action = array(
        'Folded before the FLOP' => 'folded before Flop',
        'Folded on the FLOP' => 'folded on the Flop',
        'Folded on the TURN' => 'folded on the Turn',
        'Folded on the RIVER' => 'folded on the River',
    );

    // Hand's parent file
    protected $file;
 
    // All hand action across all streets
    private $all_action;

    // Player name to seat record association, mainly for hole cards
    private $player_to_seat;

    public function __construct(IgnitionHhFile $file)
    {
        $this->file = $file;
    }

    public function getFileAccountId()
    {
        return $this->file->getAccountId();
    }

    /**
     * Get hand action across all streets
     */
    protected function getAllAction()
    {
        if (! isset($this->all_action)) {
            $this->all_action = array_merge($this->preflop['action'], empty($this->flop['action']) ? array() : $this->flop['action'], empty($this->turn['action']) ? array() : $this->turn['action'], empty($this->river['action']) ? array() : $this->river['action']);
        }

        return $this->all_action;
    }

    /**
     * Get spcific player's Seat record
     */
    protected function getPlayerSeat($player)
    {
        if (! isset($this->player_to_seat)) {
            foreach ($this->preflop['hole_cards'] as $seat) {
                if (empty($seat))
                    continue;
                $this->player_to_seat[$seat['player']] = $seat;
            }
        }

        return  $this->player_to_seat[$player];
    }

    /**
     * Generate Poker Stars hand initial line
     */
    protected function getHandInitText()
    {
        $str_casino = 'PokerStars';
        $str_handid_prefix = '99999';

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

        $time_format = '%Y/%m/%d %H:%M:%S';
        $out = '';

        $timestamp = mktime($this->info['hour'], $this->info['minute'], $this->info['second'],
                            $this->info['month'], $this->info['day'], $this->info['year']);
        $aest_time = strftime($time_format, $timestamp);
        $et_time = strftime($time_format, $timestamp - (15 * 60 * 60));

        $out .= sprintf("%s Hand #%d%d:  %s %s ($%.02f/$%.02f USD) - %s AEST [%s ET]\n", $str_casino, $str_handid_prefix, $this->info['id'], $tr_game[$this->info['game']], $tr_limit[$this->info['limit']], $this->file->history['sb'], $this->file->history['bb'], $aest_time, $et_time);
        
        return $out;
    }

    /**
     * Get Dealer's seat number. If empty, use first free seat's number.
     */
    protected function getDealerSeatno()
    {
        if (! isset($this->dealer['seatno'])) {
            // Find first empty seat, that'll be the dealer.
            $i = 1;
            foreach ($this->seats as $seat) {
                if (empty($seat))
                    continue;

                if ($seat['seatno'] != $i) { // Found a gap
                    $this->dealer['seatno'] = $i;
                    break;
                }

                $i++;
            }

            if (! isset($this->dealer['seatno'])) // Use last
                $this->dealer['seatno'] = $i;
        }

        return $this->dealer['seatno'];
    }

    /**
     * Generate Poker Stars hand table and dealer line
     */
    protected function getTableText()
    {
        $str_table_prefix = 'Ignition';
        $str_table_size = '6-max'; // table size missing in ignition (or RING?)

        $out = '';

        $button_seat = '';
        if ($dealer_seatno = $this->getDealerSeatno())
            $button_seat = sprintf(" Seat #%d is the button", $dealer_seatno);
        $out .= sprintf("Table '%s%s' %s%s\n", $str_table_prefix, $this->info['table'], $str_table_size, $button_seat);

        return $out;
    }

    /**
     * Get seat record of the [ME] player
     */
    protected function getMePlayer()
    {
        if (! isset($this->me_player)) {
            foreach ($this->preflop['hole_cards'] as $player) {
                if (! empty($player['me'])) {
                    $this->me_player = $player;
                    break;
                }
            }
        }
        return $this->me_player;
    }

    /**
     * Return player name with [ME] player's converted to Ignition<ID>
     */
    protected function me($player)
    {
        $str_id_prefix = 'Ignition';

        $me = $this->getMePlayer();
        if ($player == $me['player'])
            $player = $str_id_prefix . $this->getFileAccountId();

        return $player;
    }

    /**
     * Generate Poker Stars seats lines and blind post lines
     */
    protected function getSeatsBlindsText()
    {
        $out = '';

        foreach ($this->seats as $seat) {
            if (empty($seat))
                continue;
            $out .= sprintf("Seat %d: %s ($%.02f in chips)\n", $seat['seatno'], $this->me($seat['player']), $seat['chips']);
        }

        if (! empty($this->posts['sb']))
            $out .= sprintf("%s: posts small blind $%.02f\n", $this->me($this->posts['sb']['player']), $this->posts['sb']['chips']);

        $out .= sprintf("%s: posts big blind $%.02f\n", $this->me($this->posts['bb']['player']), $this->posts['bb']['chips']);

        return $out;
    }

    /**
     * Generate Poker Stars hand hole cards (and preflop action) section
     * XXX: Try to include hole cards for all players
     */
    protected function getHoleCardsText()
    {
        $out = '';

        $out .= sprintf("*** HOLE CARDS ***\n");

        $player = $this->getMePlayer();
        $out .= sprintf("Dealt to %s [%s %s]\n", $this->me($player['player']), $player['card1'], $player['card2']);

        $out .= $this->getActionsText(self::STREET_PREFLOP);

        return $out;
    }

    /**
     * Generate Poker Stars hand street action lines
     */
    protected function getActionsText($street)
    {
        $out = '';

        $tr_action = array(
            'Folds' => 'folds',
            'Checks' => 'checks',
            'Calls' => 'calls',
            'Bets' => 'bets',
            'Raises' => 'raises',
            'All-in(bet)' => 'bets',
            'All-in(call)' => 'calls',
            'All-in(raise)' => 'raises',
            'Return uncalled portion of bet' => '',
        );

        $last_bet_size = 0;
        if ($street == self::STREET_PREFLOP)
            $last_bet_size = $this->posts['bb']['chips'];

        foreach ($this->{$street}['action'] as $action) {
            if (empty($action['action']))
                continue;

            if (in_array($action['action'], array('Folds', 'Checks'))) {
                $out .= sprintf("%s: %s\n", $this->me($action['player']), $tr_action[$action['action']]);

            } elseif (in_array($action['action'], array('Bets', 'Calls'))) {
                $out .= sprintf("%s: %s $%.2f\n", $this->me($action['player']), $tr_action[$action['action']], $action['chips']);
                if ($action['action'] == 'Bets')
                    $last_bet_size = $action['chips'];

            } elseif (in_array($action['action'], array('Raises', 'All-in(raise)'))) {
                $out .= sprintf("%s: %s $%.2f to $%.2f%s\n", $this->me($action['player']), $tr_action[$action['action']], $action['chips'], $action['to_chips'], ($action['action'] == 'Raises' ? '' : ' and is all-in'));

                $last_bet_size = $action['to_chips'];

            } elseif (in_array($action['action'], array('All-in'))) {
                if ($last_bet_size == 0)
                    $allin_action = 'bets';
                elseif ($action['chips'] <= $last_bet_size)
                    $allin_action = 'calls';
                else
                    $allin_action = 'raises';

                $out .= sprintf("%s: %s $%.2f and is all-in\n", $this->me($action['player']), $allin_action, $action['chips']);
                $last_bet_size = $action['chips'];

            } elseif ($action['action'] == 'Return') {
                $out .= sprintf("Uncalled bet ($%.02f) returned to %s\n", $action['chips'], $this->me($action['player']));
            } else {
                // Ignore everything else
            }
        }

        return $out;
    }

    protected function getFlopText()
    {
        $out = '';

        if (! empty($this->flop['cards'])) {
            $out .= sprintf("*** FLOP *** [%s %s %s]\n", $this->flop['cards'][1], $this->flop['cards'][2], $this->flop['cards'][3]);

            if (! empty($this->flop['action']))
                $out .= $this->getActionsText(self::STREET_FLOP);
        }

        return $out;
    }

    protected function getTurnText()
    {
        $out = '';

        if (! empty($this->turn['cards'])) {
            $out .= sprintf("*** TURN *** [%s %s %s] [%s]\n", $this->flop['cards'][1], $this->flop['cards'][2], $this->flop['cards'][3], $this->turn['cards'][1]);

            if (! empty($this->turn['action']))
                $out .= $this->getActionsText(self::STREET_TURN);
        }

        return $out;
    }

    protected function getRiverText()
    {
        $out = '';

        if (! empty($this->river['cards'])) {
            $out .= sprintf("*** RIVER *** [%s %s %s %s] [%s]\n", $this->flop['cards'][1], $this->flop['cards'][2], $this->flop['cards'][3], $this->turn['cards'][1], $this->river['cards'][1]);

            if (! empty($this->river['action']))
                $out .= $this->getActionsText(self::STREET_RIVER);
        }

        return $out;
    }

    /**
     * Generate Poker Stars hand showdown section
     */
    protected function getShowdownText()
    {
        $out = '';
        $has_showdown = false;
        $showdown = array();

        foreach ($this->getAllAction() as $action) {
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
                    foreach ($this->preflop['hole_cards'] as $seat) {
                        if ($seat['player'] == $action['player'])
                            break;
                    }

                    $out .= sprintf("%s: shows [%s %s] (%s)\n", $this->me($action['player']), $seat['card1'], $seat['card2'], $action['ranking']);

                } elseif ($action['action'] == 'Mucks') {
                    $out .= sprintf("%s: mucks hand\n", $this->me($action['player']));

                } elseif ($action['action'] == 'Hand result') {
                    $out .= sprintf("%s collected $%.02f from pot\n", $this->me($action['player']), $action['chips']);
                }
            }
        }

        return $out;
    }

    /**
     * Generate Poker Stars hand summary seat lines
     */
    protected function getSummarySeatsText()
    {
        $out = '';

        foreach ($this->summary['seats'] as $action) {
            if (empty($action))
                continue;

            $str_position = '';
            if (isset($tr_summary_position[$action['player']]))
                $str_position = ' (' . $tr_summary_position[$action['player']] . ')';

            if (empty($action['action'])) {
                $seat = $this->getPlayerSeat($action['player']);

                $won_with = '';
                if (! empty($action['ranking']))
                    $won_with = sprintf(' with %s', $action['ranking']);

                if (! $won_with) // make up something
                    $won_with = ' with a pair of Deuces';

                $result = sprintf('showed [%s %s] and won ($%.02f)%s', $seat['card1'], $seat['card2'], $action['chips'], $won_with);
            } elseif ($action['action'] == 'Folded') {
                $result = $this->tr_summary_action[$action['action_full']];
            } elseif ($action['action'] == 'Mucked') {
                $result = sprintf('mucked [%s %s]', $action['card1'], $action['card2']);
            }

            $out .= sprintf("Seat %d: %s%s %s\n", $action['seatno'], $this->me($action['player']), $str_position, $result);
        }

        return $out;
    }

    /**
     * Generate Poker Stars hand summary section
     */
    protected function getSummaryText()
    {
        $out = '';
        $all_action = $this->getAllAction();

        $out .= "*** SUMMARY ***\n";

        $result_pot = 0;
        $total_pot = $this->summary['pot']['chips'];
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
        $out .= sprintf("Total pot $%.02f | Rake $%.02f\n", $this->summary['pot']['chips'], $rake);

        if (! empty($this->summary['board']))
            $out .= sprintf("Board [%s]\n", implode(' ', array_slice($this->summary['board'], 1, 5)));

        $out .= $this->getSummarySeatsText();

        return $out;
    }

    /**
     * Use collected Igntion Casino hand data to generate Poker Stars hand text
     */
    public function getPsHandText()
    {
        debug('file %s', $this->file->file['full_path']);
        debug('line %d', $this->file->lineno);
        debug('hand %s', $this->info['id']);

        $out = '';

        $tr_summary_position = array(
            'Dealer' => 'button',
            'Big Blind' => 'big blind',
            'Small Blind' => 'small blind',
        );

        $out .= $this->getHandInitText();

        $out .= $this->getTableText();

        $out .= $this->getSeatsBlindsText();

        $out .= $this->getHoleCardsText();

        $out .= $this->getFlopText();

        $out .= $this->getTurnText();

        $out .= $this->getRiverText();

        $out .= $this->getShowdownText();

        $out .= $this->getSummaryText();

        return $out . "\n";
    }
}

$ignition_hh_dir = getenv("HOME") . '/Ignition Casino Poker/Hand History';

$ignition2ps = new Ignition2Ps($ignition_hh_dir);
try {
    $ignition2ps->processIgnitionHh();
} catch (Exception $e) {
    err($e->getMessage());
    exit(1);
}

exit(0);
