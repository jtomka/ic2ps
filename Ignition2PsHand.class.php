<?php

/**
 * Ignition Casino to Poker Stars conversion
 */
class Ignition2PsHand
{
    protected $tr_game = array(
        'HOLDEM' => "Hold'em",
    );

    protected $tr_limit = array(
        'No Limit' => "No Limit",
    );

    const STREET_PREFLOP = 'preflop';
    const STREET_FLOP = 'flop';
    const STREET_TURN = 'turn';
    const STREET_RIVER = 'river';
 
    // Ignition to Poker Stars hand summary translation
    private $tr_summary_action = array(
        'Folded before the FLOP' => 'folded before Flop',
        'Folded on the FLOP' => 'folded on the Flop',
        'Folded on the TURN' => 'folded on the Turn',
        'Folded on the RIVER' => 'folded on the River',
    );

    // Hand's parent file
    protected $file;
 
    // PokerStars format representation of hand
    protected $ps_format;

    // All hand action across all streets
    private $all_action;

    // Player name to seat record association, mainly for hole cards
    private $player_to_seat;

    public function __construct(IgnitionHhFile $file)
    {
        $this->file = $file;
    }

    public function getTimestamp()
    {
        return $this->info['timestamp'];
    }

    public function getPsAccountId()
    {
        return $this->file->getPsAccountId();
    }

    public function getPsTableName()
    {
        return $this->file->getPsTableName($this->info['table']);
    }

    public function getPsHandId()
    {
        return $this->file->getPsHandId($this->info['id']);
    }

    public function getFileSb()
    {
        return $this->file->getSb();
    }

    public function getFileBb()
    {
        return $this->file->getBb();
    }

    public function getSb()
    {
        if (isset($this->posts['sb']['chips']))
            $sb = $this->posts['sb']['chips'];
        else
            $sb = $this->getFileSb();

        return $sb;
    }

    public function getBb()
    {
        if (isset($this->posts['bb']['chips']))
            $bb = $this->posts['bb']['chips'];
        else
            $bb = $this->getFileBb();

        return $bb;
    }

    public function getPsLimit()
    {
        return $this->tr_limit[$this->info['limit']];
    }

    public function getPsGame()
    {
        return $this->tr_game[$this->info['game']];
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
        $tr_blind = array(
            'Small Blind' => 'small blind',
            'Big blind' => 'big blind',
        );

        $time_format = '%Y/%m/%d %H:%M:%S';
        $out = '';

        $this->info['timestamp'] = mktime($this->info['hour'], $this->info['minute'], $this->info['second'],
                            $this->info['month'], $this->info['day'], $this->info['year']);
        $aest_time = strftime($time_format, $this->info['timestamp']);
        $et_time = strftime($time_format, $this->info['timestamp'] - (15 * 60 * 60));

        $out .= sprintf("PokerStars Hand #%d:  %s %s ($%.02f/$%.02f USD) - %s AEST [%s ET]\n", $this->getPsHandId(), $this->getPsGame(), $this->getPsLimit(), $this->getFileSb(), $this->getFileBb(), $aest_time, $et_time);
        
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
        $str_table_size = '6-max'; // table size missing in ignition (or RING?)

        $out = '';

        $button_seat = '';
        if ($dealer_seatno = $this->getDealerSeatno())
            $button_seat = sprintf(" Seat #%d is the button", $dealer_seatno);
        $out .= sprintf("Table '%s' %s%s\n", $this->getPsTableName(), $str_table_size, $button_seat);

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
        $me = $this->getMePlayer();
        if ($player == $me['player'])
            $player = $this->getPsAccountId();

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

    public function getPsFormat()
    {
        return $this->ps_format;
    }

    /**
     * Use collected Ignition Casino hand data to generate Poker Stars hand text
     */
    public function convertToPsFormat()
    {
        if (isset($this->ps_format))
            return $this;

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

        $out .= "\n";

        $this->ps_format = $out;

        return $this;
    }
}


