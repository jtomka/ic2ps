<?php

class Ranking extends Base
{
    const HIGH_CARD = 'high_card';
    const PAIR = 'pair';
    const TWO_PAIR = 'two_pair';
    const TRIPS = 'trips';
    const STRAIGHT = 'straight';
    const FLUSH = 'flush';
    const FULL_HOUSE = 'full_house';
    const QUADS = 'quads';
    const STRAIGHT_FLUSH = 'straight_flush';

    const LOW_ACE = 1;
    const DEUCE = 2;
    const TREY = 3;
    const FOUR = 4;
    const FIVE = 5;
    const SIX = 6;
    const SEVEN = 7;
    const EIGHT = 8;
    const NINE = 9;
    const TEN = 10;
    const JACK = 11;
    const QUEEN = 12;
    const KING = 13;
    const ACE = 14;

    private static $card_rank = array(
        '2' => self::DEUCE,
        '3' => self::TREY,
        '4' => self::FOUR,
        '5' => self::FIVE,
        '6' => self::SIX,
        '7' => self::SEVEN,
        '8' => self::EIGHT,
        '9' => self::NINE,
        'T' => self::TEN,
        'J' => self::JACK,
        'Q' => self::QUEEN,
        'K' => self::KING,
        'A' => self::ACE,
    );

    private static $card_rank_rev = '.A23456789TJQKA';

    private $rank; // all
    private $high_card; // high, straight, flush, quads
    private $low_pair; // twopair
    private $kickers = array(); // high, trips, flush, quads
    private $score;

    private $hand;

    private $cards_by_rank;
    private $cards_by_suite;

    public function __construct($board, $hand)
    {
        if (is_string($board))
            $board = explode(' ', $board);

        if (is_string($hand))
            $hand = explode(' ', $hand);

        Street::validateCardCount(null, count($board));

        if (count($hand) != 2)
            throw new InvalidArgumentException(sprintf("Invalid nuber of hand cards (%d)", count($hand)));

        $all_cards = array_merge($board, $hand);
        if (count(array_unique($all_cards)) != count($all_cards))
            throw new InvalidArgumentException(sprintf("Duplicate cards in %s", implode(' ', $all_cards)));

        foreach ($all_cards as $card) {
            Hand::validateCard($card);

            $rank = $this::$card_rank[(string) $card[0]];
            $suite = $card[1];

            $this->cards_by_rank[$rank][] = $suite;
            $this->cards_by_suite[$suite][] = $rank;
        }
        krsort($this->cards_by_rank);
        foreach ($this->cards_by_suite as $suite => $ranks)
            rsort($this->cards_by_suite[$suite]);

        $this->hand = $hand;
    }

    private function setRank($rank) 
    {
        $this->rank = $rank;
    }

    public function getRank()
    {
        return $this->rank;
    }

    private function setLowCard($low_card)
    {
        $this->low_card = $low_card;
    }

    public function getLowCard()
    {
        return $this->low_card;
    }

    private function setHighCard($high_card)
    {
        $this->high_card = $high_card;
    }

    public function getHighCard()
    {
        return $this->high_card;
    }

    private function setKickers($kickers)
    {
        $this->kickers = $kickers;
    }

    public function getKickers()
    {
        return $this->kickers;
    }

    private function setScore($score)
    {
        $this->score = $score;
    }

    public function getScore()
    {
        $this->score();
    }

    private function setCardsByRank($cards_by_rank)
    {
        $this->cards_by_rank = $cards_by_rank;
    }

    private function getCardsByRank()
    {
        return $this->cards_by_rank;
    }

    private function setCardsBySuite($cards_by_suite)
    {
        $this->cards_by_suite = $cards_by_suite;
    }

    private function getCardsBySuite()
    {
        return $this->cards_by_suite;
    }

    private function checkQuads()
    {
        if ($this->getRank() == self::QUADS)
            return true;

        $quads = false;
        $cards = $this->getCardsByRank();
        foreach ($cards as $rank => $suites) {
            if (count($cards[$rank]) == 4) {
                $quads = $rank;
                break;
            }
        }

        if (! $quads)
            return false;

        $this->setRank(self::QUADS);

        $this->setHighCard($quads);
        unset($cards[$quads]);

        $this->setKickers(array(array_keys($cards)[0]));

        return true;
    }

    private function checkFullHouse()
    {
        if ($this->getRank() == self::FULL_HOUSE)
            return true;

        if ($this->checkTrips() && $this->checkPair()) {
            $this->setRank(self::FULL_HOUSE);
            $this->setKickers(array());

            return true;
        }

        return false;
    }

    private function getHigherHandRanks($rank_low, $exclude)
    {
        if (! is_array($exclude))
            $exclude = array($exclude);

        $kickers = array();

        foreach ($this->hand as $card) {
            $rank = $this::$card_rank[(string) $card[0]];
            $suite = $card[1];

            if ($rank >= $rank_low && ! in_array($rank, $exclude))
                $kickers[] = $rank;
        }

        return $kickers;
    }

    private function getHigherHandRanksBySuite($rank_low, $flush_suite)
    {
        $kickers = array();

        foreach ($this->hand as $card) {
            $rank = $this::$card_rank[(string) $card[0]];
            $suite = $card[1];

            if ($suite == $flush_suite && $rank >= $rank_low)
                $kickers[] = $rank;
        }

        return $kickers;
    }

    private function checkFlush()
    {
        if ($this->rank == self::FLUSH)
            return true;

        $flush = false;
        $cards = $this->getCardsBySuite();
        foreach ($cards as $suite => $ranks) {
            if (count($ranks) >= 5) {
                $flush = $ranks;
                break;
            }
        }

        if (! $flush)
            return false;

        $this->setRank(self::FLUSH);
        $this->setHighCard($flush[0]);
        $this->setKickers($this->getHigherHandRanksBySuite($flush[4], $suite));

        return true;
    }

    private function checkStraight($required_high_card = null)
    {
        if ($this->getRank() == self::STRAIGHT_FLUSH || $this->getRank() == self::STRAIGHT)
            return true;

        $straight = false;
        $straight_flush = false;
        $cards = $this->getCardsByRank();
        if (isset($cards[self::ACE]))
            $cards[self::LOW_ACE] = $cards[self::ACE];

        for ($i = 0; $i < count($cards) - 5; $i++) {
            $key_slice = array_slice(array_keys($cards), $i, 5);
            if ($key_slice[0] - $key_slice[4] == 4) {
                if (! $straight) // save highest straight
                    $straight = $key_slice[0];

                // keep looking for straight flush
                $suite_counter = array();
                foreach (array_slice($cards, $i, 5) as $suites) {
                    if ($straight_flush)
                        break;

                    foreach ($suites as $suite) {
                        if (! isset($suite_counter[$suite]))
                            $suite_counter[$suite] = 0;

                        $suite_counter[$suite]++;

                        if ($suite_counter[$suite] == 5) {
                            $straight_flush = $key_slice[0];
                            break;
                        }
                    }
                }
            }
        }

        if (! $straight)
            return false;

        if ($straight_flush) {
            $this->setRank(self::STRAIGHT_FLUSH);
            $this->setHighCard($straight_flush);
        } else {
            $this->setRank(self::STRAIGHT);
            $this->setHighCard($straight);
        }

        $this->setKickers(array());

        return true;
    }

    private function checkTrips()
    {
        if ($this->getRank() == self::TRIPS)
            return true;

        $trips = false;
        $cards = $this->getCardsByRank();
        foreach ($cards as $rank => $suites) {
            if (count($cards[$rank]) == 3) {
                $trips = $rank;
                break;
            }
        }

        if (! $trips)
            return false;

        $this->setRank(self::TRIPS);

        $this->setHighCard($trips);
        unset($cards[$trips]);
                          
        $this->setKickers($this->getHigherHandRanks(array_reverse(array_keys($cards))[1], $trips));

        return true;
    }

    private function checkTwoPair()
    {
        if ($this->getRank() == self::TWO_PAIR)
            return true;

        if (! $this->checkPair())
            return false;

        $this->setRank(null);

        $cards = $this->getCardsByRank();
        $high_pair = $this->getHighCard();
        $saved_cards = $cards;
        unset($cards[$high_pair]);
        $this->setCardsByRank($cards);

        if (! $this->checkPair()) {
            $this->setRank(self::PAIR);
        $this->setCardsByRank($saved_cards);
            return false;
        }

        $this->setRank(self::TWO_PAIR);
        $low_pair = $this->getHighCard();
        $this->setHighCard($high_pair);
        $this->setLowCard($low_pair);

        $this->setKickers(array($this->getKickers()[0]));

        return true;
    }

    private function checkPair()
    {
        if ($this->getRank() == self::PAIR)
            return true;

        $pair = false;
        $cards = $this->getCardsByRank();
        foreach ($cards as $rank => $suites) {
            if (count($cards[$rank]) == 2) {
                $pair = $rank;
                break;
            }
        }

        if (! $pair)
            return false;

        $this->setRank(self::PAIR);

        $this->setHighCard($pair);
        unset($cards[$pair]);

        $this->setKickers(array_slice(array_keys($cards), 0, 3));

        return true;
    }

    private function checkHighCard()
    {
        if ($this->getRank() == self::HIGH_CARD)
            return true;

        $this->setRank(self::HIGH_CARD);
        $cards = $this->getCardsByRank();
        $high_card = array_keys($cards)[0];
        $this->setHighCard($high_card);
        $this->setKickers(array_slice(array_keys($cards), 1, 4));
        unset($cards[0]);
        $this->setKickers($this->getHigherHandRanks(array_keys($cards)[3], $high_card));

        return true;
    }

    private function calculateScore()
    {
        $ranks = array(
            self::STRAIGHT_FLUSH,
            self::QUADS,
            self::FULL_HOUSE,
            self::FLUSH,
            self::STRAIGHT,
            self::TRIPS,
            self::TWO_PAIR,
            self::PAIR,
            self::HIGH_CARD,
        );

        $rank = $this->getRank();
    }

    private function calculateReal()
    {
        $methods_in_order = array(
            'checkStraight',
            'checkQuads',
            'checkFullHouse',
            'checkFlush',
            'checkTrips',
            'checkTwoPair',
            'checkPair',
            'checkHighCard'
        );

        foreach ($methods_in_order as $method_name) {
            $result = call_user_func(array($this, $method_name));
            if ($result)
                break;
        }

        $this->calculateScore();

        return $this;
    }

    public static function calculate($hand, $board)
    {
        $ranking = new Ranking($board, $hand);

        return $ranking->calculateReal();
    }
}

