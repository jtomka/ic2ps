<?php

class IcParser extends Base implements ParserInterface
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

    const STREET_FLOP = 'flop';
    const STREET_TURN = 'turn';
    const STREET_RIVER = 'river';

    protected $filename;

    // Open file descriptor
    protected $fh;

    // Game information parsed from file name
    protected $info;

    // Current line number in file
    protected $line;

    // Current line number in file
    protected $lineno;

    // Current hand overall
    protected $handno;

    // Hand parser state
    protected $state;

    // Flag for parser to re-process current line
    protected $rerun;

    // End-of-file flag for current file
    protected $eof;

    // Object where parser collects current hand's data, to be then converted
    protected $hand;

    public function __construct()
    {
        $this->initObject();
    }

    protected function initObject()
    {
        $this->line = null;
        $this->lineno = 0;
        $this->handno = 0;
        $this->state = self::STATE_INIT;
        $this->rerun = false;
        $this->eof = false;
    }
    
    public function getFilename()
    {
        return $this->filename;
    }

    public function getLine()
    {
        return $this->line;
    }

    public function getLineNo()
    {
        return $this->lineno;
    }

    /**
     * Update Ignition hand history file parser state
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

    protected function skipIgnoredLine()
    {
        $REGEX_IGNORE = array(
            '/^((?<player>.*) : )?Table enter user$/',
            '/^((?<player>.*) : )?Table leave user$/',
            '/^((?<player>.*) : )?Seat sit down$/',
            '/^((?<player>.*) : )?Seat sit out$/',
            '/^((?<player>.*) : )?Seat stand$/',
            '/^((?<player>.*) : )?Seat re-join$/',
            '/^((?<player>.*) : )?Table deposit (?<chips>\$[0-9.]+)$/',
        );

        if ($this->eof)
            return false;

        if ($this->line == '')
            return true;

        foreach ($REGEX_IGNORE as $regex) {
            if (preg_match($regex, $this->line))
                return true;
        }

        return false;
    }

    /**
     * Parse Ignition hand street action lines
     */
    function parseAction()
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

        foreach ($action_regexs as $regex) {
            if (preg_match($regex, $this->line, $this->hand->{$prop_name}['action'][])) {
                return true;
            }
        }

        throw new ParserException('Unexpected action line');
    }

    protected function parseStreet($street, $regex)
    {
        if (empty($this->hand->{$street}['cards'])) {
            if (! preg_match($regex, $this->line, $this->hand->{$street}['cards']))
                throw new ParserException(sprintf('Expecting %s line', strtoupper($street)));
            return;
        }

        $this->parseAction();
    }

    public function setFilename() 
    {
        if (($this->fh = @fopen($filename(), 'r')) === FALSE)
            throw new ParserException('Failed to open file ' . $filename);

        $this->filename = $filename;
        $this->initObject();
    }

    /**
     * Parse Ignition hand history file and return next hand converted to Poker Stars format
     */
    public function parseNextHand() 
    {
        if (! is_resource($this->fh))
            throw ParserException('No file set to parse');

        while ($this->rerun || ($this->line = fgets($this->fh)) || ($this->eof = feof($this->fh))) {
            if ($this->eof && $this->state != self::STATE_SUMMARY)
                throw new ParserException('Unexpected end of file');

            if ($this->eof || $this->rerun) { // Processing same line after a state change.
                $this->rerun = false;
            } else {
                $this->line = trim($this->line);
                $this->lineno++;

                if ($this->updateState())
                    continue;
            }

            if ($this->skipIgnoredLine())
                continue;

            switch ($this->state) {


            case self::STATE_INIT:

                if (! empty($this->previous_state)) {
                    try {
                        $this->previous_state = null;
                        $this->rerun = true;

                        return $this->hand;

                    } catch (Exception $e) {
                        throw new HandException('%s in hand #%s' . $e->getMessage(), $this->hand->info['id']);
                    }
                }

                // EOF in the correct spot
                if ($this->eof)
                    break;

                $this->hand = new Hand();

                $REGEX_INIT = '/^(?<casino>Ignition) Hand #(?<id>\d+) TBL#(?<table>\d+) (?<game>[A-Z]+) (?<limit>[^-]+) - (?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2}) (?<hour>\d{2}):(?<minute>\d{2}):(?<second>\d{2})$/';   
                if (! preg_match($REGEX_INIT, $this->line, $this->hand->info))
                    throw new ParserException('Expecting init line');

                $this->handno++;

                break;


            case self::STATE_SEATS:

                $REGEX_SEAT = '/^Seat (?<seatno>\d+): (?<player>.*?)(?<me> \[ME\])? \(\$(?<chips>[0-9.]+) in chips\)$/';
                if (preg_match($REGEX_SEAT, $this->line, $this->hand->seats[]))
                    break;

                $REGEX_DEALER = '/^((?<player>.*?)(?<me>  \[ME\])? : )?Set dealer( \[(?<seatno>\d+)\])?$/';
                if (empty($this->hand->dealer) && preg_match($REGEX_DEALER, $this->line, $this->hand->dealer))
                    break;

                $REGEX_SB = '/^(?<player>.*?)(?<me>  \[ME\])? : (?<blind>Small Blind) \$(?<chips>[0-9.]+)$/';
                if (empty($this->hand->posts['sb']) && preg_match($REGEX_SB, $this->line, $this->hand->posts['sb']))
                    break;

                $REGEX_BB = '/^(?<player>.*?)(?<me>  \[ME\])? : (?<blind>Big blind) \$(?<chips>[0-9.]+)$/';
                if (empty($this->hand->posts['bb']) && preg_match($REGEX_BB, $this->line, $this->hand->posts['bb']))
                    break;

                $REGEX_SITOUT = '/^(?<player>.*?)(?<me>  \[ME\])? : Sitout \(wait for bb\)$/';
                if (preg_match($REGEX_SITOUT, $this->line, $this->hand->posts['sitout'][]))
                    break;

                $REGEX_RETURN_PRE = '/^(?<player>.*?)(?<me>  \[ME\])? : Return uncalled blind \$(?<chips>[0-9.]+)$/';
                if (preg_match($REGEX_RETURN_PRE, $this->line, $this->hand->posts['return'][]))
                    break;

                $REGEX_POST = '/^(?<player>.*?)(?<me>  \[ME\])? : Posts( (?<dead>dead))? chip \$(?<chips>[0-9.]+)$/';
                if (preg_match($REGEX_POST, $this->line, $this->hand->posts['other'][]))
                    break;

                throw new ParserException('Unexpected init section line');


            case self::STATE_CARDS: // PREFLOP

                // Small Blind  [ME] : Card dealt to a spot [4c 4d] 
                $REGEX_DEALT = '/^(?<player>.*?)(?<me>  \[ME\])? : Card dealt to a spot \[(?<card1>[2-9TJQKA][cdhs]) (?<card2>[2-9TJQKA][cdhs])\]$/';

                if (preg_match($REGEX_DEALT, $this->line, $this->hand->preflop['hole_cards'][]))
                    break;

                $this->parseAction();

                break;


            case self::STATE_FLOP:

                $REGEX_FLOP = '/^\*\*\* FLOP \*\*\* \[([2-9TJQKA][cdhs]) ([2-9TJQKA][cdhs]) ([2-9TJQKA][cdhs])\]$/';

                $this->parseStreet(self::STREET_FLOP, $REGEX_FLOP);

                break;


            case self::STATE_TURN:

                $REGEX_TURN = '/^\*\*\* TURN \*\*\* \[[2-9TJQKA][cdhs] [2-9TJQKA][cdhs] [2-9TJQKA][cdhs]\] \[([2-9TJQKA][cdhs])\]$/';

                $this->parseStreet(self::STREET_TURN, $REGEX_TURN);

                break;


            case self::STATE_RIVER:

                $REGEX_RIVER = '/^\*\*\* RIVER \*\*\* \[[2-9TJQKA][cdhs] [2-9TJQKA][cdhs] [2-9TJQKA][cdhs] [2-9TJQKA][cdhs]\] \[([2-9TJQKA][cdhs])\]$/';

                $this->parseStreet(self::STREET_RIVER, $REGEX_RIVER);

                break;


            case self::STATE_SUMMARY:

                if ($this->previous_state == self::STATE_SEATS) {
                    // abort mission, this hand ended before cards were dealt
                    $this->previous_state = null;
                    $this->state = self::STATE_INIT;
                }

                // Total Pot($0.90)
                $REGEX_TOTAL = '/^Total Pot\(\$(?<chips>[0-9.]+)\)$/';
                if (empty($this->hand->summary['pot']) && preg_match($REGEX_TOTAL, $this->line, $this->hand->summary['pot']))
                    break;

                // Board [8d 4h Ah Th 6s]
                $REGEX_BOARD = '/^Board \[([2-9TJQKA][cdhs]) ([2-9TJQKA][cdhs]) ([2-9TJQKA][cdhs]) ([2-9TJQKA][cdhs])? ([2-9TJQKA][cdhs])?\]$/';
                if (empty($this->hand->summary['board']) && preg_match($REGEX_BOARD, $this->line, $this->hand->summary['board']))
                    break;

                // Seat+1: Dealer Folded before the FLOP
                $REGEX_FOLDED = '/^Seat\+(?<seatno>\d+): (?<player>.*?) (?<action_full>(?<action>Folded) (?<street>(on|before) the (FLOP|TURN|RIVER)))$/';
                if (preg_match($REGEX_FOLDED, $this->line, $this->hand->summary['seats'][]))
                    break;

                // Seat+4: Small Blind Mucked [2d 3s]
                $REGEX_MUCKED = '/^Seat\+(?<seatno>\d+): (?<player>.*?) \[(?<action>Mucked)\] \[(?<card1>[2-9TJQKA][cdhs]) (?<card2>[2-9TJQKA][cdhs])\]$/';
                if (preg_match($REGEX_MUCKED, $this->line, $this->hand->summary['seats'][]))
                    break;

                // Seat+5: Big Blind $0.86 [Does not show]  
                $REGEX_WON = '/^Seat\+(?<seatno>\d+): (?<player>.*?) \$(?<chips>[0-9.]+) (\[Does not show\]| with (?<ranking>.*) \[(?<hand>[^[]*)\])$/';
                if (preg_match($REGEX_WON, $this->line, $this->hand->summary['seats'][]))
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

