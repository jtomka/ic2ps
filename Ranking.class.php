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

    private static $ranking_strings = array(
        self::HIGH_CARD => 'High Card',
        self::PAIR => 'a Pair',
        self::TWO_PAIR => 'Two Pair',
        self::TRIPS => 'Three of a Kind',
        self::STRAIGHT => 'Straight',
        self::FLUSH => 'Flush',
        self::FULL_HOUSE => 'Full House',
        self::QUADS => 'Four of a Kind',
        self::STRAIGHT_FLUSH => 'Straight Flush',
    );

    public static function handRankStr($hand_rank)
    {
        if (! isset(self::$ranking_strings[$hand_rank]))
            throw new InvalidArgumentException(sprintf("Invalid hand rank (%s)", $hand_rank));

        return self::$ranking_strings[$hand_rank];
    }

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

    private static $RANK_CHAR_MAP = '0A23456789TJQKA';

    private static $RANK_STR_MAP = array(
        self::LOW_ACE => 'Ace',
        self::DEUCE => 'Deuce',
        self::TREY => 'Trey',
        self::FOUR => 'Four',
        self::FIVE => 'Five',
        self::SIX => 'Six',
        self::SEVEN => 'Seven',
        self::EIGHT => 'Eight',
        self::NINE => 'Nine',
        self::TEN => 'Ten',
        self::JACK => 'Jack',
        self::QUEEN => 'Queen',
        self::KING => 'King',
        self::ACE => 'Ace',
    );

    private static $SCORE_CELL_SHIFT = 16; // 4 bits

    private static $HAND_RANK_SCORE_MAP = array(
        self::STRAIGHT_FLUSH => 8,
        self::QUADS => 7,
        self::FULL_HOUSE => 6,
        self::FLUSH => 5,
        self::STRAIGHT => 4,
        self::TRIPS => 3,
        self::TWO_PAIR => 2,
        self::PAIR => 1,
        self::HIGH_CARD => 0,
    );

    private $rank; // all
    private $high_card; // high, straight, flush, quads
    private $low_pair; // twopair
    private $kickers = array(); // high, trips, flush, quads
    private $score;

    private $hand;

    private $cards_by_rank;
    private $cards_by_suite;

    private function __construct($board, $hand)
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

            $rank = $this::$card_rank[$card[0]];
            $suite = $card[1];

            $this->cards_by_rank[(string) $rank][] = $suite;
            $this->cards_by_suite[$suite][] = $rank;
        }
        krsort($this->cards_by_rank);
        foreach ($this->cards_by_suite as $suite => $ranks)
            rsort($this->cards_by_suite[$suite]);

        $this->hand = $hand;
    }

    public static function validateCardRank($card_rank) 
    {
        if (! is_int($card_rank) || $card_rank < 1 || $card_rank >= strlen(self::$RANK_CHAR_MAP))
            throw new InvalidArgumentException(sprintf("Invalid card rank (%d)", $card_rank));
    }

    public static function cardRankCh($card_rank)
    {
        self::validateCardRank($card_rank);

        return self::$RANK_CHAR_MAP[$card_rank];
    }

    public static function cardRankStr($card_rank)
    {
        self::validateCardRank($card_rank);

        return self::$RANK_STR_MAP[$card_rank];
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
        if (! is_null($this->score))
            return $this->score;

        $score_hash = array();

        $score_hash[] = $this::$HAND_RANK_SCORE_MAP[$this->getRank()];
        $score_hash[] = $this->getHighCard();
        $score_hash[] = (int) $this->getLowCard();

        $kickers = $this->getKickers();
        $score_hash[] = isset($kickers[0]) ? $kickers[0] : 0;
        $score_hash[] = isset($kickers[1]) ? $kickers[1] : 0;
        
        $score = 0;
        foreach ($score_hash as $cell)
            $score = ($score * $this::$SCORE_CELL_SHIFT) + $cell;

        $this->score = $score;

        return $score;
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

    private function checkStraightFlush()
    {
        if ($this->getRank() == self::STRAIGHT_FLUSH)
            return true;

        $this->checkStraight();

        return ($this->getRank() == self::STRAIGHT_FLUSH);
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
        $this->setLowCard(null);

        unset($cards[$quads]);
        $this->setKickers(array(array_keys($cards)[0]));

        return true;
    }

    private function checkFullHouse()
    {
        if ($this->getRank() == self::FULL_HOUSE)
            return true;

        if (! $this->checkTrips())
            return false;
           
        $save_high = $this->getHighCard();

        if (! $this->checkPair())
            return false;

        $save_low = $this->getHighCard();

        $this->setRank(self::FULL_HOUSE);
        $this->setHighCard($save_high);
        $this->setLowCard($save_low);
        $this->setKickers(array());

        return true;
    }

    private function getHigherHandRankBySuite($rank_low, $flush_suite)
    {
        $kickers = array();

        foreach ($this->hand as $card) {
            $rank = $this::$card_rank[(string) $card[0]];
            $suite = $card[1];

            if ($suite == $flush_suite && $rank >= $rank_low) {
                if (! isset($kickers[0]) || $kickers[0] < $rank)
                    $kickers[0] = $rank;
            }
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
        $this->setLowCard(null);
        $this->setKickers($this->getHigherHandRankBySuite($flush[4], $suite));

        return true;
    }

    private function checkStraight()
    {
        if (in_array($this->getRank(), array(self::STRAIGHT_FLUSH, self::STRAIGHT)))
            return true;

        $straight = false;
        $straight_flush = false;
        $cards = $this->getCardsByRank();
        if (isset($cards[self::ACE]))
            $cards[self::LOW_ACE] = $cards[self::ACE];

        for ($i = 0; $i < count($cards) - 5; $i++) {
            $five_ranks = array_slice(array_keys($cards), $i, 5);
            if ($five_ranks[0] - $five_ranks[4] == 4) {
                $top_card = $five_ranks[0];

                if (! $straight) // save highest straight
                    $straight = $top_card;

                // keep looking for straight flush
                $suite_counter = array();
                foreach (array_slice($cards, $i, 5, true) as $rank_suites) {
                    if ($straight_flush)
                        break;

                    foreach ($rank_suites as $suite) {
                        if (! isset($suite_counter[$suite]))
                            $suite_counter[$suite] = 0;

                        $suite_counter[$suite]++;

                        if ($suite_counter[$suite] == 5) {
                            $straight_flush =  $top_card;
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

        $this->setLowCard(null);
        $this->setKickers(array());

        return true;
    }

    private function getHigherHandRanks($rank_low, $exclude)
    {
        if (! is_array($exclude))
            $exclude = array($exclude);

        $kickers = array();

        foreach ($this->hand as $card) {
            $rank = $this::$card_rank[(string) $card[0]];

            if ($rank >= $rank_low && ! in_array($rank, $exclude))
                $kickers[] = $rank;
        }

        return $kickers;
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
        $this->setLowCard(null);

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

        $this->setKickers($this->getHigherHandRanks(0, array($high_pair, $low_pair)));

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
        $this->setLowCard(null);

        unset($cards[$pair]);
        $this->setKickers($this->getHigherHandRanks(array_keys($cards)[2], $pair));

        return true;
    }

    private function checkHighCard()
    {
        if ($this->getRank() == self::HIGH_CARD)
            return true;

        $cards = $this->getCardsByRank();
        $high_card = array_keys($cards)[0];

        $this->setRank(self::HIGH_CARD);
        $this->setHighCard($high_card);
        $this->setLowCard(null);

        unset($cards[0]);
        $this->setKickers($this->getHigherHandRanks(array_keys($cards)[3], $high_card));

        return true;
    }

    public static function compare($hand1, $hand2, $board)
    {
        $score1 = self::calculate($hand1, $board)->getScore();
        $score2 = self::calculate($hand2, $board)->getScore();

        if (is_string($hand1))
            $hand1 = explode(' ', $hand1);

        if (is_string($hand2))
            $hand2 = explode(' ', $hand2);

        $both_hands = array_merge($hand1, $hand2);
        if (count(array_unique($both_hands)) != count($both_hands))
            throw new InvalidArgumentException(sprintf("Duplicate cards in hands %s",
                implode(' ', $both_hands)));

        return $score2 - $score1;
    }

    private function calculateReal()
    {
        $methods_in_order = array(
            'checkStraightFlush',
            'checkQuads',
            'checkFullHouse',
            'checkFlush',
            'checkStraight',
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

        return $this;
    }

    public static function calculate($hand, $board)
    {
        $ranking = new Ranking($board, $hand);

        return $ranking->calculateReal();
    }
}

